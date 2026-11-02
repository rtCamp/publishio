<?php
/**
 * Content Guide resource: the Publishio content guide, from the skill file.
 *
 * @package rtCamp\Publishio\Modules\MCP\Resources\Content_Guide
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Resources\Content_Guide;

use WP\MCP\Domain\Resources\McpResource;

/**
 * Class - Content_Guide
 *
 * One file, skills/publishio/SKILL.md, feeds this resource, the get-content-guide tool and the guide page's download, so they always match the installed version.
 */
class Content_Guide {
	public const FILE = 'skills/publishio/SKILL.md';

	/**
	 * The skill file as it ships, frontmatter included.
	 */
	public static function file(): string {
		$path = PUBLISHIO_PATH . self::FILE;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- A file in the plugin.
		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/**
	 * The guide without the skill's frontmatter.
	 */
	public static function guide(): string {
		return ltrim( (string) preg_replace( '/^---\R.*?\R---\R/s', '', self::file(), 1 ) );
	}

	/**
	 * Add the resource to the MCP server config.
	 *
	 * @param array<string, mixed> $config MCP server config.
	 * @return array<string, mixed>
	 */
	public function add_resource( array $config ): array {
		$resource = McpResource::fromArray(
			[
				'uri'         => 'wordpress://publishio/content-generation-guide',
				'name'        => 'publishio/content-generation-guide',
				'title'       => 'Publishio content guide',
				'description' => 'How to plan, build and finish content on this site with Publishio. Read it before creating or editing content.',
				'mimeType'    => 'text/markdown',
				'handler'     => static fn () => self::file(),
				'permission'  => static fn () => current_user_can( 'edit_posts' ),
			]
		);

		if ( ! is_wp_error( $resource ) ) {
			$config['resources'][] = $resource;
		}

		return $config;
	}
}
