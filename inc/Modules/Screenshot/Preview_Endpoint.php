<?php
/**
 * Token-gated preview endpoint.
 *
 * Validates a single-use preview token and proxies the fully-rendered
 * WordPress page HTML back to the caller (typically a screenshot API).
 *
 * Route: GET /wp-json/rtpwai/v1/preview?token={token}
 *
 * The endpoint is intentionally public (no WordPress authentication required)
 * because the token itself provides the security: it is a random UUID v4,
 * single-use, and expires after 10 minutes.
 *
 * @package rtCamp\Publish_With_AI\Modules\Screenshot
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Screenshot;

use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;

/**
 * Class - Preview_Endpoint
 */
class Preview_Endpoint implements Registrable {
	/**
	 * Transient key prefix for pre-rendered preview HTML.
	 */
	public const HTML_PREFIX = 'rtpwai_preview_html_';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	/**
	 * Register the preview REST route.
	 */
	public function register_route(): void {
		register_rest_route(
			'rtpwai/v1',
			'/preview',
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
	 * Validate the token and serve the rendered page HTML.
	 *
	 * Exits directly rather than returning a WP_REST_Response so that raw HTML
	 * is sent without JSON wrapping.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 */
	public function serve( \WP_REST_Request $request ): never {
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );

		if ( '' === $token ) {
			status_header( 400 );
			exit;
		}

		$data = Token_Store::consume( $token );

		if ( null === $data ) {
			status_header( 403 );
			exit;
		}

		// Use pre-rendered HTML cached by the MCP tool before Microlink was called.
		// Falls back to a live loopback request if the cache is missing.
		$cached = get_transient( self::HTML_PREFIX . $token );
		delete_transient( self::HTML_PREFIX . $token );

		$html = false !== $cached ? $cached : self::fetch_page_html( $data['post_id'], $data['user_id'] );

		if ( is_wp_error( $html ) ) {
			status_header( 500 );
			exit;
		}

		header( 'Content-Type: text/html; charset=utf-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
		exit;
	}

	/**
	 * Fetch the rendered WordPress preview HTML via an authenticated loopback request.
	 *
	 * Switches to the token owner's user so that the preview nonce is valid, then
	 * makes an internal HTTP request with short-lived auth cookies.
	 *
	 * @param int $post_id Post to preview.
	 * @param int $user_id User whose session renders the preview.
	 *
	 * @return string|\WP_Error Rendered HTML or error.
	 */
	public static function fetch_page_html( int $post_id, int $user_id ): string|\WP_Error {
		// Switch user so get_preview_post_link() generates a nonce for the right user.
		wp_set_current_user( $user_id );

		$preview_url = get_preview_post_link( $post_id );

		if ( ! $preview_url ) {
			return new \WP_Error( 'no_preview_url', __( 'Could not generate preview URL.', 'rtcamp-publish-with-ai' ) );
		}

		$expiry  = time() + MINUTE_IN_SECONDS;
		$cookies = [
			new \WP_Http_Cookie(
				[
					'name'  => AUTH_COOKIE, // @phpstan-ignore constant.notFound
					'value' => wp_generate_auth_cookie( $user_id, $expiry, 'auth' ),
				]
			),
			new \WP_Http_Cookie(
				[
					'name'  => SECURE_AUTH_COOKIE, // @phpstan-ignore constant.notFound
					'value' => wp_generate_auth_cookie( $user_id, $expiry, 'secure_auth' ),
				]
			),
			new \WP_Http_Cookie(
				[
					'name'  => LOGGED_IN_COOKIE, // @phpstan-ignore constant.notFound
					'value' => wp_generate_auth_cookie( $user_id, $expiry, 'logged_in' ),
				]
			),
		];

		$response = wp_remote_get( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
			$preview_url,
			[
				'cookies'     => $cookies,
				'timeout'     => 30, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
				// SSL verification is disabled for the server-to-itself loopback request.
				'sslverify'   => false,
				'redirection' => 5,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new \WP_Error(
				'preview_failed',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Preview request returned HTTP %d.', 'rtcamp-publish-with-ai' ),
					$code
				)
			);
		}

		return wp_remote_retrieve_body( $response );
	}
}
