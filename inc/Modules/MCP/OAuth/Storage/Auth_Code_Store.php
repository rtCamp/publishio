<?php
/**
 * Short-lived authorization code storage using transients.
 *
 * Codes are stored keyed by their SHA-256 hash and consumed once.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage;

use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Config;

/**
 * Class - Auth_Code_Store
 */
class Auth_Code_Store {
	/**
	 * Transient key prefix for auth codes.
	 * Kept as-is for external compatibility.
	 */
	private const TRANSIENT_PREFIX = 'rtpwai_oauth_code_';

	/**
	 * Create a new authorization code and store it as a transient.
	 *
	 * @param int    $user_id       The WordPress user ID.
	 * @param string $client_id     The OAuth client ID.
	 * @param string $redirect_uri  The redirect URI from the authorize request.
	 * @param string $code_challenge The PKCE code challenge.
	 * @param string $scope         The requested scope.
	 * @param string $resource_indicator The resource indicator.
	 *
	 * @return string The plain-text authorization code.
	 */
	public static function create( int $user_id, string $client_id, string $redirect_uri, string $code_challenge, string $scope, string $resource_indicator ): string {
		$code = wp_generate_password( 64, false );

		$data = [
			'user_id'        => $user_id,
			'client_id'      => $client_id,
			'redirect_uri'   => $redirect_uri,
			'code_challenge' => $code_challenge,
			'scope'          => $scope,
			'resource'       => $resource_indicator,
			'created_at'     => time(),
		];

		set_transient( self::TRANSIENT_PREFIX . hash( 'sha256', $code ), $data, Config::AUTH_CODE_TTL );

		return $code;
	}

	/**
	 * Consume an authorization code (one-time use).
	 *
	 * Returns the stored data and deletes the transient atomically.
	 *
	 * @param string $code The plain-text authorization code.
	 *
	 * @return array<string, mixed>|null The stored data, or null if invalid/expired.
	 */
	public static function consume( string $code ): ?array {
		$key  = self::TRANSIENT_PREFIX . hash( 'sha256', $code );
		$data = get_transient( $key );

		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( ! delete_transient( $key ) ) {
			return null;
		}

		return $data;
	}
}
