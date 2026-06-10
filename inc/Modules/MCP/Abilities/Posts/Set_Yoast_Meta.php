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
				'description'         => __( 'Writes Yoast SEO metadata for a post. Only provided fields are updated — omitted fields are left unchanged. Returns an error if Yoast SEO is not active.', 'publishio' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'post_id' ],
					'properties'           => [
						'post_id'             => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'ID of the post or page.',
						],
						'seo_title'           => [
							'type'        => 'string',
							'description' => 'SEO title shown in search results. Supports Yoast replacement variables (e.g. %%title%% %%sep%% %%sitename%%).',
						],
						'meta_description'    => [
							'type'        => 'string',
							'description' => 'Meta description shown in search results (recommended 120–156 characters).',
						],
						'focus_keyphrase'     => [
							'type'        => 'string',
							'description' => 'The primary keyphrase this post should rank for.',
						],
						'canonical'           => [
							'type'        => 'string',
							'description' => 'Canonical URL override. Leave empty to use the default permalink.',
						],
						'noindex'             => [
							'type'        => 'boolean',
							'description' => 'Set to true to add a noindex robots directive.',
						],
						'nofollow'            => [
							'type'        => 'boolean',
							'description' => 'Set to true to add a nofollow robots directive.',
						],
						'og_title'            => [
							'type'        => 'string',
							'description' => 'Open Graph title (used by Facebook and other social platforms).',
						],
						'og_description'      => [
							'type'        => 'string',
							'description' => 'Open Graph description.',
						],
						'twitter_title'       => [
							'type'        => 'string',
							'description' => 'Twitter card title.',
						],
						'twitter_description' => [
							'type'        => 'string',
							'description' => 'Twitter card description.',
						],
						'schema_page_type'    => [
							'type'        => 'string',
							'description' => 'Yoast Schema page type (e.g. "WebPage", "FAQPage", "ItemPage").',
						],
						'schema_article_type' => [
							'type'        => 'string',
							'description' => 'Yoast Schema article type (e.g. "Article", "BlogPosting", "NewsArticle").',
						],
					],
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
					if ( ! get_post( $post_id ) ) {
						return new \WP_Error( 'invalid_post', __( 'Post not found.', 'publishio' ) );
					}

					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this post.', 'publishio' ) );
					}

					$field_map = [
						'seo_title'           => '_yoast_wpseo_title',
						'meta_description'    => '_yoast_wpseo_metadesc',
						'focus_keyphrase'     => '_yoast_wpseo_focuskw',
						'canonical'           => '_yoast_wpseo_canonical',
						'og_title'            => '_yoast_wpseo_opengraph-title',
						'og_description'      => '_yoast_wpseo_opengraph-description',
						'twitter_title'       => '_yoast_wpseo_twitter-title',
						'twitter_description' => '_yoast_wpseo_twitter-description',
						'schema_page_type'    => '_yoast_wpseo_schema_page_type',
						'schema_article_type' => '_yoast_wpseo_schema_article_type',
					];

					$bool_field_map = [
						'noindex'  => '_yoast_wpseo_meta-robots-noindex',
						'nofollow' => '_yoast_wpseo_meta-robots-nofollow',
					];

					$updated = [];

					foreach ( $field_map as $input_key => $meta_key ) {
						if ( ! isset( $input[ $input_key ] ) ) {
							continue;
						}
						update_post_meta( $post_id, $meta_key, sanitize_text_field( $input[ $input_key ] ) );
						$updated[] = $input_key;
					}

					foreach ( $bool_field_map as $input_key => $meta_key ) {
						if ( ! isset( $input[ $input_key ] ) ) {
							continue;
						}
						update_post_meta( $post_id, $meta_key, $input[ $input_key ] ? '1' : '0' );
						$updated[] = $input_key;
					}

					return [
						'success'        => true,
						'post_id'        => $post_id,
						'updated_fields' => $updated,
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
