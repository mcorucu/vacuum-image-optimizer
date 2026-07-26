<?php
/**
 * Generates derivatives for registered WordPress image sizes.
 *
 * @package VacuumImageOptimizer\Frontend
 */

namespace VacuumImageOptimizer\Frontend;

use VacuumImageOptimizer\Settings\CompressionSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Creates only missing, smaller-than-source modern derivatives.
 */
class ResponsiveDerivativeGenerator {

	/**
	 * Generate derivatives for the full source and registered sizes.
	 *
	 * @param int                 $attachment_id Attachment ID.
	 * @param array<string,mixed> $metadata Attachment metadata.
	 * @return array<string,int>
	 */
	public function generate( int $attachment_id, array $metadata = [] ): array {
		if ( ! CompressionSettings::should_generate_registered_derivatives() ) {
			return [ 'webp' => 0, 'avif' => 0, 'skipped' => 1, 'failed' => 0 ];
		}
		$source_path = get_attached_file( $attachment_id );
		if ( ! is_string( $source_path ) || ! is_readable( $source_path ) ) {
			return [ 'webp' => 0, 'avif' => 0, 'skipped' => 1, 'failed' => 0 ];
		}

		$metadata = ! empty( $metadata ) ? $metadata : (array) wp_get_attachment_metadata( $attachment_id );
		$sources  = [ 'full' => wp_normalize_path( $source_path ) ];
		$base_dir = wp_normalize_path( dirname( $source_path ) );
		foreach ( (array) ( $metadata['sizes'] ?? [] ) as $size ) {
			$file = is_array( $size ) ? sanitize_file_name( (string) ( $size['file'] ?? '' ) ) : '';
			if ( '' !== $file ) {
				$sources[ $file ] = trailingslashit( $base_dir ) . $file;
			}
		}

		$counts = [ 'webp' => 0, 'avif' => 0, 'skipped' => 0, 'failed' => 0 ];
		foreach ( $sources as $path ) {
			if ( ! is_readable( $path ) ) {
				++$counts['skipped'];
				continue;
			}
			foreach ( [ 'webp', 'avif' ] as $format ) {
				if ( ( 'webp' === $format && ! CompressionSettings::is_webp_enabled() ) || ( 'avif' === $format && ! CompressionSettings::is_avif_enabled() ) ) {
					continue;
				}
				$result = $this->generate_one( $path, $format, $attachment_id );
				if ( 'generated' === $result ) {
					++$counts[ $format ];
				} elseif ( 'skipped' === $result ) {
					++$counts['skipped'];
				} else {
					++$counts['failed'];
				}
			}
		}

		update_post_meta( $attachment_id, '_vacimg_delivery_state', $counts['failed'] > 0 ? 'partial' : 'ready' );
		update_post_meta( $attachment_id, '_vacimg_delivery_generated_at', current_time( 'mysql' ) );
		do_action( 'vacimg_derivatives_updated', $attachment_id, $counts );
		return $counts;
	}

	private function generate_one( string $source_path, string $format, int $attachment_id ): string {
		$source_mime = wp_get_image_mime( $source_path );
		if ( ! in_array( $source_mime, [ 'image/jpeg', 'image/png', 'image/webp' ], true ) ) {
			return 'skipped';
		}
		if ( 'image/webp' === $source_mime && 'webp' === $format ) {
			// A WebP source already is the preferred WebP delivery candidate.
			DerivativeResolver::record( $attachment_id, $source_path, $format, $source_path );
			return 'skipped';
		}
		$output = trailingslashit( dirname( $source_path ) ) . pathinfo( $source_path, PATHINFO_FILENAME ) . '.' . $format;
		if ( is_readable( $output ) ) {
			if ( DerivativeResolver::is_valid_derivative( $attachment_id, $source_path, $format, $output ) ) {
				DerivativeResolver::record( $attachment_id, $source_path, $format, $output );
				return 'skipped';
			}
			// The expected plugin-owned output is stale; replace only that file.
			wp_delete_file( $output );
		}
		$editor = wp_get_image_editor( $source_path );
		if ( is_wp_error( $editor ) ) {
			return 'failed';
		}
		$editor->set_quality( 'webp' === $format ? CompressionSettings::get_quality() : CompressionSettings::get_avif_quality() );
		$saved = $editor->save( $output, 'image/' . $format );
		if ( is_wp_error( $saved ) || ! is_readable( $output ) ) {
			return 'failed';
		}
		$source_size = filesize( $source_path );
		$output_size = filesize( $output );
		if ( false === $source_size || false === $output_size || $output_size > $source_size || ! is_array( @getimagesize( $output ) ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			wp_delete_file( $output );
			return 'skipped';
		}
		DerivativeResolver::record( $attachment_id, $source_path, $format, $output );
		return 'generated';
	}
}
