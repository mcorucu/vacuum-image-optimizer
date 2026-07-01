<?php
/**
 * Image format and eligibility helpers.
 *
 * @package VacuumImageOptimizer\Utils
 */

namespace VacuumImageOptimizer\Utils;

use VacuumImageOptimizer\Settings\CompressionSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Centralizes source-image detection so queue, upload, and manual actions agree.
 */
class ImageFormat {

	/**
	 * Source formats that Vacuum can safely process in 1.0.1.
	 *
	 * @var array<int, string>
	 */
	private const SUPPORTED_SOURCE_MIMES = [
		'image/jpeg',
		'image/png',
		'image/webp',
	];

	/**
	 * Resolve an attachment's real MIME type using WordPress and file inspection.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public static function get_attachment_mime_type( int $attachment_id ): string {
		$file_path = get_attached_file( $attachment_id );
		$file_path = is_string( $file_path ) ? wp_normalize_path( $file_path ) : '';
		$mime_type = (string) get_post_mime_type( $attachment_id );

		if ( '' !== $file_path && file_exists( $file_path ) ) {
			$checked = wp_check_filetype_and_ext( $file_path, basename( $file_path ) );
			if ( ! empty( $checked['type'] ) ) {
				$mime_type = (string) $checked['type'];
			}

			if ( function_exists( 'getimagesize' ) ) {
				$size = @getimagesize( $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( is_array( $size ) && ! empty( $size['mime'] ) ) {
					$mime_type = (string) $size['mime'];
				}
			}
		}

		return sanitize_mime_type( $mime_type );
	}

	/**
	 * Get supported source MIME types.
	 *
	 * @return array<int, string>
	 */
	public static function get_supported_source_mime_types(): array {
		return self::SUPPORTED_SOURCE_MIMES;
	}

	/**
	 * Check whether an attachment is an eligible source image.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_supported_source_attachment( int $attachment_id ): bool {
		if ( 0 === absint( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}

		$file_path = get_attached_file( $attachment_id );
		$file_path = is_string( $file_path ) ? wp_normalize_path( $file_path ) : '';
		$mime_type = self::get_attachment_mime_type( $attachment_id );

		if ( CompressionSettings::is_mime_excluded( $mime_type ) || ! in_array( $mime_type, self::SUPPORTED_SOURCE_MIMES, true ) ) {
			return false;
		}

		if ( '' === $file_path || ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		if ( self::is_excluded_by_size( $file_path ) || self::is_excluded_by_pattern( $file_path ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check whether a MIME type represents a WebP source.
	 *
	 * @param string $mime_type MIME type.
	 * @return bool
	 */
	public static function is_webp_mime( string $mime_type ): bool {
		return 'image/webp' === sanitize_mime_type( $mime_type );
	}

	/**
	 * Check whether a GIF is animated.
	 *
	 * @param string $file_path Absolute path.
	 * @return bool
	 */
	public static function is_animated_gif( string $file_path ): bool {
		if ( '' === $file_path || ! is_readable( $file_path ) ) {
			return false;
		}

		$handle = fopen( $file_path, 'rb' );
		if ( false === $handle ) {
			return false;
		}

		$count = 0;
		while ( ! feof( $handle ) && $count < 2 ) {
			$chunk = fread( $handle, 1024 * 100 );
			if ( ! is_string( $chunk ) ) {
				break;
			}

			$count += preg_match_all( '#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $chunk );
		}

		fclose( $handle );

		return $count > 1;
	}

	/**
	 * Determine a clear unsupported/skipped reason for UI display.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public static function get_skip_reason( int $attachment_id ): string {
		$file_path = get_attached_file( $attachment_id );
		$file_path = is_string( $file_path ) ? wp_normalize_path( $file_path ) : '';
		$mime_type = self::get_attachment_mime_type( $attachment_id );

		if ( 'image/svg+xml' === $mime_type ) {
			return __( 'SVG files are skipped for safety.', 'vacuum-image-optimizer' );
		}

		if ( 'image/gif' === $mime_type ) {
			return self::is_animated_gif( $file_path )
				? __( 'Animated GIF files are skipped.', 'vacuum-image-optimizer' )
				: __( 'GIF files are skipped unless safe support is available.', 'vacuum-image-optimizer' );
		}

		if ( CompressionSettings::is_mime_excluded( $mime_type ) ) {
			return __( 'This MIME type is excluded by settings.', 'vacuum-image-optimizer' );
		}

		if ( ! in_array( $mime_type, self::SUPPORTED_SOURCE_MIMES, true ) ) {
			return __( 'This image format is not supported.', 'vacuum-image-optimizer' );
		}

		if ( '' === $file_path || ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return __( 'The source file is missing or unreadable.', 'vacuum-image-optimizer' );
		}

		if ( self::is_excluded_by_size( $file_path ) ) {
			return __( 'This file is outside the configured size limits.', 'vacuum-image-optimizer' );
		}

		if ( self::is_excluded_by_pattern( $file_path ) ) {
			return __( 'This file matches an exclusion pattern.', 'vacuum-image-optimizer' );
		}

		return __( 'This image is pending optimization.', 'vacuum-image-optimizer' );
	}

	/**
	 * Check file-size exclusion settings.
	 *
	 * @param string $file_path Absolute path.
	 * @return bool
	 */
	private static function is_excluded_by_size( string $file_path ): bool {
		$file_size = is_readable( $file_path ) ? filesize( $file_path ) : false;
		if ( false === $file_size ) {
			return true;
		}

		$min = CompressionSettings::get_min_file_size();
		$max = CompressionSettings::get_max_file_size();

		return ( $min > 0 && $file_size < $min ) || ( $max > 0 && $file_size > $max );
	}

	/**
	 * Check filename/path pattern exclusions.
	 *
	 * @param string $file_path Absolute path.
	 * @return bool
	 */
	private static function is_excluded_by_pattern( string $file_path ): bool {
		$filename = basename( $file_path );

		foreach ( CompressionSettings::get_filename_patterns() as $pattern ) {
			if ( '' !== $pattern && false !== stripos( $filename, $pattern ) ) {
				return true;
			}
		}

		foreach ( CompressionSettings::get_path_patterns() as $pattern ) {
			if ( '' !== $pattern && false !== stripos( $file_path, $pattern ) ) {
				return true;
			}
		}

		return false;
	}
}
