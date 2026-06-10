<?php
/**
 * Get Pattern ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Patterns
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Patterns;

/**
 * Class - Get_Pattern
 */
class Get_Pattern {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'publishio/get-pattern',
			[
				'label'               => __( 'Get Pattern by Name', 'publishio' ),
				'category'            => \rtCamp\Publishio\Modules\MCP\Abilities\Categories\Patterns::SLUG,
				'description'         => __( 'Returns full details of a single block pattern including its block markup content.', 'publishio' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'name' ],
					'properties'           => [
						'name' => [
							'type'        => 'string',
							'description' => 'The fully-qualified pattern name (e.g. core/query-standard-posts).',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'name', 'title', 'content' ],
					'properties' => [
						'name'           => [ 'type' => 'string' ],
						'title'          => [ 'type' => 'string' ],
						'description'    => [ 'type' => 'string' ],
						'content'        => [
							'type'        => 'string',
							'description' => 'Raw block markup for the pattern.',
						],
						'categories'     => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
						'keywords'       => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
						'block_types'    => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
						'post_types'     => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
						'template_types' => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
						'inserter'       => [ 'type' => 'boolean' ],
						'source'         => [ 'type' => 'string' ],
						'viewport_width' => [
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => 'Suggested preview viewport width in pixels.',
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ) {
					$name = sanitize_text_field( $input['name'] ?? '' );

					if ( ! $name ) {
						return new \WP_Error( 'missing_name', __( 'Pattern name is required.', 'publishio' ) );
					}

					$registry = \WP_Block_Patterns_Registry::get_instance();

					if ( ! $registry->is_registered( $name ) ) {
						return new \WP_Error(
							'pattern_not_found',
							sprintf(
								/* translators: %s: pattern name */
								__( 'No pattern found with name "%s".', 'publishio' ),
								$name
							)
						);
					}

					$pattern = $registry->get_registered( $name );

					return [
						'name'           => $pattern['name'] ?? '',
						'title'          => $pattern['title'] ?? '',
						'description'    => $pattern['description'] ?? '',
						'content'        => $pattern['content'] ?? '',
						'categories'     => $pattern['categories'] ?? [],
						'keywords'       => $pattern['keywords'] ?? [],
						'block_types'    => $pattern['blockTypes'] ?? [],
						'post_types'     => $pattern['postTypes'] ?? [],
						'template_types' => $pattern['templateTypes'] ?? [],
						'inserter'       => $pattern['inserter'] ?? true,
						'source'         => $pattern['source'] ?? '',
						'viewport_width' => $pattern['viewportWidth'] ?? 0,
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
