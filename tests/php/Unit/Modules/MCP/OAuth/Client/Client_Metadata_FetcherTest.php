<?php
/**
 * Tests for fetching Client ID Metadata Documents: address checks, response checks and cache lifetime.
 *
 * HTTP is mocked with pre_http_request, which runs before cURL, so the IP pin itself is covered by repro-04.sh instead.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Client
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use rtCamp\Publishio\Modules\MCP\OAuth\Client\Client_Metadata_Fetcher;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Client_Metadata_FetcherTest
 */
#[CoversClass( Client_Metadata_Fetcher::class )]
class Client_Metadata_FetcherTest extends TestCase {
	private const CLIENT_ID = 'https://app.example.com/client.json';
	private const PUBLIC_IP = '93.184.215.14';

	/**
	 * Arguments of each request that reached the HTTP layer.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $requests = [];

	/**
	 * Internal and special-use addresses that must never be fetched.
	 *
	 * @return array<string, array{list<string>}>
	 */
	public static function blocked_addresses(): array {
		return [
			'no addresses'         => [ [] ],
			'loopback'             => [ [ '127.0.0.1' ] ],
			'private 10/8'         => [ [ '10.0.0.1' ] ],
			'private 172.16/12'    => [ [ '172.16.0.1' ] ],
			'private 192.168/16'   => [ [ '192.168.1.1' ] ],
			'cloud metadata'       => [ [ '169.254.169.254' ] ],
			'carrier-grade NAT'    => [ [ '100.64.0.1' ] ],
			'unspecified'          => [ [ '0.0.0.0' ] ],
			'IPv6 loopback'        => [ [ '::1' ] ],
			'IPv6 unique local'    => [ [ 'fc00::1' ] ],
			'IPv6 link-local'      => [ [ 'fe80::1' ] ],
			'IPv4-mapped loopback' => [ [ '::ffff:127.0.0.1' ] ],
			'public and private'   => [ [ self::PUBLIC_IP, '10.0.0.1' ] ],
		];
	}

	/**
	 * A host with any non-public address is refused before a request is made.
	 *
	 * @param string[] $ips Addresses the host resolves to.
	 */
	#[DataProvider( 'blocked_addresses' )]
	public function test_refuses_hosts_with_non_public_addresses( array $ips ): void {
		$this->mock_response( 200, '{}' );

		$result = ( new Client_Metadata_Fetcher( static fn (): array => $ips ) )->fetch( self::CLIENT_ID );

		$this->assertFetchFailed( 'host_not_public', $result );
		$this->assertSame( [], $this->requests );
	}

	/**
	 * A valid document is decoded, and the request is made without redirects, with TLS checks and a size cap.
	 */
	public function test_fetches_valid_document(): void {
		$this->mock_response( 200, '{"client_id":"' . self::CLIENT_ID . '"}', 'application/json; charset=utf-8' );

		$result = $this->fetcher()->fetch( self::CLIENT_ID );

		$this->assertIsArray( $result );
		$this->assertSame( [ 'client_id' => self::CLIENT_ID ], $result['document'] );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 0, $this->requests[0]['redirection'] );
		$this->assertTrue( $this->requests[0]['sslverify'] );
		$this->assertSame( 5121, $this->requests[0]['limit_response_size'] );
	}

	/**
	 * Responses that must not be accepted as a document.
	 *
	 * @return array<string, array{int, string, string, string}>
	 */
	public static function bad_responses(): array {
		$nested = str_repeat( '{"a":', 9 ) . '1' . str_repeat( '}', 9 );

		return [
			'redirect'                   => [ 301, '{}', 'application/json', 'status_not_200' ],
			'not found'                  => [ 404, '{}', 'application/json', 'status_not_200' ],
			'server error'               => [ 500, '{}', 'application/json', 'status_not_200' ],
			'HTML'                       => [ 200, '{}', 'text/html', 'not_json' ],
			'server-defined JSON type'   => [ 200, '{}', 'application/vnd.api+json', 'not_json' ],
			'over 5 KB'                  => [ 200, '{"a":"' . str_repeat( 'x', 5115 ) . '"}', 'application/json', 'too_large' ],
			'invalid JSON'               => [ 200, '{not json', 'application/json', 'invalid_json' ],
			'nested deeper than 8'       => [ 200, $nested, 'application/json', 'invalid_json' ],
			'JSON list, not an object'   => [ 200, '["a"]', 'application/json', 'not_an_object' ],
			'empty JSON object'          => [ 200, '{}', 'application/json', 'not_an_object' ],
			'JSON scalar, not an object' => [ 200, '"a"', 'application/json', 'not_an_object' ],
		];
	}

	/**
	 * Each bad response fails with its reason code.
	 *
	 * @param int    $status       HTTP status.
	 * @param string $body         Response body.
	 * @param string $content_type Content-Type header.
	 * @param string $reason       Expected failure reason.
	 */
	#[DataProvider( 'bad_responses' )]
	public function test_rejects_bad_responses( int $status, string $body, string $content_type, string $reason ): void {
		$this->mock_response( $status, $body, $content_type );

		$this->assertFetchFailed( $reason, $this->fetcher()->fetch( self::CLIENT_ID ) );
	}

	/**
	 * Nothing is fetched when the site turns TLS verification off.
	 */
	public function test_refuses_when_tls_verification_is_off(): void {
		$this->mock_response( 200, '{}' );
		add_filter( 'https_ssl_verify', '__return_false' );

		$this->assertFetchFailed( 'secure_transport_unavailable', $this->fetcher()->fetch( self::CLIENT_ID ) );
		$this->assertSame( [], $this->requests );
	}

	/**
	 * Cache-Control headers and the cache lifetime they give.
	 *
	 * @return array<string, array{string, int}>
	 */
	public static function cache_headers(): array {
		return [
			'no header'               => [ '', 60 ],
			'no-store'                => [ 'no-store', 60 ],
			'no-cache with max-age'   => [ 'no-cache, max-age=600', 60 ],
			'private'                 => [ 'private, max-age=600', 60 ],
			'max-age in range'        => [ 'public, max-age=300', 300 ],
			'max-age below the floor' => [ 'max-age=30', 60 ],
			'max-age above the cap'   => [ 'max-age=86400', 3600 ],
			's-maxage wins'           => [ 's-maxage=120, max-age=600', 120 ],
		];
	}

	/**
	 * The lifetime follows Cache-Control, clamped to 1 minute – 1 hour.
	 *
	 * @param string $cache_control Cache-Control header.
	 * @param int    $ttl           Expected lifetime in seconds.
	 */
	#[DataProvider( 'cache_headers' )]
	public function test_cache_lifetime( string $cache_control, int $ttl ): void {
		$this->mock_response( 200, '{"a":1}', 'application/json', $cache_control );

		$result = $this->fetcher()->fetch( self::CLIENT_ID );

		$this->assertIsArray( $result );
		$this->assertSame( $ttl, $result['ttl'] );
	}

	/**
	 * A fetcher whose host resolves to a public address.
	 */
	private function fetcher(): Client_Metadata_Fetcher {
		return new Client_Metadata_Fetcher( static fn (): array => [ self::PUBLIC_IP ] );
	}

	/**
	 * Answer every HTTP request with this response, and record the request arguments.
	 *
	 * @param int    $status        HTTP status.
	 * @param string $body          Response body.
	 * @param string $content_type  Content-Type header.
	 * @param string $cache_control Cache-Control header.
	 */
	private function mock_response( int $status, string $body, string $content_type = 'application/json', string $cache_control = '' ): void {
		add_filter(
			'pre_http_request',
			function ( $_pre, array $args ) use ( $status, $body, $content_type, $cache_control ): array { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter -- The filter passes the request arguments second.
				$this->requests[] = $args;

				return [
					'headers'  => array_filter(
						[
							'content-type'  => $content_type,
							'cache-control' => $cache_control,
						]
					),
					'body'     => $body,
					'response' => [
						'code'    => $status,
						'message' => '',
					],
					'cookies'  => [],
					'filename' => null,
				];
			},
			10,
			2
		);
	}

	/**
	 * Assert that a fetch failed with the given reason.
	 *
	 * @param string $reason Expected reason code.
	 * @param mixed  $result Result of fetch().
	 */
	private function assertFetchFailed( string $reason, mixed $result ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Matches PHPUnit's assertion naming.
		$this->assertWPError( $result );
		$this->assertSame( 'client_metadata_unavailable', $result->get_error_code() );
		$this->assertSame( $reason, $result->get_error_message() );
	}
}
