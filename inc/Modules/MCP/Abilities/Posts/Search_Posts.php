<?php
/**
 * Search Posts ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Posts;

use rtCamp\Publishio\Modules\MCP\Abilities\Content\Post_Details;
use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Writer;

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
							'description' => 'Post type slug. Defaults to "post". Use "any" for every type get-post-types lists.',
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
							'url'       => Schema::post_url(),
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
					$post_type = sanitize_key( $input['post_type'] ?? 'post' );
					$per_page  = min( (int) ( $input['per_page'] ?? 10 ), 50 );
					$types     = 'any' === $post_type ? wp_list_pluck( Post_Writer::editable_types(), 'name' ) : array_filter( [ $post_type ], [ Post_Writer::class, 'is_supported_type' ] );
					$args      = [
						'post_status'            => array_map( 'sanitize_key', (array) ( $input['status'] ?? [ 'publish', 'draft' ] ) ),
						'posts_per_page'         => $per_page,
						'orderby'                => [
							'date' => 'DESC',
							'ID'   => 'DESC',
						],
						'perm'                   => 'editable',
						'ignore_sticky_posts'    => true,
						'no_found_rows'          => true,
						'update_post_meta_cache' => false,
					];

					if ( ! empty( $input['search'] ) ) {
						$args['s'] = sanitize_text_field( $input['search'] );
					}

					// One query per type: with several types, WP_Query checks a capability no role has and returns only the user's own posts.
					$posts = [];

					foreach ( $types as $type ) {
						$posts = array_merge( $posts, array_filter( ( new \WP_Query( [ 'post_type' => $type ] + $args ) )->posts, static fn ( $post ): bool => $post instanceof \WP_Post ) );
					}

					usort(
						$posts,
						static fn ( \WP_Post $a, \WP_Post $b ): int => [ $b->post_date, $b->ID ] <=> [ $a->post_date, $a->ID ]
					);

					$results = [];

					foreach ( array_slice( $posts, 0, $per_page ) as $post ) {
						$results[] = [
							'post_id'   => $post->ID,
							'title'     => $post->post_title,
							'post_type' => $post->post_type,
							'status'    => $post->post_status,
							'date'      => $post->post_date,
							'url'       => Post_Details::url( $post ),
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
