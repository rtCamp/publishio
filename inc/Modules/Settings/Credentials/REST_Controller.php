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
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'id' => [
						'type'     => 'integer',
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * GET /credentials — list admin-managed credentials.
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	public function get_items( $request ): WP_REST_Response { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
		$credentials = Client_Store::all_by_source( 'cred' );

		if ( empty( $credentials ) ) {
			return new WP_REST_Response( [], 200 );
		}

		$client_ids = array_column( $credentials, 'client_id' );
		$token_data = Token_Store::get_client_token_data( $client_ids );

		foreach ( $credentials as &$credential ) {
			$data                         = $token_data[ $credential['client_id'] ] ?? null;
			$credential['last_active_at'] = $data ? $data['last_active_at'] : null;
		}
		unset( $credential );

		return new WP_REST_Response( $credentials, 200 );
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

		$secret      = wp_generate_password( 40, false );
		$secret_hash = wp_hash_password( $secret );

		$client_id = Client_Store::register(
			[
				'source'             => 'cred',
				'client_name'        => sanitize_text_field( (string) $request->get_param( 'client_name' ) ),
				'redirect_uris'      => $redirect_uris,
				'client_secret_hash' => $secret_hash,
				'grant_types'        => [ 'authorization_code', 'refresh_token' ],
				'response_types'     => [ 'code' ],
				'scope'              => implode( ' ', Config::SUPPORTED_SCOPES ),
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
	 * Only site admins may manage credentials.
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Args schema for the create endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_schema_args(): array {
		return [
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
		];
	}
}
