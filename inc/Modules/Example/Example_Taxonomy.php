<?php
/**
 * Example Taxonomy.
 *
 * @package rtCamp\Publish_With_AI\Modules\Example
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Example;

use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_Taxonomy;

/**
 * Class - Example_Taxonomy
 */
final class Example_Taxonomy extends Abstract_Taxonomy {
	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'example-tax';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_object_types(): array {
		return [ Example_Post_Type::get_slug() ];
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			self::get_slug(),
			self::get_object_types(),
			array_merge(
				$this->default_args(),
				[
					'hierarchical' => true,
					'label'        => __( 'Example Categories', 'rtcamp-publish-with-ai' ),
					'labels'       => [
						'name'              => __( 'Example Categories', 'rtcamp-publish-with-ai' ),
						'singular_name'     => __( 'Example Category', 'rtcamp-publish-with-ai' ),
						'search_items'      => __( 'Search Example Categories', 'rtcamp-publish-with-ai' ),
						'all_items'         => __( 'All Example Categories', 'rtcamp-publish-with-ai' ),
						'parent_item'       => __( 'Parent Example Category', 'rtcamp-publish-with-ai' ),
						'parent_item_colon' => __( 'Parent Example Category:', 'rtcamp-publish-with-ai' ),
						'edit_item'         => __( 'Edit Example Category', 'rtcamp-publish-with-ai' ),
						'update_item'       => __( 'Update Example Category', 'rtcamp-publish-with-ai' ),
						'add_new_item'      => __( 'Add New Example Category', 'rtcamp-publish-with-ai' ),
						'new_item_name'     => __( 'New Example Category Name', 'rtcamp-publish-with-ai' ),
						'menu_name'         => __( 'Example Categories', 'rtcamp-publish-with-ai' ),
					],
				]
			)
		);
	}
}
