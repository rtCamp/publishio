<?php
/**
 * REST API endpoints for admin-managed OAuth credential management.
 *
 * Credentials are manually created by site admins (client name + redirect URIs).
 * All credentials are confidential (client_secret). The secret is returned once
 * on creation and cannot be retrieved again.
 *
 * Deleting a credential revokes all associated OAuth tokens.
 *
 * Routes (all require manage_options):
 *   GET    /wp-json/rtpwai/v1/credentials
 *   POST   /wp-json/rtpwai/v1/credentials
 *   PATCH  /wp-json/rtpwai/v1/credentials/{id}
 *   DELETE /wp-json/rtpwai/v1/credentials/{id}
 *
 * @package rtCamp\Publish_With_AI\Modules\Settings\Credentials
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Settings\Credentials;

use WP_REST_Response;
use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_REST_Controller;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Config;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage\Client_Store;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage\Token_Store;

/**
 * Class - REST_Controller
 */
class REST_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 */
	protected $rest_base = 'credentials';

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		/** @var non-falsy-string $namespace */
		$namespace = $this->namespace . $this->version;

		register_rest_route(
			$namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => [
						'page' => [
							'type'              => 'integer',
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						],
					],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => $this->get_schema_args(),
				],
			]
		);

		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			[
				[
					'methods'             => 'PATCH',
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => array_merge(
						[
							'id'          => [
								'type'     => 'integer',
								'required' => true,
							],
							'client_name' => [
								'type'              => 'string',
								'required'          => true,
								'sanitize_callback' => 'sanitize_text_field',
							],
						],
						self::get_optional_metadata_args()
					),
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => [
						'id' => [
							'type'     => 'integer',
							'required' => true,
						],
					],
				],
			]
		);
	}

	/**
	 * GET /credentials — list admin-managed credentials (paginated, page size fixed at 10).
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	public function get_items( $request ): WP_REST_Response {
		$page   = max( 1, (int) $request->get_param( 'page' ) );
		$offset = ( $page - 1 ) * Client_Store::PAGE_SIZE;
		$total  = Client_Store::count_by_source( 'cred' );

		if ( 0 === $total ) {
			return new WP_REST_Response(
				[
					'items' => [],
					'total' => 0,
				],
				200
			);
		}

		$credentials = Client_Store::all_by_source( 'cred', $offset );

		if ( empty( $credentials ) ) {
			return new WP_REST_Response(
				[
					'items' => [],
					'total' => $total,
				],
				200
			);
		}

		$client_ids = array_column( $credentials, 'client_id' );
		$token_data = Token_Store::get_client_token_data( $client_ids );

		foreach ( $credentials as &$credential ) {
			$data                         = $token_data[ $credential['client_id'] ] ?? null;
			$credential['last_active_at'] = $data ? $data['last_active_at'] : null;
		}
		unset( $credential );

		return new WP_REST_Response(
			[
				'items' => $credentials,
				'total' => $total,
			],
			200
		);
	}

	/**
	 * POST /credentials — create a new confidential credential.
	 *
	 * The client_secret is generated, hashed for storage, and returned once.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$redirect_uris = array_values(
			array_filter( array_map( 'esc_url_raw', (array) $request->get_param( 'redirect_uris' ) ) )
		);

		if ( empty( $redirect_uris ) ) {
			return new \WP_Error( 'invalid_redirect_uris', __( 'At least one valid redirect URI is required.', 'rtcamp-publish-with-ai' ), [ 'status' => 400 ] );
		}

		$uri_error = $this->validate_redirect_uris( $redirect_uris );
		if ( null !== $uri_error ) {
			return $uri_error;
		}

		$client_name = sanitize_text_field( (string) $request->get_param( 'client_name' ) );

		if ( '' === $client_name ) {
			return new \WP_Error( 'invalid_client_name', __( 'Client name is required.', 'rtcamp-publish-with-ai' ), [ 'status' => 400 ] );
		}

		$secret      = wp_generate_password( 40, false );
		$secret_hash = wp_hash_password( $secret );

		$client_id = Client_Store::register(
			[
				'source'             => 'cred',
				'client_name'        => $client_name,
				'redirect_uris'      => $redirect_uris,
				'client_secret_hash' => $secret_hash,
				'grant_types'        => [ 'authorization_code', 'refresh_token' ],
				'response_types'     => [ 'code' ],
				'scope'              => implode( ' ', Config::SUPPORTED_SCOPES ),
				...$this->extract_optional_metadata( $request ),
			]
		);

		if ( null === $client_id ) {
			return new \WP_Error( 'create_failed', __( 'Failed to create credential.', 'rtcamp-publish-with-ai' ), [ 'status' => 500 ] );
		}

		$client = Client_Store::get_by_client_id( $client_id );

		if ( null === $client ) {
			return new \WP_Error( 'create_failed', __( 'Failed to retrieve created credential.', 'rtcamp-publish-with-ai' ), [ 'status' => 500 ] );
		}

		unset( $client['client_secret_hash'] );
		$client['client_secret']  = $secret;
		$client['last_active_at'] = null;

		return new WP_REST_Response( $client, 201 );
	}

	/**
	 * DELETE /credentials/{id} — delete a credential and all its tokens.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$id     = (int) $request->get_param( 'id' );
		$client = Client_Store::get_by_id( $id );

		if ( null === $client ) {
			return new \WP_Error( 'not_found', __( 'Credential not found.', 'rtcamp-publish-with-ai' ), [ 'status' => 404 ] );
		}

		$tokens_deleted = Token_Store::delete_all_for_client( $client['client_id'] );

		if ( false === $tokens_deleted ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete associated tokens.', 'rtcamp-publish-with-ai' ), [ 'status' => 500 ] );
		}

		$deleted = Client_Store::delete( $id );

		if ( ! $deleted ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete credential.', 'rtcamp-publish-with-ai' ), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'tokens_deleted' => $tokens_deleted ], 200 );
	}

	/**
	 * PATCH /credentials/{id} — update the client name of a credential.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$id     = (int) $request->get_param( 'id' );
		$client = Client_Store::get_by_id( $id );

		if ( null === $client || 'cred' !== $client['source'] ) {
			return new \WP_Error( 'not_found', __( 'Credential not found.', 'rtcamp-publish-with-ai' ), [ 'status' => 404 ] );
		}

		$client_name = sanitize_text_field( (string) $request->get_param( 'client_name' ) );

		if ( '' === $client_name ) {
			return new \WP_Error( 'invalid_client_name', __( 'Client name is required.', 'rtcamp-publish-with-ai' ), [ 'status' => 400 ] );
		}

		$updated = Client_Store::update( $id, array_merge( [ 'client_name' => $client_name ], $this->extract_optional_metadata( $request ) ) );

		if ( ! $updated ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update credential.', 'rtcamp-publish-with-ai' ), [ 'status' => 500 ] );
		}

		$client = Client_Store::get_by_id( $id );

		if ( null === $client ) {
			return new \WP_Error( 'update_failed', __( 'Failed to retrieve updated credential.', 'rtcamp-publish-with-ai' ), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( $client, 200 );
	}

	/**
	 * Extract and sanitize optional RFC 7591 metadata fields from a request.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return array<string, mixed>
	 */
	private function extract_optional_metadata( \WP_REST_Request $request ): array {
		$data = [];

		foreach ( [ 'client_uri', 'logo_uri', 'tos_uri', 'policy_uri' ] as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$sanitized      = esc_url_raw( (string) $value );
				$data[ $field ] = str_starts_with( $sanitized, 'https://' ) ? $sanitized : null;
			}
		}

		$contacts_raw = $request->get_param( 'contacts' );
		if ( null !== $contacts_raw ) {
			$list             = is_array( $contacts_raw ) ? $contacts_raw : array_filter( array_map( 'trim', explode( ',', (string) $contacts_raw ) ) );
			$data['contacts'] = array_values( array_slice( array_map( 'sanitize_text_field', $list ), 0, 10 ) );
		}

		foreach ( [ 'software_id', 'software_version' ] as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$sanitized      = sanitize_text_field( (string) $value );
				$data[ $field ] = '' !== $sanitized ? $sanitized : null;
			}
		}

		return $data;
	}

	/**
	 * Only site admins may manage credentials.
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Validate each redirect URI in the given array.
	 *
	 * Rules: must be a valid URL, scheme must be https (or http for localhost),
	 * and no fragment (#) is allowed (per OAuth 2.0 §3.1.2).
	 *
	 * @param string[] $uris Already-sanitized URIs.
	 *
	 * @return \WP_Error|null WP_Error on failure, null on success.
	 */
	private function validate_redirect_uris( array $uris ): ?\WP_Error {
		$invalid = [];

		foreach ( $uris as $uri ) {
			$parsed   = wp_parse_url( $uri );
			$scheme   = strtolower( $parsed['scheme'] ?? '' );
			$host     = $parsed['host'] ?? '';
			$fragment = $parsed['fragment'] ?? '';

			if ( ! empty( $fragment ) ) {
				$invalid[] = $uri;
				continue;
			}

			$is_localhost = in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true );
			$valid_scheme = 'https' === $scheme || ( 'http' === $scheme && $is_localhost );

			if ( ! $valid_scheme || empty( $host ) ) {
				$invalid[] = $uri;
			}
		}

		if ( ! empty( $invalid ) ) {
			return new \WP_Error(
				'invalid_redirect_uris',
				sprintf(
					/* translators: %s: comma-separated list of invalid URIs */
					__( 'Invalid redirect URI(s): %s. URIs must use https:// (or http:// for localhost only). Fragments (#) are not allowed.', 'rtcamp-publish-with-ai' ),
					implode( ', ', $invalid )
				),
				[ 'status' => 400 ]
			);
		}

		return null;
	}

	/**
	 * Args schema for the create endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_schema_args(): array {
		return array_merge(
			[
				'client_name'   => [
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'redirect_uris' => [
					'type'     => 'array',
					'required' => true,
					'items'    => [ 'type' => 'string' ],
				],
			],
			self::get_optional_metadata_args()
		);
	}

	/**
	 * Route arg definitions for optional RFC 7591 metadata fields.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_optional_metadata_args(): array {
		return [
			'client_uri'       => [
				'type'     => 'string',
				'required' => false,
			],
			'logo_uri'         => [
				'type'     => 'string',
				'required' => false,
			],
			'tos_uri'          => [
				'type'     => 'string',
				'required' => false,
			],
			'policy_uri'       => [
				'type'     => 'string',
				'required' => false,
			],
			'contacts'         => [
				'type'     => [ 'string', 'array' ],
				'required' => false,
			],
			'software_id'      => [
				'type'     => 'string',
				'required' => false,
			],
			'software_version' => [
				'type'     => 'string',
				'required' => false,
			],
		];
	}
}
