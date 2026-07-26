<?php
/**
 * Local WordPress smoke contract for the 1.2.0 migration and diagnostics services.
 * This file is development-only and is excluded from release ZIPs.
 */

require '/var/www/html/wp-load.php';

$fail = static function ( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
};

$migration = new \VacuumImageOptimizer\Frontend\ModernFormatMigration();
$scan      = $migration->scan();

if ( absint( $scan['summary']['scanned'] ?? 0 ) < 1 ) {
	$fail( 'readiness scan found no local attachments' );
}

$diagnostics = $migration->inspect( 181 );
foreach ( [ 'attachment_id', 'mime', 'dimensions', 'sizes', 'delivery_enabled', 'preferred_format' ] as $key ) {
	if ( ! array_key_exists( $key, $diagnostics ) ) {
		$fail( "diagnostics missing {$key}" );
	}
}

$migration->start();
$migration->pause();
if ( 'paused' !== ( $migration->get_state()['state'] ?? '' ) ) {
	$fail( 'migration did not pause' );
}

$migration->start();
if ( 'running' !== ( $migration->get_state()['state'] ?? '' ) ) {
	$fail( 'migration did not resume' );
}

$migration->cancel();
if ( 'cancelled' !== ( $migration->get_state()['state'] ?? '' ) ) {
	$fail( 'migration did not cancel' );
}

$state              = $migration->get_state();
$state['failed_ids'] = [ 181 ];
$state['state']      = 'completed';
update_option( 'vacimg_modern_migration_state', $state, false );
if ( 'running' !== ( $migration->retry_failed()['state'] ?? '' ) ) {
	$fail( 'migration did not retry failed work' );
}
$migration->cancel();

echo "PASS: migration scan, diagnostics, pause, resume, cancel, and retry\n";
