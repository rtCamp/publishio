<?php
/**
 * Inlines the preview's stylesheets.
 *
 * @package rtCamp\Publishio\Modules\MCP\Apps\Preview
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Apps\Preview;

/**
 * Class - Preview_Styles
 *
 * The app pins viewport-height lengths in the preview, which it can only do with inline CSS: a linked stylesheet is cross-origin to the srcdoc frame, so its rules can't be read.
 */
final class Preview_Styles {
	/**
	 * Last, so a plugin that defers stylesheets with an onload handler, which the scriptless frame never runs, can't leave them unapplied.
	 */
	private const PRIORITY = PHP_INT_MAX;

	/**
	 * Inline stylesheets for one render.
	 */
	public static function activate(): void {
		add_filter( 'style_loader_tag', [ self::class, 'inline_tag' ], self::PRIORITY, 4 );
	}

	/**
	 * Stop inlining.
	 */
	public static function deactivate(): void {
		remove_filter( 'style_loader_tag', [ self::class, 'inline_tag' ], self::PRIORITY );
	}

	/**
	 * Replace a stylesheet link with the stylesheet's contents. Stylesheets outside this install stay linked.
	 *
	 * @param string $tag    The `<link>` tag.
	 * @param string $handle The style's handle.
	 * @param string $href   The stylesheet URL.
	 * @param string $media  The stylesheet's media attribute.
	 */
	public static function inline_tag( string $tag, string $handle, string $href, string $media ): string {
		$path = self::local_path( $href );

		if ( null === $path ) {
			return $tag;
		}

		$css = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- A local stylesheet inside this install.

		if ( '' === $css ) {
			return $tag;
		}

		$css = self::wrap_for_media( self::absolute_urls( $css, $href ), $media );

		return sprintf( "<style id='%s-css'>%s</style>\n", esc_attr( $handle ), str_ireplace( '</style', '<\/style', $css ) );
	}

	/**
	 * The stylesheet's file, if it's inside wp-content or the install. wp-content is its own root because it can sit outside the install, and on a network it keeps the network's host and path.
	 *
	 * @param string $url The stylesheet URL.
	 */
	private static function local_path( string $url ): ?string {
		$target = self::split( $url );

		if ( null === $target || ! str_ends_with( strtolower( $target['path'] ), '.css' ) ) {
			return null;
		}

		$roots = [
			content_url() => WP_CONTENT_DIR,
			site_url()    => ABSPATH,
		];

		foreach ( $roots as $root_url => $root_dir ) {
			$root = self::split( (string) $root_url );

			if ( null === $root || ! self::is_under( $target, $root ) ) {
				continue;
			}

			$relative  = substr( $target['path'], strlen( untrailingslashit( $root['path'] ) ) );
			$real_root = realpath( $root_dir );
			$candidate = realpath( untrailingslashit( $root_dir ) . $relative );

			if ( false === $real_root || false === $candidate || ! str_starts_with( $candidate, $real_root . DIRECTORY_SEPARATOR ) ) {
				continue;
			}

			return is_readable( $candidate ) ? $candidate : null;
		}

		return null;
	}

	/**
	 * Whether a URL is under a root URL. A URL without a host matches any host.
	 *
	 * @param array{host: string, path: string} $target The URL.
	 * @param array{host: string, path: string} $root   The root URL.
	 */
	private static function is_under( array $target, array $root ): bool {
		if ( '' !== $target['host'] && '' !== $root['host'] && $target['host'] !== $root['host'] ) {
			return false;
		}

		return str_starts_with( $target['path'], untrailingslashit( $root['path'] ) . '/' );
	}

	/**
	 * A URL's host and path, or null if it can't be parsed.
	 *
	 * @param string $url The URL.
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
	 * Make the stylesheet's relative URLs absolute, since inline CSS resolves them against the app instead.
	 *
	 * @param string $css The stylesheet.
	 * @param string $url The stylesheet URL.
	 */
	private static function absolute_urls( string $css, string $url ): string {
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
	 * The stylesheet's origin, or this site's when the URL has no host.
	 *
	 * @param string $url The stylesheet URL.
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
	 * Keep a media attribute such as print as a media query once the stylesheet is inline.
	 *
	 * @param string $css   The stylesheet.
	 * @param string $media The stylesheet's media attribute.
	 */
	private static function wrap_for_media( string $css, string $media ): string {
		$media = trim( $media );

		if ( '' === $media || 'all' === strtolower( $media ) ) {
			return $css;
		}

		$media = (string) preg_replace( '#[^a-z0-9\s:,()\-\.]#i', '', $media );

		return '' === trim( $media ) ? $css : sprintf( '@media %s{%s}', $media, $css );
	}
}
