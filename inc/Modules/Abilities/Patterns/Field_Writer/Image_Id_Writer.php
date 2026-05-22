<?php
/**
 * Image ID Writer.
 *
 * @package rtCamp\Publish_With_AI\Modules\Abilities\Patterns\Field_Writer
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Abilities\Patterns\Field_Writer;

use WP_HTML_Tag_Processor;

/**
 * Handles the attachment ID field for core/image.
 *
 * Reads/writes $block['attrs']['id'] and keeps the
 * wp-image-{id} class on the <img> tag in sync.
 */
class Image_Id_Writer implements Field_Writer_Interface {
	/**
	 * {@inheritDoc}
	 */
	public function read( array $block, array $_field_def ): string { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
		$val = $block['attrs']['id'] ?? '';
		return (string) $val;
	}

	/**
	 * {@inheritDoc}
	 */
	public function write( array &$block, array $_field_def, string $new_value ): void { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
		$id                   = (int) $new_value;
		$block['attrs']['id'] = $id;

		$block['innerHTML'] = self::update_class( $block['innerHTML'] ?? '', $id );

		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerContent'] as &$chunk ) {
				if ( is_string( $chunk ) ) {
					$chunk = self::update_class( $chunk, $id );
				}
			}
			unset( $chunk );
		} else {
			$block['innerContent'] = [ $block['innerHTML'] ];
		}
	}

	// ------------------------------------------------------------------

	/**
	 * Update the wp-image-{id} class on an <img> tag.
	 *
	 * @param string $html The HTML string.
	 * @param int    $id   New attachment ID.
	 */
	private static function update_class( string $html, int $id ): string {
		$proc = new WP_HTML_Tag_Processor( $html );

		if ( $proc->next_tag( [ 'tag_name' => 'img' ] ) ) {
			$class = (string) ( $proc->get_attribute( 'class' ) ?? '' );
			$class = (string) preg_replace( '/\bwp-image-\d+\b/', '', $class );
			$class = trim( $class ) . ' wp-image-' . $id;
			$proc->set_attribute( 'class', trim( $class ) );
			return $proc->get_updated_html();
		}

		return $html;
	}
}
