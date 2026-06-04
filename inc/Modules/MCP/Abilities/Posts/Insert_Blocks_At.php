<?php
/**
 * Insert Blocks At ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts;

/**
 * Class - Insert_Blocks_At
 */
class Insert_Blocks_At {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'pwai/insert-blocks-at',
			[
				'label'               => __( 'Insert Blocks at Position in Post', 'publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Inserts raw block markup at a specific top-level position in a post. Only works for posts (not pages).', 'publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'post_id', 'position', 'markup' ],
					'properties'           => [
						'post_id'  => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'ID of the post.',
						],
						'position' => [
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Zero-based top-level block index at which to insert.',
						],
						'markup'   => [
							'type'        => 'string',
							'description' => 'Serialized block markup to insert.',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'success', 'post_id', 'position' ],
					'properties' => [
						'success'  => [ 'type' => 'boolean' ],
						'post_id'  => [
							'type'    => 'integer',
							'minimum' => 1,
						],
						'position' => [
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Actual insertion index.',
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ) {
					$post_id  = (int) ( $input['post_id'] ?? 0 );
					$position = (int) ( $input['position'] ?? 0 );
					$post     = get_post( $post_id );

					if ( ! $post ) {
						return new \WP_Error( 'invalid_post', __( 'Post not found.', 'publish-with-ai' ) );
					}

					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this post.', 'publish-with-ai' ) );
					}

					if ( 'page' === $post->post_type ) {
						return new \WP_Error( 'pages_use_patterns', __( 'Raw block markup is not allowed for pages. Use insert-pattern-at instead.', 'publish-with-ai' ) );
					}

					$markup = trim( $input['markup'] ?? '' );

					if ( empty( $markup ) ) {
						return new \WP_Error( 'missing_markup', __( 'Block markup is required.', 'publish-with-ai' ) );
					}

					$new_blocks = parse_blocks( $markup );
					$blocks     = array_values(
						array_filter(
							parse_blocks( $post->post_content ),
							static function ( $block ) {
								return ! empty( $block['blockName'] ) || ! empty( trim( $block['innerHTML'] ) );
							}
						)
					);

					if ( $position < 0 || $position > count( $blocks ) ) {
						return new \WP_Error(
							'invalid_position',
							sprintf(
								// translators: 1: requested position, 2: maximum valid position.
								__( 'Position %1$d is out of range (0–%2$d).', 'publish-with-ai' ),
								$position,
								count( $blocks )
							)
						);
					}

					array_splice( $blocks, $position, 0, $new_blocks );

					$result = wp_update_post(
						[
							'ID'           => $post_id,
							'post_content' => serialize_blocks( $blocks ),
						],
						true
					);

					if ( is_wp_error( $result ) ) {
						return $result;
					}

					return [
						'success'  => true,
						'post_id'  => $post_id,
						'position' => $position,
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
