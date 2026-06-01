<?php
/**
 * Get Post Terms ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts;

/**
 * Class - Get_Post_Terms
 */
class Get_Post_Terms {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'rtpwai/get-post-terms',
			[
				'label'               => __( 'Get Post Terms', 'rtcamp-publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Returns the taxonomy terms currently assigned to a post (e.g. its categories and tags).', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'post_id' ],
					'properties'           => [
						'post_id'  => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'ID of the post.',
						],
						'taxonomy' => [
							'type'        => 'string',
							'description' => 'Taxonomy slug to filter by (e.g. "category", "post_tag"). Omit to return all taxonomies.',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'post_id', 'taxonomies' ],
					'properties' => [
						'post_id'    => [ 'type' => 'integer' ],
						'taxonomies' => [
							'type'                 => 'object',
							'additionalProperties' => [
								'type'  => 'array',
								'items' => [
									'type'       => 'object',
									'required'   => [ 'term_id', 'name', 'slug' ],
									'properties' => [
										'term_id' => [ 'type' => 'integer' ],
										'name'    => [ 'type' => 'string' ],
										'slug'    => [ 'type' => 'string' ],
									],
								],
							],
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ) {
					$post_id = (int) ( $input['post_id'] ?? 0 );
					$post    = get_post( $post_id );

					if ( ! $post ) {
						return new \WP_Error( 'invalid_post', __( 'Post not found.', 'rtcamp-publish-with-ai' ) );
					}

					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this post.', 'rtcamp-publish-with-ai' ) );
					}

					if ( ! empty( $input['taxonomy'] ) ) {
						$taxonomies = [ sanitize_key( $input['taxonomy'] ) ];
					} else {
						$taxonomies = get_object_taxonomies( $post->post_type );
					}

					$result = [];
					foreach ( $taxonomies as $taxonomy ) {
						if ( ! taxonomy_exists( $taxonomy ) ) {
							continue;
						}
						$terms = get_the_terms( $post_id, $taxonomy );
						if ( ! $terms || is_wp_error( $terms ) ) {
							$result[ $taxonomy ] = [];
							continue;
						}
						$result[ $taxonomy ] = array_map(
							static function ( $term ) {
								return [
									'term_id' => $term->term_id,
									'name'    => $term->name,
									'slug'    => $term->slug,
								];
							},
							$terms
						);
					}

					return [
						'post_id'    => $post_id,
						'taxonomies' => $result,
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
