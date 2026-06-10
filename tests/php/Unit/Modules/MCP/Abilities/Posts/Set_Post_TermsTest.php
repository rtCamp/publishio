<?php
/**
 * Permission-check tests for the Set_Post_Terms ability.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Set_Post_Terms;
use rtCamp\Publishio\Tests\Abstracts\Ability_TestCase;

/**
 * Class - Set_Post_TermsTest
 *
 * Exercises the capability guards added inside the ability's execute callback:
 * the taxonomy-level `assign_terms` check and the per-term `assign_term` check
 * that runs against every term being added or removed.
 */
#[CoversClass( Set_Post_Terms::class )]
class Set_Post_TermsTest extends Ability_TestCase {
	/**
	 * Slug of the custom taxonomy registered for the taxonomy-level denial test.
	 */
	private const CUSTOM_TAXONOMY = 'publishio_test_tax';

	/**
	 * {@inheritDoc}
	 */
	protected function ability_name(): string {
		return 'publishio/set-post-terms';
	}

	/**
	 * Unregister the custom taxonomy created by individual tests.
	 */
	protected function tearDown(): void {
		if ( taxonomy_exists( self::CUSTOM_TAXONOMY ) ) {
			unregister_taxonomy( self::CUSTOM_TAXONOMY );
		}

		parent::tearDown();
	}

	/**
	 * A user without the taxonomy's `assign_terms` capability is rejected.
	 */
	public function test_denies_when_user_cannot_assign_terms_in_taxonomy(): void {
		register_taxonomy(
			self::CUSTOM_TAXONOMY,
			'post',
			[
				'capabilities' => [
					'manage_terms' => 'manage_publishio_terms',
					'edit_terms'   => 'manage_publishio_terms',
					'delete_terms' => 'manage_publishio_terms',
					'assign_terms' => 'manage_publishio_terms',
				],
			]
		);

		$editor  = self::factory()->user->create( [ 'role' => 'editor' ] );
		$post_id = self::factory()->post->create();
		wp_set_current_user( $editor );

		$result = $this->execute(
			[
				'post_id'  => $post_id,
				'taxonomy' => self::CUSTOM_TAXONOMY,
				'terms'    => [],
			]
		);

		$this->assertAbilityError( $result, 'forbidden' );
		$this->assertStringContainsString( 'assign terms in this taxonomy', $result->get_error_message() );
	}

	/**
	 * A term the user may not assign is reported when added.
	 */
	public function test_denies_assigning_a_term_the_user_cannot_assign(): void {
		$editor  = self::factory()->user->create( [ 'role' => 'editor' ] );
		$post_id = self::factory()->post->create();
		$term_id = self::factory()->category->create( [ 'name' => 'Restricted' ] );
		wp_set_current_user( $editor );

		$this->deny_meta_cap( 'assign_term', $term_id );

		$result = $this->execute(
			[
				'post_id'  => $post_id,
				'taxonomy' => 'category',
				'terms'    => [ $term_id ],
			]
		);

		$this->assertAbilityError( $result, 'forbidden' );
		$this->assertStringContainsString( (string) $term_id, $result->get_error_message() );
		// Not assertSame( [] ): the post retains its default category.
		$this->assertNotContains( $term_id, wp_get_object_terms( $post_id, 'category', [ 'fields' => 'ids' ] ) );
	}

	/**
	 * A term the user may not assign is reported when removed.
	 */
	public function test_denies_removing_a_term_the_user_cannot_assign(): void {
		$editor  = self::factory()->user->create( [ 'role' => 'editor' ] );
		$post_id = self::factory()->post->create();
		$term_id = self::factory()->category->create( [ 'name' => 'Locked' ] );
		wp_set_object_terms( $post_id, [ $term_id ], 'category' );

		wp_set_current_user( $editor );
		$this->deny_meta_cap( 'assign_term', $term_id );

		$result = $this->execute(
			[
				'post_id'  => $post_id,
				'taxonomy' => 'category',
				'terms'    => [],
			]
		);

		$this->assertAbilityError( $result, 'forbidden' );
		$this->assertStringContainsString( (string) $term_id, $result->get_error_message() );
		$this->assertContains( $term_id, wp_get_object_terms( $post_id, 'category', [ 'fields' => 'ids' ] ) );
	}

	/**
	 * Terms are assigned when the user holds every required capability.
	 */
	public function test_assigns_terms_when_user_has_permission(): void {
		$editor  = self::factory()->user->create( [ 'role' => 'editor' ] );
		$post_id = self::factory()->post->create();
		$term_id = self::factory()->category->create( [ 'name' => 'Allowed' ] );
		wp_set_current_user( $editor );

		$result = $this->execute(
			[
				'post_id'  => $post_id,
				'taxonomy' => 'category',
				'terms'    => [ $term_id ],
			]
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertSame( [ $term_id ], wp_get_object_terms( $post_id, 'category', [ 'fields' => 'ids' ] ) );
		$this->assertSame( $term_id, $result['assigned_terms'][0]['term_id'] );
	}

	/**
	 * An empty term list clears existing terms when the user may assign them.
	 */
	public function test_removes_all_terms_with_empty_array(): void {
		$editor  = self::factory()->user->create( [ 'role' => 'editor' ] );
		$post_id = self::factory()->post->create();
		$term_id = self::factory()->category->create( [ 'name' => 'Temporary' ] );
		wp_set_object_terms( $post_id, [ $term_id ], 'category' );
		wp_set_current_user( $editor );

		$result = $this->execute(
			[
				'post_id'  => $post_id,
				'taxonomy' => 'category',
				'terms'    => [],
			]
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertSame( [], $result['assigned_terms'] );
		$this->assertSame( [], wp_get_object_terms( $post_id, 'category', [ 'fields' => 'ids' ] ) );
	}
}
