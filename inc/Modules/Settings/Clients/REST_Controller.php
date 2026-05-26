<?php
/**
 * REST API endpoints for OAuth client management.
 *
 * Routes (all require manage_options):
 *   GET    /wp-json/rtpwai/v1/clients
 *   POST   /wp-json/rtpwai/v1/clients
 *   PUT    /wp-json/rtpwai/v1/clients/{id}
 *   DELETE /wp-json/rtpwai/v1/clients/{id}
 *
 * @package rtCamp\Publish_With_AI\Modules\Settings\Clients
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Settings\Clients;

use WP_REST_Response;
use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_REST_Controller;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Config;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage\Dynamic_Client_Store;

/**
 * Class - REST_Controller
 */
class REST_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 */
	protected $rest_base = 'clients';

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
	 * GET /clients
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	public function get_items( $request ): WP_REST_Response { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
		return new WP_REST_Response( Dynamic_Client_Store::all(), 200 );
	}

	/**
	 * POST /clients
	 *
	 * For confidential clients (is_public = false), a client_secret is generated,
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
			return new \WP_Error( 'create_failed', __( 'Failed to create client.', 'rtcamp-publish-with-ai' ), [ 'status' => 500 ] );
		}

		$client = Dynamic_Client_Store::get_by_client_id( $client_id );

		if ( null === $client ) {
			return new \WP_Error( 'create_failed', __( 'Failed to retrieve created client.', 'rtcamp-publish-with-ai' ), [ 'status' => 500 ] );
		}

		unset( $client['client_secret_hash'] );

		if ( null !== $secret ) {
			$client['client_secret'] = $secret;
		}

		return new WP_REST_Response( $client, 201 );
	}

	/**
	 * PUT /clients/{id}
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$id = (int) $request->get_param( 'id' );

		if ( null === Dynamic_Client_Store::get_by_id( $id ) ) {
			return new \WP_Error( 'not_found', __( 'Client not found.', 'rtcamp-publish-with-ai' ), [ 'status' => 404 ] );
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
	 * DELETE /clients/{id}
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$id      = (int) $request->get_param( 'id' );
		$deleted = Dynamic_Client_Store::delete( $id );

		if ( ! $deleted ) {
			return new \WP_Error( 'not_found', __( 'Client not found.', 'rtcamp-publish-with-ai' ), [ 'status' => 404 ] );
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Only site admins may manage OAuth clients.
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
