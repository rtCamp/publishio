<?php
/**
 * WP-CLI command to set the OAuth client credentials.
 *
 * Usage: wp rtcamp-publish-with-ai oauth set-client --client_id=claude --client_secret=s3cret --redirect_uri=https://claude.ai/oauth/callback
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth\CLI
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth\CLI;

use WP_CLI;
use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\CLI_Command;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Client\Client_Registry;

/**
 * Class - Set_Client
 */
final class Set_Client implements CLI_Command {
	/**
	 * {@inheritDoc}
	 */
	public static function get_name(): string {
		return 'oauth set-client';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_description(): string {
		return 'Set the OAuth client credentials.';
	}

	/**
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
	 *   wp rtcamp-publish-with-ai oauth set-client --client_id=claude --client_secret=s3cret --redirect_uri=https://claude.ai/oauth/callback --client_name="Claude AI"
	 *
	 * @param array<int, mixed>    $args       Positional arguments (unused).
	 * @param array<string, mixed> $assoc_args Named arguments.
	 */
	public static function run( array $args = [], array $assoc_args = [] ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
		$client_id     = (string) ( $assoc_args['client_id'] ?? '' );
		$client_secret = (string) ( $assoc_args['client_secret'] ?? '' );
		$redirect_uri  = (string) ( $assoc_args['redirect_uri'] ?? '' );
		$client_name   = (string) ( $assoc_args['client_name'] ?? 'MCP Client' );

		Client_Registry::save_client(
			$client_id,
			$client_secret,
			[ $redirect_uri ],
			$client_name
		);

		WP_CLI::success( sprintf( 'Client "%s" saved (secret hashed).', $client_id ) );
	}
}
