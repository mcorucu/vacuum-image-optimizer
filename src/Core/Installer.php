<?php
/**
 * Plugin activation handler.
 *
 * @package VacuumImageOptimizer\Core
 */

namespace VacuumImageOptimizer\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation.
 */
class Installer {

	/**
	 * Plugin option key for DB version.
	 *
	 * @var string
	 */
	private const OPTION_DB_VERSION = 'vio_db_version';

	/**
	 * Option key used to mark the Phase 5 queue schema as installed.
	 *
	 * @var string
	 */
	private const OPTION_QUEUE_READY = 'vio_phase5_queue_ready';

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::check_requirements();
		self::create_tables();
		self::set_default_options();
		self::schedule_cron();
	}

	/**
	 * Run safe database upgrades when the admin area loads.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$stored_version = (string) get_option( self::OPTION_DB_VERSION, '' );
		$queue_ready    = (bool) get_option( self::OPTION_QUEUE_READY, false );

		if ( VIO_VERSION === $stored_version && $queue_ready ) {
			return;
		}

		self::create_tables();
		self::set_default_options();
	}

	/**
	 * Check minimum requirements.
	 *
	 * @return void
	 * @throws \Exception If requirements not met.
	 */
	private static function check_requirements(): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			wp_die(
				esc_html__( 'Vacuum Image Optimizer requires PHP 8.1 or higher.', 'vacuum-image-optimizer' ),
				esc_html__( 'Plugin Activation Error', 'vacuum-image-optimizer' ),
				[ 'back_link' => true ]
			);
		}
	}

	/**
	 * Create custom database tables.
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		$sql_queue = "CREATE TABLE {$prefix}vio_queue (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			attempts int(10) unsigned NOT NULL DEFAULT 0,
			error_message text DEFAULT NULL,
			PRIMARY KEY (id),
			KEY attachment_id (attachment_id),
			KEY status_created (status, created_at)
		) {$charset_collate};";

		$sql_stats = "CREATE TABLE {$prefix}vio_stats (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			size_name varchar(50) NOT NULL DEFAULT 'full',
			original_size bigint(20) unsigned NOT NULL DEFAULT 0,
			optimized_size bigint(20) unsigned NOT NULL DEFAULT 0,
			webp_size bigint(20) unsigned DEFAULT NULL,
			avif_size bigint(20) unsigned DEFAULT NULL,
			compression_ratio decimal(5,2) NOT NULL DEFAULT 0.00,
			profile varchar(20) NOT NULL DEFAULT 'balanced',
			engine varchar(20) NOT NULL DEFAULT 'gd',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY attachment_size (attachment_id, size_name),
			KEY attachment_id (attachment_id)
		) {$charset_collate};";

		$sql_backups = "CREATE TABLE {$prefix}vio_backups (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			size_name varchar(50) NOT NULL DEFAULT 'full',
			backup_path text NOT NULL,
			original_path text NOT NULL,
			file_hash varchar(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			expires_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY attachment_size (attachment_id, size_name),
			KEY expires_at (expires_at),
			KEY attachment_id (attachment_id)
		) {$charset_collate};";

		dbDelta( $sql_queue );
		dbDelta( $sql_stats );
		dbDelta( $sql_backups );
	}

	/**
	 * Set default plugin options.
	 *
	 * @return void
	 */
	private static function set_default_options(): void {
		$defaults = [
			'quality'               => 85,
			'profile'               => 'balanced',
			'auto_optimize_uploads' => false,
			'auto_optimize_mode'    => 'queue',
			'enable_avif'           => false,
			'avif_quality'          => 60,
			'enable_frontend_delivery' => false,
			'preferred_format'      => 'auto',
			'enable_backups'        => true,
			'backup_retention_days' => 0,
			'enable_lazy_loading'   => false,
			'exclude_gif'           => true,
			'exclude_svg'           => true,
			'interface_language'    => 'wordpress',
		];

		if ( false === get_option( 'vio_settings' ) ) {
			add_option( 'vio_settings', $defaults );
		} else {
			$settings = get_option( 'vio_settings', [] );
			if ( is_array( $settings ) ) {
				update_option( 'vio_settings', array_merge( $defaults, $settings ) );
			}
		}

		if ( false === get_option( 'vio_queue_state' ) ) {
			add_option( 'vio_queue_state', 'idle' );
		}

		update_option( self::OPTION_DB_VERSION, VIO_VERSION );
		update_option( self::OPTION_QUEUE_READY, true );
	}

	/**
	 * Schedule cron events.
	 *
	 * @return void
	 */
	private static function schedule_cron(): void {
		if ( ! wp_next_scheduled( 'vio_cleanup_backups' ) ) {
			wp_schedule_event( time(), 'daily', 'vio_cleanup_backups' );
		}
	}
}
