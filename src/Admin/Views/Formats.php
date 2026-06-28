<?php
/**
 * WebP & AVIF tab view.
 *
 * Read-only format status overview. This screen never duplicates settings —
 * all controls live on their own tabs (Compression, Bulk Optimize, etc.). It
 * surfaces WebP/AVIF generation status, delivery readiness, and the format
 * workflow, plus quick links to the tools that change behavior.
 *
 * @package VacuumImageOptimizer\Admin\Views
 */

namespace VacuumImageOptimizer\Admin\Views;

use VacuumImageOptimizer\Admin\Router;
use VacuumImageOptimizer\Settings\CompressionSettings;
use VacuumImageOptimizer\Stats\StatsService;
use VacuumImageOptimizer\Utils\SystemCheck;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WebP & AVIF status overview view.
 */
class Formats {

	/**
	 * Render the formats status content.
	 *
	 * @return void
	 */
	public function render(): void {
		$stats        = new StatsService();
		$status       = SystemCheck::get_status();
		$webp_engine  = ! empty( $status['webp_support'] );
		$avif_engine  = ! empty( $status['avif_support'] );
		$avif_enabled = CompressionSettings::is_avif_enabled();
		$backups_on   = CompressionSettings::is_backups_enabled();
		$delivery_on  = CompressionSettings::is_frontend_delivery_enabled();
		$preferred    = CompressionSettings::get_preferred_format();
		?>
		<div class="vacimg-formats">
			<div class="vacimg-card">
				<h2><?php esc_html_e( 'Format Generation', 'vacuum-image-optimizer' ); ?></h2>
				<p><?php esc_html_e( 'Monitor WebP and AVIF generation, check delivery readiness, and jump to the tools that control optimization behavior.', 'vacuum-image-optimizer' ); ?></p>
			</div>

			<div class="vacimg-format-grid">

				<!-- WebP status -->
				<section class="vacimg-card vacimg-format-card" aria-labelledby="vacimg-webp-status-heading">
					<h3 id="vacimg-webp-status-heading"><?php esc_html_e( 'WebP Status', 'vacuum-image-optimizer' ); ?></h3>
					<p><?php esc_html_e( 'WebP is generated automatically by manual actions, bulk processing, and upload automation.', 'vacuum-image-optimizer' ); ?></p>
					<ul class="vacimg-status-list">
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'Engine support', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_availability_badge( $webp_engine ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'Generated WebP files', 'vacuum-image-optimizer' ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $stats->get_webp_generated_count() ) ); ?></span>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'WebP Media Library items', 'vacuum-image-optimizer' ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $stats->get_generated_webp_attachments() ) ); ?></span>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'WebP quality', 'vacuum-image-optimizer' ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( (string) CompressionSettings::get_quality() ); ?></span>
						</li>
					</ul>
				</section>

				<!-- AVIF status -->
				<section class="vacimg-card vacimg-format-card" aria-labelledby="vacimg-avif-status-heading">
					<h3 id="vacimg-avif-status-heading"><?php esc_html_e( 'AVIF Status', 'vacuum-image-optimizer' ); ?></h3>
					<p><?php esc_html_e( 'AVIF is optional and depends on server support. Enable it from Compression Settings.', 'vacuum-image-optimizer' ); ?></p>
					<ul class="vacimg-status-list">
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'Generation', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_state_badge( $avif_enabled, __( 'Enabled', 'vacuum-image-optimizer' ), __( 'Disabled', 'vacuum-image-optimizer' ) ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'Engine support', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_availability_badge( $avif_engine ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'Generated AVIF files', 'vacuum-image-optimizer' ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $stats->get_avif_generated_count() ) ); ?></span>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'AVIF Media Library items', 'vacuum-image-optimizer' ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $stats->get_generated_avif_attachments() ) ); ?></span>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'AVIF quality', 'vacuum-image-optimizer' ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( (string) CompressionSettings::get_avif_quality() ); ?></span>
						</li>
					</ul>
				</section>

				<!-- Frontend delivery -->
				<section class="vacimg-card vacimg-format-card" aria-labelledby="vacimg-delivery-status-heading">
					<h3 id="vacimg-delivery-status-heading"><?php esc_html_e( 'Frontend Delivery', 'vacuum-image-optimizer' ); ?></h3>
					<p><?php esc_html_e( 'Frontend Delivery serves optimized formats when derivatives exist and the browser supports them.', 'vacuum-image-optimizer' ); ?></p>
					<ul class="vacimg-status-list">
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'Delivery', 'vacuum-image-optimizer' ); ?></span>
							<?php $this->render_state_badge( $delivery_on, __( 'Enabled', 'vacuum-image-optimizer' ), __( 'Disabled', 'vacuum-image-optimizer' ) ); ?>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'Preferred format', 'vacuum-image-optimizer' ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( $this->preferred_format_label( $preferred ) ); ?></span>
						</li>
						<li>
							<span class="vacimg-status-label"><?php esc_html_e( 'Deliverable images', 'vacuum-image-optimizer' ); ?></span>
							<span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $stats->get_deliverable_images_count() ) ); ?></span>
						</li>
					</ul>
				</section>

			</div>

			<!-- Format workflow -->
			<section class="vacimg-card" aria-labelledby="vacimg-format-workflow-heading">
				<h3 id="vacimg-format-workflow-heading"><?php esc_html_e( 'Format Workflow', 'vacuum-image-optimizer' ); ?></h3>
				<p><?php esc_html_e( 'How a source image flows through Vacuum Image Optimizer.', 'vacuum-image-optimizer' ); ?></p>
				<ol class="vacimg-format-workflow">
					<?php
					$this->render_workflow_step( __( 'Original image', 'vacuum-image-optimizer' ), true, __( 'Source', 'vacuum-image-optimizer' ), __( 'Source', 'vacuum-image-optimizer' ) );
					$this->render_workflow_step( __( 'Backup', 'vacuum-image-optimizer' ), $backups_on, __( 'On', 'vacuum-image-optimizer' ), __( 'Off', 'vacuum-image-optimizer' ) );
					$this->render_workflow_step( __( 'WebP', 'vacuum-image-optimizer' ), $webp_engine, __( 'Ready', 'vacuum-image-optimizer' ), __( 'Unavailable', 'vacuum-image-optimizer' ) );
					$this->render_workflow_step( __( 'AVIF', 'vacuum-image-optimizer' ), $avif_enabled && $avif_engine, __( 'Ready', 'vacuum-image-optimizer' ), __( 'Off', 'vacuum-image-optimizer' ) );
					$this->render_workflow_step( __( 'Frontend delivery', 'vacuum-image-optimizer' ), $delivery_on, __( 'On', 'vacuum-image-optimizer' ), __( 'Off', 'vacuum-image-optimizer' ) );
					?>
				</ol>
			</section>

			<!-- Quick actions -->
			<section class="vacimg-card" aria-labelledby="vacimg-format-actions-heading">
				<h3 id="vacimg-format-actions-heading"><?php esc_html_e( 'Quick Actions', 'vacuum-image-optimizer' ); ?></h3>
				<div class="vacimg-button-row">
					<a class="vacimg-button vacimg-button--primary" href="<?php echo esc_url( Router::get_tab_url( 'compression' ) ); ?>"><?php esc_html_e( 'Compression Settings', 'vacuum-image-optimizer' ); ?></a>
					<a class="vacimg-button vacimg-button--secondary" href="<?php echo esc_url( Router::get_tab_url( 'bulk-optimize' ) ); ?>"><?php esc_html_e( 'Bulk Optimize', 'vacuum-image-optimizer' ); ?></a>
					<a class="vacimg-button vacimg-button--secondary" href="<?php echo esc_url( Router::get_tab_url( 'reports' ) ); ?>"><?php esc_html_e( 'Reports', 'vacuum-image-optimizer' ); ?></a>
					<a class="vacimg-button vacimg-button--secondary" href="<?php echo esc_url( Router::get_tab_url( 'system-status' ) ); ?>"><?php esc_html_e( 'System Status', 'vacuum-image-optimizer' ); ?></a>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Render an "Available / Not available" engine badge (text + color).
	 *
	 * @param bool $available Whether the engine is available.
	 * @return void
	 */
	private function render_availability_badge( bool $available ): void {
		$this->render_state_badge(
			$available,
			__( 'Available', 'vacuum-image-optimizer' ),
			__( 'Not available', 'vacuum-image-optimizer' )
		);
	}

	/**
	 * Render an on/off state badge using shared, accessible badge styling.
	 *
	 * Status is conveyed by text first; color is a secondary cue only.
	 *
	 * @param bool   $on On/enabled state.
	 * @param string $on_text Label when on.
	 * @param string $off_text Label when off.
	 * @return void
	 */
	private function render_state_badge( bool $on, string $on_text, string $off_text ): void {
		$class = $on ? 'vacimg-badge--success' : 'vacimg-badge--error';
		$text  = $on ? $on_text : $off_text;
		printf(
			'<span class="vacimg-badge %1$s">%2$s</span>',
			esc_attr( $class ),
			esc_html( $text )
		);
	}

	/**
	 * Render a single ordered workflow step.
	 *
	 * @param string $label Step name.
	 * @param bool   $on Whether the step is active/ready.
	 * @param string $on_text Status text when active.
	 * @param string $off_text Status text when inactive.
	 * @return void
	 */
	private function render_workflow_step( string $label, bool $on, string $on_text, string $off_text ): void {
		$status_text = $on ? $on_text : $off_text;
		?>
		<li class="vacimg-format-step">
			<span class="vacimg-format-step__name"><?php echo esc_html( $label ); ?></span>
			<?php $this->render_state_badge( $on, $on_text, $off_text ); ?>
			<span class="screen-reader-text"><?php echo esc_html( $label . ': ' . $status_text ); ?></span>
		</li>
		<?php
	}

	/**
	 * Map a preferred-format key to a human label.
	 *
	 * @param string $preferred Preferred format key.
	 * @return string
	 */
	private function preferred_format_label( string $preferred ): string {
		$labels = [
			'auto' => __( 'Auto (AVIF, then WebP)', 'vacuum-image-optimizer' ),
			'avif' => __( 'AVIF', 'vacuum-image-optimizer' ),
			'webp' => __( 'WebP', 'vacuum-image-optimizer' ),
		];

		return $labels[ $preferred ] ?? $labels['auto'];
	}
}
