<?php
/**
 * Blocks ability category.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories;

/**
 * Class - Blocks
 */
class Blocks {
	public const SLUG = 'pwai-blocks';

	/**
	 * Register the category.
	 */
	public function register(): void {
		wp_register_ability_category(
			self::SLUG,
			[
				'label'       => __( 'Blocks', 'publish-with-ai' ),
				'description' => __( 'Abilities to discover and inspect registered custom blocks.', 'publish-with-ai' ),
			]
		);
	}
}
