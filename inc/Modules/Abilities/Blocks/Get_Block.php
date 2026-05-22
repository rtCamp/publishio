<?php
/**
 * Get Block ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\Abilities\Blocks
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Abilities\Blocks;

/**
 * Class - Get_Block
 */
class Get_Block {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'rtcamp-publish-with-ai/get-block',
			[
				'label'               => __( 'Get Block Details', 'rtcamp-publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\Abilities\Categories\Blocks::SLUG,
				'description'         => __( 'Returns full details of a single registered block type including attributes, example, supports, and styles.', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'name' ],
					'properties'           => [
						'name' => [
							'type'        => 'string',
							'description' => 'The fully-qualified block name (e.g. my-plugin/my-block).',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'required'   => [ 'name', 'title' ],
					'properties' => [
						'name'             => [
							'type'        => 'string',
							'description' => 'Fully qualified block name.',
						],
						'title'            => [
							'type'        => 'string',
							'description' => 'Human-readable block title.',
						],
						'description'      => [
							'type'        => 'string',
							'description' => 'Block description.',
						],
						'category'         => [
							'type'        => 'string',
							'description' => 'Block category slug.',
						],
						'icon'             => [
							'type'        => 'string',
							'description' => 'Block icon.',
						],
						'keywords'         => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => 'Keywords for discovery.',
						],
						'parent'           => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => 'Allowed parent block types.',
						],
						'ancestor'         => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => 'Required ancestor block types.',
						],
						'allowed_blocks'   => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => 'Allowed child block types.',
						],
						'attributes'       => [
							'type'        => 'object',
							'description' => 'Block attributes schema definition.',
						],
						'supports'         => [
							'type'        => 'object',
							'description' => 'Block editor supports configuration.',
						],
						'styles'           => [
							'type'        => 'array',
							'items'       => [ 'type' => 'object' ],
							'description' => 'Registered block styles.',
						],
						'example'          => [
							'type'        => 'object',
							'description' => 'Example data for block preview.',
						],
						'variations'       => [
							'type'        => 'array',
							'items'       => [ 'type' => 'object' ],
							'description' => 'Block variations.',
						],
						'uses_context'     => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => 'Context values this block uses.',
						],
						'provides_context' => [
							'type'        => 'object',
							'description' => 'Context values this block provides.',
						],
						'api_version'      => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'Block API version.',
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ) {
					$name = sanitize_text_field( $input['name'] ?? '' );

					if ( ! $name ) {
						return new \WP_Error( 'missing_name', __( 'Block name is required.', 'rtcamp-publish-with-ai' ) );
					}

					$registry   = \WP_Block_Type_Registry::get_instance();
					$block_type = $registry->get_registered( $name );

					if ( ! $block_type ) {
						return new \WP_Error(
							'block_not_found',
							sprintf(
								/* translators: %s: block name */
								__( 'No block found with name "%s".', 'rtcamp-publish-with-ai' ),
								$name
							)
						);
					}

					$icon = $block_type->icon ?? '';
					if ( is_array( $icon ) ) {
						$icon = $icon['src'] ?? '';
					}

					return [
						'name'             => $name,
						'title'            => $block_type->title,
						'description'      => $block_type->description,
						'category'         => $block_type->category ?? '',
						'icon'             => is_string( $icon ) ? $icon : '',
						'keywords'         => $block_type->keywords,
						'parent'           => $block_type->parent ?? [],
						'ancestor'         => $block_type->ancestor ?? [],
						'allowed_blocks'   => $block_type->allowed_blocks ?? [],
						'attributes'       => $block_type->attributes ?? [],
						'supports'         => $block_type->supports ?? [],
						'styles'           => $block_type->styles,
						'example'          => $block_type->example ?? [],
						'variations'       => $block_type->variations ?? [],
						'uses_context'     => $block_type->uses_context ?? [],
						'provides_context' => $block_type->provides_context ?? [],
						'api_version'      => $block_type->api_version,
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
