<?php
/**
 * Patterns ability category.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Categories
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Categories;

/**
 * Class - Patterns
 */
class Patterns {
	public const SLUG = 'publishio-patterns';

	/**
	 * Register the category.
	 */
	public function register(): void {
		wp_register_ability_category(
			self::SLUG,
			[
				'label'       => __( 'Patterns', 'publishio' ),
				'description' => __( 'Abilities to discover and inspect registered block patterns.', 'publishio' ),
			]
		);
	}
}
