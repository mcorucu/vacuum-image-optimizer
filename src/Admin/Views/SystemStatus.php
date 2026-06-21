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
		<div class="vio-status">
			<div class="vio-cards-row">
				<div class="vio-card">
					<h2><?php esc_html_e( 'Server Environment', 'vacuum-image-optimizer' ); ?></h2>
					<ul class="vio-status-list">
						<li>
							<span class="vio-status-label"><?php esc_html_e( 'PHP Version', 'vacuum-image-optimizer' ); ?></span>
							<span class="vio-status-value"><?php echo esc_html( $status['php_version'] ); ?></span>
							<?php $this->render_badge( version_compare( $status['php_version'], '8.1', '>=' ) ); ?>
						</li>
						<li>
							<span class="vio-status-label"><?php esc_html_e( 'WordPress Version', 'vacuum-image-optimizer' ); ?></span>
							<span class="vio-status-value"><?php echo esc_html( $status['wp_version'] ); ?></span>
							<?php $this->render_badge( version_compare( $status['wp_version'], '6.2', '>=' ) ); ?>
						</li>
						<li>
							<span class="vio-status-label"><?php esc_html_e( 'Memory Limit', 'vacuum-image-optimizer' ); ?></span>
							<span class="vio-status-value"><?php echo esc_html( $status['memory_limit'] ); ?></span>
						</li>
						<li>
							<span class="vio-status-label"><?php esc_html_e( 'Upload Max Filesize', 'vacuum-image-optimizer' ); ?></span>
							<span class="vio-status-value"><?php echo esc_html( $status['upload_limit'] ); ?></span>
						</li>
						<li>
							<span class="vio-status-label"><?php esc_html_e( 'Post Max Size', 'vacuum-image-optimizer' ); ?></span>
							<span class="vio-status-value"><?php echo esc_html( $status['post_max_size'] ); ?></span>
						</li>
					</ul>
				</div>

				<div class="vio-card">
					<h2><?php esc_html_e( 'Image Engines', 'vacuum-image-optimizer' ); ?></h2>
					<ul class="vio-status-list">
						<li>
							<span class="vio-status-label"><?php esc_html_e( 'GD Extension', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_badge( $status['gd'] ); ?>
						</li>
						<li>
							<span class="vio-status-label"><?php esc_html_e( 'Imagick Extension', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_badge( $status['imagick'] ); ?>
						</li>
						<li>
							<span class="vio-status-label"><?php esc_html_e( 'WebP Support', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_badge( $status['webp_support'] ); ?>
						</li>
						<li>
							<span class="vio-status-label"><?php esc_html_e( 'WebP via Imagick', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_badge( $status['webp_imagick'] ); ?>
						</li>
						<li>
							<span class="vio-status-label"><?php esc_html_e( 'WebP via GD', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_badge( $status['webp_gd'] ); ?>
						</li>
						<li>
							<span class="vio-status-label"><?php esc_html_e( 'AVIF via Imagick', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_badge( $status['avif_imagick'] ); ?>
						</li>
						<li>
							<span class="vio-status-label"><?php esc_html_e( 'AVIF via GD', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_badge( $status['avif_gd'] ); ?>
						</li>
						<li>
							<span class="vio-status-label"><?php esc_html_e( 'AVIF Support', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_badge( $status['avif_support'] ); ?>
						</li>
					</ul>
				</div>
			</div>

			<div class="vio-card">
				<h2><?php esc_html_e( 'Production Readiness', 'vacuum-image-optimizer' ); ?></h2>
				<ul class="vio-status-list">
					<li>
						<span class="vio-status-label"><?php esc_html_e( 'Queue Table', 'vacuum-image-optimizer' ); ?></span>
						<?php $this->render_badge( (bool) $status['queue_table'] ); ?>
					</li>
					<li>
						<span class="vio-status-label"><?php esc_html_e( 'Uploads Writable', 'vacuum-image-optimizer' ); ?></span>
						<?php $this->render_badge( (bool) $status['upload_writable'] ); ?>
					</li>
					<li>
						<span class="vio-status-label"><?php esc_html_e( 'Backups Writable', 'vacuum-image-optimizer' ); ?></span>
						<?php $this->render_badge( (bool) $status['backups_writable'] ); ?>
					</li>
					<li>
						<span class="vio-status-label"><?php esc_html_e( 'WebP Available', 'vacuum-image-optimizer' ); ?></span>
						<?php $this->render_badge( (bool) $status['webp_support'] ); ?>
					</li>
					<li>
						<span class="vio-status-label"><?php esc_html_e( 'AVIF Available', 'vacuum-image-optimizer' ); ?></span>
						<?php $this->render_badge( (bool) $status['avif_support'] ); ?>
					</li>
				</ul>
			</div>

			<div class="vio-card">
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
				<ul class="vio-status-list">
					<li>
						<span class="vio-status-label"><?php esc_html_e( 'Status', 'vacuum-image-optimizer' ); ?></span>
						<span class="vio-badge <?php echo esc_attr( $frontend_enabled ? 'vio-badge--success' : 'vio-badge--error' ); ?>"><?php echo esc_html( $frontend_enabled ? __( 'Enabled', 'vacuum-image-optimizer' ) : __( 'Disabled', 'vacuum-image-optimizer' ) ); ?></span>
					</li>
					<li>
						<span class="vio-status-label"><?php esc_html_e( 'Preferred Format', 'vacuum-image-optimizer' ); ?></span>
						<span class="vio-status-value"><?php echo esc_html( $format_labels[ $preferred_format ] ?? $format_labels['auto'] ); ?></span>
					</li>
				</ul>
			</div>

			<div class="vio-card">
				<h2><?php esc_html_e( 'Configuration', 'vacuum-image-optimizer' ); ?></h2>
				<?php
				$config_rows = [
					__( 'Backups', 'vacuum-image-optimizer' )       => CompressionSettings::is_backups_enabled(),
					__( 'Lazy Loading', 'vacuum-image-optimizer' )  => CompressionSettings::is_lazy_loading_enabled(),
					__( 'GIF Exclusion', 'vacuum-image-optimizer' ) => CompressionSettings::is_gif_excluded(),
					__( 'SVG Exclusion', 'vacuum-image-optimizer' ) => CompressionSettings::is_svg_excluded(),
				];
				?>
				<ul class="vio-status-list">
					<?php foreach ( $config_rows as $config_label => $config_on ) : ?>
						<li>
							<span class="vio-status-label"><?php echo esc_html( $config_label ); ?></span>
							<span class="vio-status-value"><?php echo esc_html( $config_on ? __( 'Enabled', 'vacuum-image-optimizer' ) : __( 'Disabled', 'vacuum-image-optimizer' ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="vio-card">
				<h2><?php esc_html_e( 'Localization Status', 'vacuum-image-optimizer' ); ?></h2>
				<?php
				$textdomain_loaded = is_textdomain_loaded( 'vacuum-image-optimizer' );
				$language_source   = CompressionSettings::is_language_overridden()
					? __( 'Override', 'vacuum-image-optimizer' )
					: __( 'WordPress', 'vacuum-image-optimizer' );
				?>
				<ul class="vio-status-list">
					<li>
						<span class="vio-status-label"><?php esc_html_e( 'Text Domain Loaded', 'vacuum-image-optimizer' ); ?></span>
						<?php $this->render_badge( $textdomain_loaded ); ?>
					</li>
					<li>
						<span class="vio-status-label"><?php esc_html_e( 'Current Locale', 'vacuum-image-optimizer' ); ?></span>
						<span class="vio-status-value"><?php echo esc_html( CompressionSettings::get_resolved_locale() ); ?></span>
					</li>
					<li>
						<span class="vio-status-label"><?php esc_html_e( 'Language Source', 'vacuum-image-optimizer' ); ?></span>
						<span class="vio-status-value"><?php echo esc_html( $language_source ); ?></span>
					</li>
				</ul>
			</div>

			<div class="vio-card">
				<h2><?php esc_html_e( 'Debug Info', 'vacuum-image-optimizer' ); ?></h2>
				<textarea class="vio-debug-info" rows="10" readonly><?php echo esc_textarea( $this->format_debug_info( $status ) ); ?></textarea>
				<p>
					<button type="button" class="vio-button vio-button--secondary" id="vio-copy-debug">
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
		$class = $pass ? 'vio-badge--success' : 'vio-badge--error';
		$text  = $pass ? __( 'Available', 'vacuum-image-optimizer' ) : __( 'Not Available', 'vacuum-image-optimizer' );
		?>
		<span class="vio-badge <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $text ); ?></span>
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
			'Upload Limit:     ' . $status['upload_limit'],
			'Post Max Size:    ' . $status['post_max_size'],
			'GD:               ' . ( $status['gd'] ? 'Yes' : 'No' ),
			'Imagick:          ' . ( $status['imagick'] ? 'Yes' : 'No' ),
			'WebP Support:     ' . ( $status['webp_support'] ? 'Yes' : 'No' ),
			'WebP via Imagick: ' . ( $status['webp_imagick'] ? 'Yes' : 'No' ),
			'WebP via GD:      ' . ( $status['webp_gd'] ? 'Yes' : 'No' ),
			'AVIF via Imagick: ' . ( $status['avif_imagick'] ? 'Yes' : 'No' ),
			'AVIF via GD:      ' . ( $status['avif_gd'] ? 'Yes' : 'No' ),
			'AVIF Support:     ' . ( $status['avif_support'] ? 'Yes' : 'No' ),
			'Upload Writable:  ' . ( $status['upload_writable'] ? 'Yes' : 'No' ),
		];

		return implode( "\n", $lines );
	}
}
