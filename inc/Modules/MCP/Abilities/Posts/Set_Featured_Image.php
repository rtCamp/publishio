<?php
/**
 * Set Featured Image ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts;

/**
 * Class - Set_Featured_Image
 */
class Set_Featured_Image {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'pwai/set-featured-image',
			[
				'label'               => __( 'Set Featured Image for Post or Page', 'rtcamp-publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Sets or removes the featured image (post thumbnail) for a post or page. Pass an attachment ID to set, or 0 to remove.', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'post_id', 'attachment_id' ],
					'properties'           => [
						'post_id'       => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'ID of the post or page.',
						],
						'attachment_id' => [
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Attachment ID to set as featured image. Use 0 to remove.',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'success', 'url' ],
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'url'     => [
							'type'        => 'string',
							'description' => 'URL of the featured image (empty if removed).',
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ) {
					$post_id       = (int) ( $input['post_id'] ?? 0 );
					$attachment_id = (int) ( $input['attachment_id'] ?? -1 );

					if ( ! get_post( $post_id ) ) {
						return new \WP_Error( 'invalid_post', __( 'Post not found.', 'rtcamp-publish-with-ai' ) );
					}

					if ( 0 === $attachment_id ) {
						delete_post_thumbnail( $post_id );
						return [
							'success' => true,
							'url'     => '',
						];
					}

					if ( ! get_post( $attachment_id ) || get_post_type( $attachment_id ) !== 'attachment' ) {
						return new \WP_Error( 'invalid_attachment', __( 'Attachment not found.', 'rtcamp-publish-with-ai' ) );
					}

					$result = set_post_thumbnail( $post_id, $attachment_id );

					if ( ! $result ) {
						return new \WP_Error( 'failed', __( 'Could not set featured image.', 'rtcamp-publish-with-ai' ) );
					}

					return [
						'success' => true,
						'url'     => wp_get_attachment_url( $attachment_id ) ?: '',
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
