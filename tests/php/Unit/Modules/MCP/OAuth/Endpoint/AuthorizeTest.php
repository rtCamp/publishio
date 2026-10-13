<?php
/**
 * Tests for scope handling at the authorization endpoint.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Endpoint
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Endpoint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use rtCamp\Publishio\Modules\MCP\OAuth\Client\Client_Metadata_Validator;
use rtCamp\Publishio\Modules\MCP\OAuth\Config;
use rtCamp\Publishio\Modules\MCP\OAuth\Endpoint\Authorize;
use rtCamp\Publishio\Modules\MCP\OAuth\Scope;
use rtCamp\Publishio\Modules\MCP\OAuth\Storage\Auth_Code_Store;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - AuthorizeTest
 *
 * Each request stops where the endpoint would exit: at a redirect, at an error page (wp_die), or when the consent template loads.
 */
#[CoversClass( Authorize::class )]
class AuthorizeTest extends TestCase {
	private const CLIENT_ID    = 'https://app.example.com/client.json';
	private const REDIRECT_URI = 'https://app.example.com/callback';

	/**
	 * Location of the redirect the request ended with.
	 */
	private ?string $redirect = null;

	/**
	 * Arguments passed to the consent template.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $consent = null;

	/**
	 * Cache the client's metadata document, sign in, and stop requests where they would exit.
	 */
	protected function setUp(): void {
		parent::setUp();

		$client = Client_Metadata_Validator::validate_document(
			[
				'client_id'                  => self::CLIENT_ID,
				'client_name'                => 'Example App',
				'redirect_uris'              => [ self::REDIRECT_URI ],
				'token_endpoint_auth_method' => 'none',
			],
			self::CLIENT_ID
		);
		$this->assertIsArray( $client );
		set_transient( 'publishio_cimd_' . hash( 'sha256', self::CLIENT_ID ), $client, 300 );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		add_filter(
			'wp_redirect',
			function ( string $location ): string { // phpcs:ignore WordPressVIPMinimum.Hooks.AlwaysReturnInFilter.TerminatingInsteadOfReturn -- Stops the request where the endpoint would exit.
				$this->redirect = $location;
				throw new \RuntimeException( 'redirect' );
			}
		);
		add_action(
			'wp_before_load_template',
			function ( string $_file, bool $_once, array $args ): void { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
				$this->consent = $args;
				throw new \RuntimeException( 'consent' );
			},
			10,
			3
		);
	}

	/**
	 * Reset the request.
	 */
	protected function tearDown(): void {
		$_GET  = [];
		$_POST = [];
		unset( $_SERVER['REQUEST_METHOD'] );

		parent::tearDown();
	}

	/**
	 * With no scope requested, the consent screen offers read access only.
	 */
	public function test_consent_without_scope_offers_read_only(): void {
		$this->get( '' );

		$this->assertSame( Scope::describe( 'mcp:read' ), $this->consent['permissions'] ?? null );
	}

	/**
	 * A request for mcp:write shows read access too, since write access includes it.
	 */
	public function test_consent_for_write_lists_read_and_write(): void {
		$this->get( 'mcp:write' );

		$this->assertSame( Scope::describe( 'mcp:read mcp:write' ), $this->consent['permissions'] ?? null );
	}

	/**
	 * A request with no supported scope gets an error page, not a redirect to the client, even before sign-in.
	 */
	public function test_unsupported_scope_shows_error_without_redirect(): void {
		wp_set_current_user( 0 );

		$error = $this->get( 'bogus:nonsense' );

		$this->assertStringContainsString( 'invalid_scope', $error );
		$this->assertNull( $this->redirect );
		$this->assertNull( $this->consent );
	}

	/**
	 * Requested scopes and the scope an approval stores on the authorization code.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function approve_cases(): array {
		return [
			'no scope'                => [ '', 'mcp:read' ],
			'read plus an OIDC scope' => [ 'mcp:read openid', 'mcp:read' ],
			'write only'              => [ 'mcp:write', 'mcp:read mcp:write' ],
		];
	}

	/**
	 * Approval stores the normalized scope, not the scope string the client sent.
	 *
	 * @param string $requested Requested scope.
	 * @param string $expected  Scope stored on the authorization code.
	 */
	#[DataProvider( 'approve_cases' )]
	public function test_approve_stores_normalized_scope( string $requested, string $expected ): void {
		$this->post( $requested, 'approve' );

		$query = $this->redirect_query();
		$this->assertArrayHasKey( 'code', $query );

		$code = Auth_Code_Store::consume( $query['code'] );
		$this->assertSame( $expected, $code['scope'] ?? null );
	}

	/**
	 * Approving a form with no supported scope gets an error page and issues no code.
	 */
	public function test_approve_with_unsupported_scope_shows_error(): void {
		$error = $this->post( 'bogus:nonsense', 'approve' );

		$this->assertStringContainsString( 'invalid_scope', $error );
		$this->assertNull( $this->redirect );
	}

	/**
	 * Deny is reported as access_denied, whatever the scope.
	 */
	public function test_deny_reports_access_denied_before_checking_scope(): void {
		$this->post( 'bogus:nonsense', 'deny' );

		$this->assertSame( 'access_denied', $this->redirect_query()['error'] ?? null );
	}

	/**
	 * Send a GET request for the consent screen.
	 *
	 * @param string $scope Requested scope.
	 *
	 * @return string The error page message, or an empty string when the request redirected or showed the consent screen.
	 */
	private function get( string $scope ): string {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET                      = $this->params( $scope );

		return $this->dispatch();
	}

	/**
	 * Submit the consent form.
	 *
	 * @param string $scope   Requested scope.
	 * @param string $consent "approve" or "deny".
	 *
	 * @return string The error page message, or an empty string when the request redirected.
	 */
	private function post( string $scope, string $consent ): string {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = $this->params( $scope ) + [
			'_wpnonce' => wp_create_nonce( 'publishio_oauth_consent' ),
			'consent'  => $consent,
		];

		return $this->dispatch();
	}

	/**
	 * Authorization request parameters.
	 *
	 * @param string $scope Requested scope.
	 *
	 * @return array<string, string>
	 */
	private function params( string $scope ): array {
		return [
			'response_type'         => 'code',
			'client_id'             => self::CLIENT_ID,
			'redirect_uri'          => self::REDIRECT_URI,
			'code_challenge'        => 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFXYZ',
			'code_challenge_method' => 'S256',
			'state'                 => 'xyz',
			'scope'                 => $scope,
			'resource'              => Config::get_mcp_resource_claim(),
		];
	}

	/**
	 * Run the endpoint until it would exit.
	 *
	 * @return string The error page message, or an empty string when the request redirected or showed the consent screen.
	 */
	private function dispatch(): string {
		$wp = new \WP();

		$wp->query_vars['publishio_oauth_authorize'] = '1';

		try {
			( new Authorize() )->handle_request( $wp );
		} catch ( \WPDieException $e ) {
			return $e->getMessage();
		} catch ( \RuntimeException $e ) {
			return '';
		}

		$this->fail( 'The request did not redirect, show an error or show the consent screen.' );
	}

	/**
	 * Query parameters of the redirect the request ended with.
	 *
	 * @return array<string, string>
	 */
	private function redirect_query(): array {
		$this->assertIsString( $this->redirect );
		$this->assertStringStartsWith( self::REDIRECT_URI . '?', $this->redirect );

		parse_str( (string) wp_parse_url( $this->redirect, PHP_URL_QUERY ), $query );

		return $query; // @phpstan-ignore return.type
	}
}
