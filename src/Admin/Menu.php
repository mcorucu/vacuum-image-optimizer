<?php
/**
 * Admin menu registration.
 *
 * @package VacuumImageOptimizer\Admin
 */

namespace VacuumImageOptimizer\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin under Media → Vacuum Optimizer.
 */
class Menu {

	/**
	 * Parent slug for the Media menu.
	 *
	 * @var string
	 */
	private const PARENT_SLUG = 'upload.php';

	/**
	 * Main page slug.
	 *
	 * @var string
	 */
	private const MENU_SLUG = 'vacuum-image-optimizer';

	/**
	 * Required capability.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Register the submenu page.
	 *
	 * @return void
	 */
	public function register(): void {
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Vacuum Image Optimizer', 'vacuum-image-optimizer' ),
			__( 'Vacuum Optimizer', 'vacuum-image-optimizer' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'vacuum-image-optimizer' ) );
		}

		$router = new Router();
		$router->dispatch();
	}
}
