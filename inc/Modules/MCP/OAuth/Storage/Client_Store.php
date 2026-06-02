<?php
/**
 * Storage for OAuth clients.
 *
 * Clients registered via the /register endpoint are stored here.
 * Supports multiple clients and distinguishes public (PKCE-only) from confidential ones.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage;

/**
 * Class - Client_Store
 */
class Client_Store {
	private const TABLE_SUFFIX = 'rtpwai_oauth_clients';
	public const PAGE_SIZE     = 10;

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
			source             VARCHAR(20)     NOT NULL DEFAULT 'dcr',
			client_secret_hash VARCHAR(255)    NULL DEFAULT NULL,
			redirect_uris      TEXT            NOT NULL,
			client_name        VARCHAR(255)    NOT NULL DEFAULT '',
			grant_types        VARCHAR(500)    NOT NULL DEFAULT 'authorization_code',
			response_types     VARCHAR(500)    NOT NULL DEFAULT 'code',
			scope              VARCHAR(500)    NOT NULL DEFAULT '',
			client_uri         VARCHAR(2048)   NULL DEFAULT NULL,
			logo_uri           VARCHAR(2048)   NULL DEFAULT NULL,
			tos_uri            VARCHAR(2048)   NULL DEFAULT NULL,
			policy_uri         VARCHAR(2048)   NULL DEFAULT NULL,
			contacts           TEXT            NULL DEFAULT NULL,
			software_id        VARCHAR(255)    NULL DEFAULT NULL,
			software_version   VARCHAR(255)    NULL DEFAULT NULL,
			registered_at      INT UNSIGNED    NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY client_id (client_id),
			KEY source (source)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Register a new client and return its generated client_id.
	 *
	 * @param array<string, mixed> $data Client metadata (redirect_uris, client_name, grant_types, response_types, scope, source, client_secret_hash).
	 *
	 * @return string|null The generated client_id, or null on failure.
	 */
	public static function register( array $data ): ?string {
		global $wpdb;

		$source    = (string) ( $data['source'] ?? 'dcr' );
		$prefix    = 'cred' === $source ? 'cred_' : 'dcr_';
		$client_id = $prefix . wp_generate_password( 32, false );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			self::table_name(),
			[
				'client_id'          => $client_id,
				'source'             => $source,
				'client_secret_hash' => $data['client_secret_hash'] ?? null,
				'redirect_uris'      => wp_json_encode( $data['redirect_uris'] ),
				'client_name'        => $data['client_name'] ?? '',
				'grant_types'        => implode( ' ', $data['grant_types'] ?? [ 'authorization_code' ] ),
				'response_types'     => implode( ' ', $data['response_types'] ?? [ 'code' ] ),
				'scope'              => $data['scope'] ?? '',
				'client_uri'         => $data['client_uri'] ?? null,
				'logo_uri'           => $data['logo_uri'] ?? null,
				'tos_uri'            => $data['tos_uri'] ?? null,
				'policy_uri'         => $data['policy_uri'] ?? null,
				'contacts'           => isset( $data['contacts'] ) ? wp_json_encode( $data['contacts'] ) : null,
				'software_id'        => $data['software_id'] ?? null,
				'software_version'   => $data['software_version'] ?? null,
				'registered_at'      => time(),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ]
		);

		if ( false === $result ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'publish-with-ai: Client_Store::register() DB insert failed — ' . $wpdb->last_error );
			return null;
		}

		return $client_id;
	}

	/**
	 * Look up a client by its client_id.
	 *
	 * @param string $client_id The client ID.
	 *
	 * @return array{
	 *   id: int,
	 *   client_id: string,
	 *   source: string,
	 *   is_public: bool,
	 *   client_secret_hash: string|null,
	 *   redirect_uris: array<string>,
	 *   client_name: string,
	 *   grant_types: string,
	 *   response_types: string,
	 *   scope: string,
	 *   registered_at: int,
	 * }|null
	 */
	public static function get_by_client_id( string $client_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE client_id = %s',
				self::table_name(),
				$client_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		return self::parse_row( $row, true );
	}

	/**
	 * Update mutable fields of an existing client.
	 *
	 * Immutable fields (client_id, client_secret_hash, registered_at) are ignored.
	 *
	 * @param int                  $id   The DB primary key.
	 * @param array<string, mixed> $data Fields to update.
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$fields  = [];
		$formats = [];

		if ( isset( $data['client_name'] ) ) {
			$fields['client_name'] = sanitize_text_field( (string) $data['client_name'] );
			$formats[]             = '%s';
		}

		if ( isset( $data['redirect_uris'] ) && is_array( $data['redirect_uris'] ) ) {
			$fields['redirect_uris'] = wp_json_encode( array_map( 'esc_url_raw', $data['redirect_uris'] ) );
			$formats[]               = '%s';
		}

		if ( isset( $data['grant_types'] ) && is_array( $data['grant_types'] ) ) {
			$fields['grant_types'] = implode( ' ', array_map( 'sanitize_key', $data['grant_types'] ) );
			$formats[]             = '%s';
		}

		if ( isset( $data['scope'] ) ) {
			$fields['scope'] = sanitize_text_field( (string) $data['scope'] );
			$formats[]       = '%s';
		}

		foreach ( [ 'client_uri', 'logo_uri', 'tos_uri', 'policy_uri' ] as $uri_field ) {
			if ( array_key_exists( $uri_field, $data ) ) {
				$fields[ $uri_field ] = ! empty( $data[ $uri_field ] ) ? esc_url_raw( (string) $data[ $uri_field ] ) : null;
				$formats[]            = '%s';
			}
		}

		if ( array_key_exists( 'contacts', $data ) ) {
			$fields['contacts'] = is_array( $data['contacts'] ) && ! empty( $data['contacts'] )
				? wp_json_encode( $data['contacts'] )
				: null;
			$formats[]          = '%s';
		}

		foreach ( [ 'software_id', 'software_version' ] as $str_field ) {
			if ( array_key_exists( $str_field, $data ) ) {
				$fields[ $str_field ] = ! empty( $data[ $str_field ] ) ? sanitize_text_field( (string) $data[ $str_field ] ) : null;
				$formats[]            = '%s';
			}
		}

		if ( empty( $fields ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update( self::table_name(), $fields, [ 'id' => $id ], $formats, [ '%d' ] );

		return false !== $result;
	}

	/**
	 * Delete a client by its client_id.
	 *
	 * @param string $client_id The client ID.
	 */
	public static function delete_by_client_id( string $client_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( self::table_name(), [ 'client_id' => $client_id ], [ '%s' ] );

		return false !== $result && $result > 0;
	}

	/**
	 * Normalise a raw DB row into consistent PHP types.
	 *
	 * @param array<string, mixed> $row              Raw row from wpdb.
	 * @param bool                 $keep_secret_hash Preserve client_secret_hash (needed internally for auth).
	 *
	 * @return array{
	 *   id: int,
	 *   client_id: string,
	 *   source: string,
	 *   is_public: bool,
	 *   client_secret_hash: string|null,
	 *   redirect_uris: array<string>,
	 *   client_name: string,
	 *   grant_types: string,
	 *   response_types: string,
	 *   scope: string,
	 *   client_uri: string|null,
	 *   logo_uri: string|null,
	 *   tos_uri: string|null,
	 *   policy_uri: string|null,
	 *   contacts: array<string>,
	 *   software_id: string|null,
	 *   software_version: string|null,
	 *   registered_at: int,
	 * }
	 */
	private static function parse_row( array $row, bool $keep_secret_hash = false ): array {
		$has_secret = ! empty( $row['client_secret_hash'] );

		return [
			'id'                 => (int) $row['id'],
			'client_id'          => (string) $row['client_id'],
			'source'             => (string) ( $row['source'] ?? 'dcr' ),
			'is_public'          => ! $has_secret,
			'client_secret_hash' => $keep_secret_hash && $has_secret ? (string) $row['client_secret_hash'] : null,
			'redirect_uris'      => json_decode( (string) $row['redirect_uris'], true ) ?? [],
			'client_name'        => (string) $row['client_name'],
			'grant_types'        => (string) $row['grant_types'],
			'response_types'     => (string) $row['response_types'],
			'scope'              => (string) $row['scope'],
			'client_uri'         => ! empty( $row['client_uri'] ) ? (string) $row['client_uri'] : null,
			'logo_uri'           => ! empty( $row['logo_uri'] ) ? (string) $row['logo_uri'] : null,
			'tos_uri'            => ! empty( $row['tos_uri'] ) ? (string) $row['tos_uri'] : null,
			'policy_uri'         => ! empty( $row['policy_uri'] ) ? (string) $row['policy_uri'] : null,
			'contacts'           => ! empty( $row['contacts'] ) ? ( json_decode( (string) $row['contacts'], true ) ?? [] ) : [],
			'software_id'        => ! empty( $row['software_id'] ) ? (string) $row['software_id'] : null,
			'software_version'   => ! empty( $row['software_version'] ) ? (string) $row['software_version'] : null,
			'registered_at'      => (int) $row['registered_at'],
		];
	}
}
