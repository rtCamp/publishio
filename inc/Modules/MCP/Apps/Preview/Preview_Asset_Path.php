<?php
/**
 * Resolves an asset URL to a file this install can read, for the preview app.
 *
 * @package rtCamp\Publishio\Modules\MCP\Apps\Preview
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Apps\Preview;

/**
 * Class - Preview_Asset_Path
 */
final class Preview_Asset_Path {
	/**
	 * Resolve an asset URL to a readable file inside this install, or null.
	 *
	 * Only the directories WordPress serves its own assets from are considered, and the resolved file has to still be inside the one it matched — a URL is attacker-adjacent input and `..` must not walk out of it.
	 *
	 * @param string $url       The asset URL, as enqueued.
	 * @param string $extension The extension the file has to have, without the dot.
	 */
	public static function resolve( string $url, string $extension ): ?string {
		$target = self::split( $url );

		if ( null === $target || ! str_ends_with( strtolower( $target['path'] ), '.' . $extension ) ) {
			return null;
		}

		foreach ( self::roots() as $root_url => $root_dir ) {
			$root = self::split( (string) $root_url );

			if ( null === $root || ! self::is_under( $target, $root ) ) {
				continue;
			}

			$relative  = substr( $target['path'], strlen( untrailingslashit( $root['path'] ) ) );
			$real_root = realpath( $root_dir );
			$candidate = realpath( untrailingslashit( $root_dir ) . $relative );

			if ( false === $real_root || false === $candidate ) {
				continue;
			}

			if ( ! str_starts_with( $candidate, $real_root . DIRECTORY_SEPARATOR ) ) {
				continue;
			}

			return is_readable( $candidate ) ? $candidate : null;
		}

		return null;
	}

	/**
	 * The URL-to-directory pairs an asset is allowed to resolve through.
	 *
	 * Most specific first. wp-content is its own entry because it does not have to sit under the site URL: on a subdirectory network it stays at the network root while site_url() carries the subsite path, and on a subdomain network its host is the network's rather than this site's. Everything else WordPress serves, wp-includes included, is under ABSPATH and follows site_url().
	 *
	 * @return array<string, string>
	 */
	private static function roots(): array {
		return [
			content_url() => WP_CONTENT_DIR,
			site_url()    => ABSPATH,
		];
	}

	/**
	 * Whether an asset sits beneath one of the asset roots.
	 *
	 * A URL WordPress printed without a host is relative to whichever root it came from, so an absent host on either side is not a mismatch.
	 *
	 * @param array{host: string, path: string} $target Asset host and path.
	 * @param array{host: string, path: string} $root   Asset root host and path.
	 */
	private static function is_under( array $target, array $root ): bool {
		if ( '' !== $target['host'] && '' !== $root['host'] && $target['host'] !== $root['host'] ) {
			return false;
		}

		return str_starts_with( $target['path'], untrailingslashit( $root['path'] ) . '/' );
	}

	/**
	 * Reduce a URL to the host and path a prefix match needs, or null if unparseable.
	 *
	 * An install at the domain root gives site_url() no path at all, which still describes a root every asset under it sits beneath.
	 *
	 * @param string $url URL to split.
	 *
	 * @return array{host: string, path: string}|null
	 */
	private static function split( string $url ): ?array {
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) ) {
			return null;
		}

		$path = (string) ( $parts['path'] ?? '' );

		return [
			'host' => strtolower( (string) ( $parts['host'] ?? '' ) ),
			'path' => '' === $path ? '/' : $path,
		];
	}

	/**
	 * Rewrite the URLs a stylesheet references to absolute ones.
	 *
	 * Relative URLs resolve against the stylesheet, and root-relative ones against the document — neither of which survives being moved into a srcdoc document, whose base URL is the parent's. Absolute URLs need no base at all.
	 *
	 * @param string $css Stylesheet contents.
	 * @param string $url URL the stylesheet was loaded from.
	 */
	public static function absolute_urls( string $css, string $url ): string {
		$origin = self::origin( $url );
		$base   = dirname( $url );

		return (string) preg_replace_callback(
			'#(url\s*\(\s*[\'"]?\s*)([^\'"\)]+)#',
			static function ( array $matches ) use ( $origin, $base ): string {
				[ , $prefix, $target ] = $matches;

				if (
					str_starts_with( $target, 'http:' ) ||
					str_starts_with( $target, 'https:' ) ||
					str_starts_with( $target, '//' ) ||
					str_starts_with( $target, 'data:' ) ||
					str_starts_with( $target, '#' )
				) {
					return $matches[0];
				}

				$absolute = str_starts_with( $target, '/' )
					? $origin . $target
					: $base . '/' . $target;

				return $prefix . str_replace( '/./', '/', $absolute );
			},
			$css
		);
	}

	/**
	 * The origin a root-relative URL inside a stylesheet resolves against.
	 *
	 * The stylesheet's own origin, since on a network its host is not necessarily this site's. A stylesheet enqueued without one falls back to this site.
	 *
	 * @param string $url URL the stylesheet was loaded from.
	 */
	private static function origin( string $url ): string {
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return untrailingslashit( home_url() );
		}

		$scheme = empty( $parts['scheme'] ) ? (string) wp_parse_url( home_url(), PHP_URL_SCHEME ) : (string) $parts['scheme'];
		$origin = $scheme . '://' . $parts['host'];

		return isset( $parts['port'] ) ? $origin . ':' . $parts['port'] : $origin;
	}

	/**
	 * Make asset contents unable to close the element they are being placed in.
	 *
	 * `\/` is a valid escape for `/` in both CSS strings and JavaScript, so quoted values keep their meaning; the sequence does not occur outside one in well-formed source. Escaping rather than stripping also means a second pass cannot reassemble it out of nested input such as `<</style/style`.
	 *
	 * @param string $source  Asset contents.
	 * @param string $element Element the contents go inside, without brackets.
	 */
	public static function neutralize_closing_tags( string $source, string $element ): string {
		return str_ireplace( '</' . $element, '<\/' . $element, $source );
	}
}
