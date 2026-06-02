<?php
/**
 * REST API endpoints for OAuth connection management.
 *
 * Connections are auto-registered by MCP clients (Claude.ai, ChatGPT, etc.)
 * via the /register endpoint. Admins can list or revoke individual user
 * sessions here. Clients are never deleted — only their tokens are revoked.
 *
 * Routes (all require manage_options):
 *   GET    /wp-json/rtpwai/v1/connections
 *   DELETE /wp-json/rtpwai/v1/connections/{client_id}/users/{user_id}
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
			'/' . $this->rest_base . '/(?P<client_id>[a-zA-Z0-9_]+)/users/(?P<user_id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'client_id' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
					'user_id'   => [
						'type'     => 'integer',
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * GET /connections — list one row per active (client, user) pair, paginated.
	 *
	 * Pagination is over distinct (client_id, user_id) pairs that have a valid
	 * refresh token. Clients with no active sessions are not shown.
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	public function get_items( $request ): WP_REST_Response {
		$now   = time();
		$page  = max( 1, (int) $request->get_param( 'page' ) );
		$total = Token_Store::count_connection_pairs( $now );

		if ( 0 === $total ) {
			return new WP_REST_Response(
				[
					'items' => [],
					'total' => 0,
				],
				200
			);
		}

		$offset = ( $page - 1 ) * Client_Store::PAGE_SIZE;
		$pairs  = Token_Store::get_connection_pairs( $offset, Client_Store::PAGE_SIZE, $now );

		if ( empty( $pairs ) ) {
			return new WP_REST_Response(
				[
					'items' => [],
					'total' => $total,
				],
				200
			);
		}

		// Batch-load client metadata (one lookup per unique client on this page).
		$client_ids = array_unique( array_column( $pairs, 'client_id' ) );
		$client_map = [];
		foreach ( $client_ids as $cid ) {
			$client = Client_Store::get_by_client_id( $cid );
			if ( $client ) {
				$client_map[ $cid ] = $client;
			}
		}

		// Batch-load WP_User objects.
		$user_ids = array_unique( array_column( $pairs, 'user_id' ) );
		$user_map = [];
		if ( ! empty( $user_ids ) ) {
			foreach ( get_users( [ 'include' => $user_ids ] ) as $wp_user ) {
				$user_map[ $wp_user->ID ] = [
					'id'             => $wp_user->ID,
					'name'           => $wp_user->display_name,
					'email'          => $wp_user->user_email,
					'avatar_url'     => get_avatar_url( $wp_user->ID, [ 'size' => 32 ] ),
					'admin_edit_url' => admin_url( 'user-edit.php?user_id=' . $wp_user->ID ),
				];
			}
		}

		// Build one item per (client, user) pair.
		$items = [];
		foreach ( $pairs as $pair ) {
			$client = $client_map[ $pair['client_id'] ] ?? null;
			$user   = $user_map[ $pair['user_id'] ] ?? null;
			if ( ! $client || ! $user ) {
				continue; // Token orphaned — client or user deleted.
			}
			$items[] = array_merge(
				$this->prepare_client_for_response( $client ),
				[
					'user'           => $user,
					'last_active_at' => $pair['last_active_at'],
				]
			);
		}

		return new WP_REST_Response(
			[
				'items' => $items,
				'total' => $total,
			],
			200
		);
	}

	/**
	 * DELETE /connections/{client_id}/users/{user_id} — revoke one user's tokens for a client.
	 *
	 * The client record is never deleted. Only the tokens belonging to this
	 * specific (user, client) pair are removed.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$client_id = (string) $request->get_param( 'client_id' );
		$user_id   = (int) $request->get_param( 'user_id' );

		if ( null === Client_Store::get_by_client_id( $client_id ) ) {
			return new \WP_Error( 'not_found', __( 'Connection not found.', 'rtcamp-publish-with-ai' ), [ 'status' => 404 ] );
		}

		$tokens_deleted = Token_Store::revoke_for_client( $user_id, $client_id );

		if ( false === $tokens_deleted ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to revoke tokens.', 'rtcamp-publish-with-ai' ), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'tokens_deleted' => $tokens_deleted ], 200 );
	}

	/**
	 * Strip internal-only fields from a client row before sending it to the browser.
	 *
	 * `client_secret_hash` must never leave the server. `id` and `is_public` are
	 * internal implementation details that the connections UI does not need.
	 *
	 * @param array<string, mixed> $client Parsed client row from Client_Store.
	 *
	 * @return array<string, mixed>
	 */
	private function prepare_client_for_response( array $client ): array {
		unset( $client['client_secret_hash'], $client['id'], $client['is_public'] );
		return $client;
	}

	/**
	 * Only site admins may manage OAuth connections.
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}
}
