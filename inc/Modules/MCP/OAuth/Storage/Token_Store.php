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
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'deleted_user', [ self::class, 'revoke_all' ] );
	}

	/**
	 * Get the custom table name.
	 */
	private static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'publishio_oauth_tokens';
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

		$access_token  = wp_generate_password( 64, false );
		$refresh_token = wp_generate_password( 64, false );
		$now           = time();

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
				'access_expires_at'  => $now + Config::get_access_token_ttl(),
				'refresh_expires_at' => $now + Config::get_refresh_token_ttl(),
				'authorized_at'      => $authorized_at ?: $now,
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
			'expires_in'    => Config::get_access_token_ttl(),
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
				'SELECT user_id, client_id, scope, resource, access_expires_at FROM %i WHERE access_token_hash = %s',
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
	 * Count tokens with an unexpired refresh token. Tokens of deleted users are left out.
	 *
	 * @param int $now Current Unix timestamp.
	 */
	public static function count_connections( int $now ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i t INNER JOIN %i u ON u.ID = t.user_id WHERE t.refresh_expires_at > %d',
				self::table_name(),
				$wpdb->users, // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users -- Primary-key join that leaves out tokens of deleted users.
				$now
			)
		);

		return (int) $count;
	}

	/**
	 * A page of tokens with an unexpired refresh token, sorted by last activity. Each token is one connection. Tokens of deleted users are left out.
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// $direction is one of two fixed strings; prepare() has no placeholder for a sort direction.
				"SELECT t.id, t.client_id, t.client_name, t.user_id, t.authorized_at, t.created_at FROM %i t INNER JOIN %i u ON u.ID = t.user_id WHERE t.refresh_expires_at > %d ORDER BY t.created_at {$direction}, t.id {$direction} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::table_name(),
				$wpdb->users, // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users -- Primary-key join that leaves out tokens of deleted users.
				$now,
				$limit,
				$offset
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
	 * Delete expired token rows for a user.
	 *
	 * @param int $user_id The WordPress user ID.
	 */
	private static function prune_expired_for_user( int $user_id ): void {
		global $wpdb;

		$now = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE user_id = %d AND refresh_expires_at < %d',
				self::table_name(),
				$user_id,
				$now
			)
		);
	}
}
