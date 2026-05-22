<?php
/**
 * WP-CLI commands for the OAuth module.
 *
 * Usage:
 *   wp pwai-oauth set-client --client_id=claude --client_secret=s3cret --redirect_uri=https://claude.ai/oauth/callback
 *   wp pwai-oauth get-client
 *   wp pwai-oauth remove-client
 *
 * @package rtCamp\Publish_With_AI\Modules\OAuth\CLI
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\OAuth\CLI;

use WP_CLI;
use rtCamp\Publish_With_AI\Modules\OAuth\Client\Client_Registry;
use rtCamp\Publish_With_AI\Modules\OAuth\Config;

/**
 * Class - OAuth_Commands
 */
class OAuth_Commands {
	/**
	 * Set the OAuth client credentials.
	 *
	 * ## OPTIONS
	 *
	 * --client_id=<client_id>
	 * : The client identifier.
	 *
	 * --client_secret=<client_secret>
	 * : The client secret (stored hashed).
	 *
	 * --redirect_uri=<redirect_uri>
	 * : Allowed redirect URI.
	 *
	 * [--client_name=<client_name>]
	 * : Display name for the client.
	 * ---
	 * default: MCP Client
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *   wp pwai-oauth set-client --client_id=claude --client_secret=s3cret --redirect_uri=https://claude.ai/oauth/callback --client_name="Claude AI"
	 *
	 * @param array<int, string>    $_args      Positional arguments (unused).
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function set_client( array $_args, array $assoc_args ): void { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
		$client_id     = $assoc_args['client_id'];
		$client_secret = $assoc_args['client_secret'];
		$redirect_uri  = $assoc_args['redirect_uri'];
		$client_name   = $assoc_args['client_name'] ?? 'MCP Client';

		Client_Registry::save_client(
			$client_id,
			$client_secret,
			[ $redirect_uri ],
			$client_name
		);

		WP_CLI::success( sprintf( 'Client "%s" saved (secret hashed).', $client_id ) );
	}

	/**
	 * Show the current OAuth client (without the secret hash).
	 *
	 * ## EXAMPLES
	 *
	 *   wp pwai-oauth get-client
	 */
	public function get_client(): void {
		$client = Client_Registry::get_client();

		if ( ! $client ) {
			WP_CLI::warning( 'No client configured. Use: wp pwai-oauth set-client --client_id=... --client_secret=... --redirect_uri=...' );
			return;
		}

		WP_CLI::log( sprintf( 'Client ID:     %s', $client['client_id'] ) );
		WP_CLI::log( sprintf( 'Client Name:   %s', $client['client_name'] ) );
		WP_CLI::log( sprintf( 'Redirect URIs: %s', implode( ', ', $client['redirect_uris'] ) ) );
		WP_CLI::log( 'Secret:        [hashed]' );
	}

	/**
	 * Remove the OAuth client.
	 *
	 * ## EXAMPLES
	 *
	 *   wp pwai-oauth remove-client
	 */
	public function remove_client(): void {
		delete_option( Config::CLIENT_OPTION_KEY );
		WP_CLI::success( 'Client removed.' );
	}
}
