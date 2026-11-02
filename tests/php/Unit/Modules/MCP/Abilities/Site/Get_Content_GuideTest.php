<?php
/**
 * Tests for the get-content-guide ability and the content guide file.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Site
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Site;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Abilities\Site\Get_Content_Guide;
use rtCamp\Publishio\Modules\MCP\Resources\Content_Guide\Content_Guide;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Get_Content_GuideTest
 */
#[CoversClass( Get_Content_Guide::class )]
#[CoversClass( Content_Guide::class )]
class Get_Content_GuideTest extends TestCase {
	/**
	 * Accounts that can't edit posts can't read the guide.
	 */
	public function test_needs_an_editing_account(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$this->assertWPError( wp_get_ability( 'publishio/get-content-guide' )->execute( [] ) ); // @phpstan-ignore method.nonObject
	}

	/**
	 * The tool returns the skill file without its frontmatter, and the guide page where the user downloads it.
	 */
	public function test_returns_the_guide_and_its_page(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'author' ] ) );

		$result = wp_get_ability( 'publishio/get-content-guide' )->execute( [] ); // @phpstan-ignore method.nonObject

		$this->assertIsArray( $result );
		$this->assertStringStartsWith( '# Publishio content guide', $result['guide'] );
		$this->assertStringNotContainsString( "name: publishio\n", $result['guide'] );
		$this->assertStringEndsWith( $result['guide'], Content_Guide::file() );
		$this->assertStringStartsWith( "---\nname: publishio\n", Content_Guide::file() );
		$this->assertStringEndsWith( 'admin.php?page=publishio', $result['guide_page_url'] );

		wp_set_current_user( 0 );
	}
}
