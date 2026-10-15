<?php
/**
 * Attachment ID.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Field_Writer
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Field_Writer;

use WP_HTML_Tag_Processor;

/**
 * Writes a media block's attachment ID the way core's save() does.
 *
 * Core keeps the ID in the block's id attribute and a wp-image-{id} class on the media element, and leaves both out when there is no ID.
 */
final class Attachment_Id {
	/**
	 * Set the block's id attribute, or remove it when the value isn't a positive integer.
	 *
	 * @param array<string, mixed> $block Block (passed by reference).
	 * @param string               $value New attachment ID.
	 *
	 * @return int The ID written, or 0 when it was removed.
	 */
	public static function write_attr( array &$block, string $value ): int {
		$id = (int) filter_var(
			$value,
			FILTER_VALIDATE_INT,
			[
				'options' => [
					'min_range' => 1,
					'default'   => 0,
				],
			]
		);

		if ( $id > 0 ) {
			$block['attrs']['id'] = $id;
		} else {
			unset( $block['attrs']['id'] );
		}

		return $id;
	}

	/**
	 * Replace the wp-image-{id} class on the current tag, or remove it for an ID of 0.
	 *
	 * @param \WP_HTML_Tag_Processor $proc Tag processor positioned on the media element.
	 * @param int                    $id   Attachment ID.
	 */
	public static function sync_class( WP_HTML_Tag_Processor $proc, int $id ): void {
		foreach ( iterator_to_array( $proc->class_list(), false ) as $class ) {
			if ( preg_match( '/^wp-image-\d+$/', $class ) ) {
				$proc->remove_class( $class );
			}
		}

		if ( $id > 0 ) {
			$proc->add_class( 'wp-image-' . $id );
		}
	}
}
