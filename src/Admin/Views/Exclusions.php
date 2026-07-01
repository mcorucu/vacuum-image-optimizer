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
		<div class="vacimg-exclusions">
			<div class="vacimg-card">
				<h2><?php esc_html_e( 'Exclusion Rules', 'vacuum-image-optimizer' ); ?></h2>

				<?php settings_errors( CompressionSettings::OPTION_NAME ); ?>

				<form method="post" action="options.php" class="vacimg-settings-form">
					<?php settings_fields( CompressionSettings::OPTION_GROUP ); ?>

					<table class="form-table vacimg-form-table">
						<tr>
							<th scope="row">
								<label for="vacimg-exclude-mime-types"><?php esc_html_e( 'Specific MIME Types', 'vacuum-image-optimizer' ); ?></label>
							</th>
							<td>
								<textarea id="vacimg-exclude-mime-types" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[exclude_mime_types]" rows="4" class="large-text code"><?php echo esc_textarea( implode( "\n", (array) $settings['exclude_mime_types'] ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One MIME type per line, for example image/tiff.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'File Size Limits', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<label for="vacimg-min-file-size"><strong><?php esc_html_e( 'Minimum bytes', 'vacuum-image-optimizer' ); ?></strong></label>
								<input id="vacimg-min-file-size" type="number" min="0" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[min_file_size]" value="<?php echo esc_attr( (string) $settings['min_file_size'] ); ?>">
								<label for="vacimg-max-file-size"><strong><?php esc_html_e( 'Maximum bytes', 'vacuum-image-optimizer' ); ?></strong></label>
								<input id="vacimg-max-file-size" type="number" min="0" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[max_file_size]" value="<?php echo esc_attr( (string) $settings['max_file_size'] ); ?>">
								<p class="description"><?php esc_html_e( 'Use 0 for no limit.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="vacimg-filename-patterns"><?php esc_html_e( 'Filename Patterns', 'vacuum-image-optimizer' ); ?></label>
							</th>
							<td>
								<textarea id="vacimg-filename-patterns" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[exclude_filename_patterns]" rows="4" class="large-text code"><?php echo esc_textarea( (string) $settings['exclude_filename_patterns'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One plain-text pattern per line. Matching filenames are skipped.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="vacimg-path-patterns"><?php esc_html_e( 'Folder / Path Patterns', 'vacuum-image-optimizer' ); ?></label>
							</th>
							<td>
								<textarea id="vacimg-path-patterns" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[exclude_path_patterns]" rows="4" class="large-text code"><?php echo esc_textarea( (string) $settings['exclude_path_patterns'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One plain-text pattern per line. Matching upload paths are skipped.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Exclude GIF Animations', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[exclude_gif]" value="0">
								<label class="vacimg-toggle">
									<input type="checkbox" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[exclude_gif]" value="1" <?php checked( ! empty( $settings['exclude_gif'] ) ); ?>>
									<span class="vacimg-toggle__slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'When enabled, GIF images are ignored by eligibility detection.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Exclude SVG Files', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[exclude_svg]" value="0">
								<label class="vacimg-toggle">
									<input type="checkbox" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[exclude_svg]" value="1" <?php checked( ! empty( $settings['exclude_svg'] ) ); ?>>
									<span class="vacimg-toggle__slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'When enabled, SVG files are ignored by eligibility detection.', 'vacuum-image-optimizer' ); ?></p>
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
