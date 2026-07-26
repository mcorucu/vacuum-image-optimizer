<?php
/** Admin endpoints for migration and delivery diagnostics. */

namespace VacuumImageOptimizer\Admin;

use VacuumImageOptimizer\Frontend\ModernFormatMigration;

defined( 'ABSPATH' ) || exit;

/** Protected AJAX controller for existing-media preparation. */
class DeliveryMigrationAjax {
	private ModernFormatMigration $migration;
	public function __construct() { $this->migration = new ModernFormatMigration(); }
	public function register(): void {
		foreach ( [ 'scan', 'start', 'pause', 'resume', 'cancel', 'retry', 'process', 'status', 'diagnostics' ] as $action ) { add_action( 'wp_ajax_vacimg_migration_' . $action, [ $this, $action ] ); }
	}
	public function scan(): void { $this->verify(); wp_send_json_success( $this->migration->scan() ); }
	public function start(): void { $this->verify(); wp_send_json_success( $this->migration->start() ); }
	public function pause(): void { $this->verify(); wp_send_json_success( $this->migration->pause() ); }
	public function resume(): void { $this->verify(); wp_send_json_success( $this->migration->start() ); }
	public function cancel(): void { $this->verify(); wp_send_json_success( $this->migration->cancel() ); }
	public function retry(): void { $this->verify(); wp_send_json_success( $this->migration->retry_failed() ); }
	public function process(): void { $this->verify(); wp_send_json_success( $this->migration->process_batch() ); }
	public function status(): void { $this->verify(); wp_send_json_success( $this->migration->get_state() ); }
	public function diagnostics(): void {
		$this->verify();
		$id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( 0 === $id || 'attachment' !== get_post_type( $id ) ) { wp_send_json_error( [ 'message' => __( 'Select a valid attachment.', 'vacuum-image-optimizer' ) ], 400 ); }
		wp_send_json_success( $this->migration->inspect( $id ) );
	}
	private function verify(): void { check_ajax_referer( 'vacimg_queue_ajax', 'nonce' ); if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => __( 'You are not allowed to manage delivery preparation.', 'vacuum-image-optimizer' ) ], 403 ); } }
}
