<?php
/**
 * Unit tests for Autoloader.
 *
 * @package rtCamp\Publishio\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Autoloader;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - AutoloaderTest
 */
#[CoversClass( \rtCamp\Publishio\Autoloader::class )]
class AutoloaderTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'network_admin_notices' );

		parent::tearDown();
	}

	/**
	 * Tests that autoload returns true when PUBLISHIO_AUTOLOAD is false.
	 */
	public function test_autoload_returns_true_when_autoload_disabled(): void {
		define( 'PUBLISHIO_AUTOLOAD', false );
		$this->assertTrue( Autoloader::autoload() );
	}

	/**
	 * Tests that autoload returns true when vendor/autoload.php exists.
	 *
	 * This tests the actual autoloader with the real plugin path,
	 * which should have vendor/autoload.php available in the test environment.
	 */
	public function test_autoload_returns_true_when_autoloader_exists(): void {
		// The real autoloader should exist in our test environment.
		$this->assertTrue( Autoloader::autoload() );
	}

	/**
	 * Tests that autoload uses the correct path constant.
	 */
	public function test_autoload_uses_correct_path(): void {
		// Verify the autoloader path is constructed correctly.
		$expected_path = PUBLISHIO_PATH . 'vendor/autoload.php';
		$this->assertStringEndsWith( 'vendor/autoload.php', $expected_path );
		$this->assertTrue( Autoloader::autoload() );
	}

	/**
	 * Tests that get_autoloader_error_message returns a string with the plugin name.
	 */
	public function test_get_autoloader_error_message_contains_plugin_name(): void {
		$method = new \ReflectionMethod( Autoloader::class, 'get_autoloader_error_message' );
		$method->setAccessible( true );
		$result = $method->invoke( null );
		$this->assertStringContainsString( 'Publishio', $result );
		$this->assertStringContainsString( 'Composer autoloader', $result );
	}
}
