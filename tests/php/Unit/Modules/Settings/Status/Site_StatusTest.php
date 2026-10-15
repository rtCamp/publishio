<?php
/**
 * Tests for the Status page's site facts and app checks.
 *
 * HTTP is mocked with pre_http_request. The mock fires http_api_curl and http_api_debug as WordPress does around a real request, so the fetcher's IP pin runs and the response is recorded.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\Settings\Status
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\Settings\Status;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\OAuth\Client\Client_Metadata_Fetcher;
use rtCamp\Publishio\Modules\MCP\OAuth\Config;
use rtCamp\Publishio\Modules\Settings\Status\Site_Status;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Site_StatusTest
 */
#[CoversClass( Site_Status::class )]
class Site_StatusTest extends TestCase {
	private const CLAUDE      = 'https://claude.ai/oauth/mcp-oauth-client-metadata';
	private const CLAUDE_CODE = 'https://claude.ai/oauth/claude-code-client-metadata';
	private const CHATGPT     = 'https://chatgpt.com/oauth/client.json';

	/**
	 * URLs that reached the HTTP layer.
	 *
	 * @var list<string>
	 */
	private array $requests = [];

	/**
	 * Facts describe the site's addresses, versions and MCP server.
	 */
	public function test_facts_describe_the_site(): void {
		$this->set_permalink_structure( '/%postname%/' );

		$facts = ( new Site_Status() )->facts();

		$this->assertSame( PUBLISHIO_VERSION, $facts['publishio_version'] );
		$this->assertSame( PHP_VERSION, $facts['php_version'] );
		$this->assertSame( 'pretty', $facts['permalinks'] );
		$this->assertSame( Config::get_mcp_resource_url(), $facts['mcp_url'] );
		$this->assertSame( rest_url( 'publishio-oauth/v1/token' ), $facts['token_url'] );
		$this->assertNotNull( $facts['mcp_adapter'] );
		$this->assertTrue( $facts['mcp_adapter']['bundled'] );
		$this->assertTrue( $facts['mcp_server'] );
		$this->assertNotSame( '', $facts['mcp_adapter']['version'] );
	}

	/**
	 * Plain permalinks are reported, since they break discovery.
	 */
	public function test_facts_report_plain_permalinks(): void {
		$this->set_permalink_structure( '' );

		$this->assertSame( 'plain', ( new Site_Status() )->facts()['permalinks'] );
	}

	/**
	 * Valid documents from every app pass, and the HTTP stack is left out.
	 */
	public function test_apps_pass_when_their_documents_validate(): void {
		$this->mock_responses( self::valid_responses() );

		$result = $this->site_status()->verify_apps();

		$this->assertSame( [ 'claude', 'claude_code', 'chatgpt' ], array_column( $result['apps'], 'id' ) );
		$this->assertSame( [ true, true, true ], array_column( $result['apps'], 'ok' ) );
		$this->assertSame( 200, $result['apps'][0]['status'] );
		$this->assertArrayNotHasKey( 'transport', $result );
	}

	/**
	 * An app that refuses the server is reported with its status and server name, without the version, and the HTTP stack is added.
	 */
	public function test_refused_request_reports_status_and_server(): void {
		$this->mock_responses( [ self::CHATGPT => [ 403, '<html></html>', 'text/html', 'cloudflare/2.4' ] ] + self::valid_responses() );

		$result  = $this->site_status()->verify_apps();
		$chatgpt = $result['apps'][2];

		$this->assertTrue( $result['apps'][0]['ok'] );
		$this->assertFalse( $chatgpt['ok'] );
		$this->assertSame( 'bad_status', $chatgpt['code'] );
		$this->assertSame( 403, $chatgpt['status'] );
		$this->assertSame( 'cloudflare', $chatgpt['server'] );
		$this->assertArrayHasKey( 'transport', $result );
		$this->assertArrayHasKey( 'block_external', $result['transport'] );
	}

	/**
	 * A failed connection keeps the cURL error, which says why.
	 */
	public function test_failed_connection_reports_the_curl_error(): void {
		$timeout = new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 5001 milliseconds with 0 bytes received' );
		$this->mock_responses( [ self::CLAUDE => $timeout ] + self::valid_responses() );

		$claude = $this->site_status()->verify_apps()['apps'][0];

		$this->assertSame( 'connection_failed', $claude['code'] );
		$this->assertSame( $timeout->get_error_message(), $claude['error'] );
	}

	/**
	 * A document the consent screen would reject is reported with the validator's reason.
	 */
	public function test_rejected_document_reports_why(): void {
		$document = (array) json_decode( self::document( self::CLAUDE ), true );
		unset( $document['redirect_uris'] );
		$this->mock_responses( [ self::CLAUDE => [ 200, (string) wp_json_encode( $document ) ] ] + self::valid_responses() );

		$claude = $this->site_status()->verify_apps()['apps'][0];

		$this->assertSame( 'invalid_document', $claude['code'] );
		$this->assertIsString( $claude['detail'] );
		$this->assertNotSame( '', $claude['detail'] );
	}

	/**
	 * With certificate checks turned off, nothing is fetched.
	 */
	public function test_disabled_certificate_checks_stop_the_fetch(): void {
		$this->mock_responses( [] );
		add_filter( 'https_ssl_verify', '__return_false' );

		$result = $this->site_status()->verify_apps();

		$this->assertSame( [ 'ssl_verify_off', 'ssl_verify_off', 'ssl_verify_off' ], array_column( $result['apps'], 'code' ) );
		$this->assertSame( [], $this->requests );
	}

	/**
	 * An app the site doesn't allow to connect is reported without fetching its document.
	 */
	public function test_app_left_out_of_allowed_hosts_is_not_allowed(): void {
		$this->mock_responses( self::valid_responses() );
		add_filter( 'publishio_cimd_allowed_hosts', static fn (): array => [ 'claude.ai' ] );

		$result = $this->site_status()->verify_apps();

		$this->assertSame( [ 'ok', 'ok', 'not_allowed' ], array_column( $result['apps'], 'code' ) );
		$this->assertNotContains( self::CHATGPT, $this->requests );
	}

	/**
	 * A web page in place of the document is reported with its content type, not blamed on the app.
	 */
	public function test_web_page_reports_its_content_type(): void {
		$this->mock_responses( [ self::CHATGPT => [ 200, '<html>Just a moment...</html>', 'text/html; charset=UTF-8' ] ] + self::valid_responses() );

		$chatgpt = $this->site_status()->verify_apps()['apps'][2];

		$this->assertSame( 'not_json', $chatgpt['code'] );
		$this->assertSame( 'text/html', $chatgpt['detail'] );
		$this->assertSame( 200, $chatgpt['status'] );
	}

	/**
	 * A document the fetcher refuses to parse is reported with the reason.
	 */
	public function test_oversized_document_reports_why(): void {
		$this->mock_responses( [ self::CLAUDE => [ 200, '{"a":"' . str_repeat( 'x', 6000 ) . '"}' ] ] + self::valid_responses() );

		$claude = $this->site_status()->verify_apps()['apps'][0];

		$this->assertSame( 'invalid_document', $claude['code'] );
		$this->assertSame( 'The document is over 5 KB.', $claude['detail'] );
	}

	/**
	 * A response from a filter that answered before cURL ran is reported as intercepted, not as missing cURL.
	 */
	public function test_answer_from_a_filter_is_intercepted(): void {
		add_filter( 'pre_http_request', static fn (): array => [ 'response' => [ 'code' => 200 ] ] );

		$result = $this->site_status()->verify_apps();

		$this->assertSame( [ 'request_intercepted', 'request_intercepted', 'request_intercepted' ], array_column( $result['apps'], 'code' ) );
	}

	/**
	 * An error from a filter that answered before cURL ran is reported as intercepted, with no status.
	 */
	public function test_error_from_a_filter_is_intercepted(): void {
		add_filter( 'pre_http_request', static fn (): \WP_Error => new \WP_Error( 'http_request_failed', 'Offline.' ) );

		$claude = $this->site_status()->verify_apps()['apps'][0];

		$this->assertSame( 'request_intercepted', $claude['code'] );
		$this->assertArrayNotHasKey( 'status', $claude );
	}

	/**
	 * A Site_Status whose fetcher resolves every host to a public address.
	 */
	private function site_status(): Site_Status {
		return new Site_Status( new Client_Metadata_Fetcher( static fn (): array => [ '93.184.215.14' ] ) );
	}

	/**
	 * A valid response for every app.
	 *
	 * @return array<string, array{0: int, 1: string}>
	 */
	private static function valid_responses(): array {
		return [
			self::CLAUDE      => [ 200, self::document( self::CLAUDE ) ],
			self::CLAUDE_CODE => [ 200, self::document( self::CLAUDE_CODE ) ],
			self::CHATGPT     => [ 200, self::document( self::CHATGPT ) ],
		];
	}

	/**
	 * A valid document for one of the apps.
	 *
	 * @param string $client_id The app's client_id URL.
	 */
	private static function document( string $client_id ): string {
		$host = (string) wp_parse_url( $client_id, PHP_URL_HOST );

		return (string) wp_json_encode(
			[
				'client_id'                  => $client_id,
				'client_name'                => 'claude.ai' === $host ? 'Claude' : 'ChatGPT',
				'redirect_uris'              => [ self::CLAUDE_CODE === $client_id ? 'http://localhost/callback' : "https://{$host}/callback" ],
				'grant_types'                => [ 'authorization_code', 'refresh_token' ],
				'response_types'             => [ 'code' ],
				'token_endpoint_auth_method' => 'none',
			]
		);
	}

	/**
	 * Answer each URL with its response, as the HTTP layer would.
	 *
	 * @param array<string, array{0: int, 1: string, 2?: string, 3?: string}|\WP_Error> $responses Status, body, content type and Server header, or an error, by URL.
	 */
	private function mock_responses( array $responses ): void {
		add_filter(
			'pre_http_request',
			function ( $_pre, array $args, string $url ) use ( $responses ) { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter -- The filter passes the request arguments second.
				$this->requests[] = $url;

				$handle = curl_init(); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
				do_action_ref_array( 'http_api_curl', [ &$handle, $args, $url ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core action.

				$response = $responses[ $url ] ?? new \WP_Error( 'http_request_failed', 'Unexpected request.' );
				if ( ! is_wp_error( $response ) ) {
					$response = [
						'headers'  => array_filter(
							[
								'content-type' => $response[2] ?? 'application/json',
								'server'       => $response[3] ?? '',
							]
						),
						'body'     => $response[1],
						'response' => [
							'code'    => $response[0],
							'message' => '',
						],
						'cookies'  => [],
						'filename' => null,
					];
				}

				do_action( 'http_api_debug', $response, 'response', 'WpOrg\Requests\Requests', $args, $url ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core action.

				return $response;
			},
			10,
			3
		);
	}
}
