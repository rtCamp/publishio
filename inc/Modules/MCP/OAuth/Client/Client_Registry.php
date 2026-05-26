<?php
/**
 * Manages OAuth client lookups for clients registered via the /register endpoint.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth\Client
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth\Client;

use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage\Client_Store;

/**
 * Class - Client_Registry
 */
class Client_Registry {
	/**
	 * Validate a client_id and client_secret pair.
	 *
	 * Only meaningful for confidential clients. Returns false for public clients
	 * (which authenticate via PKCE instead).
	 *
	 * @param string $client_id     The client ID.
	 * @param string $client_secret The plain-text client secret.
	 */
	public static function validate_credentials( string $client_id, string $client_secret ): bool {
		$client = Client_Store::get_by_client_id( $client_id );
		if ( $client && ! $client['is_public'] && ! empty( $client['client_secret_hash'] ) ) {
			return wp_check_password( $client_secret, $client['client_secret_hash'] );
		}

		return false;
	}

	/**
	 * Validate that a redirect_uri is registered for the client.
	 *
	 * @param string $client_id    The client ID.
	 * @param string $redirect_uri The redirect URI to validate.
	 */
	public static function validate_redirect_uri( string $client_id, string $redirect_uri ): bool {
		$client = Client_Store::get_by_client_id( $client_id );
		if ( $client ) {
			return in_array( $redirect_uri, $client['redirect_uris'], true );
		}

		return false;
	}

	/**
	 * Check if a client_id exists.
	 *
	 * @param string $client_id The client ID.
	 */
	public static function client_exists( string $client_id ): bool {
		return Client_Store::get_by_client_id( $client_id ) !== null;
	}

	/**
	 * Return true if the client is a public client (no secret, PKCE-only).
	 *
	 * @param string $client_id The client ID.
	 */
	public static function is_public_client( string $client_id ): bool {
		$client = Client_Store::get_by_client_id( $client_id );
		return null !== $client && $client['is_public'];
	}
}
