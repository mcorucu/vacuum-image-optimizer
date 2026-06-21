<?php
/**
 * Frontend delivery engine.
 *
 * Serves generated WebP/AVIF derivatives on the frontend without modifying the
 * original media, the database attachment URLs, or the uploads directory. It is
 * fully reversible: disabling the setting removes all rewriting on the next load.
 *
 * @package VacuumImageOptimizer\Frontend
 */

namespace VacuumImageOptimizer\Frontend;

use VacuumImageOptimizer\Settings\CompressionSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Rewrites image URLs to validated, browser-supported derivatives via core filters.
 */
class DeliveryEngine {

	/**
	 * Whether hooks have been registered.
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * Source MIME extensions eligible for rewriting.
	 *
	 * @var array<int, string>
	 */
	private const SOURCE_EXTENSIONS = [ 'jpg', 'jpeg', 'png' ];

	/**
	 * Ordered list of formats acceptable for the current request.
	 *
	 * Each entry is [ 'format' => 'avif'|'webp', 'ext' => 'avif'|'webp' ].
	 *
	 * @var array<int, array<string, string>>|null
	 */
	private ?array $accepted = null;

	/**
	 * Cached upload directory base path.
	 *
	 * @var string
	 */
	private string $upload_basedir = '';

	/**
	 * Cached upload directory base URL.
	 *
	 * @var string
	 */
	private string $upload_baseurl = '';

	/**
	 * Register frontend delivery hooks when appropriate.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( $this->registered || ! $this->should_run() ) {
			return;
		}

		$this->registered = true;

		add_filter( 'wp_get_attachment_image_attributes', [ $this, 'filter_image_attributes' ], 20 );
		add_filter( 'wp_calculate_image_srcset', [ $this, 'filter_image_srcset' ], 20 );
		add_filter( 'wp_content_img_tag', [ $this, 'filter_content_img_tag' ], 20 );
	}

	/**
	 * Determine whether frontend delivery should run for this request.
	 *
	 * @return bool
	 */
	private function should_run(): bool {
		if ( is_admin() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return false;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}

		if ( wp_doing_ajax() ) {
			return false;
		}

		return CompressionSettings::is_frontend_delivery_enabled();
	}

	/**
	 * Rewrite the src (and any srcset) of attachment image attributes.
	 *
	 * @param array<string, string> $attr Image attributes.
	 * @return array<string, string>
	 */
	public function filter_image_attributes( $attr ) {
		if ( ! is_array( $attr ) || $this->is_excluded_context() ) {
			return $attr;
		}

		if ( isset( $attr['src'] ) && is_string( $attr['src'] ) ) {
			$attr['src'] = $this->swap_url( $attr['src'] );
		}

		if ( ! empty( $attr['srcset'] ) && is_string( $attr['srcset'] ) ) {
			$attr['srcset'] = $this->swap_srcset_string( $attr['srcset'] );
		}

		return $attr;
	}

	/**
	 * Rewrite srcset candidate URLs to derivatives when available.
	 *
	 * @param array<int|string, array<string, string|int>> $sources Srcset sources.
	 * @return array<int|string, array<string, string|int>>
	 */
	public function filter_image_srcset( $sources ) {
		if ( ! is_array( $sources ) || $this->is_excluded_context() ) {
			return $sources;
		}

		foreach ( $sources as $key => $source ) {
			if ( isset( $source['url'] ) && is_string( $source['url'] ) ) {
				$sources[ $key ]['url'] = $this->swap_url( $source['url'] );
			}
		}

		return $sources;
	}

	/**
	 * Rewrite the base src attribute of a content image tag.
	 *
	 * @param string $filtered_image The image tag HTML.
	 * @return string
	 */
	public function filter_content_img_tag( $filtered_image ) {
		if ( ! is_string( $filtered_image ) || '' === $filtered_image || $this->is_excluded_context() ) {
			return $filtered_image;
		}

		if ( ! preg_match( '/\ssrc=("|\')(.*?)\1/i', $filtered_image, $matches ) ) {
			return $filtered_image;
		}

		$original_url = $matches[2];
		$new_url      = $this->swap_url( $original_url );

		if ( $new_url === $original_url ) {
			return $filtered_image;
		}

		$quote       = $matches[1];
		$replacement = ' src=' . $quote . $new_url . $quote;

		return str_replace( $matches[0], $replacement, $filtered_image );
	}

	/**
	 * Rewrite each URL inside a srcset attribute string.
	 *
	 * @param string $srcset Srcset attribute value.
	 * @return string
	 */
	private function swap_srcset_string( string $srcset ): string {
		$candidates = explode( ',', $srcset );

		foreach ( $candidates as $index => $candidate ) {
			$candidate = trim( $candidate );
			if ( '' === $candidate ) {
				continue;
			}

			$parts             = preg_split( '/\s+/', $candidate, 2 );
			$url               = isset( $parts[0] ) ? $parts[0] : '';
			$descriptor        = isset( $parts[1] ) ? ' ' . $parts[1] : '';
			$candidates[ $index ] = $this->swap_url( $url ) . $descriptor;
		}

		return implode( ', ', $candidates );
	}

	/**
	 * Swap a single image URL for the best available, browser-supported derivative.
	 *
	 * Only rewrites when the derivative file physically exists next to the source,
	 * guaranteeing dimensions match and no broken URLs are ever emitted.
	 *
	 * @param string $url Original image URL.
	 * @return string
	 */
	private function swap_url( string $url ): string {
		$accepted = $this->get_accepted_formats();
		if ( empty( $accepted ) || '' === $url ) {
			return $url;
		}

		$this->prime_upload_paths();
		if ( '' === $this->upload_basedir || '' === $this->upload_baseurl ) {
			return $url;
		}

		// Strip any query string / fragment for path resolution.
		$clean_url = preg_replace( '/[?#].*$/', '', $url );
		$clean_url = is_string( $clean_url ) ? $clean_url : $url;

		$normalized_url = $this->normalize_scheme( $clean_url );
		$base_url       = $this->normalize_scheme( $this->upload_baseurl );

		if ( 0 !== strpos( $normalized_url, trailingslashit( $base_url ) ) ) {
			return $url;
		}

		$relative  = ltrim( substr( $normalized_url, strlen( trailingslashit( $base_url ) ) ), '/' );
		$extension = strtolower( (string) pathinfo( $relative, PATHINFO_EXTENSION ) );

		if ( '' === $relative || ! in_array( $extension, self::SOURCE_EXTENSIONS, true ) ) {
			return $url;
		}

		$relative_no_ext = substr( $relative, 0, - ( strlen( $extension ) + 1 ) );

		foreach ( $accepted as $candidate ) {
			$candidate_relative = $relative_no_ext . '.' . $candidate['ext'];
			$candidate_path     = trailingslashit( $this->upload_basedir ) . $candidate_relative;

			if ( file_exists( $candidate_path ) && is_file( $candidate_path ) ) {
				return trailingslashit( $this->upload_baseurl ) . $candidate_relative;
			}
		}

		return $url;
	}

	/**
	 * Resolve the ordered list of formats acceptable for this request.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_accepted_formats(): array {
		if ( null !== $this->accepted ) {
			return $this->accepted;
		}

		$preferred      = CompressionSettings::get_preferred_format();
		$accept_header  = isset( $_SERVER['HTTP_ACCEPT'] ) ? (string) wp_unslash( $_SERVER['HTTP_ACCEPT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$supports_avif  = false !== strpos( $accept_header, 'image/avif' );
		$supports_webp  = false !== strpos( $accept_header, 'image/webp' );

		$order = [];

		if ( 'avif' === $preferred ) {
			if ( $supports_avif ) {
				$order[] = [ 'format' => 'avif', 'ext' => 'avif' ];
			}
		} elseif ( 'webp' === $preferred ) {
			if ( $supports_webp ) {
				$order[] = [ 'format' => 'webp', 'ext' => 'webp' ];
			}
		} else {
			// auto: AVIF first, then WebP, based on browser support.
			if ( $supports_avif ) {
				$order[] = [ 'format' => 'avif', 'ext' => 'avif' ];
			}
			if ( $supports_webp ) {
				$order[] = [ 'format' => 'webp', 'ext' => 'webp' ];
			}
		}

		$this->accepted = $order;

		return $this->accepted;
	}

	/**
	 * Cache the upload directory base path and URL.
	 *
	 * @return void
	 */
	private function prime_upload_paths(): void {
		if ( '' !== $this->upload_basedir ) {
			return;
		}

		$uploads = wp_get_upload_dir();

		$this->upload_basedir = isset( $uploads['basedir'] ) ? wp_normalize_path( (string) $uploads['basedir'] ) : '';
		$this->upload_baseurl = isset( $uploads['baseurl'] ) ? untrailingslashit( (string) $uploads['baseurl'] ) : '';
	}

	/**
	 * Normalize a URL scheme so http/https comparisons succeed.
	 *
	 * @param string $url URL to normalize.
	 * @return string
	 */
	private function normalize_scheme( string $url ): string {
		return preg_replace( '#^https?://#i', '//', $url ) ?? $url;
	}

	/**
	 * Skip rewriting in contexts where the original must be preserved.
	 *
	 * @return bool
	 */
	private function is_excluded_context(): bool {
		return is_feed() || is_embed();
	}
}
