<?php
/**
 * Expired backup cleanup service.
 *
 * @package VacuumImageOptimizer\Backup
 */

namespace VacuumImageOptimizer\Backup;

use VacuumImageOptimizer\Settings\CompressionSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the daily vacimg_cleanup_backups cron event.
 *
 * Deletes only expired original backups (files that live inside the plugin's
 * backup directory and whose age exceeds the configured retention period).
 * Originals and attachment records are never touched. When retention is
 * disabled (0 days) the service is a safe no-op so existing behavior — keeping
 * every backup forever — is preserved by default.
 */
class BackupCleanup {

	/**
	 * Cron hook handled by this service.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'vacimg_cleanup_backups';

	/**
	 * Option storing the result of the most recent cleanup run.
	 *
	 * @var string
	 */
	public const LAST_RUN_OPTION = 'vacimg_last_backup_cleanup';

	/**
	 * Backup path validator.
	 *
	 * @var BackupManager
	 */
	private BackupManager $backup_manager;

	/**
	 * Constructor.
	 *
	 * @param BackupManager|null $backup_manager Optional backup manager.
	 */
	public function __construct( ?BackupManager $backup_manager = null ) {
		$this->backup_manager = $backup_manager ?? new BackupManager();
	}

	/**
	 * Register the cron handler.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, [ $this, 'run' ] );
	}

	/**
	 * Run the backup cleanup pass.
	 *
	 * @return array<string, int|bool>
	 */
	public function run(): array {
		// Retention disabled: keep every backup, do nothing.
		if ( ! CompressionSettings::is_backup_retention_enabled() ) {
			return [
				'disabled' => true,
				'examined' => 0,
				'deleted'  => 0,
				'skipped'  => 0,
			];
		}

		$retention_days = CompressionSettings::get_backup_retention_days();
		$cutoff         = time() - ( $retention_days * DAY_IN_SECONDS );
		$batch_size     = $this->get_batch_size();

		$candidates = $this->get_backup_candidates( $batch_size );

		$deleted  = 0;
		$skipped  = 0;
		$examined = 0;

		foreach ( $candidates as $candidate ) {
			$examined++;

			$attachment_id = absint( $candidate->post_id ?? 0 );
			$backup_path   = isset( $candidate->meta_value ) ? wp_normalize_path( (string) $candidate->meta_value ) : '';

			if ( 0 === $attachment_id || '' === $backup_path ) {
				$skipped++;
				continue;
			}

			// Safety: only ever touch files inside the plugin backup directory.
			// This guarantees originals (which live in the uploads root) are never deleted.
			if ( ! $this->backup_manager->is_valid_backup_path( $backup_path ) ) {
				$skipped++;
				continue;
			}

			// Missing file: clear the now-orphaned reference but count it as skipped.
			if ( ! file_exists( $backup_path ) || ! is_file( $backup_path ) ) {
				$this->forget_backup( $attachment_id );
				$skipped++;
				continue;
			}

			$created = $this->resolve_backup_timestamp( $attachment_id, $backup_path );

			// Not yet expired: leave it untouched.
			if ( $created <= 0 || $created > $cutoff ) {
				$skipped++;
				continue;
			}

			if ( wp_delete_file( $backup_path ) || ! file_exists( $backup_path ) ) {
				$this->forget_backup( $attachment_id );
				$deleted++;
			} else {
				$skipped++;
			}
		}

		$result = [
			'disabled' => false,
			'examined' => $examined,
			'deleted'  => $deleted,
			'skipped'  => $skipped,
		];

		$this->record_run( $result, $retention_days );

		return $result;
	}

	/**
	 * Fetch attachments that still have a stored backup path.
	 *
	 * @param int $limit Maximum rows to inspect this run.
	 * @return array<int, object>
	 */
	private function get_backup_candidates( int $limit ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				AND pm.meta_value <> ''
				AND p.post_type = %s
				ORDER BY pm.post_id ASC
				LIMIT %d",
				'_vacimg_backup_path',
				'attachment',
				$limit
			)
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Resolve the creation time of a backup file.
	 *
	 * Uses the backup file's modification time (the moment it was copied) as the
	 * authoritative age, falling back to the stored optimization timestamp.
	 *
	 * @param int    $attachment_id Source attachment ID.
	 * @param string $backup_path Absolute backup file path.
	 * @return int Unix timestamp, or 0 when it cannot be determined.
	 */
	private function resolve_backup_timestamp( int $attachment_id, string $backup_path ): int {
		$mtime = @filemtime( $backup_path ); // phpcs:ignore WordPress.PHP.NoSilentErrors.Discouraged
		if ( false !== $mtime && $mtime > 0 ) {
			return (int) $mtime;
		}

		$optimized_at = (string) get_post_meta( $attachment_id, '_vacimg_optimized_at', true );
		if ( '' !== $optimized_at ) {
			$timestamp = strtotime( $optimized_at );
			if ( false !== $timestamp ) {
				return (int) $timestamp;
			}
		}

		return 0;
	}

	/**
	 * Clear backup tracking metadata once a backup file is gone.
	 *
	 * @param int $attachment_id Source attachment ID.
	 * @return void
	 */
	private function forget_backup( int $attachment_id ): void {
		delete_post_meta( $attachment_id, '_vacimg_backup_path' );
	}

	/**
	 * Persist a lightweight record of the last cleanup run.
	 *
	 * @param array<string, int|bool> $result Cleanup result.
	 * @param int                     $retention_days Retention period used.
	 * @return void
	 */
	private function record_run( array $result, int $retention_days ): void {
		update_option(
			self::LAST_RUN_OPTION,
			[
				'time'           => current_time( 'mysql' ),
				'retention_days' => $retention_days,
				'examined'       => (int) $result['examined'],
				'deleted'        => (int) $result['deleted'],
				'skipped'        => (int) $result['skipped'],
			],
			false
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf(
					'[Vacuum Image Optimizer] Backup cleanup: examined %d, deleted %d, skipped %d (retention %d days).',
					(int) $result['examined'],
					(int) $result['deleted'],
					(int) $result['skipped'],
					$retention_days
				)
			);
		}
	}

	/**
	 * Resolve the per-run batch size.
	 *
	 * @return int
	 */
	private function get_batch_size(): int {
		$batch = defined( 'VACIMG_BACKUP_CLEANUP_BATCH' ) ? (int) VACIMG_BACKUP_CLEANUP_BATCH : 500;

		return max( 1, min( 5000, $batch ) );
	}
}
