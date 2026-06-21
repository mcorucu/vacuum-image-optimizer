<?php
/**
 * Queue AJAX endpoints.
 *
 * @package VacuumImageOptimizer\Queue
 */

namespace VacuumImageOptimizer\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles WordPress AJAX queue requests.
 */
class AjaxController {

	/**
	 * Queue manager.
	 *
	 * @var QueueManager
	 */
	private QueueManager $queue_manager;

	/**
	 * Queue processor.
	 *
	 * @var QueueProcessor
	 */
	private QueueProcessor $queue_processor;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->queue_manager   = new QueueManager();
		$this->queue_processor = new QueueProcessor( $this->queue_manager );
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_vio_scan_library', [ $this, 'scan_library' ] );
		add_action( 'wp_ajax_vio_start_queue', [ $this, 'start_queue' ] );
		add_action( 'wp_ajax_vio_pause_queue', [ $this, 'pause_queue' ] );
		add_action( 'wp_ajax_vio_resume_queue', [ $this, 'resume_queue' ] );
		add_action( 'wp_ajax_vio_process_batch', [ $this, 'process_batch' ] );
		add_action( 'wp_ajax_vio_queue_status', [ $this, 'queue_status' ] );
		add_action( 'wp_ajax_vio_retry_queue_job', [ $this, 'retry_queue_job' ] );
	}

	/**
	 * Scan library endpoint.
	 *
	 * @return void
	 */
	public function scan_library(): void {
		$this->verify_request();

		$result = $this->queue_manager->scan_library();
		wp_send_json_success(
			[
				'message' => sprintf(
					/* translators: %d: number of queued images. */
					_n( '%d image added to queue.', '%d images added to queue.', $result['added'], 'vacuum-image-optimizer' ),
					$result['added']
				),
				'scan'    => $result,
				'stats'   => $this->queue_manager->get_statistics(),
				'failed'  => $this->format_failed_jobs(),
			]
		);
	}

	/**
	 * Start queue endpoint.
	 *
	 * @return void
	 */
	public function start_queue(): void {
		$this->verify_request();
		$this->queue_manager->reset_processing_jobs();
		$this->queue_manager->set_queue_state( 'running' );
		$this->queue_status();
	}

	/**
	 * Pause queue endpoint.
	 *
	 * @return void
	 */
	public function pause_queue(): void {
		$this->verify_request();
		$this->queue_manager->set_queue_state( 'paused' );
		$this->queue_status();
	}

	/**
	 * Resume queue endpoint.
	 *
	 * @return void
	 */
	public function resume_queue(): void {
		$this->verify_request();
		$this->queue_manager->reset_processing_jobs();
		$this->queue_manager->set_queue_state( 'running' );
		$this->queue_status();
	}

	/**
	 * Process batch endpoint.
	 *
	 * @return void
	 */
	public function process_batch(): void {
		$this->verify_request();
		$result = $this->queue_processor->process_batch();

		wp_send_json_success(
			[
				'batch'  => $result,
				'stats'  => $this->queue_manager->get_statistics(),
				'failed' => $this->format_failed_jobs(),
			]
		);
	}

	/**
	 * Queue status endpoint.
	 *
	 * @return void
	 */
	public function queue_status(): void {
		$this->verify_request();

		wp_send_json_success(
			[
				'stats'  => $this->queue_manager->get_statistics(),
				'failed' => $this->format_failed_jobs(),
			]
		);
	}

	/**
	 * Retry failed job endpoint.
	 *
	 * @return void
	 */
	public function retry_queue_job(): void {
		$this->verify_request();

		$queue_id = isset( $_POST['queue_id'] ) ? absint( $_POST['queue_id'] ) : 0;
		if ( 0 === $queue_id || ! $this->queue_manager->retry_job( $queue_id ) ) {
			$message = ( $queue_id > 0 && $this->queue_manager->is_retry_exhausted( $queue_id ) )
				? sprintf(
					/* translators: %d: maximum number of retry attempts. */
					__( 'This job reached the maximum of %d attempts and will stay failed.', 'vacuum-image-optimizer' ),
					$this->queue_manager->get_max_attempts()
				)
				: __( 'The failed queue job could not be retried.', 'vacuum-image-optimizer' );

			wp_send_json_error( [ 'message' => $message ], 400 );
		}

		wp_send_json_success(
			[
				'stats'  => $this->queue_manager->get_statistics(),
				'failed' => $this->format_failed_jobs(),
			]
		);
	}

	/**
	 * Verify nonce and capability.
	 *
	 * @return void
	 */
	private function verify_request(): void {
		check_ajax_referer( 'vio_queue_ajax', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to manage the optimization queue.', 'vacuum-image-optimizer' ) ], 403 );
		}
	}

	/**
	 * Format failed jobs for the UI.
	 *
	 * @return array<int, array<string, int|string>>
	 */
	private function format_failed_jobs(): array {
		$jobs         = [];
		$max_attempts = $this->queue_manager->get_max_attempts();

		foreach ( $this->queue_manager->get_failed_jobs( 20 ) as $job ) {
			$attachment_id = absint( $job->attachment_id ?? 0 );
			$attempts      = absint( $job->attempts ?? 0 );
			$jobs[]        = [
				'id'            => absint( $job->id ?? 0 ),
				'attachment_id' => $attachment_id,
				'attachment'    => $attachment_id > 0 ? get_the_title( $attachment_id ) : __( 'Unknown attachment', 'vacuum-image-optimizer' ),
				'error'         => sanitize_text_field( (string) ( $job->error_message ?? '' ) ),
				'attempts'      => $attempts,
				'max_attempts'  => $max_attempts,
				'exhausted'     => $attempts >= $max_attempts,
			];
		}

		return $jobs;
	}
}