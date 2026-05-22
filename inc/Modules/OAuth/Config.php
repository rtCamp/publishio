<?php
/**
 * Configuration constants for the OAuth module.
 *
 * @package rtCamp\Publish_With_AI\Modules\OAuth
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\OAuth;

/**
 * Class - Config
 */
class Config {
	public const MCP_ROUTE_NAMESPACE  = 'mcp';
	public const MCP_ROUTE            = 'mcp-adapter-default-server';
	public const OAUTH_REST_NAMESPACE = 'rt-mcp-oauth/v1';
	public const ACCESS_TOKEN_TTL     = 3600;
	public const REFRESH_TOKEN_TTL    = 2592000;
	public const AUTH_CODE_TTL        = 120;
	public const CLIENT_OPTION_KEY    = 'pwai_oauth_client';

	/**
	 * Get the default MCP endpoint path.
	 */
	public static function get_mcp_endpoint_path(): string {
		return self::MCP_ROUTE_NAMESPACE . '/' . self::MCP_ROUTE;
	}

	/**
	 * Get all registered MCP endpoint paths.
	 *
	 * @return string[]
	 */
	public static function get_all_mcp_endpoint_paths(): array {
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			return [ self::get_mcp_endpoint_path() ];
		}

		$adapter = \WP\MCP\Core\McpAdapter::instance();
		$paths   = [];

		foreach ( $adapter->get_servers() as $server ) {
			$paths[] = $server->get_server_route_namespace() . '/' . $server->get_server_route();
		}

		return ! empty( $paths ) ? $paths : [ self::get_mcp_endpoint_path() ];
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
		return (int) apply_filters( 'publish_with_ai_access_token_ttl', self::ACCESS_TOKEN_TTL );
	}

	/**
	 * Get the refresh token TTL, allowing filter overrides.
	 */
	public static function get_refresh_token_ttl(): int {
		return (int) apply_filters( 'publish_with_ai_refresh_token_ttl', self::REFRESH_TOKEN_TTL );
	}
}
