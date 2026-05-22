<?php
/**
 * Insert Pattern At ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Posts;

use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns\Pattern_Schema;

/**
 * Class - Insert_Pattern_At
 */
class Insert_Pattern_At {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'rtcamp-publish-with-ai/insert-pattern-at',
			[
				'label'               => __( 'Insert Pattern at Position in Page', 'rtcamp-publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Posts::SLUG,
				'description'         => __( 'Inserts a pattern at a specific top-level position in a page. Requires a pattern name and filled content schema. Only works for pages (not posts).', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'post_id', 'position', 'pattern_name', 'schema' ],
					'properties'           => [
						'post_id'      => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'ID of the page.',
						],
						'position'     => [
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Zero-based top-level block index at which to insert.',
						],
						'pattern_name' => [
							'type'        => 'string',
							'description' => 'Fully-qualified pattern name.',
						],
						'schema'       => [
							'type'        => 'array',
							'minItems'    => 1,
							'description' => 'Filled content schema (from get-pattern-schema, with values populated).',
							'items'       => [
								'anyOf' => [
									[
										'type'       => 'object',
										'required'   => [ 'block', 'fields' ],
										'properties' => [
											'block'  => [ 'type' => 'string' ],
											'fields' => [
												'type' => 'object',
												'additionalProperties' => [ 'type' => 'string' ],
											],
										],
										'additionalProperties' => false,
									],
									[
										'type'       => 'object',
										'required'   => [ 'block', 'repeatable', 'items' ],
										'properties' => [
											'block'      => [ 'type' => 'string' ],
											'repeatable' => [ 'type' => 'boolean' ],
											'items'      => [
												'type'  => 'array',
												'items' => [ 'type' => 'array' ],
											],
										],
										'additionalProperties' => false,
									],
								],
							],
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
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Actual insertion index.',
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_pages' );
				},
				'execute_callback'    => static function ( array $input ) {
					$post_id  = (int) ( $input['post_id'] ?? 0 );
					$position = (int) ( $input['position'] ?? 0 );
					$post     = get_post( $post_id );

					if ( ! $post ) {
						return new \WP_Error( 'invalid_post', __( 'Page not found.', 'rtcamp-publish-with-ai' ) );
					}

					if ( 'page' !== $post->post_type ) {
						return new \WP_Error( 'posts_use_markup', __( 'Patterns are only for pages. Use insert-blocks-at for posts.', 'rtcamp-publish-with-ai' ) );
					}

					$markup = self::resolve_pattern( $input );
					if ( is_wp_error( $markup ) ) {
						return $markup;
					}

					$new_blocks = parse_blocks( $markup );
					$blocks     = array_values(
						array_filter(
							parse_blocks( $post->post_content ),
							static function ( $block ) {
								return ! empty( $block['blockName'] ) || ! empty( trim( $block['innerHTML'] ) );
							}
						)
					);

					if ( $position < 0 || $position > count( $blocks ) ) {
						return new \WP_Error(
							'invalid_position',
							sprintf(
								// translators: 1: requested position, 2: maximum valid position.
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
					],
				],
			]
		);
	}

	/**
	 * Resolve pattern markup from input.
	 *
	 * @param array<string, mixed> $input Ability input.
	 */
	private static function resolve_pattern( array $input ): string|\WP_Error {
		$pattern_name = sanitize_text_field( $input['pattern_name'] );
		$schema       = $input['schema'] ?? [];

		if ( empty( $schema ) || ! is_array( $schema ) ) {
			return new \WP_Error( 'missing_schema', __( 'A filled content schema is required.', 'rtcamp-publish-with-ai' ) );
		}

		$registry = \WP_Block_Patterns_Registry::get_instance();

		if ( ! $registry->is_registered( $pattern_name ) ) {
			return new \WP_Error(
				'pattern_not_found',
				sprintf(
					// translators: %s: pattern name.
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

		return Pattern_Schema::apply( $content, $schema );
	}
}
