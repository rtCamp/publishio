<?php
/**
 * Screenshot provider — Microlink.
 *
 * API docs: https://microlink.io/docs/api/parameters/screenshot
 *
 * @package rtCamp\Publish_With_AI\Modules\Screenshot\Providers
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Screenshot\Providers;

/**
 * Class - Microlink_Provider
 */
class Microlink_Provider {
	/**
	 * Microlink API base URL.
	 */
	private const API_URL = 'https://api.microlink.io';

	/**
	 * Optional Microlink API key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_key Optional Microlink API key (unlocks higher rate limits).
	 */
	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Capture a screenshot of the given URL and return raw PNG bytes.
	 *
	 * @param string $url      URL to screenshot.
	 * @param string $selector CSS selector to crop to (e.g. 'main').
	 *
	 * @return string|\WP_Error Raw binary PNG on success, WP_Error on failure.
	 */
	public function capture( string $url, string $selector ): string|\WP_Error {
		$query = http_build_query(
			[
				'url'                        => $url,
				'screenshot'                 => 'true',
				'element'                    => $selector,
				'viewport.width'             => '1440',
				'viewport.height'            => '900',
				'viewport.deviceScaleFactor' => '1',
			]
		);

		$headers = [ 'Accept' => 'application/json' ];

		if ( '' !== $this->api_key ) {
			$headers['x-api-key'] = $this->api_key;
		}

		$response = wp_remote_get( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
			self::API_URL . '?' . $query,
			[
				'headers' => $headers,
				'timeout' => 60, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['data']['screenshot']['url'] ) ) {
			$message = $body['message'] ?? sprintf(
				/* translators: %d: HTTP status code */
				__( 'Microlink API returned HTTP %d.', 'rtcamp-publish-with-ai' ),
				$code
			);
			return new \WP_Error( 'microlink_failed', $message );
		}

		$image_response = wp_remote_get( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
			$body['data']['screenshot']['url'],
			[ 'timeout' => 30 ] // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
		);

		if ( is_wp_error( $image_response ) ) {
			return $image_response;
		}

		return wp_remote_retrieve_body( $image_response );
	}
}
