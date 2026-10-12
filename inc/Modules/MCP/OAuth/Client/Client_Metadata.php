<?php
/**
 * Resolves OAuth clients from their Client ID Metadata Documents (CIMD), with caching and a per-user fetch limit.
 *
 * @package rtCamp\Publishio\Modules\MCP\OAuth\Client
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\OAuth\Client;

/**
 * Class - Client_Metadata
 */
final class Client_Metadata {
	private const CACHE_PREFIX      = 'publishio_cimd_';
	private const RATE_LIMIT_PREFIX = 'publishio_cimd_rl_';
	private const RATE_LIMIT        = 10;
	private const RATE_WINDOW       = 600;

	/**
	 * Client hosts with a logo bundled in assets/images/provider/.
	 */
	public const APP_HOSTS = [
		'claude' => [ 'claude.ai', 'anthropic.com' ],
		'openai' => [ 'openai.com', 'chatgpt.com' ],
	];

	/**
	 * Fetches documents on a cache miss.
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
	 * Validated metadata for a client, from the cache or a fresh fetch.
	 *
	 * Only call this for a logged-in user: a cache miss fetches a URL taken from the request.
	 *
	 * @param string $client_id The client_id from the request.
	 *
	 * @return array{client_id: string, client_name: string, redirect_uris: list<string>, client_uri: ?string, tos_uri: ?string, policy_uri: ?string}|\WP_Error
	 */
	public function get( string $client_id ): array|\WP_Error {
		if ( ! Client_Metadata_Validator::is_valid_client_id( $client_id ) ) {
			return self::invalid_client_id_error();
		}

		$cache_key = self::CACHE_PREFIX . hash( 'sha256', $client_id );
		$cached    = self::from_cache( get_transient( $cache_key ), $client_id );
		if ( null !== $cached ) {
			return $cached;
		}

		if ( ! $this->consume_fetch_budget() ) {
			return new \WP_Error( 'rate_limited', __( 'Too many authorization attempts. Please try again in a few minutes.', 'publishio' ), [ 'status' => 429 ] );
		}

		$fetched = $this->fetcher->fetch( $client_id );
		if ( is_wp_error( $fetched ) ) {
			return self::unverified( $fetched );
		}

		$client = Client_Metadata_Validator::validate_document( $fetched['document'], $client_id );
		if ( is_wp_error( $client ) ) {
			return self::unverified( $client );
		}

		set_transient( $cache_key, $client, $fetched['ttl'] );

		return $client;
	}

	/**
	 * Error for a client_id that isn't a client metadata document URL, such as one registered by an older Publishio version.
	 */
	public static function invalid_client_id_error(): \WP_Error {
		return new \WP_Error( 'invalid_client', __( 'client_id must be the HTTPS URL of a client metadata document. If this keeps happening, disconnect the connector in your AI app and connect it again.', 'publishio' ), [ 'status' => 400 ] );
	}

	/**
	 * Host of a client_id URL, shown to users as the client's verified domain.
	 *
	 * @param string $client_id A client_id URL.
	 */
	public static function host( string $client_id ): string {
		return (string) wp_parse_url( $client_id, PHP_URL_HOST );
	}

	/**
	 * URL of the bundled logo for a well-known client, chosen by host so a document can't claim another brand's logo.
	 *
	 * @param string $client_id A client_id URL.
	 */
	public static function logo_url( string $client_id ): ?string {
		$host = self::host( $client_id );

		foreach ( self::APP_HOSTS as $app => $domains ) {
			foreach ( $domains as $domain ) {
				if ( Client_Metadata_Validator::is_same_or_subdomain( $host, $domain ) ) {
					return plugins_url( "assets/images/provider/{$app}-logo.svg", PUBLISHIO_FILE );
				}
			}
		}

		return null;
	}

	/**
	 * Rebuild a cached entry, or null if it is missing or not a well-formed record for this client.
	 *
	 * @param mixed  $cached    Value from get_transient().
	 * @param string $client_id The client the entry must belong to.
	 *
	 * @return array{client_id: string, client_name: string, redirect_uris: list<string>, client_uri: ?string, tos_uri: ?string, policy_uri: ?string}|null
	 */
	private static function from_cache( mixed $cached, string $client_id ): ?array {
		if ( ! is_array( $cached ) || ( $cached['client_id'] ?? null ) !== $client_id || ! is_string( $cached['client_name'] ?? null ) || ! is_array( $cached['redirect_uris'] ?? null ) ) {
			return null;
		}

		$optional = static fn ( string $key ): ?string => is_string( $cached[ $key ] ?? null ) ? $cached[ $key ] : null;

		return [
			'client_id'     => $client_id,
			'client_name'   => $cached['client_name'],
			'redirect_uris' => array_values( array_filter( $cached['redirect_uris'], 'is_string' ) ),
			'client_uri'    => $optional( 'client_uri' ),
			'tos_uri'       => $optional( 'tos_uri' ),
			'policy_uri'    => $optional( 'policy_uri' ),
		];
	}

	/**
	 * Error for a document that couldn't be fetched or failed validation. The reason is logged under WP_DEBUG only.
	 *
	 * @param \WP_Error $reason Fetch or validation error; its message is fixed text, never remote content.
	 */
	private static function unverified( \WP_Error $reason ): \WP_Error {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'publishio: client metadata rejected: ' . $reason->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return new \WP_Error( 'invalid_client', __( 'The application\'s client metadata document could not be verified.', 'publishio' ), [ 'status' => 400 ] );
	}

	/**
	 * Count an uncached fetch against the current user's budget. Failed fetches count too.
	 */
	private function consume_fetch_budget(): bool {
		$key    = self::RATE_LIMIT_PREFIX . get_current_user_id();
		$now    = time();
		$budget = get_transient( $key );

		// A fixed window that starts at the first fetch; later fetches don't extend it.
		if ( ! is_array( $budget ) || (int) ( $budget['reset'] ?? 0 ) <= $now ) {
			$budget = [
				'count' => 0,
				'reset' => $now + self::RATE_WINDOW,
			];
		}

		if ( (int) $budget['count'] >= self::RATE_LIMIT ) {
			return false;
		}

		// An expiration of 0 would keep the transient forever, so keep at least 1 second.
		set_transient(
			$key,
			[
				'count' => (int) $budget['count'] + 1,
				'reset' => (int) $budget['reset'],
			],
			max( 1, (int) $budget['reset'] - $now )
		);

		return true;
	}
}
