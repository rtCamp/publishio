<?php
/**
 * Tests for the Connections page's REST routes.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\Settings\Connections
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\Settings\Connections;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\OAuth\Storage\Token_Store;
use rtCamp\Publishio\Modules\Settings\Connections\REST_Controller;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - REST_ControllerTest
 */
#[CoversClass( REST_Controller::class )]
class REST_ControllerTest extends TestCase {
	/**
	 * A site admin, who is not a super admin on multisite.
	 */
	private int $admin_id;

	/**
	 * Create the admin.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
	}

	/**
	 * On a single site every user is a member, and an admin gets a link to edit them.
	 */
	public function test_lists_members_with_edit_links_on_single_site(): void {
		$this->skipWithMultisite();

		$editor = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->connect( $editor );

		$items = $this->list_as( $this->admin_id )['items'];

		$this->assertCount( 1, $items );
		$this->assertSame( 'member', $items[0]['user']['membership'] );
		$this->assertSame( admin_url( 'user-edit.php?user_id=' . $editor ), $items[0]['user']['admin_edit_url'] );
	}

	/**
	 * A super admin who isn't a member, and a user who no longer is, are listed with labels. The total matches the list.
	 */
	public function test_lists_users_who_are_not_members(): void {
		$this->skipWithoutMultisite();

		$member = self::factory()->user->create( [ 'role' => 'editor' ] );
		$super  = $this->user_outside_site();
		$former = $this->user_outside_site();
		grant_super_admin( $super );
		$this->connect( $member );
		$this->connect( $super );
		$this->connect( $former );

		$data  = $this->list_as( $this->admin_id );
		$users = array_column( array_column( $data['items'], 'user' ), 'membership', 'id' );
		ksort( $users );

		$this->assertSame( 3, $data['total'] );
		$this->assertSame(
			[
				$member => 'member',
				$super  => 'super_admin',
				$former => 'not_member',
			],
			$users
		);
	}

	/**
	 * A site admin can't edit users on multisite, so gets no link to; a super admin does.
	 */
	public function test_edit_link_only_for_those_who_can_edit_users(): void {
		$this->skipWithoutMultisite();

		$editor = self::factory()->user->create( [ 'role' => 'editor' ] );
		$super  = $this->user_outside_site();
		grant_super_admin( $super );
		$this->connect( $editor );

		$this->assertNull( $this->list_as( $this->admin_id )['items'][0]['user']['admin_edit_url'] );
		$this->assertSame( admin_url( 'user-edit.php?user_id=' . $editor ), $this->list_as( $super )['items'][0]['user']['admin_edit_url'] );
	}

	/**
	 * A site admin can remove a connection of a super admin who isn't a member.
	 */
	public function test_site_admin_removes_super_admin_connection(): void {
		$this->skipWithoutMultisite();

		$super = $this->user_outside_site();
		grant_super_admin( $super );
		$this->connect( $super );
		$id = $this->list_as( $this->admin_id )['items'][0]['id'];

		$request = new \WP_REST_Request( 'DELETE', '/publishio/v1/connections/' . $id );
		$request->set_param( 'user_id', $super );

		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertSame( 0, Token_Store::count_connections( time() ) );
	}

	/**
	 * A user who isn't a member of the current site.
	 */
	private function user_outside_site(): int {
		$user_id = self::factory()->user->create();
		remove_user_from_blog( $user_id, get_current_blog_id() );

		return $user_id;
	}

	/**
	 * Store a connection for a user on the current site.
	 *
	 * @param int $user_id The user.
	 */
	private function connect( int $user_id ): void {
		$this->assertIsArray( Token_Store::issue( $user_id, 'https://app.example.com/client.json', 'Example App', 'mcp:read', 'https://example.org/wp-json/mcp/publishio' ) );
	}

	/**
	 * The connection list as a user sees it.
	 *
	 * @param int $user_id The viewing user.
	 *
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	private function list_as( int $user_id ): array {
		wp_set_current_user( $user_id );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/publishio/v1/connections' ) );

		$this->assertSame( 200, $response->get_status() );

		return (array) $response->get_data();
	}
}
