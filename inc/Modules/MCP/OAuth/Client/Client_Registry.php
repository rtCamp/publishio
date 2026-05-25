<?php
/**
 * Manages OAuth client lookups across the legacy single-client option and the
 * dynamic client table populated by the /register endpoint.
 *
 * The legacy client (configured via the admin UI or WP-CLI) is treated as a
 * confidential client (has a hashed secret). Clients registered via RFC 7591
 * Dynamic Client Registration are public clients (PKCE-only, no secret).
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth\Client
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth\Client;

use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Config;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage\Dynamic_Client_Store;

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
		// Check legacy confidential client first.
		$legacy = self::get_client();
		if ( $legacy && $legacy['client_id'] === $client_id ) {
			return wp_check_password( $client_secret, $legacy['client_secret_hash'] );
		}

		// Check dynamic clients (confidential ones only).
		$dyn = Dynamic_Client_Store::get_by_client_id( $client_id );
		if ( $dyn && ! $dyn['is_public'] && ! empty( $dyn['client_secret_hash'] ) ) {
			return wp_check_password( $client_secret, $dyn['client_secret_hash'] );
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
		// Check legacy client.
		$legacy = self::get_client();
		if ( $legacy && $legacy['client_id'] === $client_id ) {
			return in_array( $redirect_uri, $legacy['redirect_uris'], true );
		}

		// Check dynamic clients.
		$dyn = Dynamic_Client_Store::get_by_client_id( $client_id );
		if ( $dyn ) {
			return in_array( $redirect_uri, $dyn['redirect_uris'], true );
		}

		return false;
	}

	/**
	 * Check if a client_id exists (legacy or dynamic).
	 *
	 * @param string $client_id The client ID.
	 */
	public static function client_exists( string $client_id ): bool {
		$legacy = self::get_client();
		if ( $legacy && $legacy['client_id'] === $client_id ) {
			return true;
		}

		return Dynamic_Client_Store::get_by_client_id( $client_id ) !== null;
	}

	/**
	 * Return true if the client is a public client (no secret, PKCE-only).
	 *
	 * Legacy clients configured via the admin UI or WP-CLI are always confidential.
	 * Clients registered via the /register DCR endpoint are always public.
	 *
	 * @param string $client_id The client ID.
	 */
	public static function is_public_client( string $client_id ): bool {
		// Legacy clients are confidential by definition.
		$legacy = self::get_client();
		if ( $legacy && $legacy['client_id'] === $client_id ) {
			return false;
		}

		$dyn = Dynamic_Client_Store::get_by_client_id( $client_id );
		return null !== $dyn && $dyn['is_public'];
	}

	/**
	 * Get the stored legacy client record (from wp_options).
	 *
	 * @return array{client_id: string, client_secret_hash: string, redirect_uris: string[], client_name: string}|null
	 */
	public static function get_client(): ?array {
		$client = get_option( Config::CLIENT_OPTION_KEY );

		if ( ! is_array( $client ) || empty( $client['client_id'] ) ) {
			return null;
		}

		/** @var array{client_id: string, client_secret_hash: string, redirect_uris: array<string>, client_name: string} $client */
		return $client;
	}

	/**
	 * Save a legacy client record to wp_options.
	 *
	 * @param string   $client_id     The client ID.
	 * @param string   $client_secret Plain-text secret (will be hashed).
	 * @param string[] $redirect_uris Allowed redirect URIs.
	 * @param string   $client_name   Display name.
	 */
	public static function save_client( string $client_id, string $client_secret, array $redirect_uris, string $client_name = 'MCP Client' ): void {
		$record = [
			'client_id'          => sanitize_text_field( $client_id ),
			'client_secret_hash' => wp_hash_password( $client_secret ),
			'redirect_uris'      => array_map( 'esc_url_raw', $redirect_uris ),
			'client_name'        => sanitize_text_field( $client_name ),
		];

		update_option( Config::CLIENT_OPTION_KEY, $record, false );
	}
}
