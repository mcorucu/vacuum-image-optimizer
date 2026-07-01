<?php
/**
 * System Status tab view.
 *
 * @package VacuumImageOptimizer\Admin\Views
 */

namespace VacuumImageOptimizer\Admin\Views;

use VacuumImageOptimizer\Settings\CompressionSettings;
use VacuumImageOptimizer\Utils\SystemCheck;

defined( 'ABSPATH' ) || exit;

/**
 * System Status view.
 */
class SystemStatus {

	/**
	 * Render the system status content.
	 *
	 * @return void
	 */
	public function render(): void {
		$status = SystemCheck::get_status();
		?>
		<div class="vacimg-status">
			<div class="vacimg-cards-row">
				<div class="vacimg-card">
					<h2><?php esc_html_e( 'Server Environment', 'vacuum-image-optimizer' ); ?></h2>
					<ul class="vacimg-status-list">
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'PHP Version', 'vacuum-image-optimizer' ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( $status['php_version'] ); ?></span>
							<?php $this->render_badge( version_compare( $status['php_version'], '8.1', '>=' ) ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'WordPress Version', 'vacuum-image-optimizer' ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( $status['wp_version'] ); ?></span>
							<?php $this->render_badge( version_compare( $status['wp_version'], '6.2', '>=' ) ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'Memory Limit', 'vacuum-image-optimizer' ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( $status['memory_limit'] ); ?></span>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'Max Execution Time', 'vacuum-image-optimizer' ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( (string) $status['max_execution_time'] ); ?></span>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'Disk Free Space', 'vacuum-image-optimizer' ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( absint( $status['disk_free_space'] ) > 0 ? size_format( absint( $status['disk_free_space'] ), 2 ) : __( 'Not available', 'vacuum-image-optimizer' ) ); ?></span>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'Upload Max Filesize', 'vacuum-image-optimizer' ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( $status['upload_limit'] ); ?></span>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'Post Max Size', 'vacuum-image-optimizer' ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( $status['post_max_size'] ); ?></span>
						</li>
					</ul>
				</div>

				<div class="vacimg-card">
					<h2><?php esc_html_e( 'Image Engines', 'vacuum-image-optimizer' ); ?></h2>
					<ul class="vacimg-status-list">
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'GD Extension', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_badge( $status['gd'] ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'Imagick Extension', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_badge( $status['imagick'] ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'WebP Read Support', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_support_badge( $status['webp_read'] ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'WebP Write Support', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_support_badge( $status['webp_write'] ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'WebP via Imagick', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_badge( $status['webp_imagick'] ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'WebP via GD', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_badge( $status['webp_gd'] ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'AVIF via Imagick', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_badge( $status['avif_imagick'] ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'AVIF via GD', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_badge( $status['avif_gd'] ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'AVIF Read Support', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_support_badge( $status['avif_read'] ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'AVIF Write Support', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_support_badge( $status['avif_write'] ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'AVIF Support', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_support_badge( $status['avif_support'] ); ?>
						</li>
					</ul>
				</div>
			</div>

			<div class="vacimg-card">
				<h2><?php esc_html_e( 'Production Readiness', 'vacuum-image-optimizer' ); ?></h2>
				<ul class="vacimg-status-list">
					<li>
						<span class="vacimg-status-label"><?php esc_html_e( 'Queue Table', 'vacuum-image-optimizer' ); ?></span>
						<?php $this->render_badge( (bool) $status['queue_table'] ); ?>
					</li>
					<li>
						<span class="vacimg-status-label"><?php esc_html_e( 'Uploads Writable', 'vacuum-image-optimizer' ); ?></span>
						<?php $this->render_badge( (bool) $status['upload_writable'] ); ?>
					</li>
					<li>
						<span class="vacimg-status-label"><?php esc_html_e( 'Backups Writable', 'vacuum-image-optimizer' ); ?></span>
						<?php $this->render_badge( (bool) $status['backups_writable'] ); ?>
					</li>
					<li>
						<span class="vacimg-status-label"><?php esc_html_e( 'WebP Available', 'vacuum-image-optimizer' ); ?></span>
						<?php $this->render_badge( (bool) $status['webp_support'] ); ?>
					</li>
					<li>
						<span class="vacimg-status-label"><?php esc_html_e( 'AVIF Available', 'vacuum-image-optimizer' ); ?></span>
						<?php $this->render_badge( (bool) $status['avif_support'] ); ?>
					</li>
				</ul>
			</div>

			<div class="vacimg-card">
				<h2><?php esc_html_e( 'Frontend Delivery', 'vacuum-image-optimizer' ); ?></h2>
				<?php
				$frontend_enabled = CompressionSettings::is_frontend_delivery_enabled();
				$preferred_format = CompressionSettings::get_preferred_format();
				$format_labels    = [
					'auto' => __( 'Auto (AVIF → WebP → Original)', 'vacuum-image-optimizer' ),
					'avif' => __( 'AVIF', 'vacuum-image-optimizer' ),
					'webp' => __( 'WebP', 'vacuum-image-optimizer' ),
				];
				?>
				<ul class="vacimg-status-list">
					<li>
						<span class="vacimg-status-label"><?php esc_html_e( 'Status', 'vacuum-image-optimizer' ); ?></span>
						<span class="vacimg-badge <?php echo esc_attr( $frontend_enabled ? 'vacimg-badge--success' : 'vacimg-badge--error' ); ?>"><?php echo esc_html( $frontend_enabled ? __( 'Enabled', 'vacuum-image-optimizer' ) : __( 'Disabled', 'vacuum-image-optimizer' ) ); ?></span>
					</li>
					<li>
						<span class="vacimg-status-label"><?php esc_html_e( 'Preferred Format', 'vacuum-image-optimizer' ); ?></span>
						<span class="vacimg-status-value"><?php echo esc_html( $format_labels[ $preferred_format ] ?? $format_labels['auto'] ); ?></span>
					</li>
				</ul>
			</div>

			<div class="vacimg-card">
				<h2><?php esc_html_e( 'Configuration', 'vacuum-image-optimizer' ); ?></h2>
				<?php
				$config_rows = [
					__( 'Backups', 'vacuum-image-optimizer' )       => CompressionSettings::is_backups_enabled(),
					__( 'Lazy Loading', 'vacuum-image-optimizer' )  => CompressionSettings::is_lazy_loading_enabled(),
					__( 'Safe Mode', 'vacuum-image-optimizer' )     => CompressionSettings::is_safe_mode_enabled(),
					__( 'WebP', 'vacuum-image-optimizer' )          => CompressionSettings::is_webp_enabled(),
					__( 'GIF Exclusion', 'vacuum-image-optimizer' ) => CompressionSettings::is_gif_excluded(),
					__( 'SVG Exclusion', 'vacuum-image-optimizer' ) => CompressionSettings::is_svg_excluded(),
				];
				?>
				<ul class="vacimg-status-list">
					<?php foreach ( $config_rows as $config_label => $config_on ) : ?>
						<li>
							<span class="vacimg-status-label"><?php echo esc_html( $config_label ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( $config_on ? __( 'Enabled', 'vacuum-image-optimizer' ) : __( 'Disabled', 'vacuum-image-optimizer' ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="vacimg-card">
				<h2><?php esc_html_e( 'Localization Status', 'vacuum-image-optimizer' ); ?></h2>
				<?php
				$textdomain_loaded = is_textdomain_loaded( 'vacuum-image-optimizer' );
				$language_source   = CompressionSettings::is_language_overridden()
					? __( 'Override', 'vacuum-image-optimizer' )
					: __( 'WordPress', 'vacuum-image-optimizer' );
				?>
				<ul class="vacimg-status-list">
					<li>
						<span class="vacimg-status-label"><?php esc_html_e( 'Text Domain Loaded', 'vacuum-image-optimizer' ); ?></span>
						<?php $this->render_badge( $textdomain_loaded ); ?>
					</li>
					<li>
						<span class="vacimg-status-label"><?php esc_html_e( 'Current Locale', 'vacuum-image-optimizer' ); ?></span>
						<span class="vacimg-status-value"><?php echo esc_html( CompressionSettings::get_resolved_locale() ); ?></span>
					</li>
					<li>
						<span class="vacimg-status-label"><?php esc_html_e( 'Language Source', 'vacuum-image-optimizer' ); ?></span>
						<span class="vacimg-status-value"><?php echo esc_html( $language_source ); ?></span>
					</li>
				</ul>
			</div>

			<div class="vacimg-card">
				<h2><?php esc_html_e( 'Debug Info', 'vacuum-image-optimizer' ); ?></h2>
				<textarea class="vacimg-debug-info" rows="10" readonly><?php echo esc_textarea( $this->format_debug_info( $status ) ); ?></textarea>
				<p>
					<button type="button" class="vacimg-button vacimg-button--secondary" id="vacimg-copy-debug">
						<?php esc_html_e( 'Copy to Clipboard', 'vacuum-image-optimizer' ); ?>
					</button>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a status badge.
	 *
	 * @param bool $pass Whether the check passed.
	 * @return void
	 */
	private function render_badge( bool $pass ): void {
		$class = $pass ? 'vacimg-badge--success' : 'vacimg-badge--error';
		$text  = $pass ? __( 'Available', 'vacuum-image-optimizer' ) : __( 'Not available', 'vacuum-image-optimizer' );
		?>
		<span class="vacimg-badge <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $text ); ?></span>
		<?php
	}

	/**
	 * Render a support badge.
	 *
	 * @param bool $supported Whether supported.
	 * @return void
	 */
	private function render_support_badge( bool $supported ): void {
		$class = $supported ? 'vacimg-badge--success' : 'vacimg-badge--error';
		$text  = $supported ? __( 'Supported', 'vacuum-image-optimizer' ) : __( 'Not supported', 'vacuum-image-optimizer' );
		?>
		<span class="vacimg-badge <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $text ); ?></span>
		<?php
	}

	/**
	 * Format debug info as plain text.
	 *
	 * @param array $status Status array.
	 * @return string
	 */
	private function format_debug_info( array $status ): string {
		$lines = [
			'Vacuum Image Optimizer - System Report',
			'=====================================',
			'',
			'PHP Version:      ' . $status['php_version'],
			'WordPress:        ' . $status['wp_version'],
			'Memory Limit:     ' . $status['memory_limit'],
			'Max Exec Time:    ' . $status['max_execution_time'],
			'Disk Free Space:  ' . ( absint( $status['disk_free_space'] ) > 0 ? size_format( absint( $status['disk_free_space'] ), 2 ) : 'Not available' ),
			'Upload Limit:     ' . $status['upload_limit'],
			'Post Max Size:    ' . $status['post_max_size'],
			'GD:               ' . ( $status['gd'] ? 'Yes' : 'No' ),
			'Imagick:          ' . ( $status['imagick'] ? 'Yes' : 'No' ),
			'WebP Support:     ' . ( $status['webp_support'] ? 'Yes' : 'No' ),
			'WebP Read:        ' . ( $status['webp_read'] ? 'Yes' : 'No' ),
			'WebP Write:       ' . ( $status['webp_write'] ? 'Yes' : 'No' ),
			'WebP via Imagick: ' . ( $status['webp_imagick'] ? 'Yes' : 'No' ),
			'WebP via GD:      ' . ( $status['webp_gd'] ? 'Yes' : 'No' ),
			'AVIF Read:        ' . ( $status['avif_read'] ? 'Yes' : 'No' ),
			'AVIF Write:       ' . ( $status['avif_write'] ? 'Yes' : 'No' ),
			'AVIF via Imagick: ' . ( $status['avif_imagick'] ? 'Yes' : 'No' ),
			'AVIF via GD:      ' . ( $status['avif_gd'] ? 'Yes' : 'No' ),
			'AVIF Support:     ' . ( $status['avif_support'] ? 'Yes' : 'No' ),
			'Upload Writable:  ' . ( $status['upload_writable'] ? 'Yes' : 'No' ),
		];

		return implode( "\n", $lines );
	}
}
