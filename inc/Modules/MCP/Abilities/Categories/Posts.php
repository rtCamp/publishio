<?php
/**
 * Posts ability category.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Categories
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Categories;

/**
 * Class - Posts
 */
class Posts {
	public const SLUG = 'publishio-content';

	/**
	 * Register the category.
	 */
	public function register(): void {
		wp_register_ability_category(
			self::SLUG,
			[
				'label'       => __( 'Content', 'publishio' ),
				'description' => __( 'Manage posts and pages — create, edit blocks, and assemble content.', 'publishio' ),
			]
		);
	}
}
