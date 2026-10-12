<?php
/**
 * Validation rules for Client ID Metadata Documents (CIMD).
 *
 * @package rtCamp\Publishio\Modules\MCP\OAuth\Client
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\OAuth\Client;

/**
 * Class - Client_Metadata_Validator
 */
final class Client_Metadata_Validator {
	private const CLIENT_ID_MAX_LENGTH  = 255;
	private const NAME_MAX_LENGTH       = 100;
	private const MAX_REDIRECT_URIS     = 20;
	private const URI_MAX_LENGTH        = 2048;
	private const LOOPBACK_HOSTS        = [ '127.0.0.1', '[::1]', 'localhost' ];
	private const BLOCKED_HOST_SUFFIXES = [ '.localhost', '.local', '.internal' ];

	/**
	 * Whether a client_id is an acceptable metadata document URL.
	 *
	 * @param string $client_id The client_id from the request.
	 */
	public static function is_valid_client_id( string $client_id ): bool {
		if ( strlen( $client_id ) > self::CLIENT_ID_MAX_LENGTH ) {
			return false;
		}

		/**
		 * Allows only:
		 * - https;
		 * - a host of lowercase letters, digits, dots and hyphens (no port, credentials or Unicode);
		 * - one or more non-empty path segments of URL-safe characters, plus an optional trailing slash.
		 * That rules out queries, fragments, percent-encoding and whitespace. The D flag stops $ from matching before a trailing newline.
		 */
		if ( ! preg_match( '#^https://([a-z0-9.-]+)((?:/[A-Za-z0-9._~!$&\'()*+,;=:@-]+)+)/?$#D', $client_id, $matches ) ) {
			return false;
		}

		[ , $host, $path ] = $matches;

		$segments = explode( '/', $path );
		if ( in_array( '.', $segments, true ) || in_array( '..', $segments, true ) ) {
			return false;
		}

		return self::is_allowed_client_host( $host );
	}

	/**
	 * Validate a fetched document and reduce it to the fields Publishio uses.
	 *
	 * @param array<string, mixed> $document  Decoded JSON document.
	 * @param string               $client_id The URL the document was fetched from.
	 *
	 * @return array{client_id: string, client_name: string, redirect_uris: list<string>, client_uri: ?string, tos_uri: ?string, policy_uri: ?string}|\WP_Error
	 */
	public static function validate_document( array $document, string $client_id ): array|\WP_Error {
		if ( ( $document['client_id'] ?? null ) !== $client_id ) {
			return self::invalid( 'client_id does not match the document URL.' );
		}

		/**
		 * Sanitized first, so the checks apply to the name that is stored and shown. Then visible characters only, in any language: letters (\p{L}), accents (\p{M}), numbers (\p{N}), punctuation (\p{P}), symbols such as emoji (\p{S}) and spaces (\p{Zs}).
		 * That leaves out control characters and invisible formatting such as U+202E, which reverses the text displayed after it.
		 */
		$name = is_string( $document['client_name'] ?? null ) ? sanitize_text_field( $document['client_name'] ) : '';
		if ( '' === $name || mb_strlen( $name ) > self::NAME_MAX_LENGTH || 1 !== preg_match( '/^[\p{L}\p{M}\p{N}\p{P}\p{S}\p{Zs}]+$/Du', $name ) ) {
			return self::invalid( 'client_name is missing or invalid.' );
		}

		if ( array_key_exists( 'client_secret', $document ) || array_key_exists( 'client_secret_expires_at', $document ) ) {
			return self::invalid( 'The document must not contain a client secret.' );
		}

		$methods = self::auth_methods( $document );
		if ( ! in_array( 'none', $methods, true ) || [] !== preg_grep( '/^client_secret_/', $methods ) ) {
			return self::invalid( 'The document must allow the "none" token auth method and no shared-secret methods.' );
		}

		if ( ! self::lists_or_omits( $document, 'grant_types', 'authorization_code' ) || ! self::lists_or_omits( $document, 'response_types', 'code' ) ) {
			return self::invalid( 'The document must support the authorization_code grant and the code response type.' );
		}

		$host          = (string) wp_parse_url( $client_id, PHP_URL_HOST );
		$redirect_uris = self::allowed_redirect_uris( $document['redirect_uris'] ?? null, $host );
		if ( null === $redirect_uris ) {
			return self::invalid( sprintf( 'redirect_uris must list 1 to %d allowed URIs.', self::MAX_REDIRECT_URIS ) );
		}

		return [
			'client_id'     => $client_id,
			'client_name'   => $name,
			'redirect_uris' => $redirect_uris,
			'client_uri'    => self::same_host_uri( $document['client_uri'] ?? null, $host ),
			'tos_uri'       => self::same_host_uri( $document['tos_uri'] ?? null, $host ),
			'policy_uri'    => self::same_host_uri( $document['policy_uri'] ?? null, $host ),
		];
	}

	/**
	 * Whether a redirect URI may be used by a client hosted on $client_host.
	 *
	 * Allows https on the client's own host (or a subdomain), and http on a loopback host for native apps (RFC 8252).
	 *
	 * @param string $uri         The redirect URI.
	 * @param string $client_host Host of the client_id URL.
	 */
	public static function is_allowed_redirect_uri( string $uri, string $client_host ): bool {
		/**
		 * Reject:
		 * - whitespace and control characters, which browsers may silently drop, so the browser could read a different URL than we checked;
		 * - a fragment (#), which OAuth forbids in redirect URIs.
		 */
		if ( strlen( $uri ) > self::URI_MAX_LENGTH || preg_match( '/[[:space:][:cntrl:]#]/', $uri ) ) {
			return false;
		}

		$parts = wp_parse_url( $uri );
		if ( ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		$scheme = $parts['scheme'] ?? '';
		if ( 'http' === $scheme ) {
			return self::is_loopback_host( $parts['host'] );
		}

		return 'https' === $scheme && self::is_same_or_subdomain( $parts['host'], $client_host );
	}

	/**
	 * Whether a requested redirect URI matches one listed in the document.
	 *
	 * Exact string match, except loopback URIs may use any port because native apps pick a free one at runtime (RFC 8252 §7.3).
	 *
	 * @param string   $requested  The redirect_uri from the request.
	 * @param string[] $registered The redirect URIs listed in the document.
	 */
	public static function matches_redirect_uri( string $requested, array $registered ): bool {
		if ( in_array( $requested, $registered, true ) ) {
			return true;
		}

		if ( ! self::is_loopback_uri( $requested ) ) {
			return false;
		}

		$requested_key = self::without_port( $requested );
		foreach ( $registered as $uri ) {
			if ( self::is_loopback_uri( $uri ) && self::without_port( $uri ) === $requested_key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a redirect URI points at the user's own machine.
	 *
	 * @param string $uri The redirect URI.
	 */
	public static function is_loopback_uri( string $uri ): bool {
		return 'http' === wp_parse_url( $uri, PHP_URL_SCHEME ) && self::is_allowed_redirect_uri( $uri, '' );
	}

	/**
	 * Whether $host equals $base or is a subdomain of it.
	 *
	 * @param string $host Host to check.
	 * @param string $base Base host.
	 */
	public static function is_same_or_subdomain( string $host, string $base ): bool {
		$host = strtolower( $host );
		return '' !== $base && ( $host === $base || str_ends_with( $host, '.' . $base ) );
	}

	/**
	 * Host rules for the client_id: a public DNS name that isn't this site, optionally narrowed by an allowlist.
	 *
	 * @param string $host Lowercase ASCII host.
	 */
	private static function is_allowed_client_host( string $host ): bool {
		if ( ! str_contains( $host, '.' ) || str_contains( $host, '..' ) || str_starts_with( $host, '.' ) || str_ends_with( $host, '.' ) ) {
			return false;
		}

		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		foreach ( self::BLOCKED_HOST_SUFFIXES as $suffix ) {
			if ( str_ends_with( $host, $suffix ) ) {
				return false;
			}
		}

		// Not this site itself because content on the site could otherwise pose as an app under the site's own domain.
		$site_hosts = [ wp_parse_url( home_url(), PHP_URL_HOST ), wp_parse_url( site_url(), PHP_URL_HOST ) ];
		if ( in_array( $host, $site_hosts, true ) ) {
			return false;
		}

		/**
		 * Filters the client_id hosts allowed to authorize. An empty list allows any host that passes validation.
		 *
		 * @param string[] $allowed_hosts Hosts; subdomains of a listed host are allowed too.
		 */
		$allowed_hosts = (array) apply_filters( 'publishio_cimd_allowed_hosts', [] );
		if ( [] === $allowed_hosts ) {
			return true;
		}

		foreach ( $allowed_hosts as $allowed_host ) {
			if ( is_string( $allowed_host ) && self::is_same_or_subdomain( $host, strtolower( $allowed_host ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Token endpoint auth methods the client offers.
	 *
	 * Reads the plural field first, as ChatGPT publishes its accepted methods there and its preference in the singular field.
	 *
	 * @param array<string, mixed> $document Decoded document.
	 *
	 * @return list<string>
	 */
	private static function auth_methods( array $document ): array {
		$methods = $document['token_endpoint_auth_methods_supported'] ?? [ $document['token_endpoint_auth_method'] ?? null ];

		return is_array( $methods ) ? array_values( array_filter( $methods, 'is_string' ) ) : [];
	}

	/**
	 * Whether an optional array field is absent or contains $required.
	 *
	 * @param array<string, mixed> $document Decoded document.
	 * @param string               $field    Field name.
	 * @param string               $required Value that must be listed.
	 */
	private static function lists_or_omits( array $document, string $field, string $required ): bool {
		if ( ! array_key_exists( $field, $document ) ) {
			return true;
		}

		return is_array( $document[ $field ] ) && in_array( $required, $document[ $field ], true );
	}

	/**
	 * The document's redirect URIs that pass the redirect policy.
	 *
	 * @param mixed  $uris        The redirect_uris value.
	 * @param string $client_host Host of the client_id URL.
	 *
	 * @return list<string>|null Null when the value is malformed or nothing usable remains.
	 */
	private static function allowed_redirect_uris( mixed $uris, string $client_host ): ?array {
		if ( ! is_array( $uris ) || ! array_is_list( $uris ) || [] === $uris || count( $uris ) > self::MAX_REDIRECT_URIS ) {
			return null;
		}

		foreach ( $uris as $uri ) {
			if ( ! is_string( $uri ) ) {
				return null;
			}
		}

		$allowed = array_values( array_filter( $uris, static fn ( string $uri ): bool => self::is_allowed_redirect_uri( $uri, $client_host ) ) );

		return [] === $allowed ? null : $allowed;
	}

	/**
	 * An optional https link from the document, kept only when it is on the client's host.
	 *
	 * @param mixed  $uri         The value from the document.
	 * @param string $client_host Host of the client_id URL.
	 */
	private static function same_host_uri( mixed $uri, string $client_host ): ?string {
		// Reject whitespace and control characters. Fragments are fine in these links.
		if ( ! is_string( $uri ) || strlen( $uri ) > self::URI_MAX_LENGTH || preg_match( '/[[:space:][:cntrl:]]/', $uri ) ) {
			return null;
		}

		$parts = wp_parse_url( $uri );
		if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ! self::is_same_or_subdomain( $parts['host'] ?? '', $client_host ) ) {
			return null;
		}

		return $uri;
	}

	/**
	 * Whether a host is a loopback host.
	 *
	 * @param string $host Host from wp_parse_url() (IPv6 keeps its brackets).
	 */
	private static function is_loopback_host( string $host ): bool {
		return in_array( strtolower( $host ), self::LOOPBACK_HOSTS, true );
	}

	/**
	 * A loopback URI without its port, for port-agnostic comparison.
	 *
	 * @param string $uri A loopback URI.
	 */
	private static function without_port( string $uri ): string {
		$parts = (array) wp_parse_url( $uri );

		return strtolower( (string) ( $parts['host'] ?? '' ) ) . ( $parts['path'] ?? '' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
	}

	/**
	 * Build a validation error.
	 *
	 * @param string $message Reason the document was rejected.
	 */
	private static function invalid( string $message ): \WP_Error {
		return new \WP_Error( 'invalid_client_metadata', $message );
	}
}
