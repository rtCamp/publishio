<?php
/**
 * Tests for the Status page's REST routes.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\Settings\Status
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\Settings\Status;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\Settings\Status\REST_Controller;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - REST_ControllerTest
 */
#[CoversClass( REST_Controller::class )]
class REST_ControllerTest extends TestCase {
	/**
	 * Contributors can connect an AI app, so they can check the site; subscribers can't.
	 */
	public function test_anyone_who_can_connect_an_app_may_check(): void {
		$controller = new REST_Controller();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'contributor' ] ) );
		$this->assertTrue( $controller->permissions_check() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertFalse( $controller->permissions_check() );
	}

	/**
	 * Both routes answer a contributor: the site facts, and each app's result.
	 */
	public function test_routes_return_facts_and_app_results(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'contributor' ] ) );
		add_filter( 'https_ssl_verify', '__return_false' );

		$facts = rest_do_request( new \WP_REST_Request( 'GET', '/publishio/v1/status' ) );
		$apps  = rest_do_request( new \WP_REST_Request( 'GET', '/publishio/v1/status/apps' ) );

		$this->assertSame( 200, $facts->get_status() );
		$this->assertSame( PUBLISHIO_VERSION, ( (array) $facts->get_data() )['publishio_version'] );
		$this->assertSame( 200, $apps->get_status() );
		$this->assertSame( [ 'ssl_verify_off', 'ssl_verify_off', 'ssl_verify_off' ], array_column( ( (array) $apps->get_data() )['apps'], 'code' ) );
	}

	/**
	 * Subscribers get a 403 from both routes.
	 */
	public function test_routes_refuse_subscribers(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$this->assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/publishio/v1/status' ) )->get_status() );
		$this->assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/publishio/v1/status/apps' ) )->get_status() );
	}
}
