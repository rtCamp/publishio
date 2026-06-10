<?php
/**
 * Permission-check tests for the Upload_Media ability.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Upload_Media;
use rtCamp\Publishio\Tests\Abstracts\Ability_TestCase;

/**
 * Class - Upload_MediaTest
 *
 * Covers the parent-post capability guard. Only the denial branches are tested;
 * both return before the sideload, so the success path (a live fetch) is omitted.
 */
#[CoversClass( Upload_Media::class )]
class Upload_MediaTest extends Ability_TestCase {
	/**
	 * A schema-valid URL; never fetched, as both branches return before sideload.
	 */
	private const STUB_URL = 'https://example.com/image.png';

	/**
	 * {@inheritDoc}
	 */
	protected function ability_name(): string {
		return 'publishio/upload-media';
	}

	/**
	 * A parent post ID that does not resolve to a post is rejected.
	 */
	public function test_returns_error_when_parent_post_does_not_exist(): void {
		$author = self::factory()->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $author );

		$result = $this->execute(
			[
				'url'         => self::STUB_URL,
				'filename'    => 'image',
				'title'       => 'Image',
				'alt'         => '',
				'caption'     => '',
				'description' => '',
				'post_id'     => 999999,
			]
		);

		$this->assertAbilityError( $result, 'invalid_post' );
	}

	/**
	 * Attaching to a post the user cannot edit is rejected.
	 */
	public function test_denies_when_user_cannot_edit_parent_post(): void {
		$owner   = self::factory()->user->create( [ 'role' => 'editor' ] );
		$author  = self::factory()->user->create( [ 'role' => 'author' ] );
		$post_id = self::factory()->post->create(
			[
				'post_author' => $owner,
				'post_status' => 'publish',
			]
		);

		wp_set_current_user( $author );

		$result = $this->execute(
			[
				'url'         => self::STUB_URL,
				'filename'    => 'image',
				'title'       => 'Image',
				'alt'         => '',
				'caption'     => '',
				'description' => '',
				'post_id'     => $post_id,
			]
		);

		$this->assertAbilityError( $result, 'forbidden' );
	}
}
