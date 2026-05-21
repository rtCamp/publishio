<?php
/**
 * Unit tests for Main.
 *
 * @package rtCamp\Publish_With_AI\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publish_With_AI\Main;
use rtCamp\Publish_With_AI\Tests\TestCase;

/**
 * Class - MainTest
 */
#[CoversClass( \rtCamp\Publish_With_AI\Main::class )]
class MainTest extends TestCase {
	/**
	 * Reset the Main singleton instance.
	 */
	private function reset_main_instance(): void {
		$ref  = new \ReflectionClass( Main::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		remove_all_actions( 'init' );
		remove_all_actions( 'admin_enqueue_scripts' );
		remove_all_filters( 'script_loader_tag' );
		delete_option( 'Publish_With_AI_version' );

		parent::tearDown();
	}

	/**
	 * Test that get_instance returns the same singleton instance.
	 */
	public function test_get_instance_returns_singleton(): void {
		$this->reset_main_instance();
		$a = Main::get_instance();
		$b = Main::get_instance();
		$this->assertSame( $a, $b );
	}

	/**
	 * Test that activate() sets the version option.
	 */
	public function test_activate_sets_version_option(): void {
		$this->reset_main_instance();

		Main::activate();

		$this->assertEquals( RTCAMP_PUBLISH_WITH_AI_VERSION, get_option( 'Publish_With_AI_version' ) );
	}

	/**
	 * Test that deactivate() runs without errors.
	 */
	public function test_deactivate_runs_without_errors(): void {
		$this->reset_main_instance();

		Main::deactivate();

		// Deactivate is currently a no-op, just verify it doesn't throw.
		$this->assertTrue( true );
	}

	/**
	 * Test that setup() registers activation and deactivation hooks.
	 */
	public function test_setup_registers_activation_deactivation_hooks(): void {
		$this->reset_main_instance();

		Main::get_instance();

		$this->assertNotFalse( has_action( 'activate_' . plugin_basename( RTCAMP_PUBLISH_WITH_AI_FILE ) ) );
		$this->assertNotFalse( has_action( 'deactivate_' . plugin_basename( RTCAMP_PUBLISH_WITH_AI_FILE ) ) );
	}
}
