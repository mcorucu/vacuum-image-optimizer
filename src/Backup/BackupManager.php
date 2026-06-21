<?php
/**
 * Backup path and directory infrastructure.
 *
 * @package VacuumImageOptimizer\Backup
 */

namespace VacuumImageOptimizer\Backup;

defined( 'ABSPATH' ) || exit;

/**
 * Manages backup directory paths and validation.
 */
class BackupManager {

	/**
	 * Backup path helper.
	 *
	 * @var BackupPathHelper
	 */
	private BackupPathHelper $path_helper;

	/**
	 * Constructor.
	 *
	 * @param BackupPathHelper|null $path_helper Optional path helper.
	 */
	public function __construct( ?BackupPathHelper $path_helper = null ) {
		$this->path_helper = $path_helper ?? new BackupPathHelper();
	}

	/**
	 * Get the absolute backup directory path.
	 *
	 * @return string
	 */
	public function get_backup_directory(): string {
		return $this->path_helper->get_backup_directory();
	}

	/**
	 * Ensure the backup directory exists.
	 *
	 * @return bool
	 */
	public function ensure_backup_directory(): bool {
		$directory = $this->get_backup_directory();

		if ( '' === $directory ) {
			return false;
		}

		if ( is_dir( $directory ) ) {
			return true;
		}

		return wp_mkdir_p( $directory );
	}

	/**
	 * Check whether the backup directory is writable.
	 *
	 * @return bool
	 */
	public function is_backup_directory_writable(): bool {
		if ( ! $this->ensure_backup_directory() ) {
			return false;
		}

		return wp_is_writable( $this->get_backup_directory() );
	}

	/**
	 * Create an absolute backup path for an attachment source file.
	 *
	 * This creates only the containing backup folder path; it does not copy files.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $source_file Absolute source file path.
	 * @return string
	 */
	public function create_backup_path( int $attachment_id, string $source_file ): string {
		$folder = $this->path_helper->get_attachment_backup_directory( $attachment_id );
		$path   = $this->path_helper->get_attachment_backup_file_path( $attachment_id, $source_file );

		if ( '' === $folder || '' === $path ) {
			return '';
		}

		if ( ! is_dir( $folder ) ) {
			wp_mkdir_p( $folder );
		}

		return $path;
	}

	/**
	 * Validate that a path is inside the backup directory.
	 *
	 * @param string $path Absolute path to validate.
	 * @return bool
	 */
	public function is_valid_backup_path( string $path ): bool {
		if ( '' === $path || '' === $this->get_backup_directory() ) {
			return false;
		}

		$backup_directory = wp_normalize_path( trailingslashit( $this->get_backup_directory() ) );
		$path             = wp_normalize_path( $path );

		return 0 === strpos( $path, $backup_directory );
	}
}