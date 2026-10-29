<?php
/**
 * Tests for Post_Writer.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Editing;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Tree;
use rtCamp\Publishio\Modules\MCP\Editing\Errors;
use rtCamp\Publishio\Modules\MCP\Editing\Live_Approval;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Writer;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Post_WriterTest
 */
#[CoversClass( Post_Writer::class )]
#[CoversClass( Errors::class )]
class Post_WriterTest extends TestCase {
	/**
	 * The editor running each test.
	 *
	 * @var int
	 */
	private int $editor;

	/**
	 * Run as an editor.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->editor = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $this->editor );
	}

	/**
	 * Missing, trashed and auto-draft posts are not found.
	 */
	public function test_refuses_posts_that_do_not_exist(): void {
		$trashed = self::factory()->post->create( [ 'post_status' => 'trash' ] );

		$this->assertSame( 'post_not_found', $this->code( Post_Writer::load( 999999 ) ) );
		$this->assertSame( 'post_not_found', $this->code( Post_Writer::load( $trashed ) ) );
	}

	/**
	 * A post the user can't edit is refused.
	 */
	public function test_refuses_a_post_the_user_cannot_edit(): void {
		$post_id = self::factory()->post->create( [ 'post_author' => $this->editor ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'contributor' ] ) );

		$this->assertSame( 'forbidden', $this->code( Post_Writer::load( $post_id ) ) );
	}

	/**
	 * Post types without the block editor, and saved patterns, are refused.
	 */
	public function test_refuses_post_types_without_the_block_editor(): void {
		register_post_type(
			'publishio_classic',
			[
				'public'       => true,
				'show_in_rest' => false,
			]
		);
		$classic = self::factory()->post->create( [ 'post_type' => 'publishio_classic' ] );
		$pattern = self::factory()->post->create( [ 'post_type' => 'wp_block' ] );

		$this->assertSame( 'no_block_editor', $this->code( Post_Writer::load( $classic ) ) );
		$this->assertSame( 'no_block_editor', $this->code( Post_Writer::load( $pattern ) ) );
		$this->assertFalse( Post_Writer::is_editable_type( 'wp_template' ) );
		$this->assertTrue( Post_Writer::is_editable_type( 'page' ) );

		unregister_post_type( 'publishio_classic' );
	}

	/**
	 * A post open in the editor is refused, including by the current user in another tab.
	 */
	public function test_refuses_a_post_open_in_the_editor(): void {
		$post = $this->post( 'draft' );
		$tree = Block_Tree::from_markup( '<!-- wp:paragraph --><p>New</p><!-- /wp:paragraph -->' );

		update_post_meta( $post->ID, '_edit_lock', time() . ':' . $this->editor );
		$this->assertSame( 'post_locked', $this->code( Post_Writer::save( $post, $tree ) ) );

		update_post_meta( $post->ID, '_edit_lock', ( time() - 600 ) . ':' . $this->editor );
		$this->assertInstanceOf( Block_Tree::class, Post_Writer::save( $post, $tree ) );
	}

	/**
	 * A live post needs a token, which the refusal provides.
	 */
	public function test_live_post_needs_a_valid_token(): void {
		$post = $this->post( 'publish' );
		$tree = Block_Tree::from_markup( '<!-- wp:paragraph --><p>New</p><!-- /wp:paragraph -->' );

		$refused = Post_Writer::save( $post, $tree );
		$this->assertWPError( $refused );
		$this->assertSame( 'live_post_needs_approval', $refused->get_error_code() );
		$this->assertMatchesRegularExpression( '/approval_token: "(\d+\.[a-f0-9]{64})"/', $refused->get_error_message() );

		preg_match( '/approval_token: "([^"]+)"/', $refused->get_error_message(), $matches );
		$this->assertSame( 'live_post_needs_approval', $this->code( Post_Writer::save( $post, $tree, Live_Approval::issue( $post->ID + 1, $this->editor ) ) ) );
		$this->assertInstanceOf( Block_Tree::class, Post_Writer::save( $post, $tree, $matches[1] ) );
		$this->assertStringContainsString( '<p>New</p>', get_post_field( 'post_content', $post->ID ) );
	}

	/**
	 * Backslashes in block attributes survive the save.
	 */
	public function test_keeps_backslashes(): void {
		$post   = $this->post( 'draft' );
		$markup = '<!-- wp:paragraph {"metadata":{"name":"Line \\u003cbr\\u003e \\\\ path"}} --><p>C:\\temp</p><!-- /wp:paragraph -->';

		$this->assertInstanceOf( Block_Tree::class, Post_Writer::save( $post, Block_Tree::from_markup( $markup ) ) );

		$saved = parse_blocks( get_post_field( 'post_content', $post->ID ) )[0];

		$this->assertSame( 'Line <br> \\ path', $saved['attrs']['metadata']['name'] );
		$this->assertSame( '<p>C:\\temp</p>', $saved['innerHTML'] );
	}

	/**
	 * An unchanged post is not saved again.
	 */
	public function test_skips_saving_unchanged_content(): void {
		$post     = $this->post( 'draft' );
		$modified = $post->post_modified_gmt;
		$revision = count( wp_get_post_revisions( $post->ID ) );

		$this->assertInstanceOf( Block_Tree::class, Post_Writer::save( $post, Block_Tree::from_markup( $post->post_content ) ) );
		$this->assertSame( $modified, get_post_field( 'post_modified_gmt', $post->ID ) );
		$this->assertCount( $revision, wp_get_post_revisions( $post->ID ) );
	}

	/**
	 * A post the user can't read is reported as not found, so its title isn't revealed.
	 */
	public function test_hides_posts_the_user_cannot_read(): void {
		$private = self::factory()->post->create(
			[
				'post_status' => 'private',
				'post_author' => $this->editor,
				'post_title'  => 'Secret launch',
			]
		);
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'contributor' ] ) );

		$result = Post_Writer::load( $private );

		$this->assertWPError( $result );
		$this->assertSame( 'post_not_found', $result->get_error_code() );
		$this->assertStringNotContainsString( 'Secret launch', $result->get_error_message() );
	}

	/**
	 * Saving checks the capability itself, whoever loaded the post.
	 */
	public function test_save_checks_the_capability(): void {
		$post = $this->post( 'draft' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'contributor' ] ) );

		$this->assertSame( 'forbidden', $this->code( Post_Writer::save( $post, Block_Tree::from_markup( '<!-- wp:paragraph --><p>New</p><!-- /wp:paragraph -->' ) ) ) );
	}

	/**
	 * A post changed by another call since it was loaded is refused instead of overwritten.
	 */
	public function test_refuses_a_post_changed_since_it_was_loaded(): void {
		$post = $this->post( 'draft' );

		wp_update_post(
			[
				'ID'           => $post->ID,
				'post_content' => '<!-- wp:paragraph --><p>From another call</p><!-- /wp:paragraph -->',
			]
		);

		$this->assertSame( 'post_changed', $this->code( Post_Writer::save( $post, Block_Tree::from_markup( '<!-- wp:paragraph --><p>New</p><!-- /wp:paragraph -->' ) ) ) );
		$this->assertStringContainsString( 'From another call', get_post_field( 'post_content', $post->ID ) );
	}

	/**
	 * The tree returned is what was stored, after save filters.
	 */
	public function test_returns_the_tree_as_saved(): void {
		$post   = $this->post( 'draft' );
		$filter = static fn ( string $content ): string => str_replace( 'New', 'Filtered', $content );
		add_filter( 'content_save_pre', $filter );

		$saved = Post_Writer::save( $post, Block_Tree::from_markup( '<!-- wp:paragraph --><p>New</p><!-- /wp:paragraph -->' ) );

		remove_filter( 'content_save_pre', $filter );

		$this->assertInstanceOf( Block_Tree::class, $saved );
		$this->assertSame( '<!-- wp:paragraph --><p>Filtered</p><!-- /wp:paragraph -->', $saved->to_markup() );
	}

	/**
	 * A post by the editor, with one paragraph.
	 *
	 * @param string $status The post status.
	 */
	private function post( string $status ): \WP_Post {
		$post = Post_Writer::load(
			self::factory()->post->create(
				[
					'post_status'  => $status,
					'post_author'  => $this->editor,
					'post_content' => '<!-- wp:paragraph --><p>Old</p><!-- /wp:paragraph -->',
				]
			)
		);
		$this->assertInstanceOf( \WP_Post::class, $post );

		return $post;
	}

	/**
	 * The error code of a result, or "ok".
	 *
	 * @param mixed $result A result.
	 */
	private function code( mixed $result ): string {
		return is_wp_error( $result ) ? (string) $result->get_error_code() : 'ok';
	}
}
