<?php
/**
 * OAuth scopes: what a client may ask for, and what a granted scope allows.
 *
 * @package rtCamp\Publishio\Modules\MCP\OAuth
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\OAuth;

/**
 * Class - Scope
 */
final class Scope {
	public const READ  = 'mcp:read';
	public const WRITE = 'mcp:write';

	/**
	 * Supported scopes, in canonical order. Each needs a label in labels().
	 */
	public const SUPPORTED = [ self::READ, self::WRITE ];

	/**
	 * What each supported scope grants, as shown on the consent screen.
	 *
	 * @return array<string, string>
	 */
	private static function labels(): array {
		return [
			self::READ  => __( 'Read posts, pages, media, blocks and patterns', 'publishio' ),
			self::WRITE => __( 'Create and edit posts and pages, remove content from them, and upload media', 'publishio' ),
		];
	}

	/**
	 * Split a space-separated scope string into scope names.
	 *
	 * @param string $scope Space-separated scope.
	 *
	 * @return list<string>
	 */
	public static function parse( string $scope ): array {
		return preg_split( '/\s+/', trim( $scope ), -1, PREG_SPLIT_NO_EMPTY ) ?: [];
	}

	/**
	 * Turn a requested scope into the scope to grant.
	 *
	 * An empty request gets read-only access (the RFC 6749 §3.3 default), write access includes read access, and unsupported scopes are dropped.
	 *
	 * @param string $requested Space-separated scope from the authorization request.
	 *
	 * @return string Supported scopes in canonical order, or an empty string when none of the requested scopes are supported.
	 */
	public static function normalize( string $requested ): string {
		$items = self::parse( $requested );

		if ( [] === $items ) {
			return self::READ;
		}

		if ( in_array( self::WRITE, $items, true ) ) {
			$items[] = self::READ;
		}

		return implode( ' ', array_values( array_intersect( self::SUPPORTED, $items ) ) );
	}

	/**
	 * Whether a granted scope allows abilities that change the site.
	 *
	 * @param string $scope Space-separated granted scope.
	 */
	public static function allows_write( string $scope ): bool {
		return in_array( self::WRITE, self::parse( $scope ), true );
	}

	/**
	 * Describe a granted scope for the consent screen.
	 *
	 * @param string $scope Space-separated granted scope.
	 *
	 * @return list<string>
	 */
	public static function describe( string $scope ): array {
		return array_values( array_intersect_key( self::labels(), array_flip( self::parse( $scope ) ) ) );
	}
}
