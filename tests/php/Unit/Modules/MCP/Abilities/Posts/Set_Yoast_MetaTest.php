<?php
/**
 * Tests for the Set_Yoast_Meta ability without Yoast SEO. The tests with Yoast active are in tests/php/Yoast.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Set_Yoast_Meta;
use rtCamp\Publishio\Tests\Abstracts\Ability_TestCase;

/**
 * Class - Set_Yoast_MetaTest
 */
#[CoversClass( Set_Yoast_Meta::class )]
class Set_Yoast_MetaTest extends Ability_TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function ability_name(): string {
		return 'publishio/set-yoast-meta';
	}

	/**
	 * Without Yoast SEO the ability fails and writes nothing.
	 */
	public function test_errors_when_yoast_is_not_active(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$post_id = self::factory()->post->create();

		$result = $this->execute(
			[
				'post_id'   => $post_id,
				'seo_title' => 'Title',
			]
		);

		$this->assertAbilityError( $result, 'yoast_not_active' );
		$this->assertSame( '', get_post_meta( $post_id, '_yoast_wpseo_title', true ) );
	}
}
