<?php
/**
 * Abstract REST controller class.
 *
 * Includes the shared namespace, version and hook registration.
 *
 * @package rtCamp\Publish_With_AI\Framework\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Framework\Contracts\Abstracts;

use WP_REST_Controller;
use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;

/**
 * Class - Abstract_REST_Controller
 */
abstract class Abstract_REST_Controller extends WP_REST_Controller implements Registrable {
	/**
	 * Route namespace for the REST API routes.
	 *
	 * Usually at /wp-json/{namespace}/{route}
	 *
	 * @var string
	 */
	protected $namespace = 'pwai/v';

	/**
	 * Version number for the REST API routes.
	 *
	 * @var string
	 */
	protected string $version = '1';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * {@inheritDoc}
	 *
	 * We throw an exception here to force the child class to implement this method.
	 *
	 * @throws \Exception If method not implemented.
	 *
	 * @codeCoverageIgnore
	 */
	public function register_routes(): void {
		throw new \Exception( __FUNCTION__ . ' Method not implemented.' );
	}
}
