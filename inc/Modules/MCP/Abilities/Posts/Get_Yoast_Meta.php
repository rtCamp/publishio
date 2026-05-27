<?php
/**
 * Get Yoast SEO Meta ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts;

/**
 * Class - Get_Yoast_Meta
 */
class Get_Yoast_Meta {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'rtpwai/get-yoast-meta',
			[
				'label'               => __( 'Get Yoast SEO Metadata', 'rtcamp-publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Returns Yoast SEO metadata for a post — SEO title, meta description, focus keyphrase, canonical URL, and robots settings. Returns an error if Yoast SEO is not active.', 'rtcamp-publish-with-ai' ),
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
					'properties' => [
						'post_id'             => [ 'type' => 'integer' ],
						'seo_title'           => [ 'type' => 'string' ],
						'meta_description'    => [ 'type' => 'string' ],
						'focus_keyphrase'     => [ 'type' => 'string' ],
						'canonical'           => [ 'type' => 'string' ],
						'noindex'             => [ 'type' => 'boolean' ],
						'nofollow'            => [ 'type' => 'boolean' ],
						'og_title'            => [ 'type' => 'string' ],
						'og_description'      => [ 'type' => 'string' ],
						'twitter_title'       => [ 'type' => 'string' ],
						'twitter_description' => [ 'type' => 'string' ],
						'schema_page_type'    => [ 'type' => 'string' ],
						'schema_article_type' => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ) {
					if ( ! defined( 'WPSEO_VERSION' ) ) {
						return new \WP_Error( 'yoast_not_active', __( 'Yoast SEO is not installed or active.', 'rtcamp-publish-with-ai' ) );
					}

					$post_id = (int) ( $input['post_id'] ?? 0 );
					if ( ! get_post( $post_id ) ) {
						return new \WP_Error( 'invalid_post', __( 'Post not found.', 'rtcamp-publish-with-ai' ) );
					}

					$get = static function ( string $key ) use ( $post_id ): string {
						return (string) ( get_post_meta( $post_id, $key, true ) ?: '' );
					};

					return [
						'post_id'             => $post_id,
						'seo_title'           => $get( '_yoast_wpseo_title' ),
						'meta_description'    => $get( '_yoast_wpseo_metadesc' ),
						'focus_keyphrase'     => $get( '_yoast_wpseo_focuskw' ),
						'canonical'           => $get( '_yoast_wpseo_canonical' ),
						'noindex'             => (bool) $get( '_yoast_wpseo_meta-robots-noindex' ),
						'nofollow'            => (bool) $get( '_yoast_wpseo_meta-robots-nofollow' ),
						'og_title'            => $get( '_yoast_wpseo_opengraph-title' ),
						'og_description'      => $get( '_yoast_wpseo_opengraph-description' ),
						'twitter_title'       => $get( '_yoast_wpseo_twitter-title' ),
						'twitter_description' => $get( '_yoast_wpseo_twitter-description' ),
						'schema_page_type'    => $get( '_yoast_wpseo_schema_page_type' ),
						'schema_article_type' => $get( '_yoast_wpseo_schema_article_type' ),
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
