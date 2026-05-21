<?php
/**
 * Unit tests for the Healthcheck CLI command.
 *
 * @package rtCamp\Publish_With_AI\Tests\Unit\Modules\CLI
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Tests\Unit\Modules\CLI;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use rtCamp\Publish_With_AI\Modules\CLI\Healthcheck;
use rtCamp\Publish_With_AI\Tests\TestCase;

/**
 * Class - HealthcheckTest
 */
#[CoversClass( Healthcheck::class )]
class HealthcheckTest extends TestCase {
	/**
	 * Test that get_name() returns the expected command name.
	 */
	public function test_healthcheck_get_name(): void {
		$this->assertSame( 'health-check', Healthcheck::get_name() );
	}

	/**
	 * Test that get_description() returns a non-empty string.
	 */
	public function test_healthcheck_get_description(): void {
		$this->assertNotEmpty( Healthcheck::get_description() );
	}

	/**
	 * Test that run() executes without errors and logs the expected output.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_healthcheck_run(): void {
		$this->mock_wp_cli();

		Healthcheck::run();
		$this->assertNotEmpty( Mock_WP_CLI::$logs );
		$this->assertStringContainsString( 'Publish with AI Health Check', Mock_WP_CLI::$logs[0] );
	}

	/**
	 * Mocks the WP_CLI class for testing purposes.
	 */
	private function mock_wp_cli(): void {
		Mock_WP_CLI::reset();

		if ( ! class_exists( 'WP_CLI' ) ) {
			class_alias( Mock_WP_CLI::class, 'WP_CLI' );
		}
	}
}

/**
 * Mock class for WP_CLI to capture registered commands during testing.
 */
class Mock_WP_CLI {
	/**
	 * {@inheritDoc}
	 *
	 * @var array<string,mixed>
	 */
	public static array $logs = [];

	/**
	 * {@inheritDoc}
	 *
	 * @var array<string,mixed>
	 */
	public static array $errors = [];

	/**
	 * {@inheritDoc}
	 *
	 * @var array<string,mixed>
	 */
	public static array $successes = [];

	/**
	 * {@inheritDoc}
	 */
	public static function reset(): void {
		self::$logs      = [];
		self::$errors    = [];
		self::$successes = [];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $message The message to log.
	 */
	public static function log( string $message ): void {
		self::$logs[] = $message;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $message The error message.
	 */
	public static function error( string $message ): void {
		self::$errors[] = $message;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $message The success message.
	 */
	public static function success( string $message ): void {
		self::$successes[] = $message;
	}
}
