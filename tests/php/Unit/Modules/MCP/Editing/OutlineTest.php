<?php
/**
 * Tests for Outline and Errors.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Editing;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Path;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Tree;
use rtCamp\Publishio\Modules\MCP\Editing\Errors;
use rtCamp\Publishio\Modules\MCP\Editing\Outline;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - OutlineTest
 */
#[CoversClass( Outline::class )]
#[CoversClass( Errors::class )]
class OutlineTest extends TestCase {
	/**
	 * Containers show the first text inside them, and images their alt text.
	 */
	public function test_lists_every_block_with_a_short_text(): void {
		$tree = Block_Tree::from_markup(
			'<!-- wp:cover --><div class="wp-block-cover"><div class="wp-block-cover__inner-container"><!-- wp:heading --><h2 class="wp-block-heading">Build &amp; ship</h2><!-- /wp:heading --></div></div><!-- /wp:cover -->'
			. '<!-- wp:image --><figure class="wp-block-image"><img src="https://example.com/a.jpg" alt="A bakery"/></figure><!-- /wp:image -->'
			. '<!-- wp:paragraph --><p>' . str_repeat( 'word ', 30 ) . '</p><!-- /wp:paragraph -->'
		);

		$outline = Outline::of( $tree, Block_Path::root() );

		$this->assertIsArray( $outline );
		$this->assertFalse( $outline['truncated'] );
		$this->assertSame( [ '0:cover', '0:cover > 0:heading', '1:image', '2:paragraph' ], array_column( $outline['rows'], 'path' ) );
		$this->assertSame( 'Build & ship', $outline['rows'][0]['text'] );
		$this->assertSame( 'A bakery', $outline['rows'][2]['text'] );
		$this->assertSame( 60, mb_strlen( $outline['rows'][3]['text'] ) );
		$this->assertStringEndsWith( '…', $outline['rows'][3]['text'] );
	}

	/**
	 * A synced pattern's row shows its override fields and this post's values, and nothing inside it.
	 */
	public function test_lists_synced_pattern_overrides(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$ref  = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_title'   => 'Footer CTA',
				'post_content' => '<!-- wp:heading {"metadata":{"name":"CTA heading","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><h2 class="wp-block-heading">Ready?</h2><!-- /wp:heading -->',
			]
		);
		$tree = Block_Tree::from_markup( '<!-- wp:block {"ref":' . $ref . ',"content":{"CTA heading":{"content":"Start now"}}} /-->' );

		$outline = Outline::of( $tree, Block_Path::root() );

		$this->assertIsArray( $outline );
		$this->assertSame( 'Synced pattern "Footer CTA"', $outline['rows'][0]['text'] );
		$this->assertEquals(
			[
				[
					'name'       => 'CTA heading',
					'block'      => 'core/heading',
					'attributes' => [ 'content' ],
					'values'     => (object) [ 'content' => 'Start now' ],
				],
			],
			$outline['rows'][0]['overrides'] ?? null
		);

		$unset = Outline::of( Block_Tree::from_markup( '<!-- wp:block {"ref":' . $ref . '} /-->' ), Block_Path::root() );

		$this->assertIsArray( $unset );
		$this->assertStringContainsString( '"values":{}', (string) wp_json_encode( $unset['rows'][0] ) );
	}

	/**
	 * After a change at the top of the post, the outline lists every top-level block but only opens the changed ones.
	 */
	public function test_outline_after_a_change(): void {
		$group = '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
		$tree  = Block_Tree::from_markup( sprintf( $group, 'A' ) . sprintf( $group, 'B' ) . sprintf( $group, 'C' ) );
		$path  = Block_Path::parse( '1:group' );

		$this->assertInstanceOf( Block_Path::class, $path );
		$this->assertSame(
			[ '0:group', '1:group', '1:group > 0:paragraph', '2:group' ],
			array_column( Outline::after_change( $tree, Block_Path::root(), [ $path ] )['rows'], 'path' )
		);
		$this->assertSame( [ '0:group', '1:group', '2:group' ], array_column( Outline::after_change( $tree, Block_Path::root(), [] )['rows'], 'path' ) );
		$this->assertSame( [ '1:group', '1:group > 0:paragraph' ], array_column( Outline::after_change( $tree, $path, [] )['rows'], 'path' ) );
	}

	/**
	 * A pattern the user can't read shows a placeholder instead of its title.
	 */
	public function test_hides_titles_of_patterns_the_user_cannot_read(): void {
		$ref = self::factory()->post->create(
			[
				'post_type'   => 'wp_block',
				'post_status' => 'private',
				'post_title'  => 'Internal pricing',
				'post_author' => self::factory()->user->create( [ 'role' => 'administrator' ] ),
			]
		);
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$outline = Outline::of( Block_Tree::from_markup( '<!-- wp:block {"ref":' . $ref . '} /-->' ), Block_Path::root() );

		$this->assertIsArray( $outline );
		$this->assertSame( 'Synced pattern "Unavailable pattern"', $outline['rows'][0]['text'] );
	}

	/**
	 * An outline under a block starts with that block, and stops at MAX_ROWS.
	 */
	public function test_outline_under_a_block_and_truncation(): void {
		$paragraphs = str_repeat( '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->', Outline::MAX_ROWS + 5 );
		$tree       = Block_Tree::from_markup( '<!-- wp:group --><div class="wp-block-group">' . $paragraphs . '</div><!-- /wp:group -->' );
		$path       = Block_Path::parse( '0:group' );
		$this->assertInstanceOf( Block_Path::class, $path );

		$outline = Outline::of( $tree, $path );

		$this->assertIsArray( $outline );
		$this->assertSame( '0:group', $outline['rows'][0]['path'] );
		$this->assertCount( Outline::MAX_ROWS, $outline['rows'] );
		$this->assertTrue( $outline['truncated'] );
	}

	/**
	 * One problem becomes an error with that problem's code; several become one invalid_markup error listing all.
	 */
	public function test_errors_carry_codes_and_every_problem(): void {
		$one = Errors::from_problems( [ Errors::problem( 'unknown_block', '0:acme/x', 'acme/x isn\'t registered.' ) ] );

		$this->assertSame( 'unknown_block', $one->get_error_code() );
		$this->assertSame( 'unknown_block: At 0:acme/x: acme/x isn\'t registered.', $one->get_error_message() );

		$problems = array_map( static fn ( int $i ): array => Errors::problem( 'unknown_block', $i . ':acme/x', 'Not registered.' ), range( 0, Errors::MAX_PROBLEMS + 2 ) );
		$many     = Errors::from_problems( $problems );
		$data     = $many->get_error_data();

		$this->assertSame( 'invalid_markup', $many->get_error_code() );
		$this->assertStringContainsString( 'has 23 problems. The first 20 are listed', $many->get_error_message() );
		$this->assertStringContainsString( '20. unknown_block at 19:acme/x: Not registered.', $many->get_error_message() );
		$this->assertStringNotContainsString( '21. ', $many->get_error_message() );
		$this->assertIsArray( $data );
		$this->assertCount( Errors::MAX_PROBLEMS, $data['problems'] );
	}
}
