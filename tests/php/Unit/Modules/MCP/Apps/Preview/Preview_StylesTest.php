<?php
/**
 * Unit tests for Preview_Styles.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Apps\Preview
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Apps\Preview;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use rtCamp\Publishio\Modules\MCP\Apps\Preview\Preview_Styles;
use rtCamp\Publishio\Tests\Abstracts\Preview_Fixtures;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Preview_StylesTest
 */
#[CoversClass( Preview_Styles::class )]
class Preview_StylesTest extends TestCase {
	use Preview_Fixtures;

	/**
	 * {@inheritDoc}
	 */
	public function set_up(): void {
		parent::set_up();

		$this->make_fixture_dir();
	}

	/**
	 * {@inheritDoc}
	 */
	public function tear_down(): void {
		Preview_Styles::deactivate();

		$this->remove_fixture_dir();

		parent::tear_down();
	}

	/**
	 * A stylesheet this install can read is replaced by its own contents.
	 */
	public function test_local_stylesheet_is_inlined(): void {
		$href = $this->fixture( 'local.css', '.pattern{color:red}' );

		$tag = Preview_Styles::inline_tag( $this->link_tag( $href ), 'fixture', $href, 'all' );

		$this->assertStringContainsString( '.pattern{color:red}', $tag );
		$this->assertStringNotContainsString( '<link', $tag );
		$this->assertStringContainsString( "id='fixture-css'", $tag );
	}

	/**
	 * A stylesheet served from wp-includes is inlined too.
	 *
	 * Core, the theme, a plugin, a custom block's `style` and a block extension's all arrive through this one filter, and wp-includes resolves through a different asset root than wp-content does.
	 */
	public function test_core_stylesheet_root_resolves(): void {
		$href = includes_url( 'css/dashicons.min.css' );

		if ( ! is_readable( ABSPATH . WPINC . '/css/dashicons.min.css' ) ) {
			$this->markTestSkipped( 'This install has no built core stylesheets.' );
		}

		$tag = Preview_Styles::inline_tag( $this->link_tag( $href ), 'dashicons', $href, 'all' );

		$this->assertStringContainsString( 'dashicons', $tag );
		$this->assertStringNotContainsString( '<link', $tag );
	}

	/**
	 * A stylesheet on another host stays linked.
	 */
	public function test_remote_stylesheet_is_left_alone(): void {
		$href = 'https://fonts.googleapis.com/css2?family=Instrument+Sans';
		$link = $this->link_tag( $href );

		$this->assertSame( $link, Preview_Styles::inline_tag( $link, 'webfont', $href, 'all' ) );
	}

	/**
	 * A URL that walks out of the directory it matched is refused.
	 */
	public function test_traversal_outside_the_asset_root_is_refused(): void {
		$href = content_url( '../../../../etc/passwd.css' );
		$link = $this->link_tag( $href );

		$this->assertSame( $link, Preview_Styles::inline_tag( $link, 'traversal', $href, 'all' ) );
	}

	/**
	 * URLs inside the stylesheet come out absolute.
	 *
	 * A srcdoc document takes the parent's base URL, so neither a stylesheet-relative nor a root-relative reference resolves once the CSS is inline.
	 */
	public function test_stylesheet_urls_are_made_absolute(): void {
		$css  = '@font-face{src:url(fonts/a.woff2)}.b{background:url("/wp-content/uploads/b.png")}';
		$href = $this->fixture( 'urls.css', $css );

		$tag = Preview_Styles::inline_tag( '', 'urls', $href, 'all' );

		$this->assertStringContainsString( dirname( $href ) . '/fonts/a.woff2', $tag );
		$this->assertStringContainsString( home_url( '/wp-content/uploads/b.png' ), $tag );
		$this->assertStringNotContainsString( 'url(fonts/', $tag );
	}

	/**
	 * Absolute and data URLs inside the stylesheet are untouched.
	 */
	public function test_absolute_and_data_urls_are_preserved(): void {
		$css  = '.a{background:url(https://cdn.example.com/a.png)}.b{background:url(data:image/gif;base64,AA)}';
		$href = $this->fixture( 'absolute.css', $css );

		$tag = Preview_Styles::inline_tag( '', 'absolute', $href, 'all' );

		$this->assertStringContainsString( 'url(https://cdn.example.com/a.png)', $tag );
		$this->assertStringContainsString( 'url(data:image/gif;base64,AA)', $tag );
	}

	/**
	 * A non-default media attribute survives as a media query.
	 */
	public function test_media_attribute_becomes_a_media_query(): void {
		$href = $this->fixture( 'print.css', '.a{color:#000}' );

		$tag = Preview_Styles::inline_tag( '', 'print', $href, 'print' );

		$this->assertStringContainsString( '@media print{.a{color:#000}}', $tag );
	}

	/**
	 * CSS cannot close the style element it is placed in.
	 */
	public function test_style_element_cannot_be_closed_from_css(): void {
		$href = $this->fixture( 'breakout.css', '.a{content:"</style><script>x()</script>"}' );

		$tag = Preview_Styles::inline_tag( '', 'breakout', $href, 'all' );

		$this->assertStringNotContainsString( '</style><script>', $tag );
		$this->assertStringContainsString( '<\/style>', $tag );
	}

	/**
	 * On a subsite, a stylesheet under wp-content still resolves.
	 *
	 * The wp-content directory does not follow site_url() on a network: on a subdirectory install it stays at the network root while site_url() carries the subsite path, so a resolver anchored on this site's URL alone would refuse every plugin and theme stylesheet the preview needs.
	 */
	#[Group( 'ms-required' )]
	public function test_subsite_content_stylesheet_resolves(): void {
		$this->skipWithoutMultisite();

		$blog_id = self::factory()->blog->create( [ 'path' => '/preview-test/' ] );

		switch_to_blog( $blog_id ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- The database context is exactly what this asserts on: content_url() and the uploads directory of a subsite.

		$this->make_fixture_dir();

		$href          = $this->fixture( 'subsite.css', '.subsite{color:blue}' );
		$site_url_path = (string) wp_parse_url( site_url(), PHP_URL_PATH );

		$tag = Preview_Styles::inline_tag( $this->link_tag( $href ), 'subsite', $href, 'all' );

		$this->remove_fixture_dir();

		restore_current_blog();

		$this->assertStringContainsString( '.subsite{color:blue}', $tag );
		$this->assertStringNotContainsString( '<link', $tag );

		// Guard the premise: on a subdomain install site_url() has no path to diverge on.
		if ( ! is_subdomain_install() ) {
			$this->assertNotSame( '', $site_url_path );
		}
	}

	/**
	 * An asset root on another host still resolves.
	 *
	 * On a subdomain network wp-content is served from the network's host rather than this site's, so a resolver that compares an asset's host against home_url() would refuse every plugin and theme stylesheet the preview needs. Each root has to be matched on its own host.
	 */
	public function test_asset_root_on_another_host_resolves(): void {
		$content_host = 'https://assets.example.net/wp-content';

		add_filter( 'content_url', static fn (): string => $content_host );

		$relative = str_replace( WP_CONTENT_DIR, '', $this->fixture_dir() );

		$this->fixture( 'other-host.css', '.elsewhere{color:green}' );

		$href = $content_host . $relative . '/other-host.css';

		$tag = Preview_Styles::inline_tag( $this->link_tag( $href ), 'elsewhere', $href, 'all' );

		$this->assertNotSame(
			(string) wp_parse_url( home_url(), PHP_URL_HOST ),
			(string) wp_parse_url( $href, PHP_URL_HOST ),
			'The premise is that the asset is not on this site\'s host.'
		);
		$this->assertStringContainsString( '.elsewhere{color:green}', $tag );
		$this->assertStringNotContainsString( '<link', $tag );
	}

	/**
	 * Inlining is scoped to one render.
	 *
	 * Every other response this process serves has to keep linking its stylesheets.
	 */
	public function test_inlining_is_scoped_to_one_render(): void {
		Preview_Styles::activate();
		$this->assertNotFalse( has_filter( 'style_loader_tag', [ Preview_Styles::class, 'inline_tag' ] ) );

		Preview_Styles::deactivate();
		$this->assertFalse( has_filter( 'style_loader_tag', [ Preview_Styles::class, 'inline_tag' ] ) );
	}
}
