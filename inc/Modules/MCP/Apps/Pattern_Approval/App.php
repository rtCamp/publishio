<?php
/**
 * Pattern Approval MCP App resource.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Apps\Pattern_Approval
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Apps\Pattern_Approval;

use rtCamp\Publish_With_AI\Modules\MCP\Apps\McpAppResource;

/**
 * Class - App
 */
class App extends McpAppResource {
	public const URI = 'ui://rtpwai/pattern-approval';

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
		return 'Pattern Approval';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return 'Shows a rendered block-pattern preview and collects user approval before inserting it into a page.';
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
		$build_file = RTCAMP_PUBLISH_WITH_AI_PATH . 'build-apps/pattern-approval.html';

		if ( ! is_readable( $build_file ) ) {
			return '<html><body style="font-family:sans-serif;padding:1rem">Run <code>npm run build</code> to generate the Pattern Approval app.</body></html>';
		}

		$plugin_url = RTCAMP_PUBLISH_WITH_AI_URL . 'build-apps';

		$site_data = (string) wp_json_encode(
			[
				'siteUrl'  => home_url(),
				'siteName' => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			]
		);

		return strtr(
			(string) file_get_contents( $build_file ),
			[
				'%%PLUGIN_URL%%' => $plugin_url,
				'%%SITE_DATA%%'  => $site_data,
			]
		);
	}
}
