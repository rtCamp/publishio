<?php
/**
 * Access and refresh token storage using a custom database table.
 *
 * Tokens are stored in {prefix}rtpwai_oauth_tokens and looked up by
 * hash for fast, indexed access.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage;

use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Config;

/**
 * Class - Token_Store
 */
class Token_Store {
	/**
	 * Get the custom table name.
	 */
	private static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'rtpwai_oauth_tokens';
	}

	/**
	 * Create the custom token table using dbDelta.
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id            BIGINT UNSIGNED NOT NULL,
			client_id          VARCHAR(255)    NOT NULL,
			access_token_hash  VARCHAR(64)     NOT NULL,
			refresh_token_hash VARCHAR(64)     NOT NULL,
			scope              VARCHAR(500)    NOT NULL DEFAULT '',
			resource           VARCHAR(2048)   NOT NULL DEFAULT '',
			access_expires_at  INT UNSIGNED    NOT NULL,
			refresh_expires_at INT UNSIGNED    NOT NULL,
			created_at         INT UNSIGNED    NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY access_token_hash (access_token_hash),
			UNIQUE KEY refresh_token_hash (refresh_token_hash),
			KEY user_refresh (user_id, refresh_expires_at),
			KEY client_refresh (client_id, refresh_expires_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Issue a new access token and refresh token for a user.
	 *
	 * @param int    $user_id            The WordPress user ID.
	 * @param string $client_id          The OAuth client ID.
	 * @param string $scope              The granted scope.
	 * @param string $resource_indicator The resource the token is bound to.
	 *
	 * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, scope: string}|null
	 */
	public static function issue( int $user_id, string $client_id, string $scope, string $resource_indicator ): ?array {
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
				'access_token_hash'  => wp_hash( $access_token, 'auth', 'sha256' ),
				'refresh_token_hash' => wp_hash( $refresh_token, 'auth', 'sha256' ),
				'scope'              => $scope,
				'resource'           => $resource_indicator,
				'access_expires_at'  => $now + Config::get_access_token_ttl(),
				'refresh_expires_at' => $now + Config::get_refresh_token_ttl(),
				'created_at'         => $now,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' ]
		);

		if ( false === $inserted ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'publish-with-ai: Token_Store::issue() DB insert failed — ' . $wpdb->last_error );
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
	 * Exchange a refresh token for a new token pair.
	 *
	 * Rotates the refresh token (old one becomes invalid).
	 *
	 * @param string $refresh_token The plain-text refresh token.
	 * @param string $client_id     The client ID (must match).
	 *
	 * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, scope: string}|false|null
	 */
	public static function refresh( string $refresh_token, string $client_id ): array|false|null {
		global $wpdb;

		$hash = wp_hash( $refresh_token, 'auth', 'sha256' );
		$now  = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, user_id, client_id, scope, resource, refresh_expires_at FROM %i WHERE refresh_token_hash = %s',
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

		// Issue new tokens first; only remove the old row if issuance succeeds.
		// Wrap in a transaction so a failure between INSERT and DELETE does not leave both tokens valid.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );

		$new_tokens = self::issue( (int) $row['user_id'], $client_id, $row['scope'], $row['resource'] );
		if ( null === $new_tokens ) {
			// DB failure — old token is still valid; signal server_error to caller.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			self::table_name(),
			[ 'id' => (int) $row['id'] ],
			[ '%d' ]
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'COMMIT' );

		return $new_tokens;
	}

	/**
	 * Delete all tokens for a client across all users. Used when a client is deleted.
	 *
	 * @param string $client_id The OAuth client ID.
	 *
	 * @return int|false Number of rows deleted.
	 */
	public static function delete_all_for_client( string $client_id ): int|false {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->delete( self::table_name(), [ 'client_id' => $client_id ], [ '%s' ] );
	}

	/**
	 * Revoke all tokens for a specific client and user.
	 *
	 * @param int    $user_id   The WordPress user ID.
	 * @param string $client_id The OAuth client ID.
	 */
	public static function revoke_for_client( int $user_id, string $client_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			self::table_name(),
			[
				'user_id'   => $user_id,
				'client_id' => $client_id,
			],
			[ '%d', '%s' ]
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
	 * Get all active token records for a user (for profile page display).
	 *
	 * @param int $user_id The WordPress user ID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_active_for_user( int $user_id ): array {
		global $wpdb;

		$now = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT client_id, scope, resource, created_at, refresh_expires_at FROM %i WHERE user_id = %d AND refresh_expires_at > %d ORDER BY created_at DESC',
				self::table_name(),
				$user_id,
				$now
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Get active user IDs and latest token time grouped by client_id.
	 *
	 * Single query returning both the distinct user IDs and MAX(created_at) per
	 * client. Only rows where the refresh token is still valid are included.
	 *
	 * @param string[] $client_ids OAuth client IDs to look up.
	 *
	 * @return array<string, array{user_ids: int[], last_active_at: int}> Map of client_id => data.
	 */
	public static function get_client_token_data( array $client_ids ): array {
		if ( empty( $client_ids ) ) {
			return [];
		}

		global $wpdb;

		$now = time();
		$sql = sprintf(
			'SELECT client_id, GROUP_CONCAT(DISTINCT user_id) AS user_ids, MAX(created_at) AS last_active_at FROM %%i WHERE client_id IN (%s) AND refresh_expires_at > %%d GROUP BY client_id',
			implode( ', ', array_fill( 0, count( $client_ids ), '%s' ) )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, array_merge( [ self::table_name() ], $client_ids, [ $now ] ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$result = [];
		foreach ( $rows as $row ) {
			$result[ $row['client_id'] ] = [
				'user_ids'       => array_map( 'intval', explode( ',', $row['user_ids'] ) ),
				'last_active_at' => (int) $row['last_active_at'],
			];
		}

		return $result;
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
