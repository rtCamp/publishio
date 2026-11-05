<?php
/**
 * Access and refresh token storage using a custom database table.
 *
 * Tokens are stored in {prefix}publishio_oauth_tokens and looked up by
 * hash for fast, indexed access.
 *
 * @package rtCamp\Publishio\Modules\MCP\OAuth\Storage
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\OAuth\Storage;

use rtCamp\Publishio\Framework\Contracts\Interfaces\Registrable;
use rtCamp\Publishio\Modules\MCP\OAuth\Config;

/**
 * Class - Token_Store
 */
class Token_Store implements Registrable {
	/**
	 * User meta holding when the user's tokens were last revoked. Anything issued before it is rejected.
	 */
	private const REVOKED_AT_META = 'publishio_tokens_revoked_at';

	/**
	 * User meta holding a fingerprint of the user's password hash, from when they last approved an app. A different hash means the password changed, perhaps on a site of the network where Publishio isn't loaded to see it.
	 */
	private const PASSWORD_META = 'publishio_password_fingerprint';

	/**
	 * Whether each user being added to a site, keyed "user:site", is joining it rather than already a member.
	 *
	 * @var array<string, bool>
	 */
	private static array $joining = [];

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'deleted_user', [ self::class, 'revoke_all' ] );
		add_action( 'user_register', [ self::class, 'revoke_all' ] );
		add_action( 'wp_set_password', [ self::class, 'revoke_on_set_password' ], 10, 3 );
		add_action( 'profile_update', [ self::class, 'revoke_on_profile_update' ], 10, 3 );
		// Core's handler runs at priority 1 and exits.
		add_action( 'wp_ajax_destroy-sessions', [ self::class, 'revoke_on_destroy_sessions' ], 0 );

		if ( is_multisite() ) {
			add_action( 'remove_user_from_blog', [ self::class, 'revoke_all_on_site' ], 10, 2 );
			add_filter( 'can_add_user_to_blog', [ self::class, 'note_join' ], 10, 4 );
			add_action( 'add_user_to_blog', [ self::class, 'revoke_on_join' ], 10, 3 );
			add_action( 'make_spam_user', [ self::class, 'revoke_on_spam' ] );
			add_filter( 'wpmu_drop_tables', [ self::class, 'drop_with_site' ], 10, 2 );
		}
	}

	/**
	 * Get the custom table name of the current site, or of another site on the network.
	 *
	 * @param int|null $site_id Site ID; the current site when null.
	 */
	private static function table_name( ?int $site_id = null ): string {
		global $wpdb;
		return $wpdb->get_blog_prefix( $site_id ) . 'publishio_oauth_tokens';
	}

	/**
	 * Create the custom token table using dbDelta.
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		/**
		 * Columns are written the way dbDelta compares them, or it alters them on every run:
		 * - one space between the name and the type;
		 * - integer display widths, which MariaDB and MySQL 5.7 report.
		 */
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			client_id VARCHAR(255) NOT NULL,
			client_name VARCHAR(100) NOT NULL DEFAULT '',
			access_token_hash VARCHAR(64) NOT NULL,
			refresh_token_hash VARCHAR(64) NOT NULL,
			scope VARCHAR(500) NOT NULL DEFAULT '',
			resource VARCHAR(2048) NOT NULL DEFAULT '',
			access_expires_at INT(10) UNSIGNED NOT NULL,
			refresh_expires_at INT(10) UNSIGNED NOT NULL,
			authorized_at INT(10) UNSIGNED NOT NULL DEFAULT 0,
			created_at INT(10) UNSIGNED NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY access_token_hash (access_token_hash),
			UNIQUE KEY refresh_token_hash (refresh_token_hash),
			KEY user_refresh (user_id, refresh_expires_at),
			KEY client_refresh (client_id, refresh_expires_at),
			KEY user_client (user_id, client_id),
			KEY client_user_refresh (client_id, user_id, refresh_expires_at),
			KEY refresh_expires_at (refresh_expires_at),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Issue a new access token and refresh token for a user.
	 *
	 * @param int    $user_id            The WordPress user ID.
	 * @param string $client_id          The OAuth client ID.
	 * @param string $client_name        The client name the user approved.
	 * @param string $scope              The granted scope.
	 * @param string $resource_indicator The resource the token is bound to.
	 * @param int    $authorized_at      When the user approved the client; 0 means now.
	 *
	 * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, scope: string}|null
	 */
	public static function issue( int $user_id, string $client_id, string $client_name, string $scope, string $resource_indicator, int $authorized_at = 0 ): ?array {
		global $wpdb;

		$access_token      = wp_generate_password( 64, false );
		$refresh_token     = wp_generate_password( 64, false );
		$now               = time();
		$authorized_at     = $authorized_at ?: $now;
		$ends_at           = self::connection_ends_at( $authorized_at );
		$access_expires_at = min( $now + Config::get_access_token_ttl(), $ends_at );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			self::table_name(),
			[
				'user_id'            => $user_id,
				'client_id'          => $client_id,
				'client_name'        => $client_name,
				'access_token_hash'  => wp_hash( $access_token, 'auth', 'sha256' ),
				'refresh_token_hash' => wp_hash( $refresh_token, 'auth', 'sha256' ),
				'scope'              => $scope,
				'resource'           => $resource_indicator,
				'access_expires_at'  => $access_expires_at,
				'refresh_expires_at' => min( $now + Config::get_refresh_token_ttl(), $ends_at ),
				'authorized_at'      => $authorized_at,
				'created_at'         => $now,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' ]
		);

		if ( false === $inserted ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'publishio: Token_Store::issue() DB insert failed — ' . $wpdb->last_error );
			return null;
		}

		// Prune expired rows for this user to keep the table tidy.
		self::prune_expired_for_user( $user_id );

		return [
			'access_token'  => $access_token,
			'refresh_token' => $refresh_token,
			'token_type'    => 'Bearer',
			'expires_in'    => $access_expires_at - $now,
			'scope'         => $scope,
		];
	}

	/**
	 * Look up a user by their access token.
	 *
	 * @param string $access_token The plain-text access token.
	 *
	 * @return array{user_id: int, client_id: string, scope: string, resource: string}|null
	 */
	public static function validate_access_token( string $access_token ): ?array {
		global $wpdb;

		$hash = wp_hash( $access_token, 'auth', 'sha256' );
		$now  = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT user_id, client_id, scope, resource, access_expires_at, authorized_at FROM %i WHERE access_token_hash = %s',
				self::table_name(),
				$hash
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		if ( (int) $row['access_expires_at'] < $now ) {
			return null; // Expired.
		}

		if ( self::has_ended( (int) $row['authorized_at'] ) ) {
			return null;
		}

		if ( self::is_revoked( (int) $row['user_id'], (int) $row['authorized_at'] ) ) {
			return null;
		}

		return [
			'user_id'   => (int) $row['user_id'],
			'client_id' => $row['client_id'],
			'scope'     => $row['scope'],
			'resource'  => $row['resource'],
		];
	}

	/**
	 * Exchange a refresh token for a new token pair. The old row is deleted and a new one is issued.
	 *
	 * The old row is deleted first, and new tokens are issued only if that delete removed it. One DELETE is atomic, so:
	 * - when two refreshes race with the same token, only one gets new tokens;
	 * - when a connection is removed during a refresh, the refresh fails instead of creating a new row.
	 *
	 * There is no transaction, so a failed insert after the delete leaves the app to reconnect.
	 *
	 * @param string $refresh_token The plain-text refresh token.
	 * @param string $client_id     The client ID (must match).
	 *
	 * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, scope: string}|false|null False on a database error; null when the refresh token is invalid, expired, already used or removed.
	 */
	public static function refresh( string $refresh_token, string $client_id ): array|false|null {
		global $wpdb;

		$hash = wp_hash( $refresh_token, 'auth', 'sha256' );
		$now  = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, user_id, client_id, client_name, scope, resource, refresh_expires_at, authorized_at FROM %i WHERE refresh_token_hash = %s',
				self::table_name(),
				$hash
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		if ( $row['client_id'] !== $client_id ) {
			return null; // Client mismatch.
		}

		if ( (int) $row['refresh_expires_at'] < $now ) {
			return null; // Expired.
		}

		// issue() already limits refresh_expires_at to the lifetime; this also ends connections when the lifetime is lowered later.
		if ( self::has_ended( (int) $row['authorized_at'] ) ) {
			return null;
		}

		if ( self::is_revoked( (int) $row['user_id'], (int) $row['authorized_at'] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete(
			self::table_name(),
			[
				'id'                 => (int) $row['id'],
				'refresh_token_hash' => $hash,
			],
			[ '%d', '%s' ]
		);

		if ( false === $deleted ) {
			return false;
		}

		if ( 1 !== $deleted ) {
			return null; // A concurrent refresh used this token first, or the connection was removed.
		}

		return self::issue( (int) $row['user_id'], $client_id, $row['client_name'], $row['scope'], $row['resource'], (int) $row['authorized_at'] ) ?? false;
	}

	/**
	 * Revoke one of a user's tokens.
	 *
	 * A token's id changes each time it is refreshed, so a stale id deletes nothing. Matching the user as well keeps a stale id from hitting another user's token if the id is ever reused.
	 *
	 * @param int $id      Token row ID.
	 * @param int $user_id The user the token must belong to.
	 *
	 * @return int|false Number of rows deleted, or false on DB error.
	 */
	public static function revoke( int $id, int $user_id ): int|false {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->delete(
			self::table_name(),
			[
				'id'      => $id,
				'user_id' => $user_id,
			],
			[ '%d', '%d' ]
		);
	}

	/**
	 * Count connections that still work: an unexpired refresh token, within the current lifetime, approved after the user's last revocation. Tokens of deleted users are left out.
	 *
	 * @param int $now Current Unix timestamp.
	 */
	public static function count_connections( int $now ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The spread fills live_connection()'s placeholders.
				'SELECT COUNT(*) FROM %i t INNER JOIN %i u ON u.ID = t.user_id WHERE ' . self::live_connection(), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed conditions with placeholders.
				self::table_name(),
				$wpdb->users, // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users -- Primary-key join that leaves out tokens of deleted users.
				...self::live_connection_args( $now )
			)
		);

		return (int) $count;
	}

	/**
	 * A page of connections that still work, as count_connections() counts them, sorted by last activity. Each token is one connection.
	 *
	 * @param int  $offset       Page offset (0-based).
	 * @param int  $limit        Number of rows to return.
	 * @param int  $now          Current Unix timestamp.
	 * @param bool $oldest_first Least recently active first instead of most recently active first.
	 *
	 * @return array<int, array{id: int, client_id: string, client_name: string, user_id: int, connected_at: int, last_active_at: int}>
	 */
	public static function get_connections( int $offset, int $limit, int $now, bool $oldest_first = false ): array {
		global $wpdb;

		$direction = $oldest_first ? 'ASC' : 'DESC';
		$args      = [ ...self::live_connection_args( $now ), $limit, $offset ];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The spread fills live_connection()'s placeholders.
				// $direction is one of two fixed strings; prepare() has no placeholder for a sort direction.
				'SELECT t.id, t.client_id, t.client_name, t.user_id, t.authorized_at, t.created_at FROM %i t INNER JOIN %i u ON u.ID = t.user_id WHERE ' . self::live_connection() . " ORDER BY t.created_at {$direction}, t.id {$direction} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
				self::table_name(),
				$wpdb->users, // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users -- Primary-key join that leaves out tokens of deleted users.
				...$args
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map(
			static fn ( $row ) => [
				'id'             => (int) $row['id'],
				'client_id'      => $row['client_id'],
				'client_name'    => $row['client_name'],
				'user_id'        => (int) $row['user_id'],
				'connected_at'   => (int) $row['authorized_at'],
				'last_active_at' => (int) $row['created_at'],
			],
			$rows
		);
	}

	/**
	 * Delete tokens issued to dynamically registered (DCR) clients, which no longer exist.
	 */
	public static function delete_dcr_tokens(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE client_id NOT LIKE %s',
				self::table_name(),
				$wpdb->esc_like( 'https://' ) . '%'
			)
		);
	}

	/**
	 * Revoke all tokens for a user.
	 *
	 * @param int $user_id The WordPress user ID.
	 */
	public static function revoke_all( int $user_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			self::table_name(),
			[ 'user_id' => $user_id ],
			[ '%d' ]
		);
	}

	/**
	 * Revoke a user's tokens on one site of the network, when they are removed from it.
	 *
	 * Publishio stores its version on a site when it creates the table there, so a site without it has no tokens.
	 *
	 * @param mixed $user_id The WordPress user ID.
	 * @param mixed $site_id The site the user is removed from.
	 */
	public static function revoke_all_on_site( mixed $user_id, mixed $site_id ): void {
		global $wpdb;

		$site_id = (int) $site_id;

		if ( false === get_blog_option( $site_id, 'publishio_version' ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			self::table_name( $site_id ),
			[ 'user_id' => (int) $user_id ],
			[ '%d' ]
		);
	}

	/**
	 * Note whether a user being added to a site is joining it, before core sets their role there. Core also adds current members, such as when their role changes.
	 *
	 * @param mixed $can_add Whether the user can be added, passed through.
	 * @param mixed $user_id The WordPress user ID.
	 * @param mixed $_role   The user's role on the site.
	 * @param mixed $site_id The site the user is added to.
	 */
	public static function note_join( mixed $can_add, mixed $user_id, mixed $_role, mixed $site_id ): mixed { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
		self::$joining[ (int) $user_id . ':' . (int) $site_id ] = ! is_user_member_of_blog( (int) $user_id, (int) $site_id );

		return $can_add;
	}

	/**
	 * Revoke a user's leftover tokens on a site they join or rejoin, so their apps need approving again there.
	 *
	 * Rows can outlive a removal: a code approved just before it can be exchanged after it, and network admin can run without Publishio. Once the user is back, those rows would work again. A super admin's connections on a site they weren't a member of are their own, so they stay.
	 *
	 * @param mixed $user_id The WordPress user ID.
	 * @param mixed $_role   The user's role on the site.
	 * @param mixed $site_id The site the user is added to.
	 */
	public static function revoke_on_join( mixed $user_id, mixed $_role, mixed $site_id ): void { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
		$key     = (int) $user_id . ':' . (int) $site_id;
		$joining = self::$joining[ $key ] ?? false;

		unset( self::$joining[ $key ] );

		if ( $joining && ! is_super_admin( (int) $user_id ) ) {
			self::revoke_all_on_site( $user_id, $site_id );
		}
	}

	/**
	 * Revoke all tokens for a user marked as spam, on every site of the network.
	 *
	 * @param mixed $user_id The WordPress user ID.
	 */
	public static function revoke_on_spam( mixed $user_id ): void {
		self::revoke_issued_until_now( (int) $user_id );
	}

	/**
	 * Drop a site's token table along with the site.
	 *
	 * @param mixed $tables  Tables core drops with the site.
	 * @param mixed $site_id The site being deleted.
	 *
	 * @return array<array-key, mixed>
	 */
	public static function drop_with_site( mixed $tables, mixed $site_id ): array {
		$tables   = is_array( $tables ) ? $tables : [];
		$tables[] = self::table_name( (int) $site_id );

		return $tables;
	}

	/**
	 * Revoke all of a user's tokens, and the authorization codes and refreshes still in flight.
	 *
	 * Deleting rows can't reach a pending code or a row a refresh inserts after the delete, so the time is recorded and anything issued before it is rejected.
	 *
	 * @param int $user_id The WordPress user ID.
	 */
	private static function revoke_issued_until_now( int $user_id ): void {
		update_user_meta( $user_id, self::REVOKED_AT_META, time() );
		self::revoke_all( $user_id );
		self::follow_password( $user_id );
	}

	/**
	 * Record the user's current password, as they approve an app.
	 *
	 * @param int $user_id The WordPress user ID.
	 */
	public static function remember_password( int $user_id ): void {
		update_user_meta( $user_id, self::PASSWORD_META, self::password_fingerprint( $user_id ) );
	}

	/**
	 * Keep the recorded password in step after a change Publishio saw, for a user who has one recorded.
	 *
	 * @param int $user_id The WordPress user ID.
	 */
	private static function follow_password( int $user_id ): void {
		if ( metadata_exists( 'user', $user_id, self::PASSWORD_META ) ) {
			self::remember_password( $user_id );
		}
	}

	/**
	 * A fingerprint of the user's password hash, or an empty string for a user who doesn't exist.
	 *
	 * @param int $user_id The WordPress user ID.
	 */
	private static function password_fingerprint( int $user_id ): string {
		$user = get_userdata( $user_id );

		return $user instanceof \WP_User ? wp_hash( $user->user_pass, 'auth', 'sha256' ) : '';
	}

	/**
	 * Revoke the user's tokens if their password changed since it was recorded.
	 *
	 * Catches a change made where Publishio wasn't loaded to revoke at the time, such as another site of a network. A login that only re-hashes the password there counts too, so those connections end; that is rare, and safe.
	 *
	 * @param int $user_id The WordPress user ID.
	 */
	private static function notice_password_change( int $user_id ): void {
		$recorded = (string) get_user_meta( $user_id, self::PASSWORD_META, true );
		$current  = self::password_fingerprint( $user_id );

		if ( '' === $current || $recorded === $current ) {
			return;
		}

		// Connections approved before the fingerprint existed start from the current password.
		if ( '' === $recorded ) {
			self::remember_password( $user_id );
			return;
		}

		self::revoke_issued_until_now( $user_id );
	}

	/**
	 * Whether a user's token or authorization code was revoked after it was issued, or the user is marked as spam on the network. A password change Publishio didn't see is revoked here first.
	 *
	 * The revocation's own second counts, or a script with the old password could mint codes just before it.
	 *
	 * @param int $user_id   The WordPress user ID.
	 * @param int $issued_at When the code was created or the connection approved.
	 */
	public static function is_revoked( int $user_id, int $issued_at ): bool {
		self::notice_password_change( $user_id );

		$revoked_at = (int) get_user_meta( $user_id, self::REVOKED_AT_META, true );

		if ( $revoked_at > 0 && $issued_at <= $revoked_at ) {
			return true;
		}

		// A spam flag set without the make_spam_user action, such as directly in the database, still blocks the user's apps.
		$user = is_multisite() ? get_userdata( $user_id ) : false;

		return $user instanceof \WP_User && is_user_spammy( $user );
	}

	/**
	 * Revoke all tokens for a user whose password was set, unless the save is still to come or a login only re-hashed the password.
	 *
	 * @param mixed $password      The new plain-text password.
	 * @param mixed $user_id       The WordPress user ID.
	 * @param mixed $old_user_data The user before the change. wp_set_password() passes false for a user that doesn't exist.
	 */
	public static function revoke_on_set_password(
		#[\SensitiveParameter]
		mixed $password,
		mixed $user_id,
		mixed $old_user_data = null
	): void {
		$user_id = (int) $user_id;
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$old_pass = $old_user_data instanceof \WP_User ? $old_user_data->user_pass : null;

		// wp_update_user() fires this before saving, and wp_insert_user() for a new user: profile_update and user_register handle those.
		if ( $user->user_pass === $old_pass ) {
			return;
		}

		// Logging in re-hashes an unchanged password whose hash is outdated. Only then is the password worth a second hash check.
		if ( null !== $old_pass && is_string( $password ) && doing_filter( 'authenticate' ) && wp_check_password( $password, $old_pass, $user_id ) ) {
			self::follow_password( $user_id );
			return;
		}

		self::revoke_issued_until_now( $user_id );
	}

	/**
	 * Revoke all tokens for a user whose password wp_update_user() saved.
	 *
	 * @param mixed $user_id       The WordPress user ID.
	 * @param mixed $old_user_data The user before the update.
	 * @param mixed $userdata      The data saved, with the password already hashed.
	 */
	public static function revoke_on_profile_update( mixed $user_id, mixed $old_user_data, mixed $userdata = [] ): void {
		if ( ! $old_user_data instanceof \WP_User || ! is_array( $userdata ) || empty( $userdata['user_pass'] ) || $userdata['user_pass'] === $old_user_data->user_pass ) {
			return;
		}

		self::revoke_issued_until_now( (int) $user_id );
	}

	/**
	 * Revoke all tokens for a user logged out everywhere from their profile screen.
	 *
	 * Runs before core's handler, so it repeats core's checks.
	 */
	public static function revoke_on_destroy_sessions(): void {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! $user_id || ! get_userdata( $user_id ) || ! current_user_can( 'edit_user', $user_id ) || ! wp_verify_nonce( $nonce, 'update-user_' . $user_id ) ) {
			return;
		}

		self::revoke_issued_until_now( $user_id );
	}

	/**
	 * When a connection approved at this time ends, or PHP_INT_MAX when connections don't end.
	 *
	 * @param int $authorized_at When the user approved the client.
	 */
	private static function connection_ends_at( int $authorized_at ): int {
		$lifetime = Config::get_max_connection_lifetime();

		return $lifetime > 0 ? $authorized_at + $lifetime : PHP_INT_MAX;
	}

	/**
	 * Whether a connection approved at this time has reached its lifetime.
	 *
	 * @param int $authorized_at When the user approved the client.
	 */
	public static function has_ended( int $authorized_at ): bool {
		return self::connection_ends_at( $authorized_at ) <= time();
	}

	/**
	 * When a connection stops working: when its refresh token expires, or sooner if the lifetime was lowered after it was issued.
	 *
	 * @param int $refresh_expires_at When its refresh token expires.
	 * @param int $authorized_at      When the user approved the client.
	 */
	public static function expires_at( int $refresh_expires_at, int $authorized_at ): int {
		return min( $refresh_expires_at, self::connection_ends_at( $authorized_at ) );
	}

	/**
	 * The earliest approval time of a connection that hasn't reached its lifetime.
	 *
	 * @param int $now Current Unix timestamp.
	 */
	private static function earliest_live_approval( int $now ): int {
		$lifetime = Config::get_max_connection_lifetime();

		return $lifetime > 0 ? max( 0, $now - $lifetime + 1 ) : 0;
	}

	/**
	 * Conditions on the token table `t`, joined to users as `u`, for a connection that still works: an unexpired refresh token, within the lifetime, approved after the user's last revocation, and on multisite a user not marked as spam (only networks have that column).
	 *
	 * The revocation time is network-wide user meta, while each site keeps its own rows, so other sites' rows are left in place and filtered here. NOT EXISTS, unlike a join, can't count a row twice if the meta is duplicated.
	 *
	 * Placeholders are filled by live_connection_args().
	 */
	private static function live_connection(): string {
		$conditions = 't.refresh_expires_at > %d AND t.authorized_at >= %d AND NOT EXISTS ( SELECT 1 FROM %i m WHERE m.user_id = t.user_id AND m.meta_key = %s AND CAST( m.meta_value AS UNSIGNED ) >= t.authorized_at )';

		return is_multisite() ? $conditions . ' AND u.spam = 0' : $conditions;
	}

	/**
	 * Values for the placeholders in live_connection().
	 *
	 * @param int $now Current Unix timestamp.
	 *
	 * @return array{int, int, string, string}
	 */
	private static function live_connection_args( int $now ): array {
		global $wpdb;

		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__usermeta -- Reads one key per row to leave out revoked connections.
		return [ $now, self::earliest_live_approval( $now ), $wpdb->usermeta, self::REVOKED_AT_META ];
	}

	/**
	 * All of a user's tokens, including expired ones, most recently active first (for the profile page).
	 *
	 * @param int $user_id The WordPress user ID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_for_user( int $user_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, client_id, client_name, scope, resource, authorized_at, created_at, refresh_expires_at FROM %i WHERE user_id = %d ORDER BY created_at DESC, id DESC',
				self::table_name(),
				$user_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Delete a user's token rows that can no longer be used: expired, past the lifetime, or approved before a revocation.
	 *
	 * Runs right after the insert in issue(), so a row that a refresh or code exchange inserts while racing a revocation is gone before the request ends.
	 *
	 * @param int $user_id The WordPress user ID.
	 */
	private static function prune_expired_for_user( int $user_id ): void {
		global $wpdb;

		$now = time();

		// is_revoked() may have cached the meta before another request revoked the tokens.
		wp_cache_delete( $user_id, 'user_meta' );
		$revoked_at = (int) get_user_meta( $user_id, self::REVOKED_AT_META, true );
		$live_from  = max( self::earliest_live_approval( $now ), $revoked_at > 0 ? $revoked_at + 1 : 0 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE user_id = %d AND ( refresh_expires_at < %d OR authorized_at < %d )',
				self::table_name(),
				$user_id,
				$now,
				$live_from
			)
		);
	}
}
