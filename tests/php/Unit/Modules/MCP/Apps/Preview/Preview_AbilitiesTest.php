<?php
/**
 * Tests for render-preview, preview-post and the preview card text.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Apps\Preview
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Apps\Preview;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Preview_Post;
use rtCamp\Publishio\Modules\MCP\Apps\Preview\App;
use rtCamp\Publishio\Modules\MCP\Apps\Preview\Page_Details;
use rtCamp\Publishio\Modules\MCP\Apps\Preview\Preview_Card;
use rtCamp\Publishio\Modules\MCP\Apps\Preview\Render_Preview;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Preview_AbilitiesTest
 */
#[CoversClass( Render_Preview::class )]
#[CoversClass( Preview_Post::class )]
#[CoversClass( Preview_Card::class )]
#[CoversClass( Page_Details::class )]
class Preview_AbilitiesTest extends TestCase {
	private const CONTENT = '<!-- wp:heading --><h2 class="wp-block-heading">Our story</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Open 7 days a week</p><!-- /wp:paragraph --><!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>Inside</p><!-- /wp:paragraph --></div><!-- /wp:group --><!-- wp:spacer --><div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';

	/**
	 * Run as an editor, with pretty permalinks as Publishio requires.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->set_permalink_structure( '/%postname%/' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
	}

	/**
	 * Reset the user, permalinks and the search engine setting.
	 */
	protected function tearDown(): void {
		$this->set_permalink_structure( '' );
		update_option( 'blog_public', '1' );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Previews refuse posts the user can't edit and paths that don't exist.
	 */
	public function test_refusals(): void {
		$other = self::factory()->post->create( [ 'post_status' => 'draft' ] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'contributor' ] ) );
		$this->assertSame( 'post_not_found', $this->code( $this->card( 'page', [ 'post_id' => $other ] ) ) );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$post_id = $this->page();

		$this->assertSame(
			'path_not_found',
			$this->code(
				$this->card(
					'blocks',
					[
						'post_id' => $post_id,
						'paths'   => [ '9:paragraph' ],
					]
				)
			)
		);
		$this->assertSame( 'invalid_path', $this->code( $this->card( 'blocks', [ 'post_id' => $post_id ] ) ) );
		$this->assertSame( 'unknown_pattern', $this->code( $this->card( 'pattern', [ 'pattern' => 'acme/none' ] ) ) );
	}

	/**
	 * Inserted blocks are named by their first heading, else one block's text, else the pattern's title, else the block's type; the line says where they went.
	 */
	public function test_blocks_card(): void {
		$post_id = $this->page();

		$after = $this->card(
			'blocks',
			[
				'post_id'   => $post_id,
				'paths'     => [ '1:paragraph' ],
				'placement' => 'after',
				'targeted'  => true,
			]
		);

		$this->assertIsArray( $after );
		$this->assertSame( 'Open 7 days a week', $after['title'] );
		$this->assertSame( 'Added', $after['status']['word'] );
		$this->assertSame( 'About us, after Our story', $after['subtitle'] );
		$this->assertSame( 'The block was added to About us. Only the preview didn\'t load.', $after['error'] );

		$before = $this->card(
			'blocks',
			[
				'post_id'   => $post_id,
				'paths'     => [ '0:heading', '1:paragraph' ],
				'placement' => 'before',
				'targeted'  => true,
			]
		);

		$this->assertIsArray( $before );
		$this->assertSame( 'Our story', $before['title'] );
		$this->assertSame( 'About us, before Inside', $before['subtitle'] );
		$this->assertStringStartsWith( 'The blocks were added', $before['error'] );

		$inside = $this->card(
			'blocks',
			[
				'post_id'   => $post_id,
				'paths'     => [ '2:group > 0:paragraph' ],
				'placement' => 'end',
				'targeted'  => true,
			]
		);

		$this->assertIsArray( $inside );
		$this->assertSame( 'About us, inside Inside', $inside['subtitle'] );

		$end = $this->card(
			'blocks',
			[
				'post_id' => $post_id,
				'paths'   => [ '3:spacer' ],
			]
		);

		$this->assertIsArray( $end );
		$this->assertSame( 'Spacer', $end['title'] );
		$this->assertSame( 'About us, at the end', $end['subtitle'] );

		$top = $this->card(
			'blocks',
			[
				'post_id'   => $post_id,
				'paths'     => [ '3:spacer' ],
				'placement' => 'start',
			]
		);

		$this->assertIsArray( $top );
		$this->assertSame( 'About us, at the top', $top['subtitle'] );
	}

	/**
	 * A page's status word follows WordPress, and a draft shows the address it gets when published.
	 */
	public function test_page_card(): void {
		$post_id = $this->page();
		$card    = $this->card( 'page', [ 'post_id' => $post_id ] );

		$this->assertIsArray( $card );
		$this->assertSame( 'About us', $card['title'] );
		$this->assertSame(
			[
				'word' => 'Draft',
				'tone' => 'warn',
			],
			$card['status']
		);
		$this->assertStringEndsWith( 'about-us', $card['subtitle'] );
		$this->assertStringNotContainsString( 'http', $card['subtitle'] );

		$words = [];

		foreach ( [ 'pending', 'publish', 'private' ] as $status ) {
			wp_update_post(
				[
					'ID'          => $post_id,
					'post_status' => $status,
				]
			);
			$words[] = $this->card( 'page', [ 'post_id' => $post_id ] )['status']['word'] ?? '';
		}

		$this->assertSame( [ 'Pending review', 'Published', 'Private' ], $words );

		$untitled = self::factory()->post->create(
			[
				'post_status' => 'draft',
				'post_title'  => '',
			]
		);

		$this->assertSame( '(no title)', $this->card( 'page', [ 'post_id' => $untitled ] )['title'] ?? '' );
	}

	/**
	 * A saved pattern's line names its sync status and first category; an update is blue.
	 */
	public function test_pattern_card(): void {
		$id = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => 'Footer CTA',
				'post_content' => '<!-- wp:paragraph --><p>Ready?</p><!-- /wp:paragraph -->',
			]
		);
		wp_set_object_terms( $id, [ 'Calls to action' ], 'wp_pattern_category' );

		$saved = $this->card( 'pattern', [ 'pattern' => 'core/block/' . $id ] );

		$this->assertIsArray( $saved );
		$this->assertSame( 'Footer CTA', $saved['title'] );
		$this->assertSame( 'Synced pattern · Calls to action', $saved['subtitle'] );
		$this->assertSame( 'ok', $saved['status']['tone'] );
		$this->assertNotNull( $saved['edit_url'] );

		$updated = $this->card(
			'pattern',
			[
				'pattern' => 'core/block/' . $id,
				'updated' => true,
			]
		);

		$this->assertIsArray( $updated );
		$this->assertSame(
			[
				'word' => 'Updated',
				'tone' => 'info',
			],
			$updated['status']
		);
		$this->assertSame( 'The pattern was updated. Only the preview didn\'t load.', $updated['error'] );

		$body = $this->body( 'pattern', [ 'pattern' => 'core/block/' . $id ] );

		$this->assertIsArray( $body );
		$this->assertStringContainsString( 'Ready?', $body['html'] );
	}

	/**
	 * The body is a whole themed document of only the inserted blocks; a page also gets its search and social details.
	 */
	public function test_bodies(): void {
		$post_id = $this->page();
		$blocks  = $this->body(
			'blocks',
			[
				'post_id' => $post_id,
				'paths'   => [ '1:paragraph' ],
			]
		);

		$this->assertIsArray( $blocks );
		$this->assertStringStartsWith( '<!DOCTYPE html>', trim( $blocks['html'] ) );
		$this->assertStringContainsString( 'Open 7 days a week', $blocks['html'] );
		$this->assertStringNotContainsString( 'Our story', $blocks['html'] );
		$this->assertStringContainsString( 'is-layout-constrained has-global-padding', $blocks['html'] );

		$page = $this->body( 'page', [ 'post_id' => $post_id ] );

		$this->assertIsArray( $page );
		$this->assertStringContainsString( 'Our story', $page['html'] );
		$this->assertSame( 'Family bakery since 1998.', $page['search']['description'] );
		$this->assertStringStartsWith( 'About us', $page['search']['title'] );
		$this->assertStringEndsWith( 'about-us', $page['search']['address'] );
		$this->assertFalse( $page['search']['hidden'] );
		$this->assertSame( $page['search']['title'], $page['social']['title'] );
		$this->assertNull( $page['social']['image'] );

		update_option( 'blog_public', '0' );
		wp_update_post(
			[
				'ID'           => $post_id,
				'post_excerpt' => '',
			]
		);

		$hidden = $this->body( 'page', [ 'post_id' => $post_id ] );

		$this->assertIsArray( $hidden );
		$this->assertTrue( $hidden['search']['hidden'] );
		$this->assertNull( $hidden['search']['description'] );
	}

	/**
	 * The preview-post tool returns the links to share, and only the tools that change what the user sees open the app.
	 */
	public function test_preview_post_and_app_wiring(): void {
		$post_id = $this->page();
		$result  = wp_get_ability( 'publishio/preview-post' )->execute( [ 'post_id' => $post_id ] ); // @phpstan-ignore method.nonObject

		$this->assertIsArray( $result );
		$this->assertSame( 'About us', $result['title'] );
		$this->assertSame( 'draft', $result['status'] );
		$this->assertStringContainsString( 'preview=true', $result['preview_url'] );
		$this->assertNotNull( $result['edit_url'] );

		foreach ( [ 'preview-post', 'insert-blocks', 'insert-pattern', 'create-pattern', 'update-pattern' ] as $name ) {
			$this->assertSame( App::URI, wp_get_ability( 'publishio/' . $name )->get_meta()['mcp']['_meta']['ui']['resourceUri'] ?? null, $name ); // @phpstan-ignore method.nonObject
		}

		$this->assertArrayNotHasKey( '_meta', wp_get_ability( 'publishio/replace-block' )->get_meta()['mcp'] ); // @phpstan-ignore method.nonObject
		$this->assertSame( [ 'app' ], wp_get_ability( 'publishio/render-preview' )->get_meta()['mcp']['_meta']['ui']['visibility'] ?? null ); // @phpstan-ignore method.nonObject
	}

	/**
	 * A draft page by the current user.
	 */
	private function page(): int {
		return self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => 'About us',
				'post_name'    => 'about-us',
				'post_excerpt' => 'Family bakery since 1998.',
				'post_author'  => get_current_user_id(),
				'post_content' => self::CONTENT,
			]
		);
	}

	/**
	 * Ask render-preview for a card.
	 *
	 * @param string               $view  The view.
	 * @param array<string, mixed> $input The rest of the input.
	 */
	private function card( string $view, array $input ): mixed {
		return $this->render( $view, 'card', $input );
	}

	/**
	 * Ask render-preview for a body.
	 *
	 * @param string               $view  The view.
	 * @param array<string, mixed> $input The rest of the input.
	 */
	private function body( string $view, array $input ): mixed {
		return $this->render( $view, 'body', $input );
	}

	/**
	 * Run render-preview.
	 *
	 * @param string               $view  The view.
	 * @param string               $part  The part.
	 * @param array<string, mixed> $input The rest of the input.
	 */
	private function render( string $view, string $part, array $input ): mixed {
		return wp_get_ability( 'publishio/render-preview' )->execute( // @phpstan-ignore method.nonObject
			[
				'view' => $view,
				'part' => $part,
			] + $input
		);
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
