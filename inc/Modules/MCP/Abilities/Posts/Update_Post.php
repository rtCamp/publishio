<?php
/**
 * Update Post ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts;

/**
 * Class - Update_Post
 */
class Update_Post {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'pwai/update-post',
			[
				'label'               => __( 'Update Post or Page Metadata', 'rtcamp-publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Updates metadata of a post or page — title, slug, excerpt, parent, and page template. Only provided fields are changed.', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'post_id' ],
					'properties'           => [
						'post_id'   => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'ID of the post or page.',
						],
						'title'     => [
							'type'        => 'string',
							'description' => 'New title.',
						],
						'slug'      => [
							'type'        => 'string',
							'description' => 'New URL slug.',
						],
						'excerpt'   => [
							'type'        => 'string',
							'description' => 'New excerpt.',
						],
						'parent_id' => [
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Parent post/page ID (for hierarchical types). Use 0 to remove parent.',
						],
						'template'  => [
							'type'        => 'string',
							'description' => 'Page template filename (e.g. "template-full-width.php"). Use empty string to reset to default.',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'success', 'post_id' ],
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'post_id' => [
							'type'    => 'integer',
							'minimum' => 1,
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

					$update = [ 'ID' => $post_id ];

					if ( isset( $input['title'] ) ) {
						$update['post_title'] = sanitize_text_field( $input['title'] );
					}

					if ( isset( $input['slug'] ) ) {
						$update['post_name'] = sanitize_title( $input['slug'] );
					}

					if ( isset( $input['excerpt'] ) ) {
						$update['post_excerpt'] = sanitize_textarea_field( $input['excerpt'] );
					}

					if ( isset( $input['parent_id'] ) ) {
						$update['post_parent'] = (int) $input['parent_id'];
					}

					$result = wp_update_post( $update, true );

					if ( is_wp_error( $result ) ) {
						return $result;
					}

					if ( isset( $input['template'] ) ) {
						update_post_meta( $post_id, '_wp_page_template', sanitize_text_field( $input['template'] ) );
					}

					return [
						'success' => true,
						'post_id' => $post_id,
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
