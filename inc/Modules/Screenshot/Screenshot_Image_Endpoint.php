<?php
/**
 * Ephemeral screenshot image endpoint.
 *
 * Serves a short-lived screenshot PNG captured by the MCP screenshot tool.
 * The image binary is stored in a transient; this endpoint reads and returns it
 * so that external clients (e.g. Claude) can display it via a public URL.
 *
 * Route: GET /wp-json/rtpwai/v1/screenshot-image?token={token}
 *
 * @package rtCamp\Publish_With_AI\Modules\Screenshot
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Screenshot;

use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;

/**
 * Class - Screenshot_Image_Endpoint
 */
class Screenshot_Image_Endpoint implements Registrable {
	/**
	 * Transient key prefix for screenshot image binaries.
	 */
	public const TRANSIENT_PREFIX = 'rtpwai_screenshot_img_';

	/**
	 * How long to keep the image available (1 hour).
	 */
	public const TTL = 3600;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	/**
	 * Register the screenshot image REST route.
	 */
	public function register_route(): void {
		register_rest_route(
			'rtpwai/v1',
			'/screenshot-image',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'serve' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'token' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Serve the screenshot image binary from the transient store.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 */
	public function serve( \WP_REST_Request $request ): never {
		$token = sanitize_key( (string) $request->get_param( 'token' ) );

		if ( '' === $token ) {
			status_header( 400 );
			exit;
		}

		$image = get_transient( self::TRANSIENT_PREFIX . $token );

		if ( false === $image || ! is_string( $image ) ) {
			status_header( 404 );
			exit;
		}

		header( 'Content-Type: image/png' );
		header( 'Cache-Control: private, max-age=' . self::TTL );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo base64_decode( $image );
		exit;
	}
}
