<?php
/**
 * Example_Post_Type_MetaTest file.
 *
 * @package rtCamp\Publish_With_AI\Tests\Unit\Modules\Example
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Tests\Unit\Modules\Example;

use PHPUnit\Framework\Attributes\CoversClass;
use WP_REST_Request;
use rtCamp\Publish_With_AI\Modules\Example\Example_Post_Type;
use rtCamp\Publish_With_AI\Modules\Example\Example_Post_Type_Meta;
use rtCamp\Publish_With_AI\Tests\TestCase;

/**
 * Class - Example_Post_Type_MetaTest
 */
#[CoversClass( Example_Post_Type_Meta::class )]
class Example_Post_Type_MetaTest extends TestCase {
	/**
	 * Setup before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Register the post type and meta so it's available for actual get/set tests.
		( new Example_Post_Type() )->register_post_type();
		( new Example_Post_Type_Meta() )->register_meta();

		// REST API requires the post type to support 'custom-fields' to process meta.
		add_post_type_support( Example_Post_Type::get_slug(), 'custom-fields' );
	}

	/**
	 * Test that register_hooks adds the init action for post meta registration.
	 */
	public function test_register_hooks_adds_init_action(): void {
		$post_type_meta = new Example_Post_Type_Meta();

		$post_type_meta->register_hooks();

		$this->assertNotFalse( has_action( 'init', [ $post_type_meta, 'register_meta' ] ) );
	}

	/**
	 * Test that getting and setting post meta works normally and sanitizes Input.
	 */
	public function test_can_get_and_set_meta_with_sanitization(): void {
		$post_id = $this->factory()->post->create( [ 'post_type' => Example_Post_Type::get_slug() ] );

		// Test setting valid string.
		update_post_meta( $post_id, Example_Post_Type_Meta::EXAMPLE_META_KEY, 'valid string' );
		$this->assertSame( 'valid string', get_post_meta( $post_id, Example_Post_Type_Meta::EXAMPLE_META_KEY, true ) );

		// Test sanitization callback removes script tags (sanitize_text_field).
		update_post_meta( $post_id, Example_Post_Type_Meta::EXAMPLE_META_KEY, 'Text <script>alert("xss")</script>' );
		$this->assertSame( 'Text', get_post_meta( $post_id, Example_Post_Type_Meta::EXAMPLE_META_KEY, true ) );
	}

	/**
	 * Test that the meta can be updated via the REST API by an authorized user (acting correctly on auth_callback).
	 */
	public function test_meta_can_be_updated_via_rest_by_authorized_user(): void {
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'editor' ] ) );
		$post_id = $this->factory()->post->create( [ 'post_type' => Example_Post_Type::get_slug() ] );

		// Initialize REST API.
		rest_get_server();
		do_action( 'rest_api_init' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/example/' . $post_id );
		$request->set_body_params(
			[
				'meta' => [
					Example_Post_Type_Meta::EXAMPLE_META_KEY => 'updated via rest',
				],
			]
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'updated via rest', get_post_meta( $post_id, Example_Post_Type_Meta::EXAMPLE_META_KEY, true ) );
	}

	/**
	 * Test that the meta cannot be updated via the REST API by an unauthorized user.
	 */
	public function test_meta_cannot_be_updated_via_rest_by_unauthorized_user(): void {
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$post_id = $this->factory()->post->create( [ 'post_type' => Example_Post_Type::get_slug() ] );

		// Initialize REST API.
		rest_get_server();
		do_action( 'rest_api_init' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/example/' . $post_id );
		$request->set_body_params(
			[
				'meta' => [
					Example_Post_Type_Meta::EXAMPLE_META_KEY => 'unauthorized rest update',
				],
			]
		);

		$response = rest_get_server()->dispatch( $request );

		// The update should be rejected (could be 401/403 since subscriber can't edit post).
		$this->assertTrue( in_array( $response->get_status(), [ 401, 403 ], true ), 'Response should be unauthorized or forbidden.' );

		// The meta value must not exist/must not be updated.
		$this->assertNotSame( 'unauthorized rest update', get_post_meta( $post_id, Example_Post_Type_Meta::EXAMPLE_META_KEY, true ) );
	}
}
