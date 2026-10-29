<?php
/**
 * Publishio Preview MCP App resource.
 *
 * @package rtCamp\Publishio\Modules\MCP\Apps\Preview
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Apps\Preview;

use rtCamp\Publishio\Modules\MCP\Apps\McpAppResource;

/**
 * Class - App
 */
class App extends McpAppResource {
	public const URI = 'ui://publishio/preview';

	/**
	 * {@inheritDoc}
	 */
	public function uri(): string {
		return self::URI;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function title(): string {
		return 'Publishio Preview';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return 'Shows what a Publishio tool changed: the inserted blocks, a saved pattern, or a whole page with its search and social details.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function template_dir(): string {
		return __DIR__;
	}

	/**
	 * {@inheritDoc}
	 *
	 * The pattern preview iframe runs wp_head()/wp_footer() which load
	 * emoji SVGs and potentially theme fonts from these CDNs.
	 */
	protected function resource_domains(): array {
		return [
			'https://s.w.org', // WordPress emoji SVGs (twemoji).
			'https://fonts.googleapis.com',
			'https://fonts.gstatic.com',
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * Reads the webpack-built index.html and substitutes the %%PLUGIN_URL%%
	 * placeholder with the real asset base URL so <link>/<script> tags resolve.
	 */
	protected function build_html(): string {
		$build_file = PUBLISHIO_PATH . 'build-apps/preview.html';

		if ( ! is_readable( $build_file ) ) {
			return '<html><body style="font-family:sans-serif;padding:1rem">Run <code>npm run build</code> to generate the Publishio Preview app.</body></html>';
		}

		$plugin_url = PUBLISHIO_URL . 'build-apps';

		$site_data = (string) wp_json_encode(
			[
				'siteUrl'  => home_url(),
				'siteName' => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			]
		);

		return strtr(
			(string) file_get_contents( $build_file ), // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
			[
				'%%PLUGIN_URL%%' => $plugin_url,
				'%%SITE_DATA%%'  => $site_data,
			]
		);
	}
}
