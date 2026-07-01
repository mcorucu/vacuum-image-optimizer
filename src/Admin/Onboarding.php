<?php
/**
 * Optional setup wizard.
 *
 * @package VacuumImageOptimizer\Admin
 */

namespace VacuumImageOptimizer\Admin;

use VacuumImageOptimizer\Settings\CompressionSettings;
use VacuumImageOptimizer\Utils\SystemCheck;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles the lightweight first-run setup wizard.
 */
class Onboarding {

	private const OPTION_STATUS = 'vacimg_setup_wizard_status';

	/**
	 * Register form handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_vacimg_save_wizard', [ $this, 'save' ] );
		add_action( 'admin_post_vacimg_skip_wizard', [ $this, 'skip' ] );
	}

	/**
	 * Whether the wizard should render on the plugin admin page.
	 *
	 * @return bool
	 */
	public static function should_show(): bool {
		$explicit = isset( $_GET['vacimg_wizard'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['vacimg_wizard'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return $explicit || ! in_array( self::get_status(), [ 'completed', 'skipped' ], true );
	}

	/**
	 * Build a relaunch URL for settings screens.
	 *
	 * @return string
	 */
	public static function get_relaunch_url(): string {
		return add_query_arg(
			[
				'page'          => 'vacuum-image-optimizer',
				'tab'           => 'compression',
				'vacimg_wizard' => '1',
			],
			admin_url( 'upload.php' )
		);
	}

	/**
	 * Render the wizard.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = CompressionSettings::get();
		$status   = SystemCheck::get_status();
		?>
		<div class="vacimg-card vacimg-wizard">
			<div class="vacimg-card-header-inline">
				<h2><?php esc_html_e( 'Setup Wizard', 'vacuum-image-optimizer' ); ?></h2>
				<a class="vacimg-button vacimg-button--secondary vacimg-button--small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vacimg_skip_wizard' ), 'vacimg_skip_wizard' ) ); ?>"><?php esc_html_e( 'Skip setup', 'vacuum-image-optimizer' ); ?></a>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vacimg-settings-form">
				<input type="hidden" name="action" value="vacimg_save_wizard">
				<?php wp_nonce_field( 'vacimg_save_wizard' ); ?>

				<div class="vacimg-wizard-grid">
					<section>
						<h3><?php esc_html_e( '1. Welcome', 'vacuum-image-optimizer' ); ?></h3>
						<p><?php esc_html_e( 'Choose safe defaults for local image optimization. You can change everything later.', 'vacuum-image-optimizer' ); ?></p>
					</section>

					<section>
						<h3><?php esc_html_e( '2. Optimization Mode', 'vacuum-image-optimizer' ); ?></h3>
						<label><input type="radio" name="vacimg_mode" value="safe" <?php checked( ! empty( $settings['safe_mode'] ) ); ?>> <?php esc_html_e( 'Safe mode: preserve originals and prefer generated alternatives', 'vacuum-image-optimizer' ); ?></label>
						<label><input type="radio" name="vacimg_mode" value="standard" <?php checked( empty( $settings['safe_mode'] ) ); ?>> <?php esc_html_e( 'Standard mode: optimize with backups enabled', 'vacuum-image-optimizer' ); ?></label>
					</section>

					<section>
						<h3><?php esc_html_e( '3. Format Options', 'vacuum-image-optimizer' ); ?></h3>
						<label><input type="checkbox" name="enable_webp" value="1" <?php checked( ! empty( $settings['enable_webp'] ) ); ?>> <?php esc_html_e( 'Enable WebP generation and WebP source optimization', 'vacuum-image-optimizer' ); ?></label>
						<label><input type="checkbox" name="enable_avif" value="1" <?php checked( ! empty( $settings['enable_avif'] ) ); ?> <?php disabled( empty( $status['avif_support'] ) ); ?>> <?php esc_html_e( 'Enable AVIF generation when supported', 'vacuum-image-optimizer' ); ?></label>
					</section>

					<section>
						<h3><?php esc_html_e( '4. Quality Settings', 'vacuum-image-optimizer' ); ?></h3>
						<label><?php esc_html_e( 'JPEG/WebP quality', 'vacuum-image-optimizer' ); ?> <input type="number" name="quality" min="1" max="100" value="<?php echo esc_attr( (string) $settings['quality'] ); ?>"></label>
						<label><?php esc_html_e( 'AVIF quality', 'vacuum-image-optimizer' ); ?> <input type="number" name="avif_quality" min="0" max="100" value="<?php echo esc_attr( (string) $settings['avif_quality'] ); ?>"></label>
					</section>

					<section>
						<h3><?php esc_html_e( '5. Resize Options', 'vacuum-image-optimizer' ); ?></h3>
						<label><?php esc_html_e( 'Optional max width', 'vacuum-image-optimizer' ); ?> <input type="number" name="max_width" min="0" value="<?php echo esc_attr( (string) $settings['max_width'] ); ?>"></label>
						<label><?php esc_html_e( 'Optional max height', 'vacuum-image-optimizer' ); ?> <input type="number" name="max_height" min="0" value="<?php echo esc_attr( (string) $settings['max_height'] ); ?>"></label>
					</section>

					<section>
						<h3><?php esc_html_e( '6. Backup Preference', 'vacuum-image-optimizer' ); ?></h3>
						<label><input type="checkbox" name="enable_backups" value="1" <?php checked( ! empty( $settings['enable_backups'] ) ); ?>> <?php esc_html_e( 'Keep original backups enabled', 'vacuum-image-optimizer' ); ?></label>
					</section>

					<section>
						<h3><?php esc_html_e( '7. Lazy Loading', 'vacuum-image-optimizer' ); ?></h3>
						<label><input type="checkbox" name="enable_lazy_loading" value="1" <?php checked( ! empty( $settings['enable_lazy_loading'] ) ); ?>> <?php esc_html_e( 'Enable native browser lazy loading', 'vacuum-image-optimizer' ); ?></label>
					</section>

					<section>
						<h3><?php esc_html_e( '8. Finish', 'vacuum-image-optimizer' ); ?></h3>
						<div class="vacimg-button-row">
							<button type="submit" class="vacimg-button vacimg-button--primary"><?php esc_html_e( 'Save settings', 'vacuum-image-optimizer' ); ?></button>
							<a class="vacimg-button vacimg-button--secondary" href="<?php echo esc_url( Router::get_tab_url( 'bulk-optimize' ) ); ?>"><?php esc_html_e( 'Go to Bulk Optimize', 'vacuum-image-optimizer' ); ?></a>
							<a class="vacimg-button vacimg-button--secondary" href="<?php echo esc_url( Router::get_tab_url( 'dashboard' ) ); ?>"><?php esc_html_e( 'Go to Dashboard', 'vacuum-image-optimizer' ); ?></a>
						</div>
					</section>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Save wizard choices.
	 *
	 * @return void
	 */
	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage setup.', 'vacuum-image-optimizer' ) );
		}

		check_admin_referer( 'vacimg_save_wizard' );

		$current = CompressionSettings::get();
		$mode    = isset( $_POST['vacimg_mode'] ) ? sanitize_key( wp_unslash( $_POST['vacimg_mode'] ) ) : 'safe';
		$input   = [
			'enable_webp'         => ! empty( $_POST['enable_webp'] ) ? 1 : 0,
			'enable_avif'         => ! empty( $_POST['enable_avif'] ) ? 1 : 0,
			'quality'             => isset( $_POST['quality'] ) ? absint( $_POST['quality'] ) : $current['quality'],
			'avif_quality'        => isset( $_POST['avif_quality'] ) ? absint( $_POST['avif_quality'] ) : $current['avif_quality'],
			'max_width'           => isset( $_POST['max_width'] ) ? absint( $_POST['max_width'] ) : $current['max_width'],
			'max_height'          => isset( $_POST['max_height'] ) ? absint( $_POST['max_height'] ) : $current['max_height'],
			'enable_backups'      => ! empty( $_POST['enable_backups'] ) ? 1 : 0,
			'enable_lazy_loading' => ! empty( $_POST['enable_lazy_loading'] ) ? 1 : 0,
			'safe_mode'           => 'standard' === $mode ? 0 : 1,
		];

		$settings = ( new CompressionSettings() )->sanitize( array_merge( $current, $input ) );
		if ( ! empty( $settings['safe_mode'] ) ) {
			$settings['enable_backups'] = true;
		}

		update_option( CompressionSettings::OPTION_NAME, $settings );
		update_option( self::OPTION_STATUS, 'completed' );

		wp_safe_redirect( Router::get_tab_url( 'dashboard' ) );
		exit;
	}

	/**
	 * Skip the wizard.
	 *
	 * @return void
	 */
	public function skip(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage setup.', 'vacuum-image-optimizer' ) );
		}

		check_admin_referer( 'vacimg_skip_wizard' );
		update_option( self::OPTION_STATUS, 'skipped' );

		wp_safe_redirect( Router::get_tab_url( 'dashboard' ) );
		exit;
	}

	/**
	 * Get stored wizard status.
	 *
	 * @return string
	 */
	private static function get_status(): string {
		$status = sanitize_key( (string) get_option( self::OPTION_STATUS, 'pending' ) );

		return in_array( $status, [ 'pending', 'completed', 'skipped' ], true ) ? $status : 'pending';
	}
}
