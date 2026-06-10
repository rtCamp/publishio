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
	public const MCP_ROUTE_NAMESPACE  = 'mcp';
	public const MCP_ROUTE            = 'publishio';
	public const OAUTH_REST_NAMESPACE = 'publishio-oauth/v1';
	public const ACCESS_TOKEN_TTL     = 3600;
	public const REFRESH_TOKEN_TTL    = 2592000;
	public const AUTH_CODE_TTL        = 120;
	public const SUPPORTED_SCOPES     = [ 'mcp:read', 'mcp:write' ];

	/**
	 * Local/loopback hostnames that are blocked in production.
	 */
	private const LOCAL_HOSTS = [ 'localhost', '127.0.0.1', '::1' ];

	/**
	 * Validate a redirect URI for use as an OAuth redirect target.
	 *
	 * Production rules: must use https and must not point at a local/loopback host.
	 * WP_DEBUG mode: local hosts and http are permitted (for local dev tools).
	 *
	 * @param string $uri The redirect URI to validate.
	 */
	public static function is_redirect_uri_allowed( string $uri ): bool {
		$scheme = (string) wp_parse_url( $uri, PHP_URL_SCHEME );
		$host   = strtolower( (string) wp_parse_url( $uri, PHP_URL_HOST ) );

		if ( empty( $host ) ) {
			return false;
		}

		$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$is_local = in_array( $host, self::LOCAL_HOSTS, true )
			|| str_ends_with( $host, '.local' )
			|| str_ends_with( $host, '.localhost' );

		if ( $is_local ) {
			return $is_debug;
		}

		return 'https' === $scheme;
	}

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
}
