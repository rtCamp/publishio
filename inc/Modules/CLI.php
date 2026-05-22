<?php
/**
 * WP-CLI Commands for Publish with AI.
 *
 * @package rtCamp\Publish_With_AI\Modules
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules;

use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\CLI\Get_Client;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\CLI\Remove_Client;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\CLI\Set_Client;

/**
 * Class - CLI
 *
 * Registers WP-CLI commands for the plugin.
 */
final class CLI implements Registrable {
	/**
	 * {@inheritDoc}
	 *
	 * @uses \WP_CLI::add_command() instead of a hook.
	 */
	public function register_hooks(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		foreach ( $this->get_commands() as $name => $command ) {
			\WP_CLI::add_command(
				"rtcamp-publish-with-ai {$name}",
				$command['callback'],
				[
					'shortdesc' => $command['description'],
				]
			);
		}
	}

	/**
	 * Get available CLI commands.
	 *
	 * @return array<string, array{
	 *   callback: callable( array<int, mixed>, array<string, mixed> ): void,
	 *   description: string
	 * }>
	 */
	private function get_commands(): array {
		$commands = [
			CLI\Healthcheck::class,
			Set_Client::class,
			Get_Client::class,
			Remove_Client::class,
		];

		return array_reduce(
			$commands,
			static function ( $carry, $command_class ) {
				$carry[ $command_class::get_name() ] = [
					'callback'    => [ $command_class, 'run' ],
					'description' => $command_class::get_description(),
				];
				return $carry;
			},
			[]
		);
	}
}
