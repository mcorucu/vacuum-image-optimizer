<?php
/**
 * Plugin Name: Vacuum Image Optimizer
 * Plugin URI:  https://mcorucu.com/vacuum-image-optimizer/
 * Description: Modern image optimization toolkit for WordPress. Generate WebP and AVIF variants, automate optimization workflows, optimize media libraries in bulk, improve frontend delivery, and reduce image footprint with a streamlined optimization pipeline.
 * Version:     0.9.0
 * Author:      Mehmet Can Orucu
 * Author URI:  https://mcorucu.com
 * License:     GPL-2.0-or-later
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
define( 'VIO_VERSION', '0.9.0' );
define( 'VIO_PLUGIN_FILE', __FILE__ );
define( 'VIO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VIO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VIO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

if ( ! defined( 'VIO_QUEUE_BATCH_SIZE' ) ) {
	define( 'VIO_QUEUE_BATCH_SIZE', 10 );
}

if ( ! defined( 'VIO_MAX_RETRIES' ) ) {
	define( 'VIO_MAX_RETRIES', 3 );
}

if ( ! defined( 'VIO_BACKUP_CLEANUP_BATCH' ) ) {
	define( 'VIO_BACKUP_CLEANUP_BATCH', 500 );
}

// Load Composer autoloader when available; otherwise use a lightweight PSR-4 fallback.
$composer_autoload = VIO_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix   = 'VacuumImageOptimizer\\';
			$base_dir = VIO_PLUGIN_DIR . 'src/';

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
register_activation_hook( VIO_PLUGIN_FILE, [ Installer::class, 'activate' ] );

// Deactivation hook.
register_deactivation_hook( VIO_PLUGIN_FILE, [ Uninstaller::class, 'deactivate' ] );
