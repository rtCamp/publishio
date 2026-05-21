<?php
/**
 * Example_Post_TypeTest file.
 *
 * @package rtCamp\Publish_With_AI\Tests\Unit\Modules\Example
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Tests\Unit\Modules\Example;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_Post_Type;
use rtCamp\Publish_With_AI\Modules\Example\Example_Post_Type;
use rtCamp\Publish_With_AI\Modules\Example\Example_Taxonomy;
use rtCamp\Publish_With_AI\Tests\TestCase;

/**
 * Class - Example_Post_TypeTest
 */
#[CoversClass( Example_Post_Type::class )]
#[CoversClass( Abstract_Post_Type::class )]
class Example_Post_TypeTest extends TestCase {
	/**
	 * Test that register_hooks adds the init action for post type registration.
	 */
	public function test_register_hooks_adds_init_action(): void {
		$post_type = new Example_Post_Type();

		$post_type->register_hooks();

		$this->assertNotFalse( has_action( 'init', [ $post_type, 'register_post_type' ] ) );
	}

	/**
	 * Test that register_post_type registers a public post type with REST support.
	 */
	public function test_register_post_type_registers_a_public_post_type_with_rest_support(): void {
		$post_type = new Example_Post_Type();

		$post_type->register_post_type();

		$this->assertTrue( post_type_exists( Example_Post_Type::get_slug() ) );

		$object = get_post_type_object( Example_Post_Type::get_slug() );

		$this->assertTrue( $object->public );
		$this->assertTrue( $object->show_in_rest );
		$this->assertTrue( $object->has_archive );
	}

	/**
	 * Test that the registered post type is associated with the example taxonomy.
	 */
	public function test_register_post_type_associates_the_example_taxonomy(): void {
		$post_type = new Example_Post_Type();

		$post_type->register_post_type();

		$object = get_post_type_object( Example_Post_Type::get_slug() );

		$this->assertContains( Example_Taxonomy::get_slug(), $object->taxonomies );
	}
}
