<?php
/**
 * OAuth 2.1 Token Endpoint.
 *
 * Handles two grant types:
 * - authorization_code: exchanges code + PKCE verifier for tokens
 * - refresh_token: rotates refresh token and issues new access token
 *
 * Registered at: POST /wp-json/publishio-oauth/v1/token
 *
 * @package rtCamp\Publishio\Modules\MCP\OAuth\Endpoint
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\OAuth\Endpoint;

use rtCamp\Publishio\Framework\Contracts\Abstracts\Abstract_REST_Controller;
use rtCamp\Publishio\Modules\MCP\OAuth\Client\Client_Registry;
use rtCamp\Publishio\Modules\MCP\OAuth\Config;
use rtCamp\Publishio\Modules\MCP\OAuth\Storage\Auth_Code_Store;
use rtCamp\Publishio\Modules\MCP\OAuth\Storage\Token_Store;

/**
 * Class - Token
 */
class Token extends Abstract_REST_Controller {
	/**
	 * Register the token route.
	 */
	public function register_routes(): void {
		register_rest_route(
			Config::OAUTH_REST_NAMESPACE,
			'/token',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Handle the token request.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle( \WP_REST_Request $request ) {
		$grant_type = (string) $request->get_param( 'grant_type' );

		return match ( $grant_type ) {
			'authorization_code' => $this->handle_authorization_code( $request ),
			'refresh_token'      => $this->handle_refresh_token( $request ),
			default              => $this->error_response(
				'unsupported_grant_type',
				'Only authorization_code and refresh_token grant types are supported.',
				400
			),
		};
	}

	/**
	 * Handle the authorization_code grant type.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	private function handle_authorization_code( \WP_REST_Request $request ) {
		$code          = (string) $request->get_param( 'code' );
		$client_id     = (string) $request->get_param( 'client_id' );
		$redirect_uri  = (string) $request->get_param( 'redirect_uri' );
		$code_verifier = (string) $request->get_param( 'code_verifier' );

		// Public clients (registered via DCR) authenticate via PKCE — no secret needed.
		// is_public_client() already confirms the client exists; confidential clients require client_secret.
		if ( ! Client_Registry::is_public_client( $client_id ) ) {
			$client_secret = (string) ( $request->get_param( 'client_secret' ) ?? '' );
			if ( ! Client_Registry::validate_credentials( $client_id, $client_secret ) ) {
				return $this->error_response( 'invalid_client', 'Invalid client credentials.', 401 );
			}
		}

		// Consume the auth code (one-time use).
		$code_data = Auth_Code_Store::consume( $code );

		if ( ! $code_data ) {
			return $this->error_response( 'invalid_grant', 'Authorization code is invalid or expired.', 400 );
		}

		// Verify the code was issued to this client.
		if ( $code_data['client_id'] !== $client_id ) {
			return $this->error_response( 'invalid_grant', 'Authorization code was not issued to this client.', 400 );
		}

		// Verify redirect_uri matches.
		if ( $code_data['redirect_uri'] !== $redirect_uri ) {
			return $this->error_response( 'invalid_grant', 'redirect_uri does not match the authorization request.', 400 );
		}

		// Verify PKCE code_verifier against stored code_challenge (S256).
		if ( ! $this->verify_pkce( $code_verifier, $code_data['code_challenge'] ) ) {
			return $this->error_response( 'invalid_grant', 'PKCE verification failed.', 400 );
		}

		// If the client sends a resource parameter (RFC 8707), it must match what was authorized.
		$resource = (string) ( $request->get_param( 'resource' ) ?? '' );
		if ( '' !== $resource && $resource !== $code_data['resource'] ) {
			return $this->error_response( 'invalid_target', 'resource does not match the authorization request.', 400 );
		}

		// Issue tokens.
		$tokens = Token_Store::issue(
			$code_data['user_id'],
			$client_id,
			$code_data['scope'],
			$code_data['resource']
		);

		if ( null === $tokens ) {
			return $this->error_response( 'server_error', 'Failed to issue tokens. Please try again.', 500 );
		}

		return $this->token_response( $tokens );
	}

	/**
	 * Handle the refresh_token grant type.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	private function handle_refresh_token( \WP_REST_Request $request ) {
		$refresh_token = (string) $request->get_param( 'refresh_token' );
		$client_id     = (string) $request->get_param( 'client_id' );

		if ( ! Client_Registry::is_public_client( $client_id ) ) {
			$client_secret = (string) ( $request->get_param( 'client_secret' ) ?? '' );
			if ( ! Client_Registry::validate_credentials( $client_id, $client_secret ) ) {
				return $this->error_response( 'invalid_client', 'Invalid client credentials.', 401 );
			}
		}

		$tokens = Token_Store::refresh( $refresh_token, $client_id );

		if ( false === $tokens ) {
			return $this->error_response( 'server_error', 'Failed to issue tokens. Please try again.', 500 );
		}

		if ( null === $tokens ) {
			return $this->error_response( 'invalid_grant', 'Refresh token is invalid or expired.', 400 );
		}

		return $this->token_response( $tokens );
	}

	/**
	 * Verify a PKCE code_verifier against a stored code_challenge (S256).
	 *
	 * @param string $code_verifier  The plain code verifier from the token request.
	 * @param string $code_challenge The stored code challenge from the authorize request.
	 */
	private function verify_pkce( string $code_verifier, string $code_challenge ): bool {
		if ( empty( $code_verifier ) || empty( $code_challenge ) ) {
			return false;
		}

		// S256: BASE64URL(SHA256(code_verifier)) === code_challenge.
		$computed = rtrim( strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ), '=' );

		return hash_equals( $code_challenge, $computed );
	}

	/**
	 * Build a successful token response.
	 *
	 * @param array<string, mixed> $tokens The token data.
	 */
	private function token_response( array $tokens ): \WP_REST_Response {
		$response = new \WP_REST_Response( $tokens, 200 );

		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'Pragma', 'no-cache' );

		return $response;
	}

	/**
	 * Build an OAuth error response.
	 *
	 * @param string $error_code  The OAuth error code.
	 * @param string $description Human-readable description.
	 * @param int    $status      HTTP status code.
	 */
	private function error_response( string $error_code, string $description, int $status ): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'error'             => $error_code,
				'error_description' => $description,
			],
			$status,
			[ 'Cache-Control' => 'no-store' ]
		);
	}
}
