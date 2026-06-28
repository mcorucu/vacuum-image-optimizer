<?php
/**
 * Internal tab router for the admin page.
 *
 * @package VacuumImageOptimizer\Admin
 */

namespace VacuumImageOptimizer\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Routes tab requests to the appropriate view.
 */
class Router {

	/**
	 * Default tab slug.
	 *
	 * @var string
	 */
	private const DEFAULT_TAB = 'dashboard';

	/**
	 * Available tabs.
	 *
	 * @var array<string, string>
	 */
	private array $tabs = [
		'dashboard'      => 'Dashboard',
		'bulk-optimize'  => 'Bulk Optimize',
		'webp-avif'      => 'WebP & AVIF',
		'compression'    => 'Compression',
		'lazy-load'      => 'Lazy Load',
		'backup-restore' => 'Backup & Restore',
		'exclusions'     => 'Exclusions',
		'reports'        => 'Reports',
		'system-status'  => 'System Status',
	];

	/**
	 * Dispatch the current tab.
	 *
	 * @return void
	 */
	public function dispatch(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'vacuum-image-optimizer' ) );
		}

		$tab = $this->get_current_tab();

		// Output the page wrapper and tab navigation.
		$this->render_header( $tab );

		// Route to the view class.
		$view_class = $this->resolve_view_class( $tab );
		if ( class_exists( $view_class ) ) {
			$view = new $view_class();
			$view->render();
		} else {
			$this->render_placeholder( $tab );
		}

		$this->render_footer();
	}

	/**
	 * Get the current active tab from query string.
	 *
	 * @return string
	 */
	public function get_current_tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return array_key_exists( $tab, $this->tabs ) ? $tab : self::DEFAULT_TAB;
	}

	/**
	 * Get all registered tabs.
	 *
	 * @return array<string, string>
	 */
	public function get_tabs(): array {
		return $this->tabs;
	}

	/**
	 * Build the admin page URL for a given tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function get_tab_url( string $tab ): string {
		return admin_url( 'upload.php?page=vacuum-image-optimizer&tab=' . sanitize_key( $tab ) );
	}

	/**
	 * Render the page header and tab navigation.
	 *
	 * @param string $active_tab Active tab slug.
	 * @return void
	 */
	private function render_header( string $active_tab ): void {
		?>
		<div class="wrap vacimg-admin">
			<header class="vacimg-page-header">
				<img class="vacimg-page-header__logo" src="<?php echo esc_url( VACIMG_PLUGIN_URL . 'assets/branding/admin-icon.svg' ); ?>" width="36" height="36" alt="" aria-hidden="true">
				<div class="vacimg-page-header__text">
					<h1 class="vacimg-page-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
					<p class="vacimg-page-description"><?php esc_html_e( 'Modern image optimization for WordPress — WebP &amp; AVIF generation, bulk tools, and clean delivery.', 'vacuum-image-optimizer' ); ?></p>
				</div>
			</header>
			<?php $this->render_tab_nav( $active_tab ); ?>
			<div class="vacimg-tab-content">
		<?php
	}

	/**
	 * Render tab navigation.
	 *
	 * @param string $active_tab Active tab slug.
	 * @return void
	 */
	private function render_tab_nav( string $active_tab ): void {
		?>
		<nav class="vacimg-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Vacuum Optimizer Sections', 'vacuum-image-optimizer' ); ?>">
			<?php foreach ( $this->tabs as $slug => $label ) : ?>
				<a
					href="<?php echo esc_url( self::get_tab_url( $slug ) ); ?>"
					class="vacimg-tab<?php echo ( $slug === $active_tab ) ? ' is-active' : ''; ?>"
					role="tab"
					aria-selected="<?php echo ( $slug === $active_tab ) ? 'true' : 'false'; ?>"
				>
					<?php echo esc_html( self::get_tab_label( $slug ) ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Get the translated label for a tab slug using static, extractable strings.
	 *
	 * @param string $slug Tab slug.
	 * @return string
	 */
	public static function get_tab_label( string $slug ): string {
		$labels = [
			'dashboard'      => __( 'Dashboard', 'vacuum-image-optimizer' ),
			'bulk-optimize'  => __( 'Bulk Optimize', 'vacuum-image-optimizer' ),
			'webp-avif'      => __( 'WebP & AVIF', 'vacuum-image-optimizer' ),
			'compression'    => __( 'Compression', 'vacuum-image-optimizer' ),
			'lazy-load'      => __( 'Lazy Load', 'vacuum-image-optimizer' ),
			'backup-restore' => __( 'Backup & Restore', 'vacuum-image-optimizer' ),
			'exclusions'     => __( 'Exclusions', 'vacuum-image-optimizer' ),
			'reports'        => __( 'Reports', 'vacuum-image-optimizer' ),
			'system-status'  => __( 'System Status', 'vacuum-image-optimizer' ),
		];

		return $labels[ $slug ] ?? $slug;
	}

	/**
	 * Render a placeholder for missing views.
	 *
	 * @param string $tab Tab slug.
	 * @return void
	 */
	private function render_placeholder( string $tab ): void {
		?>
		<div class="vacimg-card">
			<h2><?php echo esc_html( self::get_tab_label( $tab ) ); ?></h2>
			<p><?php esc_html_e( 'This feature is coming soon.', 'vacuum-image-optimizer' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Resolve the view class name for a tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	private function resolve_view_class( string $tab ): string {
		$class_map = [
			'dashboard'      => 'Dashboard',
			'bulk-optimize'  => 'BulkOptimize',
			'webp-avif'      => 'Formats',
			'compression'    => 'Compression',
			'lazy-load'      => 'LazyLoad',
			'backup-restore' => 'BackupRestore',
			'exclusions'     => 'Exclusions',
			'reports'        => 'Reports',
			'system-status'  => 'SystemStatus',
		];

		$view = $class_map[ $tab ] ?? '';
		return __NAMESPACE__ . '\\Views\\' . $view;
	}

	/**
	 * Render the page footer.
	 *
	 * @return void
	 */
	private function render_footer(): void {
		?>
			</div>
		</div>
		<?php
	}
}
