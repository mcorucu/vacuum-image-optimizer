<?php
/**
 * Real WordPress integration regressions for Vacuum Image Optimizer.
 * Run with: wp eval-file tests/integration.php
 */

use VacuumImageOptimizer\Admin\ReportExporter;
use VacuumImageOptimizer\Engine\AVIFGenerator;
use VacuumImageOptimizer\Engine\RestoreEngine;
use VacuumImageOptimizer\Engine\WebPGenerator;
use VacuumImageOptimizer\Frontend\DeliveryEngine;
use VacuumImageOptimizer\Frontend\ModernFormatMigration;
use VacuumImageOptimizer\Frontend\DerivativeResolver;
use VacuumImageOptimizer\Frontend\ResponsiveDerivativeGenerator;
use VacuumImageOptimizer\Media\AttachmentActions;
use VacuumImageOptimizer\Media\LibraryIntegration;
use VacuumImageOptimizer\Queue\QueueManager;
use VacuumImageOptimizer\Queue\QueueProcessor;
use VacuumImageOptimizer\Settings\CompressionSettings;
use VacuumImageOptimizer\Utils\ImageFormat;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This suite must run inside WP-CLI against a real QA WordPress installation.' );
}

$GLOBALS['vio_pass'] = 0;
$GLOBALS['vio_fail'] = 0;
$vio_admin_id = (int) get_option( 'default_role' );
$vio_admin_id = 1;
wp_set_current_user( $vio_admin_id );

function vio_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function vio_test( string $name, callable $test ): void {
	global $vio_pass, $vio_fail;
	try {
		$test();
		++$vio_pass;
		echo "PASS: {$name}\n";
	} catch ( Throwable $throwable ) {
		++$vio_fail;
		echo "FAIL: {$name} — {$throwable->getMessage()}\n";
	}
}

function vio_fixture_path( string $extension ): string {
	$uploads = wp_upload_dir();
	$dir = trailingslashit( $uploads['basedir'] ) . 'vio-qa-fixtures';
	wp_mkdir_p( $dir );
	return trailingslashit( $dir ) . 'vio-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 8, false, false ) . '.' . $extension;
}

function vio_color( $image, int $red, int $green, int $blue ): int {
	return imagecolorallocate( $image, ( ( $red % 255 ) + 255 ) % 255, ( ( $green % 255 ) + 255 ) % 255, ( ( $blue % 255 ) + 255 ) % 255 );
}

function vio_make_image( string $format = 'jpg', int $variant = 0, int $quality = 94 ): string {
	$width = 1400;
	$height = 900;
	$image = imagecreatetruecolor( $width, $height );
	if ( false === $image ) {
		throw new RuntimeException( 'GD could not allocate a synthetic fixture.' );
	}
	$background = vio_color( $image, 28 + 37 * $variant, 92 + 53 * $variant, 155 + 29 * $variant );
	imagefill( $image, 0, 0, $background );
	for ( $x = 0; $x < $width; $x += 13 ) {
		$color = vio_color( $image, $x + 41 * $variant, 255 - $x + 17 * $variant, 70 + 3 * $x + 19 * $variant );
		imageline( $image, $x, 0, $width - $x - 1, $height - 1, $color );
	}
	for ( $y = 0; $y < $height; $y += 17 ) {
		$color = vio_color( $image, 17 * $variant + $y, 100 + 2 * $y, 220 - $y + 11 * $variant );
		imageline( $image, 0, $y, $width - 1, $y, $color );
	}
	$path = vio_fixture_path( $format );
	if ( 'webp' === $format ) {
		$saved = imagewebp( $image, $path, $quality );
	} elseif ( 'png' === $format ) {
		$saved = imagepng( $image, $path, 6 );
	} else {
		$saved = imagejpeg( $image, $path, $quality );
	}
	imagedestroy( $image );
	if ( ! $saved || ! is_file( $path ) ) {
		throw new RuntimeException( 'Could not write synthetic ' . $format . ' fixture.' );
	}
	return $path;
}

function vio_upload_image( string $format = 'jpg', int $variant = 0, int $quality = 94 ): int {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$path = vio_make_image( $format, $variant, $quality );
	$mime = 'webp' === $format ? 'image/webp' : ( 'png' === $format ? 'image/png' : 'image/jpeg' );
	$id = media_handle_sideload(
		[
			'name'     => basename( $path ),
			'tmp_name' => $path,
			'type'     => $mime,
			'error'    => 0,
			'size'     => filesize( $path ),
		],
		0,
		'Vacuum QA synthetic image'
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'WordPress sideload failed: ' . $id->get_error_message() );
	}
	update_post_meta( (int) $id, '_vio_qa_fixture', '1.2.2' );
	return (int) $id;
}

function vio_derivative_paths( int $attachment_id ): array {
	$paths = [];
	$registry = get_post_meta( $attachment_id, '_vacimg_derivatives', true );
	if ( is_array( $registry ) ) {
		foreach ( $registry as $formats ) {
			if ( ! is_array( $formats ) ) {
				continue;
			}
			foreach ( $formats as $entry ) {
				if ( is_array( $entry ) && ! empty( $entry['path'] ) ) {
					$paths[] = wp_normalize_path( (string) $entry['path'] );
				}
			}
		}
	}
	foreach ( [ '_vacimg_webp_path', '_vacimg_avif_path' ] as $key ) {
		$path = get_post_meta( $attachment_id, $key, true );
		if ( is_string( $path ) && '' !== $path ) {
			$paths[] = wp_normalize_path( $path );
		}
	}
	$source = get_attached_file( $attachment_id );
	$paths = array_filter( array_unique( $paths ), static function ( $path ) use ( $source ) {
		return $path !== wp_normalize_path( (string) $source );
	} );
	return array_values( $paths );
}

function vio_derivative_attachment_ids( int $source_id ): array {
	return array_map(
		'absint',
		get_posts(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[ 'key' => '_vacimg_source_attachment_id', 'value' => $source_id ],
				],
			]
		)
	);
}

$vio_settings_before = get_option( CompressionSettings::OPTION_NAME, [] );
$vio_settings = is_array( $vio_settings_before ) ? $vio_settings_before : [];
$vio_settings = array_merge(
	$vio_settings,
	[
		'enable_webp'                    => true,
		'enable_avif'                    => true,
		'enable_backups'                 => true,
		'enable_frontend_delivery'       => true,
		'preferred_format'               => 'auto',
		'generate_registered_derivatives' => true,
		'auto_optimize_uploads'           => false,
		'safe_mode'                      => true,
		'quality'                         => 82,
		'avif_quality'                    => 60,
	]
);
update_option( CompressionSettings::OPTION_NAME, $vio_settings );

$vio_main_id = vio_upload_image( 'jpg', 1, 94 );
$vio_main_path = wp_normalize_path( (string) get_attached_file( $vio_main_id ) );
$vio_original_hash = hash_file( 'sha256', $vio_main_path );
$vio_webp_result = ( new WebPGenerator() )->generate( $vio_main_id );
$vio_webp_path = wp_normalize_path( (string) get_post_meta( $vio_main_id, '_vacimg_webp_path', true ) );
$vio_avif_result = ( new AVIFGenerator() )->generate( $vio_main_id );
$vio_responsive = new ResponsiveDerivativeGenerator();
$vio_responsive->generate( $vio_main_id, (array) wp_get_attachment_metadata( $vio_main_id ) );

vio_test( 'WebP generation succeeds and preserves the JPEG source checksum', static function () use ( $vio_main_id, $vio_main_path, $vio_original_hash, $vio_webp_result ) {
	vio_assert( ! empty( $vio_webp_result['success'] ), 'WebP generator reported failure.' );
	vio_assert( hash_file( 'sha256', $vio_main_path ) === $vio_original_hash, 'The JPEG source changed during derivative generation.' );
	vio_assert( is_file( (string) get_post_meta( $vio_main_id, '_vacimg_webp_path', true ) ), 'The WebP derivative is missing.' );
} );

vio_test( 'AVIF generation succeeds when GD and Imagick support it', static function () use ( $vio_avif_result, $vio_main_id ) {
	vio_assert( ! empty( $vio_avif_result['success'] ), 'AVIF generator reported failure: ' . (string) ( $vio_avif_result['message'] ?? '' ) );
	vio_assert( is_file( (string) get_post_meta( $vio_main_id, '_vacimg_avif_path', true ) ), 'The AVIF derivative is missing.' );
} );

vio_test( 'PNG upload receives valid server-side metadata and responsive modern derivatives', static function () {
	$id = vio_upload_image( 'png', 2, 94 );
	$metadata = wp_get_attachment_metadata( $id );
	vio_assert( is_array( $metadata ) && ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ), 'WordPress did not create dimensions for a PNG upload.' );
	$counts = ( new ResponsiveDerivativeGenerator() )->generate( $id, $metadata );
	vio_assert( 0 === $counts['failed'] && ( $counts['webp'] + $counts['avif'] ) >= 1, 'The server-side PNG path did not generate modern derivatives.' );
	$source = (string) get_attached_file( $id );
	vio_assert( 'image/png' === wp_get_image_mime( $source ), 'PNG source MIME changed during derivative generation.' );
} );

vio_test( 'backup is created before image optimization and can be restored', static function () use ( $vio_main_id, $vio_original_hash ) {
	$backup = (string) get_post_meta( $vio_main_id, '_vacimg_backup_path', true );
	vio_assert( '' !== $backup && is_file( $backup ), 'The original backup file is missing.' );
	vio_assert( hash_file( 'sha256', $backup ) === $vio_original_hash, 'The backup checksum differs from the pre-optimization original.' );
} );

vio_test( 'frontend negotiates WebP and falls back after a derivative is truncated', static function () use ( $vio_main_id, $vio_main_path, $vio_webp_path ) {
	vio_assert( is_file( $vio_webp_path ), 'WebP output is unavailable for negotiation.' );
	$_SERVER['HTTP_ACCEPT'] = 'image/webp';
	$original_url = (string) wp_get_attachment_url( $vio_main_id );
	$engine = new DeliveryEngine();
	$before = $engine->diagnostic_candidate( $vio_main_id, $vio_main_path, $original_url );
	vio_assert( $before !== $original_url, 'A valid WebP was not selected.' );
	$bytes = file_get_contents( $vio_webp_path );
	vio_assert( is_string( $bytes ) && strlen( $bytes ) > 1024, 'The WebP fixture is too small to corrupt safely.' );
	file_put_contents( $vio_webp_path, substr( $bytes, 0, -1 ) );
	$after = ( new DeliveryEngine() )->diagnostic_candidate( $vio_main_id, $vio_main_path, $original_url );
	vio_assert( $after === $original_url, 'Frontend selected a derivative whose byte length no longer matches its recorded file.' );
	$vio_responsive = new ResponsiveDerivativeGenerator();
	$vio_responsive->generate( $vio_main_id, (array) wp_get_attachment_metadata( $vio_main_id ) );
	vio_assert( hash_file( 'sha256', $vio_webp_path ) !== hash( 'sha256', substr( $bytes, 0, -1 ) ), 'A corrupt tracked derivative was not regenerated.' );
} );

vio_test( 'derivative generation is idempotent for a ready source', static function () use ( $vio_main_id, $vio_responsive ) {
	$before = get_post_meta( $vio_main_id, '_vacimg_derivatives', true );
	$counts = $vio_responsive->generate( $vio_main_id, (array) wp_get_attachment_metadata( $vio_main_id ) );
	vio_assert( 0 === $counts['webp'] && 0 === $counts['avif'] && 0 === $counts['failed'], 'A second pass regenerated or failed a ready derivative.' );
	vio_assert( $before === get_post_meta( $vio_main_id, '_vacimg_derivatives', true ), 'An idempotent pass changed the derivative registry.' );
} );

vio_test( 'Accept negotiation honors MIME tokens and q-values', static function () use ( $vio_main_id, $vio_main_path ) {
	$original_url = (string) wp_get_attachment_url( $vio_main_id );
	$_SERVER['HTTP_ACCEPT'] = 'image/avif;q=0, IMAGE/WEBP;Q=0.5';
	$webp = ( new DeliveryEngine() )->diagnostic_candidate( $vio_main_id, $vio_main_path, $original_url );
	vio_assert( false !== strpos( $webp, '.webp' ), 'The client accepting WebP but refusing AVIF did not receive WebP.' );
	$_SERVER['HTTP_ACCEPT'] = 'image/avif;q=1.0, image/webp;q=0.2';
	$avif = ( new DeliveryEngine() )->diagnostic_candidate( $vio_main_id, $vio_main_path, $original_url );
	vio_assert( false !== strpos( $avif, '.avif' ), 'The higher-quality AVIF preference was ignored.' );
	$_SERVER['HTTP_ACCEPT'] = 'image/avif;q=0, image/webp;q=0';
	$none = ( new DeliveryEngine() )->diagnostic_candidate( $vio_main_id, $vio_main_path, $original_url );
	vio_assert( $none === $original_url, 'The server selected a format the client explicitly rejected.' );
} );

vio_test( 'replacing an image regenerates its tracked derivative instead of re-registering stale pixels', static function () use ( $vio_main_id, $vio_main_path, $vio_webp_path, $vio_responsive ) {
	$old_derivative_hash = hash_file( 'sha256', $vio_webp_path );
	$replacement = vio_make_image( 'jpg', 4, 96 );
	vio_assert( copy( $replacement, $vio_main_path ), 'Could not replace the QA source image.' );
	$vio_responsive->generate( $vio_main_id, (array) wp_get_attachment_metadata( $vio_main_id ) );
	$new_derivative_hash = hash_file( 'sha256', $vio_webp_path );
	vio_assert( $new_derivative_hash !== $old_derivative_hash, 'A stale derivative was re-registered for the replacement source.' );
} );

vio_test( 'source replacement gets its own backup without deleting the previous backup', static function () use ( $vio_main_id, $vio_main_path ) {
	$new_source_hash = hash_file( 'sha256', $vio_main_path );
	$old_backup = (string) get_post_meta( $vio_main_id, '_vacimg_backup_path', true );
	$old_backup_hash = is_file( $old_backup ) ? hash_file( 'sha256', $old_backup ) : '';
	$result = ( new WebPGenerator() )->generate( $vio_main_id );
	vio_assert( ! empty( $result['success'] ), 'WebP metadata refresh failed after source replacement.' );
	$new_backup = (string) get_post_meta( $vio_main_id, '_vacimg_backup_path', true );
	vio_assert( is_file( $new_backup ) && hash_file( 'sha256', $new_backup ) === $new_source_hash, 'The replacement source was not backed up before processing.' );
	vio_assert( $new_backup !== $old_backup && '' !== $old_backup_hash && hash_file( 'sha256', $old_backup ) === $old_backup_hash, 'The previous backup was overwritten or removed.' );
} );

vio_test( 'restore removes every registered derivative while preserving its backup', static function () use ( $vio_main_id, $vio_main_path ) {
	$expected_source_hash = hash_file( 'sha256', $vio_main_path );
	$paths = vio_derivative_paths( $vio_main_id );
	$backup = (string) get_post_meta( $vio_main_id, '_vacimg_backup_path', true );
	vio_assert( count( $paths ) >= 2, 'The attachment has no responsive derivatives to verify.' );
	$result = ( new RestoreEngine() )->restore( $vio_main_id );
	vio_assert( ! empty( $result['success'] ), 'Restore failed: ' . (string) ( $result['message'] ?? '' ) );
	vio_assert( hash_file( 'sha256', $vio_main_path ) === $expected_source_hash, 'Restore did not return the latest saved original.' );
	foreach ( $paths as $path ) {
		vio_assert( ! file_exists( $path ), 'Restore left a registered derivative on disk: ' . basename( $path ) );
	}
	vio_assert( is_file( $backup ), 'Restore removed the source backup.' );
	vio_assert( empty( get_post_meta( $vio_main_id, '_vacimg_derivatives', true ) ), 'Restore left derivative registry metadata.' );
} );

vio_test( 'a WebP source can be recompressed backup-first and restored byte-for-byte', static function () {
	$id = vio_upload_image( 'webp', 3, 100 );
	$source = wp_normalize_path( (string) get_attached_file( $id ) );
	$before = hash_file( 'sha256', $source );
	$result = ( new WebPGenerator() )->generate( $id );
	vio_assert( ! empty( $result['success'] ), 'WebP source optimization failed: ' . (string) ( $result['message'] ?? '' ) );
	$backup = (string) get_post_meta( $id, '_vacimg_backup_path', true );
	vio_assert( is_file( $backup ) && hash_file( 'sha256', $backup ) === $before, 'The WebP source backup is not the byte-for-byte pre-optimization image.' );
	$restore = ( new RestoreEngine() )->restore( $id );
	vio_assert( ! empty( $restore['success'] ), 'WebP source restore failed.' );
	vio_assert( hash_file( 'sha256', $source ) === $before, 'WebP source was not restored byte-for-byte.' );
	vio_assert( is_file( $backup ), 'The WebP source backup was removed after restore.' );
} );

vio_test( 'restore rejects a backup path outside the plugin backup directory', static function () {
	$id = vio_upload_image( 'jpg', 5, 94 );
	$source = wp_normalize_path( (string) get_attached_file( $id ) );
	$before = file_get_contents( $source );
	$existing_backup = get_post_meta( $id, '_vacimg_backup_path', true );
	update_post_meta( $id, '_vacimg_backup_path', '/etc/hostname' );
	$result = ( new RestoreEngine() )->restore( $id );
	$traversal = trailingslashit( wp_upload_dir()['basedir'] ) . 'vacimg-backups/../invalid.jpg';
	update_post_meta( $id, '_vacimg_backup_path', $traversal );
	$traversal_result = ( new RestoreEngine() )->restore( $id );
	$unchanged = hash_equals( hash( 'sha256', (string) $before ), hash_file( 'sha256', $source ) );
	if ( ! $unchanged ) {
		file_put_contents( $source, (string) $before );
	}
	if ( is_string( $existing_backup ) && '' !== $existing_backup ) {
		update_post_meta( $id, '_vacimg_backup_path', $existing_backup );
	} else {
		delete_post_meta( $id, '_vacimg_backup_path' );
	}
	vio_assert( empty( $result['success'] ), 'Restore accepted a system path as an original backup.' );
	vio_assert( empty( $traversal_result['success'] ), 'Restore accepted a traversal path under the backup prefix.' );
	vio_assert( $unchanged, 'Rejected restore changed the QA source image.' );
} );

vio_test( 'CSV output neutralizes spreadsheet formulas in string cells', static function () {
	vio_assert( method_exists( ReportExporter::class, 'escape_csv_cell' ), 'CSV formula neutralization helper is missing.' );
	vio_assert( "'=SUM(1,1)" === ReportExporter::escape_csv_cell( '=SUM(1,1)' ), 'An equals formula was not neutralized.' );
	vio_assert( "'  @SUM(1,1)" === ReportExporter::escape_csv_cell( '  @SUM(1,1)' ), 'A whitespace-prefixed formula was not neutralized.' );
	vio_assert( 'normal.jpg' === ReportExporter::escape_csv_cell( 'normal.jpg' ), 'A normal filename was changed.' );
} );

vio_test( 'invalid image bytes are rejected even when the attachment says image/jpeg', static function () {
	$uploads = wp_upload_dir();
	$path = trailingslashit( $uploads['basedir'] ) . 'vio-qa-fixtures/invalid-' . wp_generate_password( 10, false, false ) . '.jpg';
	file_put_contents( $path, 'not an image' );
	$id = wp_insert_attachment(
		[
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'VIO QA Invalid MIME',
			'post_status'    => 'inherit',
		],
		$path,
		0,
		true
	);
	vio_assert( ! is_wp_error( $id ), 'Could not create the invalid MIME test attachment.' );
	update_post_meta( (int) $id, '_vio_qa_fixture', '1.2.2' );
	vio_assert( ! ImageFormat::is_supported_source_attachment( (int) $id ), 'Non-image bytes were accepted based only on the .jpg extension and attachment MIME.' );
} );

vio_test( 'queue rejects duplicate attachment jobs and atomically claims a pending job once', static function () {
	global $wpdb;
	$id = vio_upload_image( 'jpg', 6, 94 );
	$manager = new QueueManager();
	vio_assert( $manager->add_attachment( $id ), 'The first queue insertion failed.' );
	vio_assert( ! $manager->add_attachment( $id ), 'A duplicate queue insertion was accepted.' );
	$table = $wpdb->prefix . 'vacimg_queue';
	$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE attachment_id = %d", $id ) );
	vio_assert( 1 === $count, 'The attachment has more than one queue row.' );
	$queue_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE attachment_id = %d", $id ) );
	vio_assert( $manager->claim_job( $queue_id ), 'The first atomic queue claim failed.' );
	vio_assert( ! $manager->claim_job( $queue_id ), 'The same pending queue row was claimed twice.' );
	vio_assert( $manager->mark_completed( $queue_id ), 'Could not finalize the atomic claim fixture.' );
} );

vio_test( 'retrying failed automatic uploads resets their status and completes successfully', static function () {
	global $wpdb;
	$ids = [ vio_upload_image( 'webp', 9, 82 ), vio_upload_image( 'webp', 10, 82 ) ];
	$manager = new QueueManager();
	$table = $wpdb->prefix . 'vacimg_queue';
	$queue_ids = [];

	foreach ( $ids as $id ) {
		update_post_meta( $id, '_vacimg_auto_status', 'failed' );
		vio_assert( $manager->add_attachment( $id ), 'Could not add the retry fixture to the queue.' );
		$queue_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE attachment_id = %d", $id ) );
		vio_assert( $queue_id > 0 && $manager->mark_failed( $queue_id, 'Synthetic first-attempt failure.' ), 'Could not mark the retry fixture failed.' );
		$queue_ids[] = $queue_id;
	}

	vio_assert( $manager->retry_job( $queue_ids[0] ), 'The per-job retry was rejected.' );
	vio_assert( 'pending' === get_post_meta( $ids[0], '_vacimg_auto_status', true ), 'The per-job retry left automatic-upload status failed.' );
	vio_assert( 1 === $manager->retry_failed_jobs(), 'The bulk retry did not requeue exactly one remaining failed job.' );
	vio_assert( 'pending' === get_post_meta( $ids[1], '_vacimg_auto_status', true ), 'The bulk retry left automatic-upload status failed.' );

	$manager->start_queue();
	$result = ( new QueueProcessor( $manager ) )->process_batch( 2 );
	vio_assert( 2 === absint( $result['completed'] ) && 0 === absint( $result['failed'] ), 'Retried WebP uploads did not complete.' );
	foreach ( $ids as $id ) {
		$state = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE attachment_id = %d", $id ) );
		vio_assert( 'completed' === $state, 'A retried queue row did not reach completed state.' );
		vio_assert( 'optimized' === get_post_meta( $id, '_vacimg_auto_status', true ), 'Successful retry did not clear the automatic-upload failure state.' );
	}
} );

vio_test( 'authors cannot see or invoke image actions for another user\'s attachment', static function () {
	global $vio_admin_id;
	$id = vio_upload_image( 'jpg', 7, 94 );
	$author_id = username_exists( 'vio_qa_author' );
	if ( ! $author_id ) {
		$author_id = wp_insert_user(
			[
				'user_login' => 'vio_qa_author',
				'user_pass'  => wp_generate_password( 32, true, true ),
				'user_email' => 'vio-qa-author@example.invalid',
				'role'       => 'author',
			]
		);
	}
	vio_assert( ! is_wp_error( $author_id ), 'Could not create the restricted author role.' );
	wp_set_current_user( (int) $author_id );
	vio_assert( current_user_can( 'upload_files' ) && ! current_user_can( 'edit_post', $id ), 'The test user does not have the expected restricted capabilities.' );
	$post = get_post( $id );
	$actions = ( new AttachmentActions() )->add_row_actions( [], $post );
	vio_assert( ! isset( $actions['vacimg_generate_webp'] ), 'A non-owner sees a row action for another user\'s media.' );
	ob_start();
	( new LibraryIntegration() )->render_column( 'vacimg_image_optimizer', $id );
	$inline = (string) ob_get_clean();
	vio_assert( false === strpos( $inline, 'vacimg-media-action' ), 'A non-owner sees inline actions for another user\'s media.' );
	$before = get_post_meta( $id, '_vacimg_webp_path', true );
	$action = 'vacimg_generate_webp';
	$_GET = [ 'action' => $action, 'attachment_id' => $id, '_wpnonce' => wp_create_nonce( $action . '_' . $id ) ];
	$_REQUEST = $_GET;
	$die_filter = static function () {
		return static function () {
			throw new RuntimeException( 'blocked' );
		};
	};
	$redirect_filter = static function () {
		throw new RuntimeException( 'redirected' );
	};
	add_filter( 'wp_die_handler', $die_filter );
	add_filter( 'wp_redirect', $redirect_filter );
	$caught = '';
	try {
		( new AttachmentActions() )->handle_action();
	} catch ( Throwable $throwable ) {
		$caught = $throwable->getMessage();
	} finally {
		remove_filter( 'wp_die_handler', $die_filter );
		remove_filter( 'wp_redirect', $redirect_filter );
		wp_set_current_user( $vio_admin_id );
	}
	vio_assert( 'blocked' === $caught, 'An author with upload_files invoked an action on another user\'s attachment.' );
	vio_assert( $before === get_post_meta( $id, '_vacimg_webp_path', true ), 'The restricted action changed image metadata.' );
} );

vio_test( 'modern-format migration is idempotent across completed reruns', static function () {
	$settings = CompressionSettings::get();
	$without_delivery = $settings;
	$without_delivery['enable_frontend_delivery'] = false;
	update_option( CompressionSettings::OPTION_NAME, $without_delivery );
	$id = vio_upload_image( 'jpg', 8, 94 );
	$with_delivery = $without_delivery;
	$with_delivery['enable_frontend_delivery'] = true;
	update_option( CompressionSettings::OPTION_NAME, $with_delivery );
	$migration = new ModernFormatMigration();
	$only_fixture = static function ( $query ) use ( $id ) {
		if ( $query instanceof WP_Query && 'attachment' === $query->get( 'post_type' ) && 'image' === $query->get( 'post_mime_type' ) ) {
			$query->set( 'post__in', [ $id ] );
		}
	};
	add_action( 'pre_get_posts', $only_fixture );
	try {
		$scan = $migration->scan();
		vio_assert( 1 === (int) $scan['summary']['scanned'], 'The migration fixture query did not isolate one synthetic attachment.' );
		vio_assert( (int) $scan['summary']['missing'] > 0, 'The migration fixture unexpectedly began delivery-ready.' );
		$migration->start();
		$first = $migration->process_batch( 10 );
		vio_assert( 'completed' === $first['state'], 'The initial migration pass did not complete.' );
		vio_assert( (int) $first['results']['generated'] > 0, 'The initial migration did not generate missing derivatives.' );
		$registry = get_post_meta( $id, '_vacimg_derivatives', true );
		$ready = $migration->scan();
		vio_assert( 1 === (int) $ready['summary']['scanned'] && 0 === (int) $ready['summary']['missing'] && 0 === (int) $ready['summary']['stale'], 'The completed migration did not report the attachment ready on its next scan.' );
		$migration->start();
		$second = $migration->process_batch( 10 );
		vio_assert( 'completed' === $second['state'], 'The repeated migration pass did not complete.' );
		vio_assert( 0 === (int) $second['results']['generated'], 'A completed migration rerun generated duplicate derivatives.' );
		vio_assert( $registry === get_post_meta( $id, '_vacimg_derivatives', true ), 'A completed migration rerun changed the derivative registry.' );
	} finally {
		remove_action( 'pre_get_posts', $only_fixture );
		update_option( CompressionSettings::OPTION_NAME, $settings );
	}
} );

vio_test( 'attachment deletion removes plugin derivatives but preserves its backup', static function () {
	$id = vio_upload_image( 'jpg', 8, 94 );
	$path = (string) get_attached_file( $id );
	$generated = ( new WebPGenerator() )->generate( $id );
	vio_assert( ! empty( $generated['success'] ), 'Could not create deletion cleanup derivatives.' );
	$generator = new ResponsiveDerivativeGenerator();
	$generator->generate( $id, (array) wp_get_attachment_metadata( $id ) );
	$paths = vio_derivative_paths( $id );
	$backup = (string) get_post_meta( $id, '_vacimg_backup_path', true );
	$linked_ids = vio_derivative_attachment_ids( $id );
	vio_assert( count( $paths ) >= 1 && is_file( $backup ), 'The cleanup fixture did not create derivatives and backup.' );
	wp_delete_attachment( $id, true );
	foreach ( $paths as $derivative ) {
		vio_assert( ! file_exists( $derivative ), 'Deleting the source left a plugin derivative on disk: ' . basename( $derivative ) );
	}
	foreach ( $linked_ids as $derivative_id ) {
		vio_assert( ! get_post( $derivative_id ), 'Deleting the source left its derivative attachment in the Media Library.' );
	}
	vio_assert( ! file_exists( $path ), 'WordPress did not delete the source file.' );
	vio_assert( is_file( $backup ), 'Source deletion removed an existing backup.' );
} );

fwrite( STDOUT, "SUMMARY: {$GLOBALS['vio_pass']} passed, {$GLOBALS['vio_fail']} failed\n" );
if ( $GLOBALS['vio_fail'] > 0 ) {
	WP_CLI::error( 'One or more Vacuum Image Optimizer integration regressions failed.' );
}
WP_CLI::success( 'All Vacuum Image Optimizer integration regressions passed.' );
