<?php
/**
 * Hooks into the MCP Adapter transport to authenticate via Bearer tokens.
 *
 * This bridges the OAuth module with the mcp-adapter plugin by:
 * 1. Intercepting REST authentication for MCP routes.
 * 2. Validating Bearer tokens and setting the current WP user
 * 3. Adding WWW-Authenticate headers on 401 responses
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth\Transport
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth\Transport;

use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Config;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage\Token_Store;

/**
 * Class - Bearer_Token_Auth
 */
class Bearer_Token_Auth {
	/**
	 * Register authentication hooks.
	 */
	public function register(): void {
		// Authenticate Bearer tokens on REST API requests to MCP routes.
		add_filter( 'rest_authentication_errors', [ $this, 'authenticate_bearer_token' ], 90 );

		// Add WWW-Authenticate header on 401 responses from MCP routes.
		add_filter( 'rest_post_dispatch', [ $this, 'add_www_authenticate_header' ], 10, 3 );
	}

	/**
	 * Authenticate Bearer tokens for MCP endpoint requests.
	 *
	 * This fires early in the REST lifecycle, before permission callbacks.
	 * If a Bearer token is present and valid, we set the current user.
	 * If no Bearer token is present, we pass through (let WP handle cookies/app-passwords).
	 * If a Bearer token is present but invalid, we return an error.
	 *
	 * @param \WP_Error|true|null $result Existing auth result.
	 *
	 * @return \WP_Error|true|null
	 */
	public function authenticate_bearer_token( $result ) {
		// If another auth handler already determined the result, don't override.
		if ( null !== $result ) {
			return $result;
		}

		// Only act on MCP endpoint requests.
		$matched_path = $this->get_matched_mcp_path();

		if ( ! $matched_path ) {
			return $result;
		}

		$token = $this->extract_bearer_token();

		if ( ! $token ) {
			// No Bearer token — fall through to WP's default auth.
			return $result;
		}

		// We have a Bearer token — validate it.
		$token_data = Token_Store::validate_access_token( $token );

		if ( ! $token_data ) {
			return new \WP_Error(
				'rest_oauth_invalid_token',
				__( 'Invalid or expired access token.', 'rtcamp-publish-with-ai' ),
				[ 'status' => 401 ]
			);
		}

		// Validate the resource claim matches this MCP endpoint.
		$expected_resource = untrailingslashit( rest_url( $matched_path ) );
		$token_resource    = isset( $token_data['resource'] ) ? untrailingslashit( $token_data['resource'] ) : '';

		// Resource claim is required — reject tokens without one to prevent cross-resource usage.
		if ( empty( $token_resource ) || $token_resource !== $expected_resource ) {
			return new \WP_Error(
				'rest_oauth_invalid_audience',
				__( 'Token was not issued for this resource.', 'rtcamp-publish-with-ai' ),
				[ 'status' => 401 ]
			);
		}

		// Set the authenticated user.
		wp_set_current_user( $token_data['user_id'] );

		return true;
	}

	/**
	 * Add WWW-Authenticate header on 401 responses from MCP routes.
	 *
	 * @param \WP_REST_Response $response The response.
	 * @param \WP_REST_Server   $_server  The REST server (unused).
	 * @param \WP_REST_Request  $request  The request.
	 */
	public function add_www_authenticate_header( \WP_REST_Response $response, \WP_REST_Server $_server, \WP_REST_Request $request ): \WP_REST_Response { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
		if ( 401 !== $response->get_status() ) {
			return $response;
		}

		// Only add the header for protected MCP endpoint requests.
		$route        = $request->get_route();
		$matched_path = null;

		foreach ( Config::get_all_mcp_endpoint_paths() as $path ) {
			if ( '/' . $path === $route ) {
				$matched_path = $path;
				break;
			}
		}

		if ( ! $matched_path ) {
			return $response;
		}

		$resource_metadata_url = site_url( '/.well-known/oauth-protected-resource/wp-json/' . $matched_path );

		$www_auth = sprintf(
			'Bearer resource_metadata="%s", scope="mcp:read mcp:write"',
			$resource_metadata_url
		);

		$response->header( 'WWW-Authenticate', $www_auth );

		return $response;
	}

	/**
	 * Get the matched MCP endpoint path for the current request, or null if not an MCP request.
	 *
	 * Uses exact path matching to prevent partial prefix collisions.
	 *
	 * @return string|null The matched endpoint path, or null.
	 */
	private function get_matched_mcp_path(): ?string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		$request_path = untrailingslashit( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) );

		foreach ( Config::get_all_mcp_endpoint_paths() as $endpoint_path ) {
			$expected_path = untrailingslashit( (string) wp_parse_url( rest_url( $endpoint_path ), PHP_URL_PATH ) );

			if ( $request_path === $expected_path ) {
				return $endpoint_path;
			}
		}

		return null;
	}

	/**
	 * Extract a Bearer token from the Authorization header.
	 *
	 * @return string|null The token, or null if not present.
	 */
	private function extract_bearer_token(): ?string {
		$header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) : '';

		if ( empty( $header ) ) {
			// Some servers don't pass Authorization to PHP. Try the redirect version.
			$header = isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) : '';
		}

		if ( empty( $header ) ) {
			return null;
		}

		if ( ! preg_match( '/^Bearer\s+(\S+)$/i', $header, $matches ) ) {
			return null;
		}

		return $matches[1];
	}
}
