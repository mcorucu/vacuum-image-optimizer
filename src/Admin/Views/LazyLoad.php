<?php
/**
 * Lazy Load tab view.
 *
 * @package VacuumImageOptimizer\Admin\Views
 */

namespace VacuumImageOptimizer\Admin\Views;

use VacuumImageOptimizer\Settings\CompressionSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lazy Load settings view.
 */
class LazyLoad {

	/**
	 * Render the lazy load content.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage these settings.', 'vacuum-image-optimizer' ) );
		}

		$settings = CompressionSettings::get();
		?>
		<div class="vacimg-lazyload">
			<div class="vacimg-card">
				<h2><?php esc_html_e( 'Lazy Loading', 'vacuum-image-optimizer' ); ?></h2>

				<?php settings_errors( CompressionSettings::OPTION_NAME ); ?>

				<form method="post" action="options.php" class="vacimg-settings-form">
					<?php settings_fields( CompressionSettings::OPTION_GROUP ); ?>

					<table class="form-table vacimg-form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Lazy Loading', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[enable_lazy_loading]" value="0">
								<label class="vacimg-toggle">
									<input type="checkbox" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[enable_lazy_loading]" value="1" <?php checked( ! empty( $settings['enable_lazy_loading'] ) ); ?>>
									<span class="vacimg-toggle__slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Add native browser loading="lazy" to frontend images. No JavaScript is used.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Settings', 'vacuum-image-optimizer' ), 'primary vacimg-button vacimg-button--primary', 'submit', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}
}
