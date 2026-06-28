<?php
/**
 * Reports tab view.
 *
 * @package VacuumImageOptimizer\Admin\Views
 */

namespace VacuumImageOptimizer\Admin\Views;

use VacuumImageOptimizer\Admin\ReportExporter;
use VacuumImageOptimizer\Queue\QueueManager;
use VacuumImageOptimizer\Stats\StatsService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reports view — operational optimization analytics.
 */
class Reports {

	/**
	 * Render the reports content.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view reports.', 'vacuum-image-optimizer' ) );
		}

		$stats       = new StatsService();
		$queue_stats = ( new QueueManager() )->get_statistics();
		$summary     = $stats->get_report_summary();
		$automation  = $stats->get_auto_processed_counts();
		$last_auto   = $stats->get_last_auto_processed_at();
		$recent      = $stats->get_optimization_rows( 'recent', 20 );
		$top         = $stats->get_optimization_rows( 'savings', 20 );
		?>
		<div class="vacimg-reports">

			<?php /* Header + export. */ ?>
			<section class="vacimg-dashboard-section">
				<div class="vacimg-dashboard-section-header vacimg-reports-header">
					<div>
						<h2><?php esc_html_e( 'Reports', 'vacuum-image-optimizer' ); ?></h2>
						<p><?php esc_html_e( 'Optimization history, savings, and media insights.', 'vacuum-image-optimizer' ); ?></p>
					</div>
					<a href="<?php echo esc_url( ReportExporter::get_export_url() ); ?>" class="vacimg-button vacimg-button--secondary">
						<?php esc_html_e( 'Export CSV', 'vacuum-image-optimizer' ); ?>
					</a>
				</div>

				<div class="vacimg-kpi-grid vacimg-kpi-grid--wide">
					<div class="vacimg-stat-card vacimg-kpi-card">
						<span class="vacimg-stat-number"><?php echo esc_html( number_format_i18n( $summary['total_images'] ) ); ?></span>
						<span class="vacimg-stat-label"><?php esc_html_e( 'Total Images', 'vacuum-image-optimizer' ); ?></span>
					</div>
					<div class="vacimg-stat-card vacimg-kpi-card">
						<span class="vacimg-stat-number"><?php echo esc_html( number_format_i18n( $summary['optimized_images'] ) ); ?></span>
						<span class="vacimg-stat-label"><?php esc_html_e( 'Optimized Images', 'vacuum-image-optimizer' ); ?></span>
					</div>
					<div class="vacimg-stat-card vacimg-kpi-card">
						<span class="vacimg-stat-number"><?php echo esc_html( number_format_i18n( $summary['webp_generated'] ) ); ?></span>
						<span class="vacimg-stat-label"><?php esc_html_e( 'Generated WebP Files', 'vacuum-image-optimizer' ); ?></span>
					</div>
					<div class="vacimg-stat-card vacimg-kpi-card">
						<span class="vacimg-stat-number"><?php echo esc_html( number_format_i18n( $summary['avif_generated'] ) ); ?></span>
						<span class="vacimg-stat-label"><?php esc_html_e( 'Generated AVIF Files', 'vacuum-image-optimizer' ); ?></span>
					</div>
					<div class="vacimg-stat-card vacimg-kpi-card">
						<span class="vacimg-stat-number"><?php echo esc_html( number_format_i18n( absint( $queue_stats['completed'] ) ) ); ?></span>
						<span class="vacimg-stat-label"><?php esc_html_e( 'Queue Jobs Completed', 'vacuum-image-optimizer' ); ?></span>
					</div>
					<div class="vacimg-stat-card vacimg-kpi-card">
						<span class="vacimg-stat-number"><?php echo esc_html( number_format_i18n( absint( $queue_stats['failed'] ) ) ); ?></span>
						<span class="vacimg-stat-label"><?php esc_html_e( 'Queue Jobs Failed', 'vacuum-image-optimizer' ); ?></span>
					</div>
				</div>
			</section>

			<?php /* Storage savings + automation. */ ?>
			<section class="vacimg-dashboard-section">
				<div class="vacimg-dashboard-section-header">
					<h2><?php esc_html_e( 'Storage Savings', 'vacuum-image-optimizer' ); ?></h2>
				</div>
				<div class="vacimg-cards-row">
					<div class="vacimg-card">
						<ul class="vacimg-status-list">
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Total Original Size', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( size_format( $summary['original_size'], 2 ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Total WebP Size', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( size_format( $summary['webp_size'], 2 ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Total AVIF Size', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( size_format( $summary['avif_size'], 2 ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Total Saved Space', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( size_format( $summary['saved_space'], 2 ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Average Savings %', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $summary['average_savings'], 2 ) ); ?>%</span></li>
						</ul>
					</div>

					<div class="vacimg-card">
						<h3><?php esc_html_e( 'Automation', 'vacuum-image-optimizer' ); ?></h3>
						<ul class="vacimg-status-list">
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Auto Optimized Uploads', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $automation['total'] ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Queue Processed Uploads', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $automation['queue'] ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Immediate Mode Uploads', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $automation['immediate'] ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Last Automated Optimization', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( $this->format_date( $last_auto ) ); ?></span></li>
						</ul>
					</div>
				</div>
			</section>

			<?php /* Format distribution. */ ?>
			<section class="vacimg-dashboard-section">
				<div class="vacimg-dashboard-section-header">
					<h2><?php esc_html_e( 'Format Distribution', 'vacuum-image-optimizer' ); ?></h2>
				</div>
				<div class="vacimg-card">
					<?php
					$distribution_total = max( $summary['total_images'], $summary['webp_generated'], $summary['avif_generated'], 1 );
					$this->render_distribution_bar( __( 'Original Images', 'vacuum-image-optimizer' ), $summary['total_images'], $distribution_total );
					$this->render_distribution_bar( __( 'WebP Generated', 'vacuum-image-optimizer' ), $summary['webp_generated'], $distribution_total );
					$this->render_distribution_bar( __( 'AVIF Generated', 'vacuum-image-optimizer' ), $summary['avif_generated'], $distribution_total );
					?>
				</div>
			</section>

			<?php /* Recent activity. */ ?>
			<section class="vacimg-dashboard-section">
				<div class="vacimg-dashboard-section-header">
					<h2><?php esc_html_e( 'Recent Activity', 'vacuum-image-optimizer' ); ?></h2>
				</div>
				<div class="vacimg-card">
					<?php
					$this->render_report_table(
						$recent,
						[
							__( 'Image', 'vacuum-image-optimizer' ),
							__( 'Format', 'vacuum-image-optimizer' ),
							__( 'Original Size', 'vacuum-image-optimizer' ),
							__( 'Optimized Size', 'vacuum-image-optimizer' ),
							__( 'Savings', 'vacuum-image-optimizer' ),
							__( 'Date', 'vacuum-image-optimizer' ),
						],
						'recent'
					);
					?>
				</div>
			</section>

			<?php /* Top savings. */ ?>
			<section class="vacimg-dashboard-section">
				<div class="vacimg-dashboard-section-header">
					<h2><?php esc_html_e( 'Top Savings', 'vacuum-image-optimizer' ); ?></h2>
				</div>
				<div class="vacimg-card">
					<?php
					$this->render_report_table(
						$top,
						[
							__( 'Image', 'vacuum-image-optimizer' ),
							__( 'Original', 'vacuum-image-optimizer' ),
							__( 'Optimized', 'vacuum-image-optimizer' ),
							__( 'Saved', 'vacuum-image-optimizer' ),
							__( 'Percentage', 'vacuum-image-optimizer' ),
						],
						'savings'
					);
					?>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Render a labelled distribution progress bar.
	 *
	 * @param string $label Row label.
	 * @param int    $value Value for this row.
	 * @param int    $total Maximum value used for scaling.
	 * @return void
	 */
	private function render_distribution_bar( string $label, int $value, int $total ): void {
		$value   = max( 0, $value );
		$percent = $total > 0 ? (int) round( ( $value / $total ) * 100 ) : 0;
		?>
		<div class="vacimg-distribution-row">
			<div class="vacimg-distribution-row__head">
				<span class="vacimg-status-label"><?php echo esc_html( $label ); ?></span>
				<span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $value ) ); ?></span>
			</div>
			<div class="vacimg-progress" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $percent ); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php echo esc_attr( $label ); ?>">
				<div class="vacimg-progress__bar" style="width:<?php echo esc_attr( (string) $percent ); ?>%"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a report data table (recent activity or top savings).
	 *
	 * @param array<int, object> $rows Report rows.
	 * @param array<int, string> $headings Column headings.
	 * @param string             $type Either "recent" or "savings".
	 * @return void
	 */
	private function render_report_table( array $rows, array $headings, string $type ): void {
		if ( empty( $rows ) ) {
			printf( '<p class="vacimg-empty-state">%s</p>', esc_html__( 'No optimized images yet. Run a bulk optimization to get started.', 'vacuum-image-optimizer' ) );
			return;
		}

		echo '<div class="vacimg-table-wrap"><table class="wp-list-table widefat striped vacimg-report-table"><thead><tr>';
		foreach ( $headings as $heading ) {
			printf( '<th scope="col">%s</th>', esc_html( $heading ) );
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$attachment_id   = absint( $row->ID ?? 0 );
			$title           = '' !== (string) ( $row->post_title ?? '' ) ? (string) $row->post_title : __( 'Unknown attachment', 'vacuum-image-optimizer' );
			$source_size     = absint( $row->source_size ?? 0 );
			$webp_size       = absint( $row->webp_size ?? 0 );
			$avif_size       = absint( $row->avif_size ?? 0 );
			$savings_bytes   = absint( $row->savings_bytes ?? 0 );
			$savings_percent = (float) ( $row->savings_percent ?? 0 );
			$optimized_size  = $webp_size > 0 ? $webp_size : $avif_size;

			echo '<tr>';
			printf( '<td>%s</td>', esc_html( $title ) );

			if ( 'savings' === $type ) {
				printf( '<td>%s</td>', esc_html( size_format( $source_size, 2 ) ) );
				printf( '<td>%s</td>', esc_html( size_format( $optimized_size, 2 ) ) );
				printf( '<td>%s</td>', esc_html( size_format( $savings_bytes, 2 ) ) );
				printf( '<td>%s%%</td>', esc_html( number_format_i18n( $savings_percent, 2 ) ) );
			} else {
				printf( '<td>%s</td>', esc_html( $this->format_label( $webp_size, $avif_size ) ) );
				printf( '<td>%s</td>', esc_html( size_format( $source_size, 2 ) ) );
				printf( '<td>%s</td>', esc_html( size_format( $optimized_size, 2 ) ) );
				printf( '<td>%s</td>', esc_html( size_format( $savings_bytes, 2 ) ) );
				printf( '<td>%s</td>', esc_html( $this->format_date( (string) ( $row->optimized_at ?? '' ) ) ) );
			}

			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Build a format label from available derivative sizes.
	 *
	 * @param int $webp_size WebP size in bytes.
	 * @param int $avif_size AVIF size in bytes.
	 * @return string
	 */
	private function format_label( int $webp_size, int $avif_size ): string {
		$formats = [];

		if ( $webp_size > 0 ) {
			$formats[] = 'WebP';
		}

		if ( $avif_size > 0 ) {
			$formats[] = 'AVIF';
		}

		return empty( $formats ) ? '—' : implode( ' + ', $formats );
	}

	/**
	 * Format a stored MySQL datetime for display.
	 *
	 * @param string $datetime Stored datetime.
	 * @return string
	 */
	private function format_date( string $datetime ): string {
		$datetime = trim( $datetime );
		if ( '' === $datetime ) {
			return '—';
		}

		$timestamp = strtotime( $datetime );
		if ( false === $timestamp ) {
			return $datetime;
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}
}
