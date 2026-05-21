<?php
/**
 * Unit tests for the CLI module.
 *
 * @package rtCamp\Publish_With_AI\Tests\Unit\Modules
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Tests\Unit\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use rtCamp\Publish_With_AI\Modules\CLI;
use rtCamp\Publish_With_AI\Tests\TestCase;

/**
 * Class - CLITest
 */
#[CoversClass( CLI::class )]
class CLITest extends TestCase {
	/**
	 * Tests that register_hooks() does not attempt to register commands when WP_CLI is not defined or false.
	 */
	public function test_cli_register_hooks_safe_without_wp_cli(): void {
		$cli = new CLI();

		$this->assertNull( $cli->register_hooks() );
	}

	/**
	 * Test that CLI commands are registered when WP_CLI is available.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_register_hooks_registers_commands_when_wp_cli_available(): void {
		$this->mock_wp_cli();

		define( 'WP_CLI', true );

		$cli = new CLI();
		$cli->register_hooks();

		$this->assertCount( 1, CLI_Mock_WP_CLI::$commands );
		$this->assertStringContainsString( 'rtcamp-publish-with-ai', array_key_first( CLI_Mock_WP_CLI::$commands ) );
	}

	/**
	 * Mocks the WP_CLI class for testing purposes.
	 */
	private function mock_wp_cli(): void {
		CLI_Mock_WP_CLI::reset();

		if ( ! class_exists( 'WP_CLI' ) ) {
			class_alias( CLI_Mock_WP_CLI::class, 'WP_CLI' );
		}
	}
}

/**
 * Mock class for WP_CLI to capture registered commands during testing.
 */
class CLI_Mock_WP_CLI {
	/**
	 * {@inheritDoc}
	 *
	 * @var array<string, mixed> Registered commands.
	 */
	public static array $commands = [];

	/**
	 * {@inheritDoc}
	 */
	public static function reset(): void {
		self::$commands = [];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function add_command( string $name, $callback, array $args = [] ): void {
		self::$commands[ $name ] = [
			'callback' => $callback,
			'args'     => $args,
		];
	}
}
