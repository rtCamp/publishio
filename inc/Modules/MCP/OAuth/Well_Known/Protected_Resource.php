<?php
/**
 * Serves the OAuth 2.0 Protected Resource Metadata (RFC 9728).
 *
 * Responds to: /.well-known/oauth-protected-resource/wp-json/mcp/mcp-adapter-default-server
 * Also:        /.well-known/oauth-protected-resource (root fallback)
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth\Well_Known
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth\Well_Known;

use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Config;

/**
 * Class - Protected_Resource
 */
class Protected_Resource implements Registrable {
	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'parse_request', [ $this, 'handle_request' ] );
	}

	/**
	 * Add rewrite rules for .well-known endpoints.
	 */
	public function add_rewrite_rules(): void {
		// Path-specific rules for each protected MCP resource.
		foreach ( Config::get_all_mcp_endpoint_paths() as $endpoint_path ) {
			add_rewrite_rule(
				'^\.well-known/oauth-protected-resource/wp-json/' . preg_quote( $endpoint_path, '/' ) . '/?$',
				'index.php?pwai_oauth_wellknown=protected-resource&pwai_oauth_resource_path=' . rawurlencode( $endpoint_path ),
				'top'
			);
		}

		// Root fallback: /.well-known/oauth-protected-resource.
		add_rewrite_rule(
			'^\.well-known/oauth-protected-resource/?$',
			'index.php?pwai_oauth_wellknown=protected-resource',
			'top'
		);
	}

	/**
	 * Register custom query variables.
	 *
	 * @param array<string> $vars Existing query vars.
	 *
	 * @return array<string>
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'pwai_oauth_wellknown';
		$vars[] = 'pwai_oauth_resource_path';
		return $vars;
	}

	/**
	 * Handle the request if our query var is set.
	 *
	 * @param \WP $wp The WordPress environment instance.
	 */
	public function handle_request( \WP $wp ): void {
		if ( empty( $wp->query_vars['pwai_oauth_wellknown'] ) ) {
			return;
		}

		if ( 'protected-resource' !== $wp->query_vars['pwai_oauth_wellknown'] ) {
			return;
		}

		$resource_path = ! empty( $wp->query_vars['pwai_oauth_resource_path'] )
			? sanitize_text_field( $wp->query_vars['pwai_oauth_resource_path'] )
			: Config::get_mcp_endpoint_path();

		// Validate that the resource path is a registered protected route.
		if ( ! in_array( $resource_path, Config::get_all_mcp_endpoint_paths(), true ) ) {
			status_header( 404 );
			exit;
		}

		$this->send_json_response( $this->get_metadata( $resource_path ) );
	}

	/**
	 * Build the Protected Resource Metadata document.
	 *
	 * @param string $endpoint_path The MCP endpoint path (e.g. "mcp/mcp-adapter-default-server").
	 *
	 * @return array<string, mixed>
	 */
	private function get_metadata( string $endpoint_path ): array {
		$resource_url = rest_url( $endpoint_path );

		return [
			'resource'                 => untrailingslashit( $resource_url ),
			'authorization_servers'    => [ Config::get_issuer_url() ],
			'scopes_supported'         => Config::SUPPORTED_SCOPES,
			'bearer_methods_supported' => [ 'header' ],
		];
	}

	/**
	 * Send a JSON response and exit.
	 *
	 * @param array<string, mixed> $data Response data.
	 */
	private function send_json_response( array $data ): void {
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		header( 'Access-Control-Allow-Origin: *' );

		echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		exit;
	}
}
