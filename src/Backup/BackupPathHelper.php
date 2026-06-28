<?php
/**
 * Backup path helper utilities.
 *
 * @package VacuumImageOptimizer\Backup
 */

namespace VacuumImageOptimizer\Backup;

defined( 'ABSPATH' ) || exit;

/**
 * Builds normalized backup paths under the uploads directory.
 */
class BackupPathHelper {

	/**
	 * Backup directory name inside wp-content/uploads.
	 *
	 * @var string
	 */
	private const BACKUP_DIR_NAME = 'vacimg-backups';

	/**
	 * Get the uploads base directory.
	 *
	 * @return string
	 */
	public function get_uploads_base_directory(): string {
		$uploads = wp_upload_dir();
		$base    = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';

		return '' === $base ? '' : untrailingslashit( $base );
	}

	/**
	 * Get the absolute backup directory path.
	 *
	 * @return string
	 */
	public function get_backup_directory(): string {
		$base = $this->get_uploads_base_directory();

		return '' === $base ? '' : trailingslashit( $base ) . self::BACKUP_DIR_NAME;
	}

	/**
	 * Build an attachment-specific backup directory path.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public function get_attachment_backup_directory( int $attachment_id ): string {
		$backup_directory = $this->get_backup_directory();

		return '' === $backup_directory ? '' : trailingslashit( $backup_directory ) . absint( $attachment_id );
	}

	/**
	 * Build an attachment backup file path.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $source_file Absolute source file path.
	 * @return string
	 */
	public function get_attachment_backup_file_path( int $attachment_id, string $source_file ): string {
		$directory = $this->get_attachment_backup_directory( $attachment_id );
		$file_name = sanitize_file_name( wp_basename( $source_file ) );

		return '' === $directory || '' === $file_name ? '' : trailingslashit( $directory ) . $file_name;
	}
}