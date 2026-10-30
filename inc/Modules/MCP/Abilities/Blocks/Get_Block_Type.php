<?php
/**
 * Get block type ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Blocks
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Blocks;

use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Abilities\Categories\Blocks as Blocks_Category;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Catalog;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Path;

/**
 * Class - Get_Block_Type
 */
final class Get_Block_Type extends Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'get-block-type';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Get Block Type', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Everything needed to write one block: its attributes (in_html means the value lives in the block\'s HTML, not the comment), supports, where it may go (parent, ancestor, allowed_blocks), styles and variations. example_markup is the block copied from a registered pattern, so it is real editor output: start from it instead of writing the block from scratch. dynamic blocks are rendered by the server and usually save a self-closing comment.', 'publishio' );
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
			'required'             => [ 'name' ],
			'properties'           => [
				'name' => [
					'type'        => 'string',
					'pattern'     => '^(?:[a-z0-9-]+/)?[a-z0-9-]+$',
					'maxLength'   => 200,
					'description' => 'The block name, such as "heading" or "acme/pricing-table". A name without a namespace means core/.',
				],
			],
			'additionalProperties' => false,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema(): array {
		$string  = [ 'type' => 'string' ];
		$strings = [
			'type'  => 'array',
			'items' => $string,
		];

		return [
			'type'       => 'object',
			'required'   => [ 'name', 'title', 'attributes', 'supports', 'parent', 'ancestor', 'dynamic', 'example_markup' ],
			'properties' => [
				'name'           => $string,
				'title'          => $string,
				'description'    => $string,
				'category'       => $string,
				'source'         => $string,
				'dynamic'        => [ 'type' => 'boolean' ],
				'attributes'     => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'required'   => [ 'name', 'in_html' ],
						'properties' => [
							'name'    => $string,
							'in_html' => [ 'type' => 'boolean' ],
						],
					],
				],
				'supports'       => [ 'type' => 'object' ],
				'parent'         => $strings,
				'ancestor'       => $strings,
				'allowed_blocks' => [
					'type'  => [ 'array', 'null' ],
					'items' => $string,
				],
				'styles'         => $strings,
				'variations'     => [
					'type'  => 'array',
					'items' => [ 'type' => 'object' ],
				],
				'example_markup' => [ 'type' => [ 'string', 'null' ] ],
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
	protected function execute( array $input ): array|\WP_Error {
		return Block_Catalog::type( Block_Path::full_name( (string) ( $input['name'] ?? '' ) ) );
	}
}
