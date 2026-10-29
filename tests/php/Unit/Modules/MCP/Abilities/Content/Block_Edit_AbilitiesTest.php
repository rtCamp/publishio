<?php
/**
 * Tests for the block-edit abilities.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Content
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Block_Edit_Ability;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Delete_Blocks;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Duplicate_Block;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Insert_Blocks;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Move_Block;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Replace_Block;
use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Block_Edit_AbilitiesTest
 */
#[CoversClass( Ability::class )]
#[CoversClass( Schema::class )]
#[CoversClass( Block_Edit_Ability::class )]
#[CoversClass( Insert_Blocks::class )]
#[CoversClass( Replace_Block::class )]
#[CoversClass( Move_Block::class )]
#[CoversClass( Duplicate_Block::class )]
#[CoversClass( Delete_Blocks::class )]
class Block_Edit_AbilitiesTest extends TestCase {
	private const CONTENT = '<!-- wp:heading --><h2 class="wp-block-heading">Title</h2><!-- /wp:heading -->'
		. "\n\n" . '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>A</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

	/**
	 * The draft being edited.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Run as an editor with a draft to edit.
	 */
	protected function setUp(): void {
		parent::setUp();

		$editor = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor );

		$this->post_id = self::factory()->post->create(
			[
				'post_status'  => 'draft',
				'post_author'  => $editor,
				'post_content' => self::CONTENT,
			]
		);
	}

	/**
	 * Reset the current user.
	 */
	protected function tearDown(): void {
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * The insert-blocks tool refuses invalid markup with every problem and saves nothing.
	 */
	public function test_insert_refuses_invalid_markup(): void {
		$result = $this->run_ability( 'insert-blocks', [ 'markup' => '<p>Loose</p><!-- wp:acme/none /-->' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_markup', $result->get_error_code() );
		$this->assertStringContainsString( 'freeform_html', $result->get_error_message() );
		$this->assertStringContainsString( 'unknown_block', $result->get_error_message() );
		$this->assertSame( self::CONTENT, get_post_field( 'post_content', $this->post_id ) );
	}

	/**
	 * The insert-blocks tool adds blocks inside a container and returns the container's outline.
	 */
	public function test_insert_adds_blocks_and_returns_the_outline(): void {
		$result = $this->run_ability(
			'insert-blocks',
			[
				'markup'    => '<!-- wp:paragraph --><p>B</p><!-- /wp:paragraph -->',
				'target'    => '1:group',
				'placement' => 'end',
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( [ '1:group > 1:paragraph' ], $result['changed'] );
		$this->assertSame( [ '1:group', '1:group > 0:paragraph', '1:group > 1:paragraph' ], array_column( $result['outline']['rows'], 'path' ) );
		$this->assertStringContainsString( '<p>A</p><!-- /wp:paragraph -->' . "\n\n" . '<!-- wp:paragraph --><p>B</p>', get_post_field( 'post_content', $this->post_id ) );
	}

	/**
	 * A post the user can't edit is refused without changing it.
	 */
	public function test_refuses_a_post_the_user_cannot_edit(): void {
		wp_update_post(
			[
				'ID'          => $this->post_id,
				'post_status' => 'publish',
			]
		);
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'author' ] ) );

		$result = $this->run_ability( 'insert-blocks', [ 'markup' => '<!-- wp:paragraph --><p>B</p><!-- /wp:paragraph -->' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'forbidden', $result->get_error_code() );
		$this->assertSame( self::CONTENT, get_post_field( 'post_content', $this->post_id ) );
	}

	/**
	 * The replace-block tool refuses a stale path and keeps problems the block already had out of the refusal.
	 */
	public function test_replace_refuses_stale_paths_and_ignores_existing_problems(): void {
		$stale = $this->run_ability(
			'replace-block',
			[
				'path'   => '0:paragraph',
				'markup' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->',
			]
		);

		$this->assertWPError( $stale );
		$this->assertSame( 'path_mismatch', $stale->get_error_code() );

		wp_update_post(
			[
				'ID'           => $this->post_id,
				'post_content' => '<!-- wp:heading {"acmeOnly":true} --><h2 class="wp-block-heading">Old</h2><!-- /wp:heading -->',
			]
		);

		$result = $this->run_ability(
			'replace-block',
			[
				'path'   => '0:heading',
				'markup' => '<!-- wp:heading {"acmeOnly":true} --><h2 class="wp-block-heading">New</h2><!-- /wp:heading -->',
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( [ '0:heading' ], $result['changed'] );
		$this->assertStringContainsString( '>New</h2>', get_post_field( 'post_content', $this->post_id ) );
	}

	/**
	 * The move-block tool refuses a place the block can't go, then moves it where it can.
	 */
	public function test_move_checks_the_new_place(): void {
		wp_update_post(
			[
				'ID'           => $this->post_id,
				'post_content' => '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>A</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->',
			]
		);

		$refused = $this->run_ability(
			'move-block',
			[
				'path'      => '0:columns > 0:column',
				'placement' => 'end',
			]
		);

		$this->assertWPError( $refused );
		$this->assertSame( 'invalid_parent', $refused->get_error_code() );

		$moved = $this->run_ability(
			'move-block',
			[
				'path'      => '0:columns > 0:column > 0:paragraph',
				'placement' => 'end',
			]
		);

		$this->assertIsArray( $moved );
		$this->assertSame( [ '1:paragraph' ], $moved['changed'] );
	}

	/**
	 * The duplicate-block tool refuses a second single-use block, and copies others.
	 */
	public function test_duplicate_copies_blocks(): void {
		wp_update_post(
			[
				'ID'           => $this->post_id,
				'post_content' => '<!-- wp:more --><!--more--><!-- /wp:more --><!-- wp:paragraph --><p>A</p><!-- /wp:paragraph -->',
			]
		);

		$refused = $this->run_ability( 'duplicate-block', [ 'path' => '0:more' ] );

		$this->assertWPError( $refused );
		$this->assertSame( 'duplicate_not_allowed', $refused->get_error_code() );

		$result = $this->run_ability(
			'duplicate-block',
			[
				'path'  => '1:paragraph',
				'count' => 2,
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( [ '2:paragraph', '3:paragraph' ], $result['changed'] );
	}

	/**
	 * The delete-blocks tool refuses when any path is wrong, then removes every listed block.
	 */
	public function test_delete_checks_every_path_first(): void {
		$refused = $this->run_ability( 'delete-blocks', [ 'paths' => [ '0:heading', '5:group' ] ] );

		$this->assertWPError( $refused );
		$this->assertSame( 'path_not_found', $refused->get_error_code() );
		$this->assertSame( self::CONTENT, get_post_field( 'post_content', $this->post_id ) );

		$result = $this->run_ability( 'delete-blocks', [ 'paths' => [ '0:heading', '1:group > 0:paragraph' ] ] );

		$this->assertIsArray( $result );
		$this->assertSame( [ '0:group' ], array_column( $result['outline']['rows'], 'path' ) );
	}

	/**
	 * The delete-blocks tool is marked destructive, so read-only connections can't call it.
	 */
	public function test_annotations(): void {
		$this->assertTrue( wp_get_ability( 'publishio/delete-blocks' )->get_meta()['annotations']['destructive'] ); // @phpstan-ignore method.nonObject
		$this->assertFalse( wp_get_ability( 'publishio/insert-blocks' )->get_meta()['annotations']['readonly'] ); // @phpstan-ignore method.nonObject
	}

	/**
	 * Run an ability on the test draft.
	 *
	 * @param string               $name  The ability name without the prefix.
	 * @param array<string, mixed> $input The input, without post_id.
	 */
	private function run_ability( string $name, array $input ): mixed {
		return wp_get_ability( 'publishio/' . $name )->execute( [ 'post_id' => $this->post_id ] + $input ); // @phpstan-ignore method.nonObject
	}
}
