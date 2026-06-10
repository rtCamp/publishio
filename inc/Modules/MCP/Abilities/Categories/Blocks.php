<?php
/**
 * Blocks ability category.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Categories
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Categories;

/**
 * Class - Blocks
 */
class Blocks {
	public const SLUG = 'publishio-blocks';

	/**
	 * Register the category.
	 */
	public function register(): void {
		wp_register_ability_category(
			self::SLUG,
			[
				'label'       => __( 'Blocks', 'publishio' ),
				'description' => __( 'Abilities to discover and inspect registered custom blocks.', 'publishio' ),
			]
		);
	}
}
