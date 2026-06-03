<?php
/**
 * Delete Block At ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts;

/**
 * Class - Delete_Block_At
 */
class Delete_Block_At {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'pwai/delete-block-at',
			[
				'label'               => __( 'Delete Block at Position', 'publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Deletes a top-level block at a specific position in a post or page.', 'publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'post_id', 'position' ],
					'properties'           => [
						'post_id'  => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'ID of the post or page.',
						],
						'position' => [
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Zero-based index of the top-level block to delete.',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'success', 'post_id', 'deleted_block' ],
					'properties' => [
						'success'       => [ 'type' => 'boolean' ],
						'post_id'       => [
							'type'    => 'integer',
							'minimum' => 1,
						],
						'deleted_block' => [
							'type'        => 'string',
							'description' => 'Name of the block that was deleted.',
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ) {
					$post_id  = (int) ( $input['post_id'] ?? 0 );
					$position = (int) ( $input['position'] ?? -1 );
					$post     = get_post( $post_id );

					if ( ! $post ) {
						return new \WP_Error( 'invalid_post', __( 'Post not found.', 'publish-with-ai' ) );
					}

					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this post.', 'publish-with-ai' ) );
					}

					$blocks = array_values(
						array_filter(
							parse_blocks( $post->post_content ),
							static function ( $block ) {
								return ! empty( $block['blockName'] ) || ! empty( trim( $block['innerHTML'] ) );
							}
						)
					);

					if ( $position < 0 || $position >= count( $blocks ) ) {
						return new \WP_Error(
							'invalid_position',
							sprintf(
								// translators: 1: requested position, 2: maximum valid position.
								__( 'Position %1$d is out of range (0–%2$d).', 'publish-with-ai' ),
								$position,
								count( $blocks ) - 1
							)
						);
					}

					$deleted_name = $blocks[ $position ]['blockName'] ?? '(freeform)';

					array_splice( $blocks, $position, 1 );

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
						'success'       => true,
						'post_id'       => $post_id,
						'deleted_block' => $deleted_name,
					];
				},
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'    => false,
						'destructive' => true,
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
