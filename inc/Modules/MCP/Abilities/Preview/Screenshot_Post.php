<?php
/**
 * Screenshot Post ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Preview
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Preview;

use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Preview as Preview_Category;
use rtCamp\Publish_With_AI\Modules\Screenshot\Screenshot_Client;
use rtCamp\Publish_With_AI\Modules\Screenshot\Settings;
use rtCamp\Publish_With_AI\Modules\Screenshot\Token_Store;

/**
 * Class - Screenshot_Post
 */
class Screenshot_Post {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'rtpwai/screenshot-post',
			[
				'label'               => __( 'Screenshot Page or Post', 'rtcamp-publish-with-ai' ),
				'category'            => Preview_Category::SLUG,
				'description'         => __( 'Captures a screenshot of a page or post and returns it as an inline image. Requires screenshot provider to be configured in Settings → Screenshots. Returns not_supported when the feature is disabled or misconfigured.', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'post_id' ],
					'properties'           => [
						'post_id'  => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'ID of the post or page to screenshot.',
						],
						'selector' => [
							'type'        => 'string',
							'description' => 'CSS selector to crop the screenshot to. Defaults to "main". Use "body" for the full viewport.',
							'default'     => 'main',
						],
					],
					'additionalProperties' => false,
				],
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ): array|\WP_Error {
					if ( ! Settings::is_configured() ) {
						return new \WP_Error(
							'not_supported',
							__( 'Screenshot feature is not configured. Enable it under Settings → Screenshots and provide an API key if required.', 'rtcamp-publish-with-ai' )
						);
					}

					$post_id  = (int) ( $input['post_id'] ?? 0 );
					$selector = sanitize_text_field( (string) ( $input['selector'] ?? 'main' ) );

					if ( $post_id < 1 || ! get_post( $post_id ) ) {
						return new \WP_Error( 'invalid_post', __( 'Post not found.', 'rtcamp-publish-with-ai' ) );
					}

					$user_id = get_current_user_id();

					if ( 0 === $user_id ) {
						return new \WP_Error( 'not_authenticated', __( 'No authenticated user for preview.', 'rtcamp-publish-with-ai' ) );
					}

					$token       = Token_Store::create( $post_id, $user_id );
					$preview_url = rest_url( 'rtpwai/v1/preview' ) . '?token=' . rawurlencode( $token );

					$image = Screenshot_Client::capture( $preview_url, $selector );

					if ( is_wp_error( $image ) ) {
						return $image;
					}

					return [
						'type'     => 'image',
						'results'  => $image,
						'mimeType' => 'image/png',
					];
				},
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'    => true,
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
