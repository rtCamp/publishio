<?php
/**
 * Get Custom Blocks ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Blocks
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Blocks;

/**
 * Class - Get_Custom_Blocks
 */
class Get_Custom_Blocks {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'rtpwai/get-custom-blocks',
			[
				'label'               => __( 'Get Custom Blocks', 'rtcamp-publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Blocks::SLUG,
				'description'         => __( 'Returns a list of all registered custom blocks (excludes core blocks). These are blocks added by plugins and themes.', 'rtcamp-publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'keyword' => [
							'type'        => 'string',
							'description' => 'Filter by keyword (optional, case-insensitive substring match against block name or title).',
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'required'   => [ 'name', 'title' ],
						'properties' => [
							'name'        => [
								'type'        => 'string',
								'description' => 'Fully qualified block name (namespace/slug).',
							],
							'title'       => [
								'type'        => 'string',
								'description' => 'Human-readable block title.',
							],
							'description' => [
								'type'        => 'string',
								'description' => 'Block description.',
							],
							'category'    => [
								'type'        => 'string',
								'description' => 'Block category slug.',
							],
							'icon'        => [
								'type'        => 'string',
								'description' => 'Block icon (dashicon name or SVG).',
							],
							'keywords'    => [
								'type'        => 'array',
								'items'       => [ 'type' => 'string' ],
								'description' => 'Keywords for block discovery.',
							],
							'parent'      => [
								'type'        => 'array',
								'items'       => [ 'type' => 'string' ],
								'description' => 'Parent block types this block is allowed within.',
							],
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ): array {
					$registry = \WP_Block_Type_Registry::get_instance();
					$blocks   = $registry->get_all_registered();

					$keyword_filter = isset( $input['keyword'] ) ? strtolower( sanitize_text_field( $input['keyword'] ) ) : '';

					$result = [];

					foreach ( $blocks as $block_name => $block_type ) {
						// Skip core blocks.
						if ( str_starts_with( $block_name, 'core/' ) ) {
							continue;
						}

						if ( $keyword_filter ) {
							$title_hit = str_contains( strtolower( $block_type->title ), $keyword_filter );
							$name_hit  = str_contains( strtolower( $block_name ), $keyword_filter );
							$keywords  = array_map( 'strtolower', $block_type->keywords );
							$kw_hit    = (bool) array_filter( $keywords, static fn ( $kw ) => str_contains( $kw, $keyword_filter ) );

							if ( ! $title_hit && ! $name_hit && ! $kw_hit ) {
								continue;
							}
						}

						$icon = $block_type->icon ?? '';
						if ( is_array( $icon ) ) {
							$icon = $icon['src'] ?? '';
						}

						$result[] = [
							'name'        => $block_name,
							'title'       => $block_type->title,
							'description' => $block_type->description,
							'category'    => $block_type->category ?? '',
							'icon'        => is_string( $icon ) ? $icon : '',
							'keywords'    => $block_type->keywords,
							'parent'      => $block_type->parent ?? [],
						];
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
					],
				],
			]
		);
	}
}
