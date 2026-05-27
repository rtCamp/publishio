<?php
/**
 * REST API endpoint for reading and updating screenshot settings.
 *
 * Routes (both require manage_options):
 *   GET /wp-json/rtpwai/v1/screenshot-settings
 *   PUT /wp-json/rtpwai/v1/screenshot-settings
 *
 * @package rtCamp\Publish_With_AI\Modules\Screenshot
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Screenshot;

use WP_REST_Request;
use WP_REST_Response;
use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_REST_Controller;

/**
 * Class - REST_Controller
 */
class REST_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 */
	protected $rest_base = 'screenshot-settings';

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
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => [
						'enabled'  => [
							'type'     => 'boolean',
							'required' => true,
						],
						'provider' => [
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'api_key'  => [
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);
	}

	/**
	 * GET /screenshot-settings
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
		return new WP_REST_Response( $this->current_settings(), 200 );
	}

	/**
	 * PUT /screenshot-settings
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings( WP_REST_Request $request ) {
		$provider_id = sanitize_text_field( (string) $request->get_param( 'provider' ) );

		if ( null === Settings::find_provider( $provider_id ) ) {
			return new \WP_Error(
				'invalid_provider',
				__( 'Unknown screenshot provider.', 'rtcamp-publish-with-ai' ),
				[ 'status' => 400 ]
			);
		}

		update_option( Settings::OPTION_ENABLED, (bool) $request->get_param( 'enabled' ), false );
		update_option( Settings::OPTION_PROVIDER, $provider_id, false );

		$api_key = sanitize_text_field( (string) $request->get_param( 'api_key' ) );

		if ( '' !== $api_key ) {
			update_option( Settings::OPTION_API_KEY, $api_key, false );
		}

		return new WP_REST_Response( $this->current_settings(), 200 );
	}

	/**
	 * Only site admins may manage screenshot settings.
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Build the settings payload returned to the client.
	 *
	 * The raw API key is never returned — only a boolean indicating one is stored.
	 *
	 * @return array{enabled: bool, provider: string, has_api_key: bool, providers: array<int, mixed>}
	 */
	private function current_settings(): array {
		return [
			'enabled'     => Settings::is_enabled(),
			'provider'    => Settings::get_provider(),
			'has_api_key' => '' !== Settings::get_api_key(),
			'providers'   => Settings::get_providers(),
		];
	}
}
