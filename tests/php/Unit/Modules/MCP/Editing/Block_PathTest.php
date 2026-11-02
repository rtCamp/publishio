<?php
/**
 * Tests for Block_Path.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Editing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Path;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Block_PathTest
 */
#[CoversClass( Block_Path::class )]
class Block_PathTest extends TestCase {
	/**
	 * Paths that can't be parsed.
	 *
	 * @return array<string, array{string}>
	 */
	public static function invalid_paths(): array {
		return [
			'empty'               => [ '' ],
			'no index'            => [ 'heading' ],
			'no name'             => [ '0:' ],
			'negative index'      => [ '-1:heading' ],
			'uppercase name'      => [ '0:Heading' ],
			'empty step'          => [ '0:group > > 1:paragraph' ],
			'two namespaces'      => [ '0:a/b/c' ],
			'five digit position' => [ '10000:paragraph' ],
		];
	}

	/**
	 * Malformed paths are refused with invalid_path.
	 *
	 * @param string $path The path.
	 */
	#[DataProvider( 'invalid_paths' )]
	public function test_refuses_malformed_paths( string $path ): void {
		$result = Block_Path::parse( $path );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_path', $result->get_error_code() );
		$this->assertStringStartsWith( 'invalid_path: ', $result->get_error_message() );
	}

	/**
	 * A path deeper than MAX_DEPTH is refused.
	 */
	public function test_refuses_paths_deeper_than_the_limit(): void {
		$path = implode( ' > ', array_fill( 0, Block_Path::MAX_DEPTH + 1, '0:group' ) );

		$this->assertWPError( Block_Path::parse( $path ) );
		$this->assertInstanceOf( Block_Path::class, Block_Path::parse( implode( ' > ', array_fill( 0, Block_Path::MAX_DEPTH, '0:group' ) ) ) );
	}

	/**
	 * A nested path parses into steps with full names, and prints with short names.
	 */
	public function test_parses_nested_paths(): void {
		$path = Block_Path::parse( '1:columns > 0:column >2:acme/pricing-table' );

		$this->assertInstanceOf( Block_Path::class, $path );
		$this->assertSame(
			[
				[
					'index' => 1,
					'name'  => 'core/columns',
				],
				[
					'index' => 0,
					'name'  => 'core/column',
				],
				[
					'index' => 2,
					'name'  => 'acme/pricing-table',
				],
			],
			$path->steps()
		);
		$this->assertSame( '1:columns > 0:column > 2:acme/pricing-table', $path->to_string() );
		$this->assertSame( 2, $path->index() );
		$this->assertSame( '1:columns > 0:column', $path->parent()->to_string() );
	}

	/**
	 * A bare name and its core/ name are the same block.
	 */
	public function test_bare_and_core_names_resolve_the_same(): void {
		$short = Block_Path::parse( '0:heading' );
		$full  = Block_Path::parse( '0:core/heading' );

		$this->assertInstanceOf( Block_Path::class, $short );
		$this->assertInstanceOf( Block_Path::class, $full );
		$this->assertSame( $short->steps(), $full->steps() );
	}

	/**
	 * The root path has no steps and no index.
	 */
	public function test_root(): void {
		$root = Block_Path::root();

		$this->assertTrue( $root->is_root() );
		$this->assertSame( -1, $root->index() );
		$this->assertNull( $root->last() );
		$this->assertSame( '0:group', $root->child( 0, 'core/group' )->to_string() );
	}

	/**
	 * Containment compares positions, and with_index_at() changes one step.
	 */
	public function test_is_within_and_with_index_at(): void {
		$outer = Block_Path::parse( '1:group' );
		$inner = Block_Path::parse( '1:group > 0:paragraph' );
		$other = Block_Path::parse( '2:group > 0:paragraph' );

		$this->assertInstanceOf( Block_Path::class, $outer );
		$this->assertInstanceOf( Block_Path::class, $inner );
		$this->assertInstanceOf( Block_Path::class, $other );
		$this->assertTrue( $inner->is_within( $outer ) );
		$this->assertTrue( $outer->is_within( $outer ) );
		$this->assertFalse( $outer->is_within( $inner ) );
		$this->assertFalse( $other->is_within( $outer ) );
		$this->assertSame( '0:group > 0:paragraph', $inner->with_index_at( 0, 0 )->to_string() );
	}
}
