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
		$sources  = [];
		$this->add_source( $sources, wp_normalize_path( $source_path ), $metadata['width'] ?? 0, $metadata['height'] ?? 0 );
		$base_dir = wp_normalize_path( dirname( $source_path ) );
		foreach ( (array) ( $metadata['sizes'] ?? [] ) as $size ) {
			$file = is_array( $size ) ? sanitize_file_name( (string) ( $size['file'] ?? '' ) ) : '';
			if ( '' !== $file ) {
				$this->add_source( $sources, trailingslashit( $base_dir ) . $file, $size['width'] ?? 0, $size['height'] ?? 0 );
			}
		}

		$counts = [ 'webp' => 0, 'avif' => 0, 'skipped' => 0, 'failed' => 0 ];
		foreach ( $sources as $source ) {
			$path = (string) $source['path'];
			if ( ! $this->is_owned_physical_source( $path, $source['width'], $source['height'] ) ) {
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

		$delivery_state = $counts['failed'] > 0 ? 'partial' : 'ready';
		$previous_state  = sanitize_key( (string) get_post_meta( $attachment_id, '_vacimg_delivery_state', true ) );
		update_post_meta( $attachment_id, '_vacimg_delivery_state', $delivery_state );
		if ( $counts['webp'] > 0 || $counts['avif'] > 0 || $previous_state !== $delivery_state ) {
			update_post_meta( $attachment_id, '_vacimg_delivery_generated_at', current_time( 'mysql' ) );
		}
		do_action( 'vacimg_derivatives_updated', $attachment_id, $counts );
		return $counts;
	}

	/**
	 * Add a source only when it resolves inside this site's uploads directory.
	 *
	 * @param array<string, array{path:string,width:int,height:int}> $sources Sources by path.
	 * @param string                                                     $path Physical file path.
	 * @param mixed                                                      $width Expected width.
	 * @param mixed                                                      $height Expected height.
	 * @return void
	 */
	private function add_source( array &$sources, string $path, $width, $height ): void {
		$path = wp_normalize_path( $path );
		if ( '' === DerivativeResolver::relative_path( $path ) ) {
			return;
		}

		$sources[ $path ] = [
			'path'   => $path,
			'width'  => absint( $width ),
			'height' => absint( $height ),
		];
	}

	/**
	 * Validate that a metadata-listed file belongs to this attachment's upload area.
	 *
	 * @param string $path Expected physical file path.
	 * @param int    $width Expected width, when supplied.
	 * @param int    $height Expected height, when supplied.
	 * @return bool
	 */
	private function is_owned_physical_source( string $path, int $width, int $height ): bool {
		if ( ! is_file( $path ) || ! is_readable( $path ) || '' === DerivativeResolver::relative_path( $path ) ) {
			return false;
		}

		$mime = wp_get_image_mime( $path );
		if ( ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/webp' ], true ) ) {
			return false;
		}

		$dimensions = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $dimensions ) ) {
			return false;
		}

		return ( 0 === $width || absint( $dimensions[0] ?? 0 ) === $width )
			&& ( 0 === $height || absint( $dimensions[1] ?? 0 ) === $height );
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

		$output    = trailingslashit( dirname( $source_path ) ) . pathinfo( $source_path, PATHINFO_FILENAME ) . '.' . $format;
		$lock_path = $output . '.vacimg-lock';
		// Keep the lock inode in place. Unlinking it after flock() can let another process lock a new inode concurrently.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged -- flock() requires a native file handle for an atomic lock.
		$lock = @fopen( $lock_path, 'c' );
		if ( false === $lock ) {
			return 'failed';
		}

		$temp_path = '';
		try {
			if ( ! flock( $lock, LOCK_EX ) ) {
				return 'failed';
			}

			$inspection  = DerivativeResolver::inspect( $attachment_id, $source_path, $format );
			$tracked_path = wp_normalize_path( (string) ( $inspection['entry']['path'] ?? '' ) );
			if ( 'ready' === $inspection['status'] && $tracked_path === wp_normalize_path( $output ) ) {
				return 'skipped';
			}

			if ( is_file( $output ) ) {
				// Do not claim or replace an unrelated pre-existing file. Only regenerate a file the registry
				// already identifies as this attachment's stale derivative.
				if ( 'stale' !== $inspection['status'] || $tracked_path !== wp_normalize_path( $output ) ) {
					return 'skipped';
				}
			}

			$temp_path = trailingslashit( dirname( $output ) ) . '.vacimg-' . wp_generate_password( 20, false, false ) . '.' . $format;
			if ( file_exists( $temp_path ) ) {
				return 'failed';
			}

			$editor = wp_get_image_editor( $source_path );
			if ( is_wp_error( $editor ) ) {
				return 'failed';
			}
			$editor->set_quality( 'webp' === $format ? CompressionSettings::get_quality() : CompressionSettings::get_avif_quality() );
			$saved = $editor->save( $temp_path, 'image/' . $format );
			if ( is_wp_error( $saved ) || ! is_array( $saved ) || wp_normalize_path( (string) ( $saved['path'] ?? '' ) ) !== wp_normalize_path( $temp_path ) || ! is_readable( $temp_path ) ) {
				return 'failed';
			}

			$source_size = filesize( $source_path );
			$output_size = filesize( $temp_path );
			$output_info = @getimagesize( $temp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false === $source_size || false === $output_size || $output_size > $source_size || ! is_array( $output_info ) || 'image/' . $format !== wp_get_image_mime( $temp_path ) ) {
				return 'skipped';
			}

			// WP_Filesystem::move(..., true) deletes an existing destination first; native same-directory rename atomically replaces this tracked derivative.
			// phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors.Discouraged -- Keep the previous complete derivative until the replacement is ready.
			if ( ! @rename( $temp_path, $output ) ) {
				return 'failed';
			}
			$temp_path = '';
			clearstatcache( true, $output );
			DerivativeResolver::record( $attachment_id, $source_path, $format, $output );
			return 'generated';
		} finally {
			if ( '' !== $temp_path && is_file( $temp_path ) ) {
				wp_delete_file( $temp_path );
			}
			if ( is_resource( $lock ) ) {
				flock( $lock, LOCK_UN );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closes the native handle used by flock().
				fclose( $lock );
			}
		}
	}
}
