<?php
/**
 * Content Guide resource — rules and workflows for generating WordPress content.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Resources\Content_Guide
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Resources\Content_Guide;

use WP\MCP\Domain\Resources\McpResource;

/**
 * Class - Content_Guide
 */
class Content_Guide {
	/**
	 * Add the resource to the MCP server config.
	 *
	 * @param array<string, mixed> $config MCP server config.
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
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				'handler'     => static fn () => file_get_contents( RTCAMP_PUBLISH_WITH_AI_PATH . 'skills/rt-publish-with-ai/SKILL.md' ),
				'permission'  => static fn () => current_user_can( 'edit_posts' ),
			]
		);

		if ( ! is_wp_error( $resource ) ) {
			$config['resources'][] = $resource;
		}

		return $config;
	}
}
