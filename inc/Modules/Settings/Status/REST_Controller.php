<?php
/**
 * REST API endpoints for the Status page.
 *
 * Routes (require the capability to connect an AI app):
 *   GET /wp-json/publishio/v1/status       Site facts the browser can't see.
 *   GET /wp-json/publishio/v1/status/apps  Fetch each app's Client ID Metadata Document from this server.
 *
 * @package rtCamp\Publishio\Modules\Settings\Status
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\Settings\Status;

use WP_REST_Response;
use rtCamp\Publishio\Framework\Contracts\Abstracts\Abstract_REST_Controller;
use rtCamp\Publishio\Modules\MCP\OAuth\Endpoint\Authorize;

/**
 * Class - REST_Controller
 */
class REST_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 */
	protected $rest_base = 'status';

	/**
	 * Builds the responses.
	 */
	private Site_Status $site_status;

	/**
	 * Constructor.
	 *
	 * @param \rtCamp\Publishio\Modules\Settings\Status\Site_Status|null $site_status Site status source.
	 */
	public function __construct( ?Site_Status $site_status = null ) {
		$this->site_status = $site_status ?? new Site_Status();
	}

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
				'callback'            => fn (): WP_REST_Response => new WP_REST_Response( $this->site_status->facts(), 200 ),
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);

		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/apps',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => fn (): WP_REST_Response => new WP_REST_Response( $this->site_status->verify_apps(), 200 ),
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);
	}

	/**
	 * Anyone who can connect an AI app can check whether the site lets them.
	 */
	public function permissions_check(): bool {
		return current_user_can( Authorize::CAPABILITY ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined
	}
}
