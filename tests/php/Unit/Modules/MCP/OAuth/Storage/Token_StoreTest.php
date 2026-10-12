<?php
/**
 * Tests for token rotation, removal and the connection list.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Storage
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\OAuth\Storage\Token_Store;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Token_StoreTest
 */
#[CoversClass( Token_Store::class )]
class Token_StoreTest extends TestCase {
	private const CLIENT_ID = 'https://app.example.com/client.json';

	/**
	 * A user to issue tokens for.
	 */
	private int $user_id;

	/**
	 * Create the user.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->user_id = self::factory()->user->create();
	}

	/**
	 * A refresh replaces the row: the old tokens stop working, and the name and approval time carry over.
	 */
	public function test_refresh_rotates_tokens(): void {
		$old = $this->issue();
		$this->set_row_field( 'authorized_at', 1000 );

		$new = Token_Store::refresh( $old['refresh_token'], self::CLIENT_ID );

		$this->assertIsArray( $new );
		$this->assertNull( Token_Store::validate_access_token( $old['access_token'] ) );
		$this->assertNotNull( Token_Store::validate_access_token( $new['access_token'] ) );

		$rows = $this->rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'Example App', $rows[0]['client_name'] );
		$this->assertSame( '1000', $rows[0]['authorized_at'] );
	}

	/**
	 * A refresh token works once; using it again fails.
	 */
	public function test_refresh_token_works_once(): void {
		$tokens = $this->issue();

		$this->assertIsArray( Token_Store::refresh( $tokens['refresh_token'], self::CLIENT_ID ) );
		$this->assertNull( Token_Store::refresh( $tokens['refresh_token'], self::CLIENT_ID ) );
		$this->assertCount( 1, $this->rows() );
	}

	/**
	 * When the old row disappears between the lookup and the delete (a concurrent refresh, or a removal), the refresh fails and creates nothing.
	 */
	public function test_refresh_fails_when_row_is_removed_mid_refresh(): void {
		global $wpdb;

		$tokens = $this->issue();
		$table  = $wpdb->prefix . 'publishio_oauth_tokens';

		// Remove the row right before refresh() runs its DELETE, as a concurrent request would.
		$remove_first = static function ( string $query ) use ( &$remove_first, $wpdb, $table ): string {
			if ( str_starts_with( $query, "DELETE FROM `{$table}`" ) && str_contains( $query, 'refresh_token_hash' ) ) {
				remove_filter( 'query', $remove_first );
				$wpdb->query( "DELETE FROM `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			return $query;
		};
		add_filter( 'query', $remove_first );

		$this->assertNull( Token_Store::refresh( $tokens['refresh_token'], self::CLIENT_ID ) );
		$this->assertSame( [], $this->rows() );
	}

	/**
	 * A refresh token is bound to its client and its expiry.
	 */
	public function test_refresh_rejects_other_client_and_expired_token(): void {
		$tokens = $this->issue();

		$this->assertNull( Token_Store::refresh( $tokens['refresh_token'], 'https://other.example.com/client.json' ) );

		$this->set_row_field( 'refresh_expires_at', time() - 1 );
		$this->assertNull( Token_Store::refresh( $tokens['refresh_token'], self::CLIENT_ID ) );
	}

	/**
	 * Removing a connection needs both its id and its user.
	 */
	public function test_revoke_requires_matching_user(): void {
		$this->issue();
		$id = (int) $this->rows()[0]['id'];

		$this->assertSame( 0, Token_Store::revoke( $id, $this->user_id + 1 ) );
		$this->assertCount( 1, $this->rows() );

		$this->assertSame( 1, Token_Store::revoke( $id, $this->user_id ) );
		$this->assertSame( [], $this->rows() );
	}

	/**
	 * The connection list and its count leave out expired tokens and tokens of users who no longer exist.
	 */
	public function test_connections_leave_out_expired_and_orphaned_tokens(): void {
		global $wpdb;

		// Issue all three first: issuing prunes the user's expired rows.
		$this->issue();
		$this->issue( 'Expired App' );
		$this->issue( 'Orphaned App' );
		$this->set_row_field( 'refresh_expires_at', time() - 1, 'Expired App' );
		$this->set_row_field( 'user_id', 999999, 'Orphaned App' );

		$now = time();

		$this->assertSame( 1, Token_Store::count_connections( $now ) );
		$this->assertSame( [ 'Example App' ], array_column( Token_Store::get_connections( 0, 10, $now ), 'client_name' ) );
		$this->assertSame( 3, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'publishio_oauth_tokens' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * The profile list shows the user's expired connections too, so they can be removed, and nobody else's.
	 */
	public function test_profile_list_includes_expired_connections(): void {
		$this->issue();
		$this->set_row_field( 'refresh_expires_at', time() - 1 );
		Token_Store::issue( self::factory()->user->create(), self::CLIENT_ID, 'Other User App', 'mcp:read', 'https://example.org/wp-json/mcp/publishio' );

		$connections = Token_Store::get_for_user( $this->user_id );

		$this->assertSame( [ 'Example App' ], array_column( $connections, 'client_name' ) );
		$this->assertSame( 1, Token_Store::revoke( (int) $connections[0]['id'], $this->user_id ) );
	}

	/**
	 * Deleting a user removes their tokens.
	 */
	public function test_deleting_user_removes_tokens(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$this->issue();
		wp_delete_user( $this->user_id );

		$this->assertSame( [], $this->rows() );
	}

	/**
	 * Connections sort by last activity, newest or oldest first.
	 */
	public function test_connections_sort_by_last_activity(): void {
		foreach ( [
			'Week ago'   => WEEK_IN_SECONDS,
			'Minute ago' => MINUTE_IN_SECONDS,
			'Hour ago'   => HOUR_IN_SECONDS,
		] as $name => $ago ) {
			$this->issue( $name );
			$this->set_row_field( 'created_at', time() - $ago, $name );
		}

		$now = time();

		$this->assertSame( [ 'Minute ago', 'Hour ago', 'Week ago' ], array_column( Token_Store::get_connections( 0, 10, $now ), 'client_name' ) );
		$this->assertSame( [ 'Week ago', 'Hour ago', 'Minute ago' ], array_column( Token_Store::get_connections( 0, 10, $now, true ), 'client_name' ) );
	}

	/**
	 * Issue tokens for the test user.
	 *
	 * @param string $client_name Client name to store.
	 *
	 * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, scope: string}
	 */
	private function issue( string $client_name = 'Example App' ): array {
		$tokens = Token_Store::issue( $this->user_id, self::CLIENT_ID, $client_name, 'mcp:read mcp:write', 'https://example.org/wp-json/mcp/publishio' );
		$this->assertIsArray( $tokens );

		return $tokens;
	}

	/**
	 * Set a column on the test rows, optionally only on rows with this client name.
	 *
	 * @param string      $column      Column name.
	 * @param int         $value       New value.
	 * @param string|null $client_name Limit to this client name.
	 */
	private function set_row_field( string $column, int $value, ?string $client_name = null ): void {
		global $wpdb;

		$where = null === $client_name ? [ 'client_id' => self::CLIENT_ID ] : [ 'client_name' => $client_name ];
		$wpdb->update( $wpdb->prefix . 'publishio_oauth_tokens', [ $column => $value ], $where ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * The test client's token rows.
	 *
	 * @return list<array<string, string>>
	 */
	private function rows(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE client_id = %s', $wpdb->prefix . 'publishio_oauth_tokens', self::CLIENT_ID ), ARRAY_A );

		return is_array( $rows ) ? array_values( $rows ) : [];
	}
}
