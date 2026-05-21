<?php
/**
 * Example_REST_ControllerTest file.
 *
 * @package rtCamp\Publish_With_AI\Tests\Unit\Modules\Example
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Tests\Unit\Modules\Example;

use PHPUnit\Framework\Attributes\CoversClass;
use WP_REST_Request;
use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_REST_Controller;
use rtCamp\Publish_With_AI\Modules\Example\Example_REST_Controller;
use rtCamp\Publish_With_AI\Tests\TestCase;

/**
 * Class - Example_REST_ControllerTest
 */
#[CoversClass( Example_REST_Controller::class )]
#[CoversClass( Abstract_REST_Controller::class )]
class Example_REST_ControllerTest extends TestCase {
	/**
	 * Test that register_hooks adds the rest_api_init action.
	 */
	public function test_register_hooks_adds_rest_api_init_action(): void {
		$controller = new Example_REST_Controller();

		$controller->register_hooks();

		$this->assertNotFalse( \has_action( 'rest_api_init', [ $controller, 'register_routes' ] ) );
	}

	/**
	 * Test that get_items returns the expected response structure.
	 */
	public function test_get_items_returns_expected_response_structure(): void {
		$controller = new Example_REST_Controller();
		$request    = new WP_REST_Request();

		$response = $controller->get_items( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			[
				'example_response' => 'example_response',
			],
			$response->get_data()
		);
	}

	/**
	 * Test that get_items_permissions_check returns true for administrator.
	 */
	public function test_get_items_permissions_check_returns_true_for_administrator(): void {
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		\wp_set_current_user( $admin_id );

		$controller = new Example_REST_Controller();
		$request    = new WP_REST_Request();

		$result = $controller->get_items_permissions_check( $request );

		$this->assertTrue( $result );
	}

	/**
	 * Test that get_items_permissions_check returns WP_Error for subscriber.
	 */
	public function test_get_items_permissions_check_returns_wp_error_for_subscriber(): void {
		$subscriber_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		\wp_set_current_user( $subscriber_id );

		$controller = new Example_REST_Controller();
		$request    = new WP_REST_Request();

		$result = $controller->get_items_permissions_check( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
		$this->assertSame(
			'Sorry, you are not allowed to access this endpoint.',
			$result->get_error_message()
		);
	}

	/**
	 * Test that get_items_permissions_check returns WP_Error for unauthenticated user.
	 */
	public function test_get_items_permissions_check_returns_wp_error_for_unauthenticated_user(): void {
		\wp_set_current_user( 0 );

		$controller = new Example_REST_Controller();
		$request    = new WP_REST_Request();

		$result = $controller->get_items_permissions_check( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}
}
