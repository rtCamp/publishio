<?php
/**
 * Fetches Client ID Metadata Documents without exposing internal networks (SSRF).
 *
 * @package rtCamp\Publishio\Modules\MCP\OAuth\Client
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\OAuth\Client;

/**
 * Class - Client_Metadata_Fetcher
 */
final class Client_Metadata_Fetcher {
	private const MAX_BYTES     = 5120;
	private const TIMEOUT       = 5;
	private const MIN_CACHE_TTL = 60;
	private const MAX_CACHE_TTL = 3600;

	/**
	 * Resolves a host to its IP addresses.
	 *
	 * @var \Closure(string): list<string>
	 */
	private \Closure $resolver;

	/**
	 * Constructor.
	 *
	 * @param (\Closure(string): list<string>)|null $resolver DNS resolver; defaults to the system resolver.
	 */
	public function __construct( ?\Closure $resolver = null ) {
		$this->resolver = $resolver ?? self::resolve( ... );
	}

	/**
	 * Fetch and decode a client's metadata document.
	 *
	 * @param string $client_id A client_id that passed Client_Metadata_Validator::is_valid_client_id().
	 *
	 * @return array{document: array<string, mixed>, ttl: int}|\WP_Error
	 */
	public function fetch( string $client_id ): array|\WP_Error {
		$host = (string) wp_parse_url( $client_id, PHP_URL_HOST );
		$ip   = $this->public_ip( $host );

		if ( null === $ip ) {
			return self::failure( 'host_not_public' );
		}

		if ( ! function_exists( 'curl_init' ) || ! apply_filters( 'https_ssl_verify', true, $client_id ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter: refuse to fetch when a site disables TLS verification.
			return self::failure( 'secure_transport_unavailable' );
		}

		// Connect to the IP we checked, so a second DNS answer can't redirect the request (DNS rebinding).
		$pin = static function ( $handle, array $_args, string $url ) use ( $client_id, $host, $ip ): void { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			if ( $url === $client_id ) {
				curl_setopt( $handle, CURLOPT_RESOLVE, [ sprintf( '%s:443:%s', $host, str_contains( $ip, ':' ) ? "[{$ip}]" : $ip ) ] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
			}
		};

		add_action( 'http_api_curl', $pin, 10, 3 );
		$response = wp_safe_remote_get(
			$client_id,
			[
				'timeout'             => self::TIMEOUT,
				'redirection'         => 0,
				'limit_response_size' => self::MAX_BYTES + 1,
				'sslverify'           => true,
				'cookies'             => [],
				'user-agent'          => 'Publishio/' . PUBLISHIO_VERSION,
				'headers'             => [ 'Accept' => 'application/json' ],
			]
		);
		remove_action( 'http_api_curl', $pin, 10 );

		if ( is_wp_error( $response ) ) {
			return self::failure( 'request_failed' );
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return self::failure( 'status_not_200' );
		}

		$content_type = strtolower( trim( explode( ';', self::header( $response, 'content-type' ) )[0] ) );
		if ( 'application/json' !== $content_type ) {
			return self::failure( 'not_json' );
		}

		// Reading stops at MAX_BYTES + 1, so a longer body means the document is over the limit.
		$body = wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > self::MAX_BYTES ) {
			return self::failure( 'too_large' );
		}

		try {
			$document = json_decode( $body, true, 8, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $exception ) {
			return self::failure( 'invalid_json' );
		}

		if ( ! is_array( $document ) || array_is_list( $document ) ) {
			return self::failure( 'not_an_object' );
		}

		return [
			'document' => $document,
			'ttl'      => self::cache_ttl( self::header( $response, 'cache-control' ) ),
		];
	}

	/**
	 * The address to connect to, or null when any resolved address is not publicly routable.
	 *
	 * Checks every address ourselves: core's wp_http_validate_url() misses 169.254.0.0/16 before WordPress 7.1 and never checks IPv6.
	 *
	 * @param string $host The client_id host.
	 */
	private function public_ip( string $host ): ?string {
		$ips = ( $this->resolver )( $host );

		if ( [] === $ips ) {
			return null;
		}

		foreach ( $ips as $ip ) {
			if ( ! self::is_public_ip( $ip ) ) {
				return null;
			}
		}

		return $ips[0];
	}

	/**
	 * Whether an IP address is globally routable (RFC 6890).
	 *
	 * @param string $ip IPv4 or IPv6 address.
	 */
	private static function is_public_ip( string $ip ): bool {
		if ( str_starts_with( strtolower( $ip ), '::ffff:' ) && false !== filter_var( substr( $ip, 7 ), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$ip = substr( $ip, 7 );
		}

		return false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE );
	}

	/**
	 * Resolve a host's A and AAAA records with the system resolver.
	 *
	 * @param string $host Host name.
	 *
	 * @return list<string>
	 */
	private static function resolve( string $host ): array {
		$records = @dns_get_record( $host, DNS_A | DNS_AAAA ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Forbidden, WordPress.PHP.NoSilencedErrors.Discouraged -- A failed lookup warns; it is handled as "no addresses" below.
		$ips     = [];

		foreach ( is_array( $records ) ? $records : [] as $record ) {
			$ip = $record['ip'] ?? $record['ipv6'] ?? null;
			if ( is_string( $ip ) ) {
				$ips[] = $ip;
			}
		}

		if ( [] === $ips ) {
			$ips = gethostbynamel( $host ) ?: [];
		}

		return $ips;
	}

	/**
	 * Cache lifetime from Cache-Control, clamped to 1 minute – 1 hour.
	 *
	 * @param string $cache_control The Cache-Control header value.
	 */
	private static function cache_ttl( string $cache_control ): int {
		if ( preg_match( '/(?:^|,)\s*(?:no-store|no-cache|private)\b/i', $cache_control ) ) {
			return self::MIN_CACHE_TTL;
		}

		// s-maxage first: it applies to shared caches like ours.
		if ( preg_match( '/(?:^|,)\s*s-maxage\s*=\s*"?(\d+)/i', $cache_control, $matches ) || preg_match( '/(?:^|,)\s*max-age\s*=\s*"?(\d+)/i', $cache_control, $matches ) ) {
			return max( self::MIN_CACHE_TTL, min( self::MAX_CACHE_TTL, (int) $matches[1] ) );
		}

		return self::MIN_CACHE_TTL;
	}

	/**
	 * A response header as a single string.
	 *
	 * @param array<string, mixed> $response HTTP API response.
	 * @param string               $name     Header name.
	 */
	private static function header( array $response, string $name ): string {
		$value = wp_remote_retrieve_header( $response, $name );

		return is_array( $value ) ? implode( ',', $value ) : $value;
	}

	/**
	 * Build a fetch error with a fixed reason code.
	 *
	 * @param string $reason Reason code.
	 */
	private static function failure( string $reason ): \WP_Error {
		return new \WP_Error( 'client_metadata_unavailable', $reason );
	}
}
