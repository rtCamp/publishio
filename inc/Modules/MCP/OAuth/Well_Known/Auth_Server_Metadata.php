<?php
/**
 * Serves the OAuth 2.0 Authorization Server Metadata (RFC 8414).
 *
 * Responds to: /.well-known/oauth-authorization-server
 *
 * @package rtCamp\Publishio\Modules\MCP\OAuth\Well_Known
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\OAuth\Well_Known;

use rtCamp\Publishio\Framework\Contracts\Interfaces\Registrable;
use rtCamp\Publishio\Modules\MCP\OAuth\Config;
use rtCamp\Publishio\Modules\MCP\OAuth\Scope;

/**
 * Class - Auth_Server_Metadata
 *
 * Uses WordPress rewrite rules + parse_request to serve content at
 * `/.well-known/oauth-authorization-server` — the exact URL path required by
 * RFC 8414 (OAuth 2.0 Authorization Server Metadata).
 *
 * We intentionally do NOT use `register_rest_route` here because the REST API
 * would prefix the URL with `/wp-json/{namespace}/`, breaking OAuth client
 * discovery. Clients look for the well-known URI at the site root per spec.
 *
 * The `echo; exit;` pattern in Json_Response::send_json_response() short-circuits WordPress template loading, which is the standard approach for lightweight custom endpoints that don't need the full theme/bootstrap pipeline.
 */
class Auth_Server_Metadata implements Registrable {
	use Json_Response;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'parse_request', [ $this, 'handle_request' ] );
	}

	/**
	 * Add rewrite rule for authorization server metadata.
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^\.well-known/oauth-authorization-server/?$',
			'index.php?publishio_oauth_as_metadata=1',
			'top'
		);
	}

	/**
	 * Register custom query variable.
	 *
	 * @param array<string> $vars Existing query vars.
	 *
	 * @return array<string>
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'publishio_oauth_as_metadata';
		return $vars;
	}

	/**
	 * Handle the request if our query var is set.
	 *
	 * @param \WP $wp The WordPress environment instance.
	 */
	public function handle_request( \WP $wp ): void {
		if ( empty( $wp->query_vars['publishio_oauth_as_metadata'] ) ) {
			return;
		}

		$this->send_json_response( self::get_metadata() );
	}

	/**
	 * Build the Authorization Server Metadata document.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_metadata(): array {
		return [
			'issuer'                                => Config::get_issuer_url(),
			'authorization_endpoint'                => Config::get_authorize_url(),
			'token_endpoint'                        => rest_url( Config::OAUTH_REST_NAMESPACE . '/token' ),
			'response_types_supported'              => [ 'code' ],
			'grant_types_supported'                 => [ 'authorization_code', 'refresh_token' ],
			'code_challenge_methods_supported'      => [ 'S256' ],
			'token_endpoint_auth_methods_supported' => [ 'none' ],
			'client_id_metadata_document_supported' => true,
			'authorization_response_iss_parameter_supported' => true,
			'scopes_supported'                      => Scope::SUPPORTED,
			'resource_indicators_supported'         => true,
		];
	}
}
