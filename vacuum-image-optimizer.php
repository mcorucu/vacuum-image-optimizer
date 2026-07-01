<?php
/**
 * Plugin Name: Vacuum Image Optimizer
 * Plugin URI:  https://mcorucu.com/en/projects/vacuum-image-optimizer
 * Description: Modern image optimization toolkit for WordPress. Generate WebP and AVIF variants, automate optimization workflows, optimize media libraries in bulk, improve frontend delivery, and reduce image footprint with a streamlined optimization pipeline.
 * Version:     1.0.1
 * Author:      Mehmet Can Orucu
 * Author URI:  https://mcorucu.com
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vacuum-image-optimizer
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.1
 *
 * @package VacuumImageOptimizer
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'VACIMG_VERSION', '1.0.1' );
define( 'VACIMG_PLUGIN_FILE', __FILE__ );
define( 'VACIMG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VACIMG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VACIMG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

if ( ! defined( 'VACIMG_QUEUE_BATCH_SIZE' ) ) {
	define( 'VACIMG_QUEUE_BATCH_SIZE', 10 );
}

if ( ! defined( 'VACIMG_MAX_RETRIES' ) ) {
	define( 'VACIMG_MAX_RETRIES', 3 );
}

if ( ! defined( 'VACIMG_BACKUP_CLEANUP_BATCH' ) ) {
	define( 'VACIMG_BACKUP_CLEANUP_BATCH', 500 );
}

// Load Composer autoloader when available; otherwise use a lightweight PSR-4 fallback.
$vacimg_composer_autoload = VACIMG_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $vacimg_composer_autoload ) ) {
	require_once $vacimg_composer_autoload;
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix   = 'VacuumImageOptimizer\\';
			$base_dir = VACIMG_PLUGIN_DIR . 'src/';

			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}

			$relative_class = substr( $class, strlen( $prefix ) );
			$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
}

use VacuumImageOptimizer\Plugin;
use VacuumImageOptimizer\Core\Installer;
use VacuumImageOptimizer\Core\Uninstaller;

// Plugin bootstrap.
add_action( 'plugins_loaded', [ Plugin::class, 'instance' ] );

// Activation hook.
register_activation_hook( VACIMG_PLUGIN_FILE, [ Installer::class, 'activate' ] );

// Deactivation hook.
register_deactivation_hook( VACIMG_PLUGIN_FILE, [ Uninstaller::class, 'deactivate' ] );
