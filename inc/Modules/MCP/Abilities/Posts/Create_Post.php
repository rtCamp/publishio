<?php
/**
 * Create Post ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Posts;

/**
 * Class - Create_Post
 */
class Create_Post {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'publishio/create-post',
			[
				'label'               => __( 'Create Empty Post or Page', 'publishio' ),
				'category'            => \rtCamp\Publishio\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Creates a new draft post or page with no content. Pass post_type as "page" for pages. Returns the post ID so you can append patterns to it.', 'publishio' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'title' ],
					'properties'           => [
						'title'     => [
							'type'        => 'string',
							'description' => 'Post title.',
						],
						'post_type' => [
							'type'        => 'string',
							'default'     => 'post',
							'description' => 'Post type slug. Defaults to "post".',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'post_id' ],
					'properties' => [
						'post_id'  => [
							'type'    => 'integer',
							'minimum' => 1,
						],
						'edit_url' => [
							'type'   => 'string',
							'format' => 'uri',
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ) {
					$title     = sanitize_text_field( $input['title'] ?? '' );
					$post_type = sanitize_key( $input['post_type'] ?? 'post' );

					if ( ! $title ) {
						return new \WP_Error( 'missing_title', __( 'Post title is required.', 'publishio' ) );
					}

					if ( ! post_type_exists( $post_type ) ) {
						return new \WP_Error( 'invalid_post_type', __( 'Invalid post type.', 'publishio' ) );
					}

					$post_type_obj = get_post_type_object( $post_type );
					if ( ! $post_type_obj || ! current_user_can( $post_type_obj->cap->create_posts ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined
						return new \WP_Error( 'forbidden', __( 'You do not have permission to create this post type.', 'publishio' ) );
					}

					$post_id = wp_insert_post(
						[
							'post_title'   => $title,
							'post_content' => '',
							'post_status'  => 'draft',
							'post_type'    => $post_type,
						],
						true
					);

					if ( is_wp_error( $post_id ) ) {
						return $post_id;
					}

					return [
						'post_id'  => $post_id,
						'edit_url' => get_edit_post_link( $post_id, 'raw' ) ?: '',
					];
				},
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					],
					'mcp'          => [
						'public' => true,
					],
				],
			]
		);
	}
}
