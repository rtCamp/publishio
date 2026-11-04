<?php
/**
 * Tests for token rotation, lifetime, removal and the connection list.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Storage
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use rtCamp\Publishio\Modules\MCP\OAuth\Config;
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
		$old           = $this->issue();
		$authorized_at = time() - DAY_IN_SECONDS;
		$this->set_row_field( 'authorized_at', $authorized_at );

		$new = Token_Store::refresh( $old['refresh_token'], self::CLIENT_ID );

		$this->assertIsArray( $new );
		$this->assertNull( Token_Store::validate_access_token( $old['access_token'] ) );
		$this->assertNotNull( Token_Store::validate_access_token( $new['access_token'] ) );

		$rows = $this->rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'Example App', $rows[0]['client_name'] );
		$this->assertSame( (string) $authorized_at, $rows[0]['authorized_at'] );
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
	 * Refreshing never extends a connection past its lifetime from approval, and a connection past it can't refresh.
	 */
	public function test_refresh_stops_at_connection_lifetime(): void {
		$tokens = $this->issue();
		$this->set_row_field( 'authorized_at', time() - ( Config::MAX_CONNECTION_LIFETIME - DAY_IN_SECONDS ) );

		$tokens = Token_Store::refresh( $tokens['refresh_token'], self::CLIENT_ID );

		$this->assertIsArray( $tokens );
		$row = $this->rows()[0];
		$this->assertSame( (int) $row['authorized_at'] + Config::MAX_CONNECTION_LIFETIME, (int) $row['refresh_expires_at'] );

		// The refresh token itself is still unexpired; only the lifetime ends it.
		$this->set_row_field( 'authorized_at', time() - Config::MAX_CONNECTION_LIFETIME - 1 );
		$this->assertNull( Token_Store::refresh( $tokens['refresh_token'], self::CLIENT_ID ) );
	}

	/**
	 * A refresh in the second the connection ends gets nothing, rather than tokens that expire as they are issued.
	 */
	public function test_refresh_at_end_of_lifetime_is_rejected(): void {
		$tokens = $this->issue();
		$this->set_row_field( 'authorized_at', time() - Config::MAX_CONNECTION_LIFETIME );

		$this->assertNull( Token_Store::refresh( $tokens['refresh_token'], self::CLIENT_ID ) );
	}

	/**
	 * Lowering the lifetime drops connections past it from the Connections page, and the profile page shows them as ended.
	 */
	public function test_lowered_lifetime_drops_connection_from_lists(): void {
		$this->issue();
		$authorized_at = time() - 2 * DAY_IN_SECONDS;
		$this->set_row_field( 'authorized_at', $authorized_at );
		$now   = time();
		$count = Token_Store::count_connections( $now );
		$this->assertContains( 'Example App', array_column( Token_Store::get_connections( 0, 100, $now ), 'client_name' ) );

		add_filter( 'publishio_max_connection_lifetime', static fn () => DAY_IN_SECONDS );

		$this->assertSame( $count - 1, Token_Store::count_connections( $now ) );
		$this->assertNotContains( 'Example App', array_column( Token_Store::get_connections( 0, 100, $now ), 'client_name' ) );
		$this->assertSame( $authorized_at + DAY_IN_SECONDS, Token_Store::expires_at( (int) $this->rows()[0]['refresh_expires_at'], $authorized_at ) );
	}

	/**
	 * Lowering the lifetime ends access tokens of connections past it at once, not at their next refresh.
	 */
	public function test_lowered_lifetime_ends_access_token(): void {
		$tokens = $this->issue();
		$this->set_row_field( 'authorized_at', time() - 2 * DAY_IN_SECONDS );
		$this->assertNotNull( Token_Store::validate_access_token( $tokens['access_token'] ) );

		add_filter( 'publishio_max_connection_lifetime', static fn () => DAY_IN_SECONDS );

		$this->assertNull( Token_Store::validate_access_token( $tokens['access_token'] ) );
	}

	/**
	 * Near the end of a connection, the access token ends with it, and expires_in says so.
	 */
	public function test_access_token_ends_with_connection(): void {
		$tokens = $this->issue();
		$this->set_row_field( 'authorized_at', time() - ( Config::MAX_CONNECTION_LIFETIME - 10 * MINUTE_IN_SECONDS ) );

		$tokens = Token_Store::refresh( $tokens['refresh_token'], self::CLIENT_ID );

		$this->assertIsArray( $tokens );
		$this->assertLessThanOrEqual( 10 * MINUTE_IN_SECONDS, $tokens['expires_in'] );
		$row = $this->rows()[0];
		$this->assertSame( (int) $row['authorized_at'] + Config::MAX_CONNECTION_LIFETIME, (int) $row['access_expires_at'] );
		$this->assertSame( $row['access_expires_at'], $row['refresh_expires_at'] );
	}

	/**
	 * Filter values that turn the connection lifetime off.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function unlimited_lifetimes(): array {
		return [
			'zero'     => [ 0 ],
			'negative' => [ -1 ],
			'false'    => [ false ],
			'null'     => [ null ],
		];
	}

	/**
	 * A lifetime of 0 or less means connections don't end, rather than ending at once.
	 *
	 * @param mixed $lifetime The filtered lifetime.
	 */
	#[DataProvider( 'unlimited_lifetimes' )]
	public function test_unlimited_lifetime( mixed $lifetime ): void {
		add_filter( 'publishio_max_connection_lifetime', static fn () => $lifetime );
		$tokens = $this->issue();
		$this->assertSame( Config::ACCESS_TOKEN_TTL, $tokens['expires_in'] );
		$this->set_row_field( 'authorized_at', time() - 1000 * DAY_IN_SECONDS );

		$tokens = Token_Store::refresh( $tokens['refresh_token'], self::CLIENT_ID );

		$this->assertIsArray( $tokens );
		$row = $this->rows()[0];
		$this->assertSame( (int) $row['created_at'] + Config::REFRESH_TOKEN_TTL, (int) $row['refresh_expires_at'] );
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
	 * Ways a user's connections are ended: changing their password, even to the same one, and logging them out everywhere.
	 *
	 * @return array<string, array{callable(int): mixed}>
	 */
	public static function revocations(): array {
		return [
			'password reset'     => [ static fn ( int $user_id ) => reset_password( get_userdata( $user_id ), 'new-password' ) ],
			'wp_update_user()'   => [
				static fn ( int $user_id ) => wp_update_user(
					[
						'ID'        => $user_id,
						'user_pass' => 'new-password',
					]
				),
			],
			'wp_set_password()'  => [ static fn ( int $user_id ) => wp_set_password( 'new-password', $user_id ) ],
			'same password'      => [ static fn ( int $user_id ) => wp_set_password( 'password', $user_id ) ],
			'log out everywhere' => [ static fn ( int $user_id ) => self::log_out_everywhere( $user_id, 'administrator' ) ],
		];
	}

	/**
	 * Each way of ending a user's connections removes their tokens, and nobody else's.
	 *
	 * @param callable(int): mixed $revoke Ends the user's connections.
	 */
	#[DataProvider( 'revocations' )]
	public function test_revocation_removes_tokens( callable $revoke ): void {
		$tokens = $this->issue();
		$other  = Token_Store::issue( self::factory()->user->create(), self::CLIENT_ID, 'Other User App', 'mcp:read', 'https://example.org/wp-json/mcp/publishio' );
		$this->assertIsArray( $other );

		$revoke( $this->user_id );

		$this->assertNull( Token_Store::validate_access_token( $tokens['access_token'] ) );
		$this->assertNull( Token_Store::refresh( $tokens['refresh_token'], self::CLIENT_ID ) );
		$this->assertNotNull( Token_Store::validate_access_token( $other['access_token'] ) );
	}

	/**
	 * A row a refresh inserts after the revocation keeps its old approval time, so it is rejected and pruned straight away, as is a connection approved in the revocation's second. One approved after it works.
	 */
	public function test_tokens_issued_before_revocation_are_rejected(): void {
		wp_set_password( 'new-password', $this->user_id );
		$revoked_at = (int) get_user_meta( $this->user_id, 'publishio_tokens_revoked_at', true );

		$raced = Token_Store::issue( $this->user_id, self::CLIENT_ID, 'Raced Refresh', 'mcp:read', 'https://example.org/wp-json/mcp/publishio', time() - DAY_IN_SECONDS );
		$this->assertIsArray( $raced );
		$this->assertNull( Token_Store::validate_access_token( $raced['access_token'] ) );
		$this->assertNull( Token_Store::refresh( $raced['refresh_token'], self::CLIENT_ID ) );

		$same_second = Token_Store::issue( $this->user_id, self::CLIENT_ID, 'Same Second', 'mcp:read', 'https://example.org/wp-json/mcp/publishio', $revoked_at );
		$after       = Token_Store::issue( $this->user_id, self::CLIENT_ID, 'After', 'mcp:read', 'https://example.org/wp-json/mcp/publishio', $revoked_at + 1 );
		$this->assertIsArray( $same_second );
		$this->assertIsArray( $after );
		$this->assertNull( Token_Store::validate_access_token( $same_second['access_token'] ) );
		$this->assertNotNull( Token_Store::validate_access_token( $after['access_token'] ) );
		$this->assertSame( [ 'After' ], array_column( $this->rows(), 'client_name' ) );
	}

	/**
	 * Logging out everywhere needs the nonce and the right to edit the user.
	 */
	public function test_log_out_everywhere_checks_nonce_and_capability(): void {
		$tokens = $this->issue();

		// A valid nonce, from a user who can't edit the test user.
		self::log_out_everywhere( $this->user_id, 'author' );

		// A user who can, with a wrong nonce.
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		if ( is_multisite() ) {
			grant_super_admin( $admin );
		}

		wp_set_current_user( $admin );
		$_POST['nonce'] = 'invalid';
		do_action( 'wp_ajax_destroy-sessions' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		$this->assertNotNull( Token_Store::validate_access_token( $tokens['access_token'] ) );
	}

	/**
	 * A wp_update_user() that fails after firing wp_set_password leaves the password, and the tokens, as they were.
	 */
	public function test_failed_profile_update_keeps_tokens(): void {
		$tokens = $this->issue();

		$result = wp_update_user(
			[
				'ID'         => $this->user_id,
				'user_pass'  => 'new-password',
				'user_email' => get_userdata( self::factory()->user->create() )->user_email,
			]
		);

		$this->assertWPError( $result );
		$this->assertNotNull( Token_Store::validate_access_token( $tokens['access_token'] ) );
	}

	/**
	 * Creating a user removes tokens left under its ID, for example by a user deleted while the plugin was inactive.
	 */
	public function test_new_user_removes_leftover_tokens(): void {
		$next_id = $this->user_id + 1;
		$this->assertIsArray( Token_Store::issue( $next_id, self::CLIENT_ID, 'Leftover', 'mcp:read', 'https://example.org/wp-json/mcp/publishio' ) );

		$this->assertSame( $next_id, self::factory()->user->create() );

		$this->assertSame( [], $this->rows() );
	}

	/**
	 * Setting a password outside a login, including every new user's, runs no extra password hash check.
	 */
	public function test_password_change_skips_hash_check(): void {
		$checks = 0;
		add_filter(
			'check_password',
			static function ( $check ) use ( &$checks ) {
				++$checks;
				return $check;
			}
		);
		$this->issue();

		self::factory()->user->create();
		wp_set_password( 'new-password', $this->user_id );

		$this->assertSame( 0, $checks );
		$this->assertSame( [], $this->rows() );
	}

	/**
	 * Logging in re-hashes an outdated hash of the unchanged password through wp_set_password(), which keeps the tokens.
	 */
	public function test_login_rehash_keeps_tokens(): void {
		global $wpdb;

		require_once ABSPATH . WPINC . '/class-phpass.php';

		// An old phpass hash, as users who haven't logged in since WordPress 6.8 still have.
		$wpdb->update( $wpdb->users, [ 'user_pass' => ( new \PasswordHash( 8, true ) )->HashPassword( 'password' ) ], [ 'ID' => $this->user_id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
		clean_user_cache( $this->user_id );
		$tokens       = $this->issue();
		$set_password = did_action( 'wp_set_password' );

		$user = wp_authenticate( get_userdata( $this->user_id )->user_login, 'password' );

		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertSame( $set_password + 1, did_action( 'wp_set_password' ) );
		$this->assertStringStartsWith( '$wp', get_userdata( $this->user_id )->user_pass );
		$this->assertNotNull( Token_Store::validate_access_token( $tokens['access_token'] ) );
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
	 * Rows approved before the user's last revocation leave the list and its count, as they stay behind on other sites of a network. A row approved after it is listed.
	 */
	public function test_connections_leave_out_rows_approved_before_revocation(): void {
		$this->issue();
		$revoked_at = time();
		update_user_meta( $this->user_id, 'publishio_tokens_revoked_at', $revoked_at );

		$this->assertSame( 0, Token_Store::count_connections( time() ) );
		$this->assertSame( [], Token_Store::get_connections( 0, 10, time() ) );

		Token_Store::issue( $this->user_id, self::CLIENT_ID, 'After', 'mcp:read', 'https://example.org/wp-json/mcp/publishio', $revoked_at + 1 );

		$this->assertSame( 1, Token_Store::count_connections( time() ) );
		$this->assertSame( [ 'After' ], array_column( Token_Store::get_connections( 0, 10, time() ), 'client_name' ) );
	}

	/**
	 * A duplicated revocation meta row doesn't list or count a connection twice.
	 */
	public function test_duplicate_revocation_meta_counts_once(): void {
		$this->issue();
		add_user_meta( $this->user_id, 'publishio_tokens_revoked_at', 1 );
		add_user_meta( $this->user_id, 'publishio_tokens_revoked_at', 1 );

		$this->assertSame( 1, Token_Store::count_connections( time() ) );
		$this->assertCount( 1, Token_Store::get_connections( 0, 10, time() ) );
	}

	/**
	 * Removing a user from a site deletes their tokens there, and nowhere else.
	 *
	 * @param string $domain Site domain; the network's when empty.
	 * @param string $path   Site path.
	 */
	#[DataProvider( 'site_shapes' )]
	public function test_removing_user_from_site_deletes_tokens_there_only( string $domain, string $path ): void {
		$this->skipWithoutMultisite();

		$main_tokens = $this->issue();
		$site_id     = $this->create_site_with_publishio( $domain, $path );
		add_user_to_blog( $site_id, $this->user_id, 'editor' );

		switch_to_blog( $site_id );
		$this->issue();
		restore_current_blog();

		remove_user_from_blog( $this->user_id, $site_id );

		switch_to_blog( $site_id );
		$site_rows = $this->rows();
		restore_current_blog();

		$this->assertSame( [], $site_rows );
		$this->assertNotNull( Token_Store::validate_access_token( $main_tokens['access_token'] ) );
	}

	/**
	 * Removing a user from a site that never ran Publishio doesn't touch a token table, which doesn't exist there.
	 */
	public function test_removing_user_from_site_without_publishio_queries_no_table(): void {
		$this->skipWithoutMultisite();

		$site_id = self::factory()->blog->create( [ 'path' => '/site3/' ] );
		add_user_to_blog( $site_id, $this->user_id, 'editor' );

		$queries = [];
		add_filter(
			'query',
			static function ( string $query ) use ( &$queries ): string {
				if ( str_contains( $query, 'publishio_oauth_tokens' ) ) {
					$queries[] = $query;
				}

				return $query;
			}
		);

		remove_user_from_blog( $this->user_id, $site_id );

		$this->assertSame( [], $queries );
	}

	/**
	 * Deleting a site drops its token table along with core's tables.
	 */
	public function test_site_table_dropped_with_site(): void {
		global $wpdb;

		$this->skipWithoutMultisite();

		$prefix = $wpdb->get_blog_prefix( 7 );
		$tables = apply_filters( 'wpmu_drop_tables', [ 'posts' => $prefix . 'posts' ], 7 );

		$this->assertSame(
			[
				'posts' => $prefix . 'posts',
				$prefix . 'publishio_oauth_tokens',
			],
			$tables
		);
	}

	/**
	 * Marking a user as spam ends their connections on every site.
	 */
	public function test_marking_user_as_spam_ends_connections(): void {
		$this->skipWithoutMultisite();

		$tokens = $this->issue();

		wp_update_user(
			[
				'ID'   => $this->user_id,
				'spam' => '1',
			]
		);

		$this->assertNull( Token_Store::validate_access_token( $tokens['access_token'] ) );
		$this->assertNull( Token_Store::refresh( $tokens['refresh_token'], self::CLIENT_ID ) );
		$this->assertGreaterThan( 0, (int) get_user_meta( $this->user_id, 'publishio_tokens_revoked_at', true ) );
	}

	/**
	 * A spam flag set without the make_spam_user action, directly in the database, blocks the user's tokens too.
	 */
	public function test_spam_flag_blocks_tokens_without_the_action(): void {
		global $wpdb;

		$this->skipWithoutMultisite();

		$tokens = $this->issue();
		$wpdb->update( $wpdb->users, [ 'spam' => 1 ], [ 'ID' => $this->user_id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
		clean_user_cache( $this->user_id );

		$this->assertSame( 0, Token_Store::count_connections( time() ) );
		$this->assertSame( [], Token_Store::get_connections( 0, 10, time() ) );
		$this->assertNull( Token_Store::validate_access_token( $tokens['access_token'] ) );
		$this->assertNull( Token_Store::refresh( $tokens['refresh_token'], self::CLIENT_ID ) );
	}

	/**
	 * The spam check is about the token's owner, never the logged-in user, even when the owner no longer exists.
	 */
	public function test_spam_check_ignores_the_logged_in_user(): void {
		global $wpdb;

		$this->skipWithoutMultisite();

		$spammer = self::factory()->user->create();
		$wpdb->update( $wpdb->users, [ 'spam' => 1 ], [ 'ID' => $spammer ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
		clean_user_cache( $spammer );
		wp_set_current_user( $spammer );

		$this->assertFalse( Token_Store::is_revoked( 999999, time() ) );
		$this->assertFalse( Token_Store::is_revoked( $this->user_id, time() ) );
	}

	/**
	 * A site in a subdirectory of the network's domain, and one on its own subdomain.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function site_shapes(): array {
		return [
			'subdirectory' => [ '', '/site2/' ],
			'subdomain'    => [ 'site2.example.org', '/' ],
		];
	}

	/**
	 * A site with Publishio's token table, as Publishio sets one up on its first request there.
	 *
	 * @param string $domain Site domain; the network's when empty.
	 * @param string $path   Site path.
	 */
	private function create_site_with_publishio( string $domain, string $path ): int {
		$site_id = self::factory()->blog->create(
			[
				'domain' => '' === $domain ? get_network()->domain : $domain,
				'path'   => $path,
			]
		);

		switch_to_blog( $site_id );
		Token_Store::create_table();
		update_option( 'publishio_version', PUBLISHIO_VERSION );
		restore_current_blog();

		return $site_id;
	}

	/**
	 * Click "Log Out Everywhere" on a user's profile as a new user with this role. On multisite an administrator is a super admin, since only they can edit other users.
	 *
	 * @param int    $user_id The user to log out.
	 * @param string $role    Role of the user clicking.
	 */
	private static function log_out_everywhere( int $user_id, string $role ): void {
		$clicker = self::factory()->user->create( [ 'role' => $role ] );

		if ( is_multisite() && 'administrator' === $role ) {
			grant_super_admin( $clicker );
		}

		wp_set_current_user( $clicker );
		$_POST['user_id'] = (string) $user_id;
		$_POST['nonce']   = wp_create_nonce( 'update-user_' . $user_id );

		// Core's handler is registered only by admin-ajax.php.
		do_action( 'wp_ajax_destroy-sessions' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
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
