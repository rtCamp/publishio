<?php
/**
 * Preview Pattern ability — shows a pattern approval UI (MCP App) before insertion.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Apps\Pattern_Approval
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Apps\Pattern_Approval;

use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Patterns as Patterns_Category;

/**
 * Class - Preview_Pattern
 *
 * Lightweight model-facing tool. Validates the pattern and echoes back the
 * insertion parameters so the Pattern Approval MCP App receives them via
 * ui/notifications/tool-result. The app then calls the app-only
 * rtpwai/render-pattern tool to fetch a fully-rendered HTML preview.
 */
class Preview_Pattern {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'rtpwai/preview-pattern',
			[
				'label'               => __( 'Preview Pattern for Approval', 'rtcamp-publish-with-ai' ),
				'category'            => Patterns_Category::SLUG,
				'description'         => __( 'Validates a filled pattern and opens the Pattern Approval UI so the user can preview and confirm before the pattern is inserted into the page.', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'post_id', 'position', 'pattern_name', 'schema' ],
					'properties'           => [
						'post_id'      => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'ID of the page to insert into.',
						],
						'position'     => [
							'type'        => 'integer',
							'minimum'     => -1,
							'description' => 'Zero-based top-level block index at which to insert. Pass -1 to append at the end.',
						],
						'pattern_name' => [
							'type'        => 'string',
							'description' => 'Fully-qualified pattern name.',
						],
						'schema'       => [
							'type'        => 'array',
							'minItems'    => 1,
							'description' => 'Filled content schema (from get-pattern-schema, with values populated).',
							'items'       => [ 'type' => 'object' ],
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'post_id', 'position', 'pattern_name', 'schema', 'message' ],
					'properties' => [
						'post_id'      => [ 'type' => 'integer' ],
						'position'     => [ 'type' => 'integer' ],
						'pattern_name' => [ 'type' => 'string' ],
						'schema'       => [ 'type' => 'array' ],
						'message'      => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => static fn () => current_user_can( 'edit_pages' ),
				'execute_callback'    => static function ( array $input ): array|\WP_Error {
					$pattern_name = sanitize_text_field( $input['pattern_name'] ?? '' );
					$registry     = \WP_Block_Patterns_Registry::get_instance();

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

					return [
						'post_id'      => (int) ( $input['post_id'] ?? 0 ),
						'position'     => (int) ( $input['position'] ?? 0 ),
						'pattern_name' => $pattern_name,
						'schema'       => $input['schema'] ?? [],
						'message'      => __( 'The pattern preview is now displayed to the user. Waiting for their approval before inserting.', 'rtcamp-publish-with-ai' ),
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
						'_meta'  => [
							'ui' => [
								'resourceUri' => App::URI,
								'visibility'  => [ 'model', 'app' ],
							],
						],
					],
				],
			]
		);
	}
}
