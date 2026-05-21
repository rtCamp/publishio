<?php
/**
 * Example_TaxonomyTest file.
 *
 * @package rtCamp\Publish_With_AI\Tests\Unit\Modules\Example
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Tests\Unit\Modules\Example;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_Taxonomy;
use rtCamp\Publish_With_AI\Modules\Example\Example_Post_Type;
use rtCamp\Publish_With_AI\Modules\Example\Example_Taxonomy;
use rtCamp\Publish_With_AI\Tests\TestCase;

/**
 * Class - Example_TaxonomyTest
 */
#[CoversClass( Example_Taxonomy::class )]
#[CoversClass( Abstract_Taxonomy::class )]
class Example_TaxonomyTest extends TestCase {
	/**
	 * Test that register_hooks adds the init action for taxonomy registration.
	 */
	public function test_register_hooks_adds_init_action(): void {
		$taxonomy = new Example_Taxonomy();

		$taxonomy->register_hooks();

		$this->assertNotFalse( has_action( 'init', [ $taxonomy, 'register_taxonomy' ] ) );
	}

	/**
	 * Test that register_taxonomy registers a hierarchical taxonomy with REST support.
	 */
	public function test_register_taxonomy_registers_a_hierarchical_taxonomy_with_rest_support(): void {
		( new Example_Post_Type() )->register_post_type();

		$taxonomy = new Example_Taxonomy();

		$taxonomy->register_taxonomy();

		$this->assertTrue( taxonomy_exists( Example_Taxonomy::get_slug() ) );

		$object = get_taxonomy( Example_Taxonomy::get_slug() );

		$this->assertTrue( $object->hierarchical );
		$this->assertTrue( $object->show_in_rest );
		$this->assertTrue( $object->show_ui );
	}

	/**
	 * Test that the registered taxonomy is associated with the example post type.
	 */
	public function test_register_taxonomy_associates_the_example_post_type(): void {
		( new Example_Post_Type() )->register_post_type();

		$taxonomy = new Example_Taxonomy();

		$taxonomy->register_taxonomy();

		$object = get_taxonomy( Example_Taxonomy::get_slug() );

		$this->assertContains( Example_Post_Type::get_slug(), $object->object_type );
	}
}
