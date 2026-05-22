<?php
/**
 * Block Attr Writer.
 *
 * @package rtCamp\Publish_With_AI\Modules\Abilities\Patterns\Field_Writer
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Abilities\Patterns\Field_Writer;

/**
 * Handles attributes stored only in the block comment JSON (no `source` in block.json).
 */
class Block_Attr_Writer implements Field_Writer_Interface {
	/**
	 * {@inheritDoc}
	 */
	public function read( array $block, array $field_def ): string {
		$key = $field_def['_attrKey'] ?? '';
		$val = $block['attrs'][ $key ] ?? ( $field_def['default'] ?? '' );

		if ( is_array( $val ) || is_object( $val ) ) {
			return (string) wp_json_encode( $val );
		}

		return (string) $val;
	}

	/**
	 * {@inheritDoc}
	 */
	public function write( array &$block, array $field_def, string $new_value ): void {
		$key  = $field_def['_attrKey'] ?? '';
		$type = $field_def['type'] ?? 'string';

		if ( 'number' === $type || 'integer' === $type ) {
			$block['attrs'][ $key ] = (int) $new_value;
		} elseif ( 'boolean' === $type ) {
			$block['attrs'][ $key ] = filter_var( $new_value, FILTER_VALIDATE_BOOLEAN );
		} elseif ( 'object' === $type || 'array' === $type ) {
			$block['attrs'][ $key ] = json_decode( $new_value, true ) ?? $new_value;
		} else {
			$block['attrs'][ $key ] = $new_value;
		}
	}
}
