<?php
/**
 * Tests for the token endpoint's refresh_token grant.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Endpoint
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Endpoint;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\OAuth\Endpoint\Token;
use rtCamp\Publishio\Modules\MCP\OAuth\Storage\Token_Store;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - TokenTest
 */
#[CoversClass( Token::class )]
class TokenTest extends TestCase {
	private const CLIENT_ID = 'https://app.example.com/client.json';

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
}
