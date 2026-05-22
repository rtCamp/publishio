<?php
/**
 * Posts ability category.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories;

/**
 * Class - Posts
 */
class Posts {
	public const SLUG = 'pwai-content';

	/**
	 * Register the category.
	 */
	public function register(): void {
		wp_register_ability_category(
			self::SLUG,
			[
				'label'       => __( 'Content', 'rtcamp-publish-with-ai' ),
				'description' => __( 'Manage posts and pages — create, edit blocks, and assemble content.', 'rtcamp-publish-with-ai' ),
			]
		);
	}
}
