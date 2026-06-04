<?php
/**
 * Get Patterns ability.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns;

/**
 * Class - Get_Patterns
 */
class Get_Patterns {
	/**
	 * Register the ability.
	 */
	public function register(): void {
		wp_register_ability(
			'pwai/get-patterns',
			[
				'label'               => __( 'Get All Patterns', 'publish-with-ai' ),
				'category'            => \rtCamp\Publish_With_AI\Modules\MCP\Abilities\Categories\Patterns::SLUG,
				'description'         => __( 'Returns a list of all registered block patterns with metadata. Pattern content is excluded.', 'publish-with-ai' ),
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'category' => [
							'type'        => 'string',
							'description' => 'Filter by category slug (optional).',
						],
						'keyword'  => [
							'type'        => 'string',
							'description' => 'Filter by keyword (optional, case-insensitive substring match against title and keywords).',
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
							'name'           => [
								'type'        => 'string',
								'description' => 'Unique pattern name (namespace/slug).',
							],
							'title'          => [
								'type'        => 'string',
								'description' => 'Human-readable pattern title.',
							],
							'description'    => [
								'type'        => 'string',
								'description' => 'Optional pattern description.',
							],
							'categories'     => [
								'type'        => 'array',
								'items'       => [ 'type' => 'string' ],
								'description' => 'Category slugs this pattern belongs to.',
							],
							'keywords'       => [
								'type'        => 'array',
								'items'       => [ 'type' => 'string' ],
								'description' => 'Keywords for pattern discovery.',
							],
							'block_types'    => [
								'type'        => 'array',
								'items'       => [ 'type' => 'string' ],
								'description' => 'Block types this pattern is intended for.',
							],
							'post_types'     => [
								'type'        => 'array',
								'items'       => [ 'type' => 'string' ],
								'description' => 'Post types this pattern is restricted to.',
							],
							'template_types' => [
								'type'        => 'array',
								'items'       => [ 'type' => 'string' ],
								'description' => 'Template types this pattern is intended for.',
							],
							'inserter'       => [
								'type'        => 'boolean',
								'description' => 'Whether the pattern appears in the block inserter.',
							],
							'source'         => [
								'type'        => 'string',
								'description' => 'Pattern source (core, plugin, theme, pattern-directory).',
							],
						],
					],
				],
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ): array {
					$registry = \WP_Block_Patterns_Registry::get_instance();
					$patterns = $registry->get_all_registered();

					$category_filter = isset( $input['category'] ) ? sanitize_text_field( $input['category'] ) : '';
					$keyword_filter  = isset( $input['keyword'] ) ? strtolower( sanitize_text_field( $input['keyword'] ) ) : '';

					$result = [];

					foreach ( $patterns as $pattern ) {
						if ( $category_filter ) {
							$cats = $pattern['categories'] ?? [];
							if ( ! in_array( $category_filter, $cats, true ) ) {
								continue;
							}
						}

						if ( $keyword_filter ) {
							$keywords  = array_map( 'strtolower', $pattern['keywords'] ?? [] );
							$title_hit = str_contains( strtolower( $pattern['title'] ?? '' ), $keyword_filter );
							$kw_hit    = (bool) array_filter( $keywords, static fn ( $kw ) => str_contains( $kw, $keyword_filter ) );

							if ( ! $title_hit && ! $kw_hit ) {
								continue;
							}
						}

						$result[] = [
							'name'           => $pattern['name'] ?? '',
							'title'          => $pattern['title'] ?? '',
							'description'    => $pattern['description'] ?? '',
							'categories'     => $pattern['categories'] ?? [],
							'keywords'       => $pattern['keywords'] ?? [],
							'block_types'    => $pattern['blockTypes'] ?? [],
							'post_types'     => $pattern['postTypes'] ?? [],
							'template_types' => $pattern['templateTypes'] ?? [],
							'inserter'       => $pattern['inserter'] ?? true,
							'source'         => $pattern['source'] ?? '',
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
