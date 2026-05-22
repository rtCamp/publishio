<?php
/**
 * Cover Media Writer.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns\Field_Writer
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns\Field_Writer;

use WP_HTML_Tag_Processor;

/**
 * Custom writer for core/cover media fields (url, alt).
 *
 * Handles both rendering modes:
 *  - Normal: <img class="wp-block-cover__image-background" src="..." alt="..."/>
 *  - Parallax: <div class="wp-block-cover__image-background" style="background-image:url(...)">
 */
class Cover_Media_Writer implements Field_Writer_Interface {
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

		// Always update block attrs.
		$block['attrs'][ $attr ] = $new_value;

		if ( 'url' === $attr ) {
			unset( $block['attrs']['id'] );
		}

		if ( 'id' === $attr ) {
			$block['attrs']['id'] = (int) $new_value;
		}

		// Update inline HTML in each innerContent chunk (cover has innerBlocks).
		foreach ( $block['innerContent'] as &$chunk ) {
			if ( ! is_string( $chunk ) ) {
				continue;
			}

			$chunk = match ( $attr ) {
				'url'   => self::write_url( $chunk, $new_value ),
				'alt'   => self::write_alt( $chunk, $new_value ),
				'id'    => self::write_id( $chunk, (int) $new_value ),
				default => $chunk,
			};
		}
		unset( $chunk );

		// Rebuild innerHTML from string chunks.
		$block['innerHTML'] = implode( '', array_filter( $block['innerContent'], 'is_string' ) );
	}

	/**
	 * Write URL to the cover image element (non-parallax mode).
	 *
	 * @param string $html The HTML chunk.
	 * @param string $url  New URL value.
	 */
	private static function write_url_to_img( string $html, string $url ): ?string {
		$proc = new WP_HTML_Tag_Processor( $html );

		if ( $proc->next_tag( [ 'tag_name' => 'img' ] ) && self::is_cover_background( $proc ) ) {
			$proc->set_attribute( 'src', $url );

			// Remove stale wp-image-{id} class.
			$class = (string) ( $proc->get_attribute( 'class' ) ?? '' );
			if ( preg_match( '/\bwp-image-\d+\b/', $class ) ) {
				$class = (string) preg_replace( '/\bwp-image-\d+\b/', '', $class );
				$proc->set_attribute( 'class', trim( (string) preg_replace( '/\s+/', ' ', $class ) ) );
			}

			return $proc->get_updated_html();
		}

		return null;
	}

	/**
	 * Write URL to the cover background div (parallax mode).
	 *
	 * @param string $html The HTML chunk.
	 * @param string $url  New URL value.
	 */
	private static function write_url_to_div( string $html, string $url ): ?string {
		$proc = new WP_HTML_Tag_Processor( $html );

		while ( $proc->next_tag() ) {
			if ( ! self::is_cover_background( $proc ) ) {
				continue;
			}

			$style = (string) ( $proc->get_attribute( 'style' ) ?? '' );
			$style = (string) preg_replace(
				'/background-image:\s*url\([^)]+\)/',
				'background-image:url(' . esc_url( $url ) . ')',
				$style
			);
			$proc->set_attribute( 'style', $style );

			// Remove stale wp-image-{id} class.
			$class = (string) ( $proc->get_attribute( 'class' ) ?? '' );
			$class = (string) preg_replace( '/\bwp-image-\d+\b/', '', $class );
			$proc->set_attribute( 'class', trim( (string) preg_replace( '/\s+/', ' ', $class ) ) );

			return $proc->get_updated_html();
		}

		return null;
	}

	/**
	 * Write URL: try img first, fall back to parallax div.
	 *
	 * @param string $html The HTML chunk.
	 * @param string $url  New URL value.
	 */
	private static function write_url( string $html, string $url ): string {
		return self::write_url_to_img( $html, $url )
			?? self::write_url_to_div( $html, $url )
			?? $html;
	}

	/**
	 * Write alt to the cover <img> element (non-parallax mode).
	 *
	 * @param string $html The HTML chunk.
	 * @param string $alt  New alt value.
	 */
	private static function write_alt_to_img( string $html, string $alt ): ?string {
		$proc = new WP_HTML_Tag_Processor( $html );

		if ( $proc->next_tag( [ 'tag_name' => 'img' ] ) && self::is_cover_background( $proc ) ) {
			$proc->set_attribute( 'alt', $alt );
			return $proc->get_updated_html();
		}

		return null;
	}

	/**
	 * Write alt as aria-label on the background div (parallax mode).
	 *
	 * @param string $html The HTML chunk.
	 * @param string $alt  New alt value.
	 */
	private static function write_alt_to_div( string $html, string $alt ): ?string {
		$proc = new WP_HTML_Tag_Processor( $html );

		while ( $proc->next_tag() ) {
			if ( ! self::is_cover_background( $proc ) ) {
				continue;
			}

			$proc->set_attribute( 'role', 'img' );
			$proc->set_attribute( 'aria-label', $alt );
			return $proc->get_updated_html();
		}

		return null;
	}

	/**
	 * Write alt: try img first, fall back to parallax div.
	 *
	 * @param string $html The HTML chunk.
	 * @param string $alt  New alt value.
	 */
	private static function write_alt( string $html, string $alt ): string {
		return self::write_alt_to_img( $html, $alt )
			?? self::write_alt_to_div( $html, $alt )
			?? $html;
	}

	/**
	 * Write attachment ID to the cover <img> element class (non-parallax mode).
	 *
	 * @param string $html The HTML chunk.
	 * @param int    $id   New attachment ID.
	 */
	private static function write_id_to_img( string $html, int $id ): ?string {
		$proc = new WP_HTML_Tag_Processor( $html );

		if ( $proc->next_tag( [ 'tag_name' => 'img' ] ) && self::is_cover_background( $proc ) ) {
			$class = (string) ( $proc->get_attribute( 'class' ) ?? '' );
			$class = (string) preg_replace( '/\bwp-image-\d+\b/', '', $class );
			$class = trim( $class ) . ' wp-image-' . $id;
			$proc->set_attribute( 'class', trim( $class ) );
			return $proc->get_updated_html();
		}

		return null;
	}

	/**
	 * Write attachment ID to the cover background div class (parallax mode).
	 *
	 * @param string $html The HTML chunk.
	 * @param int    $id   New attachment ID.
	 */
	private static function write_id_to_div( string $html, int $id ): ?string {
		$proc = new WP_HTML_Tag_Processor( $html );

		while ( $proc->next_tag() ) {
			if ( ! self::is_cover_background( $proc ) ) {
				continue;
			}

			$class = (string) ( $proc->get_attribute( 'class' ) ?? '' );
			$class = (string) preg_replace( '/\bwp-image-\d+\b/', '', $class );
			$class = trim( $class ) . ' wp-image-' . $id;
			$proc->set_attribute( 'class', trim( $class ) );
			return $proc->get_updated_html();
		}

		return null;
	}

	/**
	 * Write ID: try img first, fall back to parallax div.
	 *
	 * @param string $html The HTML chunk.
	 * @param int    $id   New attachment ID.
	 */
	private static function write_id( string $html, int $id ): string {
		return self::write_id_to_img( $html, $id )
			?? self::write_id_to_div( $html, $id )
			?? $html;
	}

	/**
	 * Check if the current tag processor position is the cover background element.
	 *
	 * @param \WP_HTML_Tag_Processor $proc Tag processor.
	 */
	private static function is_cover_background( WP_HTML_Tag_Processor $proc ): bool {
		$class = (string) ( $proc->get_attribute( 'class' ) ?? '' );
		return str_contains( $class, 'wp-block-cover__image-background' );
	}
}
