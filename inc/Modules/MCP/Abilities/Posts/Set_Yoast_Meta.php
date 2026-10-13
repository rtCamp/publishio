<?php
/**
 * Set Yoast SEO Meta ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Posts;

/**
 * Class - Set_Yoast_Meta
 */
class Set_Yoast_Meta {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'publishio/set-yoast-meta',
			[
				'label'               => __( 'Set Yoast SEO Metadata', 'publishio' ),
				'category'            => \rtCamp\Publishio\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Updates the given Yoast SEO fields of a post. Accepts only the fields publishio/get-yoast-meta returns.', 'publishio' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'post_id' ],
					'properties'           => [
						'post_id' => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'ID of the post or page.',
						],
					] + Yoast_Fields::schemas(),
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'success', 'post_id', 'updated_fields' ],
					'properties' => [
						'success'        => [ 'type' => 'boolean' ],
						'post_id'        => [ 'type' => 'integer' ],
						'updated_fields' => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ) {
					if ( ! defined( 'WPSEO_VERSION' ) ) {
						return new \WP_Error( 'yoast_not_active', __( 'Yoast SEO is not installed or active.', 'publishio' ) );
					}

					$post_id = (int) ( $input['post_id'] ?? 0 );
					$post    = get_post( $post_id );
					if ( ! $post ) {
						return new \WP_Error( 'invalid_post', __( 'Post not found.', 'publishio' ) );
					}

					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this post.', 'publishio' ) );
					}

					$visible = Yoast_Fields::visible( $post );
					if ( is_wp_error( $visible ) ) {
						return $visible;
					}

					$fields = array_keys( array_intersect_key( $input, Yoast_Fields::FIELDS ) );
					$hidden = array_diff( $fields, $visible );

					if ( [] !== $hidden ) {
						return new \WP_Error(
							'yoast_fields_unavailable',
							sprintf(
								/* translators: %s: comma-separated field names. */
								__( 'You cannot edit these Yoast SEO fields on this post: %s. Nothing was saved.', 'publishio' ),
								implode( ', ', $hidden )
							)
						);
					}

					$values = [];

					foreach ( $fields as $field ) {
						$value = Yoast_Fields::sanitize( $field, $input[ $field ] );
						if ( is_wp_error( $value ) ) {
							return $value;
						}
						$values[ $field ] = $value;
					}

					foreach ( $values as $field => $value ) {
						update_post_meta( $post_id, Yoast_Fields::meta_key( $field ), $value );
					}

					return [
						'success'        => true,
						'post_id'        => $post_id,
						'updated_fields' => array_keys( $values ),
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
