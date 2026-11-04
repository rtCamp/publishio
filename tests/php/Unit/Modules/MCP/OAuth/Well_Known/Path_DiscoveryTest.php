<?php
/**
 * Tests for discovery answered by the site at the root for the subdirectory sites under it.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Well_Known
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Well_Known;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use rtCamp\Publishio\Modules\MCP\OAuth\Well_Known\Auth_Server_Metadata;
use rtCamp\Publishio\Modules\MCP\OAuth\Well_Known\Path_Discovery;
use rtCamp\Publishio\Modules\MCP\OAuth\Well_Known\Protected_Resource;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Path_DiscoveryTest
 */
#[CoversClass( Path_Discovery::class )]
#[Group( 'ms-required' )]
class Path_DiscoveryTest extends TestCase {
	/**
	 * A subdirectory site running Publishio.
	 */
	private int $site_id;

	/**
	 * The main site's URL.
	 */
	private string $main_url;

	/**
	 * Create the site.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->skipWithoutMultisite();

		$this->set_permalink_structure( '/%postname%/' );
		$this->site_id  = $this->create_site( '/site2/' );
		$this->main_url = untrailingslashit( home_url() );
	}

	/**
	 * The authorization server document is the subsite's own.
	 */
	public function test_serves_subsite_authorization_server_metadata(): void {
		$document = Path_Discovery::document_for( '.well-known/oauth-authorization-server/site2' );

		$this->assertIsArray( $document );
		$this->assertSame( $this->main_url . '/site2', $document['issuer'] );
		$this->assertSame( $this->main_url . '/site2/publishio-oauth/authorize', $document['authorization_endpoint'] );
		$this->assertSame( $this->main_url . '/site2/wp-json/publishio-oauth/v1/token', $document['token_endpoint'] );
	}

	/**
	 * The protected resource document is the subsite's own.
	 */
	public function test_serves_subsite_protected_resource_metadata(): void {
		$document = Path_Discovery::document_for( '.well-known/oauth-protected-resource/site2/wp-json/mcp/publishio' );

		$this->assertIsArray( $document );
		$this->assertSame( $this->main_url . '/site2/wp-json/mcp/publishio', $document['resource'] );
		$this->assertSame( [ $this->main_url . '/site2' ], $document['authorization_servers'] );
	}

	/**
	 * Requests that aren't exactly a subsite's issuer or resource are left alone.
	 *
	 * @param string $request Request path.
	 */
	#[DataProvider( 'other_requests' )]
	public function test_ignores_requests_that_do_not_match_a_site( string $request ): void {
		$this->assertNull( Path_Discovery::document_for( $request ) );
	}

	/**
	 * Request paths that must fall through to WordPress.
	 *
	 * @return array<string, array{string}>
	 */
	public static function other_requests(): array {
		return [
			'main site server document'   => [ '.well-known/oauth-authorization-server' ],
			'main site resource document' => [ '.well-known/oauth-protected-resource/wp-json/mcp/publishio' ],
			'no such site'                => [ '.well-known/oauth-authorization-server/nope' ],
			'extra segment'               => [ '.well-known/oauth-authorization-server/site2/extra' ],
			'other route'                 => [ '.well-known/oauth-protected-resource/site2/wp-json/other' ],
			'resource at the issuer'      => [ '.well-known/oauth-protected-resource/site2' ],
			'issuer at the resource'      => [ '.well-known/oauth-authorization-server/site2/wp-json/mcp/publishio' ],
			'different case'              => [ '.well-known/oauth-authorization-server/SITE2' ],
			'percent-encoded'             => [ '.well-known/oauth-authorization-server/site%32' ],
			'OpenID Connect'              => [ '.well-known/openid-configuration/site2' ],
			'site path only'              => [ 'site2' ],
		];
	}

	/**
	 * Archived, deleted and spam sites get no documents.
	 *
	 * @param string $flag Site status field.
	 */
	#[DataProvider( 'closed_flags' )]
	public function test_ignores_closed_sites( string $flag ): void {
		update_blog_status( $this->site_id, $flag, '1' );

		$this->assertNull( Path_Discovery::document_for( '.well-known/oauth-authorization-server/site2' ) );
	}

	/**
	 * Site status fields that close a site to visitors.
	 *
	 * @return array<string, array{string}>
	 */
	public static function closed_flags(): array {
		return [
			'archived' => [ 'archived' ],
			'deleted'  => [ 'deleted' ],
			'spam'     => [ 'spam' ],
		];
	}

	/**
	 * A site that doesn't run Publishio gets no documents.
	 */
	public function test_ignores_site_without_publishio(): void {
		update_blog_option( $this->site_id, 'active_plugins', [] );

		$this->assertNull( Path_Discovery::document_for( '.well-known/oauth-authorization-server/site2' ) );
	}

	/**
	 * Network activation counts as running on every site.
	 */
	public function test_network_activation_counts_as_active(): void {
		update_blog_option( $this->site_id, 'active_plugins', [] );
		update_site_option( 'active_sitewide_plugins', [ plugin_basename( PUBLISHIO_FILE ) => time() ] );

		$this->assertIsArray( Path_Discovery::document_for( '.well-known/oauth-authorization-server/site2' ) );
	}

	/**
	 * A host that loads Publishio from code can mark a site as running it.
	 */
	public function test_filter_marks_site_active(): void {
		update_blog_option( $this->site_id, 'active_plugins', [] );
		add_filter( 'publishio_active_on_site', fn ( bool $active, int $site_id ): bool => $active || $this->site_id === $site_id, 10, 2 );

		$this->assertIsArray( Path_Discovery::document_for( '.well-known/oauth-authorization-server/site2' ) );
	}

	/**
	 * Only a site at the root of its domain answers for others.
	 */
	public function test_subdirectory_site_does_not_answer_for_others(): void {
		$this->create_site( '/site3/' );

		switch_to_blog( $this->site_id );
		$document = Path_Discovery::document_for( '.well-known/oauth-authorization-server/site3' );
		restore_current_blog();

		$this->assertNull( $document );
	}

	/**
	 * A site on its own domain answers its own discovery, so the main site doesn't answer for it.
	 */
	public function test_site_on_its_own_domain_is_left_alone(): void {
		$this->create_site( '/', 'site4.example.org' );

		$this->assertNull( Path_Discovery::document_for( '.well-known/oauth-authorization-server/site4' ) );
	}

	/**
	 * A subsite whose home URL is on another host isn't reached through this host.
	 */
	public function test_ignores_subsite_with_home_on_another_host(): void {
		update_blog_option( $this->site_id, 'home', 'http://elsewhere.example.org/site2' );

		$this->assertNull( Path_Discovery::document_for( '.well-known/oauth-authorization-server/site2' ) );
	}

	/**
	 * Building a subsite's document leaves the main site's untouched.
	 */
	public function test_main_site_documents_unchanged_afterwards(): void {
		Path_Discovery::document_for( '.well-known/oauth-authorization-server/site2' );
		Path_Discovery::document_for( '.well-known/oauth-protected-resource/site2/wp-json/mcp/publishio' );

		$this->assertFalse( ms_is_switched() );
		$this->assertSame( $this->main_url, Auth_Server_Metadata::get_metadata()['issuer'] );
		$this->assertSame( $this->main_url . '/wp-json/mcp/publishio', Protected_Resource::get_metadata()['resource'] );
	}

	/**
	 * The subsite's own permalinks decide its REST URLs, whichever /index.php/ setting the main site has. The main site's rewrite object is restored.
	 *
	 * @param string $main_structure Main site's permalink structure.
	 * @param string $site_structure Subsite's permalink structure.
	 * @param string $resource_path  The subsite's resource path.
	 */
	#[DataProvider( 'permalink_mixes' )]
	public function test_subsite_permalinks_decide_its_urls( string $main_structure, string $site_structure, string $resource_path ): void {
		global $wp_rewrite;

		$this->set_permalink_structure( $main_structure );
		update_blog_option( $this->site_id, 'permalink_structure', $site_structure );
		$main_rewrite = $wp_rewrite;

		$document = Path_Discovery::document_for( '.well-known/oauth-protected-resource' . $resource_path );

		$this->assertIsArray( $document );
		$this->assertSame( $this->main_url . $resource_path, $document['resource'] );
		$this->assertSame( $main_rewrite, $wp_rewrite );
		$this->assertSame( $main_structure, $wp_rewrite->permalink_structure );
	}

	/**
	 * A main site and subsite that differ on /index.php/ permalinks.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public static function permalink_mixes(): array {
		return [
			'main uses index.php'    => [ '/index.php/%postname%/', '/%postname%/', '/site2/wp-json/mcp/publishio' ],
			'subsite uses index.php' => [ '/%postname%/', '/index.php/%postname%/', '/site2/index.php/wp-json/mcp/publishio' ],
		];
	}

	/**
	 * A subsite's root site is the main site; a site on its own domain is its own root.
	 */
	public function test_root_site(): void {
		$domain_site = $this->create_site( '/', 'site4.example.org' );

		switch_to_blog( $this->site_id );
		$subdirectory_root = Path_Discovery::root_site();
		restore_current_blog();

		switch_to_blog( $domain_site );
		$domain_root = Path_Discovery::root_site();
		restore_current_blog();

		$this->assertSame( get_main_site_id(), (int) $subdirectory_root?->blog_id );
		$this->assertSame( $domain_site, (int) $domain_root?->blog_id );
	}

	/**
	 * Create a site with pretty permalinks and Publishio active.
	 *
	 * @param string $path   Site path.
	 * @param string $domain Site domain; the network's when empty.
	 */
	private function create_site( string $path, string $domain = '' ): int {
		$site_id = self::factory()->blog->create(
			[
				'domain' => '' === $domain ? get_network()->domain : $domain,
				'path'   => $path,
			]
		);

		update_blog_option( $site_id, 'active_plugins', [ plugin_basename( PUBLISHIO_FILE ) ] );
		update_blog_option( $site_id, 'permalink_structure', '/%postname%/' );

		return $site_id;
	}
}
