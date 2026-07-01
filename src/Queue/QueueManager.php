<?php
/**
 * Bulk optimization queue manager.
 *
 * @package VacuumImageOptimizer\Queue
 */

namespace VacuumImageOptimizer\Queue;

use VacuumImageOptimizer\Utils\ImageFormat;

defined( 'ABSPATH' ) || exit;

/**
 * Manages queue records, queue state, scans, and statistics.
 */
class QueueManager {

	/**
	 * Valid queue statuses.
	 *
	 * @var array<int, string>
	 */
	private const STATUSES = [ 'pending', 'processing', 'completed', 'failed' ];

	/**
	 * Valid queue states.
	 *
	 * @var array<int, string>
	 */
	private const STATES = [ 'idle', 'running', 'paused' ];

	/**
	 * Queue state option key.
	 *
	 * @var string
	 */
	private const OPTION_QUEUE_STATE = 'vacimg_queue_state';

	/**
	 * Fallback maximum retry attempts when no constant is defined.
	 *
	 * @var int
	 */
	private const DEFAULT_MAX_ATTEMPTS = 3;

	/**
	 * Get the maximum number of attempts a job may accumulate.
	 *
	 * @return int
	 */
	public function get_max_attempts(): int {
		$max = defined( 'VACIMG_MAX_RETRIES' ) ? (int) VACIMG_MAX_RETRIES : self::DEFAULT_MAX_ATTEMPTS;

		return max( 1, $max );
	}

	/**
	 * Get the queue table name.
	 *
	 * @return string
	 */
	public function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'vacimg_queue';
	}

	/**
	 * Get the queue table name escaped for direct SQL interpolation.
	 *
	 * @return string
	 */
	private function get_escaped_table_name(): string {
		return esc_sql( $this->get_table_name() );
	}

	/**
	 * Add an attachment to the queue.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function add_attachment( int $attachment_id ): bool {
		global $wpdb;

		$attachment_id = absint( $attachment_id );
		if ( ! $this->is_valid_attachment( $attachment_id ) || $this->has_queue_entry( $attachment_id ) ) {
			return false;
		}

		$inserted = $wpdb->insert(
			$this->get_escaped_table_name(),
			[
				'attachment_id'  => $attachment_id,
				'status'         => 'pending',
				'created_at'     => current_time( 'mysql' ),
				'started_at'     => null,
				'completed_at'   => null,
				'attempts'       => 0,
				'error_message'  => null,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%d', '%s' ]
		);

		return false !== $inserted;
	}

	/**
	 * Remove an attachment from the queue.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function remove_attachment( int $attachment_id ): bool {
		global $wpdb;

		$deleted = $wpdb->delete(
			$this->get_escaped_table_name(),
			[ 'attachment_id' => absint( $attachment_id ) ],
			[ '%d' ]
		);

		return false !== $deleted;
	}

	/**
	 * Get pending queue jobs.
	 *
	 * @param int $limit Maximum jobs to fetch.
	 * @return array<int, object>
	 */
	public function get_pending_jobs( int $limit = 10 ): array {
		return $this->get_jobs_by_status( 'pending', $limit );
	}

	/**
	 * Get failed queue jobs.
	 *
	 * @param int $limit Maximum jobs to fetch.
	 * @return array<int, object>
	 */
	public function get_failed_jobs( int $limit = 20 ): array {
		return $this->get_jobs_by_status( 'failed', $limit );
	}

	/**
	 * Get completed queue jobs.
	 *
	 * @param int $limit Maximum jobs to fetch.
	 * @return array<int, object>
	 */
	public function get_completed_jobs( int $limit = 20 ): array {
		return $this->get_jobs_by_status( 'completed', $limit );
	}

	/**
	 * Get recent queue jobs across all statuses.
	 *
	 * @param int $limit Maximum jobs to fetch.
	 * @return array<int, object>
	 */
	public function get_recent_jobs( int $limit = 5 ): array {
		global $wpdb;

		$limit = max( 1, min( 20, absint( $limit ) ) );
		$table = $this->get_escaped_table_name();
		$jobs  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY COALESCE(completed_at, started_at, created_at) DESC, id DESC LIMIT %d",
				$limit
			)
		);

		return is_array( $jobs ) ? $jobs : [];
	}

	/**
	 * Get queue statistics.
	 *
	 * @return array<string, int|string>
	 */
	public function get_statistics(): array {
		global $wpdb;

		$stats = [
			'total'      => 0,
			'pending'    => 0,
			'processing' => 0,
			'completed'  => 0,
			'failed'     => 0,
			'state'      => $this->get_queue_state(),
		];

		$table = $this->get_escaped_table_name();
		$rows  = $wpdb->get_results(
			"SELECT status, COUNT(*) AS count FROM {$table} GROUP BY status",
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
			if ( in_array( $status, self::STATUSES, true ) ) {
				$stats[ $status ] = absint( $row['count'] ?? 0 );
			}
		}

		$stats['total'] = absint( $stats['pending'] ) + absint( $stats['processing'] ) + absint( $stats['completed'] ) + absint( $stats['failed'] );

		return $stats;
	}

	/**
	 * Scan the Media Library and queue eligible attachments.
	 *
	 * @return array<string, int>
	 */
	public function scan_library(): array {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} status_meta
					ON p.ID = status_meta.post_id AND status_meta.meta_key = %s
				WHERE p.post_type = %s
				AND p.post_status != %s
					AND p.post_mime_type IN ( 'image/jpeg', 'image/png', 'image/webp' )
				AND ( status_meta.meta_value IS NULL OR status_meta.meta_value != %s )",
				'_vacimg_status',
				'attachment',
				'trash',
				'optimized'
			)
		);

		$added   = 0;
		$skipped = 0;

		foreach ( (array) $ids as $id ) {
			$attachment_id = absint( $id );
			if ( ! ImageFormat::is_supported_source_attachment( $attachment_id ) ) {
				update_post_meta( $attachment_id, '_vacimg_status', 'skipped' );
				update_post_meta( $attachment_id, '_vacimg_error_message', ImageFormat::get_skip_reason( $attachment_id ) );
				$skipped++;
				continue;
			}

			if ( $this->add_attachment( $attachment_id ) ) {
				$added++;
			} else {
				$skipped++;
			}
		}

		return [
			'added'   => $added,
			'skipped' => $skipped,
			'scanned' => count( (array) $ids ),
		];
	}

	/**
	 * Retry a failed queue job.
	 *
	 * @param int $queue_id Queue row ID.
	 * @return bool
	 */
	public function retry_job( int $queue_id ): bool {
		global $wpdb;

		// Conditional reset: only failed jobs that have not exhausted their retry
		// budget become pending again. Jobs at the limit stay failed.
		$table    = $this->get_escaped_table_name();
		$affected = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET status = %s, started_at = NULL, completed_at = NULL, error_message = NULL
				WHERE id = %d AND status = %s AND attempts < %d",
				'pending',
				absint( $queue_id ),
				'failed',
				$this->get_max_attempts()
			)
		);

		return is_int( $affected ) && $affected > 0;
	}

	/**
	 * Whether a failed job has exhausted its retry budget.
	 *
	 * @param int $queue_id Queue row ID.
	 * @return bool
	 */
	public function is_retry_exhausted( int $queue_id ): bool {
		global $wpdb;

		$table    = $this->get_escaped_table_name();
		$attempts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT attempts FROM {$table} WHERE id = %d",
				absint( $queue_id )
			)
		);

		if ( null === $attempts ) {
			return false;
		}

		return absint( $attempts ) >= $this->get_max_attempts();
	}

	/**
	 * Atomically claim a pending job for processing.
	 *
	 * The status transition is conditional on the row still being "pending", so
	 * two concurrent batch requests can never both claim the same job — only the
	 * first UPDATE matches a pending row and the loser is skipped. This is the
	 * race-safe replacement for mark_processing() in the batch processor.
	 *
	 * @param int $queue_id Queue row ID.
	 * @return bool True when this caller won the claim.
	 */
	public function claim_job( int $queue_id ): bool {
		global $wpdb;

		$table    = $this->get_escaped_table_name();
		$affected = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET status = %s, started_at = %s, completed_at = NULL
				WHERE id = %d AND status = %s",
				'processing',
				current_time( 'mysql' ),
				absint( $queue_id ),
				'pending'
			)
		);

		return is_int( $affected ) && $affected > 0;
	}

	/**
	 * Mark a queue job as processing.
	 *
	 * @param int $queue_id Queue row ID.
	 * @return bool
	 */
	public function mark_processing( int $queue_id ): bool {
		global $wpdb;

		$updated = $wpdb->update(
			$this->get_escaped_table_name(),
			[
				'status'       => 'processing',
				'started_at'   => current_time( 'mysql' ),
				'completed_at' => null,
			],
			[ 'id' => absint( $queue_id ) ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $updated;
	}

	/**
	 * Mark a queue job as completed.
	 *
	 * @param int $queue_id Queue row ID.
	 * @return bool
	 */
	public function mark_completed( int $queue_id ): bool {
		global $wpdb;

		$updated = $wpdb->update(
			$this->get_escaped_table_name(),
			[
				'status'        => 'completed',
				'completed_at'  => current_time( 'mysql' ),
				'error_message' => null,
			],
			[ 'id' => absint( $queue_id ) ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $updated;
	}

	/**
	 * Mark a queue job as failed and increment attempts.
	 *
	 * @param int    $queue_id Queue row ID.
	 * @param string $message Error message.
	 * @return bool
	 */
	public function mark_failed( int $queue_id, string $message ): bool {
		global $wpdb;

		$table   = $this->get_escaped_table_name();
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET status = %s, completed_at = %s, attempts = attempts + 1, error_message = %s
				WHERE id = %d",
				'failed',
				current_time( 'mysql' ),
				sanitize_text_field( $message ),
				absint( $queue_id )
			)
		);

		return false !== $updated;
	}

	/**
	 * Reset stale processing rows to pending.
	 *
	 * @return void
	 */
	public function reset_processing_jobs(): void {
		global $wpdb;

		$wpdb->update(
			$this->get_escaped_table_name(),
			[
				'status'     => 'pending',
				'started_at' => null,
			],
			[ 'status' => 'processing' ],
			[ '%s', '%s' ],
			[ '%s' ]
		);
	}

	/**
	 * Get the queue state.
	 *
	 * @return string
	 */
	public function get_queue_state(): string {
		$state = sanitize_key( (string) get_option( self::OPTION_QUEUE_STATE, 'idle' ) );

		return in_array( $state, self::STATES, true ) ? $state : 'idle';
	}

	/**
	 * Set the queue state.
	 *
	 * @param string $state Queue state.
	 * @return bool
	 */
	public function set_queue_state( string $state ): bool {
		$state = sanitize_key( $state );
		if ( ! in_array( $state, self::STATES, true ) ) {
			return false;
		}

		return update_option( self::OPTION_QUEUE_STATE, $state );
	}

	/**
	 * Check whether an attachment is valid and queue-eligible.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function is_valid_attachment( int $attachment_id ): bool {
		$attachment_id = absint( $attachment_id );
		if ( 0 === $attachment_id ) {
			return false;
		}

		$post = get_post( $attachment_id );
		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type || 'trash' === $post->post_status ) {
			return false;
		}

		if ( ! ImageFormat::is_supported_source_attachment( $attachment_id ) ) {
			return false;
		}

		return 'optimized' !== (string) get_post_meta( $attachment_id, '_vacimg_status', true );
	}

	/**
	 * Check whether an attachment is already in the queue.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function has_queue_entry( int $attachment_id ): bool {
		global $wpdb;

		$table = $this->get_escaped_table_name();
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE attachment_id = %d AND status IN ( 'pending', 'processing', 'completed', 'failed' )",
				absint( $attachment_id )
			)
		);

		return absint( $count ) > 0;
	}

	/**
	 * Get queue jobs by status.
	 *
	 * @param string $status Queue status.
	 * @param int    $limit Maximum jobs to fetch.
	 * @return array<int, object>
	 */
	private function get_jobs_by_status( string $status, int $limit ): array {
		global $wpdb;

		$status = sanitize_key( $status );
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return [];
		}

		$limit = max( 1, min( 100, absint( $limit ) ) );

		$table = $this->get_escaped_table_name();
		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at ASC, id ASC LIMIT %d",
				$status,
				$limit
			)
		);

		return is_array( $jobs ) ? $jobs : [];
	}
}
