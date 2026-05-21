<?php
/**
 * Interface for WP_CLI commands.
 *
 * @package rtCamp\Publish_With_AI\Framework\Contracts\Interfaces
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Framework\Contracts\Interfaces;

/**
 * Interface - CLI_Command
 */
interface CLI_Command {
	/**
	 * Get the command name.
	 */
	public static function get_name(): string;

	/**
	 * Get the command description.
	 */
	public static function get_description(): string;

	/**
	 * Run the command.
	 *
	 * @param array<int, mixed>    $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public static function run( array $args, array $assoc_args ): void;
}
