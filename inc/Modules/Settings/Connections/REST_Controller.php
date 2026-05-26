<?php
/**
 * REST API endpoints for OAuth connection management.
 *
 * Routes (all require manage_options):
 *   GET    /wp-json/rtpwai/v1/connections
 *   POST   /wp-json/rtpwai/v1/connections
 *   PUT    /wp-json/rtpwai/v1/connections/{id}
 *   DELETE /wp-json/rtpwai/v1/connections/{id}
 *
 * @package rtCamp\Publish_With_AI\Modules\Settings\Connections
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Settings\Connections;

use WP_REST_Response;
use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_REST_Controller;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Config;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage\Dynamic_Client_Store;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage\Token_Store;

/**
 * Class - REST_Controller
 */
class REST_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 */
	protected $rest_base = 'connections';

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
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => array_merge(
						[
							'id' => [
								'type'     => 'integer',
								'required' => true,
							],
						],
						$this->get_schema_args( false )
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
	 * GET /connections
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	public function get_items( $request ): WP_REST_Response { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
		$connections = Dynamic_Client_Store::all();

		if ( empty( $connections ) ) {
			return new WP_REST_Response( [], 200 );
		}

		// Query 1: all (client_id → user_id) pairs for these connections in one hit.
		$client_ids      = array_column( $connections, 'client_id' );
		$users_by_client = Token_Store::get_users_by_client_ids( $client_ids );

		// Collect all distinct user IDs across every connection.
		$all_user_ids = [];
		foreach ( $users_by_client as $user_ids ) {
			$all_user_ids = array_merge( $all_user_ids, $user_ids );
		}
		$all_user_ids = array_unique( $all_user_ids );

		// Query 2: batch-load all WP_User objects at once.
		$user_map = [];
		if ( ! empty( $all_user_ids ) ) {
			foreach ( get_users( [ 'include' => $all_user_ids ] ) as $user ) {
				$user_map[ $user->ID ] = [
					'id'             => $user->ID,
					'name'           => $user->display_name,
					'email'          => $user->user_email,
					'avatar_url'     => get_avatar_url( $user->ID, [ 'size' => 32 ] ),
					'admin_edit_url' => admin_url( 'user-edit.php?user_id=' . $user->ID ),
				];
			}
		}

		// Attach resolved users to each connection.
		foreach ( $connections as &$connection ) {
			$user_ids            = $users_by_client[ $connection['client_id'] ] ?? [];
			$connection['users'] = array_values(
				array_filter( array_map( static fn ( $uid ) => $user_map[ $uid ] ?? null, $user_ids ) )
			);
		}
		unset( $connection );

		return new WP_REST_Response( $connections, 200 );
	}

	/**
	 * POST /connections
	 *
	 * For confidential connections (is_public = false), a client_secret is generated,
	 * hashed for storage, and returned once in plain text.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$is_public     = (bool) $request->get_param( 'is_public' );
		$grant_types   = $this->resolve_grant_types( (array) $request->get_param( 'grant_types' ) );
		$scope         = $this->resolve_scope( (string) $request->get_param( 'scope' ) );
		$redirect_uris = array_filter( array_map( 'esc_url_raw', (array) $request->get_param( 'redirect_uris' ) ) );

		$secret      = null;
		$secret_hash = null;

		if ( ! $is_public ) {
			$secret      = wp_generate_password( 40, false );
			$secret_hash = wp_hash_password( $secret );
		}

		$client_id = Dynamic_Client_Store::register(
			[
				'client_name'        => sanitize_text_field( (string) $request->get_param( 'client_name' ) ),
				'redirect_uris'      => array_values( $redirect_uris ),
				'is_public'          => $is_public,
				'client_secret_hash' => $secret_hash,
				'grant_types'        => $grant_types,
				'response_types'     => [ 'code' ],
				'scope'              => $scope,
			]
		);

		if ( null === $client_id ) {
			return new \WP_Error( 'create_failed', __( 'Failed to create connection.', 'rtcamp-publish-with-ai' ), [ 'status' => 500 ] );
		}

		$client = Dynamic_Client_Store::get_by_client_id( $client_id );

		if ( null === $client ) {
			return new \WP_Error( 'create_failed', __( 'Failed to retrieve created connection.', 'rtcamp-publish-with-ai' ), [ 'status' => 500 ] );
		}

		unset( $client['client_secret_hash'] );

		if ( null !== $secret ) {
			$client['client_secret'] = $secret;
		}

		return new WP_REST_Response( $client, 201 );
	}

	/**
	 * PUT /connections/{id}
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( null === Dynamic_Client_Store::get_by_id( $id ) ) {
			return new \WP_Error( 'not_found', __( 'Connection not found.', 'rtcamp-publish-with-ai' ), [ 'status' => 404 ] );
		}

		$data = [];

		if ( null !== $request->get_param( 'client_name' ) ) {
			$data['client_name'] = $request->get_param( 'client_name' );
		}

		if ( null !== $request->get_param( 'redirect_uris' ) ) {
			$data['redirect_uris'] = array_values(
				array_filter( array_map( 'esc_url_raw', (array) $request->get_param( 'redirect_uris' ) ) )
			);
		}

		if ( null !== $request->get_param( 'grant_types' ) ) {
			$data['grant_types'] = $this->resolve_grant_types( (array) $request->get_param( 'grant_types' ) );
		}

		if ( null !== $request->get_param( 'scope' ) ) {
			$data['scope'] = $this->resolve_scope( (string) $request->get_param( 'scope' ) );
		}

		Dynamic_Client_Store::update( $id, $data );

		return new WP_REST_Response( Dynamic_Client_Store::get_by_id( $id ), 200 );
	}

	/**
	 * DELETE /connections/{id}
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$id      = (int) $request->get_param( 'id' );
		$deleted = Dynamic_Client_Store::delete( $id );

		if ( ! $deleted ) {
			return new \WP_Error( 'not_found', __( 'Connection not found.', 'rtcamp-publish-with-ai' ), [ 'status' => 404 ] );
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Only site admins may manage OAuth connections.
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Ensure 'authorization_code' is always present and optionally include 'refresh_token'.
	 *
	 * @param string[] $requested Requested grant types.
	 *
	 * @return string[]
	 */
	private function resolve_grant_types( array $requested ): array {
		$types = [ 'authorization_code' ];

		if ( in_array( 'refresh_token', $requested, true ) ) {
			$types[] = 'refresh_token';
		}

		return $types;
	}

	/**
	 * Filter scope to supported values only.
	 *
	 * @param string $scope Space-separated scope string.
	 */
	private function resolve_scope( string $scope ): string {
		$requested = array_filter( explode( ' ', trim( $scope ) ) );
		$allowed   = array_intersect( $requested, Config::SUPPORTED_SCOPES );
		return implode( ' ', $allowed );
	}

	/**
	 * Args schema for create (all required) and update (all optional).
	 *
	 * @param bool $required Whether fields are required (true for create, false for update).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_schema_args( bool $required = true ): array {
		return [
			'client_name'   => [
				'type'              => 'string',
				'required'          => $required,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'redirect_uris' => [
				'type'     => 'array',
				'required' => $required,
				'items'    => [ 'type' => 'string' ],
			],
			'is_public'     => [
				'type'    => 'boolean',
				'default' => true,
			],
			'scope'         => [
				'type'              => 'string',
				'default'           => implode( ' ', Config::SUPPORTED_SCOPES ),
				'sanitize_callback' => 'sanitize_text_field',
			],
			'grant_types'   => [
				'type'    => 'array',
				'default' => [ 'authorization_code' ],
				'items'   => [ 'type' => 'string' ],
			],
		];
	}
}
