<?php
/**
 * Backup & Restore tab view.
 *
 * @package VacuumImageOptimizer\Admin\Views
 */

namespace VacuumImageOptimizer\Admin\Views;

use VacuumImageOptimizer\Backup\BackupManager;
use VacuumImageOptimizer\Settings\CompressionSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backup & Restore settings view.
 */
class BackupRestore {

	/**
	 * Render the backup & restore content.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage these settings.', 'vacuum-image-optimizer' ) );
		}

		$backup_manager = new BackupManager();
		$backup_path    = $backup_manager->get_backup_directory();
		$settings       = CompressionSettings::get();
		$backups_on     = ! empty( $settings['enable_backups'] );
		?>
		<div class="vio-backup">
			<div class="vio-card">
				<h2><?php esc_html_e( 'Backup & Restore', 'vacuum-image-optimizer' ); ?></h2>

				<?php settings_errors( CompressionSettings::OPTION_NAME ); ?>

				<?php if ( ! $backups_on ) : ?>
					<div class="notice notice-warning inline">
						<p><?php esc_html_e( 'Backups are disabled. New optimizations will not create restore points, and restore actions for images without an existing backup will be unavailable. Existing backups remain untouched.', 'vacuum-image-optimizer' ); ?></p>
					</div>
				<?php endif; ?>

				<form method="post" action="options.php" class="vio-settings-form">
					<?php settings_fields( CompressionSettings::OPTION_GROUP ); ?>

					<table class="form-table vio-form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Backups', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[enable_backups]" value="0">
								<label class="vio-toggle">
									<input type="checkbox" name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[enable_backups]" value="1" <?php checked( $backups_on ); ?>>
									<span class="vio-toggle__slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Keep a copy of each original image before generating optimized formats so it can be restored later.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Backup Retention', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<input type="number" min="0" max="3650" step="1"
									id="vio-backup-retention-days"
									name="<?php echo esc_attr( CompressionSettings::OPTION_NAME ); ?>[backup_retention_days]"
									value="<?php echo esc_attr( (string) ( $settings['backup_retention_days'] ?? 0 ) ); ?>"
									class="small-text">
								<span><?php esc_html_e( 'days', 'vacuum-image-optimizer' ); ?></span>
								<p class="description"><?php esc_html_e( 'Automatically delete original backups older than this many days during the daily cleanup. Set to 0 to keep all backups indefinitely.', 'vacuum-image-optimizer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Backup Location', 'vacuum-image-optimizer' ); ?></th>
							<td>
								<code><?php echo esc_html( '' === $backup_path ? __( 'Uploads directory unavailable', 'vacuum-image-optimizer' ) : trailingslashit( $backup_path ) ); ?></code>
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
