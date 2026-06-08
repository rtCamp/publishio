<?php
/**
 * Permission-check tests for the Set_Featured_Image ability.
 *
 * @package rtCamp\Publish_With_AI\Tests\Unit\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Tests\Unit\Modules\MCP\Abilities\Posts;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts\Set_Featured_Image;
use rtCamp\Publish_With_AI\Tests\Abstracts\Ability_TestCase;

/**
 * Class - Set_Featured_ImageTest
 *
 * Covers the removal short-circuit, the `read_post` capability guard added for
 * the attachment, and the success path.
 */
#[CoversClass( Set_Featured_Image::class )]
class Set_Featured_ImageTest extends Ability_TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function ability_name(): string {
		return 'pwai/set-featured-image';
	}

	/**
	 * Passing attachment_id 0 removes the thumbnail before any attachment check.
	 */
	public function test_removes_featured_image_when_attachment_id_is_zero(): void {
		$editor        = self::factory()->user->create( [ 'role' => 'editor' ] );
		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->post->create(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => $post_id,
			]
		);
		set_post_thumbnail( $post_id, $attachment_id );
		wp_set_current_user( $editor );

		$result = $this->execute(
			[
				'post_id'       => $post_id,
				'attachment_id' => 0,
			]
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertSame( '', $result['url'] );
		$this->assertFalse( has_post_thumbnail( $post_id ) );
	}

	/**
	 * An attachment the user cannot read is rejected.
	 */
	public function test_denies_when_user_cannot_read_attachment(): void {
		$editor        = self::factory()->user->create( [ 'role' => 'editor' ] );
		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->post->create(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/jpeg',
			]
		);
		wp_set_current_user( $editor );

		$this->deny_meta_cap( 'read_post', $attachment_id );

		$result = $this->execute(
			[
				'post_id'       => $post_id,
				'attachment_id' => $attachment_id,
			]
		);

		$this->assertAbilityError( $result, 'forbidden_attachment' );
		$this->assertFalse( has_post_thumbnail( $post_id ) );
	}

	/**
	 * The thumbnail is set when the user can read the attachment.
	 */
	public function test_sets_featured_image_when_user_can_read_attachment(): void {
		$editor        = self::factory()->user->create( [ 'role' => 'editor' ] );
		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->post->create(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/jpeg',
			]
		);
		// set_post_thumbnail() needs a resolvable image; fake the metadata.
		update_post_meta( $attachment_id, '_wp_attached_file', '2020/01/test-image.jpg' );
		wp_update_attachment_metadata(
			$attachment_id,
			[
				'file'   => '2020/01/test-image.jpg',
				'width'  => 100,
				'height' => 100,
				'sizes'  => [],
			]
		);
		wp_set_current_user( $editor );

		$result = $this->execute(
			[
				'post_id'       => $post_id,
				'attachment_id' => $attachment_id,
			]
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertSame( $attachment_id, (int) get_post_thumbnail_id( $post_id ) );
	}
}
