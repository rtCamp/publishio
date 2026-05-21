<?php
/**
 * Example Post Type.
 *
 * @package rtCamp\Publish_With_AI\Modules\Example
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Example;

use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_Post_Type;

/**
 * Class - Example_Post_Type
 */
final class Example_Post_Type extends Abstract_Post_Type {
	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'example';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_post_type(): void {
		register_post_type( // phpcs:ignore WordPress.NamingConventions.ValidPostTypeSlug.NotStringLiteral -- Defined above.
			self::get_slug(),
			array_merge(
				$this->default_args(),
				[
					'label'      => __( 'Examples', 'rtcamp-publish-with-ai' ),
					'labels'     => [
						'name'               => __( 'Examples', 'rtcamp-publish-with-ai' ),
						'singular_name'      => __( 'Example', 'rtcamp-publish-with-ai' ),
						'add_new'            => __( 'Add New Example', 'rtcamp-publish-with-ai' ),
						'add_new_item'       => __( 'Add New Example', 'rtcamp-publish-with-ai' ),
						'edit_item'          => __( 'Edit Example', 'rtcamp-publish-with-ai' ),
						'new_item'           => __( 'New Example', 'rtcamp-publish-with-ai' ),
						'view_item'          => __( 'View Example', 'rtcamp-publish-with-ai' ),
						'search_items'       => __( 'Search Examples', 'rtcamp-publish-with-ai' ),
						'not_found'          => __( 'No examples found.', 'rtcamp-publish-with-ai' ),
						'not_found_in_trash' => __( 'No examples found in Trash.', 'rtcamp-publish-with-ai' ),
					],
					'supports'   => array_merge( $this->default_args()['supports'], [ 'custom-fields' ] ),
					'taxonomies' => [ Example_Taxonomy::get_slug() ],
				]
			)
		);
	}
}
