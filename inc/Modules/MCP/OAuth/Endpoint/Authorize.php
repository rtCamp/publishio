<?php
/**
 * OAuth 2.1 authorization endpoint (the user-facing consent screen).
 *
 * Served at /publishio-oauth/authorize via a rewrite rule. Cookie-authenticated, with a nonce-protected consent POST and anti-framing headers.
 *
 * @package rtCamp\Publishio\Modules\MCP\OAuth\Endpoint
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\OAuth\Endpoint;

use rtCamp\Publishio\Core\Templates;
use rtCamp\Publishio\Framework\Contracts\Interfaces\Registrable;
use rtCamp\Publishio\Modules\MCP\OAuth\Client\Client_Registry;
use rtCamp\Publishio\Modules\MCP\OAuth\Config;
use rtCamp\Publishio\Modules\MCP\OAuth\Storage\Auth_Code_Store;
use rtCamp\Publishio\Modules\MCP\OAuth\Storage\Client_Store;
use rtCamp\Publishio\Modules\MCP\Server\Server;

/**
 * Class - Authorize
 */
class Authorize implements Registrable {
	/**
	 * Query var that flags a request for the authorize endpoint.
	 */
	private const QUERY_VAR = 'publishio_oauth_authorize';

	/**
	 * Params kept exactly as sent, since they are checked by exact match.
	 */
	private const EXACT_PARAMS = [ 'state', 'redirect_uri', 'resource' ];

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'parse_request', [ $this, 'handle_request' ] );
	}

	/**
	 * Register the rewrite rule for the authorize endpoint.
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^' . Config::AUTHORIZE_PATH . '/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * Register the custom query variable.
	 *
	 * @param array<string> $vars Existing query vars.
	 *
	 * @return array<string>
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Dispatch GET/POST for the authorize endpoint.
	 *
	 * @param \WP $wp The WordPress environment instance.
	 */
	public function handle_request( \WP $wp ): void {
		if ( empty( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			return;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';

		if ( 'POST' === $method ) {
			$this->handle_post();
			return;
		}

		$this->handle_get();
	}

	/**
	 * Handle GET — validate params, ensure login, show consent.
	 */
	private function handle_get(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only consent screen; no state change. GET params are validated below.
		$params = $this->extract_params( $_GET );

		$error = $this->validate_params( $params );
		if ( $error ) {
			$this->render_error( $error );
			return;
		}

		if ( ! is_user_logged_in() ) {
			$this->send_security_headers();
			wp_safe_redirect( wp_login_url( $this->build_authorize_url( $params ) ) );
			exit;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			$this->render_error(
				new \WP_Error( 'forbidden', __( 'You do not have permission to authorize MCP clients.', 'publishio' ), [ 'status' => 403 ] )
			);
			return;
		}

		$this->render_consent_screen( $params );
	}

	/**
	 * Handle POST — process the consent form submission.
	 */
	private function handle_post(): void {
		if ( ! is_user_logged_in() ) {
			$this->render_error(
				new \WP_Error( 'not_authenticated', __( 'User must be logged in.', 'publishio' ), [ 'status' => 401 ] )
			);
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			$this->render_error(
				new \WP_Error( 'forbidden', __( 'You do not have permission to authorize MCP clients.', 'publishio' ), [ 'status' => 403 ] )
			);
			return;
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'publishio_oauth_consent' ) ) {
			$this->render_error(
				new \WP_Error( 'invalid_nonce', __( 'Invalid or expired form submission.', 'publishio' ), [ 'status' => 403 ] )
			);
			return;
		}

		$params = $this->extract_params( $_POST );

		$error = $this->validate_params( $params );
		if ( $error ) {
			$this->render_error( $error );
			return;
		}

		$action = isset( $_POST['consent'] ) ? sanitize_text_field( wp_unslash( $_POST['consent'] ) ) : '';

		if ( 'approve' !== $action ) {
			$this->redirect_to_client(
				add_query_arg(
					rawurlencode_deep(
						[
							'error'             => 'access_denied',
							'state'             => $params['state'],
							'error_description' => 'The user denied the authorization request.',
						]
					),
					$params['redirect_uri']
				)
			);
			return;
		}

		$code = Auth_Code_Store::create(
			get_current_user_id(),
			$params['client_id'],
			$params['redirect_uri'],
			$params['code_challenge'],
			$params['scope'],
			$params['resource']
		);

		$this->redirect_to_client(
			add_query_arg(
				rawurlencode_deep(
					[
						'code'  => $code,
						'state' => $params['state'],
					]
				),
				$params['redirect_uri']
			)
		);
	}

	/**
	 * Extract OAuth parameters from a request source ($_GET or $_POST).
	 *
	 * @param array<string, mixed> $source Raw request array.
	 *
	 * @return array<string, string>
	 */
	private function extract_params( array $source ): array {
		$keys = [
			'response_type',
			'client_id',
			'redirect_uri',
			'code_challenge',
			'code_challenge_method',
			'state',
			'scope',
			'resource',
		];

		$params = [];
		foreach ( $keys as $key ) {
			$params[ $key ] = $this->sanitize_param( $key, $source[ $key ] ?? '' );
		}

		return $params;
	}

	/**
	 * Sanitize a single OAuth parameter.
	 *
	 * @param string $key   Parameter name.
	 * @param mixed  $value Raw request value.
	 */
	private function sanitize_param( string $key, mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = wp_unslash( $value );

		return in_array( $key, self::EXACT_PARAMS, true ) ? $value : sanitize_text_field( $value );
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
		return add_query_arg( rawurlencode_deep( $params ), Config::get_authorize_url() );
	}

	/**
	 * Redirect the browser to a client redirect URI (external host, already validated).
	 *
	 * @param string $url The validated redirect URL with its query appended.
	 */
	private function redirect_to_client( string $url ): void {
		$this->send_security_headers();
		// Not using wp_safe_redirect because the redirect URL is external and already validated.
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Send anti-framing and hardening headers so the consent screen cannot be framed (clickjacking of "Allow").
	 */
	private function send_security_headers(): void {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Frame-Options: DENY' );
		header( "Content-Security-Policy: frame-ancestors 'none'" );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: no-referrer' );
	}

	/**
	 * Render an OAuth error page and terminate.
	 *
	 * @param \WP_Error $error The error to render.
	 */
	private function render_error( \WP_Error $error ): void {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

		$message = sprintf(
			/* translators: 1: site name, 2: OAuth error message, 3: OAuth error code */
			__( 'An application could not be authorized to access %1$s. %2$s (error code: %3$s)', 'publishio' ),
			get_bloginfo( 'name' ),
			$error->get_error_message(),
			$error->get_error_code()
		);

		$this->send_security_headers();
		wp_die(
			esc_html( $message ),
			esc_html__( 'Authorization error', 'publishio' ),
			[ 'response' => absint( $status ) ]
		);
	}

	/**
	 * Render the consent screen as an HTML page and terminate.
	 *
	 * @param array<string, string> $params The OAuth parameters.
	 */
	private function render_consent_screen( array $params ): void {
		$user   = wp_get_current_user();
		$client = Client_Store::get_by_client_id( $params['client_id'] );

		$hidden_fields = '';
		foreach ( $params as $key => $value ) {
			$hidden_fields .= sprintf(
				'<input type="hidden" name="%s" value="%s" />',
				esc_attr( $key ),
				esc_attr( $value )
			);
		}

		$_server = Server::get_server();

		$self_registered  = ! $client || 'dcr' === ( $client['source'] ?? 'dcr' );
		$destination_note = $self_registered
			? __( 'This application registered itself, so approve only if you recognize this destination.', 'publishio' )
			: __( 'Approve only if you recognize this destination.', 'publishio' );

		wp_register_style( 'publishio-consent', PUBLISHIO_URL . 'assets/css/consent.css', [], PUBLISHIO_VERSION );
		wp_enqueue_style( 'publishio-consent' );

		$args = [
			'client_name'        => $client['client_name'] ?? $params['client_id'],
			'client_uri'         => $client['client_uri'] ?? null,
			'logo_uri'           => $client['logo_uri'] ?? null,
			'tos_uri'            => $client['tos_uri'] ?? null,
			'policy_uri'         => $client['policy_uri'] ?? null,
			'site_name'          => get_bloginfo( 'name' ),
			'site_url'           => home_url( '/' ),
			'display_name'       => $user->display_name,
			'user_email'         => $user->user_email,
			'action_url'         => Config::get_authorize_url(),
			'hidden_fields'      => $hidden_fields,
			'scopes'             => $params['scope'] ? implode( ', ', explode( ' ', $params['scope'] ) ) : 'full access',
			'resource_url'       => $params['resource'],
			'server_name'        => $_server ? $_server->get_server_name() : __( 'MCP Server', 'publishio' ),
			'server_description' => $_server ? $_server->get_server_description() : '',
			'redirect_uri'       => $params['redirect_uri'],
			'destination_note'   => $destination_note,
		];

		$this->send_security_headers();
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: no-store' );

		Templates::get_template_part( 'oauth-consent', null, $args, true );
		exit;
	}
}
