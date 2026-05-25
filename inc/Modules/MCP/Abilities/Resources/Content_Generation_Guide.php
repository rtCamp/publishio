<?php
/**
 * Content Generation Guide resource.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Resources
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Resources;

use WP\MCP\Domain\Resources\McpResource;

/**
 * Class - Content_Generation_Guide
 */
class Content_Generation_Guide {
	/**
	 * Add the resource to the MCP server config.
	 *
	 * @param array<string, mixed> $config MCP server config.
	 *
	 * @return array<string, mixed>
	 */
	public function add_resource( array $config ): array {
		$resource = McpResource::fromArray(
			[
				'uri'         => 'wordpress://rtpwai/content-generation-guide',
				'name'        => 'rtpwai/content-generation-guide',
				'title'       => 'WordPress Content Generation Guide',
				'description' => 'Rules and workflows for generating WordPress content using patterns and incremental assembly. Read this before creating any post or page.',
				'mimeType'    => 'text/markdown',
				'handler'     => static fn () => file_get_contents( __DIR__ . '/content-generation-guide.md' ),
				'permission'  => static fn () => current_user_can( 'edit_posts' ),
			]
		);

		if ( ! is_wp_error( $resource ) ) {
			$config['resources'][] = $resource;
		}

		return $config;
	}
}
