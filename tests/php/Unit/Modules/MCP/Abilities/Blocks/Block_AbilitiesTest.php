<?php
/**
 * Tests for the block abilities and Block_Catalog.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Blocks
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Blocks;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Abilities\Blocks\Get_Block_Type;
use rtCamp\Publishio\Modules\MCP\Abilities\Blocks\List_Block_Types;
use rtCamp\Publishio\Modules\MCP\Abilities\Blocks\Render_Blocks;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Catalog;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Block_AbilitiesTest
 */
#[CoversClass( List_Block_Types::class )]
#[CoversClass( Get_Block_Type::class )]
#[CoversClass( Render_Blocks::class )]
#[CoversClass( Block_Catalog::class )]
class Block_AbilitiesTest extends TestCase {
	private const PATTERN = 'publishio-test/verse-example';

	/**
	 * Run as an editor, with a pattern that holds the example markup.
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		register_block_pattern(
			self::PATTERN,
			[
				'title'   => 'Verse example',
				'content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:verse {"textAlign":"center"} --><pre class="wp-block-verse has-text-align-center">Roses</pre><!-- /wp:verse --></div><!-- /wp:group -->',
			]
		);
	}

	/**
	 * Remove the pattern and reset the user.
	 */
	protected function tearDown(): void {
		unregister_block_pattern( self::PATTERN );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * The list leaves out blocks hidden from the inserter and filters by search, source and post type.
	 */
	public function test_lists_block_types_with_filters(): void {
		$all = $this->run_ability( 'list-block-types', [] );

		$this->assertIsArray( $all );
		$this->assertNotContains( 'core/missing', array_column( $all['block_types'], 'name' ) );
		$this->assertContains( 'core/paragraph', array_column( $all['block_types'], 'name' ) );
		$this->assertContains( 'text', array_column( $all['categories'], 'slug' ) );

		$search = $this->run_ability( 'list-block-types', [ 'search' => 'verse' ] );
		$this->assertIsArray( $search );
		$this->assertContains( 'core/verse', array_column( $search['block_types'], 'name' ) );
		$this->assertNotContains( 'core/paragraph', array_column( $search['block_types'], 'name' ) );

		$filter  = static fn (): array => [ 'core/paragraph', 'core/heading' ];
		add_filter( 'allowed_block_types_all', $filter );
		$allowed = $this->run_ability( 'list-block-types', [ 'post_type' => 'post' ] );
		remove_filter( 'allowed_block_types_all', $filter );

		$this->assertIsArray( $allowed );
		$this->assertSame( [ 'core/heading', 'core/paragraph' ], $this->sorted( array_column( $allowed['block_types'], 'name' ) ) );
		$this->assertSame( [], $this->run_ability( 'list-block-types', [ 'source' => 'theme' ] )['block_types'] ?? null );
	}

	/**
	 * Unknown blocks are refused; known ones list attributes with where they live, and example markup from a pattern.
	 */
	public function test_get_block_type(): void {
		$unknown = $this->run_ability( 'get-block-type', [ 'name' => 'acme/nothing' ] );

		$this->assertWPError( $unknown );
		$this->assertSame( 'unknown_block', $unknown->get_error_code() );

		$verse = $this->run_ability( 'get-block-type', [ 'name' => 'verse' ] );

		$this->assertIsArray( $verse );
		$this->assertSame( 'core/verse', $verse['name'] );
		$this->assertSame( 'core', $verse['source'] );
		$this->assertTrue( $this->attribute( $verse, 'content' )['in_html'] );
		$this->assertSame( '<!-- wp:verse {"textAlign":"center"} --><pre class="wp-block-verse has-text-align-center">Roses</pre><!-- /wp:verse -->', $verse['example_markup'] );

		$column = $this->run_ability( 'get-block-type', [ 'name' => 'column' ] );

		$this->assertIsArray( $column );
		$this->assertSame( [ 'core/columns' ], $column['parent'] );
	}

	/**
	 * A block is the theme's only when its assets are inside the active theme's folder, not a folder whose name starts the same.
	 */
	public function test_source_of_needs_the_whole_theme_folder(): void {
		$theme = get_stylesheet_directory_uri();

		wp_register_script( 'publishio-test-theme-block', $theme . '/blocks/a.js', [], '1', true );
		wp_register_script( 'publishio-test-sibling-block', $theme . '-pro/blocks/a.js', [], '1', true );

		$in_theme = Block_Catalog::source_of( new \WP_Block_Type( 'publishio-test/in-theme', [ 'editor_script_handles' => [ 'publishio-test-theme-block' ] ] ) );
		$sibling  = Block_Catalog::source_of( new \WP_Block_Type( 'publishio-test/sibling', [ 'editor_script_handles' => [ 'publishio-test-sibling-block' ] ] ) );

		wp_deregister_script( 'publishio-test-theme-block' );
		wp_deregister_script( 'publishio-test-sibling-block' );

		$this->assertSame( 'theme', $in_theme );
		$this->assertSame( 'plugin', $sibling );
	}

	/**
	 * A dry run reports what a write would refuse, renders anyway, and saves nothing.
	 */
	public function test_render_blocks_reports_problems_and_renders(): void {
		$result = $this->run_ability( 'render-blocks', [ 'markup' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph --><!-- wp:column --><div class="wp-block-column"></div><!-- /wp:column -->' ] );

		$this->assertIsArray( $result );
		$this->assertFalse( $result['valid'] );
		$this->assertSame( [ 'invalid_parent' ], array_column( $result['errors'], 'code' ) );
		$this->assertStringContainsString( '<p', $result['html'] );

		$classic = $this->run_ability(
			'render-blocks',
			[
				'markup'    => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
				'post_type' => 'attachment',
			]
		);

		$this->assertWPError( $classic );
		$this->assertSame( 'no_block_editor', $classic->get_error_code() );
	}

	/**
	 * With a post, dynamic blocks render in its context.
	 */
	public function test_render_blocks_uses_the_post_as_context(): void {
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'draft',
				'post_title'  => 'Context title',
				'post_author' => get_current_user_id(),
			]
		);

		$result = $this->run_ability(
			'render-blocks',
			[
				'markup'  => '<!-- wp:post-title /-->',
				'post_id' => $post_id,
			]
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['valid'] );
		$this->assertStringContainsString( 'Context title', $result['html'] );
	}

	/**
	 * Run an ability.
	 *
	 * @param string               $name  The ability name without the prefix.
	 * @param array<string, mixed> $input The input.
	 */
	private function run_ability( string $name, array $input ): mixed {
		return wp_get_ability( 'publishio/' . $name )->execute( $input ); // @phpstan-ignore method.nonObject
	}

	/**
	 * One attribute from a get-block-type result.
	 *
	 * @param array<string, mixed> $type The result.
	 * @param string               $name The attribute name.
	 *
	 * @return array<string, mixed>
	 */
	private function attribute( array $type, string $name ): array {
		foreach ( $type['attributes'] as $attribute ) {
			if ( $name === $attribute['name'] ) {
				return $attribute;
			}
		}

		$this->fail( 'No attribute ' . $name );
	}

	/**
	 * A sorted copy of a list.
	 *
	 * @param array<int, string> $items The items.
	 *
	 * @return array<int, string>
	 */
	private function sorted( array $items ): array {
		sort( $items );

		return $items;
	}
}
