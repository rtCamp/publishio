<?php
/**
 * Set Post Terms ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts;

/**
 * Class - Set_Post_Terms
 */
class Set_Post_Terms {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'pwai/set-post-terms',
			[
				'label'               => __( 'Set Post Categories or Tags', 'publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Assigns taxonomy terms (categories, tags, or custom taxonomies) to a post, replacing any existing terms for that taxonomy. Pass term slugs or IDs. Use pwai/get-taxonomy-terms first to discover valid values.', 'publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'post_id', 'taxonomy', 'terms' ],
					'properties'           => [
						'post_id'  => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'ID of the post.',
						],
						'taxonomy' => [
							'type'        => 'string',
							'description' => 'Taxonomy slug, e.g. "category" or "post_tag".',
						],
						'terms'    => [
							'type'        => 'array',
							'description' => 'Term slugs or integer IDs to assign. Pass an empty array to remove all terms.',
							'items'       => [
								'oneOf' => [
									[ 'type' => 'string' ],
									[
										'type'    => 'integer',
										'minimum' => 1,
									],
								],
							],
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'success', 'post_id', 'taxonomy', 'assigned_terms' ],
					'properties' => [
						'success'        => [ 'type' => 'boolean' ],
						'post_id'        => [ 'type' => 'integer' ],
						'taxonomy'       => [ 'type' => 'string' ],
						'assigned_terms' => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'term_id' => [ 'type' => 'integer' ],
									'name'    => [ 'type' => 'string' ],
									'slug'    => [ 'type' => 'string' ],
								],
							],
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ) {
					$post_id  = (int) ( $input['post_id'] ?? 0 );
					$taxonomy = sanitize_key( $input['taxonomy'] ?? '' );
					$terms    = $input['terms'] ?? [];

					$post = get_post( $post_id );
					if ( ! $post ) {
						return new \WP_Error( 'invalid_post', __( 'Post not found.', 'publish-with-ai' ) );
					}

					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this post.', 'publish-with-ai' ) );
					}

					if ( ! taxonomy_exists( $taxonomy ) ) {
						return new \WP_Error( 'invalid_taxonomy', __( 'Taxonomy does not exist.', 'publish-with-ai' ) );
					}

					if ( ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
						return new \WP_Error(
							'taxonomy_not_registered',
							sprintf(
								/* translators: 1: taxonomy slug, 2: post type slug */
								__( 'Taxonomy "%1$s" is not registered for post type "%2$s".', 'publish-with-ai' ),
								$taxonomy,
								$post->post_type
							)
						);
					}

					// Resolve slugs to term IDs.
					$term_ids = [];
					foreach ( $terms as $term ) {
						if ( is_int( $term ) || ctype_digit( (string) $term ) ) {
							$term_ids[] = (int) $term;
						} else {
							$term_obj = get_term_by( 'slug', sanitize_title( $term ), $taxonomy );
							if ( ! $term_obj ) {
								return new \WP_Error(
									'invalid_term',
									sprintf(
										/* translators: 1: term slug, 2: taxonomy slug */
										__( 'Term "%1$s" not found in taxonomy "%2$s".', 'publish-with-ai' ),
										$term,
										$taxonomy
									)
								);
							}
							$term_ids[] = $term_obj->term_id;
						}
					}

					$result = wp_set_post_terms( $post_id, $term_ids, $taxonomy );

					if ( is_wp_error( $result ) ) {
						return $result;
					}

					$assigned       = get_the_terms( $post_id, $taxonomy ) ?: [];
					$assigned_terms = array_map(
						static function ( $term ) {
							return [
								'term_id' => $term->term_id,
								'name'    => $term->name,
								'slug'    => $term->slug,
							];
						},
						is_array( $assigned ) ? $assigned : []
					);

					return [
						'success'        => true,
						'post_id'        => $post_id,
						'taxonomy'       => $taxonomy,
						'assigned_terms' => $assigned_terms,
					];
				},
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'    => false,
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
