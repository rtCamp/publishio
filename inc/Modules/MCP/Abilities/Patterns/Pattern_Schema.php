<?php
/**
 * Pattern Schema.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns;

/**
 * Extracts and applies content schemas for block patterns.
 *
 * Uses Block_Field_Resolver to discover field metadata from the WP block registry
 * and delegates read/write to Field_Writer strategy classes.
 */
class Pattern_Schema {
	/**
	 * Blocks that are always repeatable containers.
	 */
	private const REPEATABLE_CONTAINERS = [
		'core/columns'      => 'core/column',
		'core/list'         => 'core/list-item',
		'core/gallery'      => 'core/image',
		'core/social-links' => 'core/social-link',
	];

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

	// ------------------------------------------------------------------
	// Extraction
	// ------------------------------------------------------------------

	/**
	 * Walk blocks and extract schema entries.
	 *
	 * @param array<int, mixed> $blocks Block list.
	 * @param array<int, mixed> $schema Schema accumulator (passed by reference).
	 */
	private static function walk_extract( array $blocks, array &$schema ): void {
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? null;

			if ( $name && self::is_repeatable_container( $block ) ) {
				$schema[] = self::extract_repeater( $block );
				continue;
			}

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
	 * Check if a block is a repeatable container.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	private static function is_repeatable_container( array $block ): bool {
		$name     = $block['blockName'] ?? null;
		$children = $block['innerBlocks'] ?? [];

		if ( ! $name || count( $children ) < 2 ) {
			return false;
		}

		// Known containers: verify children match expected type.
		if ( isset( self::REPEATABLE_CONTAINERS[ $name ] ) ) {
			$expected   = self::REPEATABLE_CONTAINERS[ $name ];
			$first_name = $children[0]['blockName'] ?? null;

			if ( $first_name !== $expected ) {
				return false;
			}
		} elseif ( 'core/group' === $name ) {
			// Groups with grid layout qualify if children are homogeneous.
			$layout_type = $block['attrs']['layout']['type'] ?? null;

			if ( 'grid' !== $layout_type ) {
				return false;
			}
		} else {
			return false;
		}

		// All children must share the same block structure.
		$first_signature = self::block_signature( $children[0] );
		$children_count  = count( $children );

		for ( $i = 1; $i < $children_count; $i++ ) {
			if ( self::block_signature( $children[ $i ] ) !== $first_signature ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Generate a structural signature for a block (used to compare homogeneity).
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	private static function block_signature( array $block ): string {
		$sig      = $block['blockName'] ?? '';
		$children = $block['innerBlocks'] ?? [];

		if ( ! empty( $children ) ) {
			$child_sigs = [];
			foreach ( $children as $child ) {
				$child_sigs[] = self::block_signature( $child );
			}
			$sig .= '[' . implode( ',', $child_sigs ) . ']';
		}

		return $sig;
	}

	/**
	 * Extract repeater schema from a repeatable container block.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 *
	 * @return array<string, mixed>
	 */
	private static function extract_repeater( array $block ): array {
		$items = [];

		foreach ( $block['innerBlocks'] as $child ) {
			$item_schema = [];
			self::walk_extract( [ $child ], $item_schema );
			$items[] = $item_schema;
		}

		return [
			'block'       => $block['blockName'],
			'repeatable'  => true,
			'count'       => count( $block['innerBlocks'] ),
			'item_schema' => $items[0] ?? [],
			'items'       => $items,
		];
	}

	// ------------------------------------------------------------------
	// Application
	// ------------------------------------------------------------------

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

			// Repeater: adjust child count and fill each item.
			if ( $name && isset( $schema[ $cursor ]['repeatable'] ) && $schema[ $cursor ]['block'] === $name && self::is_repeatable_container( $block ) ) {
				self::apply_repeater( $block, $schema[ $cursor ] );
				++$cursor;
				continue;
			}

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

	/**
	 * Apply a repeater schema entry to a block.
	 *
	 * @param array<string, mixed> $block Parsed block (passed by reference).
	 * @param array<string, mixed> $entry Repeater schema entry.
	 */
	private static function apply_repeater( array &$block, array $entry ): void {
		$items      = $entry['items'];
		$count      = count( $items );
		$donors     = $block['innerBlocks'];
		$num_donors = count( $donors );

		if ( empty( $donors ) || $count < 1 ) {
			return;
		}

		// Extract expected item structure from each donor.
		$donor_schemas = [];
		foreach ( $donors as $donor ) {
			$ds = [];
			self::walk_extract( [ $donor ], $ds );
			$donor_schemas[] = $ds;
		}

		// Clone donors in cycle order, fill each clone with its item schema.
		$block['innerBlocks'] = [];

		for ( $i = 0; $i < $count; $i++ ) {
			$donor_index = $i % $num_donors;
			$clone       = $donors[ $donor_index ];
			$item        = self::sanitize_repeater_item( $items[ $i ] ?? [], $donor_schemas[ $donor_index ] );
			$wrapped     = [ $clone ];
			$cursor      = 0;
			self::walk_apply( $wrapped, $item, $cursor );
			$block['innerBlocks'][] = $wrapped[0];
		}

		$block['innerContent'] = self::rebuild_inner_content( $block );
	}

	/**
	 * Ensure an item matches the donor's structure exactly.
	 *
	 * Rejects extra entries, fills missing ones with donor defaults,
	 * and falls back to donor values for any block-type mismatch.
	 *
	 * @param array<mixed> $item         Item schema from caller.
	 * @param array<mixed> $donor_schema Donor block schema.
	 *
	 * @return array<mixed>
	 */
	private static function sanitize_repeater_item( array $item, array $donor_schema ): array {
		$sanitized = [];

		foreach ( $donor_schema as $index => $expected ) {
			if ( ! isset( $item[ $index ] ) || ( $item[ $index ]['block'] ?? null ) !== $expected['block'] ) {
				$sanitized[] = $expected;
				continue;
			}

			if ( ! empty( $expected['repeatable'] ) !== ! empty( $item[ $index ]['repeatable'] ) ) {
				$sanitized[] = $expected;
				continue;
			}

			$sanitized[] = $item[ $index ];
		}

		return $sanitized;
	}

	/**
	 * Rebuild the innerContent array after modifying innerBlocks.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 *
	 * @return array<int, mixed>
	 */
	private static function rebuild_inner_content( array $block ): array {
		$original    = $block['innerContent'] ?? [];
		$child_count = count( $block['innerBlocks'] );

		// Separate strings around null (child) placeholders.
		$strings = [];
		$temp    = '';

		foreach ( $original as $item ) {
			if ( null === $item ) {
				$strings[] = $temp;
				$temp      = '';
			} else {
				$temp .= $item;
			}
		}
		$strings[] = $temp; // Append final string after last child.

		$opening   = $strings[0];
		$closing   = end( $strings ) ?: '';
		$separator = $strings[1] ?? "\n\n";

		// Rebuild: opening, (null + separator) × N, closing.
		$result = [ $opening ];

		for ( $i = 0; $i < $child_count; $i++ ) {
			$result[] = null;
			if ( $i < $child_count - 1 ) {
				$result[] = $separator;
			}
		}
		$result[] = $closing;

		return $result;
	}
}
