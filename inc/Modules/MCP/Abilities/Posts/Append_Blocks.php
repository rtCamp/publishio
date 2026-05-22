<?php
/**
 * Append Blocks ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts;

/**
 * Class - Append_Blocks
 */
class Append_Blocks {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'pwai/append-blocks',
			[
				'label'               => __( 'Append Blocks to Post', 'rtcamp-publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Appends raw block markup at the bottom of a post. Only works for posts (not pages).', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'post_id', 'markup' ],
					'properties'           => [
						'post_id' => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'ID of the post.',
						],
						'markup'  => [
							'type'        => 'string',
							'description' => 'Serialized block markup (e.g. <!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->).',
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

					if ( 'page' === $post->post_type ) {
						return new \WP_Error( 'pages_use_patterns', __( 'Raw block markup is not allowed for pages. Use append-pattern instead.', 'rtcamp-publish-with-ai' ) );
					}

					$markup = trim( $input['markup'] ?? '' );

					if ( empty( $markup ) ) {
						return new \WP_Error( 'missing_markup', __( 'Block markup is required.', 'rtcamp-publish-with-ai' ) );
					}

					$new_content  = $post->post_content;
					$new_content .= ( $new_content ? "\n\n" : '' ) . serialize_blocks( parse_blocks( $markup ) );

					$result = wp_update_post(
						[
							'ID'           => $post_id,
							'post_content' => $new_content,
						],
						true
					);

					if ( is_wp_error( $result ) ) {
						return $result;
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
