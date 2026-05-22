<?php
/**
 * WP-CLI command to show the current OAuth client.
 *
 * Usage: wp rtcamp-publish-with-ai oauth get-client
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth\CLI
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth\CLI;

use WP_CLI;
use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\CLI_Command;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Client\Client_Registry;

/**
 * Class - Get_Client
 */
final class Get_Client implements CLI_Command {
	/**
	 * {@inheritDoc}
	 */
	public static function get_name(): string {
		return 'oauth get-client';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_description(): string {
		return 'Show the current OAuth client (without the secret hash).';
	}

	/**
	 * Show the current OAuth client (without the secret hash).
	 *
	 * ## EXAMPLES
	 *
	 *   wp rtcamp-publish-with-ai oauth get-client
	 *
	 * @param array<int, mixed>    $args       Positional arguments (unused).
	 * @param array<string, mixed> $assoc_args Named arguments (unused).
	 */
	public static function run( array $args = [], array $assoc_args = [] ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
		$client = Client_Registry::get_client();

		if ( ! $client ) {
			WP_CLI::warning( 'No client configured. Use: wp rtcamp-publish-with-ai oauth set-client --client_id=... --client_secret=... --redirect_uri=...' );
			return;
		}

		WP_CLI::log( sprintf( 'Client ID:     %s', $client['client_id'] ) );
		WP_CLI::log( sprintf( 'Client Name:   %s', $client['client_name'] ) );
		WP_CLI::log( sprintf( 'Redirect URIs: %s', implode( ', ', $client['redirect_uris'] ) ) );
		WP_CLI::log( 'Secret:        [hashed]' );
	}
}
