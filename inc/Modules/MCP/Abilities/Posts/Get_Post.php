<?php
/**
 * Get Post ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts;

/**
 * Class - Get_Post
 */
class Get_Post {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'rtpwai/get-post',
			[
				'label'               => __( 'Get Post or Page', 'rtcamp-publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Returns full details of a post or page including title, content (block markup), status, slug, excerpt, featured image, template, and parent.', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'post_id' ],
					'properties'           => [
						'post_id' => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'ID of the post or page.',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'post_id', 'title', 'slug', 'status', 'post_type', 'content', 'blocks' ],
					'properties' => [
						'post_id'        => [
							'type'    => 'integer',
							'minimum' => 1,
						],
						'title'          => [ 'type' => 'string' ],
						'slug'           => [ 'type' => 'string' ],
						'status'         => [
							'type' => 'string',
							'enum' => [ 'publish', 'draft', 'pending', 'private', 'trash', 'future' ],
						],
						'post_type'      => [ 'type' => 'string' ],
						'content'        => [
							'type'        => 'string',
							'description' => 'Raw block markup.',
						],
						'excerpt'        => [ 'type' => 'string' ],
						'date'           => [
							'type'   => 'string',
							'format' => 'date-time',
						],
						'modified'       => [
							'type'   => 'string',
							'format' => 'date-time',
						],
						'parent_id'      => [
							'type'    => 'integer',
							'minimum' => 0,
						],
						'template'       => [ 'type' => 'string' ],
						'featured_image' => [
							'type'       => [ 'object', 'null' ],
							'properties' => [
								'id'  => [
									'type'    => 'integer',
									'minimum' => 1,
								],
								'url' => [
									'type'   => 'string',
									'format' => 'uri',
								],
								'alt' => [ 'type' => 'string' ],
							],
						],
						'url'            => [
							'type'   => 'string',
							'format' => 'uri',
						],
						'edit_url'       => [
							'type'   => 'string',
							'format' => 'uri',
						],
						'blocks'         => [
							'type'        => 'array',
							'description' => 'Top-level block names in order.',
							'items'       => [
								'type'       => 'object',
								'required'   => [ 'index', 'block_name' ],
								'properties' => [
									'index'      => [
										'type'    => 'integer',
										'minimum' => 0,
									],
									'block_name' => [ 'type' => 'string' ],
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
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this post.', 'rtcamp-publish-with-ai' ) );
				}

					}

					$thumbnail_id = (int) get_post_thumbnail_id( $post_id );
					$featured     = null;
					if ( $thumbnail_id ) {
						$featured = [
							'id'  => $thumbnail_id,
							'url' => wp_get_attachment_url( $thumbnail_id ) ?: '',
							'alt' => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ?: '',
						];
					}

					$blocks     = parse_blocks( $post->post_content );
					$block_list = [];
					$index      = 0;
					foreach ( $blocks as $block ) {
						if ( empty( $block['blockName'] ) && empty( trim( $block['innerHTML'] ?? '' ) ) ) {
							continue;
						}
						$block_list[] = [
							'index'      => $index,
							'block_name' => $block['blockName'] ?? '(freeform)',
						];
						++$index;
					}

					return [
						'post_id'        => $post->ID,
						'title'          => $post->post_title,
						'slug'           => $post->post_name,
						'status'         => $post->post_status,
						'post_type'      => $post->post_type,
						'content'        => $post->post_content,
						'excerpt'        => $post->post_excerpt,
						'date'           => $post->post_date,
						'modified'       => $post->post_modified,
						'parent_id'      => (int) $post->post_parent,
						'template'       => get_page_template_slug( $post_id ) ?: '',
						'featured_image' => $featured,
						'url'            => get_permalink( $post_id ),
						'edit_url'       => get_edit_post_link( $post_id, 'raw' ) ?: '',
						'blocks'         => $block_list,
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
