<?php
/**
 * Unit tests for Assets.
 *
 * @package rtCamp\Publish_With_AI\Tests\Unit\Core
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Tests\Unit\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publish_With_AI\Core\Assets;
use rtCamp\Publish_With_AI\Tests\TestCase;

/**
 * Class - AssetsTest
 */
#[CoversClass( \rtCamp\Publish_With_AI\Core\Assets::class )]
class AssetsTest extends TestCase {
	/**
	 * Ensure no errors are thrown when the Assets class is instantiated.
	 */
	public function test_assets_class_instantiation(): void {
		$assets = new Assets();
		$this->assertInstanceOf( Assets::class, $assets );

		$assets->register_hooks();
		$assets->register_assets();
		$assets->register_admin_assets();
		$assets->register_editor_assets();

		$this->setExpectedIncorrectUsage( 'WP_Block_Type_Registry::register' );
		$assets->register_blocks();

		// If we reach this point without any exceptions, the test passes.
		$this->assertTrue( true );
	}

	/**
	 * Tests defer attribute is added to block editor script tags.
	 */
	public function test_defer_attribute_added_to_block_editor_scripts(): void {
		$assets = new Assets();

		// Simulate the script tag filter for a script handle that should be deferred.
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$script_tag = '<script src="test.js"></script>';

		// An unallowed handle should not be modified.
		$this->assertSame( $script_tag, $assets->defer_scripts( $script_tag, 'unallowed-handle' ) );

		// Get a handle from the private DEFERRED_ASSETS constant using reflection.
		$reflection     = new \ReflectionClass( Assets::class );
		$defer_handles  = $reflection->getConstant( 'DEFERRED_ASSETS' );
		$handle_to_test = $defer_handles[0];

		// An allowed handle should have the defer attribute added.
		$expected_tag = '<script defer src="test.js"></script>';
		$this->assertSame( $expected_tag, $assets->defer_scripts( $script_tag, $handle_to_test ) );
	}
}
