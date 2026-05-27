<?php
/**
 * Screenshot Post ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Preview
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Preview;

use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Preview as Preview_Category;
use rtCamp\Publish_With_AI\Modules\Screenshot\Preview_Endpoint;
use rtCamp\Publish_With_AI\Modules\Screenshot\Screenshot_Client;
use rtCamp\Publish_With_AI\Modules\Screenshot\Screenshot_Image_Endpoint;
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
				'description'         => __( 'Captures a screenshot of a page or post. Returns a screenshot_url — embed it in your response as ![Screenshot](screenshot_url) so the user sees it inline. Requires screenshot provider to be configured in Settings → Screenshots. Returns not_supported when the feature is disabled or misconfigured.', 'rtcamp-publish-with-ai' ),
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

					$token = Token_Store::create( $post_id, $user_id );

					// Pre-render the HTML here so the preview endpoint can serve it
					// instantly when Microlink visits — no loopback inside Microlink's timeout.
					$html = Preview_Endpoint::fetch_page_html( $post_id, $user_id );

					if ( is_wp_error( $html ) ) {
						return $html;
					}

					set_transient( Preview_Endpoint::HTML_PREFIX . $token, $html, 15 * MINUTE_IN_SECONDS );

					$preview_url = rest_url( 'rtpwai/v1/preview' ) . '?token=' . rawurlencode( $token );

					$image = Screenshot_Client::capture( $preview_url, $selector );

					if ( is_wp_error( $image ) ) {
						return $image;
					}

					$image_token = wp_generate_uuid4();
					set_transient(
						Screenshot_Image_Endpoint::TRANSIENT_PREFIX . $image_token,
						base64_encode( $image ),
						Screenshot_Image_Endpoint::TTL
					);

					$screenshot_url = rest_url( 'rtpwai/v1/screenshot-image' ) . '?token=' . rawurlencode( $image_token );

					return [ 'screenshot_url' => $screenshot_url ];
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
