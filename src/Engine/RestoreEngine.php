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

		$backup_manager = new \VacuumImageOptimizer\Backup\BackupManager();
		if ( '' === $backup_path || ! $backup_manager->is_valid_backup_path( $backup_path ) || ! file_exists( $backup_path ) || ! is_file( $backup_path ) || ! is_readable( $backup_path ) ) {
			return $this->result( false, __( 'The original backup file is missing, unreadable, or outside the backup directory.', 'vacuum-image-optimizer' ), $attachment_id );
		}

		$original_path = get_attached_file( $attachment_id );
		$original_path = is_string( $original_path ) ? wp_normalize_path( $original_path ) : '';

		if ( '' === $original_path ) {
			return $this->result( false, __( 'The attachment original path could not be resolved.', 'vacuum-image-optimizer' ), $attachment_id );
		}

		$uploads       = wp_get_upload_dir();
		$uploads_root  = isset( $uploads['basedir'] ) ? realpath( (string) $uploads['basedir'] ) : false;
		$source_parent = realpath( dirname( $original_path ) );
		$backup_mime   = wp_get_image_mime( $backup_path );
		$expected_mime = sanitize_mime_type( (string) get_post_mime_type( $attachment_id ) );
		if ( ! is_string( $uploads_root ) || ! is_string( $source_parent ) || 0 !== strpos( trailingslashit( wp_normalize_path( $source_parent ) ), trailingslashit( wp_normalize_path( $uploads_root ) ) ) || ! is_string( $backup_mime ) || $backup_mime !== $expected_mime ) {
			return $this->result( false, __( 'The backup or attachment path failed image and uploads-directory validation.', 'vacuum-image-optimizer' ), $attachment_id );
		}

		$original_directory = dirname( $original_path );
		if ( ! is_dir( $original_directory ) && ! wp_mkdir_p( $original_directory ) ) {
			return $this->result( false, __( 'The original image directory could not be created.', 'vacuum-image-optimizer' ), $attachment_id );
		}

		$restore_temp = $original_path . '.vacimg-restore-' . wp_generate_password( 12, false, false );
		if ( ! copy( $backup_path, $restore_temp ) ) {
			return $this->result( false, __( 'The original image could not be staged from backup.', 'vacuum-image-optimizer' ), $attachment_id );
		}
		// WP_Filesystem::move(..., true) deletes the destination before moving, creating a non-atomic gap.
		// phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors.Discouraged -- Native same-directory rename atomically publishes the restore; failure leaves the current source intact.
		if ( ! @rename( $restore_temp, $original_path ) ) {
			wp_delete_file( $restore_temp );
			return $this->result( false, __( 'The original image could not be restored safely from backup.', 'vacuum-image-optimizer' ), $attachment_id );
		}
		clearstatcache( true, $original_path );

		// Remove generated derivatives so the restored image behaves like a fresh upload.
		$this->remove_derivatives( $attachment_id, $original_path );
		$this->reset_optimization_metadata( $attachment_id );

		return $this->result( true, __( 'Original image restored successfully.', 'vacuum-image-optimizer' ), $attachment_id );
	}

	/**
	 * Remove owned derivatives before WordPress deletes a source attachment.
	 * Backups are intentionally preserved.
	 *
	 * @param int $attachment_id Source attachment ID.
	 * @return void
	 */
	public function cleanup_deleted_attachment( int $attachment_id ): void {
		$attachment_id = absint( $attachment_id );
		$path          = get_attached_file( $attachment_id );
		if ( 0 === $attachment_id || ! is_string( $path ) || '' === $path ) {
			return;
		}

		$this->remove_derivatives( $attachment_id, $path );
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
		$original_path = wp_normalize_path( $original_path );

		$metadata     = (array) wp_get_attachment_metadata( $attachment_id );
		$owned_paths  = [ $this->sibling_derivative_path( $original_path, 'webp' ), $this->sibling_derivative_path( $original_path, 'avif' ) ];
		$source_dir   = dirname( $original_path );
		foreach ( (array) ( $metadata['sizes'] ?? [] ) as $size ) {
			$file = is_array( $size ) ? sanitize_file_name( (string) ( $size['file'] ?? '' ) ) : '';
			if ( '' === $file ) {
				continue;
			}
			$size_path = trailingslashit( $source_dir ) . pathinfo( $file, PATHINFO_FILENAME );
			$owned_paths[] = $size_path . '.webp';
			$owned_paths[] = $size_path . '.avif';
		}
		$owned_paths = array_fill_keys( array_map( 'wp_normalize_path', $owned_paths ), true );
		$registry     = get_post_meta( $attachment_id, '_vacimg_derivatives', true );
		if ( is_array( $registry ) ) {
			foreach ( $registry as $formats ) {
				if ( ! is_array( $formats ) ) {
					continue;
				}
				foreach ( $formats as $format => $entry ) {
					$path = is_array( $entry ) ? wp_normalize_path( (string) ( $entry['path'] ?? '' ) ) : '';
					if ( isset( $owned_paths[ $path ] ) && $path !== $original_path ) {
						$this->delete_derivative_file( $path, sanitize_key( (string) $format ) );
					}
				}
			}
		}

		foreach ( [ 'webp', 'avif' ] as $format ) {
			// Remove the linked derivative Media Library attachment (and its file).
			$derivative_id = absint( get_post_meta( $attachment_id, '_vacimg_' . $format . '_attachment_id', true ) );
			if ( $derivative_id > 0 && $this->is_owned_derivative( $derivative_id, $format, $owned_paths ) ) {
				wp_delete_attachment( $derivative_id, true );
			}

			// Delete the derivative file recorded in meta, if it is still present.
			$meta_path = get_post_meta( $attachment_id, '_vacimg_' . $format . '_path', true );
			$meta_path = is_string( $meta_path ) ? wp_normalize_path( $meta_path ) : '';
			if ( isset( $owned_paths[ $meta_path ] ) && $meta_path !== $original_path ) {
				$this->delete_derivative_file( $meta_path, $format );
			}

			// Also delete the sibling derivative next to the original (covers untracked files).
			$sibling_path = $this->sibling_derivative_path( $original_path, $format );
			if ( isset( $owned_paths[ $sibling_path ] ) && $sibling_path !== $original_path ) {
				$this->delete_derivative_file( $sibling_path, $format );
			}

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
	private function is_owned_derivative( int $derivative_id, string $format, array $owned_paths ): bool {
		$post = get_post( $derivative_id );
		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type || sanitize_key( (string) get_post_meta( $derivative_id, '_vacimg_generated_by', true ) ) !== $format ) {
			return false;
		}

		$path = get_attached_file( $derivative_id );
		$path = is_string( $path ) ? wp_normalize_path( $path ) : '';
		return '' !== $path && isset( $owned_paths[ $path ] ) && $this->is_safe_upload_path( $path );
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

		$path = wp_normalize_path( $path );
		if ( $this->is_safe_upload_file( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/** Verify a path's real parent is inside the uploads tree before file deletion. */
	private function is_safe_upload_file( string $path ): bool {
		return $this->is_safe_upload_path( $path ) && file_exists( $path ) && is_file( $path );
	}

	/** Verify a path's real parent is inside the uploads tree, even when its file is already missing. */
	private function is_safe_upload_path( string $path ): bool {
		$uploads = wp_get_upload_dir();
		$base    = isset( $uploads['basedir'] ) ? realpath( (string) $uploads['basedir'] ) : false;
		$parent  = realpath( dirname( $path ) );
		if ( ! is_string( $base ) || ! is_string( $parent ) ) {
			return false;
		}

		$base   = trailingslashit( wp_normalize_path( $base ) );
		$parent = trailingslashit( wp_normalize_path( $parent ) );
		return 0 === strpos( $parent, $base );
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
		delete_post_meta( $attachment_id, '_vacimg_derivatives' );
		delete_post_meta( $attachment_id, '_vacimg_delivery_state' );
		delete_post_meta( $attachment_id, '_vacimg_delivery_generated_at' );
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
