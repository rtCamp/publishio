<?php
/**
 * Tests for the Client ID Metadata Document validation rules.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Client
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use rtCamp\Publishio\Modules\MCP\OAuth\Client\Client_Metadata_Validator;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Client_Metadata_ValidatorTest
 */
#[CoversClass( Client_Metadata_Validator::class )]
class Client_Metadata_ValidatorTest extends TestCase {
	private const CLIENT_ID = 'https://app.example.com/client.json';

	/**
	 * Documents as Claude, Claude Code and ChatGPT publish them.
	 *
	 * @return array<string, array{array<string, mixed>}>
	 */
	public static function real_documents(): array {
		return [
			'Claude'      => [
				[
					'client_id'                  => 'https://claude.ai/oauth/mcp-oauth-client-metadata',
					'client_name'                => 'Claude',
					'client_uri'                 => 'https://claude.ai',
					'redirect_uris'              => [ 'https://claude.ai/api/mcp/auth_callback' ],
					'grant_types'                => [ 'authorization_code', 'refresh_token', 'urn:ietf:params:oauth:grant-type:jwt-bearer' ],
					'response_types'             => [ 'code' ],
					'token_endpoint_auth_method' => 'none',
				],
			],
			'Claude Code' => [
				[
					'client_id'                  => 'https://claude.ai/oauth/claude-code-client-metadata',
					'client_name'                => 'Claude Code',
					'client_uri'                 => 'https://claude.ai',
					'redirect_uris'              => [ 'http://localhost/callback', 'http://127.0.0.1/callback' ],
					'grant_types'                => [ 'authorization_code', 'refresh_token' ],
					'response_types'             => [ 'code' ],
					'token_endpoint_auth_method' => 'none',
				],
			],
			'ChatGPT'     => [
				[
					'client_id'                       => 'https://chatgpt.com/oauth/client.json',
					'client_uri'                      => 'https://chatgpt.com/',
					'redirect_uris'                   => [ 'https://chatgpt.com/connector_platform_oauth_redirect' ],
					'token_endpoint_auth_method'      => 'private_key_jwt',
					'token_endpoint_auth_methods_supported' => [ 'none', 'private_key_jwt' ],
					'grant_types'                     => [ 'authorization_code', 'refresh_token' ],
					'response_types'                  => [ 'code' ],
					'client_name'                     => 'ChatGPT',
					'logo_uri'                        => 'https://persistent.oaistatic.com/sonic/misc/openai-logo.png',
					'token_endpoint_auth_signing_alg' => 'RS256',
					'jwks_uri'                        => 'https://chatgpt.com/oauth/jwks.json',
				],
			],
		];
	}

	/**
	 * Real client documents pass unchanged.
	 *
	 * @param array<string, mixed> $document Document as published.
	 */
	#[DataProvider( 'real_documents' )]
	public function test_accepts_real_documents( array $document ): void {
		$this->assertIsString( $document['client_id'] );
		$this->assertTrue( Client_Metadata_Validator::is_valid_client_id( $document['client_id'] ) );

		$client = Client_Metadata_Validator::validate_document( $document, $document['client_id'] );

		$this->assertIsArray( $client );
		$this->assertSame( $document['client_name'], $client['client_name'] );
		$this->assertSame( $document['redirect_uris'], $client['redirect_uris'] );
	}

	/**
	 * Client IDs that break the URL rules.
	 *
	 * @return array<string, array{string}>
	 */
	public static function invalid_client_ids(): array {
		return [
			'http'              => [ 'http://app.example.com/client.json' ],
			'no path'           => [ 'https://app.example.com' ],
			'root path'         => [ 'https://app.example.com/' ],
			'uppercase host'    => [ 'https://App.example.com/client.json' ],
			'port'              => [ 'https://app.example.com:443/client.json' ],
			'userinfo'          => [ 'https://user@app.example.com/client.json' ],
			'query'             => [ 'https://app.example.com/client.json?v=1' ],
			'fragment'          => [ 'https://app.example.com/client.json#x' ],
			'dot segment'       => [ 'https://app.example.com/a/./client.json' ],
			'dot-dot segment'   => [ 'https://app.example.com/a/../client.json' ],
			'percent-encoding'  => [ 'https://app.example.com/%2e%2e/client.json' ],
			'trailing newline'  => [ self::CLIENT_ID . "\n" ],
			'IPv4 host'         => [ 'https://127.0.0.1/client.json' ],
			'single-label host' => [ 'https://localhost/client.json' ],
			'.local host'       => [ 'https://printer.local/client.json' ],
			'.internal host'    => [ 'https://metadata.google.internal/client.json' ],
			'non-ASCII host'    => [ 'https://exämple.com/client.json' ],
			'too long'          => [ 'https://app.example.com/' . str_repeat( 'a', 240 ) ],
		];
	}

	/**
	 * Malformed or unsafe client IDs are rejected before any network activity.
	 *
	 * @param string $client_id The client_id to check.
	 */
	#[DataProvider( 'invalid_client_ids' )]
	public function test_rejects_invalid_client_ids( string $client_id ): void {
		$this->assertFalse( Client_Metadata_Validator::is_valid_client_id( $client_id ) );
	}

	/**
	 * A client_id hosted on this site is rejected.
	 */
	public function test_rejects_client_id_on_this_site(): void {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		$this->assertFalse( Client_Metadata_Validator::is_valid_client_id( "https://{$host}/client.json" ) );
	}

	/**
	 * The allowlist filter narrows the accepted hosts to the listed ones and their subdomains.
	 */
	public function test_allowlist_filter_narrows_hosts(): void {
		add_filter( 'publishio_cimd_allowed_hosts', static fn (): array => [ 'claude.ai' ] );

		$this->assertTrue( Client_Metadata_Validator::is_valid_client_id( 'https://claude.ai/oauth/client' ) );
		$this->assertTrue( Client_Metadata_Validator::is_valid_client_id( 'https://beta.claude.ai/oauth/client' ) );
		$this->assertFalse( Client_Metadata_Validator::is_valid_client_id( 'https://chatgpt.com/oauth/client.json' ) );
	}

	/**
	 * Documents that break one rule each.
	 *
	 * @return array<string, array{array<string, mixed>}>
	 */
	public static function invalid_documents(): array {
		$too_many = array_map( static fn ( int $i ): string => "https://app.example.com/cb{$i}", range( 1, 21 ) );

		return [
			'mismatched client_id'      => [ [ 'client_id' => 'https://other.example.com/client.json' ] ],
			'missing name'              => [ [ 'client_name' => null ] ],
			'name too long'             => [ [ 'client_name' => str_repeat( 'a', 101 ) ] ],
			'name empty after sanitize' => [ [ 'client_name' => '<b></b>' ] ],
			'name grows past the limit' => [ [ 'client_name' => str_repeat( '< ', 49 ) . '<' ] ],
			'name with RTL override'    => [ [ 'client_name' => "Claude\u{202E}" ] ],
			'name with control char'    => [ [ 'client_name' => "App\x1B" ] ],
			'client secret'             => [ [ 'client_secret' => 'abc' ] ],
			'private_key_jwt only'      => [ [ 'token_endpoint_auth_method' => 'private_key_jwt' ] ],
			'shared-secret method'      => [ [ 'token_endpoint_auth_methods_supported' => [ 'none', 'client_secret_post' ] ] ],
			'no authorization_code'     => [ [ 'grant_types' => [ 'client_credentials' ] ] ],
			'no code response type'     => [ [ 'response_types' => [ 'token' ] ] ],
			'no redirect URIs'          => [ [ 'redirect_uris' => [] ] ],
			'redirect URIs not a list'  => [ [ 'redirect_uris' => [ 'a' => 'https://app.example.com/cb' ] ] ],
			'non-string redirect URI'   => [ [ 'redirect_uris' => [ 'https://app.example.com/cb', 5 ] ] ],
			'too many redirect URIs'    => [ [ 'redirect_uris' => $too_many ] ],
			'only other-host redirects' => [ [ 'redirect_uris' => [ 'https://evil.example.net/cb' ] ] ],
		];
	}

	/**
	 * Each rule violation rejects the document.
	 *
	 * @param array<string, mixed> $changes Fields that override a valid document; null removes a field.
	 */
	#[DataProvider( 'invalid_documents' )]
	public function test_rejects_invalid_documents( array $changes ): void {
		$result = Client_Metadata_Validator::validate_document( self::document( $changes ), self::CLIENT_ID );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_client_metadata', $result->get_error_code() );
	}

	/**
	 * The name is sanitized before the checks, so the stored name is the checked one.
	 */
	public function test_name_is_sanitized_before_checks(): void {
		$client = Client_Metadata_Validator::validate_document( self::document( [ 'client_name' => '  My <b>App</b>  ' ] ), self::CLIENT_ID );

		$this->assertIsArray( $client );
		$this->assertSame( 'My App', $client['client_name'] );
	}

	/**
	 * Names in any language and with emoji pass.
	 */
	public function test_accepts_international_names(): void {
		foreach ( [ 'Café Écrit', '通义千问', 'Яндекс', 'App 🚀', 'Le Chat (Mistral)' ] as $name ) {
			$client = Client_Metadata_Validator::validate_document( self::document( [ 'client_name' => $name ] ), self::CLIENT_ID );

			$this->assertIsArray( $client, $name );
			$this->assertSame( $name, $client['client_name'] );
		}
	}

	/**
	 * Redirect URIs outside the policy are dropped, and optional links are kept only on the client's host.
	 */
	public function test_filters_redirect_uris_and_links(): void {
		$client = Client_Metadata_Validator::validate_document(
			self::document(
				[
					'redirect_uris' => [ 'https://app.example.com/cb', 'https://evil.example.net/cb', 'http://app.example.com/cb' ],
					'client_uri'    => 'https://app.example.com/',
					'tos_uri'       => 'https://app.example.com/legal#terms',
					'policy_uri'    => 'https://evil.example.net/privacy',
				]
			),
			self::CLIENT_ID
		);

		$this->assertIsArray( $client );
		$this->assertSame( [ 'https://app.example.com/cb' ], $client['redirect_uris'] );
		$this->assertSame( 'https://app.example.com/', $client['client_uri'] );
		$this->assertSame( 'https://app.example.com/legal#terms', $client['tos_uri'] );
		$this->assertNull( $client['policy_uri'] );
	}

	/**
	 * Links with credentials are dropped.
	 */
	public function test_drops_links_with_credentials(): void {
		foreach ( [ 'https://user@app.example.com/tos', 'https://:pw@app.example.com/tos' ] as $uri ) {
			$client = Client_Metadata_Validator::validate_document( self::document( [ 'tos_uri' => $uri ] ), self::CLIENT_ID );

			$this->assertIsArray( $client );
			$this->assertNull( $client['tos_uri'], $uri );
		}
	}

	/**
	 * Redirect URIs a client on app.example.com may register.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function redirect_uris(): array {
		return [
			'https same host'          => [ 'https://app.example.com/cb', true ],
			'https subdomain'          => [ 'https://eu.app.example.com/cb', true ],
			'https other host'         => [ 'https://example.com/cb', false ],
			'http non-loopback'        => [ 'http://app.example.com/cb', false ],
			'http 127.0.0.1 with port' => [ 'http://127.0.0.1:5000/cb', true ],
			'http localhost'           => [ 'http://localhost/cb', true ],
			'http [::1]'               => [ 'http://[::1]:5000/cb', true ],
			'fragment'                 => [ 'https://app.example.com/cb#x', false ],
			'space'                    => [ 'https://app.example.com/c b', false ],
			'tab'                      => [ "https://app.example.com/cb\t", false ],
			'DEL'                      => [ "https://app.example.com/cb\x7F", false ],
			'userinfo'                 => [ 'https://user@app.example.com/cb', false ],
			'javascript scheme'        => [ 'javascript:alert(1)', false ],
		];
	}

	/**
	 * The redirect policy: https on the client's host, http only on loopback.
	 *
	 * @param string $uri     Redirect URI.
	 * @param bool   $allowed Expected result.
	 */
	#[DataProvider( 'redirect_uris' )]
	public function test_redirect_uri_policy( string $uri, bool $allowed ): void {
		$this->assertSame( $allowed, Client_Metadata_Validator::is_allowed_redirect_uri( $uri, 'app.example.com' ) );
	}

	/**
	 * Requested redirect URIs against the registered ones.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function redirect_matches(): array {
		return [
			'exact https'                => [ 'https://app.example.com/cb', true ],
			'https other path'           => [ 'https://app.example.com/other', false ],
			'https added port'           => [ 'https://app.example.com:8443/cb', false ],
			'loopback any port'          => [ 'http://localhost:54321/callback', true ],
			'loopback without port'      => [ 'http://localhost/callback', true ],
			'localhost is not 127.0.0.1' => [ 'http://127.0.0.1:54321/callback', false ],
			'loopback other path'        => [ 'http://localhost:54321/other', false ],
			'loopback added query'       => [ 'http://localhost:54321/callback?x=1', false ],
		];
	}

	/**
	 * Exact match, except a loopback redirect may use any port.
	 *
	 * @param string $requested Requested redirect URI.
	 * @param bool   $matches   Expected result.
	 */
	#[DataProvider( 'redirect_matches' )]
	public function test_redirect_uri_matching( string $requested, bool $matches ): void {
		$registered = [ 'https://app.example.com/cb', 'http://localhost/callback' ];

		$this->assertSame( $matches, Client_Metadata_Validator::matches_redirect_uri( $requested, $registered ) );
	}

	/**
	 * A valid document, with fields replaced or removed.
	 *
	 * @param array<string, mixed> $changes Fields to replace; null removes a field.
	 *
	 * @return array<string, mixed>
	 */
	private static function document( array $changes = [] ): array {
		$document = array_merge(
			[
				'client_id'                  => self::CLIENT_ID,
				'client_name'                => 'Example App',
				'redirect_uris'              => [ 'https://app.example.com/cb' ],
				'token_endpoint_auth_method' => 'none',
			],
			$changes
		);

		return array_filter( $document, static fn ( $value ): bool => null !== $value );
	}
}
