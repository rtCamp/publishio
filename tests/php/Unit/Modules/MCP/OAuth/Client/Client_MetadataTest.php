<?php
/**
 * Tests for resolving clients: caching, the per-user fetch limit and bundled logos.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Client
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\OAuth\Client\Client_Metadata;
use rtCamp\Publishio\Modules\MCP\OAuth\Client\Client_Metadata_Fetcher;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Client_MetadataTest
 */
#[CoversClass( Client_Metadata::class )]
class Client_MetadataTest extends TestCase {
	private const CLIENT_ID = 'https://app.example.com/client.json';

	/**
	 * Number of requests that reached the HTTP layer.
	 */
	private int $requests = 0;

	/**
	 * Run each test as a logged-in user, since the fetch limit is per user.
	 */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create() );
	}

	/**
	 * A valid document is cached, so the second lookup makes no request.
	 */
	public function test_valid_document_is_cached(): void {
		$this->mock_response( 200 );

		$first  = $this->client_metadata()->get( self::CLIENT_ID );
		$second = $this->client_metadata()->get( self::CLIENT_ID );

		$this->assertIsArray( $first );
		$this->assertSame( 'Example App', $first['client_name'] );
		$this->assertSame( $first, $second );
		$this->assertSame( 1, $this->requests );
	}

	/**
	 * Failures are not cached, so the next lookup fetches again.
	 */
	public function test_failures_are_not_cached(): void {
		$this->mock_response( 404 );

		$this->assertWPError( $this->client_metadata()->get( self::CLIENT_ID ) );
		$this->assertWPError( $this->client_metadata()->get( self::CLIENT_ID ) );
		$this->assertSame( 2, $this->requests );
	}

	/**
	 * A cache entry that isn't a valid record for this client is ignored.
	 */
	public function test_malformed_cache_entry_is_ignored(): void {
		$this->mock_response( 200 );
		set_transient( 'publishio_cimd_' . hash( 'sha256', self::CLIENT_ID ), [ 'client_id' => 'https://other.example.com/x' ], 60 );

		$this->assertIsArray( $this->client_metadata()->get( self::CLIENT_ID ) );
		$this->assertSame( 1, $this->requests );
	}

	/**
	 * A client_id that isn't a metadata URL, such as an old dcr_ id, fails without a request and tells the user how to recover.
	 */
	public function test_invalid_client_id_is_refused_without_a_request(): void {
		$this->mock_response( 200 );

		$result = $this->client_metadata()->get( 'dcr_abc123' );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_client', $result->get_error_code() );
		$this->assertStringContainsString( 'disconnect the connector in your AI app and connect it again', $result->get_error_message() );
		$this->assertSame( 0, $this->requests );
	}

	/**
	 * The 11th uncached fetch within the window is refused.
	 */
	public function test_eleventh_uncached_fetch_is_rate_limited(): void {
		$this->mock_response( 404 );

		for ( $i = 0; $i < 10; $i++ ) {
			$this->client_metadata()->get( self::CLIENT_ID );
		}
		$result = $this->client_metadata()->get( self::CLIENT_ID );

		$this->assertWPError( $result );
		$this->assertSame( 'rate_limited', $result->get_error_code() );
		$this->assertSame( 10, $this->requests );
	}

	/**
	 * The window is fixed: a fetch counts against it without moving its end.
	 */
	public function test_fetch_does_not_extend_the_window(): void {
		$this->mock_response( 404 );
		$reset = time() + 100;
		set_transient(
			$this->budget_key(),
			[
				'count' => 3,
				'reset' => $reset,
			],
			100
		);

		$this->client_metadata()->get( self::CLIENT_ID );

		$this->assertSame(
			[
				'count' => 4,
				'reset' => $reset,
			],
			get_transient( $this->budget_key() )
		);
	}

	/**
	 * A spent budget is renewed once its window has ended.
	 */
	public function test_new_window_starts_after_reset(): void {
		$this->mock_response( 404 );
		set_transient(
			$this->budget_key(),
			[
				'count' => 10,
				'reset' => time() - 1,
			],
			100
		);

		$this->client_metadata()->get( self::CLIENT_ID );

		$this->assertSame( 1, $this->requests );
		$budget = get_transient( $this->budget_key() );
		$this->assertIsArray( $budget );
		$this->assertSame( 1, $budget['count'] );
	}

	/**
	 * The bundled logo follows the verified host, never a look-alike domain.
	 */
	public function test_logo_url_follows_host(): void {
		$this->assertStringEndsWith( 'claude-logo.svg', (string) Client_Metadata::logo_url( 'https://claude.ai/oauth/mcp-oauth-client-metadata' ) );
		$this->assertStringEndsWith( 'claude-logo.svg', (string) Client_Metadata::logo_url( 'https://api.anthropic.com/client.json' ) );
		$this->assertStringEndsWith( 'openai-logo.svg', (string) Client_Metadata::logo_url( 'https://chatgpt.com/oauth/client.json' ) );
		$this->assertNull( Client_Metadata::logo_url( 'https://claude.ai.example.com/client.json' ) );
		$this->assertNull( Client_Metadata::logo_url( 'https://notclaude.ai/client.json' ) );
	}

	/**
	 * A resolver with a public address, so requests reach the mocked HTTP layer.
	 */
	private function client_metadata(): Client_Metadata {
		return new Client_Metadata( new Client_Metadata_Fetcher( static fn (): array => [ '93.184.215.14' ] ) );
	}

	/**
	 * Transient key of the current user's fetch budget.
	 */
	private function budget_key(): string {
		return 'publishio_cimd_rl_' . get_current_user_id();
	}

	/**
	 * Answer every HTTP request with a valid document and this status, and count the requests.
	 *
	 * @param int $status HTTP status.
	 */
	private function mock_response( int $status ): void {
		$document = [
			'client_id'                  => self::CLIENT_ID,
			'client_name'                => 'Example App',
			'redirect_uris'              => [ 'https://app.example.com/cb' ],
			'token_endpoint_auth_method' => 'none',
		];

		add_filter(
			'pre_http_request',
			function () use ( $status, $document ): array {
				++$this->requests;

				return [
					'headers'  => [ 'content-type' => 'application/json' ],
					'body'     => (string) wp_json_encode( $document ),
					'response' => [
						'code'    => $status,
						'message' => '',
					],
					'cookies'  => [],
					'filename' => null,
				];
			}
		);
	}
}
