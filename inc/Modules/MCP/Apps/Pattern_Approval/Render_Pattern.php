<?php
/**
 * Render Pattern ability — app-only, renders a filled pattern to HTML.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Apps\Pattern_Approval
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Apps\Pattern_Approval;

use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Patterns as Patterns_Category;
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns\Pattern_Schema;

/**
 * Class - Render_Pattern
 *
 * App-only tool called by the Pattern Approval MCP App to render
 * a filled pattern schema to HTML for display in the preview.
 */
class Render_Pattern {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'rtpwai/render-pattern',
			[
				'label'               => __( 'Render Pattern to HTML (App Only)', 'rtcamp-publish-with-ai' ),
				'category'            => Patterns_Category::SLUG,
				'description'         => __( 'Applies a filled content schema to a pattern and renders it to HTML. Called by the Pattern Approval MCP App to generate the preview.', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'pattern_name', 'schema' ],
					'properties'           => [
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
					'required'   => [ 'preview_html' ],
					'properties' => [
						'preview_html'        => [
							'type'        => 'string',
							'description' => 'Self-contained HTML document ready for iframe srcdoc.',
						],
						'pattern_title'       => [
							'type'     => 'string',
							'nullable' => true,
						],
						'pattern_description' => [
							'type'     => 'string',
							'nullable' => true,
						],
					],
				],
				'permission_callback' => static fn () => current_user_can( 'edit_posts' ),
				'execute_callback'    => static function ( array $input ): array|\WP_Error {
					$pattern_name = sanitize_text_field( $input['pattern_name'] ?? '' );
					$schema       = $input['schema'] ?? [];
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

					$pattern = $registry->get_registered( $pattern_name );
					$markup  = Pattern_Schema::apply( $pattern['content'] ?? '', $schema );
					if ( empty( $markup ) ) {
						return new \WP_Error( 'empty_markup', __( 'Pattern schema application resulted in empty markup.', 'rtcamp-publish-with-ai' ) );
					}

					// Ensure core block styles handle is registered before rendering.
					wp_enqueue_style( 'wp-block-library' );

					$html = do_blocks( $markup ); // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable

					ob_start();
					require __DIR__ . '/preview-template.php';
					$preview_html = (string) ob_get_clean();

					$result = [ 'preview_html' => $preview_html ];

					if ( ! empty( $pattern['title'] ) ) {
						$result['pattern_title'] = wp_strip_all_tags( $pattern['title'] );
					}

					if ( ! empty( $pattern['description'] ) ) {
						$result['pattern_description'] = wp_strip_all_tags( $pattern['description'] );
					}

					return $result;
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
								'visibility' => [ 'app' ],
							],
						],
					],
				],
			]
		);
	}
}
