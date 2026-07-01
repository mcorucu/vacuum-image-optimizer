<?php
/**
 * Compression tab view.
 *
 * @package VacuumImageOptimizer\Admin\Views
 */

namespace VacuumImageOptimizer\Admin\Views;

use VacuumImageOptimizer\Admin\Onboarding;
use VacuumImageOptimizer\Settings\CompressionSettings;
use VacuumImageOptimizer\Utils\SystemCheck;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compression settings view.
 */
class Compression {

	/**
	 * Render the compression content.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage compression settings.', 'vacuum-image-optimizer' ) );
		}

		$settings = CompressionSettings::get();
		$profiles = CompressionSettings::get_profiles();
		$status   = SystemCheck::get_status();
		?>
		<div class="vacimg-compression">
			<div class="vacimg-card">
				<h2><?php esc_html_e( 'Compression Settings', 'vacuum-image-optimizer' ); ?></h2>
				<p><?php esc_html_e( 'Choose the compression profile and WebP quality used for manual WebP generation and regeneration.', 'vacuum-image-optimizer' ); ?></p>
				<p><a class="vacimg-button vacimg-button--secondary vacimg-button--small" href="<?php echo esc_url( Onboarding::get_relaunch_url() ); ?>"><?php esc_html_e( 'Launch setup wizard', 'vacuum-image-optimizer' ); ?></a></p>

				<?php settings_errors( CompressionSettings::OPTION_NAME ); ?>

				<form method="post" action="options.php" class="vacimg-settings-form">
					<?php settings_fields( CompressionSettings::OPTION_GROUP ); ?>

					<table class="form-table vacimg-form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Compression Profile', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<div class="vacimg-profile-cards" data-vacimg-profile-cards>
									<?php foreach ( $profiles as $profile => $quality ) : ?>
										<label class="vacimg-profile-card<?php echo $profile === $settings['profile'] ? ' is-active' : ''; ?>">
											<input
												type="radio"
												name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[profile]"
												value="<?php echo esc_attr( $profile ); ?>"
												data-quality="<?php echo esc_attr( (string) $quality ); ?>"
												<?php checked( $settings['profile'], $profile ); ?>
											>
											<span class="vacimg-profile-card__title"><?php echo esc_html( CompressionSettings::get_profile_label( $profile ) ); ?></span>
											<span class="vacimg-profile-card__desc"><?php echo esc_html( $this->get_profile_description( $profile, $quality ) ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Safe Mode', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[safe_mode]" value="0">
								<label>
									<input
										type="checkbox"
										name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[safe_mode]"
										value="1"
										<?php checked( ! empty( $settings['safe_mode'] ) ); ?>
									>
									<?php esc_html_e( 'Enable Safe Mode', 'vacuum-image-optimizer' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Safe Mode preserves originals, keeps backups enabled, skips risky formats, and prefers generated alternatives over destructive replacement.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="vacimg-quality"><?php esc_html_e( 'Quality', 'vacuum-image-optimizer' ); ?></label>
							</th>
							<td>
								<div class="vacimg-quality-control">
									<input
										type="range"
										id="vacimg-quality"
										name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[quality]"
										min="1"
										max="100"
										step="1"
										value="<?php echo esc_attr( (string) $settings['quality'] ); ?>"
										data-vacimg-quality-input
									>
									<strong><span data-vacimg-quality-value><?php echo esc_html( (string) $settings['quality'] ); ?></span>%</strong>
								</div>
								<p class="description"><?php esc_html_e( 'Higher values preserve more visual detail. Lower values usually reduce file size more aggressively.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'WebP Optimization', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[enable_webp]" value="0">
								<label>
									<input
										type="checkbox"
										name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[enable_webp]"
										value="1"
										<?php checked( ! empty( $settings['enable_webp'] ) ); ?>
									>
									<?php esc_html_e( 'Enable WebP generation and WebP source optimization', 'vacuum-image-optimizer' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'JPEG and PNG images generate WebP alternatives. Existing WebP uploads are recompressed in place only when the optimized result is smaller and a backup is available.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Upload Automation', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[auto_optimize_uploads]" value="0">
								<label>
									<input
										type="checkbox"
										name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[auto_optimize_uploads]"
										value="1"
										<?php checked( ! empty( $settings['auto_optimize_uploads'] ) ); ?>
									>
									<?php esc_html_e( 'Enable auto optimization for new uploads', 'vacuum-image-optimizer' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Automatically optimize new JPEG, PNG, and WebP uploads.', 'vacuum-image-optimizer' ); ?></p>

								<fieldset class="vacimg-radio-group">
									<legend class="screen-reader-text"><?php esc_html_e( 'Auto optimization mode', 'vacuum-image-optimizer' ); ?></legend>
									<label>
										<input type="radio" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[auto_optimize_mode]" value="queue" <?php checked( $settings['auto_optimize_mode'], 'queue' ); ?>>
										<?php esc_html_e( 'Queue new uploads', 'vacuum-image-optimizer' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'New uploads are added to the optimization queue. You can process them from Bulk Optimize.', 'vacuum-image-optimizer' ); ?></p>
									<label>
										<input type="radio" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[auto_optimize_mode]" value="immediate" <?php checked( $settings['auto_optimize_mode'], 'immediate' ); ?>>
										<?php esc_html_e( 'Optimize immediately', 'vacuum-image-optimizer' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'New uploads are optimized immediately after upload. Large uploads may take longer.', 'vacuum-image-optimizer' ); ?></p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'AVIF Generation', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[enable_avif]" value="0">
								<label>
									<input
										type="checkbox"
										name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[enable_avif]"
										value="1"
										<?php checked( ! empty( $settings['enable_avif'] ) ); ?>
										<?php disabled( empty( $status['avif_support'] ) ); ?>
									>
									<?php esc_html_e( 'Enable AVIF Generation', 'vacuum-image-optimizer' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'AVIF typically provides smaller file sizes than WebP but may require more processing time.', 'vacuum-image-optimizer' ); ?></p>

								<p>
									<label for="vacimg-avif-quality"><strong><?php esc_html_e( 'AVIF Quality', 'vacuum-image-optimizer' ); ?></strong></label>
								</p>
								<div class="vacimg-quality-control">
									<input
										type="range"
										id="vacimg-avif-quality"
										name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[avif_quality]"
										min="0"
										max="100"
										step="1"
										value="<?php echo esc_attr( (string) $settings['avif_quality'] ); ?>"
										data-vacimg-avif-quality-input
									>
									<strong><span data-vacimg-avif-quality-value><?php echo esc_html( (string) $settings['avif_quality'] ); ?></span>%</strong>
								</div>
								<p class="description"><?php esc_html_e( 'Lower AVIF quality values usually reduce file size further. Range: 0–100.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Resize Limits', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<label for="vacimg-max-width"><strong><?php esc_html_e( 'Max width', 'vacuum-image-optimizer' ); ?></strong></label>
								<input id="vacimg-max-width" type="number" min="0" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[max_width]" value="<?php echo esc_attr( (string) $settings['max_width'] ); ?>">
								<label for="vacimg-max-height"><strong><?php esc_html_e( 'Max height', 'vacuum-image-optimizer' ); ?></strong></label>
								<input id="vacimg-max-height" type="number" min="0" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[max_height]" value="<?php echo esc_attr( (string) $settings['max_height'] ); ?>">
								<p class="description"><?php esc_html_e( 'Use 0 to preserve original dimensions. Resize limits are stored for safe workflows and future optimization passes.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Frontend Delivery', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[enable_frontend_delivery]" value="0">
								<label>
									<input
										type="checkbox"
										name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[enable_frontend_delivery]"
										value="1"
										<?php checked( ! empty( $settings['enable_frontend_delivery'] ) ); ?>
									>
									<?php esc_html_e( 'Enable Frontend Delivery', 'vacuum-image-optimizer' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Serve generated WebP/AVIF images on the frontend. Original media files are never modified and the browser always falls back to the original when a derivative is unavailable.', 'vacuum-image-optimizer' ); ?></p>

								<p>
									<label for="vacimg-preferred-format"><strong><?php esc_html_e( 'Preferred Format', 'vacuum-image-optimizer' ); ?></strong></label>
								</p>
								<select id="vacimg-preferred-format" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[preferred_format]">
									<option value="auto" <?php selected( $settings['preferred_format'], 'auto' ); ?>><?php esc_html_e( 'Auto', 'vacuum-image-optimizer' ); ?></option>
									<option value="avif" <?php selected( $settings['preferred_format'], 'avif' ); ?>><?php esc_html_e( 'AVIF', 'vacuum-image-optimizer' ); ?></option>
									<option value="webp" <?php selected( $settings['preferred_format'], 'webp' ); ?>><?php esc_html_e( 'WebP', 'vacuum-image-optimizer' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Auto: Serve AVIF when available, otherwise WebP, otherwise the original image.', 'vacuum-image-optimizer' ); ?></p>
								<p class="description"><?php esc_html_e( 'AVIF: Prefer AVIF and fall back to the original image.', 'vacuum-image-optimizer' ); ?></p>
								<p class="description"><?php esc_html_e( 'WebP: Prefer WebP and fall back to the original image.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="vacimg-interface-language"><?php esc_html_e( 'Interface Language', 'vacuum-image-optimizer' ); ?></label>
							</th>
							<td>
								<?php
								$language_options = [
									'wordpress' => __( 'Use WordPress Language (default)', 'vacuum-image-optimizer' ),
									'en_US'     => __( 'English', 'vacuum-image-optimizer' ),
									'tr_TR'     => __( 'Turkish', 'vacuum-image-optimizer' ),
									'de_DE'     => __( 'German', 'vacuum-image-optimizer' ),
									'fr_FR'     => __( 'French', 'vacuum-image-optimizer' ),
									'es_ES'     => __( 'Spanish', 'vacuum-image-optimizer' ),
									'it_IT'     => __( 'Italian', 'vacuum-image-optimizer' ),
									'pt_PT'     => __( 'Portuguese', 'vacuum-image-optimizer' ),
									'ru_RU'     => __( 'Russian', 'vacuum-image-optimizer' ),
									'nl_NL'     => __( 'Dutch', 'vacuum-image-optimizer' ),
									'pl_PL'     => __( 'Polish', 'vacuum-image-optimizer' ),
								];
								$current_language = $settings['interface_language'];
								?>
								<select id="vacimg-interface-language" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[interface_language]">
									<?php foreach ( $language_options as $language_value => $language_label ) : ?>
										<option value="<?php echo esc_attr( $language_value ); ?>" <?php selected( $current_language, $language_value ); ?>><?php echo esc_html( $language_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Choose the admin interface language for Vacuum Image Optimizer. Missing translations fall back to English.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Settings', 'vacuum-image-optimizer' ), 'primary vacimg-button vacimg-button--primary', 'submit', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Get a profile helper description.
	 *
	 * @param string $profile Profile key.
	 * @param int    $quality Default quality.
	 * @return string
	 */
	private function get_profile_description( string $profile, int $quality ): string {
		$descriptions = [
			'lossless'   => __( 'Maximum quality, minimal compression.', 'vacuum-image-optimizer' ),
			'balanced'   => __( 'Best speed to size ratio.', 'vacuum-image-optimizer' ),
			'aggressive' => __( 'Stronger size reduction for most images.', 'vacuum-image-optimizer' ),
			'ultra'      => __( 'Smallest files with the most compression.', 'vacuum-image-optimizer' ),
		];

		$description = $descriptions[ $profile ] ?? $descriptions['balanced'];

		return sprintf(
			/* translators: 1: profile description, 2: default quality percentage. */
			__( '%1$s Default: %2$d%% quality.', 'vacuum-image-optimizer' ),
			$description,
			$quality
		);
	}
}
