<?php
/**
 * Author-scoping and status tests for the Search_Posts ability.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Search_Posts;
use rtCamp\Publishio\Tests\Abstracts\Ability_TestCase;

/**
 * Class - Search_PostsTest
 */
#[CoversClass( Search_Posts::class )]
class Search_PostsTest extends Ability_TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function ability_name(): string {
		return 'publishio/search-posts';
	}

	/**
	 * Extract the `post_id` values from a result set, preserving order.
	 *
	 * @param mixed $result The ability result.
	 *
	 * @return list<int>
	 */
	private function result_ids( $result ): array {
		$this->assertIsArray( $result );

		return array_map( 'intval', array_column( $result, 'post_id' ) );
	}

	/**
	 * With no status given, only `publish` and `draft` posts are returned.
	 */
	public function test_defaults_to_publish_and_draft_statuses(): void {
		$editor = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor );

		$published = self::factory()->post->create(
			[
				'post_author' => $editor,
				'post_status' => 'publish',
			]
		);

		$draft = self::factory()->post->create(
			[
				'post_author' => $editor,
				'post_status' => 'draft',
			]
		);

		$pending = self::factory()->post->create(
			[
				'post_author' => $editor,
				'post_status' => 'pending',
			]
		);

		$ids = $this->result_ids( $this->execute( [] ) );

		$this->assertContains( $published, $ids );
		$this->assertContains( $draft, $ids );
		$this->assertNotContains( $pending, $ids );
	}

	/**
	 * A user without `edit_others_posts` sees only their own posts, including
	 * other authors' published posts being filtered out.
	 */
	public function test_scopes_results_to_own_posts_for_user_without_edit_others(): void {
		$author = self::factory()->user->create( [ 'role' => 'author' ] );
		$other  = self::factory()->user->create( [ 'role' => 'author' ] );

		$own_draft = self::factory()->post->create(
			[
				'post_author' => $author,
				'post_status' => 'draft',
			]
		);

		$others_draft = self::factory()->post->create(
			[
				'post_author' => $other,
				'post_status' => 'draft',
			]
		);

		$others_publish = self::factory()->post->create(
			[
				'post_author' => $other,
				'post_status' => 'publish',
			]
		);

		wp_set_current_user( $author );
		$ids = $this->result_ids( $this->execute( [] ) );

		$this->assertContains( $own_draft, $ids );
		$this->assertNotContains( $others_draft, $ids );
		$this->assertNotContains( $others_publish, $ids );
	}

	/**
	 * A user with `edit_others_posts` (Editor) sees other authors' posts.
	 */
	public function test_includes_other_authors_posts_for_editor(): void {
		$editor = self::factory()->user->create( [ 'role' => 'editor' ] );
		$other  = self::factory()->user->create( [ 'role' => 'author' ] );

		$others_draft = self::factory()->post->create(
			[
				'post_author' => $other,
				'post_status' => 'draft',
			]
		);

		wp_set_current_user( $editor );
		$ids = $this->result_ids( $this->execute( [] ) );

		$this->assertContains( $others_draft, $ids );
	}

	/**
	 * The `per_page` budget is spent only on editable posts: newer, non-editable
	 * posts must not crowd out the user's own results.
	 */
	public function test_per_page_budget_is_spent_only_on_editable_posts(): void {
		$author = self::factory()->user->create( [ 'role' => 'author' ] );
		$other  = self::factory()->user->create( [ 'role' => 'author' ] );

		// Other author's posts are newer, so without scoping they would fill the
		// first page and the author would see none of their own.
		foreach ( range( 1, 5 ) as $i ) {
			self::factory()->post->create(
				[
					'post_author' => $other,
					'post_status' => 'publish',
					'post_date'   => sprintf( '2026-02-%02d 00:00:00', $i ),
				]
			);
		}

		$own = [];
		foreach ( range( 1, 2 ) as $i ) {
			$own[] = self::factory()->post->create(
				[
					'post_author' => $author,
					'post_status' => 'publish',
					'post_date'   => sprintf( '2026-01-%02d 00:00:00', $i ),
				]
			);
		}

		wp_set_current_user( $author );
		$ids = $this->result_ids( $this->execute( [ 'per_page' => 3 ] ) );

		sort( $ids );
		sort( $own );
		$this->assertSame( $own, $ids );
	}
}
