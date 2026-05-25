<?php
/**
 * Apply Pattern Schema ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns;

/**
 * Class - Apply_Pattern_Schema
 */
class Apply_Pattern_Schema {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'pwai/apply-pattern-schema',
			[
				'label'               => __( 'Apply Content Schema to Pattern', 'rtcamp-publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Patterns::SLUG,
				'description'         => __( 'Takes a pattern name and a filled content schema (from the get-pattern-schema ability) and returns the modified block markup with the new content applied. Only content (text, links, images, labels) is replaced — layout and styles are preserved. Optionally renders the result to HTML for previewing the final output.', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'name', 'schema' ],
					'properties'           => [
						'name'   => [
							'type'        => 'string',
							'description' => 'Fully-qualified pattern name (e.g. theme-slug/hero-section).',
						],
						'schema' => [
							'type'        => 'array',
							'description' => 'The filled content schema — same structure returned by get-pattern-schema with values updated to desired content. For repeatable entries, return fewer items to reduce count. Can be an empty array to use the pattern as-is.',
							'minItems'    => 0,
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
						'render' => [
							'type'        => 'boolean',
							'description' => 'When true, also returns the rendered HTML output alongside the block markup. Useful for previewing how the pattern will actually look.',
							'default'     => false,
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'markup' ],
					'properties' => [
						'markup' => [
							'type'        => 'string',
							'description' => 'The modified serialized block markup (not rendered HTML).',
						],
						'html'   => [
							'type'        => 'string',
							'description' => 'The rendered HTML output. Only present when render=true.',
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ) {
					$name   = sanitize_text_field( $input['name'] ?? '' );
					$schema = $input['schema'] ?? [];
					$render = ! empty( $input['render'] );

					if ( ! $name ) {
						return new \WP_Error( 'missing_name', __( 'Pattern name is required.', 'rtcamp-publish-with-ai' ) );
					}

					if ( ! is_array( $schema ) ) {
						return new \WP_Error( 'invalid_schema', __( 'Schema must be an array.', 'rtcamp-publish-with-ai' ) );
					}

					$registry = \WP_Block_Patterns_Registry::get_instance();

					if ( ! $registry->is_registered( $name ) ) {
						return new \WP_Error(
							'pattern_not_found',
							sprintf(
								/* translators: %s: pattern name */
								__( 'No pattern found with name "%s".', 'rtcamp-publish-with-ai' ),
								$name
							)
						);
					}

					$pattern = $registry->get_registered( $name );
					$content = $pattern['content'] ?? '';

					if ( empty( $content ) ) {
						return new \WP_Error( 'empty_pattern', __( 'Pattern has no content.', 'rtcamp-publish-with-ai' ) );
					}

					$markup = empty( $schema ) ? $content : Pattern_Schema::apply( $content, $schema );

					$result = [ 'markup' => $markup ];

					if ( $render ) {
						$blocks = parse_blocks( $markup );
						$html   = '';

						foreach ( $blocks as $block ) {
							$html .= render_block( $block );
						}

						$result['html'] = $html;
					}

					return $result;
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
