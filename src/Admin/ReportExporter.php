<?php
/**
 * CSV export for optimization reports.
 *
 * @package VacuumImageOptimizer\Admin
 */

namespace VacuumImageOptimizer\Admin;

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
				'file_name',
				'mime_type',
				'status',
				'original_size',
				'optimized_size',
				'savings_bytes',
				'savings_percent',
				'webp_file',
				'webp_status',
				'avif_file',
				'avif_status',
				'last_optimized_date',
				'reason',
			]
		);

		// Stream the report one page at a time so memory stays flat regardless of size.
		$offset = 0;
		do {
			$rows = $this->get_report_attachment_ids( $offset );

			foreach ( $rows as $attachment_id ) {
				$attachment_id = absint( $attachment_id );
				if ( $attachment_id <= 0 || '' !== (string) get_post_meta( $attachment_id, '_vacimg_generated_by', true ) ) {
					continue;
				}

				$file_path = get_attached_file( $attachment_id );
				$file_path = is_string( $file_path ) ? $file_path : '';
				$source_size = absint( get_post_meta( $attachment_id, '_vacimg_source_size', true ) );
				if ( $source_size <= 0 && '' !== $file_path && file_exists( $file_path ) ) {
					$file_size = filesize( $file_path );
					$source_size = false === $file_size ? 0 : absint( $file_size );
				}

				$webp_path = (string) get_post_meta( $attachment_id, '_vacimg_webp_path', true );
				$avif_path = (string) get_post_meta( $attachment_id, '_vacimg_avif_path', true );
				$webp_size = absint( get_post_meta( $attachment_id, '_vacimg_webp_size', true ) );
				$avif_size = absint( get_post_meta( $attachment_id, '_vacimg_avif_size', true ) );
				$status = sanitize_key( (string) get_post_meta( $attachment_id, '_vacimg_status', true ) );
				$status = '' === $status ? 'pending' : $status;

				$row = [
						$attachment_id,
						'' !== $file_path ? wp_basename( $file_path ) : sanitize_file_name( (string) get_the_title( $attachment_id ) ),
						(string) get_post_mime_type( $attachment_id ),
						$status,
						$source_size,
						$webp_size > 0 ? $webp_size : $avif_size,
						absint( get_post_meta( $attachment_id, '_vacimg_savings_bytes', true ) ),
						(float) get_post_meta( $attachment_id, '_vacimg_savings_percent', true ),
						'' !== $webp_path ? wp_basename( $webp_path ) : '',
						'' !== $webp_path && file_exists( $webp_path ) ? 'generated' : 'not_generated',
						'' !== $avif_path ? wp_basename( $avif_path ) : '',
						'' !== $avif_path && file_exists( $avif_path ) ? 'generated' : 'not_generated',
						(string) get_post_meta( $attachment_id, '_vacimg_optimized_at', true ),
						sanitize_text_field( (string) get_post_meta( $attachment_id, '_vacimg_error_message', true ) ),
				];
				fputcsv( $output, array_map( [ self::class, 'escape_csv_cell' ], $row ) );
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


	/**
	 * Prefix spreadsheet formula-leading text with an apostrophe before CSV export.
	 *
	 * @param mixed $value Cell value.
	 * @return mixed
	 */
	public static function escape_csv_cell( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}

		$leading = ltrim( $value, " \t\n\r\0\x0B\f" );
		if ( '' !== $leading && in_array( $leading[0], [ '=', '+', '-', '@' ], true ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/** Fetch source image attachment IDs in bounded pages for a memory-safe export. */
	private function get_report_attachment_ids( int $offset ): array {
		$query = new \WP_Query(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml' ],
				'posts_per_page' => self::EXPORT_PAGE_SIZE,
				'offset'         => max( 0, $offset ),
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			]
		);

		return is_array( $query->posts ) ? array_map( 'absint', $query->posts ) : [];
	}
}
