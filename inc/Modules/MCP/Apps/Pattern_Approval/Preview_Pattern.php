<?php
/**
 * Preview Pattern ability — shows a pattern approval UI (MCP App) before insertion.
 *
 * @package rtCamp\Publishio\Modules\MCP\Apps\Pattern_Approval
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Apps\Pattern_Approval;

use rtCamp\Publishio\Modules\MCP\Abilities\Categories\Patterns as Patterns_Category;

/**
 * Class - Preview_Pattern
 *
 * Lightweight model-facing tool. Validates the pattern and echoes back the
 * insertion parameters so the Pattern Approval MCP App receives them via
 * ui/notifications/tool-result. The app then calls the app-only
 * publishio/render-pattern tool to fetch a fully-rendered HTML preview.
 */
class Preview_Pattern {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'publishio/preview-pattern',
			[
				'label'               => __( 'Present Pattern for User Approval', 'publishio' ),
				'category'            => Patterns_Category::SLUG,
				'description'         => __( 'Presents a filled pattern to the user for visual review. The Pattern Approval UI opens automatically showing the rendered preview with two choices: Insert (inserts the pattern into the page and sends a confirmation back — the model resumes from that message) or Show alternative (asks the model for a different pattern). Returns an error immediately if the pattern name is not registered. Do not call any further tools — wait for the user to act.', 'publishio' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'page_id', 'position', 'pattern_name', 'schema' ],
					'properties'           => [
						'page_id'      => [
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
					'required'   => [ 'page_id', 'position', 'pattern_name', 'schema', 'message' ],
					'properties' => [
						'page_id'      => [ 'type' => 'integer' ],
						'position'     => [ 'type' => 'integer' ],
						'pattern_name' => [ 'type' => 'string' ],
						'schema'       => [ 'type' => 'array' ],
						'message'      => [
							'type'        => 'string',
							'description' => 'Confirmation that the preview UI is open. The model must stop here — resume only when the user sends a follow-up message.',
						],
					],
				],
				'permission_callback' => static fn () => current_user_can( 'edit_pages' ),
				'execute_callback'    => static function ( array $input ): array|\WP_Error {
					$page_id      = (int) ( $input['page_id'] ?? 0 );
					$pattern_name = sanitize_text_field( $input['pattern_name'] ?? '' );

					if ( ! get_post( $page_id ) ) {
						return new \WP_Error( 'invalid_post', __( 'Page not found.', 'publishio' ) );
					}

					if ( ! current_user_can( 'edit_post', $page_id ) ) {
						return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this page.', 'publishio' ) );
					}

					$registry = \WP_Block_Patterns_Registry::get_instance();

					if ( ! $registry->is_registered( $pattern_name ) ) {
						return new \WP_Error(
							'pattern_not_found',
							sprintf(
								/* translators: %s: pattern name */
								__( 'No pattern found with name "%s".', 'publishio' ),
								$pattern_name
							)
						);
					}

					return [
						'page_id'      => $page_id,
						'position'     => (int) ( $input['position'] ?? 0 ),
						'pattern_name' => $pattern_name,
						'schema'       => $input['schema'] ?? [],
						'message'      => __( 'The pattern preview is now displayed to the user. Waiting for their approval before inserting.', 'publishio' ),
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
