<?php
/**
 * OAuth 2.0 Dynamic Client Registration Endpoint (RFC 7591).
 *
 * Allows MCP clients (e.g. Claude.ai) to register themselves automatically
 * so users only need to provide the MCP Server URL — no manual client_id or
 * client_secret entry is required.
 *
 * Only public clients (token_endpoint_auth_method=none) are accepted via this
 * endpoint; PKCE (S256) serves as the proof of possession instead.
 *
 * Registered at: POST /wp-json/rtpwai-oauth/v1/register
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth\Endpoint
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth\Endpoint;

use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_REST_Controller;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Config;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage\Dynamic_Client_Store;

/**
 * Class - Register
 */
class Register extends Abstract_REST_Controller {
	/**
	 * Register the /register route.
	 */
	public function register_routes(): void {
		register_rest_route(
			Config::OAUTH_REST_NAMESPACE,
			'/register',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Handle the registration request.
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return $this->registration_error( 'invalid_client_metadata', 'Request body must be JSON.', 400 );
		}

		// redirect_uris is required.
		$redirect_uris = $body['redirect_uris'] ?? [];
		if ( ! is_array( $redirect_uris ) || empty( $redirect_uris ) ) {
			return $this->registration_error( 'invalid_redirect_uri', 'redirect_uris is required and must be a non-empty array.', 400 );
		}

		// Each redirect URI must be from an allowed origin.
		foreach ( $redirect_uris as $uri ) {
			if ( ! is_string( $uri ) ) {
				return $this->registration_error( 'invalid_redirect_uri', 'Each redirect_uri must be a string.', 400 );
			}

			$host = (string) wp_parse_url( $uri, PHP_URL_HOST );
			if ( ! in_array( $host, Config::ALLOWED_CLIENT_ORIGINS, true ) ) {
				return $this->registration_error(
					'invalid_redirect_uri',
					sprintf( 'redirect_uri "%s" is not permitted on this server.', $uri ),
					400
				);
			}
		}

		// Accept public clients (none) and confidential clients (client_secret_post).
		$auth_method = isset( $body['token_endpoint_auth_method'] ) ? (string) $body['token_endpoint_auth_method'] : 'none';
		if ( ! in_array( $auth_method, [ 'none', 'client_secret_post' ], true ) ) {
			return $this->registration_error(
				'invalid_client_metadata',
				'Supported token_endpoint_auth_methods: none, client_secret_post.',
				400
			);
		}

		$is_public = 'none' === $auth_method;

		// Validate grant_types — must include authorization_code.
		$grant_types = isset( $body['grant_types'] ) && is_array( $body['grant_types'] )
			? $body['grant_types']
			: [ 'authorization_code' ];

		if ( ! in_array( 'authorization_code', $grant_types, true ) ) {
			return $this->registration_error( 'invalid_client_metadata', 'authorization_code grant type is required.', 400 );
		}

		$unsupported_grants = array_diff( $grant_types, [ 'authorization_code', 'refresh_token' ] );
		if ( ! empty( $unsupported_grants ) ) {
			return $this->registration_error(
				'invalid_client_metadata',
				'Unsupported grant_types: ' . implode( ', ', $unsupported_grants ),
				400
			);
		}

		// Validate response_types — must include code.
		$response_types = isset( $body['response_types'] ) && is_array( $body['response_types'] )
			? $body['response_types']
			: [ 'code' ];

		if ( ! in_array( 'code', $response_types, true ) ) {
			return $this->registration_error( 'invalid_client_metadata', 'response_types must include code.', 400 );
		}

		$client_name = isset( $body['client_name'] ) ? sanitize_text_field( (string) $body['client_name'] ) : '';

		// Validate and normalise scope.
		$requested_scope = isset( $body['scope'] ) ? sanitize_text_field( (string) $body['scope'] ) : '';
		$granted_scope   = $this->resolve_scope( $requested_scope );

		$sanitized_uris = array_values( $redirect_uris );

		// Generate a secret for confidential clients; public clients get none.
		$client_secret      = null;
		$client_secret_hash = null;
		if ( ! $is_public ) {
			$client_secret      = wp_generate_password( 48, false );
			$client_secret_hash = wp_hash_password( $client_secret );
		}

		$client_id = Dynamic_Client_Store::register(
			[
				'is_public'          => $is_public,
				'client_secret_hash' => $client_secret_hash,
				'redirect_uris'      => $sanitized_uris,
				'client_name'        => $client_name,
				'grant_types'        => $grant_types,
				'response_types'     => $response_types,
				'scope'              => $granted_scope,
			]
		);

		if ( ! $client_id ) {
			return $this->registration_error( 'server_error', 'Failed to register client. Please try again.', 500 );
		}

		$response_body = [
			'client_id'                  => $client_id,
			'client_id_issued_at'        => time(),
			'redirect_uris'              => $sanitized_uris,
			'client_name'                => $client_name,
			'token_endpoint_auth_method' => $auth_method,
			'grant_types'                => $grant_types,
			'response_types'             => $response_types,
			'scope'                      => $granted_scope,
		];

		// Return the plaintext secret once — it cannot be retrieved again.
		if ( null !== $client_secret ) {
			$response_body['client_secret']            = $client_secret;
			$response_body['client_secret_expires_at'] = 0; // Never expires.
		}

		$response = new \WP_REST_Response( $response_body, 201 );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * Resolve the requested scope against the server-supported scopes.
	 *
	 * If the client requests no scope (or only unsupported scopes), fall back
	 * to the full set of supported scopes.
	 *
	 * @param string $requested Space-separated scope string.
	 */
	private function resolve_scope( string $requested ): string {
		if ( empty( $requested ) ) {
			return implode( ' ', Config::SUPPORTED_SCOPES );
		}

		$requested_items = array_filter( explode( ' ', $requested ) );
		$valid           = array_intersect( $requested_items, Config::SUPPORTED_SCOPES );

		return ! empty( $valid )
			? implode( ' ', array_values( $valid ) )
			: implode( ' ', Config::SUPPORTED_SCOPES );
	}

	/**
	 * Build a registration error response (RFC 7591 §3.2.2).
	 *
	 * @param string $error       The error code.
	 * @param string $description Human-readable description.
	 * @param int    $status      HTTP status code.
	 */
	private function registration_error( string $error, string $description, int $status ): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'error'             => $error,
				'error_description' => $description,
			],
			$status,
			[ 'Cache-Control' => 'no-store' ]
		);
	}
}
