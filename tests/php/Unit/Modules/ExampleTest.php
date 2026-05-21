<?php
/**
 * Unit tests for the Example module.
 *
 * @package rtCamp\Publish_With_AI\Tests\Unit\Modules
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Tests\Unit\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publish_With_AI\Modules\Example;
use rtCamp\Publish_With_AI\Tests\TestCase;

/**
 * Class - ExampleTest
 */
#[CoversClass( Example::class )]
class ExampleTest extends TestCase {
	/**
	 * Tests that register_hooks() instantiates and calls register_hooks() on registrable classes without fatal errors.
	 */
	public function test_register_hooks_does_not_fatal(): void {
		$example = new Example();

		$example->register_hooks();

		// If we're here, it means no fatal errors were thrown.
		$this->assertTrue( true );
	}
}
