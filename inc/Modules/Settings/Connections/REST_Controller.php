<?php
/**
 * REST API endpoints for OAuth connection management.
 *
 * Each active token is one connection: created when a user approves an MCP client (Claude.ai, ChatGPT, etc.), and kept across refreshes. Admins can list connections or remove one.
 *
 * Routes (all require manage_options):
 *   GET    /wp-json/publishio/v1/connections?page={page}&order={desc|asc}
 *   DELETE /wp-json/publishio/v1/connections/{id}?user_id={user_id}
 *
 * @package rtCamp\Publishio\Modules\Settings\Connections
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\Settings\Connections;

use WP_REST_Response;
use rtCamp\Publishio\Framework\Contracts\Abstracts\Abstract_REST_Controller;
use rtCamp\Publishio\Modules\MCP\OAuth\Client\Client_Metadata;
use rtCamp\Publishio\Modules\MCP\OAuth\Storage\Token_Store;

/**
 * Class - REST_Controller
 */
class REST_Controller extends Abstract_REST_Controller {
	/**
	 * Connections per page.
	 */
	private const PAGE_SIZE = 10;

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
					'page'  => [
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					],
					'order' => [
						'description' => __( 'Sort by last activity: newest first (desc) or oldest first (asc).', 'publishio' ),
						'type'        => 'string',
						'enum'        => [ 'asc', 'desc' ],
						'default'     => 'desc',
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
					'id'      => [
						'type'     => 'integer',
						'required' => true,
					],
					'user_id' => [
						'type'     => 'integer',
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * GET /connections — list active connections by last activity, paginated.
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	public function get_items( $request ): WP_REST_Response {
		$now          = time();
		$page         = max( 1, (int) $request->get_param( 'page' ) );
		$oldest_first = 'asc' === $request->get_param( 'order' );
		$total        = Token_Store::count_connections( $now );
		$connections  = $total > 0 ? Token_Store::get_connections( ( $page - 1 ) * self::PAGE_SIZE, self::PAGE_SIZE, $now, $oldest_first ) : [];

		// Batch-load WP_User objects.
		$user_ids = array_unique( array_column( $connections, 'user_id' ) );
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

		$items = [];
		foreach ( $connections as $connection ) {
			$user = $user_map[ $connection['user_id'] ] ?? null;
			if ( ! $user ) {
				continue; // Token orphaned: user deleted.
			}

			$host    = Client_Metadata::host( $connection['client_id'] );
			$items[] = [
				'id'             => $connection['id'],
				'client_id'      => $connection['client_id'],
				'client_name'    => '' !== $connection['client_name'] ? $connection['client_name'] : $host,
				'client_uri'     => 'https://' . $host,
				'user'           => $user,
				'connected_at'   => $connection['connected_at'],
				'last_active_at' => $connection['last_active_at'],
			];
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
	 * DELETE /connections/{id}?user_id={user_id} — remove one of a user's connections.
	 *
	 * Returns 404 when that user has no such id: already removed, or the app has refreshed its token since the list loaded.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$deleted = Token_Store::revoke( (int) $request->get_param( 'id' ), (int) $request->get_param( 'user_id' ) );

		if ( false === $deleted ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to remove the connection.', 'publishio' ), [ 'status' => 500 ] );
		}

		if ( 0 === $deleted ) {
			return new \WP_Error( 'not_found', __( 'Connection not found.', 'publishio' ), [ 'status' => 404 ] );
		}

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	/**
	 * Only site admins may manage OAuth connections.
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}
}
