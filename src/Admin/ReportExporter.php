<?php
/**
 * CSV export for optimization reports.
 *
 * @package VacuumImageOptimizer\Admin
 */

namespace VacuumImageOptimizer\Admin;

use VacuumImageOptimizer\Stats\StatsService;

defined( 'ABSPATH' ) || exit;

/**
 * Streams a CSV export of optimized attachments.
 */
class ReportExporter {

	/**
	 * Admin-post action name.
	 *
	 * @var string
	 */
	public const ACTION = 'vacimg_export_report';

	/**
	 * Nonce action name.
	 *
	 * @var string
	 */
	public const NONCE = 'vacimg_export_report';

	/**
	 * Rows fetched and streamed per page.
	 *
	 * @var int
	 */
	private const EXPORT_PAGE_SIZE = 500;

	/**
	 * Whether hooks have been registered.
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * Register the export hook.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
	}

	/**
	 * Build the nonce-protected export URL.
	 *
	 * @return string
	 */
	public static function get_export_url(): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION ),
			self::NONCE
		);
	}

	/**
	 * Handle the export request and stream a CSV download.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export reports.', 'vacuum-image-optimizer' ) );
		}

		check_admin_referer( self::NONCE );

		$stats = new StatsService();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="vacuum-image-optimizer-report-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}

		fputcsv(
			$output,
			[
				'attachment_id',
				'title',
				'status',
				'original_size',
				'webp_size',
				'avif_size',
				'savings_bytes',
				'savings_percent',
				'optimized_at',
			]
		);

		// Stream the report one page at a time so memory stays flat regardless of size.
		$offset = 0;
		do {
			$rows = $stats->get_optimization_rows_page( 'recent', $offset, self::EXPORT_PAGE_SIZE );

			foreach ( $rows as $row ) {
				fputcsv(
					$output,
					[
						absint( $row->ID ?? 0 ),
						(string) ( $row->post_title ?? '' ),
						'optimized',
						absint( $row->source_size ?? 0 ),
						absint( $row->webp_size ?? 0 ),
						absint( $row->avif_size ?? 0 ),
						absint( $row->savings_bytes ?? 0 ),
						(float) ( $row->savings_percent ?? 0 ),
						(string) ( $row->optimized_at ?? '' ),
					]
				);
			}

			// Flush each page to the client and release it from memory.
			if ( function_exists( 'flush' ) ) {
				flush();
			}

			$offset += self::EXPORT_PAGE_SIZE;
		} while ( count( $rows ) === self::EXPORT_PAGE_SIZE );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
		exit;
	}
}
