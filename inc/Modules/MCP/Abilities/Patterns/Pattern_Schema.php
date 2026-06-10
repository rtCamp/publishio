<?php
/**
 * Pattern Schema.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Patterns
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Patterns;

/**
 * Extracts and applies content schemas for block patterns.
 *
 * Uses Block_Field_Resolver to discover field metadata from the WP block registry
 * and delegates read/write to Field_Writer strategy classes.
 */
class Pattern_Schema {
	/**
	 * Extract a content schema from a pattern's block markup.
	 *
	 * Returns a flat ordered list where each entry is one replaceable block:
	 *
	 *   [
	 *     { "block": "core/heading",   "fields": { "text": "Welcome" } },
	 *     { "block": "core/paragraph", "fields": { "text": "Lorem ipsum…" } },
	 *     { "block": "core/button",    "fields": { "text": "Click", "url": "https://…" } },
	 *     { "block": "core/image",     "fields": { "url": "https://…", "alt": "Photo" } },
	 *   ]
	 *
	 * The array index acts as the implicit identifier — same order is
	 * expected when applying the filled schema back.
	 *
	 * @param string $markup Block markup.
	 *
	 * @return array<int, mixed>
	 */
	public static function extract( string $markup ): array {
		$blocks = array_values( parse_blocks( $markup ) );
		$schema = [];
		self::walk_extract( $blocks, $schema );
		return $schema;
	}

	/**
	 * Apply a filled schema to a pattern's block markup.
	 *
	 * Accepts the same structure returned by extract(), with values
	 * replaced by the caller. Returns the modified **block markup**
	 * (serialized blocks, not rendered HTML).
	 *
	 * @param string       $markup Block markup.
	 * @param array<mixed> $schema Filled schema.
	 */
	public static function apply( string $markup, array $schema ): string {
		$blocks = array_values( parse_blocks( $markup ) );
		$cursor = 0;
		self::walk_apply( $blocks, $schema, $cursor );
		return serialize_blocks( $blocks );
	}

	/**
	 * Walk blocks and extract schema entries.
	 *
	 * @param array<int, mixed> $blocks Block list.
	 * @param array<int, mixed> $schema Schema accumulator (passed by reference).
	 */
	private static function walk_extract( array $blocks, array &$schema ): void {
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? null;

			if ( $name && Block_Field_Resolver::has( $name ) ) {
				$resolved = Block_Field_Resolver::resolve( $name );
				$fields   = [];
				foreach ( $resolved as $attr_key => $entry ) {
					$fields[ $attr_key ] = $entry['writer']->read( $block, $entry['field_def'] );
				}
				$schema[] = [
					'block'  => $name,
					'fields' => $fields,
				];
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::walk_extract( $block['innerBlocks'], $schema );
			}
		}
	}

	/**
	 * Walk blocks and apply schema values.
	 *
	 * @param array<int, mixed> $blocks Block list (passed by reference).
	 * @param array<int, mixed> $schema Schema to apply.
	 * @param int               $cursor Current schema cursor (passed by reference).
	 */
	private static function walk_apply( array &$blocks, array $schema, int &$cursor ): void {
		foreach ( $blocks as &$block ) {
			$name = $block['blockName'] ?? null;

			// Content fields.
			if ( $name && Block_Field_Resolver::has( $name ) && isset( $schema[ $cursor ] ) ) {
				$entry    = $schema[ $cursor ];
				$resolved = Block_Field_Resolver::resolve( $name );
				foreach ( $entry['fields'] as $attr_key => $new_value ) {
					if ( isset( $resolved[ $attr_key ] ) ) {
						$resolved[ $attr_key ]['writer']->write( $block, $resolved[ $attr_key ]['field_def'], $new_value );
					}
				}
				++$cursor;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::walk_apply( $block['innerBlocks'], $schema, $cursor );
			}
		}
	}
}
