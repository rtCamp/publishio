<?php
/**
 * Site facts and outgoing app checks for the Status page.
 *
 * @package rtCamp\Publishio\Modules\Settings\Status
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\Settings\Status;

use WP\MCP\Core\McpAdapter;
use rtCamp\Publishio\Modules\MCP\OAuth\Client\Client_Metadata_Fetcher;
use rtCamp\Publishio\Modules\MCP\OAuth\Client\Client_Metadata_Validator;
use rtCamp\Publishio\Modules\MCP\OAuth\Config;
use rtCamp\Publishio\Modules\MCP\Server\Server;

/**
 * Class - Site_Status
 */
final class Site_Status {
	/**
	 * Apps whose Client ID Metadata Documents the site must be able to fetch.
	 */
	public const APPS = [
		'claude'      => 'https://claude.ai/oauth/mcp-oauth-client-metadata',
		'claude_code' => 'https://claude.ai/oauth/claude-code-client-metadata',
		'chatgpt'     => 'https://chatgpt.com/oauth/client.json',
	];

	/**
	 * Maps Client_Metadata_Fetcher failure reasons to the codes the Status page explains.
	 */
	private const FETCH_FAILURES = [
		'blocked_by_wordpress' => 'blocked_by_wordpress',
		'no_curl'              => 'no_curl',
		'ssl_verify_off'       => 'ssl_verify_off',
		'host_not_public'      => 'dns',
		'pin_failed'           => 'request_intercepted',
		'request_failed'       => 'connection_failed',
		'status_not_200'       => 'bad_status',
		'not_json'             => 'not_json',
	];

	/**
	 * Why the fetcher rejected a response body, for the remaining reasons.
	 */
	private const DOCUMENT_FAILURES = [
		'too_large'     => 'The document is over 5 KB.',
		'invalid_json'  => 'The body is not valid JSON.',
		'not_an_object' => 'The JSON is not an object.',
	];

	/**
	 * Fetches the apps' documents the same way the consent screen does.
	 */
	private Client_Metadata_Fetcher $fetcher;

	/**
	 * Constructor.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\OAuth\Client\Client_Metadata_Fetcher|null $fetcher Document fetcher.
	 */
	public function __construct( ?Client_Metadata_Fetcher $fetcher = null ) {
		$this->fetcher = $fetcher ?? new Client_Metadata_Fetcher();
	}

	/**
	 * Site details that explain check results and fill the report.
	 *
	 * @return array{publishio_version: string, wp_version: string, php_version: string, multisite: bool, home_url: string, site_url: string, mcp_url: string, token_url: string, permalinks: string, mcp_adapter: array{version: string, bundled: bool, source: string}|null, mcp_server: bool, tools: int}
	 */
	public function facts(): array {
		$server = Server::get_server();

		return [
			'publishio_version' => PUBLISHIO_VERSION,
			'wp_version'        => (string) get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'multisite'         => is_multisite(),
			'home_url'          => home_url(),
			'site_url'          => site_url(),
			'mcp_url'           => Config::get_mcp_resource_url(),
			'token_url'         => rest_url( Config::OAUTH_REST_NAMESPACE . '/token' ),
			'permalinks'        => '' === (string) get_option( 'permalink_structure' ) ? 'plain' : 'pretty',
			'mcp_adapter'       => self::mcp_adapter(),
			'mcp_server'        => null !== $server,
			'tools'             => null === $server ? 0 : count( $server->get_tools() ),
		];
	}

	/**
	 * Fetch and validate each app's document, as the consent screen would.
	 *
	 * @return array{apps: list<array<string, mixed>>, transport?: array<string, mixed>}
	 */
	public function verify_apps(): array {
		$apps = [];
		foreach ( self::APPS as $id => $client_id ) {
			$apps[] = [ 'id' => $id ] + $this->verify( $client_id );
		}

		$result = [ 'apps' => $apps ];
		if ( in_array( false, array_column( $apps, 'ok' ), true ) ) {
			$result['transport'] = self::transport();
		}

		return $result;
	}

	/**
	 * Check one app's document.
	 *
	 * @param string $client_id The app's client_id URL.
	 *
	 * @return array<string, mixed>
	 */
	private function verify( string $client_id ): array {
		$result = [ 'url' => $client_id ];

		if ( ! Client_Metadata_Validator::is_valid_client_id( $client_id ) ) {
			return $result + [
				'ok'   => false,
				'code' => 'not_allowed',
			];
		}

		// The fetcher keeps only a reason code, so record the raw response for the status or cURL error.
		$response = null;
		$capture  = static function ( $http_response, $_context, $_class, $_args, $url ) use ( $client_id, &$response ): void { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter, Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed
			if ( $url === $client_id ) {
				$response = $http_response;
			}
		};

		$started = microtime( true );
		add_action( 'http_api_debug', $capture, 10, 5 );
		$fetched = $this->fetcher->fetch( $client_id );
		remove_action( 'http_api_debug', $capture, 10 );
		$result['ms'] = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $response ) ) {
			$result['error'] = self::without_proxy_host( $response->get_error_message() );
		} elseif ( is_array( $response ) ) {
			$result['status'] = (int) wp_remote_retrieve_response_code( $response );
			$result['server'] = self::product( wp_remote_retrieve_header( $response, 'server' ) );
		}

		if ( is_wp_error( $fetched ) ) {
			$reason = $fetched->get_error_message();
			$code   = self::FETCH_FAILURES[ $reason ] ?? 'invalid_document';
			$result = $result + [
				'ok'   => false,
				'code' => $code,
			];

			if ( 'not_json' === $code && is_array( $response ) ) {
				$result['detail'] = self::media_type( wp_remote_retrieve_header( $response, 'content-type' ) );
			} elseif ( 'invalid_document' === $code ) {
				$result['detail'] = self::DOCUMENT_FAILURES[ $reason ] ?? $reason;
			}

			return $result;
		}

		$client = Client_Metadata_Validator::validate_document( $fetched['document'], $client_id );
		if ( is_wp_error( $client ) ) {
			return $result + [
				'ok'     => false,
				'code'   => 'invalid_document',
				'detail' => $client->get_error_message(),
			];
		}

		return $result + [
			'ok'   => true,
			'code' => 'ok',
		];
	}

	/**
	 * The HTTP stack, reported when an app check fails.
	 *
	 * @return array{curl: string|null, ssl: string|null, block_external: bool, proxy: bool}
	 */
	private static function transport(): array {
		$curl = function_exists( 'curl_version' ) ? curl_version() : false;

		return [
			'curl'           => is_array( $curl ) ? (string) $curl['version'] : null,
			'ssl'            => is_array( $curl ) ? (string) $curl['ssl_version'] : null,
			'block_external' => defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && (bool) constant( 'WP_HTTP_BLOCK_EXTERNAL' ),
			'proxy'          => ( new \WP_HTTP_Proxy() )->is_enabled(),
		];
	}

	/**
	 * The loaded MCP Adapter's version and the plugin folder it came from.
	 *
	 * @return array{version: string, bundled: bool, source: string}|null
	 */
	private static function mcp_adapter(): ?array {
		if ( ! class_exists( McpAdapter::class ) ) {
			return null;
		}

		$file    = wp_normalize_path( (string) ( new \ReflectionClass( McpAdapter::class ) )->getFileName() );
		$plugins = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );
		$source  = str_starts_with( $file, $plugins ) ? strtok( substr( $file, strlen( $plugins ) ), '/' ) : false;
		$version = defined( McpAdapter::class . '::VERSION' ) ? constant( McpAdapter::class . '::VERSION' ) : '';

		return [
			'version' => is_string( $version ) ? $version : '',
			'bundled' => str_starts_with( $file, trailingslashit( wp_normalize_path( dirname( PUBLISHIO_FILE ) ) ) ),
			'source'  => false === $source ? '' : $source,
		];
	}

	/**
	 * A Server header's product name, without its version.
	 *
	 * @param string|array<string> $header Header value.
	 */
	private static function product( string|array $header ): string {
		$value = is_array( $header ) ? (string) reset( $header ) : $header;

		return (string) strtok( $value, '/ ' );
	}

	/**
	 * A Content-Type header's media type, without parameters.
	 *
	 * @param string|array<string> $header Header value.
	 */
	private static function media_type( string|array $header ): string {
		$value = is_array( $header ) ? (string) reset( $header ) : $header;

		return substr( strtolower( trim( explode( ';', $value )[0] ) ), 0, 100 );
	}

	/**
	 * An error message with the proxy's host name taken out.
	 *
	 * @param string $message Error message.
	 */
	private static function without_proxy_host( string $message ): string {
		$proxy = new \WP_HTTP_Proxy();

		return $proxy->is_enabled() && '' !== $proxy->host() ? str_replace( $proxy->host(), 'the proxy', $message ) : $message;
	}
}
