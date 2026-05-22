<?php
/**
 * Search Attachments ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts;

/**
 * Class - Search_Attachments
 */
class Search_Attachments {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'rtcamp-publish-with-ai/search-attachments',
			[
				'label'               => __( 'Search Attachments', 'rtcamp-publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Searches the media library for attachments by keyword or MIME type. Returns URLs and metadata.', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'search'    => [
							'type'        => 'string',
							'description' => 'Search keyword (matches title, caption, description).',
						],
						'mime_type' => [
							'type'        => 'string',
							'description' => 'Filter by MIME type or group (e.g. "image", "image/jpeg", "video", "application/pdf").',
						],
						'per_page'  => [
							'type'        => 'integer',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 50,
							'description' => 'Max results to return. Defaults to 10.',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'required'   => [ 'id', 'title', 'url', 'mime_type' ],
						'properties' => [
							'id'        => [
								'type'    => 'integer',
								'minimum' => 1,
							],
							'title'     => [ 'type' => 'string' ],
							'url'       => [
								'type'   => 'string',
								'format' => 'uri',
							],
							'mime_type' => [ 'type' => 'string' ],
							'alt'       => [ 'type' => 'string' ],
							'date'      => [
								'type'   => 'string',
								'format' => 'date-time',
							],
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'upload_files' );
				},
				'execute_callback'    => static function ( array $input ): array {
					$args = [
						'post_type'      => 'attachment',
						'post_status'    => 'inherit',
						'posts_per_page' => min( (int) ( $input['per_page'] ?? 10 ), 50 ),
						'orderby'        => 'date',
						'order'          => 'DESC',
					];

					if ( ! empty( $input['search'] ) ) {
						$args['s'] = sanitize_text_field( $input['search'] );
					}

					if ( ! empty( $input['mime_type'] ) ) {
						$args['post_mime_type'] = sanitize_text_field( $input['mime_type'] );
					}

					$query   = new \WP_Query( $args );
					$results = [];

					foreach ( $query->posts as $post ) {
						if ( ! ( $post instanceof \WP_Post ) ) {
							continue;
						}
						$results[] = [
							'id'        => $post->ID,
							'title'     => $post->post_title,
							'url'       => wp_get_attachment_url( $post->ID ),
							'mime_type' => $post->post_mime_type,
							'alt'       => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ) ?: '',
							'date'      => $post->post_date,
						];
					}

					return $results;
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
