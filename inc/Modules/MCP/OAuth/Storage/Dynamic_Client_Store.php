<?php
/**
 * Storage for dynamically registered OAuth clients (RFC 7591).
 *
 * Clients registered via the /register endpoint are stored here.
 * Unlike the legacy single-client option, this table supports multiple
 * clients and distinguishes public (PKCE-only) from confidential ones.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage;

/**
 * Class - Dynamic_Client_Store
 */
class Dynamic_Client_Store {
	private const TABLE_SUFFIX = 'pwai_oauth_clients';

	/**
	 * Get the full table name.
	 */
	private static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create the clients table using dbDelta.
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			client_id          VARCHAR(255)    NOT NULL,
			is_public          TINYINT(1)      NOT NULL DEFAULT 1,
			client_secret_hash VARCHAR(255)    NULL DEFAULT NULL,
			redirect_uris      TEXT            NOT NULL,
			client_name        VARCHAR(255)    NOT NULL DEFAULT '',
			grant_types        VARCHAR(500)    NOT NULL DEFAULT 'authorization_code',
			response_types     VARCHAR(500)    NOT NULL DEFAULT 'code',
			scope              VARCHAR(500)    NOT NULL DEFAULT '',
			registered_at      INT UNSIGNED    NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY client_id (client_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Register a new dynamic client and return its generated client_id.
	 *
	 * @param array<string, mixed> $data Client metadata (is_public, redirect_uris, client_name, grant_types, response_types, scope).
	 *
	 * @return string|null The generated client_id, or null on failure.
	 */
	public static function register( array $data ): ?string {
		global $wpdb;

		$client_id = 'dyn_' . wp_generate_password( 32, false );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			self::table_name(),
			[
				'client_id'          => $client_id,
				'is_public'          => $data['is_public'] ? 1 : 0,
				'client_secret_hash' => $data['client_secret_hash'] ?? null,
				'redirect_uris'      => wp_json_encode( $data['redirect_uris'] ),
				'client_name'        => $data['client_name'] ?? '',
				'grant_types'        => implode( ' ', $data['grant_types'] ?? [ 'authorization_code' ] ),
				'response_types'     => implode( ' ', $data['response_types'] ?? [ 'code' ] ),
				'scope'              => $data['scope'] ?? '',
				'registered_at'      => time(),
			],
			[ '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ]
		);

		if ( false === $result ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'publish-with-ai: Dynamic_Client_Store::register() DB insert failed — ' . $wpdb->last_error );
			return null;
		}

		return $client_id;
	}

	/**
	 * Look up a dynamic client by its client_id.
	 *
	 * @param string $client_id The client ID.
	 *
	 * @return array{
	 *   client_id: string,
	 *   is_public: bool,
	 *   client_secret_hash: string|null,
	 *   redirect_uris: string[],
	 *   client_name: string,
	 *   grant_types: string,
	 *   response_types: string,
	 *   scope: string,
	 *   registered_at: int,
	 * }|null
	 */
	public static function get_by_client_id( string $client_id ): ?array {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE client_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$client_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$row['redirect_uris']      = json_decode( (string) $row['redirect_uris'], true ) ?? [];
		$row['is_public']          = (bool) $row['is_public'];
		$row['client_secret_hash'] = ! empty( $row['client_secret_hash'] ) ? $row['client_secret_hash'] : null;

		/** @var array{client_id: string, is_public: bool, client_secret_hash: string|null, redirect_uris: string[], client_name: string, grant_types: string, response_types: string, scope: string, registered_at: int} $row */
		return $row;
	}
}
