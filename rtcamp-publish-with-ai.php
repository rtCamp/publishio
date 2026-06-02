<?php
/**
 * Publish With AI
 *
 * @package           rtCamp/Publish_With_AI
 * @author            rtCamp
 * @copyright         2026 rtCamp
 * @license           GPL-2.0-or-later
 *
 * Plugin Name:       Publish With AI
 * Plugin URI:        https://github.com/rtCamp/publishwithai
 * Description:       Build WordPress pages and posts using your existing patterns directly from your favorite AI assistant.
 * Version:           0.2.0
 * Author:            rtCamp
 * Author URI:        https://rtcamp.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rtcamp-publish-with-ai
 * Domain Path:       /languages
 * Requires PHP:      8.2
 * Requires at least: 6.9
 * Tested up to:      7.0
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Define the plugin constants.
 */
function constants(): void {
	/**
	 * File path to the plugin's main file.
	 */
	define( 'RTCAMP_PUBLISH_WITH_AI_FILE', __FILE__ );

	/**
	 * Version of the plugin.
	 */
	define( 'RTCAMP_PUBLISH_WITH_AI_VERSION', '0.2.0' );

	/**
	 * Root path to the plugin directory.
	 */
	define( 'RTCAMP_PUBLISH_WITH_AI_PATH', plugin_dir_path( RTCAMP_PUBLISH_WITH_AI_FILE ) );

	/**
	 * Root URL to the plugin directory.
	 */
	define( 'RTCAMP_PUBLISH_WITH_AI_URL', plugin_dir_url( RTCAMP_PUBLISH_WITH_AI_FILE ) );
}

constants();

// If autoloader fails, we cannot proceed.
require_once __DIR__ . '/inc/Autoloader.php';
if ( ! class_exists( 'rtCamp\Publish_With_AI\Autoloader' ) || ! \rtCamp\Publish_With_AI\Autoloader::autoload() ) {
	return;
}

// Load the main plugin class.
if ( class_exists( 'rtCamp\Publish_With_AI\Main' ) ) {
	\rtCamp\Publish_With_AI\Main::get_instance();
}
