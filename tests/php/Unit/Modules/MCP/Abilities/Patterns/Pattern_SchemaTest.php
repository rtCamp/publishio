<?php
/**
 * Tests for Pattern_Schema image and cover handling.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Patterns
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Abilities\Patterns;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use WP_HTML_Tag_Processor;
use rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Field_Writer\Attachment_Id;
use rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Field_Writer\Cover_Media_Writer;
use rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Field_Writer\Html_Attribute_Writer;
use rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Field_Writer\Image_Id_Writer;
use rtCamp\Publishio\Modules\MCP\Abilities\Patterns\Pattern_Schema;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Pattern_SchemaTest
 *
 * Written markup must match what core's save() would produce, or the block fails editor validation and shows "Attempt Block Recovery". The fixtures are core's own serialization, from wp.blocks.serialize() in WordPress 7.1.
 */
#[CoversClass( Pattern_Schema::class )]
#[CoversClass( Image_Id_Writer::class )]
#[CoversClass( Cover_Media_Writer::class )]
#[CoversClass( Html_Attribute_Writer::class )]
#[CoversClass( Attachment_Id::class )]
class Pattern_SchemaTest extends TestCase {
	private const IMAGE_WITHOUT_ID = '<!-- wp:image {"sizeSlug":"full"} -->' . "\n"
		. '<figure class="wp-block-image size-full"><img src="https://example.com/a.svg" alt=""/></figure>' . "\n"
		. '<!-- /wp:image -->';

	private const IMAGE_WITH_ID = '<!-- wp:image {"id":5,"sizeSlug":"full"} -->' . "\n"
		. '<figure class="wp-block-image size-full"><img src="https://example.com/photo.jpg" alt="" class="wp-image-5"/></figure>' . "\n"
		. '<!-- /wp:image -->';

	private const OVERLAY = '<span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span>';

	private const COVER_END = '<div class="wp-block-cover__inner-container"><!-- wp:paragraph -->' . "\n"
		. '<p>Hi</p>' . "\n"
		. '<!-- /wp:paragraph --></div></div>' . "\n"
		. '<!-- /wp:cover -->';

	private const COVER_WITHOUT_ID = '<!-- wp:cover {"url":"https://example.com/bg.jpg","dimRatio":50} -->' . "\n"
		. '<div class="wp-block-cover"><img class="wp-block-cover__image-background" alt="" src="https://example.com/bg.jpg" data-object-fit="cover"/>'
		. self::OVERLAY . self::COVER_END;

	private const COVER_WITH_ID = '<!-- wp:cover {"url":"https://example.com/bg.jpg","id":5,"dimRatio":50} -->' . "\n"
		. '<div class="wp-block-cover"><img class="wp-block-cover__image-background wp-image-5" alt="" src="https://example.com/bg.jpg" data-object-fit="cover"/>'
		. self::OVERLAY . self::COVER_END;

	private const PARALLAX_COVER = '<!-- wp:cover {"url":"https://example.com/bg.jpg","id":5,"hasParallax":true,"dimRatio":50} -->' . "\n"
		. '<div class="wp-block-cover has-parallax"><div class="wp-block-cover__image-background wp-image-5 has-parallax" style="background-position:50% 50%;background-image:url(https://example.com/bg.jpg)"></div>'
		. self::OVERLAY . self::COVER_END;

	private const PARALLAX_COVER_WITH_QUERY = '<!-- wp:cover {"url":"https://example.com/a.jpg?x=1&y=2","hasParallax":true,"dimRatio":50} -->' . "\n"
		. '<div class="wp-block-cover has-parallax"><div class="wp-block-cover__image-background has-parallax" style="background-position:50% 50%;background-image:url(https://example.com/a.jpg?x=1&amp;y=2)"></div>'
		. self::OVERLAY . self::COVER_END;

	private const VIDEO_COVER = '<!-- wp:cover {"url":"https://example.com/v.mp4","id":9,"dimRatio":50,"backgroundType":"video"} -->' . "\n"
		. '<div class="wp-block-cover"><video class="wp-block-cover__video-background intrinsic-ignore" autoplay muted loop playsinline src="https://example.com/v.mp4" data-object-fit="cover"></video>'
		. self::OVERLAY . self::COVER_END;

	private const EMBED_COVER = '<!-- wp:cover {"url":"https://www.youtube.com/watch?v=abc","dimRatio":50,"backgroundType":"embed-video"} -->' . "\n"
		. '<div class="wp-block-cover"><figure class="wp-block-cover__video-background wp-block-cover__embed-background wp-block-embed"><div class="wp-block-embed__wrapper">https://www.youtube.com/watch?v=abc</div></figure>'
		. self::OVERLAY . self::COVER_END;

	private const GRADIENT_COVER = '<!-- wp:cover {"url":"https://example.com/bg.jpg","dimRatio":50,"gradient":"vivid-cyan-blue-to-vivid-purple"} -->' . "\n"
		. '<div class="wp-block-cover"><img class="wp-block-cover__image-background" alt="" src="https://example.com/bg.jpg" data-object-fit="cover"/>'
		. '<span aria-hidden="true" class="wp-block-cover__background has-background-dim wp-block-cover__gradient-background has-background-gradient has-vivid-cyan-blue-to-vivid-purple-gradient-background"></span>'
		. self::COVER_END;

	private const GRADIENT_COVER_WITHOUT_MEDIA = '<!-- wp:cover {"dimRatio":50,"gradient":"vivid-cyan-blue-to-vivid-purple"} -->' . "\n"
		. '<div class="wp-block-cover"><span aria-hidden="true" class="wp-block-cover__background has-background-dim has-background-gradient has-vivid-cyan-blue-to-vivid-purple-gradient-background"></span>'
		. self::COVER_END;

	private const COVER_WITHOUT_MEDIA = '<!-- wp:cover {"dimRatio":50} -->' . "\n"
		. '<div class="wp-block-cover">' . self::OVERLAY . self::COVER_END;

	private const COVER_WITHOUT_MEDIA_FULL_OVERLAY = '<!-- wp:cover -->' . "\n"
		. '<div class="wp-block-cover"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim"></span>'
		. self::COVER_END;

	private const PARALLAX_COVER_WITHOUT_MEDIA = '<!-- wp:cover {"hasParallax":true,"dimRatio":50} -->' . "\n"
		. '<div class="wp-block-cover has-parallax">' . self::OVERLAY . self::COVER_END;

	private const VIDEO_COVER_WITHOUT_MEDIA = '<!-- wp:cover {"dimRatio":50,"backgroundType":"video"} -->' . "\n"
		. '<div class="wp-block-cover">' . self::OVERLAY . self::COVER_END;

	private const EMBED_COVER_WITHOUT_MEDIA = '<!-- wp:cover {"dimRatio":50,"backgroundType":"embed-video"} -->' . "\n"
		. '<div class="wp-block-cover">' . self::OVERLAY . self::COVER_END;

	private const SIZED_COVER = '<!-- wp:cover {"url":"https://example.com/bg.jpg","dimRatio":50,"sizeSlug":"large"} -->' . "\n"
		. '<div class="wp-block-cover"><img class="wp-block-cover__image-background size-large" alt="" src="https://example.com/bg.jpg" data-object-fit="cover"/>'
		. self::OVERLAY . self::COVER_END;

	private const VIDEO = '<!-- wp:video {"id":9} -->' . "\n"
		. '<figure class="wp-block-video"><video controls src="https://example.com/v.mp4"></video></figure>' . "\n"
		. '<!-- /wp:video -->';

	private const RICH_TEXT = '<!-- wp:paragraph -->' . "\n"
		. '<p><strong>48% increase</strong><br>Core Web Vitals <a href="#">Learn more</a></p>' . "\n"
		. '<!-- /wp:paragraph -->';

	private const BUTTON_WITHOUT_URL = '<!-- wp:buttons -->' . "\n"
		. '<div class="wp-block-buttons"><!-- wp:button -->' . "\n"
		. '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Go</a></div>' . "\n"
		. '<!-- /wp:button --></div>' . "\n"
		. '<!-- /wp:buttons -->';

	/**
	 * Patterns whose unchanged round trip must keep the markup exactly.
	 *
	 * @return array<string, array{string}>
	 */
	public static function unchanged_markup(): array {
		return [
			'image without id' => [ self::IMAGE_WITHOUT_ID ],
			'image with id'    => [ self::IMAGE_WITH_ID ],
			'cover without id' => [ self::COVER_WITHOUT_ID ],
			'parallax cover'   => [ self::PARALLAX_COVER ],
			'video cover'      => [ self::VIDEO_COVER ],
			'embed cover'      => [ self::EMBED_COVER ],
			'video'            => [ self::VIDEO ],
			'rich text'        => [ self::RICH_TEXT ],
			'button'           => [ self::BUTTON_WITHOUT_URL ],
		];
	}

	/**
	 * An unchanged extract/apply round trip writes nothing, so ids, inline markup and absent attributes survive.
	 *
	 * @param string $markup Block markup.
	 */
	#[DataProvider( 'unchanged_markup' )]
	public function test_unchanged_round_trip_keeps_markup( string $markup ): void {
		$this->assertSame( serialize_blocks( parse_blocks( $markup ) ), Pattern_Schema::apply( $markup, Pattern_Schema::extract( $markup ) ) );
	}

	/**
	 * Clearing the ID removes both the id attribute and the wp-image class.
	 */
	public function test_empty_image_id_removes_attribute_and_class(): void {
		$schema                    = Pattern_Schema::extract( self::IMAGE_WITH_ID );
		$schema[0]['fields']['id'] = '';
		[ $block ]                 = parse_blocks( Pattern_Schema::apply( self::IMAGE_WITH_ID, $schema ) );

		$this->assertArrayNotHasKey( 'id', $block['attrs'] );
		$this->assertStringNotContainsString( 'wp-image-', $block['innerHTML'] );
		$this->assertStringNotContainsString( 'class=""', $block['innerHTML'] );
	}

	/**
	 * A positive ID sets the id attribute and a matching wp-image class.
	 */
	public function test_positive_image_id_sets_attribute_and_class(): void {
		$schema                    = Pattern_Schema::extract( self::IMAGE_WITHOUT_ID );
		$schema[0]['fields']['id'] = '42';
		[ $block ]                 = parse_blocks( Pattern_Schema::apply( self::IMAGE_WITHOUT_ID, $schema ) );

		$this->assertSame( 42, $block['attrs']['id'] );
		$this->assertStringContainsString( 'class="wp-image-42"', $block['innerHTML'] );
	}

	/**
	 * A positive cover ID sets the id attribute and a matching wp-image class.
	 */
	public function test_positive_cover_id_sets_attribute_and_class(): void {
		$schema                    = Pattern_Schema::extract( self::COVER_WITHOUT_ID );
		$schema[0]['fields']['id'] = '7';
		[ $block ]                 = parse_blocks( Pattern_Schema::apply( self::COVER_WITHOUT_ID, $schema ) );

		$this->assertSame( 7, $block['attrs']['id'] );
		$this->assertSame( [ 'wp-block-cover__image-background', 'wp-image-7' ], self::background_classes( $block['innerHTML'] ) );
	}

	/**
	 * A new ID on a parallax cover replaces the wp-image class on the background div.
	 */
	public function test_parallax_cover_id_updates_div_class(): void {
		$schema                    = Pattern_Schema::extract( self::PARALLAX_COVER );
		$schema[0]['fields']['id'] = '7';
		[ $block ]                 = parse_blocks( Pattern_Schema::apply( self::PARALLAX_COVER, $schema ) );

		$this->assertSame( 7, $block['attrs']['id'] );
		$this->assertSame( [ 'has-parallax', 'wp-block-cover__image-background', 'wp-image-7' ], self::background_classes( $block['innerHTML'] ) );
	}

	/**
	 * An id sent before url is kept, because the id is written last.
	 */
	public function test_id_sent_before_url_is_kept(): void {
		$entry = [
			'block'  => 'core/image',
			'fields' => [
				'id'  => '42',
				'url' => 'https://example.com/new.jpg',
			],
		];

		[ $image ]      = parse_blocks( Pattern_Schema::apply( self::IMAGE_WITHOUT_ID, [ $entry ] ) );
		$entry['block'] = 'core/cover';
		[ $cover ]      = parse_blocks( Pattern_Schema::apply( self::COVER_WITHOUT_ID, [ $entry ] ) );

		$this->assertSame( 42, $image['attrs']['id'] );
		$this->assertStringContainsString( 'wp-image-42', $image['innerHTML'] );
		$this->assertSame( 42, $cover['attrs']['id'] );
		$this->assertStringContainsString( 'wp-image-42', $cover['innerHTML'] );
	}

	/**
	 * Media whose attachment ID becomes stale when only the url changes.
	 *
	 * @return array<string, array{string}>
	 */
	public static function media_with_id(): array {
		return [
			'image'          => [ self::IMAGE_WITH_ID ],
			'cover'          => [ self::COVER_WITH_ID ],
			'parallax cover' => [ self::PARALLAX_COVER ],
			'video cover'    => [ self::VIDEO_COVER ],
		];
	}

	/**
	 * A new url with the old id left in place clears the id and its class, so the block isn't tied to the old attachment.
	 *
	 * @param string $markup Block markup.
	 */
	#[DataProvider( 'media_with_id' )]
	public function test_url_change_clears_stale_id( string $markup ): void {
		$schema                     = Pattern_Schema::extract( $markup );
		$schema[0]['fields']['url'] = 'https://example.com/new.jpg';
		[ $block ]                  = parse_blocks( Pattern_Schema::apply( $markup, $schema ) );

		$this->assertArrayNotHasKey( 'id', $block['attrs'] );
		$this->assertStringContainsString( 'https://example.com/new.jpg', $block['innerHTML'] );
		$this->assertStringNotContainsString( 'wp-image-', $block['innerHTML'] );
		$this->assertStringNotContainsString( 'class=""', $block['innerHTML'] );
	}

	/**
	 * A video cover's new url goes into the video's src.
	 */
	public function test_video_cover_url_updates_video_src(): void {
		$schema                     = Pattern_Schema::extract( self::VIDEO_COVER );
		$schema[0]['fields']['url'] = 'https://example.com/new.mp4';
		[ $block ]                  = parse_blocks( Pattern_Schema::apply( self::VIDEO_COVER, $schema ) );

		$this->assertSame( 'https://example.com/new.mp4', $block['attrs']['url'] );
		$this->assertStringContainsString( 'src="https://example.com/new.mp4"', $block['innerHTML'] );
		$this->assertStringNotContainsString( 'v.mp4', $block['innerHTML'] );
	}

	/**
	 * An embed cover's new url replaces the embed wrapper's text, and no src is added to the figure.
	 */
	public function test_embed_cover_url_updates_wrapper_text(): void {
		$schema                     = Pattern_Schema::extract( self::EMBED_COVER );
		$schema[0]['fields']['url'] = 'https://vimeo.com/123';
		[ $block ]                  = parse_blocks( Pattern_Schema::apply( self::EMBED_COVER, $schema ) );

		$this->assertSame( 'https://vimeo.com/123', $block['attrs']['url'] );
		$this->assertStringContainsString( '<div class="wp-block-embed__wrapper">https://vimeo.com/123</div>', $block['innerHTML'] );
		$this->assertStringNotContainsString( 'src=', $block['innerHTML'] );
	}

	/**
	 * A parallax url with a query string matches core's markup, with the ampersand encoded once.
	 */
	public function test_parallax_url_with_query_matches_core(): void {
		$schema                     = Pattern_Schema::extract( self::PARALLAX_COVER );
		$schema[0]['fields']['url'] = 'https://example.com/a.jpg?x=1&y=2';
		[ $block ]                  = parse_blocks( Pattern_Schema::apply( self::PARALLAX_COVER, $schema ) );
		[ $core ]                   = parse_blocks( self::PARALLAX_COVER_WITH_QUERY );

		$this->assertSame( $core['attrs']['url'], $block['attrs']['url'] );
		$this->assertSameMarkup( $core['innerHTML'], $block['innerHTML'] );
	}

	/**
	 * Emptying a cover's url removes its background element and the gradient class that depends on it, as core's save() does.
	 */
	public function test_empty_cover_url_removes_background(): void {
		$schema                     = Pattern_Schema::extract( self::GRADIENT_COVER );
		$schema[0]['fields']['url'] = '';
		[ $block ]                  = parse_blocks( Pattern_Schema::apply( self::GRADIENT_COVER, $schema ) );
		[ $core ]                   = parse_blocks( self::GRADIENT_COVER_WITHOUT_MEDIA );

		$this->assertSameMarkup( $core['innerHTML'], $block['innerHTML'] );

		$media = [
			self::VIDEO_COVER => '<video',
			self::EMBED_COVER => '<figure',
		];

		foreach ( $media as $markup => $element ) {
			$schema                     = Pattern_Schema::extract( $markup );
			$schema[0]['fields']['url'] = '';
			[ $block ]                  = parse_blocks( Pattern_Schema::apply( $markup, $schema ) );

			$this->assertStringNotContainsString( $element, $block['innerHTML'] );
		}
	}

	/**
	 * Covers with no media, the URL to give each, and core's markup for the result.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public static function covers_gaining_media(): array {
		return [
			'image'        => [ self::COVER_WITHOUT_MEDIA, 'https://example.com/bg.jpg', self::COVER_WITHOUT_ID ],
			'full overlay' => [ self::COVER_WITHOUT_MEDIA_FULL_OVERLAY, 'https://example.com/bg.jpg', self::COVER_WITHOUT_ID ],
			'sized image'  => [ str_replace( '{"dimRatio":50}', '{"dimRatio":50,"sizeSlug":"large"}', self::COVER_WITHOUT_MEDIA ), 'https://example.com/bg.jpg', self::SIZED_COVER ],
			'gradient'     => [ self::GRADIENT_COVER_WITHOUT_MEDIA, 'https://example.com/bg.jpg', self::GRADIENT_COVER ],
			'parallax'     => [ self::PARALLAX_COVER_WITHOUT_MEDIA, 'https://example.com/a.jpg?x=1&y=2', self::PARALLAX_COVER_WITH_QUERY ],
			'video'        => [ self::VIDEO_COVER_WITHOUT_MEDIA, 'https://example.com/v.mp4', self::VIDEO_COVER ],
			'embed'        => [ self::EMBED_COVER_WITHOUT_MEDIA, 'https://www.youtube.com/watch?v=abc', self::EMBED_COVER ],
		];
	}

	/**
	 * A URL on a cover with no media adds the media element core's save() renders, with the overlay classes that depend on it. A full overlay drops to 50%, as the editor does, so the media shows.
	 *
	 * @param string $markup   Cover with no media.
	 * @param string $url      New URL.
	 * @param string $expected Core's markup for the cover with that URL.
	 */
	#[DataProvider( 'covers_gaining_media' )]
	public function test_url_on_cover_without_media_adds_media( string $markup, string $url, string $expected ): void {
		$schema                     = Pattern_Schema::extract( $markup );
		$schema[0]['fields']['url'] = $url;
		[ $block ]                  = parse_blocks( Pattern_Schema::apply( $markup, $schema ) );
		[ $core ]                   = parse_blocks( $expected );

		$this->assertSame( $core['attrs']['url'], $block['attrs']['url'] );
		$this->assertSame( $core['attrs']['dimRatio'] ?? null, $block['attrs']['dimRatio'] ?? null );
		$this->assertSameMarkup( $core['innerHTML'], $block['innerHTML'] );
	}

	/**
	 * A cover whose URL was cleared can be given a URL again.
	 */
	public function test_cleared_cover_url_can_be_filled_again(): void {
		$schema                     = Pattern_Schema::extract( self::COVER_WITHOUT_ID );
		$schema[0]['fields']['url'] = '';
		$cleared                    = Pattern_Schema::apply( self::COVER_WITHOUT_ID, $schema );

		$schema                     = Pattern_Schema::extract( $cleared );
		$schema[0]['fields']['url'] = 'https://example.com/bg.jpg';
		[ $block ]                  = parse_blocks( Pattern_Schema::apply( $cleared, $schema ) );
		[ $core ]                   = parse_blocks( self::COVER_WITHOUT_ID );

		$this->assertSameMarkup( $core['innerHTML'], $block['innerHTML'] );
	}

	/**
	 * A literal > inside an attribute value doesn't stop the media element's removal partway through.
	 */
	public function test_empty_cover_url_removes_media_with_literal_greater_than(): void {
		$markup = str_replace( 'alt=""', 'alt="1 > 0"', self::COVER_WITHOUT_ID );

		$schema                     = Pattern_Schema::extract( $markup );
		$schema[0]['fields']['url'] = '';
		[ $block ]                  = parse_blocks( Pattern_Schema::apply( $markup, $schema ) );

		$this->assertSame( '<div class="wp-block-cover">' . self::OVERLAY . '<div class="wp-block-cover__inner-container"></div></div>', trim( $block['innerHTML'] ) );
	}

	/**
	 * Only whole wp-image-{id} class names are replaced, not other classes that contain the text.
	 */
	public function test_image_class_keeps_hyphenated_classes(): void {
		$markup = str_replace( 'class="wp-image-5"', 'class="wp-image-5 my-wp-image-3"', self::IMAGE_WITH_ID );

		$schema                    = Pattern_Schema::extract( $markup );
		$schema[0]['fields']['id'] = '7';
		[ $block ]                 = parse_blocks( Pattern_Schema::apply( $markup, $schema ) );

		$proc = new WP_HTML_Tag_Processor( $block['innerHTML'] );
		$proc->next_tag( [ 'tag_name' => 'img' ] );

		$this->assertSame( 'my-wp-image-3 wp-image-7', $proc->get_attribute( 'class' ) );
	}

	/**
	 * A scalar field value that isn't a string is written as a string, and an array value is ignored.
	 */
	public function test_non_string_field_values(): void {
		$entry = [
			'block'  => 'core/image',
			'fields' => [
				'id'  => 42,
				'alt' => [ 'Photo' ],
			],
		];

		[ $block ] = parse_blocks( Pattern_Schema::apply( self::IMAGE_WITHOUT_ID, [ $entry ] ) );

		$this->assertSame( 42, $block['attrs']['id'] );
		$this->assertStringContainsString( 'alt=""', $block['innerHTML'] );
	}

	/**
	 * An entry without fields is ignored.
	 */
	public function test_entry_without_fields_is_ignored(): void {
		$this->assertSame( serialize_blocks( parse_blocks( self::IMAGE_WITH_ID ) ), Pattern_Schema::apply( self::IMAGE_WITH_ID, [ [ 'block' => 'core/image' ] ] ) );
	}

	/**
	 * When an entry is left out, later entries still fill blocks of their own type instead of shifting onto the wrong blocks.
	 */
	public function test_entries_only_fill_blocks_of_their_type(): void {
		$markup = self::RICH_TEXT . "\n\n" . self::IMAGE_WITHOUT_ID . "\n\n" . self::COVER_WITHOUT_ID;
		$schema = Pattern_Schema::extract( $markup );

		array_shift( $schema );

		$schema[0]['fields']['alt'] = 'Photo';
		$schema[1]['fields']['alt'] = 'Background';

		[ $paragraph, , $image, , $cover ] = parse_blocks( Pattern_Schema::apply( $markup, $schema ) );

		$this->assertStringContainsString( '<strong>48% increase</strong>', $paragraph['innerHTML'] );
		$this->assertStringContainsString( 'alt="Photo"', $image['innerHTML'] );
		$this->assertSame( 'Background', $cover['attrs']['alt'] );
		$this->assertSame( 'https://example.com/bg.jpg', $cover['attrs']['url'] );
	}

	/**
	 * An entry for a block type with no fields is dropped, so it doesn't hold back the entries after it.
	 */
	public function test_unknown_block_entries_are_dropped(): void {
		$schema = [
			[
				'block'  => 'core/typo',
				'fields' => [ 'content' => 'Lost' ],
			],
			[
				'block'  => 'core/image',
				'fields' => [ 'alt' => 'Photo' ],
			],
		];

		$this->assertStringContainsString( 'alt="Photo"', Pattern_Schema::apply( self::IMAGE_WITHOUT_ID, $schema ) );
	}

	/**
	 * A parallax cover alt is written as role="img" and aria-label, and clearing it removes both.
	 */
	public function test_parallax_cover_alt_sets_and_clears_role(): void {
		$schema                     = Pattern_Schema::extract( self::PARALLAX_COVER );
		$schema[0]['fields']['alt'] = 'Mountains';
		$with_alt                   = Pattern_Schema::apply( self::PARALLAX_COVER, $schema );

		$this->assertStringContainsString( 'role="img"', $with_alt );
		$this->assertStringContainsString( 'aria-label="Mountains"', $with_alt );

		$schema                     = Pattern_Schema::extract( $with_alt );
		$schema[0]['fields']['alt'] = '';
		$without_alt                = Pattern_Schema::apply( $with_alt, $schema );

		$this->assertStringNotContainsString( 'role=', $without_alt );
		$this->assertStringNotContainsString( 'aria-label=', $without_alt );
	}

	/**
	 * Assert two HTML strings are equivalent the way the block editor's validator compares them: same tags and text, the same attributes in any order, class names in any order, and empty attributes ignored.
	 *
	 * @param string $expected Expected HTML.
	 * @param string $actual   Actual HTML.
	 */
	private function assertSameMarkup( string $expected, string $actual ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Matches PHPUnit's assertion naming.
		$this->assertSame( self::tokens( $expected ), self::tokens( $actual ), $actual );
	}

	/**
	 * Normalize HTML into a list of tags and text for comparison.
	 *
	 * @param string $html HTML.
	 *
	 * @return list<array{string, mixed}>
	 */
	private static function tokens( string $html ): array {
		$proc   = new WP_HTML_Tag_Processor( $html );
		$tokens = [];

		while ( $proc->next_token() ) {
			if ( '#text' === $proc->get_token_type() ) {
				$tokens[] = [ '#text', $proc->get_modifiable_text() ];
				continue;
			}

			if ( '#tag' !== $proc->get_token_type() ) {
				continue;
			}

			$attributes = [];

			foreach ( $proc->get_attribute_names_with_prefix( '' ) ?? [] as $name ) {
				$value = $proc->get_attribute( $name );

				if ( 'class' === $name ) {
					$value = implode( ' ', self::sorted_classes( $proc ) );
				}

				if ( '' !== $value ) {
					$attributes[ $name ] = $value;
				}
			}

			ksort( $attributes );

			$tokens[] = [ ( $proc->is_tag_closer() ? '/' : '' ) . $proc->get_tag(), $attributes ];
		}

		return $tokens;
	}

	/**
	 * Get the sorted class names on the cover's background element.
	 *
	 * @param string $html Cover HTML.
	 *
	 * @return list<string>
	 */
	private static function background_classes( string $html ): array {
		$proc = new WP_HTML_Tag_Processor( $html );

		while ( $proc->next_tag() ) {
			if ( $proc->has_class( 'wp-block-cover__image-background' ) ) {
				return self::sorted_classes( $proc );
			}
		}

		return [];
	}

	/**
	 * Get the sorted class names on the current tag.
	 *
	 * @param \WP_HTML_Tag_Processor $proc Tag processor positioned on a tag.
	 *
	 * @return list<string>
	 */
	private static function sorted_classes( WP_HTML_Tag_Processor $proc ): array {
		$classes = iterator_to_array( $proc->class_list(), false );

		sort( $classes );

		return $classes;
	}
}
