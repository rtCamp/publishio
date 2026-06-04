<?php
/**
 * Autoloader for PHP classes inside a WordPress plugin.
 *
 * Wraps the Composer autoloader to provide graceful failure if it is missing.
 *
 * @package rtCamp\Publish_With_AI;
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'rtCamp\Publish_With_AI\Framework\AutoloaderTrait' ) ) {
	require_once PUBLISH_WITH_AI_PATH . 'framework/AutoloaderTrait.php';
}

/**
 * Class - Autoloader
 */
final class Autoloader {
	use Framework\AutoloaderTrait;

	/**
	 * Attempts to autoload the Composer dependencies.
	 *
	 * If the autoloader is missing, it will display an admin notice and log an error.
	 */
	public static function autoload(): bool {
		// If we're not *supposed* to autoload anything, then return true.
		if ( defined( 'PUBLISH_WITH_AI_AUTOLOAD' ) && false === PUBLISH_WITH_AI_AUTOLOAD ) {
			return true;
		}

		$autoloader = PUBLISH_WITH_AI_PATH . 'vendor/autoload.php';

		return self::require_autoloader( $autoloader );
	}

	/**
	 * The error message to display when the autoloader is missing.
	 */
	private static function get_autoloader_error_message(): string {
		return sprintf(
			/* translators: %s: The plugin name. */
			__( '%s: The Composer autoloader was not found. If you installed the plugin from the GitHub source code, make sure to run `composer install`.', 'publish-with-ai' ),
			esc_html( 'Publish With AI' )
		);
	}
}
