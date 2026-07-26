<?php
/**
 * Bulk Optimize tab view.
 *
 * @package VacuumImageOptimizer\Admin\Views
 */

namespace VacuumImageOptimizer\Admin\Views;

use VacuumImageOptimizer\Queue\QueueManager;
use VacuumImageOptimizer\Stats\StatsService;

defined( 'ABSPATH' ) || exit;

/**
 * Bulk Optimize view.
 */
class BulkOptimize {

	/**
	 * Render the bulk optimize content.
	 *
	 * @return void
	 */
	public function render(): void {
		$queue_manager = new QueueManager();
		$stats_service  = new StatsService();
		$stats         = $queue_manager->get_statistics();
		$failed_jobs   = $queue_manager->get_failed_jobs( 20 );
		$percent       = $this->get_progress_percent( $stats );
		?>
		<div class="vacimg-bulk" data-vacimg-queue>
			<div class="vacimg-notice" data-vacimg-queue-notice hidden></div>

			<div class="vacimg-card">
				<h2><?php esc_html_e( 'Bulk Optimization', 'vacuum-image-optimizer' ); ?></h2>
				<p><?php esc_html_e( 'Scan eligible JPEG, PNG, and WebP images, then process the queue in safe WordPress AJAX batches.', 'vacuum-image-optimizer' ); ?></p>
				<div class="vacimg-button-row">
					<button type="button" class="vacimg-button vacimg-button--secondary" data-vacimg-queue-action="scan"><?php esc_html_e( 'Optimize all unoptimized images', 'vacuum-image-optimizer' ); ?></button>
					<button type="button" class="vacimg-button vacimg-button--secondary" data-vacimg-queue-action="scan-all"><?php esc_html_e( 'Scan all images', 'vacuum-image-optimizer' ); ?></button>
					<button type="button" class="vacimg-button vacimg-button--primary" data-vacimg-queue-action="start"><?php esc_html_e( 'Start Queue', 'vacuum-image-optimizer' ); ?></button>
					<button type="button" class="vacimg-button vacimg-button--secondary" data-vacimg-queue-action="pause"><?php esc_html_e( 'Pause Queue', 'vacuum-image-optimizer' ); ?></button>
					<button type="button" class="vacimg-button vacimg-button--secondary" data-vacimg-queue-action="resume"><?php esc_html_e( 'Resume Queue', 'vacuum-image-optimizer' ); ?></button>
					<button type="button" class="vacimg-button vacimg-button--secondary" data-vacimg-queue-action="cancel"><?php esc_html_e( 'Cancel Pending Work', 'vacuum-image-optimizer' ); ?></button>
				</div>
			</div>

			<div class="vacimg-card">
				<div class="vacimg-card-header-inline">
					<h2><?php esc_html_e( 'Queue Progress', 'vacuum-image-optimizer' ); ?></h2>
					<span class="vacimg-badge" data-vacimg-queue-state><?php echo esc_html( ucfirst( (string) $stats['state'] ) ); ?></span>
				</div>
				<div class="vacimg-queue-stats">
					<?php $this->render_stat( 'total', __( 'Total', 'vacuum-image-optimizer' ), $stats['total'] ); ?>
					<?php $this->render_stat( 'pending', __( 'Pending', 'vacuum-image-optimizer' ), $stats['pending'] ); ?>
					<?php $this->render_stat( 'processing', __( 'Processing', 'vacuum-image-optimizer' ), $stats['processing'] ); ?>
					<?php $this->render_stat( 'completed', __( 'Optimized', 'vacuum-image-optimizer' ), $stats['completed'] ); ?>
					<?php $this->render_stat( 'failed', __( 'Failed', 'vacuum-image-optimizer' ), $stats['failed'] ); ?>
					<?php $this->render_stat( 'remaining', __( 'Remaining', 'vacuum-image-optimizer' ), $stats['remaining'] ?? 0 ); ?>
					<?php $this->render_stat( 'scanned', __( 'Scanned', 'vacuum-image-optimizer' ), $stats['scanned'] ?? 0 ); ?>
					<?php $this->render_static_stat( __( 'Batch Size', 'vacuum-image-optimizer' ), $stats['batch_size'] ?? 0 ); ?>
					<?php $this->render_static_stat( __( 'Elapsed', 'vacuum-image-optimizer' ), $this->format_elapsed( absint( $stats['elapsed'] ?? 0 ) ) ); ?>
					<?php $this->render_static_stat( __( 'Skipped', 'vacuum-image-optimizer' ), $stats_service->get_skipped_images() ); ?>
					<?php $this->render_static_stat( __( 'Total Saved', 'vacuum-image-optimizer' ), size_format( $stats_service->get_space_saved(), 2 ) ); ?>
				</div>
				<div class="vacimg-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $percent ); ?>">
					<div class="vacimg-progress__bar" data-vacimg-progress-bar style="width: <?php echo esc_attr( (string) $percent ); ?>%;"></div>
				</div>
				<p class="vacimg-progress-label"><strong data-vacimg-progress-percent><?php echo esc_html( (string) $percent ); ?>%</strong></p>
			</div>

			<div class="vacimg-card">
				<div class="vacimg-card-header-inline">
					<h2><?php esc_html_e( 'Failed Jobs', 'vacuum-image-optimizer' ); ?></h2>
					<div class="vacimg-button-row">
						<button type="button" class="vacimg-button vacimg-button--secondary vacimg-button--small" data-vacimg-queue-action="retry-failed"><?php esc_html_e( 'Retry Failed', 'vacuum-image-optimizer' ); ?></button>
						<button type="button" class="vacimg-button vacimg-button--secondary vacimg-button--small" data-vacimg-queue-action="clear-failed"><?php esc_html_e( 'Clear Failed', 'vacuum-image-optimizer' ); ?></button>
					</div>
				</div>
				<div class="vacimg-table-wrap">
					<table class="widefat striped vacimg-failed-jobs">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Attachment', 'vacuum-image-optimizer' ); ?></th>
								<th><?php esc_html_e( 'Error', 'vacuum-image-optimizer' ); ?></th>
								<th><?php esc_html_e( 'Attempts', 'vacuum-image-optimizer' ); ?></th>
								<th><?php esc_html_e( 'Retry', 'vacuum-image-optimizer' ); ?></th>
							</tr>
						</thead>
						<tbody data-vacimg-failed-jobs>
							<?php $this->render_failed_rows( $failed_jobs ); ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="vacimg-card" data-vacimg-migration>
			<h2><?php esc_html_e( 'Generate Missing Modern Formats', 'vacuum-image-optimizer' ); ?></h2>
			<p><?php esc_html_e( 'Prepare existing local image attachments for automatic AVIF/WebP delivery. The scan is read-only and repeated runs preserve valid derivatives.', 'vacuum-image-optimizer' ); ?></p>
			<div class="vacimg-button-row">
				<button type="button" class="vacimg-button vacimg-button--secondary" data-vacimg-migration-action="scan"><?php esc_html_e( 'Run Readiness Scan', 'vacuum-image-optimizer' ); ?></button>
				<button type="button" class="vacimg-button vacimg-button--primary" data-vacimg-migration-action="start"><?php esc_html_e( 'Start Migration', 'vacuum-image-optimizer' ); ?></button>
				<button type="button" class="vacimg-button vacimg-button--secondary" data-vacimg-migration-action="pause"><?php esc_html_e( 'Pause', 'vacuum-image-optimizer' ); ?></button>
				<button type="button" class="vacimg-button vacimg-button--secondary" data-vacimg-migration-action="resume"><?php esc_html_e( 'Resume', 'vacuum-image-optimizer' ); ?></button>
				<button type="button" class="vacimg-button vacimg-button--secondary" data-vacimg-migration-action="cancel"><?php esc_html_e( 'Cancel', 'vacuum-image-optimizer' ); ?></button>
				<button type="button" class="vacimg-button vacimg-button--secondary" data-vacimg-migration-action="retry"><?php esc_html_e( 'Retry Failed', 'vacuum-image-optimizer' ); ?></button>
			</div>
			<div class="vacimg-notice" data-vacimg-migration-notice hidden></div>
			<div class="vacimg-queue-stats">
				<?php foreach ( [ 'scanned' => __( 'Attachments scanned', 'vacuum-image-optimizer' ), 'ready' => __( 'Delivery-ready', 'vacuum-image-optimizer' ), 'partial' => __( 'Partially ready', 'vacuum-image-optimizer' ), 'missing' => __( 'No modern derivatives', 'vacuum-image-optimizer' ), 'stale' => __( 'Stale records', 'vacuum-image-optimizer' ), 'estimated_operations' => __( 'Estimated operations', 'vacuum-image-optimizer' ) ] as $key => $label ) : ?>
					<div class="vacimg-queue-stat"><span class="vacimg-queue-stat__number" data-vacimg-migration-stat="<?php echo esc_attr( $key ); ?>">0</span><span class="vacimg-queue-stat__label"><?php echo esc_html( $label ); ?></span></div>
				<?php endforeach; ?>
			</div>
			<p><?php esc_html_e( 'Migration never converts during anonymous frontend requests and never overwrites the original image.', 'vacuum-image-optimizer' ); ?></p>
		</div>

		<div class="vacimg-card" data-vacimg-diagnostics>
			<h2><?php esc_html_e( 'Delivery Diagnostics', 'vacuum-image-optimizer' ); ?></h2>
			<p><?php esc_html_e( 'Inspect the exact derivative registry used by frontend delivery for one Media Library attachment.', 'vacuum-image-optimizer' ); ?></p>
			<div class="vacimg-button-row"><input type="number" min="1" class="small-text" data-vacimg-diagnostics-id placeholder="<?php esc_attr_e( 'Attachment ID', 'vacuum-image-optimizer' ); ?>"><button type="button" class="vacimg-button vacimg-button--secondary" data-vacimg-diagnostics-action="inspect"><?php esc_html_e( 'View delivery diagnostics', 'vacuum-image-optimizer' ); ?></button></div>
			<pre class="vacimg-debug-info" data-vacimg-diagnostics-output hidden></pre>
		</div>
		<?php
	}

	/**
	 * Render a queue stat box.
	 *
	 * @param string     $key Stat key.
	 * @param string     $label Stat label.
	 * @param int|string $value Stat value.
	 * @return void
	 */
	private function render_stat( string $key, string $label, int|string $value ): void {
		?>
		<div class="vacimg-queue-stat">
			<span class="vacimg-queue-stat__number" data-vacimg-stat="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( number_format_i18n( absint( $value ) ) ); ?></span>
			<span class="vacimg-queue-stat__label"><?php echo esc_html( $label ); ?></span>
		</div>
		<?php
	}

	/**
	 * Render a queue-adjacent static stat box.
	 *
	 * @param string     $label Stat label.
	 * @param int|string $value Stat value.
	 * @return void
	 */
	private function render_static_stat( string $label, int|string $value ): void {
		?>
		<div class="vacimg-queue-stat">
			<span class="vacimg-queue-stat__number"><?php echo esc_html( is_numeric( $value ) ? number_format_i18n( absint( $value ) ) : (string) $value ); ?></span>
			<span class="vacimg-queue-stat__label"><?php echo esc_html( $label ); ?></span>
		</div>
		<?php
	}

	/**
	 * Render failed queue rows.
	 *
	 * @param array<int, object> $jobs Failed jobs.
	 * @return void
	 */
	private function render_failed_rows( array $jobs ): void {
		if ( empty( $jobs ) ) {
			?>
			<tr class="vacimg-empty-row"><td colspan="4"><?php esc_html_e( 'No failed jobs.', 'vacuum-image-optimizer' ); ?></td></tr>
			<?php
			return;
		}

		foreach ( $jobs as $job ) {
			$attachment_id = absint( $job->attachment_id ?? 0 );
			$title         = $attachment_id > 0 ? get_the_title( $attachment_id ) : __( 'Unknown attachment', 'vacuum-image-optimizer' );
			$edit_link     = $attachment_id > 0 ? get_edit_post_link( $attachment_id ) : '';
			?>
			<tr>
				<td>
					<?php if ( $edit_link ) : ?>
						<a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $title ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $title ); ?>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( wp_html_excerpt( (string) ( $job->error_message ?? '' ), 140, '…' ) ); ?></td>
				<td><?php echo esc_html( number_format_i18n( absint( $job->attempts ?? 0 ) ) ); ?></td>
				<td><button type="button" class="vacimg-button vacimg-button--secondary vacimg-button--small" data-vacimg-retry-job="<?php echo esc_attr( (string) absint( $job->id ?? 0 ) ); ?>"><?php esc_html_e( 'Retry', 'vacuum-image-optimizer' ); ?></button></td>
			</tr>
			<?php
		}
	}

	/**
	 * Calculate progress percentage.
	 *
	 * @param array<string, int|string> $stats Queue stats.
	 * @return int
	 */
	private function get_progress_percent( array $stats ): int {
		$total = absint( $stats['total'] ?? 0 );
		if ( 0 === $total ) {
			return 0;
		}

		return min( 100, (int) round( ( absint( $stats['completed'] ?? 0 ) / $total ) * 100 ) );
	}

	/** Format a bounded elapsed time label for the queue UI. */
	private function format_elapsed( int $seconds ): string {
		return human_time_diff( time() - max( 0, $seconds ), time() );
	}
}
