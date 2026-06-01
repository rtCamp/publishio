<?php
/**
 * Get Pattern Schema ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns;

/**
 * Class - Get_Pattern_Schema
 */
class Get_Pattern_Schema {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'rtpwai/get-pattern-schema',
			[
				'label'               => __( 'Get Pattern Schema', 'rtcamp-publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Patterns::SLUG,
				'description'         => __( 'Parses a block pattern and returns a schema of its replaceable content fields (text, links, images, button labels). Use this schema as the input for the apply-pattern-schema ability.', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'name' ],
					'properties'           => [
						'name' => [
							'type'        => 'string',
							'description' => 'Fully-qualified pattern name (e.g. theme-slug/hero-section).',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'        => 'array',
					'description' => 'Ordered list of replaceable content blocks. Each entry has a block type and its replaceable fields. Pass the same structure with updated values to the apply-pattern-schema ability.',
					'items'       => [
						'type'                 => 'object',
						'required'             => [ 'block', 'fields' ],
						'properties'           => [
							'block'  => [
								'type'        => 'string',
								'description' => 'Block type name (e.g. core/paragraph, core/image).',
							],
							'fields' => [
								'type'                 => 'object',
								'description'          => 'Replaceable fields. Keys vary by block type (e.g. content, url, alt, id).',
								'additionalProperties' => [ 'type' => 'string' ],
							],
						],
						'additionalProperties' => false,
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ) {
					$name = sanitize_text_field( $input['name'] ?? '' );

					if ( ! $name ) {
						return new \WP_Error( 'missing_name', __( 'Pattern name is required.', 'rtcamp-publish-with-ai' ) );
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

					return Pattern_Schema::extract( $content );
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
