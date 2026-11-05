<?php
/**
 * WordPress-native automatic modern image delivery.
 *
 * @package VacuumImageOptimizer\Frontend
 */

namespace VacuumImageOptimizer\Frontend;

use VacuumImageOptimizer\Settings\CompressionSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves attachment-aware WebP/AVIF URLs without rewriting stored content.
 */
class DeliveryEngine {

	private bool $registered = false;
	private ?array $accepted = null;

	public function register(): void {
		if ( $this->registered || ! $this->should_run() ) {
			return;
		}
		$this->registered = true;
		add_filter( 'wp_get_attachment_image_attributes', [ $this, 'filter_image_attributes' ], 20, 3 );
		add_filter( 'wp_calculate_image_srcset', [ $this, 'filter_image_srcset' ], 20, 5 );
		add_filter( 'wp_content_img_tag', [ $this, 'filter_content_img_tag' ], 20, 3 );
		add_action( 'send_headers', [ $this, 'send_cache_header' ] );
	}

	public function filter_image_attributes( $attr, $attachment = null, $size = 'full' ) {
		if ( ! is_array( $attr ) || $this->is_excluded_context() || ! $attachment instanceof \WP_Post ) {
			return $attr;
		}
		$attachment_id = absint( $attachment->ID );
		$source_path    = $this->source_path_for_size( $attachment_id, $size );
		if ( isset( $attr['src'] ) && is_string( $attr['src'] ) ) {
			$attr['src'] = $this->resolve_url( $attachment_id, $source_path, $attr['src'] );
		}
		return $attr;
	}

	public function filter_image_srcset( $sources, $size_array = [], $image_src = [], $image_meta = [], $attachment_id = 0 ) {
		if ( ! is_array( $sources ) || $this->is_excluded_context() || 0 === absint( $attachment_id ) ) {
			return $sources;
		}
		foreach ( $sources as $key => $source ) {
			if ( isset( $source['url'] ) && is_string( $source['url'] ) ) {
				$path = $this->url_to_upload_path( $source['url'] );
				$sources[ $key ]['url'] = $this->resolve_url( absint( $attachment_id ), $path, $source['url'] );
			}
		}
		return $sources;
	}

	/**
	 * Narrow fallback for classic content saved by older WordPress versions.
	 */
	public function filter_content_img_tag( $image, $context = '', $attachment_id = 0 ) {
		if ( ! is_string( $image ) || '' === $image || $this->is_excluded_context() ) {
			return $image;
		}
		if ( 0 === absint( $attachment_id ) && preg_match( '/\ssrc=["\']([^"\']+)["\']/i', $image, $match ) ) {
			$attachment_id = $this->find_attachment_by_url( $match[1] );
		}
		if ( 0 === absint( $attachment_id ) || ! preg_match( '/\ssrc=(["\'])(.*?)\1/i', $image, $match ) ) {
			return $image;
		}
		$url = $this->resolve_url( absint( $attachment_id ), $this->url_to_upload_path( $match[2] ), $match[2] );
		if ( $url === $match[2] ) {
			return $image;
		}
		return str_replace( $match[0], ' src=' . $match[1] . esc_url( $url ) . $match[1], $image );
	}

	public function send_cache_header(): void {
		if ( $this->should_run() ) {
			header( 'Vary: Accept', false );
		}
	}

	/** Return the same candidate selection used by frontend filters for diagnostics. */
	public function diagnostic_candidate( int $attachment_id, string $source_path, string $original_url ): string {
		return $this->resolve_url( $attachment_id, $source_path, $original_url );
	}

	private function resolve_url( int $attachment_id, string $source_path, string $original_url ): string {
		if ( '' === $source_path || empty( $this->get_accepted_formats() ) ) {
			return $original_url;
		}
		foreach ( $this->get_accepted_formats() as $format ) {
			$url = DerivativeResolver::resolve( $attachment_id, $source_path, $format );
			if ( '' !== $url ) {
				return $url;
			}
		}
		if ( CompressionSettings::is_delivery_debug_enabled() ) {
			do_action( 'vacimg_delivery_fallback', $attachment_id, $source_path, 'missing_or_stale_derivative' );
		}
		return $original_url;
	}

	private function source_path_for_size( int $attachment_id, $size ): string {
		$attached = get_attached_file( $attachment_id );
		if ( ! is_string( $attached ) || '' === $attached ) {
			return '';
		}
		if ( is_array( $size ) && isset( $size['file'] ) ) {
			return trailingslashit( wp_normalize_path( dirname( $attached ) ) ) . sanitize_file_name( (string) $size['file'] );
		}
		if ( is_string( $size ) && 'full' !== $size ) {
			$meta = wp_get_attachment_metadata( $attachment_id );
			$file = is_array( $meta ) && isset( $meta['sizes'][ $size ]['file'] ) ? (string) $meta['sizes'][ $size ]['file'] : '';
			if ( '' !== $file ) {
				return trailingslashit( wp_normalize_path( dirname( $attached ) ) ) . sanitize_file_name( $file );
			}
		}
		return wp_normalize_path( $attached );
	}

	private function url_to_upload_path( string $url ): string {
		$uploads = wp_get_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? untrailingslashit( (string) $uploads['baseurl'] ) : '';
		$basedir = isset( $uploads['basedir'] ) ? wp_normalize_path( (string) $uploads['basedir'] ) : '';
		$clean   = (string) preg_replace( '/[?#].*$/', '', $url );
		if ( '' === $baseurl || '' === $basedir || 0 !== strpos( $this->normalize_scheme( $clean ), trailingslashit( $this->normalize_scheme( $baseurl ) ) ) ) {
			return '';
		}
		$relative = ltrim( substr( $this->normalize_scheme( $clean ), strlen( trailingslashit( $this->normalize_scheme( $baseurl ) ) ) ), '/' );
		return trailingslashit( $basedir ) . rawurldecode( $relative );
	}

	private function find_attachment_by_url( string $url ): int {
		$path = DerivativeResolver::relative_path( $this->url_to_upload_path( $url ) );
		if ( '' === $path ) {
			return 0;
		}
		global $wpdb;
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_wp_attached_file', $path ) ) );
	}

	private function get_accepted_formats(): array {
		if ( null !== $this->accepted ) {
			return $this->accepted;
		}

		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? (string) wp_unslash( $_SERVER['HTTP_ACCEPT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$quality = [];
		foreach ( explode( ',', strtolower( $accept ) ) as $item ) {
			$parts = array_map( 'trim', explode( ';', $item ) );
			if ( ! preg_match( '#^image/(avif|webp)$#', (string) array_shift( $parts ), $match ) ) {
				continue;
			}

			$q = 1.0;
			foreach ( $parts as $parameter ) {
				if ( preg_match( '/^q\s*=\s*(.*)$/', $parameter, $q_match ) ) {
					$q = is_numeric( $q_match[1] ) && (float) $q_match[1] >= 0 && (float) $q_match[1] <= 1 ? (float) $q_match[1] : 0.0;
				}
			}
			$format = $match[1];
			$quality[ $format ] = max( $quality[ $format ] ?? 0.0, $q );
		}

		$preferred = CompressionSettings::get_preferred_format();
		$order = 'webp' === $preferred ? [ 'webp' ] : [ 'avif', 'webp' ];
		$rank  = array_flip( $order );
		$this->accepted = array_values(
			array_filter(
				$order,
				static function ( $format ) use ( $quality ) {
					return isset( $quality[ $format ] ) && $quality[ $format ] > 0;
				}
			)
		);
		usort(
			$this->accepted,
			static function ( $left, $right ) use ( $quality, $rank ) {
				$by_quality = ( $quality[ $right ] ?? 0.0 ) <=> ( $quality[ $left ] ?? 0.0 );
				return 0 !== $by_quality ? $by_quality : ( $rank[ $left ] <=> $rank[ $right ] );
			}
		);
		return $this->accepted;
	}

	private function should_run(): bool {
		return ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! ( defined( 'DOING_CRON' ) && DOING_CRON ) && ! ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) && ! wp_doing_ajax() && CompressionSettings::is_frontend_delivery_enabled();
	}

	private function is_excluded_context(): bool {
		return is_feed() || is_embed();
	}

	private function normalize_scheme( string $url ): string {
		return preg_replace( '#^https?://#i', '//', $url ) ?? $url;
	}
}
