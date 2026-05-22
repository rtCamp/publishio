<?php
/**
 * Patterns ability category.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories;

/**
 * Class - Patterns
 */
class Patterns {
	public const SLUG = 'rtcamp-publish-with-ai-patterns';

	/**
	 * Register the category.
	 */
	public function register(): void {
		wp_register_ability_category(
			self::SLUG,
			[
				'label'       => __( 'Patterns', 'rtcamp-publish-with-ai' ),
				'description' => __( 'Abilities to discover and inspect registered block patterns.', 'rtcamp-publish-with-ai' ),
			]
		);
	}
}
