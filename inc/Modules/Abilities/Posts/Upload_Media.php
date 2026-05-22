<?php
/**
 * Upload Media ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Abilities\Posts;

/**
 * Class - Upload_Media
 */
class Upload_Media {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'rtcamp-publish-with-ai/upload-media',
			[
				'label'               => __( 'Upload Media', 'rtcamp-publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Uploads an image to the media library from a URL. Returns the attachment ID and URL.', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'url', 'filename', 'title', 'alt', 'caption', 'description' ],
					'properties'           => [
						'url'         => [
							'type'        => 'string',
							'format'      => 'uri',
							'description' => 'Public URL of the image to sideload.',
						],
						'filename'    => [
							'type'        => 'string',
							'description' => 'Custom filename to save the image as (without extension).',
						],
						'title'       => [
							'type'        => 'string',
							'description' => 'Attachment title. Defaults to filename.',
						],
						'alt'         => [
							'type'        => 'string',
							'description' => 'Alt text for the image.',
						],
						'caption'     => [
							'type'        => 'string',
							'description' => 'Caption for the image (stored as post_excerpt).',
						],
						'description' => [
							'type'        => 'string',
							'description' => 'Description for the image (stored as post_content).',
						],
						'post_id'     => [
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Optional parent post ID to attach the media to.',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'attachment_id', 'url', 'title' ],
					'properties' => [
						'attachment_id' => [
							'type'    => 'integer',
							'minimum' => 1,
						],
						'url'           => [
							'type'   => 'string',
							'format' => 'uri',
						],
						'title'         => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'upload_files' );
				},
				'execute_callback'    => static function ( array $input ) {
					$url         = $input['url'] ?? '';
					$title       = sanitize_text_field( $input['title'] ?? '' );
					$alt         = sanitize_text_field( $input['alt'] ?? '' );
					$post_id     = (int) ( $input['post_id'] ?? 0 );
					$caption     = sanitize_text_field( $input['caption'] ?? '' );
					$description = sanitize_textarea_field( $input['description'] ?? '' );
					$filename    = sanitize_file_name( $input['filename'] ?? '' );

					if ( empty( $url ) ) {
						return new \WP_Error( 'missing_source', __( 'A url is required.', 'rtcamp-publish-with-ai' ) );
					}

					// Load required admin files.
					require_once ABSPATH . 'wp-admin/includes/media.php';
					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/image.php';

					return self::upload_from_url( $url, $post_id, $title, $alt, $caption, $description, $filename );
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

	/**
	 * Upload an image from a URL.
	 *
	 * @param string $url         The image URL.
	 * @param int    $post_id     Parent post ID.
	 * @param string $title       Attachment title.
	 * @param string $alt         Alt text.
	 * @param string $caption     Caption.
	 * @param string $description Description.
	 * @param string $filename    Custom filename (without extension).
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function upload_from_url( string $url, int $post_id, string $title, string $alt, string $caption, string $description, string $filename ): array|\WP_Error {
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new \WP_Error( 'invalid_url', __( 'Invalid URL provided.', 'rtcamp-publish-with-ai' ) );
		}

		$attachment_id = self::sideload_by_content( $url, $post_id, $title, $filename );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return self::build_response( $attachment_id, $title, $alt, $caption, $description );
	}

	/**
	 * Downloads a remote image and sideloads it by detecting MIME type from the file content.
	 *
	 * @param string $url      The image URL.
	 * @param int    $post_id  Parent post ID.
	 * @param string $title    Attachment title.
	 * @param string $filename Custom filename (without extension).
	 */
	private static function sideload_by_content( string $url, int $post_id, string $title, string $filename ): int|\WP_Error {
		// download_url uses wp_safe_remote_get which guards against SSRF.
		$tmp_file = download_url( $url );

		if ( is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		// Detect actual image type from file content.
		$image_type = wp_get_image_mime( $tmp_file );

		$allowed = [
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
			'image/avif' => 'avif',
		];

		if ( ! $image_type || ! isset( $allowed[ $image_type ] ) ) {
			unlink( $tmp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
			return new \WP_Error( 'invalid_image_type', __( 'The URL did not point to a supported image type.', 'rtcamp-publish-with-ai' ) );
		}

		$file_array = [
			'name'     => $filename . '.' . $allowed[ $image_type ],
			'tmp_name' => $tmp_file,
		];

		$attachment_id = media_handle_sideload( $file_array, $post_id, $title ?: null );

		if ( file_exists( $tmp_file ) ) {
			unlink( $tmp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		}

		return $attachment_id;
	}

	/**
	 * Build the response array after sideloading.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $title        Title.
	 * @param string $alt          Alt text.
	 * @param string $caption      Caption.
	 * @param string $description  Description.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_response( int $attachment_id, string $title, string $alt, string $caption, string $description ): array {
		$update_args = [ 'ID' => $attachment_id ];

		if ( $title ) {
			$update_args['post_title'] = $title;
		}

		if ( $caption ) {
			$update_args['post_excerpt'] = $caption;
		}

		if ( $description ) {
			$update_args['post_content'] = $description;
		}

		if ( count( $update_args ) > 1 ) {
			wp_update_post( $update_args );
		}

		if ( $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}

		return [
			'attachment_id' => $attachment_id,
			'url'           => wp_get_attachment_url( $attachment_id ),
			'title'         => get_the_title( $attachment_id ),
		];
	}
}
