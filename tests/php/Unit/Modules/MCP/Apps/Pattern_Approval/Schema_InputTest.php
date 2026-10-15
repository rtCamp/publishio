<?php
/**
 * Tests the schema entries the Pattern Approval abilities accept.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Apps\Pattern_Approval
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Apps\Pattern_Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use rtCamp\Publishio\Modules\MCP\Apps\Pattern_Approval\Insert_Pattern;
use rtCamp\Publishio\Modules\MCP\Apps\Pattern_Approval\Preview_Pattern;
use rtCamp\Publishio\Modules\MCP\Apps\Pattern_Approval\Render_Pattern;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Schema_InputTest
 *
 * Pattern_Schema::apply() drops an entry without a string block, so these abilities reject it instead of returning the pattern unfilled.
 */
#[CoversClass( Preview_Pattern::class )]
#[CoversClass( Render_Pattern::class )]
#[CoversClass( Insert_Pattern::class )]
class Schema_InputTest extends TestCase {
	/**
	 * Abilities and malformed entries.
	 *
	 * @return array<string, array{string, array<string, mixed>}>
	 */
	public static function malformed_entries(): array {
		$missing = [ 'fields' => [ 'alt' => 'Photo' ] ];
		$number  = [
			'block'  => 42,
			'fields' => [ 'alt' => 'Photo' ],
		];

		return [
			'preview, no block'     => [ 'publishio/preview-pattern', $missing ],
			'preview, number block' => [ 'publishio/preview-pattern', $number ],
			'render, no block'      => [ 'publishio/render-pattern', $missing ],
			'render, number block'  => [ 'publishio/render-pattern', $number ],
			'insert, no block'      => [ 'publishio/insert-pattern-confirmed', $missing ],
			'insert, number block'  => [ 'publishio/insert-pattern-confirmed', $number ],
		];
	}

	/**
	 * An entry whose block is missing or isn't a string is rejected as invalid input.
	 *
	 * @param string               $ability Ability name.
	 * @param array<string, mixed> $entry   Schema entry.
	 */
	#[DataProvider( 'malformed_entries' )]
	public function test_rejects_entry_without_string_block( string $ability, array $entry ): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$input = [
			'pattern_name' => 'publishio-test/hero',
			'schema'       => [ $entry ],
		];

		if ( 'publishio/render-pattern' !== $ability ) {
			$input += [
				'page_id'  => 1,
				'position' => -1,
			];
		}

		$result = wp_get_ability( $ability )->execute( $input );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
		$this->assertStringContainsString( 'block', $result->get_error_message() );
	}
}
