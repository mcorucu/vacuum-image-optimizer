<?php
/**
 * Bulk optimization queue processor.
 *
 * @package VacuumImageOptimizer\Queue
 */

namespace VacuumImageOptimizer\Queue;

use VacuumImageOptimizer\Engine\AVIFGenerator;
use VacuumImageOptimizer\Engine\WebPGenerator;
use VacuumImageOptimizer\Settings\CompressionSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Processes queue jobs in small AJAX-triggered batches.
 */
class QueueProcessor {

	/**
	 * Queue manager.
	 *
	 * @var QueueManager
	 */
	private QueueManager $queue_manager;

	/**
	 * WebP generator.
	 *
	 * @var WebPGenerator
	 */
	private WebPGenerator $webp_generator;

	/**
	 * AVIF generator.
	 *
	 * @var AVIFGenerator
	 */
	private AVIFGenerator $avif_generator;

	/**
	 * Constructor.
	 *
	 * @param QueueManager|null  $queue_manager Queue manager.
	 * @param WebPGenerator|null $webp_generator WebP generator.
	 * @param AVIFGenerator|null $avif_generator AVIF generator.
	 */
	public function __construct( ?QueueManager $queue_manager = null, ?WebPGenerator $webp_generator = null, ?AVIFGenerator $avif_generator = null ) {
		$this->queue_manager = $queue_manager ?? new QueueManager();
		$this->webp_generator = $webp_generator ?? new WebPGenerator();
		$this->avif_generator = $avif_generator ?? new AVIFGenerator();
	}

	/**
	 * Process the next queue batch.
	 *
	 * @param int|null $batch_size Optional batch size.
	 * @return array<string, int|string|bool>
	 */
	public function process_batch( ?int $batch_size = null ): array {
		$state = $this->queue_manager->get_queue_state();
		if ( 'running' !== $state ) {
			return [
				'processed' => 0,
				'completed' => 0,
				'failed'    => 0,
				'state'     => $state,
				'finished'  => false,
			];
		}

		$batch_size = $batch_size ?? (int) VIO_QUEUE_BATCH_SIZE;
		$batch_size = max( 1, min( 50, absint( $batch_size ) ) );
		$jobs       = $this->queue_manager->get_pending_jobs( $batch_size );

		$processed = 0;
		$completed = 0;
		$failed    = 0;

		foreach ( $jobs as $job ) {
			$queue_id      = absint( $job->id ?? 0 );
			$attachment_id = absint( $job->attachment_id ?? 0 );

			if ( 0 === $queue_id || 0 === $attachment_id ) {
				continue;
			}

			// Atomically claim the job. If another concurrent batch already took
			// it, the claim fails and we skip it — no job is processed twice.
			if ( ! $this->queue_manager->claim_job( $queue_id ) ) {
				continue;
			}

			$processed++;

			try {
				$result = $this->webp_generator->generate( $attachment_id );

				if ( ! empty( $result['success'] ) ) {
					// AVIF is a parallel format: an AVIF failure must not fail the job.
					$this->maybe_generate_avif( $attachment_id );
					$this->queue_manager->mark_completed( $queue_id );
					$completed++;
					continue;
				}

				$message = isset( $result['message'] ) ? (string) $result['message'] : __( 'WebP generation failed.', 'vacuum-image-optimizer' );
				$this->queue_manager->mark_failed( $queue_id, $message );
				$failed++;
			} catch ( \Throwable $throwable ) {
				$this->queue_manager->mark_failed( $queue_id, $throwable->getMessage() );
				$failed++;
			}
		}

		$stats = $this->queue_manager->get_statistics();
		if ( 0 === absint( $stats['pending'] ) && 0 === absint( $stats['processing'] ) ) {
			$this->queue_manager->set_queue_state( 'idle' );
			$stats['state'] = 'idle';
		}

		return [
			'processed' => $processed,
			'completed' => $completed,
			'failed'    => $failed,
			'state'     => (string) $stats['state'],
			'finished'  => 'idle' === $stats['state'],
		];
	}

	/**
	 * Generate AVIF after a successful WebP job when enabled.
	 *
	 * AVIF failure is treated as a partial success and never fails the queue job.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function maybe_generate_avif( int $attachment_id ): void {
		if ( ! CompressionSettings::is_avif_enabled() ) {
			return;
		}

		try {
			$this->avif_generator->generate( $attachment_id );
		} catch ( \Throwable $throwable ) {
			update_post_meta( $attachment_id, '_vio_avif_error_message', sanitize_text_field( $throwable->getMessage() ) );
		}
	}
}