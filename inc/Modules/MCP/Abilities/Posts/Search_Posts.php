<?php
/**
 * Search Posts ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Posts;

/**
 * Class - Search_Posts
 */
class Search_Posts {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'publishio/search-posts',
			[
				'label'               => __( 'Search Posts and Pages', 'publishio' ),
				'category'            => \rtCamp\Publishio\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Searches posts and pages by keyword, post type, and status. Returns matching results with basic metadata.', 'publishio' ),
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'search'    => [
							'type'        => 'string',
							'description' => 'Search keyword (matches title and content).',
						],
						'post_type' => [
							'type'        => 'string',
							'default'     => 'post',
							'description' => 'Post type slug. Defaults to "post". Use "any" for all types.',
						],
						'status'    => [
							'type'        => 'array',
							'items'       => [
								'type' => 'string',
								'enum' => [ 'publish', 'draft', 'pending', 'private', 'trash', 'future' ],
							],
							'default'     => [ 'publish', 'draft' ],
							'description' => 'Post statuses to include. Defaults to publish + draft. "any" is not supported — pass explicit statuses.',
						],
						'per_page'  => [
							'type'        => 'integer',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 50,
							'description' => 'Max results to return. Defaults to 10.',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'required'   => [ 'post_id', 'title', 'post_type', 'status' ],
						'properties' => [
							'post_id'   => [
								'type'    => 'integer',
								'minimum' => 1,
							],
							'title'     => [ 'type' => 'string' ],
							'post_type' => [ 'type' => 'string' ],
							'status'    => [ 'type' => 'string' ],
							'date'      => [
								'type' => 'string',
							],
							'url'       => [
								'type'   => 'string',
								'format' => 'uri',
							],
							'edit_url'  => [
								'type'   => 'string',
								'format' => 'uri',
							],
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ): array {
					$args = [
						'post_type'      => sanitize_key( $input['post_type'] ?? 'post' ),
						'post_status'    => array_map( 'sanitize_key', (array) ( $input['status'] ?? [ 'publish', 'draft' ] ) ),
						'posts_per_page' => min( (int) ( $input['per_page'] ?? 10 ), 50 ),
						'orderby'        => 'date',
						'order'          => 'DESC',
						'perm'           => 'editable',
					];

					if ( ! empty( $input['search'] ) ) {
						$args['s'] = sanitize_text_field( $input['search'] );
					}

					$query   = new \WP_Query( $args );
					$results = [];

					foreach ( $query->posts as $post ) {
						if ( ! ( $post instanceof \WP_Post ) ) {
							continue;
						}
						$results[] = [
							'post_id'   => $post->ID,
							'title'     => $post->post_title,
							'post_type' => $post->post_type,
							'status'    => $post->post_status,
							'date'      => $post->post_date,
							'url'       => get_permalink( $post->ID ),
							'edit_url'  => get_edit_post_link( $post->ID, 'raw' ) ?: '',
						];
					}

					return $results;
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
