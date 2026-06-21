<?php
/**
 * Native browser lazy loading for frontend images.
 *
 * Adds loading="lazy" to frontend image output using core WordPress filters.
 * It never modifies image URLs, never runs in admin/feeds/REST/AJAX, and relies
 * solely on the browser's native lazy loading (no JavaScript).
 *
 * @package VacuumImageOptimizer\Frontend
 */

namespace VacuumImageOptimizer\Frontend;

use VacuumImageOptimizer\Settings\CompressionSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Applies native loading="lazy" to frontend images when enabled.
 */
class LazyLoad {

	/**
	 * Whether hooks have been registered.
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * Register lazy loading hooks when appropriate.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( $this->registered || ! $this->should_run() ) {
			return;
		}

		$this->registered = true;

		add_filter( 'wp_get_attachment_image_attributes', [ $this, 'filter_image_attributes' ], 20 );
		add_filter( 'wp_content_img_tag', [ $this, 'filter_content_img_tag' ], 20 );
	}

	/**
	 * Determine whether lazy loading should run for this request.
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

		return CompressionSettings::is_lazy_loading_enabled();
	}

	/**
	 * Add loading="lazy" to attachment image attributes.
	 *
	 * @param array<string, string> $attr Image attributes.
	 * @return array<string, string>
	 */
	public function filter_image_attributes( $attr ) {
		if ( ! is_array( $attr ) || $this->is_excluded_context() ) {
			return $attr;
		}

		if ( empty( $attr['loading'] ) ) {
			$attr['loading'] = 'lazy';
		}

		return $attr;
	}

	/**
	 * Ensure content image tags carry loading="lazy".
	 *
	 * @param string $filtered_image The image tag HTML.
	 * @return string
	 */
	public function filter_content_img_tag( $filtered_image ) {
		if ( ! is_string( $filtered_image ) || '' === $filtered_image || $this->is_excluded_context() ) {
			return $filtered_image;
		}

		// Leave existing loading attributes untouched.
		if ( false !== stripos( $filtered_image, ' loading=' ) ) {
			return $filtered_image;
		}

		if ( ! preg_match( '/<img\s/i', $filtered_image ) ) {
			return $filtered_image;
		}

		return (string) preg_replace( '/<img\s/i', '<img loading="lazy" ', $filtered_image, 1 );
	}

	/**
	 * Skip contexts where native lazy loading must not be injected.
	 *
	 * @return bool
	 */
	private function is_excluded_context(): bool {
		return is_feed() || is_embed();
	}
}
