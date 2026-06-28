<?php
/**
 * Single-image original restore engine.
 *
 * @package VacuumImageOptimizer\Engine
 */

namespace VacuumImageOptimizer\Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Restores an attachment original from its stored Vacuum backup.
 */
class RestoreEngine {

	/**
	 * Restore an attachment original from backup.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, bool|int|string>
	 */
	public function restore( int $attachment_id ): array {
		$attachment_id = absint( $attachment_id );

		if ( 0 === $attachment_id ) {
			return $this->result( false, __( 'Invalid attachment ID.', 'vacuum-image-optimizer' ), $attachment_id );
		}

		$post = get_post( $attachment_id );
		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			return $this->result( false, __( 'Attachment could not be found.', 'vacuum-image-optimizer' ), $attachment_id );
		}

		$backup_path = get_post_meta( $attachment_id, '_vacimg_backup_path', true );
		$backup_path = is_string( $backup_path ) ? wp_normalize_path( $backup_path ) : '';

		if ( '' === $backup_path || ! file_exists( $backup_path ) || ! is_file( $backup_path ) || ! is_readable( $backup_path ) ) {
			return $this->result( false, __( 'The original backup file is missing or unreadable.', 'vacuum-image-optimizer' ), $attachment_id );
		}

		$original_path = get_attached_file( $attachment_id );
		$original_path = is_string( $original_path ) ? wp_normalize_path( $original_path ) : '';

		if ( '' === $original_path ) {
			return $this->result( false, __( 'The attachment original path could not be resolved.', 'vacuum-image-optimizer' ), $attachment_id );
		}

		$original_directory = dirname( $original_path );
		if ( ! is_dir( $original_directory ) && ! wp_mkdir_p( $original_directory ) ) {
			return $this->result( false, __( 'The original image directory could not be created.', 'vacuum-image-optimizer' ), $attachment_id );
		}

		if ( ! copy( $backup_path, $original_path ) ) {
			return $this->result( false, __( 'The original image could not be restored from backup.', 'vacuum-image-optimizer' ), $attachment_id );
		}

		// Remove generated derivatives so the restored image behaves like a fresh upload.
		$this->remove_derivatives( $attachment_id, $original_path );
		$this->reset_optimization_metadata( $attachment_id );

		return $this->result( true, __( 'Original image restored successfully.', 'vacuum-image-optimizer' ), $attachment_id );
	}

	/**
	 * Delete generated WebP/AVIF derivative files and their Media Library records.
	 *
	 * Only the plugin's own derivatives are removed — the restored original is
	 * never touched. After this runs the frontend delivery engine has nothing
	 * left to serve, so the restored image falls back to its original format.
	 *
	 * @param int    $attachment_id Source attachment ID.
	 * @param string $original_path Absolute path to the restored original.
	 * @return void
	 */
	private function remove_derivatives( int $attachment_id, string $original_path ): void {
		foreach ( [ 'webp', 'avif' ] as $format ) {
			// Remove the linked derivative Media Library attachment (and its file).
			$derivative_id = absint( get_post_meta( $attachment_id, '_vacimg_' . $format . '_attachment_id', true ) );
			if ( $derivative_id > 0 && $this->is_owned_derivative( $derivative_id, $format ) ) {
				wp_delete_attachment( $derivative_id, true );
			}

			// Delete the derivative file recorded in meta, if it is still present.
			$meta_path = get_post_meta( $attachment_id, '_vacimg_' . $format . '_path', true );
			$meta_path = is_string( $meta_path ) ? wp_normalize_path( $meta_path ) : '';
			$this->delete_derivative_file( $meta_path, $format );

			// Also delete the sibling derivative next to the original (covers untracked files).
			$this->delete_derivative_file( $this->sibling_derivative_path( $original_path, $format ), $format );

			delete_post_meta( $attachment_id, '_vacimg_' . $format . '_attachment_id' );
		}
	}

	/**
	 * Whether an attachment is a plugin-generated derivative of the expected format.
	 *
	 * @param int    $derivative_id Derivative attachment ID.
	 * @param string $format Expected derivative format.
	 * @return bool
	 */
	private function is_owned_derivative( int $derivative_id, string $format ): bool {
		$post = get_post( $derivative_id );
		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			return false;
		}

		return sanitize_key( (string) get_post_meta( $derivative_id, '_vacimg_generated_by', true ) ) === $format;
	}

	/**
	 * Build the sibling derivative path for an original (same name, derivative extension).
	 *
	 * @param string $original_path Absolute original path.
	 * @param string $format Derivative format (webp/avif).
	 * @return string
	 */
	private function sibling_derivative_path( string $original_path, string $format ): string {
		$directory = wp_normalize_path( dirname( $original_path ) );
		$filename  = pathinfo( $original_path, PATHINFO_FILENAME );

		if ( '' === $directory || '' === $filename ) {
			return '';
		}

		return trailingslashit( $directory ) . $filename . '.' . $format;
	}

	/**
	 * Safely delete a derivative file, guarding against deleting non-derivatives.
	 *
	 * @param string $path Absolute file path.
	 * @param string $format Expected derivative extension.
	 * @return void
	 */
	private function delete_derivative_file( string $path, string $format ): void {
		if ( '' === $path ) {
			return;
		}

		// Never delete anything that is not the expected derivative extension.
		if ( strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) ) !== $format ) {
			return;
		}

		if ( file_exists( $path ) && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Reset optimization metadata after restore while keeping backup metadata intact.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function reset_optimization_metadata( int $attachment_id ): void {
		update_post_meta( $attachment_id, '_vacimg_status', 'pending' );

		// Clear WebP + optimization tracking so the image looks freshly uploaded.
		delete_post_meta( $attachment_id, '_vacimg_webp_path' );
		delete_post_meta( $attachment_id, '_vacimg_webp_url' );
		delete_post_meta( $attachment_id, '_vacimg_webp_size' );
		delete_post_meta( $attachment_id, '_vacimg_savings_bytes' );
		delete_post_meta( $attachment_id, '_vacimg_savings_percent' );
		delete_post_meta( $attachment_id, '_vacimg_engine_used' );
		delete_post_meta( $attachment_id, '_vacimg_source_size' );
		delete_post_meta( $attachment_id, '_vacimg_optimized_at' );
		delete_post_meta( $attachment_id, '_vacimg_error_message' );

		// Clear AVIF metadata so derivative state stays consistent after a restore.
		delete_post_meta( $attachment_id, '_vacimg_avif_path' );
		delete_post_meta( $attachment_id, '_vacimg_avif_url' );
		delete_post_meta( $attachment_id, '_vacimg_avif_size' );
		delete_post_meta( $attachment_id, '_vacimg_avif_savings_bytes' );
		delete_post_meta( $attachment_id, '_vacimg_avif_savings_percent' );
		delete_post_meta( $attachment_id, '_vacimg_avif_engine_used' );
		delete_post_meta( $attachment_id, '_vacimg_avif_generated_at' );
		delete_post_meta( $attachment_id, '_vacimg_avif_error_message' );
	}

	/**
	 * Build a standard result.
	 *
	 * @param bool   $success Whether restore succeeded.
	 * @param string $message Result message.
	 * @param int    $attachment_id Attachment ID.
	 * @return array<string, bool|int|string>
	 */
	private function result( bool $success, string $message, int $attachment_id ): array {
		return [
			'success'       => $success,
			'attachment_id' => $attachment_id,
			'message'       => $message,
		];
	}
}