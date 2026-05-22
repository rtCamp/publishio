<?php
/**
 * Field Writer Interface.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns\Field_Writer
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns\Field_Writer;

/**
 * Interface - Field_Writer_Interface
 */
interface Field_Writer_Interface {
	/**
	 * Read the field value from a parsed block.
	 *
	 * @param array<string, mixed> $block     Parsed block.
	 * @param array<string, mixed> $field_def Field definition.
	 */
	public function read( array $block, array $field_def ): string;

	/**
	 * Write a new value into the parsed block (mutates in place).
	 *
	 * @param array<string, mixed> $block     Parsed block (passed by reference).
	 * @param array<string, mixed> $field_def Field definition.
	 * @param string               $new_value New value to write.
	 */
	public function write( array &$block, array $field_def, string $new_value ): void;
}
