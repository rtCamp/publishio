<?php
/**
 * Answers discovery for the subdirectory sites under this one.
 *
 * Responds to: /.well-known/oauth-authorization-server/{site path}
 * Also:        /.well-known/oauth-protected-resource/{site path}/wp-json/mcp/publishio
 *
 * @package rtCamp\Publishio\Modules\MCP\OAuth\Well_Known
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\OAuth\Well_Known;

use rtCamp\Publishio\Framework\Contracts\Interfaces\Registrable;
use rtCamp\Publishio\Modules\MCP\OAuth\Config;

/**
 * Class - Path_Discovery
 *
 * Clients put the well-known segment between the host and the path of the issuer or resource URL (RFC 8414, RFC 9728). For a site at `example.com/site2/` that address is at the host root, which WordPress hands to the site at `/`, so that site answers for the sites under it.
 *
 * It matches the request path itself instead of adding rewrite rules, so it works without a rewrite flush.
 */
final class Path_Discovery implements Registrable {
	use Json_Response;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		if ( ! is_multisite() ) {
			return;
		}

		add_action( 'parse_request', [ $this, 'handle_request' ] );
	}

	/**
	 * Send the document if the request is for a site under this one.
	 *
	 * @param \WP $wp The WordPress environment instance.
	 */
	public function handle_request( \WP $wp ): void {
		$document = self::document_for( $wp->request );

		if ( null !== $document ) {
			$this->send_json_response( $document );
		}
	}

	/**
	 * The metadata document a well-known request at this site's root asks for on behalf of a site under it.
	 *
	 * Null unless the request matches that site's issuer or resource URL exactly, and Publishio runs there.
	 *
	 * @param string $request Request path relative to the site, without slashes at either end, as in WP::$request.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function document_for( string $request ): ?array {
		if ( ! str_starts_with( $request, '.well-known/oauth-' ) || ! preg_match( '#^\.well-known/(oauth-authorization-server|oauth-protected-resource)(/.+)$#D', $request, $matches ) ) {
			return null;
		}

		[ , $name, $path ] = $matches;
		$current           = get_site();

		if ( null === $current || '/' !== $current->path ) {
			return null;
		}

		// One path segment, as core uses to find a site on a network at the root.
		$site = get_site_by_path( $current->domain, $path . '/', 1 );

		if ( ! $site instanceof \WP_Site || (int) $site->blog_id === (int) $current->blog_id || ! self::is_available( $site ) || ! self::is_active_on( $site ) ) {
			return null;
		}

		$home_url = home_url();
		$server   = 'oauth-authorization-server' === $name;

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- Only that site's options are read, to build its URLs; its plugins and theme aren't needed.
		switch_to_blog( (int) $site->blog_id );

		try {
			$url      = $server ? Config::get_issuer_url() : Config::get_mcp_resource_claim();
			$document = $server ? Auth_Server_Metadata::get_metadata() : Protected_Resource::get_metadata();
		} finally {
			restore_current_blog();
		}

		if ( self::authority( $url ) !== self::authority( $home_url ) || wp_parse_url( $url, PHP_URL_PATH ) !== $path ) {
			return null;
		}

		return $document;
	}

	/**
	 * The site at the root of the current site's domain: the one that answers discovery for the current site. Usually the network's main site.
	 */
	public static function root_site(): ?\WP_Site {
		$current = get_site();
		$root    = null === $current ? false : get_site_by_path( $current->domain, '/', 1 );

		return $root instanceof \WP_Site ? $root : null;
	}

	/**
	 * Whether Publishio runs on a site: network-activated, or activated on that site.
	 *
	 * @param \WP_Site $site The site.
	 */
	public static function is_active_on( \WP_Site $site ): bool {
		$plugin  = plugin_basename( PUBLISHIO_FILE );
		$network = (array) get_network_option( (int) $site->network_id, 'active_sitewide_plugins', [] );
		$active  = isset( $network[ $plugin ] ) || in_array( $plugin, (array) get_blog_option( (int) $site->blog_id, 'active_plugins', [] ), true );

		/**
		 * Filters whether Publishio runs on a site of the network, for installs that load it from code instead of activating it.
		 *
		 * @param bool $active  Whether Publishio is network-activated or activated on the site.
		 * @param int  $site_id The site's ID.
		 */
		return (bool) apply_filters( 'publishio_active_on_site', $active, (int) $site->blog_id );
	}

	/**
	 * Whether a site is open to visitors: not archived, deleted or marked as spam.
	 *
	 * @param \WP_Site $site The site.
	 */
	private static function is_available( \WP_Site $site ): bool {
		return '1' !== $site->archived && '1' !== $site->deleted && '1' !== $site->spam;
	}

	/**
	 * A URL's host and port.
	 *
	 * @param string $url The URL.
	 */
	private static function authority( string $url ): string {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$port = wp_parse_url( $url, PHP_URL_PORT );

		return null === $port ? $host : $host . ':' . $port;
	}
}
