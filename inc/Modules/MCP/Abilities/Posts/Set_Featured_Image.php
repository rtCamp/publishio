<?php
/**
 * Set Featured Image ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Posts;

use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Editing\Errors;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Writer;

/**
 * Class - Set_Featured_Image
 */
class Set_Featured_Image {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'publishio/set-featured-image',
			[
				'label'               => __( 'Set Featured Image for Post or Page', 'publishio' ),
				'category'            => \rtCamp\Publishio\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Sets or removes the featured image of a post, page or other post type that has one. Pass an attachment ID from search-attachments to set it, or 0 to remove it.', 'publishio' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'post_id', 'attachment_id' ],
					'properties'           => [
						'post_id'        => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'ID of the post or page.',
						],
						'attachment_id'  => [
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Attachment ID to set as featured image. Use 0 to remove.',
						],
						'approval_token' => Schema::approval_token(),
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

					$post = Post_Writer::writable( $post_id, (string) ( $input['approval_token'] ?? '' ) );

					if ( is_wp_error( $post ) ) {
						return $post;
					}

					if ( ! post_type_supports( $post->post_type, 'thumbnail' ) ) {
						$type = get_post_type_object( $post->post_type );

						/* translators: %s: post type name */
						return Errors::make( 'no_featured_image', sprintf( __( '%s don\'t have a featured image on this site.', 'publishio' ), $type ? $type->labels->name : $post->post_type ) );
					}

					if ( 0 === $attachment_id ) {
						delete_post_thumbnail( $post_id );
						return [
							'success' => true,
							'url'     => '',
						];
					}

					if ( ! get_post( $attachment_id ) || get_post_type( $attachment_id ) !== 'attachment' ) {
						return Errors::make( 'invalid_attachment', __( 'Attachment not found. Use search-attachments to find one.', 'publishio' ), 404 );
					}

					if ( ! current_user_can( 'read_post', $attachment_id ) ) {
						return Errors::make( 'forbidden_attachment', __( 'Your account can\'t use this attachment.', 'publishio' ), 403 );
					}

					$result = set_post_thumbnail( $post_id, $attachment_id );

					if ( ! $result ) {
						return Errors::make( 'failed', __( 'The featured image couldn\'t be set.', 'publishio' ), 500 );
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
