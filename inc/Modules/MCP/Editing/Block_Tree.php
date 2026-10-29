<?php
/**
 * A post's blocks, addressed by path.
 *
 * @package rtCamp\Publishio\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Editing;

/**
 * Class - Block_Tree
 *
 * The post itself is held as a container block with no name, so top-level blocks and inner blocks are inserted, moved and removed the same way. A container keeps one null slot in `innerContent` per inner block, in order, and serializes by filling each slot with that block. Every change here keeps the slots in step with `innerBlocks`.
 *
 * @phpstan-type Block array{blockName: ?string, attrs: array<string, mixed>, innerBlocks: list<array<string, mixed>>, innerHTML: string, innerContent: list<?string>}
 */
final class Block_Tree {
	public const PLACEMENTS = [ 'before', 'after', 'start', 'end' ];

	private const SEPARATOR = "\n\n";
	private const FREEFORM  = 'core/freeform';
	private const SYNCED    = 'core/block';

	/**
	 * The post as a container block.
	 *
	 * @var array<string, mixed>
	 */
	private array $root;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $root The post as a container block.
	 */
	private function __construct( array $root ) {
		$this->root = $root;
	}

	/**
	 * Build a tree from post content.
	 *
	 * @param string $markup Serialized blocks.
	 */
	public static function from_markup( string $markup ): self {
		return new self( self::container( parse_blocks( $markup ) ) );
	}

	/**
	 * Build a tree from parsed blocks, with blank lines between them as the editor saves them.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 */
	public static function of( array $blocks ): self {
		$tree = new self( self::container( [] ) );
		self::add_children( $tree->root, 0, array_values( $blocks ) );

		return $tree;
	}

	/**
	 * Parse new markup into blocks, leaving out whitespace between them.
	 *
	 * HTML outside any block is kept as a block with no name, so the validator can refuse it.
	 *
	 * @param string $markup Serialized blocks.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function parse( string $markup ): array {
		return self::container( parse_blocks( $markup ) )['innerBlocks'];
	}

	/**
	 * A block's full name. HTML outside any block is named as the editor names it.
	 *
	 * @param array<string, mixed> $block A parsed block.
	 */
	public static function name_of( array $block ): string {
		return is_string( $block['blockName'] ?? null ) ? $block['blockName'] : self::FREEFORM;
	}

	/**
	 * A block's own HTML, without its inner blocks.
	 *
	 * @param array<string, mixed> $block A parsed block.
	 */
	public static function own_html( array $block ): string {
		return implode( '', array_filter( (array) ( $block['innerContent'] ?? [] ), 'is_string' ) );
	}

	/**
	 * The serialized post.
	 */
	public function to_markup(): string {
		return serialize_block( $this->root );
	}

	/**
	 * The top-level blocks.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function blocks(): array {
		return $this->root['innerBlocks'];
	}

	/**
	 * The block at a path, or the post as a container for the root path.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $path The block's path.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get( Block_Path $path ): array|\WP_Error {
		$found = $this->resolve( $path );

		return is_wp_error( $found ) ? $found : $found['block'];
	}

	/**
	 * The full names of the blocks from the top of the post down to a container, which the container's new children would sit inside.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $container A resolved container path.
	 *
	 * @return list<string>
	 */
	public function names_down_to( Block_Path $container ): array {
		return array_column( $container->steps(), 'name' );
	}

	/**
	 * Where new blocks go for a target and placement.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $target    The block to place next to or inside. The root path means the post.
	 * @param string                                           $placement One of PLACEMENTS: before or after the target, or at the start or end inside it.
	 *
	 * @return array{container: \rtCamp\Publishio\Modules\MCP\Editing\Block_Path, position: int}|\WP_Error
	 */
	public function spot( Block_Path $target, string $placement ): array|\WP_Error {
		if ( ! in_array( $placement, self::PLACEMENTS, true ) ) {
			/* translators: %s: the placement given */
			return Errors::invalid_placement( sprintf( __( '"%s" isn\'t a placement. Use before, after, start or end.', 'publishio' ), $placement ) );
		}

		$found = $this->resolve( $target );

		if ( is_wp_error( $found ) ) {
			return $found;
		}

		if ( in_array( $placement, [ 'before', 'after' ], true ) ) {
			if ( $target->is_root() ) {
				return Errors::invalid_placement( __( 'before and after need a target block. Leave out target and use start or end to add at the top or bottom of the post.', 'publishio' ) );
			}

			$index = $target->index();

			return [
				'container' => $target->parent(),
				'position'  => 'after' === $placement ? $index + 1 : $index,
			];
		}

		$block = $found['block'];

		if ( ! $target->is_root() && self::SYNCED === self::name_of( $block ) ) {
			return Errors::inside_synced_pattern( $target->to_string(), $target->to_string(), self::synced_title( $block ) );
		}

		$count = count( $block['innerBlocks'] );

		if ( ! $target->is_root() && 0 === $count ) {
			return Errors::empty_container( $target->to_string() );
		}

		return [
			'container' => $target,
			'position'  => 'start' === $placement ? 0 : $count,
		];
	}

	/**
	 * Insert blocks at a position in a container found with spot().
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $container The container.
	 * @param int                                              $position  Where among the container's blocks.
	 * @param array<int, array<string, mixed>>                 $blocks    The blocks to insert.
	 *
	 * @return list<\rtCamp\Publishio\Modules\MCP\Editing\Block_Path>|\WP_Error The new blocks' paths.
	 */
	public function insert_at( Block_Path $container, int $position, array $blocks ): array|\WP_Error {
		$found = $this->resolve( $container );

		if ( is_wp_error( $found ) ) {
			return $found;
		}

		$node = &$this->node( $found['chain'] );
		self::add_children( $node, $position, $blocks );

		return self::paths_of( $container, $position, $blocks );
	}

	/**
	 * Insert blocks before, after or inside a target.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $target    The block to place next to or inside.
	 * @param string                                           $placement One of PLACEMENTS.
	 * @param array<int, array<string, mixed>>                 $blocks    The blocks to insert.
	 *
	 * @return list<\rtCamp\Publishio\Modules\MCP\Editing\Block_Path>|\WP_Error The new blocks' paths.
	 */
	public function insert( Block_Path $target, string $placement, array $blocks ): array|\WP_Error {
		$spot = $this->spot( $target, $placement );

		return is_wp_error( $spot ) ? $spot : $this->insert_at( $spot['container'], $spot['position'], $blocks );
	}

	/**
	 * Replace a block, with its inner blocks, by one or more blocks.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $path   The block to replace.
	 * @param array<int, array<string, mixed>>                 $blocks The blocks that take its place.
	 *
	 * @return list<\rtCamp\Publishio\Modules\MCP\Editing\Block_Path>|\WP_Error The new blocks' paths.
	 */
	public function replace( Block_Path $path, array $blocks ): array|\WP_Error {
		$found = $this->resolve( $path );

		if ( is_wp_error( $found ) ) {
			return $found;
		}

		if ( $path->is_root() ) {
			return Errors::invalid_placement( __( 'replace-block needs the path of a block, not the post.', 'publishio' ) );
		}

		if ( [] === $blocks ) {
			return Errors::invalid_placement( __( 'replace-block needs at least one block. Use delete-blocks to remove a block.', 'publishio' ) );
		}

		$chain    = $found['chain'];
		$position = (int) array_pop( $chain );
		$parent   = &$this->node( $chain );
		$slots    = self::slots( $parent['innerContent'] );
		$chunk    = [];

		foreach ( array_keys( $blocks ) as $i ) {
			if ( $i > 0 ) {
				$chunk[] = self::SEPARATOR;
			}

			$chunk[] = null;
		}

		array_splice( $parent['innerContent'], $slots[ $position ], 1, $chunk );
		array_splice( $parent['innerBlocks'], $position, 1, $blocks );
		self::sync_html( $parent );

		return self::paths_of( $path->parent(), $position, $blocks );
	}

	/**
	 * Remove blocks. Every path is resolved before anything is removed, so the paths all refer to the post as it was.
	 *
	 * @param array<int, \rtCamp\Publishio\Modules\MCP\Editing\Block_Path> $paths The blocks to remove.
	 *
	 * @return true|\WP_Error
	 */
	public function remove( array $paths ): bool|\WP_Error {
		$chains = [];

		foreach ( $paths as $path ) {
			if ( $path->is_root() ) {
				return Errors::invalid_placement( __( 'delete-blocks needs the paths of blocks, not the post.', 'publishio' ) );
			}

			$found = $this->resolve( $path );

			if ( is_wp_error( $found ) ) {
				return $found;
			}

			$chains[ implode( '.', $found['chain'] ) ] = $found['chain'];
		}

		$chains = array_values( $chains );

		usort( $chains, [ self::class, 'compare_chains' ] );

		$kept = [];

		foreach ( $chains as $chain ) {
			$last = end( $kept );

			if ( false === $last || array_slice( $chain, 0, count( $last ) ) !== $last ) {
				$kept[] = $chain;
			}
		}

		foreach ( array_reverse( $kept ) as $chain ) {
			$position = (int) array_pop( $chain );
			$parent   = &$this->node( $chain );
			self::remove_child( $parent, $position );
			unset( $parent );
		}

		return true;
	}

	/**
	 * Move a block before, after or inside a target. The target is read from the post as it was before the move.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $path      The block to move.
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $target    Where to move it.
	 * @param string                                           $placement One of PLACEMENTS.
	 *
	 * @return list<\rtCamp\Publishio\Modules\MCP\Editing\Block_Path>|\WP_Error The block's new path.
	 */
	public function move( Block_Path $path, Block_Path $target, string $placement ): array|\WP_Error {
		if ( $path->is_root() ) {
			return Errors::invalid_placement( __( 'move-block needs the path of a block, not the post.', 'publishio' ) );
		}

		$found = $this->resolve( $path );

		if ( is_wp_error( $found ) ) {
			return $found;
		}

		$spot = $this->spot( $target, $placement );

		if ( is_wp_error( $spot ) ) {
			return $spot;
		}

		$container = $spot['container'];
		$position  = $spot['position'];

		if ( $container->is_within( $path ) ) {
			/* translators: %s: the block's path */
			return Errors::invalid_placement( sprintf( __( '%s can\'t move inside itself.', 'publishio' ), $path->to_string() ) );
		}

		$parent = $path->parent();
		$from   = $path->index();
		$depth  = $parent->depth();

		$this->remove( [ $path ] );

		if ( $container->depth() === $depth && $container->is_within( $parent ) ) {
			if ( $position > $from ) {
				--$position;
			}
		} elseif ( $container->depth() > $depth && $container->is_within( $parent ) && $container->steps()[ $depth ]['index'] > $from ) {
			$container = $container->with_index_at( $depth, $container->steps()[ $depth ]['index'] - 1 );
		}

		return $this->insert_at( $container, $position, [ $found['block'] ] );
	}

	/**
	 * Copy a block right after itself.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $path  The block to copy.
	 * @param int                                              $count How many copies.
	 *
	 * @return list<\rtCamp\Publishio\Modules\MCP\Editing\Block_Path>|\WP_Error The copies' paths.
	 */
	public function duplicate( Block_Path $path, int $count ): array|\WP_Error {
		if ( $path->is_root() ) {
			return Errors::invalid_placement( __( 'duplicate-block needs the path of a block, not the post.', 'publishio' ) );
		}

		$found = $this->resolve( $path );

		if ( is_wp_error( $found ) ) {
			return $found;
		}

		$position = $path->index() + 1;

		return $this->insert_at( $path->parent(), $position, array_fill( 0, max( 1, $count ), $found['block'] ) );
	}

	/**
	 * Find the block at a path, checking each step's name.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $path The path.
	 *
	 * @return array{chain: list<int>, block: array<string, mixed>}|\WP_Error
	 */
	private function resolve( Block_Path $path ): array|\WP_Error {
		$node   = $this->root;
		$chain  = [];
		$walked = Block_Path::root();

		foreach ( $path->steps() as $step ) {
			if ( ! $walked->is_root() && self::SYNCED === self::name_of( $node ) ) {
				return Errors::inside_synced_pattern( $path->to_string(), $walked->to_string(), self::synced_title( $node ) );
			}

			$children = $node['innerBlocks'];

			if ( $step['index'] >= count( $children ) ) {
				$where = $walked->is_root() ? __( 'the post', 'publishio' ) : $walked->to_string();

				return Errors::path_not_found( $walked->child( $step['index'], $step['name'] )->to_string(), $where, count( $children ) );
			}

			$child = $children[ $step['index'] ];
			$name  = self::name_of( $child );

			if ( $name !== $step['name'] ) {
				return Errors::path_mismatch( $walked->child( $step['index'], $step['name'] )->to_string(), $name );
			}

			$chain[] = $step['index'];
			$walked  = $walked->child( $step['index'], $name );
			$node    = $child;
		}

		return [
			'chain' => $chain,
			'block' => $node,
		];
	}

	/**
	 * A reference to the node reached by following inner-block positions from the root.
	 *
	 * @param array<int, int> $chain Positions, outermost first.
	 *
	 * @return array<string, mixed>
	 */
	private function &node( array $chain ): array {
		$node = &$this->root;

		foreach ( $chain as $position ) {
			$node = &$node['innerBlocks'][ $position ];
		}

		return $node;
	}

	/**
	 * Build a container node from a list of parsed blocks, turning whitespace between them into content strings.
	 *
	 * @param array<int|string, array<string, mixed>> $blocks Parsed blocks.
	 *
	 * @return array<string, mixed>
	 */
	private static function container( array $blocks ): array {
		$inner   = [];
		$content = [];

		foreach ( $blocks as $block ) {
			if ( null === ( $block['blockName'] ?? null ) && '' === trim( (string) ( $block['innerHTML'] ?? '' ) ) ) {
				$content[] = (string) ( $block['innerHTML'] ?? '' );
				continue;
			}

			$inner[]   = $block;
			$content[] = null;
		}

		return [
			'blockName'    => null,
			'attrs'        => [],
			'innerBlocks'  => $inner,
			'innerHTML'    => '',
			'innerContent' => $content,
		];
	}

	/**
	 * Add blocks at a position among a container's inner blocks.
	 *
	 * @param array<string, mixed>             $node     The container.
	 * @param int                              $position Where among its inner blocks.
	 * @param array<int, array<string, mixed>> $blocks   The blocks to add.
	 */
	private static function add_children( array &$node, int $position, array $blocks ): void {
		$count    = count( $node['innerBlocks'] );
		$position = max( 0, min( $position, $count ) );
		$slots    = self::slots( $node['innerContent'] );
		$chunk    = [];

		if ( $position < $count ) {
			foreach ( $blocks as $_block ) {
				$chunk[] = null;
				$chunk[] = self::SEPARATOR;
			}

			array_splice( $node['innerContent'], $slots[ $position ], 0, $chunk );
		} else {
			foreach ( $blocks as $_block ) {
				$chunk[] = self::SEPARATOR;
				$chunk[] = null;
			}

			if ( 0 === $count ) {
				array_shift( $chunk );
			}

			$at = 0 === $count ? count( $node['innerContent'] ) : $slots[ $count - 1 ] + 1;
			array_splice( $node['innerContent'], $at, 0, $chunk );
		}

		array_splice( $node['innerBlocks'], $position, 0, $blocks );
		self::sync_html( $node );
	}

	/**
	 * Remove one inner block and the whitespace that separated it from a neighbor.
	 *
	 * @param array<string, mixed> $node     The container.
	 * @param int                  $position The inner block's position.
	 */
	private static function remove_child( array &$node, int $position ): void {
		$content = $node['innerContent'];
		$slot    = self::slots( $content )[ $position ];
		$from    = $slot;
		$length  = 1;

		if ( $slot >= 2 && null === $content[ $slot - 2 ] && self::is_whitespace( $content[ $slot - 1 ] ) ) {
			$from   = $slot - 1;
			$length = 2;
		} elseif ( $slot + 2 < count( $content ) && null === $content[ $slot + 2 ] && self::is_whitespace( $content[ $slot + 1 ] ) ) {
			$length = 2;
		}

		array_splice( $node['innerContent'], $from, $length );
		array_splice( $node['innerBlocks'], $position, 1 );
		self::sync_html( $node );
	}

	/**
	 * The positions of the null slots in a container's content, one per inner block.
	 *
	 * @param array<int, ?string> $content The container's innerContent.
	 *
	 * @return list<int>
	 */
	private static function slots( array $content ): array {
		return array_keys( array_filter( $content, 'is_null' ) );
	}

	/**
	 * Keep a block's innerHTML matching its content strings. The post container has none.
	 *
	 * @param array<string, mixed> $node The block.
	 */
	private static function sync_html( array &$node ): void {
		if ( null !== $node['blockName'] ) {
			$node['innerHTML'] = self::own_html( $node );
		}
	}

	/**
	 * Whether a content entry is only whitespace.
	 *
	 * @param ?string $value A content entry.
	 */
	private static function is_whitespace( ?string $value ): bool {
		return is_string( $value ) && '' === trim( $value );
	}

	/**
	 * The paths of blocks added at a position.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $container The container.
	 * @param int                                              $position  The first block's position.
	 * @param array<int, array<string, mixed>>                 $blocks    The blocks.
	 *
	 * @return list<\rtCamp\Publishio\Modules\MCP\Editing\Block_Path>
	 */
	private static function paths_of( Block_Path $container, int $position, array $blocks ): array {
		$paths = [];

		foreach ( $blocks as $i => $block ) {
			$paths[] = $container->child( $position + $i, self::name_of( $block ) );
		}

		return $paths;
	}

	/**
	 * Order chains of positions as they appear in the post.
	 *
	 * @param array<int, int> $a A chain.
	 * @param array<int, int> $b Another chain.
	 */
	private static function compare_chains( array $a, array $b ): int {
		foreach ( $a as $i => $position ) {
			if ( ! isset( $b[ $i ] ) ) {
				return 1;
			}

			if ( $position !== $b[ $i ] ) {
				return $position <=> $b[ $i ];
			}
		}

		return count( $a ) <=> count( $b );
	}

	/**
	 * A synced pattern reference's title.
	 *
	 * @param array<string, mixed> $block A core/block block.
	 */
	private static function synced_title( array $block ): string {
		return Pattern_Overrides::title( (int) ( $block['attrs']['ref'] ?? 0 ) );
	}
}
