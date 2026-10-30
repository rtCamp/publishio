<?php
/**
 * Tests for the post abilities and get-post-types.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Content
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Create_Post;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Get_Post;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Get_Post_Content;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Post_Details;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Update_Post;
use rtCamp\Publishio\Modules\MCP\Abilities\Site\Get_Post_Types;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Post_AbilitiesTest
 */
#[CoversClass( Create_Post::class )]
#[CoversClass( Get_Post::class )]
#[CoversClass( Get_Post_Content::class )]
#[CoversClass( Update_Post::class )]
#[CoversClass( Post_Details::class )]
#[CoversClass( Get_Post_Types::class )]
class Post_AbilitiesTest extends TestCase {
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
	 * Reset the current user.
	 */
	protected function tearDown(): void {
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * The create-post tool refuses content, types without the block editor, and parents of another type.
	 */
	public function test_create_refuses_invalid_input(): void {
		$post_id = self::factory()->post->create();

		$this->assertSame(
			'ability_invalid_input',
			$this->code(
				$this->run_ability(
					'create-post',
					[
						'title'   => 'X',
						'content' => '<p>No</p>',
					]
				)
			)
		);
		$this->assertSame(
			'no_block_editor',
			$this->code(
				$this->run_ability(
					'create-post',
					[
						'title'     => 'X',
						'post_type' => 'wp_block',
					]
				)
			)
		);
		$this->assertSame(
			'invalid_parent_post',
			$this->code(
				$this->run_ability(
					'create-post',
					[
						'title'     => 'X',
						'post_type' => 'page',
						'parent_id' => $post_id,
					]
				)
			)
		);
	}

	/**
	 * The create-post tool makes an empty draft with the details given.
	 */
	public function test_create_makes_an_empty_draft(): void {
		$parent = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$result = $this->run_ability(
			'create-post',
			[
				'post_type' => 'page',
				'title'     => 'About us',
				'slug'      => 'About Us!',
				'excerpt'   => 'Who we are.',
				'parent_id' => $parent,
			]
		);

		$this->assertIsArray( $result );

		$post = get_post( $result['post_id'] );

		$this->assertInstanceOf( \WP_Post::class, $post );
		$this->assertSame( [ 'draft', 'page', '', 'about-us', 'Who we are.', $parent ], [ $post->post_status, $post->post_type, $post->post_content, $post->post_name, $post->post_excerpt, $post->post_parent ] );
	}

	/**
	 * The get-post tool refuses posts the user can't read, lists a post's outline without its markup, and can outline just one block.
	 */
	public function test_get_post(): void {
		$private = self::factory()->post->create(
			[
				'post_status' => 'private',
				'post_author' => self::factory()->user->create( [ 'role' => 'administrator' ] ),
			]
		);
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'author' ] ) );
		$this->assertSame( 'post_not_found', $this->code( $this->run_ability( 'get-post', [ 'post_id' => $private ] ) ) );
		wp_set_current_user( $this->editor );

		$post_id = self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">Hello</h2><!-- /wp:heading -->',
			]
		);
		$result  = $this->run_ability( 'get-post', [ 'post_id' => $post_id ] );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['is_live'] );
		$this->assertTrue( $result['block_editor'] );
		$this->assertArrayNotHasKey( 'content', $result );
		$this->assertSame(
			[
				[
					'path' => '0:heading',
					'text' => 'Hello',
				],
			],
			$result['outline']['rows']
		);

		$nested = self::factory()->post->create(
			[
				'post_status'  => 'draft',
				'post_content' => '<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph --><!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>Inside</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
			]
		);
		$under  = $this->run_ability(
			'get-post',
			[
				'post_id' => $nested,
				'path'    => '1:group',
			]
		);

		$this->assertIsArray( $under );
		$this->assertSame( [ '1:group', '1:group > 0:paragraph' ], array_column( $under['outline']['rows'], 'path' ) );
		$this->assertSame(
			'path_not_found',
			$this->code(
				$this->run_ability(
					'get-post',
					[
						'post_id' => $nested,
						'path'    => '5:group',
					]
				)
			)
		);
	}

	/**
	 * The get-post-content tool returns the whole post or one block, and refuses bad paths.
	 */
	public function test_get_post_content(): void {
		$content = '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>A</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
		$post_id = self::factory()->post->create(
			[
				'post_status'  => 'draft',
				'post_content' => $content,
			]
		);

		$this->assertSame(
			'invalid_path',
			$this->code(
				$this->run_ability(
					'get-post-content',
					[
						'post_id' => $post_id,
						'path'    => 'group',
					]
				)
			)
		);

		$whole = $this->run_ability( 'get-post-content', [ 'post_id' => $post_id ] );
		$block = $this->run_ability(
			'get-post-content',
			[
				'post_id' => $post_id,
				'path'    => '0:group > 0:paragraph',
			]
		);

		$this->assertIsArray( $whole );
		$this->assertIsArray( $block );
		$this->assertSame( $content, $whole['markup'] );
		$this->assertSame( '<!-- wp:paragraph --><p>A</p><!-- /wp:paragraph -->', $block['markup'] );
	}

	/**
	 * The update-post tool needs approval on a live post, refuses unknown templates, and changes only the fields given.
	 */
	public function test_update_post(): void {
		$live = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->assertSame(
			'live_post_needs_approval',
			$this->code(
				$this->run_ability(
					'update-post',
					[
						'post_id' => $live,
						'title'   => 'New',
					]
				)
			)
		);

		$page = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_title'  => 'Old title',
			]
		);
		$this->assertSame(
			'invalid_template',
			$this->code(
				$this->run_ability(
					'update-post',
					[
						'post_id'  => $page,
						'template' => 'not-a-template',
					]
				)
			)
		);

		$result = $this->run_ability(
			'update-post',
			[
				'post_id' => $page,
				'excerpt' => 'Summary',
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( [ 'Old title', 'Summary' ], [ $result['title'], $result['excerpt'] ] );
	}

	/**
	 * The get-post-types tool lists only types the user can edit, and leaves out saved patterns.
	 */
	public function test_get_post_types(): void {
		$result = $this->run_ability( 'get-post-types', [] );

		$this->assertIsArray( $result );

		$slugs = array_column( $result['post_types'], 'slug' );

		$this->assertContains( 'post', $slugs );
		$this->assertContains( 'page', $slugs );
		$this->assertNotContains( 'wp_block', $slugs );
		$this->assertTrue( $result['post_types'][ array_search( 'page', $slugs, true ) ]['hierarchical'] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertSame( 'ability_invalid_permissions', $this->code( $this->run_ability( 'get-post-types', [] ) ) );
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
	 * The error code of a result, or "ok".
	 *
	 * @param mixed $result A result.
	 */
	private function code( mixed $result ): string {
		return is_wp_error( $result ) ? (string) $result->get_error_code() : 'ok';
	}
}
