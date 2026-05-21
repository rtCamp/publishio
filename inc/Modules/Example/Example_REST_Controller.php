<?php
/**
 * Example REST Controller.
 *
 * @package rtCamp\Publish_With_AI\Modules\Example
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Example;

use WP_REST_Response;
use WP_REST_Server;
use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_REST_Controller;

/**
 * Class - Example_REST_Controller
 */
final class Example_REST_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $rest_base = 'examples';

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace . $this->version,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
			]
		);
	}

	/**
	 * Retrieves a collection of example posts.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 */
	public function get_items( $request ): WP_REST_Response { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter -- Required by parent signature.
		$response = [
			'example_response' => 'example_response',
		];

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_items_permissions_check( $request ) { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter -- Required by parent signature.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to access this endpoint.', 'rtcamp-publish-with-ai' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}
}
