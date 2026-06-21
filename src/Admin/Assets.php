<?php
/**
 * Admin asset manager.
 *
 * @package VacuumImageOptimizer\Admin
 */

namespace VacuumImageOptimizer\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues admin CSS and JS on relevant admin screens.
 */
class Assets {

	/**
	 * The hook suffix for our admin page.
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Plugin admin page hook suffix.
	 *
	 * @var string
	 */
	private const PLUGIN_HOOK_SUFFIX = 'media_page_vacuum-image-optimizer';

	/**
	 * Media Library list table hook suffix.
	 *
	 * @var string
	 */
	private const MEDIA_LIBRARY_HOOK_SUFFIX = 'upload.php';

	/**
	 * Enqueue assets if on the plugin screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook_suffix ): void {
		$this->hook_suffix = $hook_suffix;

		if ( $this->is_media_library_screen() ) {
			$this->enqueue_styles();
			return;
		}

		if ( ! $this->is_plugin_screen() ) {
			return;
		}

		$this->enqueue_styles();
		$this->enqueue_scripts();
	}

	/**
	 * Check if current screen is the plugin admin page.
	 *
	 * @return bool
	 */
	private function is_plugin_screen(): bool {
		return self::PLUGIN_HOOK_SUFFIX === $this->hook_suffix;
	}

	/**
	 * Check if current screen is the Media Library list table.
	 *
	 * @return bool
	 */
	private function is_media_library_screen(): bool {
		return self::MEDIA_LIBRARY_HOOK_SUFFIX === $this->hook_suffix;
	}

	/**
	 * Enqueue styles.
	 *
	 * @return void
	 */
	private function enqueue_styles(): void {
		wp_register_style(
			'vio-admin',
			VIO_PLUGIN_URL . 'assets/admin/css/admin.css',
			[],
			VIO_VERSION
		);

		wp_enqueue_style( 'vio-admin' );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	private function enqueue_scripts(): void {
		wp_register_script(
			'vio-admin',
			VIO_PLUGIN_URL . 'assets/admin/js/admin.js',
			[],
			VIO_VERSION,
			true
		);

		wp_localize_script(
			'vio-admin',
			'vioQueue',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'vio_queue_ajax' ),
				'i18n'    => [
					'scanning'   => __( 'Scanning library…', 'vacuum-image-optimizer' ),
					'processing' => __( 'Processing queue…', 'vacuum-image-optimizer' ),
					'error'      => __( 'The queue request could not be completed.', 'vacuum-image-optimizer' ),
				],
			]
		);

		wp_enqueue_script( 'vio-admin' );
	}
}
