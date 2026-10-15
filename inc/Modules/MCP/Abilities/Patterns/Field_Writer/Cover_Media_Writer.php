<?php
/**
 * Cover Media Writer.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Field_Writer
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Field_Writer;

use WP_HTML_Tag_Processor;

/**
 * Custom writer for core/cover media fields (url, alt, id).
 *
 * Writes the media element the way core's save() renders each background type:
 *  - Image: <img class="wp-block-cover__image-background" src="..." alt="..."/>
 *  - Parallax or repeated image: <div class="wp-block-cover__image-background" style="background-image:url(...)">
 *  - Video: <video class="wp-block-cover__video-background" src="...">
 *  - Embedded video: <figure class="wp-block-cover__video-background wp-block-cover__embed-background"> with the URL as text
 */
class Cover_Media_Writer implements Field_Writer_Interface {
	/**
	 * Patterns that match each media element, by tag, for removing it. The tag processor can't remove elements.
	 */
	private const MEDIA_PATTERNS = [
		'IMG'    => '/<img\b[^>]*\bwp-block-cover__image-background\b[^>]*>/i',
		'DIV'    => '/<div\b[^>]*\bwp-block-cover__image-background\b[^>]*>\s*<\/div>/i',
		'VIDEO'  => '/<video\b[^>]*\bwp-block-cover__video-background\b[^>]*>.*?<\/video>/is',
		'FIGURE' => '/<figure\b[^>]*\bwp-block-cover__embed-background\b[^>]*>.*?<\/figure>/is',
	];

	/**
	 * {@inheritDoc}
	 */
	public function read( array $block, array $field_def ): string {
		$attr = $field_def['_attrKey'] ?? 'url';
		$val  = $block['attrs'][ $attr ] ?? '';

		return is_int( $val ) ? (string) $val : $val;
	}

	/**
	 * {@inheritDoc}
	 */
	public function write( array &$block, array $field_def, string $new_value ): void {
		$attr = $field_def['_attrKey'] ?? 'url';
		$id   = 0;

		if ( 'id' === $attr ) {
			$id = Attachment_Id::write_attr( $block, $new_value );
		} elseif ( 'url' === $attr ) {
			$new_value             = esc_url_raw( $new_value );
			$block['attrs']['url'] = $new_value;

			unset( $block['attrs']['id'] );
		} else {
			$block['attrs'][ $attr ] = $new_value;
		}

		// Update inline HTML in each innerContent chunk (cover has innerBlocks).
		foreach ( $block['innerContent'] as &$chunk ) {
			if ( ! is_string( $chunk ) ) {
				continue;
			}

			$chunk = match ( $attr ) {
				'url'   => self::write_url( $chunk, $new_value ),
				'alt'   => self::write_alt( $chunk, $new_value ),
				'id'    => self::write_id( $chunk, $id ),
				default => $chunk,
			};
		}
		unset( $chunk );

		// Rebuild innerHTML from string chunks.
		$block['innerHTML'] = implode( '', array_filter( $block['innerContent'], 'is_string' ) );
	}

	/**
	 * Write the URL to the media element, or remove the element for an empty URL.
	 *
	 * @param string $html The HTML chunk.
	 * @param string $url  New URL value.
	 */
	private static function write_url( string $html, string $url ): string {
		$proc = self::find_media( $html );

		if ( ! $proc ) {
			return $html;
		}

		if ( '' === $url ) {
			// Setting each attribute again encodes a literal > as &gt;, so the removal pattern can't stop inside a value.
			foreach ( $proc->get_attribute_names_with_prefix( '' ) ?? [] as $name ) {
				$proc->set_attribute( $name, $proc->get_attribute( $name ) ?? '' );
			}

			return self::remove_media( $proc->get_updated_html(), (string) $proc->get_tag() );
		}

		$tag = $proc->get_tag();

		if ( 'FIGURE' === $tag ) {
			self::write_embed_url( $proc, $url );

			return $proc->get_updated_html();
		}

		if ( 'DIV' === $tag ) {
			$style = (string) preg_replace_callback(
				'/background-image:\s*url\([^)]*\)/',
				static fn () => 'background-image:url(' . $url . ')',
				(string) ( $proc->get_attribute( 'style' ) ?? '' )
			);

			$proc->set_attribute( 'style', $style );
		} else {
			$proc->set_attribute( 'src', $url );
		}

		Attachment_Id::sync_class( $proc, 0 );

		return $proc->get_updated_html();
	}

	/**
	 * Replace the URL text inside an embedded video's wrapper.
	 *
	 * @param \WP_HTML_Tag_Processor $proc Tag processor positioned on the embed figure.
	 * @param string                 $url  New URL value.
	 */
	private static function write_embed_url( WP_HTML_Tag_Processor $proc, string $url ): void {
		if ( ! $proc->next_tag() || ! $proc->has_class( 'wp-block-embed__wrapper' ) ) {
			return;
		}

		if ( $proc->next_token() && '#text' === $proc->get_token_type() ) {
			$proc->set_modifiable_text( $url );
		}
	}

	/**
	 * Write alt to the media element.
	 *
	 * An image gets an alt attribute. A parallax div gets role and aria-label, which core adds only for a non-empty alt. Videos have no alt.
	 *
	 * @param string $html The HTML chunk.
	 * @param string $alt  New alt value.
	 */
	private static function write_alt( string $html, string $alt ): string {
		$proc = self::find_media( $html );

		if ( ! $proc ) {
			return $html;
		}

		$tag = $proc->get_tag();

		if ( 'IMG' === $tag ) {
			$proc->set_attribute( 'alt', $alt );
		} elseif ( 'DIV' === $tag && '' === $alt ) {
			$proc->remove_attribute( 'role' );
			$proc->remove_attribute( 'aria-label' );
		} elseif ( 'DIV' === $tag ) {
			$proc->set_attribute( 'role', 'img' );
			$proc->set_attribute( 'aria-label', $alt );
		}

		return $proc->get_updated_html();
	}

	/**
	 * Write the attachment ID class to the image or parallax div. Videos have no wp-image class.
	 *
	 * @param string $html The HTML chunk.
	 * @param int    $id   New attachment ID.
	 */
	private static function write_id( string $html, int $id ): string {
		$proc = self::find_media( $html );

		if ( ! $proc || ! $proc->has_class( 'wp-block-cover__image-background' ) ) {
			return $html;
		}

		Attachment_Id::sync_class( $proc, $id );

		return $proc->get_updated_html();
	}

	/**
	 * Remove the media element, and the overlay's gradient class that core adds only when there is media.
	 *
	 * @param string $html The HTML chunk.
	 * @param string $tag  Media element tag name.
	 */
	private static function remove_media( string $html, string $tag ): string {
		if ( ! isset( self::MEDIA_PATTERNS[ $tag ] ) ) {
			return $html;
		}

		$proc = new WP_HTML_Tag_Processor( (string) preg_replace( self::MEDIA_PATTERNS[ $tag ], '', $html, 1 ) );

		if ( $proc->next_tag( [ 'class_name' => 'wp-block-cover__background' ] ) ) {
			$proc->remove_class( 'wp-block-cover__gradient-background' );
		}

		return $proc->get_updated_html();
	}

	/**
	 * Find the cover's media element: the background image or parallax div, the video, or the embed figure.
	 *
	 * @param string $html The HTML chunk.
	 *
	 * @return \WP_HTML_Tag_Processor|null Tag processor positioned on the media element, or null when the chunk has none.
	 */
	private static function find_media( string $html ): ?WP_HTML_Tag_Processor {
		$proc = new WP_HTML_Tag_Processor( $html );

		while ( $proc->next_tag() ) {
			if ( $proc->has_class( 'wp-block-cover__image-background' ) || $proc->has_class( 'wp-block-cover__video-background' ) ) {
				return $proc;
			}
		}

		return null;
	}
}
