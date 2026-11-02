<?php
/**
 * Tests for Block_Validator.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Editing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Tree;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Validator;
use rtCamp\Publishio\Modules\MCP\Editing\Pattern_Overrides;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Block_ValidatorTest
 *
 * @phpstan-import-type Problem from Errors
 */
#[CoversClass( Block_Validator::class )]
#[CoversClass( Pattern_Overrides::class )]
class Block_ValidatorTest extends TestCase {
	/**
	 * Run as an editor, who can save unfiltered HTML on a single site.
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
	}

	/**
	 * Remove what the binding tests registered.
	 */
	protected function tearDown(): void {
		remove_filter( 'block_bindings_supported_attributes_core/heading', [ self::class, 'bind_heading_level' ] );

		if ( get_block_bindings_source( 'publishio/test' ) ) {
			unregister_block_bindings_source( 'publishio/test' );
		}

		unregister_meta_key( 'post', 'publishio_brand' );

		foreach ( [ 'publishio_price', 'publishio_cost', '_publishio_secret' ] as $key ) {
			unregister_post_meta( 'publishio_product', $key );
		}

		foreach ( [ 'publishio_product', 'publishio_note' ] as $type ) {
			if ( post_type_exists( $type ) ) {
				unregister_post_type( $type );
			}
		}

		parent::tearDown();
	}

	/**
	 * Markup that breaks one check, with the code and a phrase the message must contain.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public static function invalid_markup(): array {
		return [
			'freeform_html'        => [ '<p>Loose text</p>', 'freeform_html', 'outside any block' ],
			'unclosed tag'         => [ '<!-- wp:paragraph --><p>Hi <strong>there</p><!-- /wp:paragraph -->', 'malformed_html', '</p> closes <strong>' ],
			'never closed'         => [ '<!-- wp:paragraph --><p>Hi<!-- /wp:paragraph -->', 'malformed_html', '<p> is never closed' ],
			'unknown_block'        => [ '<!-- wp:acme/slider /-->', 'unknown_block', 'acme/slider isn\'t a registered block' ],
			'needs a parent'       => [ '<!-- wp:column --><div class="wp-block-column"></div><!-- /wp:column -->', 'invalid_parent', 'Wrap it in a columns block' ],
			'needs an ancestor'    => [ '<!-- wp:comment-author-name /-->', 'invalid_parent', 'somewhere inside core/comment-template' ],
			'not an allowed child' => [ '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div><!-- /wp:buttons -->', 'invalid_parent', 'takes only core/button' ],
			'unknown attribute'    => [ '<!-- wp:heading {"size":"large"} --><h2 class="wp-block-heading">Hi</h2><!-- /wp:heading -->', 'invalid_attribute', 'Did you mean "fontSize"' ],
			'wrong value type'     => [ '<!-- wp:heading {"level":"high"} --><h2 class="wp-block-heading">Hi</h2><!-- /wp:heading -->', 'invalid_attribute', 'level' ],
			'attribute_in_html'    => [ '<!-- wp:image {"url":"https://example.com/a.jpg"} --><figure class="wp-block-image"><img src="https://example.com/a.jpg" alt=""/></figure><!-- /wp:image -->', 'attribute_in_html', 'the src of img' ],
			'missing pattern'      => [ '<!-- wp:block {"ref":999999} /-->', 'unknown_pattern', 'no synced pattern with ID 999999' ],
		];
	}

	/**
	 * Each check refuses the markup that breaks it.
	 *
	 * @param string $markup   The markup.
	 * @param string $code     The expected problem code.
	 * @param string $contains A phrase the message must contain.
	 */
	#[DataProvider( 'invalid_markup' )]
	public function test_refuses_invalid_markup( string $markup, string $code, string $contains ): void {
		$problems = $this->check( $markup );

		$this->assertContains( $code, array_column( $problems, 'code' ) );
		$this->assertStringContainsString( $contains, $this->message_for( $problems, $code ) );
	}

	/**
	 * Valid markup, including nested placement and typed attributes, passes every check.
	 */
	public function test_accepts_valid_markup(): void {
		$markup = '<!-- wp:heading {"level":3,"fontSize":"large"} --><h3 class="wp-block-heading has-large-font-size">Hi</h3><!-- /wp:heading -->'
			. '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>A <br>line</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->'
			. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.com">Go</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';

		$this->assertSame( [], $this->check( $markup ) );
	}

	/**
	 * A block that needs a parent passes when the insert goes inside that parent.
	 */
	public function test_uses_the_container_as_parent(): void {
		$validator = Block_Validator::for_post_type( 'post' );
		$blocks    = Block_Tree::parse( '<!-- wp:column --><div class="wp-block-column"></div><!-- /wp:column -->' );

		$this->assertSame( [], $validator->check( $blocks, [ 'core/columns' ] ) );
	}

	/**
	 * Trusted pattern markup skips the HTML and attribute-name checks, but not placement.
	 */
	public function test_trusted_markup_skips_written_markup_checks(): void {
		$validator = Block_Validator::for_post_type( 'post' );

		$this->assertSame( [], $validator->check( Block_Tree::parse( '<!-- wp:heading {"acmeOnly":true} --><h2 class="wp-block-heading">Hi</h2><!-- /wp:heading -->' ), [], true ) );
		$this->assertSame( [ 'invalid_parent' ], array_column( $validator->check( Block_Tree::parse( '<!-- wp:column --><div class="wp-block-column"></div><!-- /wp:column -->' ), [], true ), 'code' ) );
	}

	/**
	 * A block may go in a container that lists it, even when its own parent list doesn't name that container, as in the editor; placements the site's patterns use are accepted too.
	 */
	public function test_placement_follows_the_editor_and_the_site_patterns(): void {
		$link    = '<!-- wp:navigation-link {"label":"Menu","url":"#"} /-->';
		$submenu = '<!-- wp:navigation --><!-- wp:navigation-submenu {"label":"More"} -->' . $link . '<!-- /wp:navigation-submenu --><!-- /wp:navigation -->';

		$this->assertSame( [], $this->check( '<!-- wp:navigation --><!-- wp:navigation-link {"label":"A","url":"#"} -->' . $link . '<!-- /wp:navigation-link --><!-- /wp:navigation -->' ) );
		$this->assertSame( [ 'invalid_parent' ], array_column( $this->check( $submenu ), 'code' ) );

		register_block_pattern(
			'publishio-test/menu',
			[
				'title'   => 'Menu',
				'content' => $submenu,
			]
		);
		$problems = $this->check( $submenu );
		unregister_block_pattern( 'publishio-test/menu' );

		$this->assertSame( [], $problems );
		$this->assertSame( [ 'invalid_parent' ], array_column( $this->check( '<!-- wp:column --><div class="wp-block-column"></div><!-- /wp:column -->' ), 'code' ) );
	}

	/**
	 * Blocks the post's editor doesn't allow are refused.
	 */
	public function test_refuses_blocks_the_post_does_not_allow(): void {
		$filter = static fn (): array => [ 'core/paragraph' ];
		add_filter( 'allowed_block_types_all', $filter );

		$problems = $this->check( '<!-- wp:heading --><h2 class="wp-block-heading">Hi</h2><!-- /wp:heading --><!-- wp:paragraph --><p>ok</p><!-- /wp:paragraph -->' );

		remove_filter( 'allowed_block_types_all', $filter );

		$this->assertSame( [ 'block_not_allowed' ], array_column( $problems, 'code' ) );
		$this->assertSame( '0:heading', $problems[0]['path'] );
		$this->assertStringContainsString( 'post_type: post', $problems[0]['message'] );
	}

	/**
	 * Every problem is found in one pass.
	 */
	public function test_collects_every_problem(): void {
		$problems = $this->check( '<p>Loose</p><!-- wp:acme/one /--><!-- wp:acme/two /-->' );

		$this->assertSame( [ 'freeform_html', 'unknown_block', 'unknown_block' ], array_column( $problems, 'code' ) );
		$this->assertSame( [ '0:freeform', '1:acme/one', '2:acme/two' ], array_column( $problems, 'path' ) );
	}

	/**
	 * A block that may be used once per post is refused when the post would have two.
	 */
	public function test_refuses_a_second_single_use_block(): void {
		$validator = Block_Validator::for_post_type( 'post' );
		$more      = '<!-- wp:more --><!--more--><!-- /wp:more -->';

		$none = Block_Tree::from_markup( '' );

		$this->assertSame( [], $validator->check_single_use( Block_Tree::from_markup( $more ), $none ) );
		$this->assertSame( [ 'duplicate_not_allowed' ], array_column( $validator->check_single_use( Block_Tree::from_markup( $more . $more ), $none ), 'code' ) );
	}

	/**
	 * A post that already has a duplicate isn't blocked by it, unless the change adds another.
	 */
	public function test_existing_duplicates_do_not_block_unrelated_changes(): void {
		$validator = Block_Validator::for_post_type( 'post' );
		$more      = '<!-- wp:more --><!--more--><!-- /wp:more -->';
		$before    = Block_Tree::from_markup( $more . $more );

		$this->assertSame( [], $validator->check_single_use( Block_Tree::from_markup( $more . $more . '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' ), $before ) );
		$this->assertSame( [ 'duplicate_not_allowed' ], array_column( $validator->check_single_use( Block_Tree::from_markup( $more . $more . $more ), $before ), 'code' ) );
	}

	/**
	 * Problems the replaced blocks already had are not reported again, but new ones are.
	 */
	public function test_baseline_problems_are_not_reported_again(): void {
		$validator = Block_Validator::for_post_type( 'post' );
		$old       = Block_Tree::parse( '<!-- wp:heading {"acmeOnly":true} --><h2 class="wp-block-heading">Old</h2><!-- /wp:heading -->' );
		$baseline  = $validator->check( $old );

		$this->assertSame( [ 'invalid_attribute' ], array_column( $baseline, 'code' ) );
		$this->assertSame( [], $validator->check( Block_Tree::parse( '<!-- wp:heading {"acmeOnly":true} --><h2 class="wp-block-heading">New</h2><!-- /wp:heading -->' ), [], false, $baseline ) );
		$this->assertSame( [ 'invalid_attribute' ], array_column( $validator->check( Block_Tree::parse( '<!-- wp:heading {"acmeOnly":true,"size":"x"} --><h2 class="wp-block-heading">New</h2><!-- /wp:heading -->' ), [], false, $baseline ), 'code' ) );
	}

	/**
	 * For a user without unfiltered_html, tags and attributes kses would strip are refused.
	 */
	public function test_refuses_html_kses_would_strip(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'author' ] ) );

		$problems = $this->check( '<!-- wp:html --><p onclick="steal()">Hi</p><script>alert(1)</script><a href="javascript:alert(1)">x</a><!-- /wp:html -->' );

		$this->assertSame( [ 'html_stripped' ], array_column( $problems, 'code' ) );
		$this->assertStringContainsString( 'onclick attribute on <p>', $problems[0]['message'] );
		$this->assertStringContainsString( '<script>', $problems[0]['message'] );
		$this->assertStringContainsString( 'href="javascript:alert(1)"', $problems[0]['message'] );
		$this->assertSame( [], $this->check( '<!-- wp:paragraph --><p><a href="https://example.com" data-id="1">Hi</a></p><!-- /wp:paragraph -->' ) );
	}

	/**
	 * Override values are checked against the pattern's fields and the supported attributes.
	 */
	public function test_checks_synced_pattern_overrides(): void {
		$ref   = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_title'   => 'Footer CTA',
				'post_content' => '<!-- wp:heading {"metadata":{"name":"CTA heading","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><h2 class="wp-block-heading">Ready?</h2><!-- /wp:heading -->'
					. '<!-- wp:image {"metadata":{"name":"CTA image","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><figure class="wp-block-image"><img alt=""/></figure><!-- /wp:image -->',
			]
		);
		$check = fn ( array $content ): array => $this->check(
			'<!-- wp:block ' . wp_json_encode(
				[
					'ref'     => $ref,
					'content' => $content,
				]
			) . ' /-->'
		);

		$this->assertSame( [], $check( [ 'CTA heading' => [ 'content' => 'Start now' ] ] ) );
		$this->assertStringContainsString( 'Its fields are "CTA heading" and "CTA image"', $this->message_for( $check( [ 'Hero title' => [ 'content' => 'x' ] ] ), 'unknown_override' ) );
		$this->assertStringContainsString( 'It supports "content"', $this->message_for( $check( [ 'CTA heading' => [ 'level' => 3 ] ] ), 'unknown_override' ) );
		$this->assertStringContainsString( 'CTA image', $this->message_for( $check( [ 'CTA image' => [ 'id' => 'not a number' ] ] ), 'unknown_override' ) );
	}

	/**
	 * Override fields are read from the pattern, and a reference's values from its content attribute.
	 */
	public function test_reads_override_fields_and_values(): void {
		$ref = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:button {"metadata":{"name":"CTA button","bindings":{"text":{"source":"core/pattern-overrides"},"url":{"source":"core/post-meta"}}}} --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Go</a></div><!-- /wp:button --></div><!-- /wp:group -->',
			]
		);

		$this->assertSame(
			[
				'CTA button' => [
					'block'      => 'core/button',
					'attributes' => [ 'text' ],
				],
			],
			Pattern_Overrides::fields( $ref )
		);
		$this->assertSame( [ 'CTA button' => [ 'text' => 'Join' ] ], Pattern_Overrides::values( [ 'attrs' => [ 'content' => [ 'CTA button' => [ 'text' => 'Join' ] ] ] ] ) );
	}

	/**
	 * A reference to a pattern that isn't published, or has a password, is refused because it renders nothing.
	 */
	public function test_refuses_references_that_render_nothing(): void {
		foreach ( [ [ 'post_status' => 'draft' ], [ 'post_password' => 'secret' ] ] as $args ) {
			$ref = self::factory()->post->create( [ 'post_type' => 'wp_block' ] + $args );

			$this->assertStringContainsString( 'would show nothing', $this->message_for( $this->check( '<!-- wp:block {"ref":' . $ref . '} /-->' ), 'unknown_pattern' ) );
		}
	}

	/**
	 * For a user without unfiltered_html, HTML kses would strip from attribute values is refused too.
	 */
	public function test_refuses_html_kses_would_strip_from_attributes(): void {
		$ref = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_content' => '<!-- wp:heading {"metadata":{"name":"Title","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><h2 class="wp-block-heading">Hi</h2><!-- /wp:heading -->',
			]
		);
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'author' ] ) );

		$problems = $this->check(
			'<!-- wp:block ' . wp_json_encode(
				[
					'ref'     => $ref,
					'content' => [ 'Title' => [ 'content' => 'Hi <img src=x onerror="steal()">' ] ],
				]
			) . ' /-->'
		);

		$this->assertStringContainsString( 'the onerror attribute on <img>', $this->message_for( $problems, 'html_stripped' ) );
	}

	/**
	 * A pattern the current user can't read is refused.
	 */
	public function test_refuses_a_pattern_the_user_cannot_read(): void {
		$ref = self::factory()->post->create(
			[
				'post_type'   => 'wp_block',
				'post_status' => 'private',
				'post_author' => self::factory()->user->create( [ 'role' => 'administrator' ] ),
			]
		);
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$fields = Pattern_Overrides::fields( $ref );

		$this->assertWPError( $fields );
		$this->assertSame( 'unknown_pattern', $fields->get_error_code() );
	}

	/**
	 * Bindings that show a value: by post type, source, block and context.
	 *
	 * @return array<string, array{string, string, list<string>}>
	 */
	public static function shown_bindings(): array {
		return [
			'a field of the post type'                 => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"publishio_price"}}}}} --><p></p><!-- /wp:paragraph -->', 'publishio_product', [] ],
			'a field registered for every type'        => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"publishio_brand"}}}}} --><p></p><!-- /wp:paragraph -->', 'publishio_note', [] ],
			'several attributes on one block'          => [ '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"metadata":{"bindings":{"text":{"source":"core/post-meta","args":{"key":"publishio_price"}},"url":{"source":"core/post-data","args":{"field":"link"}}}}} --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button"></a></div><!-- /wp:button --></div><!-- /wp:buttons -->', 'publishio_product', [] ],
			'an image attribute'                       => [ '<!-- wp:image {"metadata":{"bindings":{"alt":{"source":"core/post-meta","args":{"key":"publishio_price"}}}}} --><figure class="wp-block-image"><img alt=""/></figure><!-- /wp:image -->', 'publishio_product', [] ],
			'post data by field'                       => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-data","args":{"field":"date"}}}}} --><p></p><!-- /wp:paragraph -->', 'page', [] ],
			'post data by the older key'               => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-data","args":{"key":"date"}}}}} --><p></p><!-- /wp:paragraph -->', 'page', [] ],
			'term data'                                => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/term-data","args":{"field":"name"}}}}} --><p></p><!-- /wp:paragraph -->', 'page', [] ],
			'a pattern override'                       => [ '<!-- wp:paragraph {"metadata":{"name":"Intro","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><p>Hi</p><!-- /wp:paragraph -->', 'page', [] ],
			'a plugin source, with any arguments'      => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"publishio/test","args":{"anything":1}}}}} --><p></p><!-- /wp:paragraph -->', 'page', [] ],
			'an attribute a filter makes bindable'     => [ '<!-- wp:heading {"metadata":{"bindings":{"level":{"source":"publishio/test"}}}} --><h2 class="wp-block-heading">Hi</h2><!-- /wp:heading -->', 'page', [] ],
			'a query loop of another type'             => [ '<!-- wp:query {"queryId":1,"query":{"postType":"publishio_product","perPage":3,"inherit":false}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"publishio_price"}}}}} --><p></p><!-- /wp:paragraph --><!-- /wp:post-template --></div><!-- /wp:query -->', 'page', [] ],
			'a query loop that follows the main query' => [ '<!-- wp:query {"queryId":1,"query":{"postType":"post","perPage":3,"inherit":true}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"anything"}}}}} --><p></p><!-- /wp:paragraph --><!-- /wp:post-template --></div><!-- /wp:query -->', 'page', [] ],
			'a saved pattern, used in any type'        => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"anything"}}}}} --><p></p><!-- /wp:paragraph -->', 'wp_block', [] ],
			'inside an existing post template'         => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"anything"}}}}} --><p></p><!-- /wp:paragraph -->', 'page', [ 'core/query', 'core/post-template' ] ],
		];
	}

	/**
	 * Bindings that show a value are accepted.
	 *
	 * @param string             $markup    The markup.
	 * @param string             $post_type The post type it's for.
	 * @param array<int, string> $ancestors The blocks it goes inside.
	 */
	#[DataProvider( 'shown_bindings' )]
	public function test_accepts_bindings_that_show_a_value( string $markup, string $post_type, array $ancestors ): void {
		$this->register_binding_fixtures();

		$this->assertSame( [], Block_Validator::for_post_type( $post_type )->check( Block_Tree::parse( $markup ), $ancestors ) );
	}

	/**
	 * Bindings that would show nothing, with the phrase the message must contain and the path it names.
	 *
	 * @return array<string, array{string, string, string, string}>
	 */
	public static function empty_bindings(): array {
		return [
			'a field not shown in REST'                => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"publishio_cost"}}}}} --><p></p><!-- /wp:paragraph -->', 'publishio_product', '"publishio_cost" isn\'t a field Products show to blocks', '0:paragraph' ],
			'a protected field'                        => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"_publishio_secret"}}}}} --><p></p><!-- /wp:paragraph -->', 'publishio_product', '"_publishio_secret" isn\'t a field Products show to blocks', '0:paragraph' ],
			'another type\'s field'                    => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"publishio_price"}}}}} --><p></p><!-- /wp:paragraph -->', 'page', 'isn\'t a field Pages show to blocks', '0:paragraph' ],
			'a type with only fields for every type'   => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"publishio_price"}}}}} --><p></p><!-- /wp:paragraph -->', 'publishio_note', 'isn\'t a field Notes show to blocks, so this block would show nothing. Use one of publishio_brand.', '0:paragraph' ],
			'a query loop of a type without the field' => [ '<!-- wp:query {"queryId":1,"query":{"postType":"post","perPage":3,"inherit":false}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"publishio_price"}}}}} --><p></p><!-- /wp:paragraph --><!-- /wp:post-template --></div><!-- /wp:query -->', 'publishio_product', 'isn\'t a field Posts show to blocks', '0:query > 0:post-template > 0:paragraph' ],
			'no key'                                   => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{}}}}} --><p></p><!-- /wp:paragraph -->', 'publishio_product', 'to core/post-meta without args.key', '0:paragraph' ],
			'an empty key'                             => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":""}}}}} --><p></p><!-- /wp:paragraph -->', 'publishio_product', 'without args.key', '0:paragraph' ],
			'arguments that aren\'t an object'         => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":"publishio_price"}}}} --><p></p><!-- /wp:paragraph -->', 'publishio_product', 'without args.key', '0:paragraph' ],
			'no key in a saved pattern'                => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta"}}}} --><p></p><!-- /wp:paragraph -->', 'wp_block', 'without args.key', '0:paragraph' ],
			'post data without a field'                => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-data"}}}} --><p></p><!-- /wp:paragraph -->', 'page', 'to core/post-data without args.field', '0:paragraph' ],
			'term data without a field'                => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/term-data","args":{"key":"name"}}}}} --><p></p><!-- /wp:paragraph -->', 'page', 'to core/term-data without args.field', '0:paragraph' ],
			'an unknown source'                        => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"acme/none"}}}} --><p></p><!-- /wp:paragraph -->', 'page', 'to "acme/none", which isn\'t a binding source', '0:paragraph' ],
			'no source'                                => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"args":{"key":"publishio_price"}}}}} --><p></p><!-- /wp:paragraph -->', 'publishio_product', 'binds "content" without a source', '0:paragraph' ],
			'a binding that isn\'t an object'          => [ '<!-- wp:paragraph {"metadata":{"bindings":{"content":"core/post-meta"}}} --><p></p><!-- /wp:paragraph -->', 'publishio_product', 'binds "content" without a source', '0:paragraph' ],
			'an attribute the block can\'t bind'       => [ '<!-- wp:paragraph {"metadata":{"bindings":{"dropCap":{"source":"core/post-meta","args":{"key":"publishio_price"}}}}} --><p></p><!-- /wp:paragraph -->', 'publishio_product', 'can\'t bind "dropCap". It can bind content', '0:paragraph' ],
			'the default attribute outside pattern overrides' => [ '<!-- wp:paragraph {"metadata":{"bindings":{"__default":{"source":"core/post-meta","args":{"key":"publishio_price"}}}}} --><p></p><!-- /wp:paragraph -->', 'publishio_product', 'can\'t bind "__default"', '0:paragraph' ],
			'a block without bindings'                 => [ '<!-- wp:group {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"publishio_price"}}}}} --><div class="wp-block-group"></div><!-- /wp:group -->', 'publishio_product', 'core/group doesn\'t support bindings', '0:group' ],
			'one bad binding among good ones'          => [ '<!-- wp:group --><div class="wp-block-group"><!-- wp:image {"metadata":{"bindings":{"alt":{"source":"core/post-meta","args":{"key":"publishio_price"}},"url":{"source":"core/post-meta","args":{"key":"publishio_cost"}}}}} --><figure class="wp-block-image"><img alt=""/></figure><!-- /wp:image --></div><!-- /wp:group -->', 'publishio_product', '"publishio_cost" isn\'t a field', '0:group > 0:image' ],
		];
	}

	/**
	 * Each binding that would show nothing is one problem at its path, in written and registered pattern markup.
	 *
	 * @param string $markup    The markup.
	 * @param string $post_type The post type it's for.
	 * @param string $phrase    A phrase the message must contain.
	 * @param string $path      The path the problem names.
	 */
	#[DataProvider( 'empty_bindings' )]
	public function test_refuses_bindings_that_show_nothing( string $markup, string $post_type, string $phrase, string $path ): void {
		$this->register_binding_fixtures();

		foreach ( [ false, true ] as $trusted ) {
			$problems = Block_Validator::for_post_type( $post_type )->check( Block_Tree::parse( $markup ), [], $trusted );

			$this->assertCount( 1, $problems, (string) wp_json_encode( $problems ) );
			$this->assertSame( 'invalid_binding', $problems[0]['code'] );
			$this->assertSame( $path, $problems[0]['path'] );
			$this->assertStringContainsString( $phrase, $problems[0]['message'] );
		}
	}

	/**
	 * A field binding in a type with no fields says to write the value in the markup.
	 */
	public function test_names_a_type_without_fields(): void {
		register_post_type(
			'publishio_note',
			[
				'labels'       => [ 'name' => 'Notes' ],
				'public'       => true,
				'show_in_rest' => true,
			]
		);

		$problems = Block_Validator::for_post_type( 'publishio_note' )->check( Block_Tree::parse( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"price"}}}}} --><p></p><!-- /wp:paragraph -->' ) );

		$this->assertStringContainsString( 'Notes have no such fields: write the value in the markup instead.', $this->message_for( $problems, 'invalid_binding' ) );
	}

	/**
	 * Post types, fields, a binding source and a filter for the binding tests.
	 */
	private function register_binding_fixtures(): void {
		register_post_type(
			'publishio_product',
			[
				'labels'       => [ 'name' => 'Products' ],
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => [ 'title', 'editor', 'custom-fields' ],
			]
		);
		register_post_type(
			'publishio_note',
			[
				'labels'       => [ 'name' => 'Notes' ],
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => [ 'title', 'editor' ],
			]
		);
		register_post_meta(
			'publishio_product',
			'publishio_price',
			[
				'type'         => 'number',
				'single'       => true,
				'show_in_rest' => [ 'schema' => [ 'minimum' => 0 ] ],
			]
		);
		register_post_meta(
			'publishio_product',
			'publishio_cost',
			[
				'type'   => 'number',
				'single' => true,
			]
		);
		register_post_meta(
			'publishio_product',
			'_publishio_secret',
			[
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => '__return_true',
			]
		);
		register_meta(
			'post',
			'publishio_brand',
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
			]
		);
		register_block_bindings_source(
			'publishio/test',
			[
				'label'              => 'Test',
				'get_value_callback' => static fn (): string => 'Value',
			]
		);
		add_filter( 'block_bindings_supported_attributes_core/heading', [ self::class, 'bind_heading_level' ] );
	}

	/**
	 * Make a heading's level bindable.
	 *
	 * @param array<int, string> $attributes The bindable attributes.
	 *
	 * @return array<int, string>
	 */
	public static function bind_heading_level( array $attributes ): array {
		return [ ...$attributes, 'level' ];
	}

	/**
	 * Check markup at the top of a post.
	 *
	 * @param string $markup The markup.
	 *
	 * @return list<Problem>
	 */
	private function check( string $markup ): array {
		return Block_Validator::for_post_type( 'post' )->check( Block_Tree::parse( $markup ) );
	}

	/**
	 * The message of the first problem with a code.
	 *
	 * @param array<int, Problem> $problems The problems.
	 * @param string              $code     The code.
	 */
	private function message_for( array $problems, string $code ): string {
		foreach ( $problems as $problem ) {
			if ( $code === $problem['code'] ) {
				return $problem['message'];
			}
		}

		$this->fail( sprintf( 'No %s problem in: %s', $code, wp_json_encode( $problems ) ) );
	}
}
