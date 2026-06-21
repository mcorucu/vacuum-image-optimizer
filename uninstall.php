<?php
/**
 * Vacuum Image Optimizer uninstall routine.
 *
 * Performs safe database-only cleanup. It NEVER deletes user images, generated
 * WebP/AVIF derivatives, or backup files from disk — only plugin options, the
 * custom queue/stats/backup tables, and plugin attachment metadata are removed.
 *
 * @package VacuumImageOptimizer
 */

// Exit if accessed directly or not invoked by the WordPress uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/*
 * 1. Remove plugin options.
 */
$vio_options = [
	'vio_settings',
	'vio_queue_state',
	'vio_db_version',
	'vio_phase5_queue_ready',
	'vio_last_auto_processed_at',
	'vio_last_backup_cleanup',
];

foreach ( $vio_options as $vio_option ) {
	delete_option( $vio_option );
}

// Remove the cached report summary transient (and its timeout row).
delete_transient( 'vio_report_summary' );

/*
 * 2. Drop custom database tables created by the plugin.
 *    Table names are internal constants, never user input.
 */
$vio_tables = [
	$wpdb->prefix . 'vio_queue',
	$wpdb->prefix . 'vio_stats',
	$wpdb->prefix . 'vio_backups',
];

foreach ( $vio_tables as $vio_table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS `{$vio_table}`" );
}

/*
 * 3. Remove plugin attachment metadata (everything prefixed with _vio_).
 *    Image files themselves are intentionally left untouched.
 */
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_vio_' ) . '%'
	)
);

/*
 * 4. Clear any scheduled cron events.
 */
$vio_timestamp = wp_next_scheduled( 'vio_cleanup_backups' );
if ( $vio_timestamp ) {
	wp_unschedule_event( $vio_timestamp, 'vio_cleanup_backups' );
}
