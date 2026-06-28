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
$vacimg_legacy_prefix = 'v' . 'io_';

$vacimg_options = [
	'vacimg_settings',
	'vacimg_queue_state',
	'vacimg_db_version',
	'vacimg_queue_table_ready',
	'vacimg_phase5_queue_ready',
	'vacimg_last_auto_processed_at',
	'vacimg_last_backup_cleanup',
	$vacimg_legacy_prefix . 'settings',
	$vacimg_legacy_prefix . 'queue_state',
	$vacimg_legacy_prefix . 'db_version',
	$vacimg_legacy_prefix . 'queue_table_ready',
	$vacimg_legacy_prefix . 'phase5_queue_ready',
	$vacimg_legacy_prefix . 'last_auto_processed_at',
	$vacimg_legacy_prefix . 'last_backup_cleanup',
];

foreach ( $vacimg_options as $vacimg_option ) {
	delete_option( $vacimg_option );
}

// Remove the cached report summary transient (and its timeout row).
delete_transient( 'vacimg_report_summary' );
delete_transient( $vacimg_legacy_prefix . 'report_summary' );

/*
 * 2. Drop custom database tables created by the plugin.
 *    Table names are internal constants, never user input.
 */
$vacimg_tables = [
	$wpdb->prefix . 'vacimg_queue',
	$wpdb->prefix . 'vacimg_stats',
	$wpdb->prefix . 'vacimg_backups',
	$wpdb->prefix . $vacimg_legacy_prefix . 'queue',
	$wpdb->prefix . $vacimg_legacy_prefix . 'stats',
	$wpdb->prefix . $vacimg_legacy_prefix . 'backups',
];

foreach ( $vacimg_tables as $vacimg_table ) {
	$vacimg_table = esc_sql( $vacimg_table );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS `{$vacimg_table}`" );
}

/*
 * 3. Remove plugin attachment metadata (everything prefixed with _vacimg_).
 *    Image files themselves are intentionally left untouched.
 */
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_vacimg_' ) . '%'
	)
);

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_' . $vacimg_legacy_prefix ) . '%'
	)
);

/*
 * 4. Clear any scheduled cron events.
 */
$vacimg_timestamp = wp_next_scheduled( 'vacimg_cleanup_backups' );
if ( $vacimg_timestamp ) {
	wp_unschedule_event( $vacimg_timestamp, 'vacimg_cleanup_backups' );
}

$vacimg_legacy_cron      = $vacimg_legacy_prefix . 'cleanup_backups';
$vacimg_legacy_timestamp = wp_next_scheduled( $vacimg_legacy_cron );
if ( $vacimg_legacy_timestamp ) {
	wp_unschedule_event( $vacimg_legacy_timestamp, $vacimg_legacy_cron );
}
