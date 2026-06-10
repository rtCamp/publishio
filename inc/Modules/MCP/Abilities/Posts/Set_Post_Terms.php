<?php
/**
 * Set Post Terms ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Posts;

/**
 * Class - Set_Post_Terms
 */
class Set_Post_Terms {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'publishio/set-post-terms',
			[
				'label'               => __( 'Set Post Categories or Tags', 'publishio' ),
				'category'            => \rtCamp\Publishio\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Assigns taxonomy terms (categories, tags, or custom taxonomies) to a post, replacing any existing terms for that taxonomy — terms omitted from the list are removed. Pass term slugs or IDs, or an empty array to remove all terms. Requires permission to assign every term being added or removed; returns an error naming any term you are not allowed to change. Use publishio/get-taxonomy-terms first to discover valid values.', 'publishio' ),
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
						return new \WP_Error( 'invalid_post', __( 'Post not found.', 'publishio' ) );
					}

					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this post.', 'publishio' ) );
					}

					if ( ! taxonomy_exists( $taxonomy ) ) {
						return new \WP_Error( 'invalid_taxonomy', __( 'Taxonomy does not exist.', 'publishio' ) );
					}

					if ( ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
						return new \WP_Error(
							'taxonomy_not_registered',
							sprintf(
								/* translators: 1: taxonomy slug, 2: post type slug */
								__( 'Taxonomy "%1$s" is not registered for post type "%2$s".', 'publishio' ),
								$taxonomy,
								$post->post_type
							)
						);
					}

					$taxonomy_obj = get_taxonomy( $taxonomy );
					if ( ! $taxonomy_obj || ! current_user_can( $taxonomy_obj->cap->assign_terms ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined
						return new \WP_Error( 'forbidden', __( 'You do not have permission to assign terms in this taxonomy.', 'publishio' ) );
					}

					if ( ! is_array( $terms ) ) {
						return new \WP_Error( 'invalid_terms', __( 'Terms must be an array of slugs or IDs.', 'publishio' ) );
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
										__( 'Term "%1$s" not found in taxonomy "%2$s".', 'publishio' ),
										$term,
										$taxonomy
									)
								);
							}
							$term_ids[] = $term_obj->term_id;
						}
					}

					$current_ids = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
					$current_ids = is_wp_error( $current_ids ) ? [] : array_map( 'intval', $current_ids );
					$removed_ids = array_diff( $current_ids, $term_ids );
					$changed_ids = array_unique( array_merge( $term_ids, $removed_ids ) );

					$forbidden = [];
					foreach ( $changed_ids as $term_id ) {
						if ( ! current_user_can( 'assign_term', $term_id ) ) {
							$term        = get_term( $term_id, $taxonomy );
							$forbidden[] = $term instanceof \WP_Term ? sprintf( '%s (%d)', $term->slug, $term_id ) : (string) $term_id;
						}
					}

					if ( ! empty( $forbidden ) ) {
						return new \WP_Error(
							'forbidden',
							sprintf(
								/* translators: %s: comma-separated list of term slugs/IDs the user cannot assign or remove. */
								__( 'You do not have permission to assign or remove the following terms: %s', 'publishio' ),
								implode( ', ', $forbidden )
							)
						);
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
