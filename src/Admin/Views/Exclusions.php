<?php
/**
 * Exclusions tab view.
 *
 * @package VacuumImageOptimizer\Admin\Views
 */

namespace VacuumImageOptimizer\Admin\Views;

use VacuumImageOptimizer\Settings\CompressionSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exclusions settings view.
 */
class Exclusions {

	/**
	 * Render the exclusions content.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage these settings.', 'vacuum-image-optimizer' ) );
		}

		$settings = CompressionSettings::get();
		?>
		<div class="vio-exclusions">
			<div class="vio-card">
				<h2><?php esc_html_e( 'Exclusion Rules', 'vacuum-image-optimizer' ); ?></h2>

				<?php settings_errors( CompressionSettings::OPTION_NAME ); ?>

				<form method="post" action="options.php" class="vio-settings-form">
					<?php settings_fields( CompressionSettings::OPTION_GROUP ); ?>

					<table class="form-table vio-form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Exclude GIF Animations', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[exclude_gif]" value="0">
								<label class="vio-toggle">
									<input type="checkbox" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[exclude_gif]" value="1" <?php checked( ! empty( $settings['exclude_gif'] ) ); ?>>
									<span class="vio-toggle__slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'When enabled, GIF images are ignored by eligibility detection.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Exclude SVG Files', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[exclude_svg]" value="0">
								<label class="vio-toggle">
									<input type="checkbox" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[exclude_svg]" value="1" <?php checked( ! empty( $settings['exclude_svg'] ) ); ?>>
									<span class="vio-toggle__slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'When enabled, SVG files are ignored by eligibility detection.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Settings', 'vacuum-image-optimizer' ), 'primary vio-button vio-button--primary', 'submit', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}
}
