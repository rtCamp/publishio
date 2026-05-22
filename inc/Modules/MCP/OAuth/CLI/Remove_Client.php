<?php
/**
 * WP-CLI command to remove the OAuth client.
 *
 * Usage: wp rtcamp-publish-with-ai oauth remove-client
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth\CLI
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth\CLI;

use WP_CLI;
use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\CLI_Command;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Config;

/**
 * Class - Remove_Client
 */
final class Remove_Client implements CLI_Command {
	/**
	 * {@inheritDoc}
	 */
	public static function get_name(): string {
		return 'oauth remove-client';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_description(): string {
		return 'Remove the OAuth client.';
	}

	/**
	 * ## EXAMPLES
	 *
	 *   wp rtcamp-publish-with-ai oauth remove-client
	 *
	 * @param array<int, mixed>    $args       Positional arguments (unused).
	 * @param array<string, mixed> $assoc_args Named arguments (unused).
	 */
	public static function run( array $args = [], array $assoc_args = [] ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
		delete_option( Config::CLIENT_OPTION_KEY );
		WP_CLI::success( 'Client removed.' );
	}
}
