<?php
/**
 * Publishio
 *
 * @package           rtCamp/Publishio
 * @author            rtCamp
 * @copyright         2026 rtCamp
 * @license           GPL-2.0-or-later
 *
 * Plugin Name:       Publishio
 * Plugin URI:        https://github.com/rtCamp/publishio
 * Description:       Build WordPress pages and posts using your existing patterns directly from your favorite AI assistant.
 * Version:           0.4.0
 * Author:            rtCamp
 * Author URI:        https://rtcamp.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       publishio
 * Domain Path:       /languages
 * Requires PHP:      8.2
 * Requires at least: 6.9
 * Tested up to:      7.0
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Define the plugin constants.
 */
function constants(): void {
	/**
	 * File path to the plugin's main file.
	 */
	define( 'PUBLISHIO_FILE', __FILE__ );

	/**
	 * Version of the plugin.
	 */
	define( 'PUBLISHIO_VERSION', '0.4.0' );

	/**
	 * Root path to the plugin directory.
	 */
	define( 'PUBLISHIO_PATH', plugin_dir_path( PUBLISHIO_FILE ) );

	/**
	 * Root URL to the plugin directory.
	 */
	define( 'PUBLISHIO_URL', plugin_dir_url( PUBLISHIO_FILE ) );
}

constants();

// If autoloader fails, we cannot proceed.
require_once __DIR__ . '/inc/Autoloader.php';
if ( ! class_exists( 'rtCamp\Publishio\Autoloader' ) || ! \rtCamp\Publishio\Autoloader::autoload() ) {
	return;
}

// Load the main plugin class.
if ( class_exists( 'rtCamp\Publishio\Main' ) ) {
	\rtCamp\Publishio\Main::get_instance();
}
