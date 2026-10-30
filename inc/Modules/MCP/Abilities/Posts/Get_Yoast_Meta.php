<?php
/**
 * Get Yoast SEO Meta ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Posts;

use rtCamp\Publishio\Modules\MCP\Editing\Errors;

/**
 * Class - Get_Yoast_Meta
 */
class Get_Yoast_Meta {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'publishio/get-yoast-meta',
			[
				'label'               => __( 'Get Yoast SEO Metadata', 'publishio' ),
				'category'            => \rtCamp\Publishio\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Returns the Yoast SEO fields you can edit on a post.', 'publishio' ),
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
					'required'   => [ 'post_id' ],
					'properties' => [ 'post_id' => [ 'type' => 'integer' ] ] + Yoast_Fields::schemas(),
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ) {
					if ( ! defined( 'WPSEO_VERSION' ) ) {
						return Errors::make( 'yoast_not_active', __( 'Yoast SEO is not installed or active.', 'publishio' ) );
					}

					$post_id = (int) ( $input['post_id'] ?? 0 );
					$post    = get_post( $post_id );
					if ( ! $post ) {
						return Errors::make( 'invalid_post', __( 'Post not found.', 'publishio' ) );
					}

					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return Errors::make( 'forbidden', __( 'You do not have permission to edit this post.', 'publishio' ) );
					}

					$fields = Yoast_Fields::visible( $post );
					if ( is_wp_error( $fields ) ) {
						return $fields;
					}

					$result = [ 'post_id' => $post_id ];

					foreach ( array_keys( $fields ) as $field ) {
						$result[ $field ] = Yoast_Fields::decode( $field, (string) get_post_meta( $post_id, Yoast_Fields::meta_key( $field ), true ) );
					}

					return $result;
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
