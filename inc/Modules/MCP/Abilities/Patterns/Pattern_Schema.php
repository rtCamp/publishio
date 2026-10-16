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
	 * Each entry fills the next block of its type, so a left-out entry can't shift content onto a different kind of block. Only fields whose value changed are written.
	 *
	 * @param string       $markup Block markup.
	 * @param array<mixed> $schema Filled schema.
	 */
	public static function apply( string $markup, array $schema ): string {
		$blocks = array_values( parse_blocks( $markup ) );
		$cursor = 0;
		$schema = array_values(
			array_filter(
				$schema,
				static fn ( $entry ) => is_array( $entry ) && is_string( $entry['block'] ?? null ) && Block_Field_Resolver::has( $entry['block'] )
			)
		);

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
			$name  = $block['blockName'] ?? null;
			$entry = $schema[ $cursor ] ?? null;

			if ( $name && $entry && $entry['block'] === $name ) {
				$fields = is_array( $entry['fields'] ?? null ) ? $entry['fields'] : [];

				self::write_fields( $block, Block_Field_Resolver::resolve( $name ), $fields );

				++$cursor;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::walk_apply( $block['innerBlocks'], $schema, $cursor );
			}
		}
	}

	/**
	 * Write the fields whose value changed.
	 *
	 * Every field is read before any is written, because writing a url clears the attachment ID. For the same reason the id is written last.
	 *
	 * @param array<string, mixed>                $block    Block (passed by reference).
	 * @param array<string, array<string, mixed>> $resolved Field definitions and writers from Block_Field_Resolver.
	 * @param array<mixed>                        $fields   New field values.
	 */
	private static function write_fields( array &$block, array $resolved, array $fields ): void {
		$changed = [];

		foreach ( $resolved as $attr_key => $field ) {
			if ( ! isset( $fields[ $attr_key ] ) || ! is_scalar( $fields[ $attr_key ] ) ) {
				continue;
			}

			$value = (string) $fields[ $attr_key ];

			if ( $field['writer']->read( $block, $field['field_def'] ) !== $value ) {
				$changed[ $attr_key ] = $value;
			}
		}

		if ( isset( $changed['id'] ) ) {
			$id = $changed['id'];

			unset( $changed['id'] );

			$changed['id'] = $id;
		}

		foreach ( $changed as $attr_key => $value ) {
			$resolved[ $attr_key ]['writer']->write( $block, $resolved[ $attr_key ]['field_def'], $value );
		}
	}
}
