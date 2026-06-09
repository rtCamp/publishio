<?php
/**
 * OAuth 2.1 Authorization Endpoint.
 *
 * Handles the user-facing authorization flow:
 * 1. Validates request parameters (client_id, redirect_uri, PKCE, etc.)
 * 2. Ensures the user is logged in (redirects to wp-login if not)
 * 3. Shows a consent screen
 * 4. On approval, issues an auth code and redirects back
 *
 * Registered at: GET /wp-json/pwai-oauth/v1/authorize
 *                POST /wp-json/pwai-oauth/v1/authorize (consent form submit)
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth\Endpoint
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth\Endpoint;

use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_REST_Controller;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Client\Client_Registry;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Config;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage\Auth_Code_Store;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage\Client_Store;
use rtCamp\Publish_With_AI\Modules\MCP\Server\Server;

/**
 * Class - Authorize
 */
class Authorize extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 *
	 * Also hooks cookie authentication for this browser-facing endpoint.
	 */
	public function register_hooks(): void {
		parent::register_hooks();

		// WordPress REST API ignores login cookies unless X-WP-Nonce header
		// is present. The authorize endpoint is browser-facing (redirect from
		// wp-login), so we need to authenticate from the cookie directly.
		add_filter( 'rest_authentication_errors', [ $this, 'authenticate_cookie_for_authorize' ], 80 );
	}

	/**
	 * Authenticate from login cookie for the authorize endpoint.
	 *
	 * REST API requires X-WP-Nonce for cookie auth (CSRF protection), but
	 * the authorize endpoint is a browser redirect from wp-login — no nonce
	 * header is possible. We validate the cookie ourselves for this route only.
	 *
	 * @param \WP_Error|true|null $result Existing auth result.
	 *
	 * @return \WP_Error|true|null
	 */
	public function authenticate_cookie_for_authorize( $result ) {
		// Don't override if already authenticated.
		if ( null !== $result ) {
			return $result;
		}

		// Only act on the authorize endpoint.
		// GET: shows consent screen (no state change — safe).
		// POST: protected by wp_verify_nonce('pwai_oauth_consent').
		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$expected     = '/' . rest_get_url_prefix() . '/' . Config::OAUTH_REST_NAMESPACE . '/authorize';

		if ( untrailingslashit( $request_path ) !== untrailingslashit( $expected ) ) {
			return $result;
		}

		// Validate the login cookie directly.
		$user_id = wp_validate_auth_cookie( '', 'logged_in' );
		if ( $user_id ) {
			wp_set_current_user( $user_id );
			return true;
		}

		return $result;
	}

	/**
	 * Register the authorize route.
	 */
	public function register_routes(): void {
		register_rest_route(
			Config::OAUTH_REST_NAMESPACE,
			'/authorize',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'handle_get' ],
					'permission_callback' => '__return_true',
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'handle_post' ],
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * Handle GET request — validate params, ensure login, show consent.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_get( \WP_REST_Request $request ) {
		$params = $this->extract_params( $request );

		$error = $this->validate_params( $params );
		if ( $error ) {
			return $error;
		}

		// If not logged in, redirect to wp-login with a return URL.
		if ( ! is_user_logged_in() ) {
			$return_url = $this->build_authorize_url( $params );

			return new \WP_REST_Response(
				null,
				302,
				[
					'Location' => wp_login_url( $return_url ),
				]
			);
		}

		// Ensure the user has permission to authorize MCP clients.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to authorize MCP clients.', 'publish-with-ai' ), [ 'status' => 403 ] );
		}

		// User is logged in — show consent screen.
		return $this->render_consent_screen( $params );
	}

	/**
	 * Handle POST request — process consent form submission.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_post( \WP_REST_Request $request ) {
		$params = $this->extract_params( $request );

		$error = $this->validate_params( $params );
		if ( $error ) {
			return $error;
		}

		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'not_authenticated', 'User must be logged in.', [ 'status' => 401 ] );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to authorize MCP clients.', 'publish-with-ai' ), [ 'status' => 403 ] );
		}

		// Verify nonce.
		$nonce = $request->get_param( '_wpnonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'pwai_oauth_consent' ) ) {
			return new \WP_Error( 'invalid_nonce', 'Invalid or expired form submission.', [ 'status' => 403 ] );
		}

		$action = $request->get_param( 'consent' );

		// User denied.
		if ( 'deny' === $action ) {
			$redirect = add_query_arg(
				[
					'error'             => 'access_denied',
					'state'             => $params['state'],
					'error_description' => 'The user denied the authorization request.',
				],
				$params['redirect_uri']
			);

			return new \WP_REST_Response( null, 302, [ 'Location' => $redirect ] );
		}

		// User approved — issue auth code.
		$code = Auth_Code_Store::create(
			get_current_user_id(),
			$params['client_id'],
			$params['redirect_uri'],
			$params['code_challenge'],
			$params['scope'],
			$params['resource']
		);

		$redirect = add_query_arg(
			[
				'code'  => $code,
				'state' => $params['state'],
			],
			$params['redirect_uri']
		);

		return new \WP_REST_Response( null, 302, [ 'Location' => $redirect ] );
	}

	/**
	 * Extract OAuth parameters from the request.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return array<string, string>
	 */
	private function extract_params( \WP_REST_Request $request ): array {
		return [
			'response_type'         => (string) $request->get_param( 'response_type' ),
			'client_id'             => (string) $request->get_param( 'client_id' ),
			'redirect_uri'          => (string) $request->get_param( 'redirect_uri' ),
			'code_challenge'        => (string) $request->get_param( 'code_challenge' ),
			'code_challenge_method' => (string) $request->get_param( 'code_challenge_method' ),
			'state'                 => (string) $request->get_param( 'state' ),
			'scope'                 => (string) ( $request->get_param( 'scope' ) ?? '' ),
			'resource'              => (string) ( $request->get_param( 'resource' ) ?? '' ),
		];
	}

	/**
	 * Validate the authorization request parameters.
	 *
	 * @param array<string, string> $params The extracted parameters.
	 *
	 * @return \WP_Error|null Error if invalid, null if valid.
	 */
	private function validate_params( array $params ): ?\WP_Error {
		if ( 'code' !== $params['response_type'] ) {
			return new \WP_Error( 'unsupported_response_type', 'Only response_type=code is supported.', [ 'status' => 400 ] );
		}

		if ( ! Client_Registry::client_exists( $params['client_id'] ) ) {
			return new \WP_Error( 'invalid_client', 'Unknown client_id.', [ 'status' => 400 ] );
		}

		if ( ! Client_Registry::validate_redirect_uri( $params['client_id'], $params['redirect_uri'] ) ) {
			return new \WP_Error( 'invalid_redirect_uri', 'The redirect_uri is not registered for this client.', [ 'status' => 400 ] );
		}

		if ( ! Config::is_redirect_uri_allowed( $params['redirect_uri'] ) ) {
			return new \WP_Error( 'unauthorized_client', 'This client is not permitted to use this authorization server.', [ 'status' => 403 ] );
		}

		if ( empty( $params['code_challenge'] ) ) {
			return new \WP_Error( 'invalid_request', 'PKCE code_challenge is required.', [ 'status' => 400 ] );
		}

		if ( 'S256' !== $params['code_challenge_method'] ) {
			return new \WP_Error( 'invalid_request', 'Only S256 code_challenge_method is supported.', [ 'status' => 400 ] );
		}

		if ( empty( $params['state'] ) ) {
			return new \WP_Error( 'invalid_request', 'state parameter is required.', [ 'status' => 400 ] );
		}

		if ( empty( $params['resource'] ) ) {
			return new \WP_Error( 'invalid_target', 'The resource parameter is required.', [ 'status' => 400 ] );
		}

		if ( untrailingslashit( $params['resource'] ) !== Config::get_mcp_resource_claim() ) {
			return new \WP_Error( 'invalid_target', 'The resource parameter does not identify a known protected resource.', [ 'status' => 400 ] );
		}

		return null;
	}

	/**
	 * Build the authorize URL with all current parameters.
	 *
	 * @param array<string, string> $params The OAuth parameters.
	 */
	private function build_authorize_url( array $params ): string {
		return add_query_arg( $params, rest_url( Config::OAUTH_REST_NAMESPACE . '/authorize' ) );
	}

	/**
	 * Render the consent screen as an HTML response.
	 *
	 * @param array<string, string> $params The OAuth parameters.
	 */
	private function render_consent_screen( array $params ): \WP_REST_Response {
		$user        = wp_get_current_user();
		$client      = Client_Store::get_by_client_id( $params['client_id'] );
		$client_name = $client ? $client['client_name'] : $params['client_id']; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
		$client_uri  = $client ? ( $client['client_uri'] ?? null ) : null; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
		$logo_uri    = $client ? ( $client['logo_uri'] ?? null ) : null; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
		$tos_uri     = $client ? ( $client['tos_uri'] ?? null ) : null; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
		$policy_uri  = $client ? ( $client['policy_uri'] ?? null ) : null; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
		$site_name   = get_bloginfo( 'name' ); // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
		$action_url  = rest_url( Config::OAUTH_REST_NAMESPACE . '/authorize' ); // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable

		// Build hidden fields for all OAuth params.
		$hidden_fields = ''; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
		foreach ( $params as $key => $value ) {
			$hidden_fields .= sprintf( // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
				'<input type="hidden" name="%s" value="%s" />',
				esc_attr( $key ),
				esc_attr( $value )
			);
		}

		$scopes       = $params['scope'] ? implode( ', ', explode( ' ', $params['scope'] ) ) : 'full access'; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
		$display_name = $user->display_name; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
		$user_email   = $user->user_email; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
		$css_url      = PUBLISH_WITH_AI_URL . 'assets/css/consent.css'; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
		$site_url     = home_url( '/' ); // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable

		$resource_url       = $params['resource'];
		$_server            = Server::get_server();
		$server_name        = $_server ? $_server->get_server_name() : __( 'MCP Server', 'publish-with-ai' ); // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
		$server_description = $_server ? $_server->get_server_description() : ''; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable

		$response = new \WP_REST_Response( null, 200 );
		$response->header( 'Content-Type', 'text/html; charset=utf-8' );
		$response->header( 'Cache-Control', 'no-store' );

		add_filter(
			'rest_pre_serve_request',
			// @phpstan-ignore-next-line
			static function ( $_served ) use ( $client_name, $client_uri, $logo_uri, $tos_uri, $policy_uri, $site_name, $site_url, $display_name, $user_email, $css_url, $action_url, $hidden_fields, $server_name, $server_description, $resource_url, $scopes ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found,SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter,SlevomatCodingStandard.Functions.UnusedInheritedVariablePassedToClosure.UnusedInheritedVariable
				include PUBLISH_WITH_AI_PATH . 'templates/oauth/consent.php';
				return true;
			}
		);

		return $response;
	}
}
