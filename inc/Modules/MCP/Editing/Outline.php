<?php
/**
 * A list of a post's blocks with their paths.
 *
 * @package rtCamp\Publishio\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Editing;

/**
 * Class - Outline
 *
 * Each row is a block's path and a short text that identifies it. A synced pattern's row also lists its override fields and the values this post sets.
 *
 * @phpstan-type Override array{name: string, block: string, attributes: list<string>, values: array<string, mixed>}
 * @phpstan-type Row array{path: string, text: string, overrides?: list<Override>}
 */
final class Outline {
	public const MAX_ROWS = 300;

	private const TEXT_LENGTH = 60;

	/**
	 * The rows for a block and everything inside it, or for the whole post.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree $tree  The post's blocks.
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $under The block to start at. The root path means the whole post.
	 *
	 * @return array{rows: list<Row>, truncated: bool}|\WP_Error
	 */
	public static function of( Block_Tree $tree, Block_Path $under ): array|\WP_Error {
		$start = $tree->get( $under );

		if ( is_wp_error( $start ) ) {
			return $start;
		}

		$rows      = [];
		$truncated = false;

		if ( ! $under->is_root() ) {
			$rows[] = self::row( $start, $under );
		}

		self::collect( (array) $start['innerBlocks'], $under, $rows, $truncated );

		return [
			'rows'      => array_values( $rows ),
			'truncated' => $truncated,
		];
	}

	/**
	 * Add rows for blocks and their inner blocks, depth first.
	 *
	 * @param array<int, array<string, mixed>>                 $blocks    The blocks.
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $parent_path Their parent's path.
	 * @param array<int, Row>                                  $rows      The rows so far.
	 * @param bool                                             $truncated Set when MAX_ROWS is reached.
	 */
	private static function collect( array $blocks, Block_Path $parent_path, array &$rows, bool &$truncated ): void {
		foreach ( array_values( $blocks ) as $i => $block ) {
			if ( count( $rows ) >= self::MAX_ROWS ) {
				$truncated = true;
				return;
			}

			$path   = $parent_path->child( $i, Block_Tree::name_of( $block ) );
			$rows[] = self::row( $block, $path );

			if ( 'core/block' !== Block_Tree::name_of( $block ) ) {
				self::collect( (array) ( $block['innerBlocks'] ?? [] ), $path, $rows, $truncated );
			}
		}
	}

	/**
	 * One block's row.
	 *
	 * @param array<string, mixed>                             $block The block.
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $path  Its path.
	 *
	 * @return Row
	 */
	private static function row( array $block, Block_Path $path ): array {
		if ( 'core/block' !== Block_Tree::name_of( $block ) ) {
			return [
				'path' => $path->to_string(),
				'text' => self::text( $block ),
			];
		}

		$ref = (int) ( $block['attrs']['ref'] ?? 0 );

		$row = [
			'path' => $path->to_string(),
			/* translators: %s: pattern title */
			'text' => sprintf( __( 'Synced pattern "%s"', 'publishio' ), Pattern_Overrides::title( $ref ) ),
		];

		$fields = $ref ? Pattern_Overrides::fields( $ref ) : [];

		if ( is_array( $fields ) && [] !== $fields ) {
			$values           = Pattern_Overrides::values( $block );
			$row['overrides'] = [];

			foreach ( $fields as $name => $field ) {
				$row['overrides'][] = [
					'name'       => (string) $name,
					'block'      => $field['block'],
					'attributes' => $field['attributes'],
					'values'     => $values[ $name ] ?? [],
				];
			}
		}

		return $row;
	}

	/**
	 * A short text that identifies a block: its own text, an image's alt text, or the first text inside it.
	 *
	 * @param array<string, mixed> $block The block.
	 */
	private static function text( array $block ): string {
		$html = Block_Tree::own_html( $block );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );

		if ( '' === $text && str_contains( $html, '<img' ) ) {
			$processor = new \WP_HTML_Tag_Processor( $html );
			$text      = $processor->next_tag( [ 'tag_name' => 'img' ] ) ? trim( (string) $processor->get_attribute( 'alt' ) ) : '';
		}

		if ( '' === $text ) {
			foreach ( (array) ( $block['innerBlocks'] ?? [] ) as $inner ) {
				$text = self::text( $inner );

				if ( '' !== $text ) {
					break;
				}
			}
		}

		return mb_strlen( $text ) > self::TEXT_LENGTH ? rtrim( mb_substr( $text, 0, self::TEXT_LENGTH - 1 ) ) . '…' : $text;
	}
}
