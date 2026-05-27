<?php
/**
 * Preview ability category.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories;

/**
 * Class - Preview
 */
class Preview {
	public const SLUG = 'rtpwai-preview';

	/**
	 * Register the category.
	 */
	public function register(): void {
		wp_register_ability_category(
			self::SLUG,
			[
				'label'       => __( 'Preview', 'rtcamp-publish-with-ai' ),
				'description' => __( 'Capture screenshots of pages and patterns during publishing.', 'rtcamp-publish-with-ai' ),
			]
		);
	}
}
