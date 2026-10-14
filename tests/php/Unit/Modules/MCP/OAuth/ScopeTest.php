<?php
/**
 * Tests for OAuth scope handling.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use rtCamp\Publishio\Modules\MCP\OAuth\Scope;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - ScopeTest
 */
#[CoversClass( Scope::class )]
class ScopeTest extends TestCase {
	/**
	 * Requested scopes and the scope granted for them.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function normalize_cases(): array {
		return [
			'empty gets read only'           => [ '', 'mcp:read' ],
			'whitespace gets read only'      => [ " \t ", 'mcp:read' ],
			'read only'                      => [ 'mcp:read', 'mcp:read' ],
			'write includes read'            => [ 'mcp:write', 'mcp:read mcp:write' ],
			'write among unsupported scopes' => [ 'bogus mcp:write', 'mcp:read mcp:write' ],
			'canonical order'                => [ 'mcp:write mcp:read', 'mcp:read mcp:write' ],
			'duplicates collapse'            => [ 'mcp:read mcp:read', 'mcp:read' ],
			'unsupported scopes are dropped' => [ 'openid mcp:read offline_access', 'mcp:read' ],
			'extra whitespace'               => [ "  mcp:read\tmcp:write  ", 'mcp:read mcp:write' ],
			'only unsupported scopes'        => [ 'bogus:nonsense', '' ],
			'scope names are case sensitive' => [ 'MCP:READ', '' ],
			'no partial matches'             => [ 'mcp:read:all', '' ],
		];
	}

	/**
	 * A requested scope is narrowed to supported scopes, an empty request gets read-only access, and write access includes read access.
	 *
	 * @param string $requested Requested scope.
	 * @param string $expected  Granted scope.
	 */
	#[DataProvider( 'normalize_cases' )]
	public function test_normalize( string $requested, string $expected ): void {
		$this->assertSame( $expected, Scope::normalize( $requested ) );
	}

	/**
	 * Only a scope containing mcp:write allows write abilities.
	 */
	public function test_allows_write(): void {
		$this->assertTrue( Scope::allows_write( 'mcp:read mcp:write' ) );
		$this->assertTrue( Scope::allows_write( 'mcp:write' ) );
		$this->assertTrue( Scope::allows_write( "mcp:read\tmcp:write" ) );
		$this->assertFalse( Scope::allows_write( 'mcp:read' ) );
		$this->assertFalse( Scope::allows_write( '' ) );
		$this->assertFalse( Scope::allows_write( 'mcp:writer' ) );
	}

	/**
	 * The consent screen gets the label of each granted scope.
	 */
	public function test_describe(): void {
		$read  = 'Read posts, pages, media, blocks and patterns';
		$write = 'Create and edit posts and pages, and remove content from them';

		$this->assertSame( [ $read, $write ], Scope::describe( 'mcp:read mcp:write' ) );
		$this->assertSame( [ $read ], Scope::describe( 'mcp:read' ) );
		$this->assertSame( [ $write ], Scope::describe( 'mcp:write' ) );
		$this->assertSame( [], Scope::describe( '' ) );
	}

	/**
	 * Every supported scope has a label, so the consent screen never hides a granted scope.
	 */
	public function test_every_supported_scope_has_a_label(): void {
		$this->assertCount( count( Scope::SUPPORTED ), Scope::describe( implode( ' ', Scope::SUPPORTED ) ) );
	}
}
