<?php
/**
 * Tests that the Apply_Pattern_Schema ability only reads.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Patterns
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Patterns;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Apply_Pattern_Schema;
use rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Pattern_Schema;
use rtCamp\Publishio\Tests\Abstracts\Ability_TestCase;

/**
 * Class - Apply_Pattern_SchemaTest
 */
#[CoversClass( Apply_Pattern_Schema::class )]
class Apply_Pattern_SchemaTest extends Ability_TestCase {
	private const PATTERN = 'publishio-test/hero';
	private const CONTENT = '<!-- wp:heading --><h2 class="wp-block-heading">Old title</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Old text</p><!-- /wp:paragraph -->';

	/**
	 * {@inheritDoc}
	 */
	protected function ability_name(): string {
		return 'publishio/apply-pattern-schema';
	}

	/**
	 * Register a pattern to apply.
	 */
	protected function setUp(): void {
		parent::setUp();

		register_block_pattern(
			self::PATTERN,
			[
				'title'   => 'Hero',
				'content' => self::CONTENT,
			]
		);
	}

	/**
	 * Remove the test pattern.
	 */
	protected function tearDown(): void {
		unregister_block_pattern( self::PATTERN );

		parent::tearDown();
	}

	/**
	 * The ability is annotated read-only, so read-only connections may call it.
	 */
	public function test_is_annotated_readonly(): void {
		$this->assertTrue( wp_get_ability( $this->ability_name() )->get_meta()['annotations']['readonly'] );
	}

	/**
	 * Applying a schema and rendering returns new markup and HTML, without writing to the database or changing the pattern.
	 */
	public function test_applies_and_renders_without_writing(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$schema = Pattern_Schema::extract( self::CONTENT );
		$this->assertNotEmpty( $schema );
		foreach ( $schema as $i => $entry ) {
			$schema[ $i ]['fields'] = array_map( static fn () => 'New text', $entry['fields'] );
		}

		$writes = [];
		add_filter(
			'query',
			static function ( string $query ) use ( &$writes ): string {
				if ( preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', $query ) ) {
					$writes[] = $query;
				}
				return $query;
			}
		);

		$result = $this->execute(
			[
				'pattern_name' => self::PATTERN,
				'schema'       => $schema,
				'render'       => true,
			]
		);

		$this->assertIsArray( $result );
		$this->assertStringNotContainsString( 'Old', $result['markup'] );
		$this->assertStringContainsString( 'New text', $result['html'] );
		$this->assertSame( [], $writes );
		$this->assertStringContainsString( 'Old title', \WP_Block_Patterns_Registry::get_instance()->get_registered( self::PATTERN )['content'] );
	}
}
