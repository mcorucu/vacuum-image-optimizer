<?php
/**
 * Main plugin bootstrap.
 *
 * @package VacuumImageOptimizer
 */

namespace VacuumImageOptimizer;

use VacuumImageOptimizer\Admin\Assets;
use VacuumImageOptimizer\Admin\Menu;
use VacuumImageOptimizer\Admin\Onboarding;
use VacuumImageOptimizer\Admin\ReportExporter;
use VacuumImageOptimizer\Backup\BackupCleanup;
use VacuumImageOptimizer\Backup\BackupManager;
use VacuumImageOptimizer\Core\Installer;
use VacuumImageOptimizer\Media\AttachmentActions;
use VacuumImageOptimizer\Media\LibraryIntegration;
use VacuumImageOptimizer\Queue\AjaxController;
use VacuumImageOptimizer\Settings\CompressionSettings;
use VacuumImageOptimizer\Frontend\DeliveryEngine;
use VacuumImageOptimizer\Frontend\LazyLoad;
use VacuumImageOptimizer\Upload\UploadAutomation;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the plugin foundation.
 */
class Plugin {

	/**
	 * Single plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Admin menu service.
	 *
	 * @var Menu|null
	 */
	private ?Menu $menu = null;

	/**
	 * Admin asset service.
	 *
	 * @var Assets|null
	 */
	private ?Assets $assets = null;

	/**
	 * Media Library integration service.
	 *
	 * @var LibraryIntegration|null
	 */
	private ?LibraryIntegration $media_library = null;

	/**
	 * Attachment action service.
	 *
	 * @var AttachmentActions|null
	 */
	private ?AttachmentActions $attachment_actions = null;

	/**
	 * Backup infrastructure service.
	 *
	 * @var BackupManager|null
	 */
	private ?BackupManager $backup_manager = null;

	/**
	 * Compression settings service.
	 *
	 * @var CompressionSettings|null
	 */
	private ?CompressionSettings $compression_settings = null;

	/**
	 * Queue AJAX controller.
	 *
	 * @var AjaxController|null
	 */
	private ?AjaxController $queue_ajax = null;

	/**
	 * Report CSV exporter.
	 *
	 * @var ReportExporter|null
	 */
	private ?ReportExporter $report_exporter = null;

	/**
	 * Setup wizard service.
	 *
	 * @var Onboarding|null
	 */
	private ?Onboarding $onboarding = null;

	/**
	 * Upload automation service.
	 *
	 * @var UploadAutomation|null
	 */
	private ?UploadAutomation $upload_automation = null;

	/**
	 * Frontend delivery service.
	 *
	 * @var DeliveryEngine|null
	 */
	private ?DeliveryEngine $delivery_engine = null;

	/**
	 * Frontend lazy loading service.
	 *
	 * @var LazyLoad|null
	 */
	private ?LazyLoad $lazy_load = null;

	/**
	 * Backup cleanup cron service.
	 *
	 * @var BackupCleanup|null
	 */
	private ?BackupCleanup $backup_cleanup = null;

	/**
	 * Create the plugin instance.
	 */
	private function __construct() {
		$this->load_textdomain();

		if ( is_admin() ) {
			$this->register_admin_hooks();
		} else {
			$this->delivery_engine = new DeliveryEngine();
			add_action( 'wp', [ $this->delivery_engine, 'register' ] );

			$this->lazy_load = new LazyLoad();
			add_action( 'wp', [ $this->lazy_load, 'register' ] );
		}

		$this->upload_automation = new UploadAutomation();
		$this->upload_automation->register();

		// Cron runs outside the admin context, so register the handler everywhere.
		$this->backup_cleanup = new BackupCleanup();
		$this->backup_cleanup->register();
	}

	/**
	 * Get the single plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register the plugin locale override.
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		// WordPress loads plugin translations on demand; this keeps the interface-language override available before that happens.
		add_filter( 'plugin_locale', [ $this, 'filter_plugin_locale' ], 10, 2 );
	}

	/**
	 * Override the locale for this plugin's text domain when a language is selected.
	 *
	 * Resolution order: plugin override language, then the WordPress site locale.
	 *
	 * @param string $locale Current locale.
	 * @param string $domain Text domain being loaded.
	 * @return string
	 */
	public function filter_plugin_locale( $locale, $domain ) {
		if ( 'vacuum-image-optimizer' !== $domain ) {
			return $locale;
		}

		if ( ! CompressionSettings::is_language_overridden() ) {
			return $locale;
		}

		return CompressionSettings::get_interface_language();
	}

	/**
	 * Register admin-only hooks.
	 *
	 * @return void
	 */
	private function register_admin_hooks(): void {
		$this->menu                 = new Menu();
		$this->assets               = new Assets();
		$this->media_library        = new LibraryIntegration();
		$this->attachment_actions   = new AttachmentActions();
		$this->backup_manager       = new BackupManager();
		$this->compression_settings = new CompressionSettings();
		$this->queue_ajax           = new AjaxController();
		$this->queue_ajax->register();
		$this->report_exporter      = new ReportExporter();
		$this->report_exporter->register();
		$this->onboarding           = new Onboarding();
		$this->onboarding->register();

		add_action( 'admin_menu', [ $this->menu, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ $this->assets, 'enqueue' ] );
		add_action( 'admin_init', [ Installer::class, 'maybe_upgrade' ] );
		add_action( 'admin_init', [ $this->compression_settings, 'register' ] );
		add_action( 'admin_init', [ $this->backup_manager, 'ensure_backup_directory' ] );
		add_action( 'admin_init', [ $this->media_library, 'register' ] );
		add_action( 'admin_init', [ $this->attachment_actions, 'register' ] );
	}
}
