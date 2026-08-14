<?php
/**
 * Upload automation for new media attachments.
 *
 * @package VacuumImageOptimizer\Upload
 */

namespace VacuumImageOptimizer\Upload;

use VacuumImageOptimizer\Engine\AVIFGenerator;
use VacuumImageOptimizer\Engine\WebPGenerator;
use VacuumImageOptimizer\Frontend\ResponsiveDerivativeGenerator;
use VacuumImageOptimizer\Queue\QueueManager;
use VacuumImageOptimizer\Settings\CompressionSettings;
use VacuumImageOptimizer\Utils\ImageFormat;

defined( 'ABSPATH' ) || exit;

/**
 * Detects newly uploaded JPEG/PNG/WebP attachments and auto-processes them safely.
 */
class UploadAutomation {

	/** Deferred hook used after WordPress has persisted finalized attachment metadata. */
	private const DEFERRED_QUEUE_HOOK = 'vacimg_enqueue_deferred_attachment';

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

	private ResponsiveDerivativeGenerator $responsive_generator;

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
		$this->responsive_generator = new ResponsiveDerivativeGenerator();
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
		// WordPress 7.1 passes a third create/update context. The update pass is the
		// completion signal for client-side sub-size sideloads.
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'handle_attachment_metadata' ], 20, 3 );
		add_action( self::DEFERRED_QUEUE_HOOK, [ $this, 'enqueue_deferred_attachment' ] );
	}

	/**
	 * Handle an attachment after WordPress has generated its metadata.
	 *
	 * @param array<string, mixed> $metadata Attachment metadata.
	 * @param int $attachment_id Attachment ID.
	 * @param string $context Metadata lifecycle context: create or update.
	 * @return array<string, mixed>
	 */
	public function handle_attachment_metadata( array $metadata, int $attachment_id, string $context = 'create' ): array {
		$attachment_id = absint( $attachment_id );
		$context       = in_array( $context, [ 'create', 'update' ], true ) ? $context : 'create';

		try {
			if ( ! CompressionSettings::is_auto_optimize_uploads_enabled() ) {
				return $metadata;
			}

			if ( ! $this->is_supported_upload( $attachment_id ) ) {
				$this->mark_auto_skipped( $attachment_id );
				return $metadata;
			}

			$auto_status = sanitize_key( (string) get_post_meta( $attachment_id, '_vacimg_auto_status', true ) );
			$processed   = sanitize_key( (string) get_post_meta( $attachment_id, '_vacimg_auto_processed', true ) );

			// On the client-side path, the create pass intentionally has no complete
			// sizes map. Do not optimize or mark delivery ready until finalize/update.
			if ( 'create' === $context && ! $this->has_finalized_sizes( $metadata ) ) {
				if ( ! in_array( $auto_status, [ 'optimized', 'skipped', 'failed' ], true ) ) {
					$this->defer_until_metadata_is_saved( $attachment_id );
				}
				return $metadata;
			}

			// A second pass reconciles newly sideloaded physical sizes only. It must
			// never recompress a source or create another backup/queue job.
			if ( 'optimized' === $auto_status ) {
				$this->responsive_generator->generate( $attachment_id, $metadata );
				return $metadata;
			}

			// A deferred queue job owns the upload. The update pass only confirms that
			// final metadata exists; it must not race the queued processor.
			if ( 'queue' === $processed || 'failed' === $auto_status || 'skipped' === $auto_status ) {
				return $metadata;
			}

			$mode = CompressionSettings::get_auto_optimize_mode();
			// Safe Mode deliberately queues work, even when immediate mode was selected,
			// so a large upload is never made slower by optimization work.
			if ( 'immediate' === $mode && ! CompressionSettings::is_safe_mode_enabled() ) {
				$this->process_immediately( $attachment_id, $metadata );
				return $metadata;
			}

			$this->defer_until_metadata_is_saved( $attachment_id );
		} catch ( \Throwable $throwable ) {
			$this->store_failure( $attachment_id, $throwable->getMessage() );
		}

		return $metadata;
	}

	/**
	 * Enqueue or process a deferred upload after its metadata has been saved.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function enqueue_deferred_attachment( int $attachment_id ): void {
		$attachment_id = absint( $attachment_id );
		if ( 0 === $attachment_id || ! CompressionSettings::is_auto_optimize_uploads_enabled() ) {
			return;
		}

		$auto_status = sanitize_key( (string) get_post_meta( $attachment_id, '_vacimg_auto_status', true ) );
		if ( ! in_array( $auto_status, [ 'awaiting_metadata', 'pending' ], true ) || ! $this->is_supported_upload( $attachment_id ) ) {
			return;
		}

		$mode = CompressionSettings::get_auto_optimize_mode();
		if ( 'immediate' === $mode && ! CompressionSettings::is_safe_mode_enabled() ) {
			$this->process_immediately( $attachment_id, (array) wp_get_attachment_metadata( $attachment_id ) );
			return;
		}

		$this->queue_attachment( $attachment_id );
	}

	/**
	 * Check whether the metadata contains the finalized registered-size map.
	 *
	 * @param array<string, mixed> $metadata Attachment metadata.
	 * @return bool
	 */
	private function has_finalized_sizes( array $metadata ): bool {
		return isset( $metadata['file'], $metadata['width'], $metadata['height'] )
			&& ! empty( $metadata['sizes'] )
			&& is_array( $metadata['sizes'] );
	}

	/**
	 * Defer work until the current request has persisted attachment metadata.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function defer_until_metadata_is_saved( int $attachment_id ): void {
		update_post_meta( $attachment_id, '_vacimg_status', 'pending' );
		update_post_meta( $attachment_id, '_vacimg_auto_status', 'awaiting_metadata' );
		update_post_meta( $attachment_id, '_vacimg_auto_processed', 'deferred' );
		update_post_meta( $attachment_id, '_vacimg_delivery_state', 'pending' );

		if ( ! wp_next_scheduled( self::DEFERRED_QUEUE_HOOK, [ $attachment_id ] ) ) {
			wp_schedule_single_event( time() + 1, self::DEFERRED_QUEUE_HOOK, [ $attachment_id ] );
		}
	}

	/**
	 * Add a supported attachment to the queue.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function queue_attachment( int $attachment_id ): void {
		update_post_meta( $attachment_id, '_vacimg_status', 'pending' );
		update_post_meta( $attachment_id, '_vacimg_auto_status', 'pending' );

		if ( $this->queue_manager->add_attachment( $attachment_id ) ) {
			update_post_meta( $attachment_id, '_vacimg_auto_processed', 'queue' );
			return;
		}
	}

	/**
	 * Generate WebP immediately for a supported upload.
	 *
	 * @param int                 $attachment_id Attachment ID.
	 * @param array<string,mixed> $metadata Finalized attachment metadata.
	 * @return void
	 */
	private function process_immediately( int $attachment_id, array $metadata = [] ): void {
		$result = $this->webp_generator->generate( $attachment_id );

		if ( ! empty( $result['success'] ) ) {
			$this->maybe_generate_avif( $attachment_id );
			$this->responsive_generator->generate( $attachment_id, $metadata );
			$this->mark_auto_processed( $attachment_id, 'optimized' );
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
			update_post_meta( $attachment_id, '_vacimg_avif_error_message', sanitize_text_field( $throwable->getMessage() ) );
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

		return ImageFormat::is_supported_source_attachment( $attachment_id );
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

		update_post_meta( $attachment_id, '_vacimg_auto_processed', sanitize_key( $mode ) );
		update_post_meta( $attachment_id, '_vacimg_auto_status', sanitize_key( $mode ) );
		update_post_meta( $attachment_id, '_vacimg_auto_processed_at', $now );
		update_option( 'vacimg_last_auto_processed_at', $now );
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

		update_post_meta( $attachment_id, '_vacimg_status', 'error' );
		update_post_meta( $attachment_id, '_vacimg_auto_status', 'failed' );
		update_post_meta( $attachment_id, '_vacimg_error_message', sanitize_text_field( $message ) );
	}

	/** Mark an ineligible automatic upload without interrupting its upload flow. */
	private function mark_auto_skipped( int $attachment_id ): void {
		if ( 0 === $attachment_id ) {
			return;
		}

		update_post_meta( $attachment_id, '_vacimg_status', 'skipped' );
		update_post_meta( $attachment_id, '_vacimg_auto_status', 'skipped' );
		update_post_meta( $attachment_id, '_vacimg_error_message', ImageFormat::get_skip_reason( $attachment_id ) );
	}
}
