<?php
/**
 * Dashboard statistics service.
 *
 * @package VacuumImageOptimizer\Stats
 */

namespace VacuumImageOptimizer\Stats;

use VacuumImageOptimizer\Settings\CompressionSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Provides image optimization statistics for admin views.
 */
class StatsService {

	/**
	 * Transient key for the cached report summary.
	 *
	 * @var string
	 */
	private const REPORT_CACHE_KEY = 'vio_report_summary';

	/**
	 * Report summary cache lifetime in seconds.
	 *
	 * @var int
	 */
	private const REPORT_CACHE_TTL = 300;

	/**
	 * Get the total number of image attachments.
	 *
	 * @return int
	 */
	public function get_total_images(): int {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(p.ID) FROM {$wpdb->posts} p
				WHERE p.post_type = %s
				AND p.post_status != %s
				AND p.post_mime_type LIKE %s
				AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} gm
					WHERE gm.post_id = p.ID AND gm.meta_key = %s
				)",
				'attachment',
				'trash',
				'image/%',
				'_vio_generated_by'
			)
		);

		return absint( $count );
	}

	/**
	 * Get the number of optimized images.
	 *
	 * @return int
	 */
	public function get_optimized_images(): int {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND p.post_status != %s
				AND p.post_mime_type IN ( 'image/jpeg', 'image/png' )
				AND pm.meta_key = %s
				AND pm.meta_value = %s",
				'attachment',
				'trash',
				'_vio_status',
				'optimized'
			)
		);

		return absint( $count );
	}

	/**
	 * Get the number of pending images.
	 *
	 * @return int
	 */
	public function get_pending_images(): int {
		$supported = $this->get_supported_images();
		$optimized = $this->get_optimized_images();

		return max( 0, $supported - $optimized );
	}

	/**
	 * Get total space saved in bytes.
	 *
	 * @return int
	 */
	public function get_space_saved(): int {
		global $wpdb;

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(savings.meta_value AS UNSIGNED))
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} status_meta ON p.ID = status_meta.post_id
				INNER JOIN {$wpdb->postmeta} savings ON p.ID = savings.post_id
				WHERE p.post_type = %s
				AND p.post_status != %s
				AND status_meta.meta_key = %s
				AND status_meta.meta_value = %s
				AND savings.meta_key = %s",
				'attachment',
				'trash',
				'_vio_status',
				'optimized',
				'_vio_savings_bytes'
			)
		);

		return absint( $total );
	}

	/**
	 * Get aggregate optimization impact from existing attachment metadata.
	 *
	 * @return array<string, float|int>
	 */
	public function get_optimization_impact(): array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(CAST(source_meta.meta_value AS UNSIGNED)) AS original_size,
					SUM(CAST(webp_meta.meta_value AS UNSIGNED)) AS webp_size,
					SUM(CAST(savings_meta.meta_value AS UNSIGNED)) AS saved_space,
					AVG(CAST(percent_meta.meta_value AS DECIMAL(10,2))) AS average_savings
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} status_meta ON p.ID = status_meta.post_id
				INNER JOIN {$wpdb->postmeta} source_meta ON p.ID = source_meta.post_id
				INNER JOIN {$wpdb->postmeta} webp_meta ON p.ID = webp_meta.post_id
				INNER JOIN {$wpdb->postmeta} savings_meta ON p.ID = savings_meta.post_id
				INNER JOIN {$wpdb->postmeta} percent_meta ON p.ID = percent_meta.post_id
				WHERE p.post_type = %s
				AND p.post_status != %s
				AND status_meta.meta_key = %s
				AND status_meta.meta_value = %s
				AND source_meta.meta_key = %s
				AND webp_meta.meta_key = %s
				AND savings_meta.meta_key = %s
				AND percent_meta.meta_key = %s",
				'attachment',
				'trash',
				'_vio_status',
				'optimized',
				'_vio_source_size',
				'_vio_webp_size',
				'_vio_savings_bytes',
				'_vio_savings_percent'
			),
			ARRAY_A
		);

		return [
			'original_size'   => absint( $row['original_size'] ?? 0 ),
			'webp_size'       => absint( $row['webp_size'] ?? 0 ),
			'saved_space'     => absint( $row['saved_space'] ?? 0 ),
			'average_savings' => isset( $row['average_savings'] ) ? max( 0.0, (float) $row['average_savings'] ) : 0.0,
		];
	}

	/**
	 * Get recently optimized images.
	 *
	 * @param int $limit Maximum images to fetch.
	 * @return array<int, object>
	 */
	public function get_recent_optimized_images( int $limit = 5 ): array {
		global $wpdb;

		$limit = max( 1, min( 20, absint( $limit ) ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, optimized_meta.meta_value AS optimized_at
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} status_meta ON p.ID = status_meta.post_id
				INNER JOIN {$wpdb->postmeta} optimized_meta ON p.ID = optimized_meta.post_id
				WHERE p.post_type = %s
				AND p.post_status != %s
				AND status_meta.meta_key = %s
				AND status_meta.meta_value = %s
				AND optimized_meta.meta_key = %s
				ORDER BY optimized_meta.meta_value DESC
				LIMIT %d",
				'attachment',
				'trash',
				'_vio_status',
				'optimized',
				'_vio_optimized_at',
				$limit
			)
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Get automatic upload optimization status.
	 *
	 * @return bool
	 */
	public function get_auto_optimization_status(): bool {
		return CompressionSettings::is_auto_optimize_uploads_enabled();
	}

	/**
	 * Get automatic upload optimization mode.
	 *
	 * @return string
	 */
	public function get_auto_optimization_mode(): string {
		return CompressionSettings::get_auto_optimize_mode();
	}

	/**
	 * Get the number of auto-processed uploads today.
	 *
	 * @return int
	 */
	public function get_auto_processed_today(): int {
		global $wpdb;

		$start_of_day = gmdate( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );
		$end_of_day   = gmdate( 'Y-m-d 23:59:59', current_time( 'timestamp' ) );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} processed_meta ON p.ID = processed_meta.post_id
				WHERE p.post_type = %s
				AND p.post_status != %s
				AND processed_meta.meta_key = %s
				AND processed_meta.meta_value BETWEEN %s AND %s",
				'attachment',
				'trash',
				'_vio_auto_processed_at',
				$start_of_day,
				$end_of_day
			)
		);

		return absint( $count );
	}

	/**
	 * Get the number of attachments with a generated WebP derivative.
	 *
	 * @return int
	 */
	public function get_webp_generated_count(): int {
		return $this->count_attachments_with_positive_meta( '_vio_webp_size' );
	}

	/**
	 * Get the number of attachments with a generated AVIF derivative.
	 *
	 * @return int
	 */
	public function get_avif_generated_count(): int {
		return $this->count_attachments_with_positive_meta( '_vio_avif_size' );
	}

	/**
	 * Count Media Library attachments generated for a given format.
	 *
	 * Counts attachment records (not physical files) flagged with _vio_generated_by.
	 *
	 * @param string $format Derivative format key (webp/avif).
	 * @return int
	 */
	private function count_generated_attachments( string $format ): int {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND p.post_status != %s
				AND pm.meta_key = %s
				AND pm.meta_value = %s",
				'attachment',
				'trash',
				'_vio_generated_by',
				sanitize_key( $format )
			)
		);

		return absint( $count );
	}

	/**
	 * Get the number of generated WebP Media Library attachments.
	 *
	 * @return int
	 */
	public function get_generated_webp_attachments(): int {
		return $this->count_generated_attachments( 'webp' );
	}

	/**
	 * Get the number of generated AVIF Media Library attachments.
	 *
	 * @return int
	 */
	public function get_generated_avif_attachments(): int {
		return $this->count_generated_attachments( 'avif' );
	}

	/**
	 * Get the number of attachments that have at least one deliverable derivative.
	 *
	 * Counts attachments with a generated WebP or AVIF file, which is the pool of
	 * images that frontend delivery can serve.
	 *
	 * @return int
	 */
	public function get_deliverable_images_count(): int {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND p.post_status != %s
				AND pm.meta_key IN ( %s, %s )
				AND CAST(pm.meta_value AS UNSIGNED) > 0",
				'attachment',
				'trash',
				'_vio_webp_size',
				'_vio_avif_size'
			)
		);

		return absint( $count );
	}

	/**
	 * Get the total bytes saved by AVIF generation.
	 *
	 * @return int
	 */
	public function get_avif_total_savings(): int {
		global $wpdb;

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(savings.meta_value AS UNSIGNED))
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} savings ON p.ID = savings.post_id
				WHERE p.post_type = %s
				AND p.post_status != %s
				AND savings.meta_key = %s",
				'attachment',
				'trash',
				'_vio_avif_savings_bytes'
			)
		);

		return absint( $total );
	}

	/**
	 * Get the last automatic upload processing timestamp.
	 *
	 * @return string
	 */
	public function get_last_auto_processed_at(): string {
		$value = get_option( 'vio_last_auto_processed_at', '' );

		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * Get the total bytes of all generated AVIF derivatives.
	 *
	 * @return int
	 */
	public function get_total_avif_size(): int {
		global $wpdb;

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm.meta_value AS UNSIGNED))
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND p.post_status != %s
				AND pm.meta_key = %s",
				'attachment',
				'trash',
				'_vio_avif_size'
			)
		);

		return absint( $total );
	}

	/**
	 * Get the timestamp of the most recent optimization.
	 *
	 * @return string
	 */
	public function get_last_optimization_date(): string {
		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.meta_value
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND p.post_status != %s
				AND pm.meta_key = %s
				ORDER BY pm.meta_value DESC
				LIMIT 1",
				'attachment',
				'trash',
				'_vio_optimized_at'
			)
		);

		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * Get automation processing counts by mode.
	 *
	 * @return array{total: int, queue: int, immediate: int}
	 */
	public function get_auto_processed_counts(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS mode, COUNT(DISTINCT p.ID) AS total
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND p.post_status != %s
				AND pm.meta_key = %s
				GROUP BY pm.meta_value",
				'attachment',
				'trash',
				'_vio_auto_processed'
			),
			ARRAY_A
		);

		$counts = [
			'total'     => 0,
			'queue'     => 0,
			'immediate' => 0,
		];

		foreach ( (array) $rows as $row ) {
			$mode  = sanitize_key( (string) ( $row['mode'] ?? '' ) );
			$count = absint( $row['total'] ?? 0 );

			if ( isset( $counts[ $mode ] ) ) {
				$counts[ $mode ] = $count;
			}

			$counts['total'] += $count;
		}

		return $counts;
	}

	/**
	 * Get detailed optimization rows for reporting tables.
	 *
	 * @param string $order_by Either "recent" (by date) or "savings" (by bytes saved).
	 * @param int    $limit Maximum rows (0 = no limit, for exports).
	 * @return array<int, object>
	 */
	public function get_optimization_rows( string $order_by = 'recent', int $limit = 20 ): array {
		global $wpdb;

		$order = 'savings' === $order_by
			? 'CAST(sav.meta_value AS UNSIGNED) DESC'
			: 'opt.meta_value DESC';

		$sql = "SELECT p.ID, p.post_title,
				src.meta_value AS source_size,
				webp.meta_value AS webp_size,
				avif.meta_value AS avif_size,
				sav.meta_value AS savings_bytes,
				pct.meta_value AS savings_percent,
				opt.meta_value AS optimized_at
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} st ON p.ID = st.post_id AND st.meta_key = %s AND st.meta_value = %s
			LEFT JOIN {$wpdb->postmeta} src ON p.ID = src.post_id AND src.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} webp ON p.ID = webp.post_id AND webp.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} avif ON p.ID = avif.post_id AND avif.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} sav ON p.ID = sav.post_id AND sav.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} pct ON p.ID = pct.post_id AND pct.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} opt ON p.ID = opt.post_id AND opt.meta_key = %s
			WHERE p.post_type = %s AND p.post_status != %s
			ORDER BY {$order}";

		$params = [
			'_vio_status',
			'optimized',
			'_vio_source_size',
			'_vio_webp_size',
			'_vio_avif_size',
			'_vio_savings_bytes',
			'_vio_savings_percent',
			'_vio_optimized_at',
			'attachment',
			'trash',
		];

		if ( $limit > 0 ) {
			$sql     .= ' LIMIT %d';
			$params[] = min( 1000, absint( $limit ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Get a single page of optimization rows for streamed exports.
	 *
	 * Mirrors get_optimization_rows() but pages through the result set with
	 * LIMIT/OFFSET so large exports never load every row into memory at once.
	 *
	 * @param string $order_by Either "recent" (by date) or "savings" (by bytes saved).
	 * @param int    $offset Zero-based row offset.
	 * @param int    $limit Page size (1-1000).
	 * @return array<int, object>
	 */
	public function get_optimization_rows_page( string $order_by, int $offset, int $limit ): array {
		global $wpdb;

		$offset = max( 0, $offset );
		$limit  = max( 1, min( 1000, absint( $limit ) ) );

		$order = 'savings' === $order_by
			? 'CAST(sav.meta_value AS UNSIGNED) DESC'
			: 'opt.meta_value DESC';

		$sql = "SELECT p.ID, p.post_title,
				src.meta_value AS source_size,
				webp.meta_value AS webp_size,
				avif.meta_value AS avif_size,
				sav.meta_value AS savings_bytes,
				pct.meta_value AS savings_percent,
				opt.meta_value AS optimized_at
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} st ON p.ID = st.post_id AND st.meta_key = %s AND st.meta_value = %s
			LEFT JOIN {$wpdb->postmeta} src ON p.ID = src.post_id AND src.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} webp ON p.ID = webp.post_id AND webp.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} avif ON p.ID = avif.post_id AND avif.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} sav ON p.ID = sav.post_id AND sav.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} pct ON p.ID = pct.post_id AND pct.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} opt ON p.ID = opt.post_id AND opt.meta_key = %s
			WHERE p.post_type = %s AND p.post_status != %s
			ORDER BY {$order}, p.ID ASC
			LIMIT %d OFFSET %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				$sql,
				'_vio_status',
				'optimized',
				'_vio_source_size',
				'_vio_webp_size',
				'_vio_avif_size',
				'_vio_savings_bytes',
				'_vio_savings_percent',
				'_vio_optimized_at',
				'attachment',
				'trash',
				$limit,
				$offset
			)
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Get a cached bundle of aggregate report figures.
	 *
	 * Cached in a short-lived transient to avoid repeating the heavier
	 * aggregate queries on every report/dashboard load.
	 *
	 * @param bool $force Bypass the cache.
	 * @return array<string, int|float>
	 */
	public function get_report_summary( bool $force = false ): array {
		$cached = $force ? false : get_transient( self::REPORT_CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$impact  = $this->get_optimization_impact();
		$summary = [
			'total_images'     => $this->get_total_images(),
			'optimized_images' => $this->get_optimized_images(),
			'webp_generated'   => $this->get_webp_generated_count(),
			'avif_generated'   => $this->get_avif_generated_count(),
			'original_size'    => absint( $impact['original_size'] ),
			'webp_size'        => absint( $impact['webp_size'] ),
			'avif_size'        => $this->get_total_avif_size(),
			'saved_space'      => absint( $impact['saved_space'] ),
			'average_savings'  => (float) $impact['average_savings'],
		];

		set_transient( self::REPORT_CACHE_KEY, $summary, self::REPORT_CACHE_TTL );

		return $summary;
	}

	/**
	 * Count attachments that have a positive numeric value for a meta key.
	 *
	 * @param string $meta_key Meta key holding a byte size.
	 * @return int
	 */
	private function count_attachments_with_positive_meta( string $meta_key ): int {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND p.post_status != %s
				AND pm.meta_key = %s
				AND CAST(pm.meta_value AS UNSIGNED) > 0",
				'attachment',
				'trash',
				$meta_key
			)
		);

		return absint( $count );
	}

	/**
	 * Get the number of WebP-supported image attachments.
	 *
	 * @return int
	 */
	private function get_supported_images(): int {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID)
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status != %s
				AND post_mime_type IN ( 'image/jpeg', 'image/png' )",
				'attachment',
				'trash'
			)
		);

		return absint( $count );
	}
}