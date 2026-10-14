<?php
/**
 * Tests for the Get_Yoast_Meta ability, with Yoast SEO active.
 *
 * @package rtCamp\Publishio\Tests\Yoast\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Yoast\Modules\MCP\Abilities\Posts;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Get_Yoast_Meta;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Yoast_Fields;
use rtCamp\Publishio\Tests\Abstracts\Yoast_Ability_TestCase;

/**
 * Class - Get_Yoast_MetaTest
 */
#[CoversClass( Get_Yoast_Meta::class )]
#[CoversClass( Yoast_Fields::class )]
class Get_Yoast_MetaTest extends Yoast_Ability_TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function ability_name(): string {
		return 'publishio/get-yoast-meta';
	}

	/**
	 * An editor gets every field with Yoast's default settings.
	 */
	public function test_returns_every_field_to_an_editor(): void {
		$post_id = $this->own_post_as( 'editor' );

		$result = $this->execute( [ 'post_id' => $post_id ] );

		$this->assertIsArray( $result );
		$this->assertSame( array_merge( [ 'post_id' ], array_keys( Yoast_Fields::FIELDS ) ), array_keys( $result ) );
	}

	/**
	 * With Yoast's default settings, authors don't get the advanced or schema fields, and nobody gets the focus keyphrase while SEO analysis is off.
	 */
	public function test_leaves_out_fields_yoast_hides(): void {
		$post_id = $this->own_post_as( 'author' );
		$this->set_yoast_option( 'keyword_analysis_active', false );

		$result = $this->execute( [ 'post_id' => $post_id ] );

		$this->assertIsArray( $result );
		$this->assertSame( [ 'post_id', 'seo_title', 'meta_description', 'og_title', 'og_description', 'twitter_title', 'twitter_description' ], array_keys( $result ) );
	}

	/**
	 * Authors get the advanced and schema fields when Yoast's advanced settings are open to all users.
	 */
	public function test_returns_advanced_fields_to_authors_when_yoast_allows(): void {
		$post_id = $this->own_post_as( 'author' );
		$this->set_yoast_option( 'disableadvanced_meta', false );

		$result = $this->execute( [ 'post_id' => $post_id ] );

		$this->assertIsArray( $result );
		$this->assertSame( array_merge( [ 'post_id' ], array_keys( Yoast_Fields::FIELDS ) ), array_keys( $result ) );
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
		$post_id = $this->own_post_as( 'editor' );
		$this->set_yoast_option( 'display-metabox-pt-post', false );

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
