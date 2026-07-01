<?php
/**
 * Dashboard tab view.
 *
 * @package VacuumImageOptimizer\Admin\Views
 */

namespace VacuumImageOptimizer\Admin\Views;

use VacuumImageOptimizer\Settings\CompressionSettings;
use VacuumImageOptimizer\Queue\QueueManager;
use VacuumImageOptimizer\Stats\StatsService;
use VacuumImageOptimizer\Utils\SystemCheck;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard view.
 */
class Dashboard {

	/**
	 * Render the dashboard content.
	 *
	 * @return void
	 */
	public function render(): void {
		$stats         = new StatsService();
		$queue_manager = new QueueManager();
		$status        = SystemCheck::get_status();
		$settings      = CompressionSettings::get();
		$queue_stats   = $queue_manager->get_statistics();
		$impact        = $stats->get_optimization_impact();
		$auto_status   = $stats->get_auto_optimization_status();
		$auto_mode     = $stats->get_auto_optimization_mode();
		$delivery_on   = CompressionSettings::is_frontend_delivery_enabled();
		$preferred     = CompressionSettings::get_preferred_format();
		$format_labels = [
			'auto' => __( 'Auto', 'vacuum-image-optimizer' ),
			'avif' => __( 'AVIF', 'vacuum-image-optimizer' ),
			'webp' => __( 'WebP', 'vacuum-image-optimizer' ),
		];

		$queue_total     = absint( $queue_stats['total'] );
		$queue_completed = absint( $queue_stats['completed'] );
		$queue_percent   = $queue_total > 0 ? (int) round( ( $queue_completed / $queue_total ) * 100 ) : 0;
		?>
		<div class="vacimg-dashboard">

			<?php /* 1. Hero summary. */ ?>
			<section class="vacimg-dashboard-hero">
				<div class="vacimg-dashboard-hero__top">
					<div class="vacimg-dashboard-hero__content">
						<div class="vacimg-dashboard-hero__head">
							<img class="vacimg-dashboard-hero__logo" src="<?php echo esc_url( VACIMG_PLUGIN_URL . 'assets/branding/icon.svg' ); ?>" width="56" height="56" alt="<?php esc_attr_e( 'Vacuum Image Optimizer logo', 'vacuum-image-optimizer' ); ?>">
							<h2 class="vacimg-dashboard-hero__title"><?php esc_html_e( 'Vacuum Image Optimizer', 'vacuum-image-optimizer' ); ?></h2>
						</div>
						<p class="vacimg-dashboard-hero__lead"><?php esc_html_e( 'Optimize smarter. Deliver faster. Keep your WordPress media library clean, lightweight, and future-ready.', 'vacuum-image-optimizer' ); ?></p>
						<p class="vacimg-dashboard-hero__meta">
							<span class="vacimg-status-pill"><?php esc_html_e( 'Profile', 'vacuum-image-optimizer' ); ?> <strong><?php echo esc_html( CompressionSettings::get_profile_label( $settings['profile'] ) ); ?></strong></span>
							<span class="vacimg-status-pill"><?php esc_html_e( 'Quality', 'vacuum-image-optimizer' ); ?> <strong><?php echo esc_html( (string) $settings['quality'] ); ?>%</strong></span>
							<span class="vacimg-status-pill"><?php esc_html_e( 'Language', 'vacuum-image-optimizer' ); ?> <strong><?php echo esc_html( CompressionSettings::get_current_language_label() ); ?></strong></span>
						</p>
					</div>
					<div class="vacimg-dashboard-hero__actions">
						<a href="<?php echo esc_url( \VacuumImageOptimizer\Admin\Router::get_tab_url( 'bulk-optimize' ) ); ?>" class="vacimg-button vacimg-button--primary"><?php esc_html_e( 'Bulk Optimize', 'vacuum-image-optimizer' ); ?></a>
						<a href="<?php echo esc_url( \VacuumImageOptimizer\Admin\Router::get_tab_url( 'compression' ) ); ?>" class="vacimg-button vacimg-button--secondary"><?php esc_html_e( 'Compression Settings', 'vacuum-image-optimizer' ); ?></a>
						<a href="<?php echo esc_url( \VacuumImageOptimizer\Admin\Router::get_tab_url( 'system-status' ) ); ?>" class="vacimg-button vacimg-button--secondary"><?php esc_html_e( 'System Status', 'vacuum-image-optimizer' ); ?></a>
					</div>
				</div>
				<?php $this->render_daily_quote(); ?>
			</section>

			<?php /* 2. Main KPI cards. */ ?>
			<section class="vacimg-dashboard-section">
				<div class="vacimg-dashboard-section-header">
					<h2><?php esc_html_e( 'Overview', 'vacuum-image-optimizer' ); ?></h2>
					<p><?php esc_html_e( 'A quick snapshot of your media library and savings.', 'vacuum-image-optimizer' ); ?></p>
				</div>
				<div class="vacimg-kpi-grid">
					<div class="vacimg-stat-card vacimg-kpi-card">
						<span class="vacimg-stat-number"><?php echo esc_html( number_format_i18n( $stats->get_total_images() ) ); ?></span>
						<span class="vacimg-stat-label"><?php esc_html_e( 'Total Images Scanned', 'vacuum-image-optimizer' ); ?></span>
					</div>
					<div class="vacimg-stat-card vacimg-kpi-card">
						<span class="vacimg-stat-number"><?php echo esc_html( number_format_i18n( $stats->get_optimized_images() ) ); ?></span>
						<span class="vacimg-stat-label"><?php esc_html_e( 'Optimized Images', 'vacuum-image-optimizer' ); ?></span>
					</div>
					<div class="vacimg-stat-card vacimg-kpi-card">
						<span class="vacimg-stat-number"><?php echo esc_html( number_format_i18n( $stats->get_pending_images() ) ); ?></span>
						<span class="vacimg-stat-label"><?php esc_html_e( 'Pending Images', 'vacuum-image-optimizer' ); ?></span>
					</div>
					<div class="vacimg-stat-card vacimg-kpi-card">
						<span class="vacimg-stat-number"><?php echo esc_html( number_format_i18n( $stats->get_skipped_images() ) ); ?></span>
						<span class="vacimg-stat-label"><?php esc_html_e( 'Skipped Images', 'vacuum-image-optimizer' ); ?></span>
					</div>
					<div class="vacimg-stat-card vacimg-kpi-card">
						<span class="vacimg-stat-number"><?php echo esc_html( size_format( $stats->get_space_saved(), 2 ) ); ?></span>
						<span class="vacimg-stat-label"><?php esc_html_e( 'Space Saved', 'vacuum-image-optimizer' ); ?></span>
					</div>
					<div class="vacimg-stat-card vacimg-kpi-card">
						<span class="vacimg-stat-number vacimg-stat-number--text"><?php echo esc_html( $stats->get_last_optimization_date() ?: '—' ); ?></span>
						<span class="vacimg-stat-label"><?php esc_html_e( 'Last Optimization', 'vacuum-image-optimizer' ); ?></span>
					</div>
				</div>
			</section>

			<?php /* 3 & 4. Optimization Impact + Queue Overview. */ ?>
			<section class="vacimg-dashboard-section">
				<div class="vacimg-dashboard-section-header">
					<h2><?php esc_html_e( 'Optimization & Queue', 'vacuum-image-optimizer' ); ?></h2>
				</div>
				<div class="vacimg-cards-row">
					<div class="vacimg-card">
						<h3><?php esc_html_e( 'Optimization Impact', 'vacuum-image-optimizer' ); ?></h3>
						<ul class="vacimg-status-list">
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Total Original Size', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( size_format( absint( $impact['original_size'] ), 2 ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Total WebP Size', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( size_format( absint( $impact['webp_size'] ), 2 ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Total Saved Space', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( size_format( absint( $impact['saved_space'] ), 2 ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Average Savings %', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( (float) $impact['average_savings'], 2 ) ); ?>%</span></li>
						</ul>
					</div>

					<div class="vacimg-card">
						<h3><?php esc_html_e( 'Queue Overview', 'vacuum-image-optimizer' ); ?></h3>
						<ul class="vacimg-status-list">
							<li><span class="vacimg-status-label"><?php esc_html_e( 'State', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-badge"><?php echo esc_html( ucfirst( (string) $queue_stats['state'] ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Pending', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( absint( $queue_stats['pending'] ) ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Completed', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $queue_completed ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Failed', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( absint( $queue_stats['failed'] ) ) ); ?></span></li>
						</ul>
						<?php if ( $queue_total > 0 ) : ?>
							<div class="vacimg-progress" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $queue_percent ); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php esc_attr_e( 'Queue completion', 'vacuum-image-optimizer' ); ?>">
								<div class="vacimg-progress__bar" style="width:<?php echo esc_attr( (string) $queue_percent ); ?>%"></div>
							</div>
							<p class="vacimg-progress-label">
								<?php
								printf(
									/* translators: 1: percent complete, 2: completed count, 3: total count. */
									esc_html__( '%1$d%% complete (%2$s of %3$s)', 'vacuum-image-optimizer' ),
									absint( $queue_percent ),
									esc_html( number_format_i18n( $queue_completed ) ),
									esc_html( number_format_i18n( $queue_total ) )
								);
								?>
							</p>
						<?php else : ?>
							<p class="vacimg-empty-state"><?php esc_html_e( 'No queue activity yet.', 'vacuum-image-optimizer' ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</section>

			<?php /* 5. Format Generation. */ ?>
			<section class="vacimg-dashboard-section">
				<div class="vacimg-dashboard-section-header">
					<h2><?php esc_html_e( 'Format Generation', 'vacuum-image-optimizer' ); ?></h2>
				</div>
				<div class="vacimg-card">
					<ul class="vacimg-status-list">
						<li><span class="vacimg-status-label"><?php esc_html_e( 'WebP Generated', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $stats->get_webp_generated_count() ) ); ?></span></li>
						<li><span class="vacimg-status-label"><?php esc_html_e( 'AVIF Generated', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $stats->get_avif_generated_count() ) ); ?></span></li>
						<li><span class="vacimg-status-label"><?php esc_html_e( 'AVIF Files Generated', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $stats->get_avif_generated_count() ) ); ?></span></li>
						<li><span class="vacimg-status-label"><?php esc_html_e( 'Generated WebP Attachments', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $stats->get_generated_webp_attachments() ) ); ?></span></li>
						<li><span class="vacimg-status-label"><?php esc_html_e( 'Generated AVIF Attachments', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $stats->get_generated_avif_attachments() ) ); ?></span></li>
						<li><span class="vacimg-status-label"><?php esc_html_e( 'Total AVIF Savings', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( size_format( $stats->get_avif_total_savings(), 2 ) ); ?></span></li>
					</ul>
				</div>
			</section>

			<?php /* 6. Automation & Delivery. */ ?>
			<section class="vacimg-dashboard-section">
				<div class="vacimg-dashboard-section-header">
					<h2><?php esc_html_e( 'Automation & Delivery', 'vacuum-image-optimizer' ); ?></h2>
				</div>
				<div class="vacimg-cards-row">
					<div class="vacimg-card">
						<h3><?php esc_html_e( 'Auto Optimization', 'vacuum-image-optimizer' ); ?></h3>
						<p><?php esc_html_e( 'Automatically optimize new JPEG, PNG, and WebP uploads.', 'vacuum-image-optimizer' ); ?></p>
						<ul class="vacimg-status-list">
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Status', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-badge <?php echo esc_attr( $auto_status ? 'vacimg-badge--success' : 'vacimg-badge--error' ); ?>"><?php echo esc_html( $auto_status ? __( 'Enabled', 'vacuum-image-optimizer' ) : __( 'Disabled', 'vacuum-image-optimizer' ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Upload Mode', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( 'immediate' === $auto_mode ? __( 'Immediate', 'vacuum-image-optimizer' ) : __( 'Queue', 'vacuum-image-optimizer' ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Processed Today', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $stats->get_auto_processed_today() ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Last Auto Processed', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( $stats->get_last_auto_processed_at() ?: '—' ); ?></span></li>
						</ul>
					</div>

					<div class="vacimg-card">
						<h3><?php esc_html_e( 'Frontend Delivery', 'vacuum-image-optimizer' ); ?></h3>
						<p>
							<?php
							echo esc_html(
								$delivery_on
									? __( 'Serving generated WebP/AVIF images with safe fallback to originals.', 'vacuum-image-optimizer' )
									: __( 'Frontend delivery is currently disabled.', 'vacuum-image-optimizer' )
							);
							?>
						</p>
						<ul class="vacimg-status-list">
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Status', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-badge <?php echo esc_attr( $delivery_on ? 'vacimg-badge--success' : 'vacimg-badge--error' ); ?>"><?php echo esc_html( $delivery_on ? __( 'Enabled', 'vacuum-image-optimizer' ) : __( 'Disabled', 'vacuum-image-optimizer' ) ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Preferred Format', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( $format_labels[ $preferred ] ?? $format_labels['auto'] ); ?></span></li>
							<li><span class="vacimg-status-label"><?php esc_html_e( 'Estimated Optimized Images Available', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $stats->get_deliverable_images_count() ) ); ?></span></li>
						</ul>
					</div>
				</div>
			</section>

			<?php /* 7. Feature Status. */ ?>
			<section class="vacimg-dashboard-section">
				<div class="vacimg-dashboard-section-header">
					<h2><?php esc_html_e( 'Feature Status', 'vacuum-image-optimizer' ); ?></h2>
				</div>
				<div class="vacimg-card">
					<ul class="vacimg-status-list">
						<?php
						$feature_rows = [
							__( 'Backups', 'vacuum-image-optimizer' )       => ! empty( $settings['enable_backups'] ),
							__( 'Lazy Loading', 'vacuum-image-optimizer' )  => ! empty( $settings['enable_lazy_loading'] ),
							__( 'Safe Mode', 'vacuum-image-optimizer' )     => ! empty( $settings['safe_mode'] ),
							__( 'WebP', 'vacuum-image-optimizer' )          => ! empty( $settings['enable_webp'] ),
							__( 'GIF Exclusion', 'vacuum-image-optimizer' ) => ! empty( $settings['exclude_gif'] ),
							__( 'SVG Exclusion', 'vacuum-image-optimizer' ) => ! empty( $settings['exclude_svg'] ),
						];
						foreach ( $feature_rows as $feature_label => $feature_on ) :
							?>
							<li>
								<span class="vacimg-status-label"><?php echo esc_html( $feature_label ); ?></span>
								<span class="vacimg-badge <?php echo esc_attr( $feature_on ? 'vacimg-badge--success' : 'vacimg-badge--error' ); ?>"><?php echo esc_html( $feature_on ? __( 'Enabled', 'vacuum-image-optimizer' ) : __( 'Disabled', 'vacuum-image-optimizer' ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</section>

			<?php /* 8. Recent Activity + System Health. */ ?>
			<section class="vacimg-dashboard-section">
				<div class="vacimg-dashboard-section-header">
					<h2><?php esc_html_e( 'Recent Activity', 'vacuum-image-optimizer' ); ?></h2>
				</div>
				<div class="vacimg-cards-row">
					<div class="vacimg-card">
						<h3><?php esc_html_e( 'Activity Log', 'vacuum-image-optimizer' ); ?></h3>
						<h4><?php esc_html_e( 'Last Optimized Images', 'vacuum-image-optimizer' ); ?></h4>
						<?php $this->render_recent_optimized_images( $stats->get_recent_optimized_images( 5 ) ); ?>
						<h4><?php esc_html_e( 'Recent Queue Activity', 'vacuum-image-optimizer' ); ?></h4>
						<?php $this->render_recent_queue_activity( $queue_manager->get_recent_jobs( 5 ) ); ?>
					</div>

					<div class="vacimg-card">
						<h3><?php esc_html_e( 'System Health', 'vacuum-image-optimizer' ); ?></h3>
						<ul class="vacimg-health-list">
							<?php $this->render_health_item( __( 'GD Available', 'vacuum-image-optimizer' ), $status['gd'] ); ?>
							<?php $this->render_health_item( __( 'Imagick Available', 'vacuum-image-optimizer' ), $status['imagick'] ); ?>
							<?php $this->render_health_item( __( 'WebP via Imagick', 'vacuum-image-optimizer' ), $status['webp_imagick'] ); ?>
							<?php $this->render_health_item( __( 'WebP via GD', 'vacuum-image-optimizer' ), $status['webp_gd'] ); ?>
							<?php $this->render_health_item( __( 'WebP Generation Available', 'vacuum-image-optimizer' ), $status['webp_support'] ); ?>
							<?php $this->render_health_item( __( 'AVIF Supported', 'vacuum-image-optimizer' ), $status['avif_support'] ); ?>
							<?php $this->render_health_item( __( 'Uploads Writable', 'vacuum-image-optimizer' ), $status['upload_writable'] ); ?>
						</ul>
					</div>
				</div>
			</section>

			<?php /* Reports summary. */ ?>
			<section class="vacimg-dashboard-section">
				<div class="vacimg-dashboard-section-header">
					<h2><?php esc_html_e( 'Reports', 'vacuum-image-optimizer' ); ?></h2>
				</div>
				<div class="vacimg-card">
					<ul class="vacimg-status-list">
						<li><span class="vacimg-status-label"><?php esc_html_e( 'Total Saved Space', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( size_format( $stats->get_space_saved(), 2 ) ); ?></span></li>
						<li><span class="vacimg-status-label"><?php esc_html_e( 'Optimized Images', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( number_format_i18n( $stats->get_optimized_images() ) ); ?></span></li>
						<li><span class="vacimg-status-label"><?php esc_html_e( 'Last Optimization Date', 'vacuum-image-optimizer' ); ?></span><span class="vacimg-status-value"><?php echo esc_html( $stats->get_last_optimization_date() ?: '—' ); ?></span></li>
					</ul>
					<p>
						<a href="<?php echo esc_url( \VacuumImageOptimizer\Admin\Router::get_tab_url( 'reports' ) ); ?>" class="vacimg-button vacimg-button--secondary"><?php esc_html_e( 'View Full Reports', 'vacuum-image-optimizer' ); ?></a>
					</p>
				</div>
			</section>

			<?php /* 9. Quick Actions. */ ?>
			<section class="vacimg-dashboard-section">
				<div class="vacimg-dashboard-section-header">
					<h2><?php esc_html_e( 'Quick Actions', 'vacuum-image-optimizer' ); ?></h2>
				</div>
				<div class="vacimg-card">
					<div class="vacimg-quick-actions">
						<a href="<?php echo esc_url( \VacuumImageOptimizer\Admin\Router::get_tab_url( 'bulk-optimize' ) ); ?>" class="vacimg-button vacimg-button--primary"><?php esc_html_e( 'Bulk Optimize', 'vacuum-image-optimizer' ); ?></a>
						<a href="<?php echo esc_url( \VacuumImageOptimizer\Admin\Router::get_tab_url( 'compression' ) ); ?>" class="vacimg-button vacimg-button--secondary"><?php esc_html_e( 'Compression', 'vacuum-image-optimizer' ); ?></a>
						<a href="<?php echo esc_url( \VacuumImageOptimizer\Admin\Router::get_tab_url( 'webp-avif' ) ); ?>" class="vacimg-button vacimg-button--secondary"><?php esc_html_e( 'WebP & AVIF', 'vacuum-image-optimizer' ); ?></a>
						<a href="<?php echo esc_url( \VacuumImageOptimizer\Admin\Router::get_tab_url( 'system-status' ) ); ?>" class="vacimg-button vacimg-button--secondary"><?php esc_html_e( 'System Status', 'vacuum-image-optimizer' ); ?></a>
					</div>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Render the daily philosophy quote inside the hero.
	 *
	 * Quotes are short, widely attributed, public-domain lines. The selection
	 * rotates once per day using the day of the year — no API, DB, or network.
	 *
	 * @return void
	 */
	private function render_daily_quote(): void {
		$quotes = [
			[ 'Simplicity is the ultimate sophistication.', 'Leonardo da Vinci' ],
			[ 'The impediment to action advances action.', 'Marcus Aurelius' ],
			[ 'We are what we repeatedly do.', 'Aristotle' ],
			[ 'The unexamined life is not worth living.', 'Socrates' ],
			[ 'Luck is what happens when preparation meets opportunity.', 'Seneca' ],
			[ 'It does not matter how slowly you go as long as you do not stop.', 'Confucius' ],
			[ 'He who has a why to live can bear almost any how.', 'Friedrich Nietzsche' ],
			[ 'Talent hits a target no one else can hit; genius hits a target no one else can see.', 'Arthur Schopenhauer' ],
			[ 'Who looks outside, dreams; who looks inside, awakes.', 'Carl Jung' ],
			[ 'The beginning is the most important part of the work.', 'Plato' ],
		];

		$index = absint( current_time( 'z' ) ) % count( $quotes );
		$quote = $quotes[ $index ];
		?>
		<figure class="vacimg-daily-quote">
			<p class="vacimg-daily-quote__eyebrow"><?php esc_html_e( 'Quote of the Day', 'vacuum-image-optimizer' ); ?></p>
			<blockquote class="vacimg-daily-quote__text"><?php echo esc_html( $quote[0] ); ?></blockquote>
			<figcaption>
				<cite class="vacimg-daily-quote__author">— <?php echo esc_html( $quote[1] ); ?></cite>
			</figcaption>
		</figure>
		<?php
	}

	/**
	 * Render a dashboard health checklist item.
	 *
	 * @param string $label Check label.
	 * @param bool   $pass Whether the check passed.
	 * @return void
	 */
	private function render_health_item( string $label, bool $pass ): void {
		?>
		<li class="<?php echo esc_attr( $pass ? 'is-pass' : 'is-fail' ); ?>">
			<span class="vacimg-health-icon" aria-hidden="true"><?php echo esc_html( $pass ? '✓' : '×' ); ?></span>
			<span><?php echo esc_html( $label ); ?></span>
		</li>
		<?php
	}

	/**
	 * Render recently optimized image list.
	 *
	 * @param array<int, object> $images Recent optimized images.
	 * @return void
	 */
	private function render_recent_optimized_images( array $images ): void {
		if ( empty( $images ) ) {
			printf( '<p class="vacimg-empty-state">%s</p>', esc_html__( 'No optimized images yet. Run a bulk optimization to get started.', 'vacuum-image-optimizer' ) );
			return;
		}

		echo '<ul class="vacimg-activity-list">';
		foreach ( $images as $image ) {
			$attachment_id = absint( $image->ID ?? 0 );
			$title         = $attachment_id > 0 ? get_the_title( $attachment_id ) : __( 'Unknown attachment', 'vacuum-image-optimizer' );
			$time          = isset( $image->optimized_at ) ? (string) $image->optimized_at : '';

			printf(
				'<li><span>%1$s</span><small>%2$s</small></li>',
				esc_html( $title ),
				esc_html( $time )
			);
		}
		echo '</ul>';
	}

	/**
	 * Render recent queue activity list.
	 *
	 * @param array<int, object> $jobs Recent queue jobs.
	 * @return void
	 */
	private function render_recent_queue_activity( array $jobs ): void {
		if ( empty( $jobs ) ) {
			printf( '<p class="vacimg-empty-state">%s</p>', esc_html__( 'No queue activity yet.', 'vacuum-image-optimizer' ) );
			return;
		}

		echo '<ul class="vacimg-activity-list">';
		foreach ( $jobs as $job ) {
			$attachment_id = absint( $job->attachment_id ?? 0 );
			$title         = $attachment_id > 0 ? get_the_title( $attachment_id ) : __( 'Unknown attachment', 'vacuum-image-optimizer' );
			$status        = sanitize_key( (string) ( $job->status ?? '' ) );
			$time          = (string) ( $job->completed_at ?: $job->started_at ?: $job->created_at ?: '' );

			printf(
				'<li><span>%1$s — %2$s</span><small>%3$s</small></li>',
				esc_html( $title ),
				esc_html( ucfirst( $status ) ),
				esc_html( $time )
			);
		}
		echo '</ul>';
	}
}
