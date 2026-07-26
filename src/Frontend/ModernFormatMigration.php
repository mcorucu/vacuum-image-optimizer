<?php
/**
 * Existing-media modern format migration.
 *
 * @package VacuumImageOptimizer\Frontend
 */

namespace VacuumImageOptimizer\Frontend;

use VacuumImageOptimizer\Settings\CompressionSettings;
use VacuumImageOptimizer\Utils\ImageFormat;

defined( 'ABSPATH' ) || exit;

/**
 * Runs a bounded, resumable scan over existing local image attachments.
 */
class ModernFormatMigration {

	private const OPTION_STATE = 'vacimg_modern_migration_state';

	/** @return array<string, mixed> */
	public function get_state(): array {
		$state = get_option( self::OPTION_STATE, [] );
		return is_array( $state ) ? $state : [];
	}

	/**
	 * Build a non-destructive readiness report and reset the migration cursor.
	 *
	 * @return array<string, mixed>
	 */
	public function scan(): array {
		$ids   = get_posts( [ 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ] );
		$state = $this->new_state( array_map( 'absint', (array) $ids ) );
		foreach ( $state['ids'] as $attachment_id ) {
			$state['summary'] = $this->add_summary( $state['summary'], $this->inspect( (int) $attachment_id ) );
		}
		$state['summary']['estimated_operations'] = $state['summary']['missing'] + $state['summary']['stale'];
		$state['state'] = 'ready';
		update_option( self::OPTION_STATE, $state, false );
		return $state;
	}

	/** Start or resume the current migration. */
	public function start(): array {
		$state = $this->get_state();
		if ( empty( $state['ids'] ) ) {
			$state = $this->scan();
		}
		$state['state'] = 'running';
		$state['started_at'] = $state['started_at'] ?: time();
		update_option( self::OPTION_STATE, $state, false );
		return $state;
	}

	public function pause(): array { return $this->set_state( 'paused' ); }
	public function cancel(): array { return $this->set_state( 'cancelled' ); }
	public function retry_failed(): array {
		$state = $this->get_state();
		$state['ids'] = array_values( array_unique( array_map( 'absint', (array) ( $state['failed_ids'] ?? [] ) ) ) );
		$state['cursor'] = 0;
		$state['failed_ids'] = [];
		$state['state'] = empty( $state['ids'] ) ? 'completed' : 'running';
		update_option( self::OPTION_STATE, $state, false );
		return $state;
	}

	/** Process one small batch. */
	public function process_batch( int $limit = 3 ): array {
		$state = $this->get_state();
		if ( 'running' !== ( $state['state'] ?? '' ) ) {
			return $state;
		}
		$limit = max( 1, min( 10, absint( $limit ) ) );
		$ids = array_slice( (array) $state['ids'], absint( $state['cursor'] ?? 0 ), $limit );
		foreach ( $ids as $attachment_id ) {
			$result = $this->process_attachment( absint( $attachment_id ) );
			$state['results'] = $this->merge_results( $state['results'], $result );
			if ( absint( $result['failed'] ?? 0 ) > 0 ) { $state['failed_ids'][] = absint( $attachment_id ); }
			$state['last_attachment'] = absint( $attachment_id );
			++$state['cursor'];
		}
		if ( $state['cursor'] >= count( (array) $state['ids'] ) ) {
			$state['state'] = 'completed';
			$state['finished_at'] = time();
		}
		update_option( self::OPTION_STATE, $state, false );
		return $state;
	}

	/** @return array<string, mixed> */
	public function inspect( int $attachment_id ): array {
		$file = get_attached_file( $attachment_id );
		$file = is_string( $file ) ? wp_normalize_path( $file ) : '';
		$mime = ImageFormat::get_attachment_mime_type( $attachment_id );
		$settings = CompressionSettings::get();
		$meta = (array) wp_get_attachment_metadata( $attachment_id );
		$result = [
			'attachment_id' => $attachment_id,
			'mime' => $mime,
			'source' => DerivativeResolver::relative_path( $file ),
			'original_url' => (string) wp_get_attachment_url( $attachment_id ),
			'selected_candidate' => '',
			'dimensions' => [ 'width' => absint( $meta['width'] ?? 0 ), 'height' => absint( $meta['height'] ?? 0 ) ],
			'delivery_enabled' => ! empty( $settings['enable_frontend_delivery'] ),
			'preferred_format' => sanitize_key( (string) ( $settings['preferred_format'] ?? 'auto' ) ),
			'delivery_state' => sanitize_key( (string) get_post_meta( $attachment_id, '_vacimg_delivery_state', true ) ),
			'generated_at' => sanitize_text_field( (string) get_post_meta( $attachment_id, '_vacimg_delivery_generated_at', true ) ),
			'error' => sanitize_text_field( (string) get_post_meta( $attachment_id, '_vacimg_error_message', true ) ),
			'queue_state' => $this->get_queue_state( $attachment_id ),
			'status' => 'unsupported',
			'sizes' => [],
			'reason' => '',
		];
		if ( '' === DerivativeResolver::relative_path( $file ) ) {
			$result['reason'] = __( 'The attachment is outside the local uploads directory.', 'vacuum-image-optimizer' );
			return $result;
		}
		$result['selected_candidate'] = ( new DeliveryEngine() )->diagnostic_candidate( $attachment_id, $file, (string) $result['original_url'] );
		if ( ! ImageFormat::is_supported_source_attachment( $attachment_id ) ) {
			$result['reason'] = ImageFormat::get_skip_reason( $attachment_id );
			return $result;
		}
		$sources = [ 'full' => $file ];
		foreach ( (array) ( $meta['sizes'] ?? [] ) as $name => $size ) {
			$relative = is_array( $size ) ? sanitize_file_name( (string) ( $size['file'] ?? '' ) ) : '';
			if ( '' !== $relative ) { $sources[ sanitize_key( (string) $name ) ] = trailingslashit( dirname( $file ) ) . $relative; }
		}
		$ready = 0; $missing = 0; $stale = 0;
		foreach ( $sources as $name => $source ) {
			$row = [ 'webp' => 'disabled', 'avif' => 'disabled', 'source' => DerivativeResolver::relative_path( $source ) ];
			foreach ( [ 'webp', 'avif' ] as $format ) {
				if ( ( 'webp' === $format && ! CompressionSettings::is_webp_enabled() ) || ( 'avif' === $format && ! CompressionSettings::is_avif_enabled() ) ) { continue; }
				$inspection = DerivativeResolver::inspect( $attachment_id, $source, $format );
				$row[ $format ] = $inspection['status'];
				if ( 'ready' === $inspection['status'] ) { ++$ready; } elseif ( 'stale' === $inspection['status'] ) { ++$stale; } else { ++$missing; }
			}
			$result['sizes'][ $name ] = $row;
		}
		$result['status'] = 0 === $missing && 0 === $stale ? 'ready' : ( $ready > 0 ? 'partial' : 'missing' );
		$result['missing'] = $missing; $result['stale'] = $stale; $result['ready'] = $ready;
		return $result;
	}

	private function get_queue_state( int $attachment_id ): string {
		global $wpdb;
		$table = $wpdb->prefix . 'vacimg_queue';
		$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE attachment_id = %d ORDER BY id DESC LIMIT 1", $attachment_id ) );
		return sanitize_key( is_string( $status ) ? $status : '' );
	}

	/** @return array<string, mixed> */
	private function process_attachment( int $attachment_id ): array {
		$inspection = $this->inspect( $attachment_id );
		if ( 'unsupported' === $inspection['status'] ) { return [ 'skipped' => 1, 'failed' => 0, 'generated' => 0, 'preserved' => 0, 'repaired' => 0 ]; }
		$before = $inspection;
		$generated = ( new ResponsiveDerivativeGenerator() )->generate( $attachment_id );
		$after = $this->inspect( $attachment_id );
		return [
			'skipped' => absint( $generated['skipped'] ?? 0 ),
			'failed' => absint( $generated['failed'] ?? 0 ),
			'generated' => absint( $generated['webp'] ?? 0 ) + absint( $generated['avif'] ?? 0 ),
			'preserved' => absint( $before['ready'] ?? 0 ),
			'repaired' => max( 0, absint( $before['stale'] ?? 0 ) - absint( $after['stale'] ?? 0 ) ),
		];
	}

	/** @return array<string, mixed> */
	private function new_state( array $ids ): array {
		return [ 'state' => 'idle', 'ids' => $ids, 'cursor' => 0, 'started_at' => 0, 'finished_at' => 0, 'last_attachment' => 0, 'failed_ids' => [], 'summary' => [ 'scanned' => count( $ids ), 'ready' => 0, 'partial' => 0, 'missing' => 0, 'stale' => 0, 'excluded' => 0, 'unsupported' => 0, 'estimated_operations' => 0 ], 'results' => [ 'generated' => 0, 'preserved' => 0, 'repaired' => 0, 'skipped' => 0, 'failed' => 0 ] ];
	}

	/** @param array<string, int> $summary @param array<string, mixed> $item @return array<string, int> */
	private function add_summary( array $summary, array $item ): array { $key = sanitize_key( (string) ( $item['status'] ?? 'unsupported' ) ); if ( isset( $summary[ $key ] ) ) { ++$summary[ $key ]; } return $summary; }
	/** @param array<string, int> $left @param array<string, int> $right @return array<string, int> */
	private function merge_results( array $left, array $right ): array { foreach ( $right as $key => $value ) { $left[ $key ] = absint( $left[ $key ] ?? 0 ) + absint( $value ); } return $left; }
	/** @return array<string, mixed> */
	private function set_state( string $state ): array { $current = $this->get_state(); $current['state'] = sanitize_key( $state ); update_option( self::OPTION_STATE, $current, false ); return $current; }
}
