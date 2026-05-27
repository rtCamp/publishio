<?php
/**
 * REST API endpoints for OAuth connection management.
 *
 * Connections are auto-registered by MCP clients (Claude.ai, ChatGPT, etc.)
 * via the /register endpoint. Admins can only list or delete them here.
 * Deleting a connection revokes all associated OAuth tokens.
 *
 * Routes (all require manage_options):
 *   GET    /wp-json/rtpwai/v1/connections
 *   DELETE /wp-json/rtpwai/v1/connections/{id}
 *
 * @package rtCamp\Publish_With_AI\Modules\Settings\Connections
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Settings\Connections;

use WP_REST_Response;
use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_REST_Controller;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage\Client_Store;
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
	 * GET /connections — list auto-registered connections only (paginated, page size fixed at 10).
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	public function get_items( $request ): WP_REST_Response {
		$page   = max( 1, (int) $request->get_param( 'page' ) );
		$offset = ( $page - 1 ) * Client_Store::PAGE_SIZE;
		$total  = Client_Store::count_by_source( 'dcr' );

		if ( 0 === $total ) {
			return new WP_REST_Response(
				[
					'items' => [],
					'total' => 0,
				],
				200
			);
		}

		$connections = Client_Store::all_by_source( 'dcr', $offset );

		if ( empty( $connections ) ) {
			return new WP_REST_Response(
				[
					'items' => [],
					'total' => $total,
				],
				200
			);
		}

		// Query 1: user IDs + last active time per client, all in one hit.
		$client_ids = array_column( $connections, 'client_id' );
		$token_data = Token_Store::get_client_token_data( $client_ids );

		// Collect all distinct user IDs across every connection.
		$all_user_ids = [];
		foreach ( $token_data as $data ) {
			$all_user_ids = array_merge( $all_user_ids, $data['user_ids'] );
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

		// Attach resolved users and last_active_at to each connection.
		foreach ( $connections as &$connection ) {
			$data                         = $token_data[ $connection['client_id'] ] ?? null;
			$user_ids                     = $data['user_ids'] ?? [];
			$connection['users']          = array_values(
				array_filter( array_map( static fn ( $uid ) => $user_map[ $uid ] ?? null, $user_ids ) )
			);
			$connection['last_active_at'] = $data ? $data['last_active_at'] : null;
		}
		unset( $connection );

		return new WP_REST_Response(
			[
				'items' => $connections,
				'total' => $total,
			],
			200
		);
	}

	/**
	 * DELETE /connections/{id} — delete a connection and all its tokens.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$id     = (int) $request->get_param( 'id' );
		$client = Client_Store::get_by_id( $id );

		if ( null === $client ) {
			return new \WP_Error( 'not_found', __( 'Connection not found.', 'rtcamp-publish-with-ai' ), [ 'status' => 404 ] );
		}

		$tokens_deleted = Token_Store::delete_all_for_client( $client['client_id'] );

		if ( false === $tokens_deleted ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete associated tokens.', 'rtcamp-publish-with-ai' ), [ 'status' => 500 ] );
		}

		$deleted = Client_Store::delete( $id );

		if ( ! $deleted ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete connection.', 'rtcamp-publish-with-ai' ), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'tokens_deleted' => $tokens_deleted ], 200 );
	}

	/**
	 * Only site admins may manage OAuth connections.
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}
}
