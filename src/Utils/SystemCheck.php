<?php
/**
 * Server capability detection.
 *
 * @package VacuumImageOptimizer\Utils
 */

namespace VacuumImageOptimizer\Utils;

use VacuumImageOptimizer\Backup\BackupManager;

defined( 'ABSPATH' ) || exit;

/**
 * Reports server environment and image processing capabilities.
 */
class SystemCheck {

	/**
	 * Get full system status report.
	 *
	 * @return array
	 */
	public static function get_status(): array {
		return [
			'php_version'    => PHP_VERSION,
			'wp_version'     => get_bloginfo( 'version' ),
			'memory_limit'   => ini_get( 'memory_limit' ),
			'upload_limit'   => ini_get( 'upload_max_filesize' ),
			'post_max_size'  => ini_get( 'post_max_size' ),
			'gd'             => self::has_gd(),
			'imagick'        => self::has_imagick(),
			'webp_imagick'   => self::has_imagick_webp_support(),
			'webp_gd'        => self::has_gd_webp_support(),
			'webp_support'   => self::has_webp_support(),
			'avif_imagick'   => self::has_imagick_avif_support(),
			'avif_gd'        => self::has_gd_avif_support(),
			'avif_support'   => self::has_avif_support(),
			'upload_writable' => self::is_upload_dir_writable(),
			'queue_table'    => self::has_queue_table(),
			'backups_writable' => self::is_backups_writable(),
		];
	}

	/**
	 * Check whether the queue database table exists.
	 *
	 * @return bool
	 */
	public static function has_queue_table(): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'vio_queue';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

		return $found === $table;
	}

	/**
	 * Check whether the backup directory is writable.
	 *
	 * @return bool
	 */
	public static function is_backups_writable(): bool {
		return ( new BackupManager() )->is_backup_directory_writable();
	}

	/**
	 * Check if GD extension is loaded.
	 *
	 * @return bool
	 */
	public static function has_gd(): bool {
		return extension_loaded( 'gd' );
	}

	/**
	 * Check if Imagick extension is loaded.
	 *
	 * @return bool
	 */
	public static function has_imagick(): bool {
		return extension_loaded( 'imagick' );
	}

	/**
	 * Check WebP support.
	 *
	 * @return bool
	 */
	public static function has_webp_support(): bool {
		return self::has_imagick_webp_support() || self::has_gd_webp_support();
	}

	/**
	 * Check Imagick WebP generation support.
	 *
	 * @return bool
	 */
	public static function has_imagick_webp_support(): bool {
		if ( ! self::has_imagick() || ! class_exists( '\\Imagick' ) ) {
			return false;
		}

		$formats = \Imagick::queryFormats( 'WEBP' );

		return ! empty( $formats );
	}

	/**
	 * Check GD WebP generation support.
	 *
	 * @return bool
	 */
	public static function has_gd_webp_support(): bool {
		return self::has_gd()
			&& function_exists( 'imagewebp' )
			&& function_exists( 'imagecreatefromjpeg' )
			&& function_exists( 'imagecreatefrompng' );
	}

	/**
	 * Check AVIF support (any available engine).
	 *
	 * @return bool
	 */
	public static function has_avif_support(): bool {
		return self::has_imagick_avif_support() || self::has_gd_avif_support();
	}

	/**
	 * Check Imagick AVIF generation support.
	 *
	 * @return bool
	 */
	public static function has_imagick_avif_support(): bool {
		if ( ! self::has_imagick() || ! class_exists( '\\Imagick' ) ) {
			return false;
		}

		$formats = \Imagick::queryFormats( 'AVIF' );

		return ! empty( $formats );
	}

	/**
	 * Check GD AVIF generation support.
	 *
	 * @return bool
	 */
	public static function has_gd_avif_support(): bool {
		return self::has_gd()
			&& function_exists( 'imageavif' )
			&& function_exists( 'imagecreatefromjpeg' )
			&& function_exists( 'imagecreatefrompng' );
	}

	/**
	 * Check if upload directory is writable.
	 *
	 * @return bool
	 */
	public static function is_upload_dir_writable(): bool {
		$upload_dir = wp_upload_dir();
		return ! empty( $upload_dir['basedir'] ) && wp_is_writable( $upload_dir['basedir'] );
	}
}
