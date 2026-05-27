<?php
/**
 * Abstract base for MCP App resources (SEP-1865).
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Apps
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Apps;

use WP\MCP\Domain\Resources\McpResource;

/**
 * Abstract class - McpAppResource
 *
 * Subclasses declare a URI, a template directory, and descriptive strings.
 * build_html() inlines style.css and script.js from that directory into template.php.
 */
abstract class McpAppResource {
	/**
	 * The ui:// URI for this resource.
	 */
	abstract public function uri(): string;

	/**
	 * Human-readable title.
	 */
	abstract protected function title(): string;

	/**
	 * Short description shown in resource listings.
	 */
	abstract protected function description(): string;

	/**
	 * Absolute path to the directory that contains template.php, style.css, script.js.
	 */
	abstract protected function template_dir(): string;

	/**
	 * Register this resource into the MCP server config array.
	 *
	 * @param array<string, mixed> $config MCP server config.
	 * @return array<string, mixed>
	 */
	public function add_resource( array $config ): array {
		$uri      = $this->uri();
		$resource = McpResource::fromArray(
			[
				'uri'         => $uri,
				'name'        => (string) preg_replace( '#^ui://#', '', $uri ),
				'title'       => $this->title(),
				'description' => $this->description(),
				'mimeType'    => 'text/html;profile=mcp-app',
				'handler'     => fn () => [
					[
						'uri'      => $uri,
						'mimeType' => 'text/html;profile=mcp-app',
						'text'     => $this->build_html(),
					],
				],
				'permission'  => static fn () => current_user_can( 'edit_posts' ),
			]
		);

		if ( ! is_wp_error( $resource ) ) {
			$config['resources'][] = $resource;
		}

		return $config;
	}

	/**
	 * Render the app HTML by inlining style.css and script.js into template.php.
	 */
	protected function build_html(): string {
		$dir    = rtrim( $this->template_dir(), '/\\' );
		$style  = is_readable( "$dir/style.css" ) ? (string) file_get_contents( "$dir/style.css" ) : ''; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
		$script = is_readable( "$dir/script.js" ) ? (string) file_get_contents( "$dir/script.js" ) : ''; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable

		ob_start();
		require "$dir/template.php"; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath
		return (string) ob_get_clean();
	}
}
