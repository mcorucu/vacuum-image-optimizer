<?php
/**
 * Validates and resolves plugin-generated derivatives.
 *
 * @package VacuumImageOptimizer\Frontend
 */

namespace VacuumImageOptimizer\Frontend;

use VacuumImageOptimizer\Settings\CompressionSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Central source-aware derivative registry.
 */
class DerivativeResolver {

	private const META_KEY = '_vacimg_derivatives';

	/**
	 * Record a derivative for the exact source file that produced it.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $source_path Source file.
	 * @param string $format webp or avif.
	 * @param string $derivative_path Derivative file.
	 * @return void
	 */
	public static function record( int $attachment_id, string $source_path, string $format, string $derivative_path ): void {
		$format = sanitize_key( $format );
		if ( 0 === $attachment_id || ! in_array( $format, [ 'webp', 'avif' ], true ) || ! is_readable( $source_path ) || ! is_readable( $derivative_path ) ) {
			return;
		}

		$source_key = self::relative_path( $source_path );
		$relative   = self::relative_path( $derivative_path );
		$source_hash = self::file_hash( $source_path );
		if ( '' === $source_key || '' === $relative || '' === $source_hash ) {
			return;
		}

		$registry = self::get_registry( $attachment_id );
		$registry[ $source_key ][ $format ] = [
			'path'        => wp_normalize_path( $derivative_path ),
			'relative'    => $relative,
			'source_hash' => $source_hash,
			'bytes'       => absint( filesize( $derivative_path ) ),
			'updated_at'  => time(),
		];

		update_post_meta( $attachment_id, self::META_KEY, $registry );
	}

	/**
	 * Resolve a validated URL for one exact source file.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $source_path Source file.
	 * @param string $format webp or avif.
	 * @return string
	 */
	public static function resolve( int $attachment_id, string $source_path, string $format ): string {
		$format = sanitize_key( $format );
		if ( 0 === $attachment_id || ! in_array( $format, [ 'webp', 'avif' ], true ) || ! self::format_enabled( $format ) ) {
			return '';
		}

		$source_path = wp_normalize_path( $source_path );
		$registry    = self::get_registry( $attachment_id );
		$source_key  = self::relative_path( $source_path );
		$entry       = $registry[ $source_key ][ $format ] ?? null;

		// Backward-compatible full-size metadata from 1.0.x/1.1.x is imported lazily.
		if ( ! is_array( $entry ) && '' !== $source_key && basename( $source_path ) === basename( (string) get_attached_file( $attachment_id ) ) ) {
			$legacy_path = get_post_meta( $attachment_id, '_vacimg_' . $format . '_path', true );
			if ( is_string( $legacy_path ) && '' !== $legacy_path ) {
				self::record( $attachment_id, $source_path, $format, $legacy_path );
				$registry = self::get_registry( $attachment_id );
				$entry    = $registry[ $source_key ][ $format ] ?? null;
			}
		}

		if ( ! is_array( $entry ) || ! self::is_valid_entry( $entry, $source_path ) ) {
			return '';
		}

		return self::relative_to_url( (string) $entry['relative'] );
	}

	/**
	 * Describe the stored derivative for one exact source file.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $source_path Source file.
	 * @param string $format webp or avif.
	 * @return array<string, mixed>
	 */
	public static function inspect( int $attachment_id, string $source_path, string $format ): array {
		$format     = sanitize_key( $format );
		$source_key = self::relative_path( $source_path );
		$registry   = self::get_registry( $attachment_id );
		$entry      = $registry[ $source_key ][ $format ] ?? null;

		if ( ! is_array( $entry ) ) {
			return [ 'status' => 'missing', 'entry' => [] ];
		}

		if ( self::is_valid_entry( $entry, $source_path ) ) {
			return [ 'status' => 'ready', 'entry' => $entry ];
		}

		return [ 'status' => 'stale', 'entry' => $entry ];
	}

	/**
	 * Validate a derivative that is about to be reused or repaired.

	 * @param int    $attachment_id Attachment ID.
	 * @param string $source_path Source file.
	 * @param string $format Format.
	 * @param string $derivative_path Derivative path.
	 * @return bool
	 */
	public static function is_valid_derivative( int $attachment_id, string $source_path, string $format, string $derivative_path ): bool {
		$inspection = self::inspect( $attachment_id, $source_path, $format );
		return 'ready' === $inspection['status'] && wp_normalize_path( (string) ( $inspection['entry']['path'] ?? '' ) ) === wp_normalize_path( $derivative_path );
	}

	/**
	 * Get a source-relative registry key.
	 *
	 * @param string $path Absolute upload path.
	 * @return string
	 */
	public static function relative_path( string $path ): string {
		$uploads = wp_get_upload_dir();
		$basedir = isset( $uploads['basedir'] ) ? wp_normalize_path( (string) $uploads['basedir'] ) : '';
		$path    = wp_normalize_path( $path );
		if ( '' === $basedir || 0 !== strpos( $path, trailingslashit( $basedir ) ) ) {
			return '';
		}

		return ltrim( substr( $path, strlen( trailingslashit( $basedir ) ) ), '/' );
	}

	/** @return array<string, array<string, array<string, mixed>>> */
	private static function get_registry( int $attachment_id ): array {
		$registry = get_post_meta( $attachment_id, self::META_KEY, true );
		return is_array( $registry ) ? $registry : [];
	}

	private static function format_enabled( string $format ): bool {
		return 'webp' === $format ? CompressionSettings::is_webp_enabled() : CompressionSettings::is_avif_enabled();
	}

	/** @param array<string, mixed> $entry */
	private static function is_valid_entry( array $entry, string $source_path ): bool {
		$path = isset( $entry['path'] ) ? wp_normalize_path( (string) $entry['path'] ) : '';
		if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) || ! is_readable( $source_path ) ) {
			return false;
		}

		$source_size = filesize( $source_path );
		$derivative_size = filesize( $path );
		if ( false === $source_size || false === $derivative_size || $derivative_size > $source_size ) {
			return false;
		}

		$dimensions = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return is_array( $dimensions ) && self::file_hash( $source_path ) === (string) ( $entry['source_hash'] ?? '' );
	}

	private static function file_hash( string $path ): string {
		$hash = @md5_file( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return is_string( $hash ) ? $hash : '';
	}

	private static function relative_to_url( string $relative ): string {
		$uploads = wp_get_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? untrailingslashit( (string) $uploads['baseurl'] ) : '';
		return '' === $baseurl || '' === $relative ? '' : trailingslashit( $baseurl ) . str_replace( '%2F', '/', rawurlencode( $relative ) );
	}
}
