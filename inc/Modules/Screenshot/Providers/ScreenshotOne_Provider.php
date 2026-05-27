<?php
/**
 * Screenshot provider — ScreenshotOne.
 *
 * API docs: https://screenshotone.com/docs/
 *
 * @package rtCamp\Publish_With_AI\Modules\Screenshot\Providers
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Screenshot\Providers;

/**
 * Class - ScreenshotOne_Provider
 */
class ScreenshotOne_Provider {
	/**
	 * ScreenshotOne API endpoint.
	 */
	private const API_URL = 'https://api.screenshotone.com/take';

	/**
	 * ScreenshotOne access key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_key ScreenshotOne access key.
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
				'access_key'      => $this->api_key,
				'url'             => $url,
				'format'          => 'png',
				'selector'        => $selector,
				'hide_selectors'  => '#wpadminbar,header,footer,nav',
				'viewport_width'  => '1440',
				'viewport_height' => '900',
				'image_quality'   => '90',
			]
		);

		$response = wp_remote_get( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
			self::API_URL . '?' . $query,
			[ 'timeout' => 60 ] // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code         = wp_remote_retrieve_response_code( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		if ( 200 !== $code || false === strpos( (string) $content_type, 'image/' ) ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			$message = is_array( $data ) && ! empty( $data['error_message'] )
				? $data['error_message']
				: sprintf(
					/* translators: %d: HTTP status code */
					__( 'ScreenshotOne API returned HTTP %d.', 'rtcamp-publish-with-ai' ),
					$code
				);

			return new \WP_Error( 'screenshotone_failed', $message );
		}

		return wp_remote_retrieve_body( $response );
	}
}
