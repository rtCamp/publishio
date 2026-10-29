<?php
/**
 * Tests for Block_Tree.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Editing;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Path;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Tree;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Block_TreeTest
 */
#[CoversClass( Block_Tree::class )]
class Block_TreeTest extends TestCase {
	private const POST = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Title</h2>\n<!-- /wp:heading -->\n\n"
		. "<!-- wp:group -->\n<div class=\"wp-block-group\"><!-- wp:paragraph -->\n<p>A</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>B</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->\n\n"
		. "<!-- wp:paragraph -->\n<p>End</p>\n<!-- /wp:paragraph -->";

	/**
	 * A path that doesn't match the block at its position is refused, naming what is there.
	 */
	public function test_refuses_a_path_with_the_wrong_name(): void {
		$result = $this->tree()->get( $this->path( '1:heading' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'path_mismatch', $result->get_error_code() );
		$this->assertStringContainsString( 'core/group', $result->get_error_message() );
	}

	/**
	 * A path past the last block is refused with the count at that level.
	 */
	public function test_refuses_a_path_past_the_last_block(): void {
		$result = $this->tree()->get( $this->path( '1:group > 5:paragraph' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'path_not_found', $result->get_error_code() );
		$this->assertStringContainsString( '1:group has 2 blocks', $result->get_error_message() );
	}

	/**
	 * A path into a synced pattern is refused, pointing at the pattern's own path.
	 */
	public function test_refuses_a_path_inside_a_synced_pattern(): void {
		$tree   = Block_Tree::from_markup( '<!-- wp:block {"ref":12} /-->' );
		$result = $tree->get( $this->path( '0:block > 0:heading' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'inside_synced_pattern', $result->get_error_code() );
		$this->assertStringContainsString( 'replace 0:block', $result->get_error_message() );
	}

	/**
	 * An unchanged tree serializes to the same markup.
	 */
	public function test_unchanged_tree_round_trips(): void {
		$this->assertSame( self::POST, $this->tree()->to_markup() );
	}

	/**
	 * Inserting after a nested block puts it between its siblings and keeps the slots in step.
	 */
	public function test_inserts_between_nested_blocks(): void {
		$tree  = $this->tree();
		$paths = $tree->insert( $this->path( '1:group > 0:paragraph' ), 'after', Block_Tree::parse( '<!-- wp:paragraph --><p>X</p><!-- /wp:paragraph -->' ) );

		$this->assertIsArray( $paths );
		$this->assertSame( '1:group > 1:paragraph', $paths[0]->to_string() );
		$this->assertSame( [ 'Title', 'A', 'X', 'B', 'End' ], $this->texts( $tree ) );
		$this->assert_slots_in_step( $tree );
	}

	/**
	 * Inserting at the start and end of a container, and of the post, lands in the right place.
	 */
	public function test_inserts_at_start_and_end(): void {
		$tree = $this->tree();
		$new  = static fn ( string $text ): array => Block_Tree::parse( '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->' );

		$tree->insert( $this->path( '1:group' ), 'start', $new( 'S' ) );
		$tree->insert( $this->path( '1:group' ), 'end', $new( 'E' ) );
		$tree->insert( Block_Path::root(), 'start', $new( 'Top' ) );
		$tree->insert( Block_Path::root(), 'end', $new( 'Bottom' ) );

		$this->assertSame( [ 'Top', 'Title', 'S', 'A', 'B', 'E', 'End', 'Bottom' ], $this->texts( $tree ) );
		$this->assert_slots_in_step( $tree );
		$this->assertStringEndsWith( "<p>End</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph --><p>Bottom</p><!-- /wp:paragraph -->", $tree->to_markup() );
	}

	/**
	 * Inserting into a container with no inner blocks is refused.
	 */
	public function test_refuses_inserting_into_an_empty_container(): void {
		$tree   = Block_Tree::from_markup( '<!-- wp:group --><div class="wp-block-group"></div><!-- /wp:group -->' );
		$result = $tree->insert( $this->path( '0:group' ), 'end', Block_Tree::parse( '<!-- wp:paragraph --><p>X</p><!-- /wp:paragraph -->' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'empty_container', $result->get_error_code() );
	}

	/**
	 * Placements must be known, and before and after need a target block.
	 */
	public function test_refuses_bad_placements(): void {
		$tree = $this->tree();

		foreach ( [ $tree->spot( Block_Path::root(), 'after' ), $tree->spot( $this->path( '0:heading' ), 'inside' ) ] as $result ) {
			$this->assertWPError( $result );
			$this->assertSame( 'invalid_placement', $result->get_error_code() );
		}
	}

	/**
	 * Replacing a block with two blocks keeps both in its place.
	 */
	public function test_replaces_a_block_with_several(): void {
		$tree  = $this->tree();
		$paths = $tree->replace( $this->path( '1:group > 1:paragraph' ), Block_Tree::parse( '<!-- wp:paragraph --><p>B1</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>B2</p><!-- /wp:paragraph -->' ) );

		$this->assertIsArray( $paths );
		$this->assertSame( [ '1:group > 1:paragraph', '1:group > 2:paragraph' ], array_map( static fn ( Block_Path $p ): string => $p->to_string(), $paths ) );
		$this->assertSame( [ 'Title', 'A', 'B1', 'B2', 'End' ], $this->texts( $tree ) );
		$this->assert_slots_in_step( $tree );
	}

	/**
	 * Replacing a block with nothing is refused; deleting is its own call.
	 */
	public function test_refuses_replacing_a_block_with_nothing(): void {
		$result = $this->tree()->replace( $this->path( '0:heading' ), [] );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_placement', $result->get_error_code() );
	}

	/**
	 * Removing blocks resolves every path first, and a block inside another removed block is not removed twice.
	 */
	public function test_removes_several_blocks(): void {
		$tree   = $this->tree();
		$result = $tree->remove( [ $this->path( '0:heading' ), $this->path( '1:group > 0:paragraph' ), $this->path( '1:group' ) ] );

		$this->assertTrue( $result );
		$this->assertSame( [ 'End' ], $this->texts( $tree ) );
		$this->assertSame( "<!-- wp:paragraph -->\n<p>End</p>\n<!-- /wp:paragraph -->", $tree->to_markup() );
	}

	/**
	 * Removing the last inner block leaves an empty container whose HTML is intact.
	 */
	public function test_removing_every_inner_block_keeps_the_wrapper(): void {
		$tree = $this->tree();
		$tree->remove( [ $this->path( '1:group > 0:paragraph' ), $this->path( '1:group > 1:paragraph' ) ] );

		$group = $tree->get( $this->path( '1:group' ) );

		$this->assertIsArray( $group );
		$this->assertSame( [], $group['innerBlocks'] );
		$this->assertSame( "\n<div class=\"wp-block-group\"></div>\n", $group['innerHTML'] );
		$this->assert_slots_in_step( $tree );
	}

	/**
	 * Moving a block later among its siblings accounts for its own removal.
	 */
	public function test_moves_a_block_within_its_container(): void {
		$tree  = $this->tree();
		$paths = $tree->move( $this->path( '0:heading' ), $this->path( '2:paragraph' ), 'after' );

		$this->assertIsArray( $paths );
		$this->assertSame( '2:heading', $paths[0]->to_string() );
		$this->assertSame( [ 'A', 'B', 'End', 'Title' ], $this->texts( $tree ) );
		$this->assert_slots_in_step( $tree );
	}

	/**
	 * Moving a block into a container after it adjusts the container's position.
	 */
	public function test_moves_a_block_into_a_later_container(): void {
		$tree  = $this->tree();
		$paths = $tree->move( $this->path( '0:heading' ), $this->path( '1:group' ), 'start' );

		$this->assertIsArray( $paths );
		$this->assertSame( '0:group > 0:heading', $paths[0]->to_string() );
		$this->assertSame( [ 'Title', 'A', 'B', 'End' ], $this->texts( $tree ) );
		$this->assert_slots_in_step( $tree );
	}

	/**
	 * A block can't move inside itself.
	 */
	public function test_refuses_moving_a_block_inside_itself(): void {
		$result = $this->tree()->move( $this->path( '1:group' ), $this->path( '1:group > 0:paragraph' ), 'after' );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_placement', $result->get_error_code() );
	}

	/**
	 * Duplicating places copies right after the block.
	 */
	public function test_duplicates_a_block(): void {
		$tree  = $this->tree();
		$paths = $tree->duplicate( $this->path( '1:group > 0:paragraph' ), 2 );

		$this->assertIsArray( $paths );
		$this->assertSame( [ '1:group > 1:paragraph', '1:group > 2:paragraph' ], array_map( static fn ( Block_Path $p ): string => $p->to_string(), $paths ) );
		$this->assertSame( [ 'Title', 'A', 'A', 'A', 'B', 'End' ], $this->texts( $tree ) );
		$this->assert_slots_in_step( $tree );
	}

	/**
	 * HTML outside any block is kept as a block named core/freeform.
	 */
	public function test_parse_keeps_stray_html_as_freeform(): void {
		$blocks = Block_Tree::parse( "<p>Loose</p>\n\n<!-- wp:paragraph --><p>A</p><!-- /wp:paragraph -->" );

		$this->assertCount( 2, $blocks );
		$this->assertSame( 'core/freeform', Block_Tree::name_of( $blocks[0] ) );
		$this->assertSame( 'core/paragraph', Block_Tree::name_of( $blocks[1] ) );
	}

	/**
	 * The test post as a tree.
	 */
	private function tree(): Block_Tree {
		return Block_Tree::from_markup( self::POST );
	}

	/**
	 * Parse a path that must be valid.
	 *
	 * @param string $path The path.
	 */
	private function path( string $path ): Block_Path {
		$parsed = Block_Path::parse( $path );
		$this->assertInstanceOf( Block_Path::class, $parsed );

		return $parsed;
	}

	/**
	 * The text of every block with text, in document order, read back from the serialized markup.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree $tree The tree.
	 *
	 * @return list<string>
	 */
	private function texts( Block_Tree $tree ): array {
		preg_match_all( '#<(?:p|h2)[^>]*>([^<]*)</(?:p|h2)>#', $tree->to_markup(), $matches );

		return $matches[1];
	}

	/**
	 * Every container has one null slot per inner block, and the markup reparses to the same tree.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree $tree The tree.
	 */
	private function assert_slots_in_step( Block_Tree $tree ): void {
		$check = function ( array $blocks ) use ( &$check ): void {
			foreach ( $blocks as $block ) {
				$this->assertCount( count( $block['innerBlocks'] ), array_filter( $block['innerContent'], 'is_null' ), 'Slots out of step in ' . (string) $block['blockName'] );
				$this->assertSame( Block_Tree::own_html( $block ), $block['innerHTML'] );
				$check( $block['innerBlocks'] );
			}
		};

		$check( $tree->blocks() );
		$this->assertSame( $tree->to_markup(), Block_Tree::from_markup( $tree->to_markup() )->to_markup() );
	}
}
