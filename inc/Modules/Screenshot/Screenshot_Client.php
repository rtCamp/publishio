<?php
/**
 * Screenshot client — dispatches to the configured provider.
 *
 * @package rtCamp\Publish_With_AI\Modules\Screenshot
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Screenshot;

use rtCamp\Publish_With_AI\Modules\Screenshot\Providers\Microlink_Provider;
use rtCamp\Publish_With_AI\Modules\Screenshot\Providers\ScreenshotOne_Provider;

/**
 * Class - Screenshot_Client
 */
final class Screenshot_Client {
	/**
	 * Capture a screenshot of the given URL using the active provider.
	 *
	 * Returns raw binary PNG data on success. The caller is responsible for
	 * confirming that Settings::is_configured() before calling this method.
	 *
	 * @param string $url      Fully-qualified URL to screenshot. Must be publicly reachable.
	 * @param string $selector CSS selector to crop to. Defaults to 'main'.
	 *
	 * @return string|\WP_Error Raw binary PNG, or WP_Error on failure.
	 */
	public static function capture( string $url, string $selector = 'main' ): string|\WP_Error {
		$provider_id = Settings::get_provider();
		$api_key     = Settings::get_api_key();

		switch ( $provider_id ) {
			case 'microlink':
				return ( new Microlink_Provider( $api_key ) )->capture( $url, $selector );

			case 'screenshotone':
				return ( new ScreenshotOne_Provider( $api_key ) )->capture( $url, $selector );

			default:
				/**
				 * Fires when a custom screenshot provider ID is encountered.
				 *
				 * Third-party providers registered via publish_with_ai_screenshot_providers
				 * can hook here to handle their own provider IDs.
				 *
				 * @param string $url         URL to screenshot.
				 * @param string $selector    CSS selector.
				 * @param string $api_key     Configured API key.
				 * @param string $provider_id Active provider ID.
				 */
				$result = apply_filters( 'publish_with_ai_screenshot_capture', null, $url, $selector, $api_key, $provider_id );

				if ( is_string( $result ) && '' !== $result ) {
					return $result;
				}

				return new \WP_Error(
					'unknown_provider',
					sprintf(
						/* translators: %s: provider ID */
						__( 'Unknown screenshot provider: %s', 'rtcamp-publish-with-ai' ),
						$provider_id
					)
				);
		}
	}
}
