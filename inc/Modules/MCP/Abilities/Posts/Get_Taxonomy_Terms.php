<?php
/**
 * Get Taxonomy Terms ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Posts;

/**
 * Class - Get_Taxonomy_Terms
 */
class Get_Taxonomy_Terms {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'publishio/get-taxonomy-terms',
			[
				'label'               => __( 'List Taxonomy Terms', 'publishio' ),
				'category'            => \rtCamp\Publishio\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Returns available terms for a taxonomy (e.g. "category", "post_tag"). Use this to discover valid categories or tags before assigning them to a post.', 'publishio' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'taxonomy' ],
					'properties'           => [
						'taxonomy' => [
							'type'        => 'string',
							'description' => 'Taxonomy slug, e.g. "category" or "post_tag".',
						],
						'search'   => [
							'type'        => 'string',
							'description' => 'Optional search string to filter terms by name.',
						],
						'per_page' => [
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 100,
							'default'     => 50,
							'description' => 'Maximum number of terms to return. Defaults to 50.',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'taxonomy', 'terms' ],
					'properties' => [
						'taxonomy' => [ 'type' => 'string' ],
						'terms'    => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'required'   => [ 'term_id', 'name', 'slug', 'count' ],
								'properties' => [
									'term_id'     => [ 'type' => 'integer' ],
									'name'        => [ 'type' => 'string' ],
									'slug'        => [ 'type' => 'string' ],
									'count'       => [ 'type' => 'integer' ],
									'description' => [ 'type' => 'string' ],
									'parent_id'   => [ 'type' => 'integer' ],
								],
							],
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ) {
					$taxonomy = sanitize_key( $input['taxonomy'] ?? '' );

					if ( ! taxonomy_exists( $taxonomy ) ) {
						return new \WP_Error( 'invalid_taxonomy', __( 'Taxonomy does not exist.', 'publishio' ) );
					}

					$args = [
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
						'number'     => (int) ( $input['per_page'] ?? 50 ),
						'orderby'    => 'count',
						'order'      => 'DESC',
					];

					if ( ! empty( $input['search'] ) ) {
						$args['search'] = sanitize_text_field( $input['search'] );
					}

					$terms = get_terms( $args );

					if ( is_wp_error( $terms ) ) {
						return $terms;
					}

					$result = [];
					foreach ( $terms as $term ) {
						$result[] = [
							'term_id'     => $term->term_id,
							'name'        => $term->name,
							'slug'        => $term->slug,
							'count'       => $term->count,
							'description' => $term->description,
							'parent_id'   => $term->parent,
						];
					}

					return [
						'taxonomy' => $taxonomy,
						'terms'    => $result,
					];
				},
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'mcp'          => [
						'public' => true,
					],
				],
			]
		);
	}
}
