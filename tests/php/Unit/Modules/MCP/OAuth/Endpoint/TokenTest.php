<?php
/**
 * Tests for the token endpoint's authorization_code and refresh_token grants.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Endpoint
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Endpoint;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\OAuth\Endpoint\Token;
use rtCamp\Publishio\Modules\MCP\OAuth\Storage\Auth_Code_Store;
use rtCamp\Publishio\Modules\MCP\OAuth\Storage\Token_Store;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - TokenTest
 */
#[CoversClass( Token::class )]
class TokenTest extends TestCase {
	private const CLIENT_ID     = 'https://app.example.com/client.json';
	private const REDIRECT_URI  = 'https://app.example.com/callback';
	private const CODE_VERIFIER = 'test-code-verifier-0123456789-abcdefghijklmnopqrstuvwxyz';

	/**
	 * A code created before the user's password changed, even in the same second, can't be exchanged; one created after can.
	 */
	public function test_code_issued_before_password_change_is_rejected(): void {
		$user_id = self::factory()->user->create();
		wp_set_password( 'new-password', $user_id );
		$revoked_at = (int) get_user_meta( $user_id, 'publishio_tokens_revoked_at', true );

		$before = $this->exchange( $this->create_code( $user_id, $revoked_at - MINUTE_IN_SECONDS ) );
		$same   = $this->exchange( $this->create_code( $user_id, $revoked_at ) );
		$after  = $this->exchange( $this->create_code( $user_id, $revoked_at + 1 ) );

		foreach ( [ $before, $same ] as $rejected ) {
			$this->assertSame( 400, $rejected->get_status() );
			$this->assertSame( 'invalid_grant', ( (array) $rejected->get_data() )['error'] );
		}
		$this->assertSame( 200, $after->get_status() );
	}

	/**
	 * A refresh token is exchanged once; replaying it fails with invalid_grant.
	 */
	public function test_refresh_works_once(): void {
		$tokens = Token_Store::issue( self::factory()->user->create(), self::CLIENT_ID, 'Example App', 'mcp:read mcp:write', 'https://example.org/wp-json/mcp/publishio' );
		$this->assertIsArray( $tokens );

		$first  = $this->refresh( $tokens['refresh_token'], self::CLIENT_ID );
		$replay = $this->refresh( $tokens['refresh_token'], self::CLIENT_ID );

		$this->assertSame( 200, $first->get_status() );
		$this->assertArrayHasKey( 'access_token', (array) $first->get_data() );
		$this->assertSame( 400, $replay->get_status() );
		$this->assertSame( 'invalid_grant', ( (array) $replay->get_data() )['error'] );
	}

	/**
	 * A refresh from a connection made before CIMD (dcr_ client id) gets invalid_grant, so the app signs in again, with a hint to reconnect.
	 */
	public function test_refresh_with_legacy_client_id_asks_to_reconnect(): void {
		$response = $this->refresh( 'old-refresh-token', 'dcr_abc123' );
		$data     = (array) $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'invalid_grant', $data['error'] );
		$this->assertIsString( $data['error_description'] );
		$this->assertStringContainsString( 'disconnect the connector in your AI app and connect it again', $data['error_description'] );
	}

	/**
	 * Send a refresh_token grant to the token endpoint's handler.
	 *
	 * Calls the handler directly: dispatching through the REST server also boots the MCP adapter, which is outside this test.
	 *
	 * @param string $refresh_token Refresh token.
	 * @param string $client_id     Client ID.
	 */
	private function refresh( string $refresh_token, string $client_id ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/publishio-oauth/v1/token' );
		$request->set_body_params(
			[
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refresh_token,
				'client_id'     => $client_id,
			]
		);

		$response = ( new Token() )->handle( $request );
		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		return $response;
	}

	/**
	 * Create an authorization code for the test client.
	 *
	 * @param int $user_id    The user who approved it.
	 * @param int $created_at When it was created.
	 */
	private function create_code( int $user_id, int $created_at ): string {
		$challenge = rtrim( strtr( base64_encode( hash( 'sha256', self::CODE_VERIFIER, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$code      = Auth_Code_Store::create( $user_id, self::CLIENT_ID, self::REDIRECT_URI, $challenge, 'mcp:read', 'https://example.org/wp-json/mcp/publishio', 'Example App' );

		$key  = 'publishio_oauth_code_' . hash( 'sha256', $code );
		$data = get_transient( $key );
		$this->assertIsArray( $data );
		$data['created_at'] = $created_at;
		set_transient( $key, $data, MINUTE_IN_SECONDS );

		return $code;
	}

	/**
	 * Send an authorization_code grant to the token endpoint's handler.
	 *
	 * @param string $code Authorization code.
	 */
	private function exchange( string $code ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/publishio-oauth/v1/token' );
		$request->set_body_params(
			[
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'client_id'     => self::CLIENT_ID,
				'redirect_uri'  => self::REDIRECT_URI,
				'code_verifier' => self::CODE_VERIFIER,
			]
		);

		$response = ( new Token() )->handle( $request );
		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		return $response;
	}
}
