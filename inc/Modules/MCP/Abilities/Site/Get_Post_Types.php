<?php
/**
 * Get post types ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Site
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Site;

use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Writer;

/**
 * Class - Get_Post_Types
 */
final class Get_Post_Types extends Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'get-post-types';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Get Post Types', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Lists the post types this user can edit, such as posts, pages and products: whether each uses the block editor (the block tools work only on those), what it supports (featured image, excerpt, parent), its taxonomies, and whether this user can create one.', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema(): array {
		$strings = [
			'type'  => 'array',
			'items' => [ 'type' => 'string' ],
		];

		return [
			'type'       => 'object',
			'required'   => [ 'post_types' ],
			'properties' => [
				'post_types' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'required'   => [ 'slug', 'label', 'singular', 'block_editor', 'hierarchical', 'supports', 'taxonomies', 'can_create' ],
						'properties' => [
							'slug'         => [ 'type' => 'string' ],
							'label'        => [ 'type' => 'string' ],
							'singular'     => [ 'type' => 'string' ],
							'block_editor' => [ 'type' => 'boolean' ],
							'hierarchical' => [ 'type' => 'boolean' ],
							'supports'     => $strings,
							'taxonomies'   => $strings,
							'can_create'   => [ 'type' => 'boolean' ],
						],
					],
				],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function annotations(): array {
		return [
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute( array $input ): array { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter -- No input.
		$types = [];

		foreach ( get_post_types( [ 'show_ui' => true ], 'objects' ) as $type ) {
			if ( ! Post_Writer::is_supported_type( $type->name ) || ! current_user_can( $type->cap->edit_posts ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- The post type's own capability.
				continue;
			}

			$taxonomies = array_values(
				array_filter(
					get_object_taxonomies( $type->name ),
					static fn ( string $taxonomy ): bool => get_taxonomy( $taxonomy ) instanceof \WP_Taxonomy && get_taxonomy( $taxonomy )->show_ui
				)
			);

			$types[] = [
				'slug'         => $type->name,
				'label'        => $type->labels->name,
				'singular'     => $type->labels->singular_name,
				'block_editor' => Post_Writer::is_editable_type( $type->name ),
				'hierarchical' => (bool) $type->hierarchical,
				'supports'     => array_keys( get_all_post_type_supports( $type->name ) ),
				'taxonomies'   => $taxonomies,
				'can_create'   => current_user_can( $type->cap->create_posts ), // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- The post type's own capability.
			];
		}

		return [ 'post_types' => $types ];
	}
}
