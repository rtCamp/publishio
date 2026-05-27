<?php
/**
 * Insert Pattern ability — app-only, called after user approves in the MCP App.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Apps\Pattern_Approval
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Apps\Pattern_Approval;

use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Posts as Posts_Category;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns\Pattern_Schema;

/**
 * Class - Insert_Pattern
 *
 * App-only tool (visibility: ["app"]) — hidden from the model.
 * The Pattern Approval MCP App calls this directly via tools/call after the
 * user clicks "Insert". Logic mirrors Insert_Pattern_At.
 */
class Insert_Pattern {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'rtpwai/insert-pattern-confirmed',
			[
				'label'               => __( 'Insert Pattern (Confirmed)', 'rtcamp-publish-with-ai' ),
				'category'            => Posts_Category::SLUG,
				'description'         => __( 'Inserts a pre-approved block pattern into a page at the specified position. Called by the Pattern Approval MCP App after user confirmation.', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'post_id', 'position', 'pattern_name', 'schema' ],
					'properties'           => [
						'post_id'      => [
							'type'    => 'integer',
							'minimum' => 1,
						],
						'position'     => [
							'type'    => 'integer',
							'minimum' => -1,
						],
						'pattern_name' => [
							'type' => 'string',
						],
						'schema'       => [
							'type'     => 'array',
							'minItems' => 1,
							'items'    => [ 'type' => 'object' ],
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'success', 'post_id', 'position' ],
					'properties' => [
						'success'  => [ 'type' => 'boolean' ],
						'post_id'  => [
							'type'    => 'integer',
							'minimum' => 1,
						],
						'position' => [
							'type'    => 'integer',
							'minimum' => 0,
						],
					],
				],
				'permission_callback' => static fn () => current_user_can( 'edit_pages' ),
				'execute_callback'    => static function ( array $input ): array|\WP_Error {
					$post_id      = (int) ( $input['post_id'] ?? 0 );
					$position     = (int) ( $input['position'] ?? 0 );
					$pattern_name = sanitize_text_field( $input['pattern_name'] ?? '' );
					$schema       = $input['schema'] ?? [];

					$post = get_post( $post_id );
					if ( ! $post ) {
						return new \WP_Error( 'invalid_post', __( 'Page not found.', 'rtcamp-publish-with-ai' ) );
					}
					if ( 'page' !== $post->post_type ) {
						return new \WP_Error( 'posts_use_markup', __( 'Patterns are only for pages. Use insert-blocks-at for posts.', 'rtcamp-publish-with-ai' ) );
					}

					if ( empty( $schema ) || ! is_array( $schema ) ) {
						return new \WP_Error( 'missing_schema', __( 'A filled content schema is required.', 'rtcamp-publish-with-ai' ) );
					}

					$registry = \WP_Block_Patterns_Registry::get_instance();
					if ( ! $registry->is_registered( $pattern_name ) ) {
						return new \WP_Error(
							'pattern_not_found',
							sprintf(
								/* translators: %s: pattern name */
								__( 'No pattern found with name "%s".', 'rtcamp-publish-with-ai' ),
								$pattern_name
							)
						);
					}

					$pattern = $registry->get_registered( $pattern_name );
					$content = $pattern['content'] ?? '';
					if ( empty( $content ) ) {
						return new \WP_Error( 'empty_pattern', __( 'Pattern has no content.', 'rtcamp-publish-with-ai' ) );
					}

					$markup = Pattern_Schema::apply( $content, $schema );
					if ( is_wp_error( $markup ) ) {
						return $markup;
					}

					$new_blocks = parse_blocks( $markup );
					$blocks     = array_values(
						array_filter(
							parse_blocks( $post->post_content ),
							static fn ( $b ) => ! empty( $b['blockName'] ) || ! empty( trim( $b['innerHTML'] ) )
						)
					);

					if ( -1 === $position ) {
						$position = count( $blocks );
					}

					if ( $position < 0 || $position > count( $blocks ) ) {
						return new \WP_Error(
							'invalid_position',
							sprintf(
								/* translators: 1: requested position, 2: maximum valid position. */
								__( 'Position %1$d is out of range (0–%2$d).', 'rtcamp-publish-with-ai' ),
								$position,
								count( $blocks )
							)
						);
					}

					array_splice( $blocks, $position, 0, $new_blocks );

					$result = wp_update_post(
						[
							'ID'           => $post_id,
							'post_content' => serialize_blocks( $blocks ),
						],
						true
					);

					if ( is_wp_error( $result ) ) {
						return $result;
					}

					return [
						'success'  => true,
						'post_id'  => $post_id,
						'position' => $position,
					];
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
						'_meta'  => [
							'ui' => [
								'visibility' => [ 'app' ],
							],
						],
					],
				],
			]
		);
	}
}
