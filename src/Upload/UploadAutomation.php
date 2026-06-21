<?php
/**
 * Upload automation for new media attachments.
 *
 * @package VacuumImageOptimizer\Upload
 */

namespace VacuumImageOptimizer\Upload;

use VacuumImageOptimizer\Engine\AVIFGenerator;
use VacuumImageOptimizer\Engine\WebPGenerator;
use VacuumImageOptimizer\Queue\QueueManager;
use VacuumImageOptimizer\Settings\CompressionSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Detects newly uploaded JPEG/PNG attachments and auto-processes them safely.
 */
class UploadAutomation {

	/**
	 * Supported upload MIME types.
	 *
	 * @var array<int, string>
	 */
	private const SUPPORTED_MIME_TYPES = [ 'image/jpeg', 'image/png' ];

	/**
	 * Whether hooks have been registered.
	 *
	 * @var bool
	 */
	private bool $registered = false;

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
		$this->queue_manager  = $queue_manager ?? new QueueManager();
		$this->webp_generator = $webp_generator ?? new WebPGenerator();
		$this->avif_generator = $avif_generator ?? new AVIFGenerator();
	}

	/**
	 * Register upload hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;
		add_action( 'add_attachment', [ $this, 'handle_new_attachment' ], 20 );
	}

	/**
	 * Handle a newly created attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function handle_new_attachment( int $attachment_id ): void {
		$attachment_id = absint( $attachment_id );

		try {
			if ( ! CompressionSettings::is_auto_optimize_uploads_enabled() || ! $this->is_supported_upload( $attachment_id ) ) {
				return;
			}

			$mode = CompressionSettings::get_auto_optimize_mode();
			if ( 'immediate' === $mode ) {
				$this->process_immediately( $attachment_id );
				return;
			}

			$this->queue_attachment( $attachment_id );
		} catch ( \Throwable $throwable ) {
			$this->store_failure( $attachment_id, $throwable->getMessage() );
		}
	}

	/**
	 * Add a supported attachment to the queue.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function queue_attachment( int $attachment_id ): void {
		if ( $this->queue_manager->add_attachment( $attachment_id ) ) {
			$this->mark_auto_processed( $attachment_id, 'queue' );
			return;
		}

		update_post_meta( $attachment_id, '_vio_status', 'pending' );
	}

	/**
	 * Generate WebP immediately for a supported upload.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function process_immediately( int $attachment_id ): void {
		$result = $this->webp_generator->generate( $attachment_id );

		if ( ! empty( $result['success'] ) ) {
			$this->maybe_generate_avif( $attachment_id );
			$this->mark_auto_processed( $attachment_id, 'immediate' );
			return;
		}

		$message = isset( $result['message'] ) ? (string) $result['message'] : __( 'Automatic WebP generation failed.', 'vacuum-image-optimizer' );
		$this->store_failure( $attachment_id, $message );
	}

	/**
	 * Generate AVIF alongside WebP when enabled, without breaking WebP success.
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

	/**
	 * Check whether an attachment is an eligible upload.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function is_supported_upload( int $attachment_id ): bool {
		if ( 0 === $attachment_id ) {
			return false;
		}

		$post = get_post( $attachment_id );
		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			return false;
		}

		$mime_type = (string) get_post_mime_type( $attachment_id );

		return wp_attachment_is_image( $attachment_id ) && in_array( $mime_type, self::SUPPORTED_MIME_TYPES, true );
	}

	/**
	 * Store successful automation metadata.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $mode Automation mode.
	 * @return void
	 */
	private function mark_auto_processed( int $attachment_id, string $mode ): void {
		$now = current_time( 'mysql' );

		update_post_meta( $attachment_id, '_vio_auto_processed', sanitize_key( $mode ) );
		update_post_meta( $attachment_id, '_vio_auto_processed_at', $now );
		update_option( 'vio_last_auto_processed_at', $now );
	}

	/**
	 * Store a safe failure record without interrupting upload flow.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $message Error message.
	 * @return void
	 */
	private function store_failure( int $attachment_id, string $message ): void {
		if ( 0 === $attachment_id ) {
			return;
		}

		update_post_meta( $attachment_id, '_vio_status', 'error' );
		update_post_meta( $attachment_id, '_vio_error_message', sanitize_text_field( $message ) );
	}
}