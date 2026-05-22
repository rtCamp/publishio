<?php
/**
 * Rich Text Writer.
 *
 * @package rtCamp\Publish_With_AI\Modules\Abilities\Patterns\Field_Writer
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Abilities\Patterns\Field_Writer;

/**
 * Handles source: "rich-text" / "html" — inner content of an HTML element.
 */
class Rich_Text_Writer implements Field_Writer_Interface {
	/**
	 * {@inheritDoc}
	 */
	public function read( array $block, array $field_def ): string {
		$html     = $block['innerHTML'] ?? '';
		$selector = $field_def['selector'] ?? 'div';
		$tag      = self::resolve_tag( $html, $selector );

		return trim(
			wp_strip_all_tags(
				self::get_tag_content( $html, $tag )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function write( array &$block, array $field_def, string $new_value ): void {
		$selector = $field_def['selector'] ?? 'div';
		$tag      = self::resolve_tag( $block['innerHTML'] ?? '', $selector );
		$safe     = wp_kses_post( $new_value );

		$block['innerHTML'] = self::replace_tag_content( $block['innerHTML'] ?? '', $tag, $safe );

		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerContent'] as &$chunk ) {
				if ( is_string( $chunk ) ) {
					$chunk = self::replace_tag_content( $chunk, $tag, $safe );
				}
			}
			unset( $chunk );
		} else {
			$block['innerContent'] = [ $block['innerHTML'] ];
		}
	}

	// ------------------------------------------------------------------

	/**
	 * Resolve which tag to target from a comma-separated selector.
	 *
	 * If the selector lists multiple tags (e.g. "h1,h2,h3,h4,h5,h6"),
	 * we detect which one is actually present in the HTML.
	 *
	 * @param string $html     The HTML string.
	 * @param string $selector CSS-like tag selector (comma-separated).
	 */
	private static function resolve_tag( string $html, string $selector ): string {
		$tags = array_map( 'trim', explode( ',', $selector ) );

		if ( count( $tags ) === 1 ) {
			return $tags[0];
		}

		// Find which tag is actually present in the markup.
		foreach ( $tags as $tag ) {
			if ( preg_match( '/<' . preg_quote( $tag, '/' ) . '[\s>]/i', $html ) ) {
				return $tag;
			}
		}

		return $tags[0];
	}

	/**
	 * Get the inner content of a tag.
	 *
	 * @param string $html The HTML string.
	 * @param string $tag  Tag name.
	 */
	private static function get_tag_content( string $html, string $tag ): string {
		$pattern = '/<' . preg_quote( $tag, '/' ) . '(?:\s[^>]*)?>(.+?)<\/' . preg_quote( $tag, '/' ) . '>/s';

		if ( preg_match( $pattern, $html, $m ) ) {
			return $m[1];
		}

		return $html;
	}

	/**
	 * Replace the inner content of a tag.
	 *
	 * @param string $html        The HTML string.
	 * @param string $tag         Tag name.
	 * @param string $new_content New inner content.
	 */
	private static function replace_tag_content( string $html, string $tag, string $new_content ): string {
		$pattern = '/(<' . preg_quote( $tag, '/' ) . '(?:\s[^>]*)?>)(.*?)(<\/' . preg_quote( $tag, '/' ) . '>)/s';

		return preg_replace_callback(
			$pattern,
			static function ( $m ) use ( $new_content ) {
				return $m[1] . $new_content . $m[3];
			},
			$html,
			1
		) ?? $html;
	}
}
