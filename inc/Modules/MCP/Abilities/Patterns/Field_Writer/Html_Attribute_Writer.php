<?php
/**
 * HTML Attribute Writer.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns\Field_Writer
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns\Field_Writer;

use WP_HTML_Tag_Processor;

/**
 * Handles source: "attribute" — reads/writes an HTML attribute on a matched element.
 */
class Html_Attribute_Writer implements Field_Writer_Interface {
	/**
	 * {@inheritDoc}
	 */
	public function read( array $block, array $field_def ): string {
		$html      = $block['innerHTML'] ?? '';
		$selector  = $field_def['selector'] ?? 'div';
		$attribute = $field_def['attribute'] ?? '';

		$tag  = self::resolve_tag( $html, $selector );
		$proc = new WP_HTML_Tag_Processor( $html );

		if ( $proc->next_tag( [ 'tag_name' => $tag ] ) && $attribute ) {
			return (string) ( $proc->get_attribute( $attribute ) ?? '' );
		}

		return '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function write( array &$block, array $field_def, string $new_value ): void {
		$selector  = $field_def['selector'] ?? 'div';
		$attribute = $field_def['attribute'] ?? '';
		$attr_key  = $field_def['_attrKey'] ?? '';
		$tag       = self::resolve_tag( $block['innerHTML'] ?? '', $selector );

		$block['innerHTML'] = self::set_attr_in_html( $block['innerHTML'] ?? '', $tag, $attribute, $new_value );

		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerContent'] as &$chunk ) {
				if ( is_string( $chunk ) ) {
					$chunk = self::set_attr_in_html( $chunk, $tag, $attribute, $new_value );
				}
			}
			unset( $chunk );
		} else {
			$block['innerContent'] = [ $block['innerHTML'] ];
		}

		// Keep block comment attrs in sync.
		if ( isset( $block['attrs'][ $attr_key ] ) ) {
			$block['attrs'][ $attr_key ] = $new_value;
		}

		// Clear stale attachment ID when URL-like attributes change.
		if ( ( 'src' === $attribute || 'url' === $attr_key ) && isset( $block['attrs']['id'] ) ) {
			unset( $block['attrs']['id'] );
		}
	}

	/**
	 * Resolve which tag to target from a comma-separated selector.
	 *
	 * @param string $html     The HTML string.
	 * @param string $selector CSS-like tag selector (comma-separated).
	 */
	private static function resolve_tag( string $html, string $selector ): string {
		$tags = array_map( 'trim', explode( ',', $selector ) );

		if ( count( $tags ) === 1 ) {
			return $tags[0];
		}

		foreach ( $tags as $tag ) {
			if ( preg_match( '/<' . preg_quote( $tag, '/' ) . '[\s>]/i', $html ) ) {
				return $tag;
			}
		}

		return $tags[0];
	}

	/**
	 * Set an attribute value in HTML string.
	 *
	 * @param string $html      The HTML string.
	 * @param string $tag       Tag to match.
	 * @param string $attribute Attribute name.
	 * @param string $value     New attribute value.
	 */
	private static function set_attr_in_html( string $html, string $tag, string $attribute, string $value ): string {
		$proc = new WP_HTML_Tag_Processor( $html );
		if ( $proc->next_tag( [ 'tag_name' => $tag ] ) ) {
			$proc->set_attribute( $attribute, $value );

			// Strip stale wp-image-{id} class when src changes.
			if ( 'src' === $attribute ) {
				$class = (string) ( $proc->get_attribute( 'class' ) ?? '' );
				if ( preg_match( '/\bwp-image-\d+\b/', $class ) ) {
					$class = (string) preg_replace( '/\bwp-image-\d+\b/', '', $class );
					$proc->set_attribute( 'class', trim( (string) preg_replace( '/\s+/', ' ', $class ) ) );
				}
			}
		}
		return $proc->get_updated_html();
	}
}
