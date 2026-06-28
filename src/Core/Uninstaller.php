<?php
/**
 * Plugin deactivation handler.
 *
 * @package VacuumImageOptimizer\Core
 */

namespace VacuumImageOptimizer\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin deactivation.
 */
class Uninstaller {

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		self::clear_cron();
	}

	/**
	 * Clear scheduled cron events.
	 *
	 * @return void
	 */
	private static function clear_cron(): void {
		$timestamp = wp_next_scheduled( 'vacimg_cleanup_backups' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'vacimg_cleanup_backups' );
		}
	}
}
