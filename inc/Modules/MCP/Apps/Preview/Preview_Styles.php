<?php
/**
 * Inlines the preview's stylesheets so the document renders standalone.
 *
 * The preview is handed to the MCP app as a string and mounted with iframe srcdoc, which makes every `<link rel="stylesheet">` that wp_head() prints a subresource fetch the MCP host has to permit. A srcdoc document also inherits the embedding document's content security policy, so the app cannot widen it.
 *
 * Two things make that fragile enough to be worth removing entirely:
 *
 * 1. The ability runs inside a REST request, where wp_should_load_separate_core_block_assets() returns false before its filter is consulted. Core block CSS therefore arrives as the single 140K block-library/style.min.css instead of the small per-block files that wp_maybe_inline_styles() folds into the front-end document, so one refused request strips every core block of its styling at once.
 * 2. Stylesheets registered without a `path` — a theme's own, typically — are never eligible for wp_maybe_inline_styles() and stay linked regardless.
 *
 * Inline stylesheets also let the app pin viewport-height lengths, which it can't read in a linked stylesheet from another origin.
 *
 * Every enqueued stylesheet goes through here, whatever registered it: core, the theme, a plugin, a custom block's `style`, or a block extension's.
 *
 * @package rtCamp\Publishio\Modules\MCP\Apps\Preview
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Apps\Preview;

/**
 * Class - Preview_Styles
 */
final class Preview_Styles {
	/**
	 * Runs after every other `style_loader_tag` filter, and replaces the tag they produced rather than building on it.
	 *
	 * Optimisation plugins rewrite this tag to defer the stylesheet, and do it with inline JavaScript: Jetpack Boost sets `media="not all"` and restores it from an `onload` attribute plus a footer script. An MCP app's content security policy blocks inline handlers, which would leave those stylesheets loaded but never applied. Taking the last word makes the preview independent of whichever of those is active.
	 */
	private const PRIORITY = PHP_INT_MAX;

	/**
	 * Inline the preview's stylesheets for the duration of one render.
	 */
	public static function activate(): void {
		add_filter( 'style_loader_tag', [ self::class, 'inline_tag' ], self::PRIORITY, 4 );
	}

	/**
	 * Stop inlining, so nothing else served by this process is affected.
	 */
	public static function deactivate(): void {
		remove_filter( 'style_loader_tag', [ self::class, 'inline_tag' ], self::PRIORITY );
	}

	/**
	 * Replace a stylesheet link with the stylesheet's contents.
	 *
	 * Stylesheets this process cannot read from disk — anything on another host — are left linked for the host to fetch or refuse.
	 *
	 * @param string $tag    The `<link>` tag as built so far.
	 * @param string $handle The style's registered handle.
	 * @param string $href   The stylesheet URL.
	 * @param string $media  The stylesheet's media attribute.
	 */
	public static function inline_tag( string $tag, string $handle, string $href, string $media ): string {
		$path = Preview_Asset_Path::resolve( $href, 'css' );

		if ( null === $path ) {
			return $tag;
		}

		$css = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Reading a local stylesheet resolved under this install's own asset directories.

		if ( '' === $css ) {
			return $tag;
		}

		$css = Preview_Asset_Path::absolute_urls( $css, $href );
		$css = self::wrap_for_media( $css, $media );

		return sprintf(
			"<style id='%s-css'>%s</style>\n",
			esc_attr( $handle ),
			Preview_Asset_Path::neutralize_closing_tags( $css, 'style' )
		);
	}

	/**
	 * Keep a non-default media attribute meaningful once the stylesheet is inline.
	 *
	 * @param string $css   Stylesheet contents.
	 * @param string $media The stylesheet's media attribute.
	 */
	private static function wrap_for_media( string $css, string $media ): string {
		$media = trim( $media );

		if ( '' === $media || 'all' === strtolower( $media ) ) {
			return $css;
		}

		// A media query reaches the document as an attribute value, so restrict it to what one is made of before it becomes CSS syntax.
		$media = (string) preg_replace( '#[^a-z0-9\s:,()\-\.]#i', '', $media );

		return '' === trim( $media ) ? $css : sprintf( '@media %s{%s}', $media, $css );
	}
}
