<?php
/**
 * Manages the single OAuth client stored in wp_options.
 *
 * Client is configured via WP-CLI:
 *   wp pwai-oauth set-client --client_id=xxx --client_secret=xxx --redirect_uri=https://...
 *
 * @package rtCamp\Publish_With_AI\Modules\OAuth\Client
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\OAuth\Client;

use rtCamp\Publish_With_AI\Modules\OAuth\Config;

/**
 * Class - Client_Registry
 */
class Client_Registry {
	/**
	 * Validate a client_id and client_secret pair.
	 *
	 * @param string $client_id     The client ID.
	 * @param string $client_secret The plain-text client secret.
	 */
	public static function validate_credentials( string $client_id, string $client_secret ): bool {
		$client = self::get_client();

		if ( ! $client || $client['client_id'] !== $client_id ) {
			return false;
		}

		return wp_check_password( $client_secret, $client['client_secret_hash'] );
	}

	/**
	 * Validate that a redirect_uri is registered for the client.
	 *
	 * @param string $client_id    The client ID.
	 * @param string $redirect_uri The redirect URI to validate.
	 */
	public static function validate_redirect_uri( string $client_id, string $redirect_uri ): bool {
		$client = self::get_client();

		if ( ! $client || $client['client_id'] !== $client_id ) {
			return false;
		}

		return in_array( $redirect_uri, $client['redirect_uris'], true );
	}

	/**
	 * Check if a client_id exists.
	 *
	 * @param string $client_id The client ID.
	 */
	public static function client_exists( string $client_id ): bool {
		$client = self::get_client();

		return $client && $client['client_id'] === $client_id;
	}

	/**
	 * Get the stored client record.
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
	 * Save a client record to the database.
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
