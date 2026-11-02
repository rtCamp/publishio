<?php
/**
 * List block types ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Blocks
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Blocks;

use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Abilities\Categories\Blocks as Blocks_Category;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Catalog;

/**
 * Class - List_Block_Types
 */
final class List_Block_Types extends Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'list-block-types';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'List Block Types', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Finds the blocks this site offers: core, theme and plugin blocks. With post_type, lists only the blocks allowed there. Blocks hidden from the inserter are left out. Use get-block-type for a block\'s attributes and example markup.', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function category(): string {
		return Blocks_Category::SLUG;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'search'    => [
					'type'        => 'string',
					'maxLength'   => 100,
					'description' => 'Words to match in a block\'s name, title, keywords or description.',
				],
				'category'  => [
					'type'        => 'string',
					'maxLength'   => 100,
					'description' => 'A block category slug, such as "text" or "media".',
				],
				'source'    => [
					'type'        => 'string',
					'enum'        => Block_Catalog::SOURCES,
					'description' => 'Only core blocks, the active theme\'s blocks, or plugin blocks.',
				],
				'post_type' => [
					'type'        => 'string',
					'maxLength'   => 20,
					'description' => 'Only blocks allowed in this post type.',
				],
			],
			'additionalProperties' => false,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema(): array {
		$string = [ 'type' => 'string' ];

		return [
			'type'       => 'object',
			'required'   => [ 'block_types', 'categories' ],
			'properties' => [
				'block_types' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'required'   => [ 'name', 'title', 'description', 'category', 'source' ],
						'properties' => [
							'name'        => $string,
							'title'       => $string,
							'description' => $string,
							'category'    => $string,
							'source'      => $string,
						],
					],
				],
				'categories'  => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'required'   => [ 'slug', 'title', 'count' ],
						'properties' => [
							'slug'  => $string,
							'title' => $string,
							'count' => [ 'type' => 'integer' ],
						],
					],
				],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function annotations(): array {
		return [
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute( array $input ): array {
		$filters = array_filter(
			[
				'search'    => isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : null,
				'category'  => isset( $input['category'] ) ? sanitize_key( (string) $input['category'] ) : null,
				'source'    => isset( $input['source'] ) ? (string) $input['source'] : null,
				'post_type' => isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : null,
			],
			static fn ( ?string $value ): bool => null !== $value && '' !== $value
		);

			$types = Block_Catalog::types( $filters );

			return [
				'block_types' => $types,
				'categories'  => Block_Catalog::categories( $types ),
			];
	}
}
