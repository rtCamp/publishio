<?php
/**
 * Render Block ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Blocks
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Blocks;

/**
 * Class - Render_Block
 */
class Render_Block {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'publishio/render-block',
			[
				'label'               => __( 'Render Block Markup', 'publishio' ),
				'category'            => \rtCamp\Publishio\Modules\MCP\Abilities\Categories\Blocks::SLUG,
				'description'         => __( 'Renders block markup to see what it outputs and validates whether the block markup is valid or not.', 'publishio' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'markup' ],
					'properties'           => [
						'markup' => [
							'type'        => 'string',
							'description' => 'The serialized block markup to render (e.g. <!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->).',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'html', 'is_valid', 'errors' ],
					'properties' => [
						'html'     => [
							'type'        => 'string',
							'description' => 'The rendered HTML output.',
						],
						'is_valid' => [
							'type'        => 'boolean',
							'description' => 'Whether the markup parsed and rendered without errors.',
						],
						'errors'   => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => 'Any errors encountered during rendering.',
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ): array {
					$markup = $input['markup'] ?? '';

					if ( empty( $markup ) ) {
						return [
							'html'     => '',
							'is_valid' => false,
							'errors'   => [ __( 'Block markup is required.', 'publishio' ) ],
						];
					}

					$blocks = parse_blocks( $markup );

					if ( empty( $blocks ) ) {
						return [
							'html'     => '',
							'is_valid' => false,
							'errors'   => [ __( 'Could not parse any blocks from the provided markup.', 'publishio' ) ],
						];
					}

					$errors = [];
					$html   = '';

					foreach ( $blocks as $block ) {
						// Skip empty filler blocks (whitespace between blocks).
						if ( empty( $block['blockName'] ) && empty( trim( $block['innerHTML'] ?? '' ) ) ) {
							continue;
						}

						if ( ! empty( $block['blockName'] ) ) {
							$registry = \WP_Block_Type_Registry::get_instance();
							if ( ! $registry->is_registered( $block['blockName'] ) ) {
								$errors[] = sprintf(
									/* translators: %s: block name */
									__( 'Block type "%s" is not registered.', 'publishio' ),
									$block['blockName']
								);
							}
						}

						$rendered = render_block( $block );
						$html    .= $rendered;
					}

					return [
						'html'     => $html,
						'is_valid' => empty( $errors ),
						'errors'   => $errors,
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
