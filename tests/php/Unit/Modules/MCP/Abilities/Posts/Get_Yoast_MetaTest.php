<?php
/**
 * Tests for the Get_Yoast_Meta ability without Yoast SEO. The tests with Yoast active are in tests/php/Yoast.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Get_Yoast_Meta;
use rtCamp\Publishio\Tests\Abstracts\Ability_TestCase;

/**
 * Class - Get_Yoast_MetaTest
 */
#[CoversClass( Get_Yoast_Meta::class )]
class Get_Yoast_MetaTest extends Ability_TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function ability_name(): string {
		return 'publishio/get-yoast-meta';
	}

	/**
	 * Without Yoast SEO the ability fails, which tells the AI to skip Yoast.
	 */
	public function test_errors_when_yoast_is_not_active(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$this->assertAbilityError( $this->execute( [ 'post_id' => self::factory()->post->create() ] ), 'yoast_not_active' );
	}
}
