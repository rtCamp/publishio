<?php
/**
 * Tests for the Get_Yoast_Meta ability.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Get_Yoast_Meta;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Yoast_Fields;
use rtCamp\Publishio\Tests\Abstracts\Yoast_Ability_TestCase;

/**
 * Class - Get_Yoast_MetaTest
 */
#[CoversClass( Get_Yoast_Meta::class )]
#[CoversClass( Yoast_Fields::class )]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
class Get_Yoast_MetaTest extends Yoast_Ability_TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function ability_name(): string {
		return 'publishio/get-yoast-meta';
	}

	/**
	 * Every field is returned when Yoast shows them all.
	 */
	public function test_returns_every_field_yoast_shows(): void {
		$post_id = $this->own_post_as( 'editor' );

		$result = $this->execute( [ 'post_id' => $post_id ] );

		$this->assertIsArray( $result );
		$this->assertSame( array_merge( [ 'post_id' ], array_keys( Yoast_Fields::FIELDS ) ), array_keys( $result ) );
	}

	/**
	 * Fields Yoast hides from the user are left out.
	 */
	public function test_leaves_out_fields_yoast_hides(): void {
		$post_id = $this->own_post_as( 'author' );
		$this->hide_tab( 'advanced' );
		$this->hide_tab( 'schema' );
		\WPSEO_Metabox_Analysis_SEO::$enabled = false;

		$result = $this->execute( [ 'post_id' => $post_id ] );

		$this->assertIsArray( $result );
		$this->assertSame( [ 'post_id', 'seo_title', 'meta_description', 'og_title', 'og_description', 'twitter_title', 'twitter_description' ], array_keys( $result ) );
	}

	/**
	 * Yoast's stored noindex value, and the choice in Yoast's editor it stands for.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function noindex_values(): array {
		return [
			'no value'          => [ '', 'default' ],
			'post type default' => [ '0', 'default' ],
			'noindex'           => [ '1', 'noindex' ],
			'index'             => [ '2', 'index' ],
		];
	}

	/**
	 * The noindex field reports the choice made in Yoast's editor.
	 *
	 * @param string $stored   Stored noindex value.
	 * @param string $expected Expected noindex.
	 */
	#[DataProvider( 'noindex_values' )]
	public function test_reads_noindex_as_yoast_editor_choice( string $stored, string $expected ): void {
		$post_id = $this->own_post_as( 'editor' );
		if ( '' !== $stored ) {
			update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', $stored );
		}
		update_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', '1' );

		$result = $this->execute( [ 'post_id' => $post_id ] );

		$this->assertIsArray( $result );
		$this->assertSame( $expected, $result['noindex'] );
		$this->assertTrue( $result['nofollow'] );
	}

	/**
	 * Nothing is returned when Yoast's SEO settings are off for the post type.
	 */
	public function test_errors_when_yoast_is_off_for_the_post_type(): void {
		$post_id                      = $this->own_post_as( 'editor' );
		\WPSEO_Utils::$metabox_active = false;

		$this->assertAbilityError( $this->execute( [ 'post_id' => $post_id ] ), 'yoast_disabled_for_post_type' );
	}

	/**
	 * The global post is set only while asking Yoast, then restored.
	 */
	public function test_restores_the_global_post(): void {
		$post_id         = $this->own_post_as( 'editor' );
		$other           = get_post( self::factory()->post->create() );
		$GLOBALS['post'] = $other; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->execute( [ 'post_id' => $post_id ] );
		$this->assertSame( $other, $GLOBALS['post'] );

		unset( $GLOBALS['post'] );
		$this->execute( [ 'post_id' => $post_id ] );
		$this->assertArrayNotHasKey( 'post', $GLOBALS );
	}
}
