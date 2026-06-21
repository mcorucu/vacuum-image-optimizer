<?php
/**
 * Single attachment action links and placeholder handlers.
 *
 * @package VacuumImageOptimizer\Media
 */

namespace VacuumImageOptimizer\Media;

use VacuumImageOptimizer\Engine\WebPGenerator;
use VacuumImageOptimizer\Engine\AVIFGenerator;
use VacuumImageOptimizer\Engine\RestoreEngine;
use VacuumImageOptimizer\Settings\CompressionSettings;

defined( 'ABSPATH' ) || exit;

/**
	 * Adds image-only row actions and handles one-at-a-time attachment requests.
 */
class AttachmentActions {

	/**
	 * Whether attachment action hooks have already been registered.
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * Supported attachment actions.
	 *
	 * @var array<string, string>
	 */
	private array $actions = [
		'vio_optimize_image'  => 'Optimize',
		'vio_generate_webp'   => 'WebP',
		'vio_generate_avif'   => 'AVIF',
		'vio_regenerate_webp' => 'Regenerate',
		'vio_restore_original' => 'Restore',
	];

	/**
	 * Register attachment action hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;

		add_filter( 'media_row_actions', [ $this, 'add_row_actions' ], 10, 2 );
		add_filter( 'post_row_actions', [ $this, 'add_row_actions' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'render_admin_notice' ] );

		foreach ( array_keys( $this->actions ) as $action ) {
			add_action( 'admin_action_' . $action, [ $this, 'handle_action' ] );
		}
	}

	/**
	 * Add optimization row actions for image attachments only.
	 *
	 * @param array<string, string> $row_actions Existing row actions.
	 * @param \WP_Post             $post Attachment post object.
	 * @return array<string, string>
	 */
	public function add_row_actions( array $row_actions, \WP_Post $post ): array {
		if ( ! $this->is_image_attachment( $post ) ) {
			return $row_actions;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return $row_actions;
		}

		$labels = $this->get_action_labels();

		foreach ( $this->actions as $action => $label ) {
			$url = wp_nonce_url(
				admin_url( 'admin.php?action=' . $action . '&attachment_id=' . absint( $post->ID ) ),
				$this->get_nonce_action( $action, $post->ID )
			);

			$row_actions[ $action ] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $url ),
				esc_html( $labels[ $action ] ?? $label )
			);
		}

		return $row_actions;
	}

	/**
	 * Handle an attachment action request.
	 *
	 * @return void
	 */
	public function handle_action(): void {
		$action        = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;

		if ( ! isset( $this->actions[ $action ] ) || 0 === $attachment_id ) {
			$this->redirect_with_notice( 'invalid', '', 'error' );
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage this attachment.', 'vacuum-image-optimizer' ) );
		}

		check_admin_referer( $this->get_nonce_action( $action, $attachment_id ) );

		$post = get_post( $attachment_id );
		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			$this->redirect_with_notice( 'invalid', '', 'error' );
		}

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			$this->redirect_with_notice( 'not_image', '', 'error' );
		}

		if ( 'vio_restore_original' === $action ) {
			$has_backup = '' !== (string) get_post_meta( $attachment_id, '_vio_backup_path', true );

			if ( ! CompressionSettings::is_backups_enabled() && ! $has_backup ) {
				$this->redirect_with_notice(
					'backups_disabled',
					__( 'Backups are disabled, so no backup is available to restore this image. Enable backups before optimizing to allow restores.', 'vacuum-image-optimizer' ),
					'warning'
				);
			}

			$restore = new RestoreEngine();
			$result  = $restore->restore( $attachment_id );

			if ( ! empty( $result['success'] ) ) {
				$this->redirect_with_notice( 'restore_success', (string) $result['message'], 'success' );
			}

			$this->redirect_with_notice( 'restore_error', (string) $result['message'], 'error' );
		}

		if ( 'vio_generate_avif' === $action ) {
			$avif_generator = new AVIFGenerator();
			$avif_result    = $avif_generator->generate( $attachment_id );

			if ( ! empty( $avif_result['success'] ) ) {
				$this->redirect_with_notice( 'avif_success', (string) $avif_result['message'], 'success' );
			}

			$this->redirect_with_notice( 'avif_error', (string) $avif_result['message'], 'error' );
		}

		$generator = new WebPGenerator();

		if ( 'vio_regenerate_webp' === $action ) {
			if ( ! $generator->delete_existing_webp( $attachment_id ) ) {
				$this->redirect_with_notice( 'regenerate_error', __( 'Existing WebP file could not be deleted before regeneration.', 'vacuum-image-optimizer' ), 'error' );
			}
		}

		$result    = $generator->generate( $attachment_id );

		if ( ! empty( $result['success'] ) ) {
			$this->redirect_with_notice( 'webp_success', (string) $result['message'], 'success' );
		}

		$status = get_post_meta( $attachment_id, '_vio_status', true );
		$type   = 'unsupported' === $status ? 'warning' : 'error';

		$this->redirect_with_notice( 'webp_error', (string) $result['message'], $type );
	}

	/**
	 * Render placeholder action admin notices.
	 *
	 * @return void
	 */
	public function render_admin_notice(): void {
		$notice = isset( $_GET['vio_notice'] ) ? sanitize_key( wp_unslash( $_GET['vio_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type   = isset( $_GET['vio_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['vio_notice_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $notice ) {
			return;
		}

		$message = isset( $_GET['vio_message'] ) ? sanitize_text_field( wp_unslash( $_GET['vio_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = '' === $message ? $this->get_notice_message( $notice ) : $message;

		if ( '' === $message ) {
			return;
		}

		if ( ! in_array( $type, [ 'success', 'error', 'warning', 'info' ], true ) ) {
			$type = in_array( $notice, [ 'invalid', 'not_image', 'webp_error', 'avif_error' ], true ) ? 'error' : 'info';
		}

		$class = 'notice-' . $type;
		?>
		<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Translated, extractable labels for the row actions.
	 *
	 * @return array<string, string>
	 */
	private function get_action_labels(): array {
		return [
			'vio_optimize_image'   => __( 'Optimize', 'vacuum-image-optimizer' ),
			'vio_generate_webp'    => __( 'WebP', 'vacuum-image-optimizer' ),
			'vio_generate_avif'    => __( 'AVIF', 'vacuum-image-optimizer' ),
			'vio_regenerate_webp'  => __( 'Regenerate', 'vacuum-image-optimizer' ),
			'vio_restore_original' => __( 'Restore', 'vacuum-image-optimizer' ),
		];
	}

	/**
	 * Get the nonce action string for an attachment action.
	 *
	 * @param string $action Action key.
	 * @param int    $attachment_id Attachment ID.
	 * @return string
	 */
	private function get_nonce_action( string $action, int $attachment_id ): string {
		return $action . '_' . absint( $attachment_id );
	}

	/**
	 * Check whether a post is an image attachment.
	 *
	 * @param \WP_Post $post Attachment post object.
	 * @return bool
	 */
	private function is_image_attachment( \WP_Post $post ): bool {
		return 'attachment' === $post->post_type
			&& is_string( $post->post_mime_type )
			&& 0 === strpos( $post->post_mime_type, 'image/' );
	}

	/**
	 * Redirect back to the Media Library with a notice key.
	 *
	 * @param string $notice Notice key.
	 * @param string $message Optional custom notice message.
	 * @param string $type Optional notice type.
	 * @return void
	 */
	private function redirect_with_notice( string $notice, string $message = '', string $type = 'info' ): void {
		$referer = wp_get_referer();
		$url     = $referer ? $referer : admin_url( 'upload.php' );
		$url     = remove_query_arg( [ 'vio_notice', 'vio_notice_type', 'vio_message', '_wpnonce', 'action', 'attachment_id' ], $url );
		$args    = [
			'vio_notice'      => sanitize_key( $notice ),
			'vio_notice_type' => sanitize_key( $type ),
		];

		if ( '' !== $message ) {
			$args['vio_message'] = sanitize_text_field( $message );
		}

		$url = add_query_arg( $args, $url );

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Resolve a notice message.
	 *
	 * @param string $notice Notice key.
	 * @return string
	 */
	private function get_notice_message( string $notice ): string {
		$messages = [
			'webp_success'      => __( 'WebP image generated successfully.', 'vacuum-image-optimizer' ),
			'webp_error'        => __( 'WebP generation could not be completed.', 'vacuum-image-optimizer' ),
			'avif_success'      => __( 'AVIF image generated successfully.', 'vacuum-image-optimizer' ),
			'avif_error'        => __( 'AVIF generation could not be completed.', 'vacuum-image-optimizer' ),
			'regenerate_error'  => __( 'WebP regeneration could not be completed.', 'vacuum-image-optimizer' ),
			'restore_success'   => __( 'Original image restored successfully.', 'vacuum-image-optimizer' ),
			'restore_error'     => __( 'Original image restore could not be completed.', 'vacuum-image-optimizer' ),
			'invalid'           => __( 'The requested attachment action is invalid.', 'vacuum-image-optimizer' ),
			'not_image'         => __( 'Vacuum Image Optimizer actions are available for image attachments only.', 'vacuum-image-optimizer' ),
		];

		return $messages[ $notice ] ?? '';
	}
}