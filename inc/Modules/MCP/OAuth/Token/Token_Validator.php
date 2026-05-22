<?php
/**
 * Validates Bearer tokens from incoming REST API requests.
 *
 * Extracts the token from the Authorization header and resolves
 * it to a WordPress user via the Token_Store.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth\Token
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth\Token;

/**
 * Class - Token_Validator
 */
class Token_Validator {
	/**
	 * Extract and validate a Bearer token from the request.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return array{user_id: int, client_id: string, scope: string, resource: string}|null
	 */
	public static function validate_request( \WP_REST_Request $request ): ?array {
		$token = self::extract_bearer_token( $request );

		if ( ! $token ) {
			return null;
		}

		return Token_Store::validate_access_token( $token );
	}

	/**
	 * Extract the Bearer token from the Authorization header.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return string|null The token, or null if not present.
	 */
	private static function extract_bearer_token( \WP_REST_Request $request ): ?string {
		$header = $request->get_header( 'Authorization' );

		if ( ! $header ) {
			return null;
		}

		if ( ! preg_match( '/^Bearer\s+(\S+)$/i', $header, $matches ) ) {
			return null;
		}

		return $matches[1];
	}
}
