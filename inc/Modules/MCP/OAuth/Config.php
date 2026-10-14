<?php
/**
 * Configuration constants for the OAuth module.
 *
 * @package rtCamp\Publishio\Modules\MCP\OAuth
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\OAuth;

/**
 * Class - Config
 */
class Config {
	public const MCP_ROUTE_NAMESPACE     = 'mcp';
	public const MCP_ROUTE               = 'publishio';
	public const OAUTH_REST_NAMESPACE    = 'publishio-oauth/v1';
	public const AUTHORIZE_PATH          = 'publishio-oauth/authorize';
	public const ACCESS_TOKEN_TTL        = 3600;
	public const REFRESH_TOKEN_TTL       = 2592000;
	public const MAX_CONNECTION_LIFETIME = 7776000;
	public const AUTH_CODE_TTL           = 120;

	/**
	 * Get the default MCP endpoint path.
	 */
	public static function get_mcp_endpoint_path(): string {
		return self::MCP_ROUTE_NAMESPACE . '/' . self::MCP_ROUTE;
	}

	/**
	 * Get the canonical resource claim (audience) for the MCP endpoint.
	 *
	 * This is the untrailingslashed resource URL used as the OAuth resource
	 * identifier (RFC 8707) for token audience validation and discovery.
	 */
	public static function get_mcp_resource_claim(): string {
		return untrailingslashit( rest_url( self::get_mcp_endpoint_path() ) );
	}

	/**
	 * Get the site URL with optional path.
	 *
	 * @param string $path Optional path to append.
	 */
	public static function site_url( string $path = '' ): string {
		return site_url( $path );
	}

	/**
	 * Get the MCP resource URL.
	 */
	public static function get_mcp_resource_url(): string {
		return rest_url( self::get_mcp_endpoint_path() );
	}

	/**
	 * Get the OAuth issuer URL.
	 */
	public static function get_issuer_url(): string {
		return untrailingslashit( site_url() );
	}

	/**
	 * Get the absolute URL of the user-facing authorization endpoint.
	 */
	public static function get_authorize_url(): string {
		return home_url( '/' . self::AUTHORIZE_PATH );
	}

	/**
	 * Get the access token TTL, allowing filter overrides.
	 */
	public static function get_access_token_ttl(): int {
		return (int) apply_filters( 'publishio_access_token_ttl', self::ACCESS_TOKEN_TTL );
	}

	/**
	 * Get the refresh token TTL, allowing filter overrides.
	 */
	public static function get_refresh_token_ttl(): int {
		return (int) apply_filters( 'publishio_refresh_token_ttl', self::REFRESH_TOKEN_TTL );
	}

	/**
	 * Get how long a connection lasts after the user approves it, allowing filter overrides. Refreshing doesn't extend it. 0 means connections don't end; the filter can return 0 or less for that.
	 */
	public static function get_max_connection_lifetime(): int {
		return max( 0, (int) apply_filters( 'publishio_max_connection_lifetime', self::MAX_CONNECTION_LIFETIME ) );
	}
}
