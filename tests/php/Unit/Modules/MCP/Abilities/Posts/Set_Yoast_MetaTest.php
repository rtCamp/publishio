<?php
/**
 * Tests for the Set_Yoast_Meta ability.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Posts;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Set_Yoast_Meta;
use rtCamp\Publishio\Modules\MCP\Abilities\Posts\Yoast_Fields;
use rtCamp\Publishio\Tests\Abstracts\Yoast_Ability_TestCase;

/**
 * Class - Set_Yoast_MetaTest
 */
#[CoversClass( Set_Yoast_Meta::class )]
#[CoversClass( Yoast_Fields::class )]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
class Set_Yoast_MetaTest extends Yoast_Ability_TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function ability_name(): string {
		return 'publishio/set-yoast-meta';
	}

	/**
	 * A call with any field Yoast hides is refused, and none of its fields are written.
	 */
	public function test_refuses_hidden_fields_and_writes_nothing(): void {
		$post_id = $this->own_post_as( 'author' );
		$this->hide_tab( 'advanced' );

		$result = $this->execute(
			[
				'post_id'   => $post_id,
				'seo_title' => 'Title',
				'noindex'   => 'noindex',
				'canonical' => 'https://elsewhere.example/',
			]
		);

		$this->assertAbilityError( $result, 'yoast_fields_unavailable' );
		$this->assertStringContainsString( 'noindex, canonical', $result->get_error_message() );
		$this->assertSame( '', get_post_meta( $post_id, '_yoast_wpseo_title', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) );
	}

	/**
	 * The focus keyphrase is refused while SEO analysis is off.
	 */
	public function test_refuses_focus_keyphrase_when_seo_analysis_is_off(): void {
		$post_id                              = $this->own_post_as( 'editor' );
		\WPSEO_Metabox_Analysis_SEO::$enabled = false;

		$this->assertAbilityError(
			$this->execute(
				[
					'post_id'         => $post_id,
					'focus_keyphrase' => 'keyphrase',
				]
			),
			'yoast_fields_unavailable'
		);
	}

	/**
	 * Visible fields are written, with tags stripped, Yoast's replacement variables kept, and robots flags stored the way Yoast stores them.
	 */
	public function test_writes_visible_fields(): void {
		$post_id = $this->own_post_as( 'editor' );

		$result = $this->execute(
			[
				'post_id'          => $post_id,
				'seo_title'        => '<b>%%title%%</b> %%sep%% %%date%%',
				'meta_description' => '%%category%% and %%caption%%',
				'noindex'          => 'noindex',
				'nofollow'         => false,
				'schema_page_type' => 'FAQPage',
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( [ 'seo_title', 'meta_description', 'noindex', 'nofollow', 'schema_page_type' ], $result['updated_fields'] );
		$this->assertSame( '%%title%% %%sep%% %%date%%', get_post_meta( $post_id, '_yoast_wpseo_title', true ) );
		$this->assertSame( '%%category%% and %%caption%%', get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) );
		$this->assertSame( '1', get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) );
		$this->assertSame( '0', get_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', true ) );
		$this->assertSame( 'FAQPage', get_post_meta( $post_id, '_yoast_wpseo_schema_page_type', true ) );
	}

	/**
	 * A nofollow sent as a string, which schema validation accepts as a boolean, is read as a boolean.
	 */
	public function test_reads_nofollow_sent_as_a_string(): void {
		$post_id = $this->own_post_as( 'editor' );
		update_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', '1' );

		$this->execute(
			[
				'post_id'  => $post_id,
				'nofollow' => 'false',
			]
		);

		$this->assertSame( '0', get_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', true ) );
	}

	/**
	 * Each choice in Yoast's editor, and the value Yoast stores for it.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function noindex_choices(): array {
		return [
			'post type default' => [ 'default', '0' ],
			'noindex'           => [ 'noindex', '1' ],
			'index'             => [ 'index', '2' ],
		];
	}

	/**
	 * The noindex field takes the choices in Yoast's editor and stores them as Yoast does.
	 *
	 * @param string $noindex  Requested noindex.
	 * @param string $expected Expected stored value.
	 */
	#[DataProvider( 'noindex_choices' )]
	public function test_writes_noindex_as_yoast_editor_choice( string $noindex, string $expected ): void {
		$post_id = $this->own_post_as( 'editor' );
		update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', '1' === $expected ? '2' : '1' );

		$this->execute(
			[
				'post_id' => $post_id,
				'noindex' => $noindex,
			]
		);

		$this->assertSame( $expected, get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) );
	}

	/**
	 * A true or false noindex, the format before 1.2.0, is refused and nothing is written.
	 */
	public function test_refuses_a_boolean_noindex(): void {
		$post_id = $this->own_post_as( 'editor' );

		$result = $this->execute(
			[
				'post_id' => $post_id,
				'noindex' => true,
			]
		);

		$this->assertAbilityError( $result, 'ability_invalid_input' );
		$this->assertSame( '', get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) );
	}

	/**
	 * Writing back the noindex that get-yoast-meta returned leaves Yoast's value as it was.
	 */
	public function test_writing_back_noindex_changes_nothing(): void {
		foreach ( [ '0', '1', '2' ] as $stored ) {
			$post_id = $this->own_post_as( 'editor' );
			update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', $stored );

			$read = wp_get_ability( 'publishio/get-yoast-meta' )->execute( [ 'post_id' => $post_id ] );
			$this->assertIsArray( $read );
			$this->execute(
				[
					'post_id' => $post_id,
					'noindex' => $read['noindex'],
				]
			);

			$this->assertSame( $stored, get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ), "stored '$stored'" );
		}
	}

	/**
	 * An absolute http(s) canonical URL is stored as sent, including percent-encoding, and an empty one clears it.
	 */
	public function test_stores_and_clears_the_canonical_url(): void {
		$post_id = $this->own_post_as( 'editor' );

		$this->execute(
			[
				'post_id'   => $post_id,
				'canonical' => 'https://example.com/caf%C3%A9?a=1&b=2',
			]
		);
		$this->assertSame( 'https://example.com/caf%C3%A9?a=1&b=2', get_post_meta( $post_id, '_yoast_wpseo_canonical', true ) );

		$this->execute(
			[
				'post_id'   => $post_id,
				'canonical' => '',
			]
		);
		$this->assertSame( '', get_post_meta( $post_id, '_yoast_wpseo_canonical', true ) );
	}

	/**
	 * Canonical URLs that aren't absolute http(s) URLs.
	 *
	 * @return array<string, array{string}>
	 */
	public static function invalid_canonical_urls(): array {
		return [
			'javascript' => [ 'javascript:alert(1)' ],
			'relative'   => [ '/relative/path' ],
			'no scheme'  => [ 'example.com/page' ],
			'ftp'        => [ 'ftp://example.com/page' ],
		];
	}

	/**
	 * An invalid canonical URL is refused and nothing in the call is written.
	 *
	 * @param string $url Canonical URL.
	 */
	#[DataProvider( 'invalid_canonical_urls' )]
	public function test_refuses_an_invalid_canonical_url( string $url ): void {
		$post_id = $this->own_post_as( 'editor' );

		$result = $this->execute(
			[
				'post_id'   => $post_id,
				'seo_title' => 'Title',
				'canonical' => $url,
			]
		);

		$this->assertAbilityError( $result, 'invalid_canonical' );
		$this->assertSame( '', get_post_meta( $post_id, '_yoast_wpseo_title', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_yoast_wpseo_canonical', true ) );
	}

	/**
	 * Nothing is written when Yoast's SEO settings are off for the post type.
	 */
	public function test_errors_when_yoast_is_off_for_the_post_type(): void {
		$post_id                      = $this->own_post_as( 'editor' );
		\WPSEO_Utils::$metabox_active = false;

		$result = $this->execute(
			[
				'post_id'   => $post_id,
				'seo_title' => 'Title',
			]
		);

		$this->assertAbilityError( $result, 'yoast_disabled_for_post_type' );
		$this->assertSame( '', get_post_meta( $post_id, '_yoast_wpseo_title', true ) );
	}
}
