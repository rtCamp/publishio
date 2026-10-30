<?php
/**
 * Get pattern ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Patterns
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Patterns;

use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Abilities\Categories\Patterns as Patterns_Category;
use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Catalog;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Path;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Tree;
use rtCamp\Publishio\Modules\MCP\Editing\Outline;
use rtCamp\Publishio\Modules\MCP\Editing\Pattern_Library;
use rtCamp\Publishio\Modules\MCP\Editing\Pattern_Overrides;

/**
 * Class - Get_Pattern
 */
final class Get_Pattern extends Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'get-pattern';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Get Pattern', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'One pattern in full: its markup, an outline of its blocks and the blocks it uses. For a synced pattern, overrides lists the fields a post can set in the reference\'s content attribute. With post_type, not_allowed lists the blocks that post type can\'t use. To add the pattern as it is, use insert-pattern instead of copying the markup.', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function category(): string {
		return Patterns_Category::SLUG;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'required'             => [ 'name' ],
			'properties'           => [
				'name'      => Schema::pattern_name(),
				'post_type' => [
					'type'        => 'string',
					'maxLength'   => 20,
					'description' => 'The post type the pattern is for, to list the blocks it can\'t use.',
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
			'required'   => [ 'name', 'title', 'source', 'synced', 'markup', 'outline', 'blocks_used', 'not_allowed', 'overrides' ],
			'properties' => [
				'name'        => $string,
				'title'       => $string,
				'description' => $string,
				'categories'  => $strings,
				'source'      => $string,
				'synced'      => [ 'type' => 'boolean' ],
				'post_types'  => $strings,
				'edit_url'    => [ 'type' => [ 'string', 'null' ] ],
				'markup'      => $string,
				'outline'     => Schema::outline(),
				'blocks_used' => $strings,
				'not_allowed' => $strings,
				'overrides'   => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'required'   => [ 'name', 'block', 'attributes' ],
						'properties' => [
							'name'       => $string,
							'block'      => $string,
							'attributes' => $strings,
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
	protected function execute( array $input ): array|\WP_Error {
		$pattern = Pattern_Library::find( (string) ( $input['name'] ?? '' ) );

		if ( is_wp_error( $pattern ) ) {
			return $pattern;
		}

		$tree    = Block_Tree::of( Pattern_Library::blocks( $pattern ) );
		$outline = Outline::of( $tree, Block_Path::root() );
		$used    = self::names( $tree->blocks() );

		$overrides = [];

		if ( $pattern['synced'] ) {
			$fields = Pattern_Overrides::fields( $pattern['id'] );

			foreach ( is_wp_error( $fields ) ? [] : $fields as $name => $field ) {
				$overrides[] = [ 'name' => (string) $name ] + $field;
			}
		}

		$not_allowed = [];

		if ( isset( $input['post_type'] ) ) {
			$allowed     = Block_Catalog::allowed_for( sanitize_key( (string) $input['post_type'] ) );
			$inserted    = $pattern['synced'] ? [ 'core/block' ] : $used;
			$not_allowed = true === $allowed ? [] : array_values( array_diff( $inserted, $allowed ) );
		}

		$edit_url = $pattern['id'] > 0 ? get_edit_post_link( $pattern['id'], 'raw' ) : null;

		return [
			'name'        => $pattern['name'],
			'title'       => $pattern['title'],
			'description' => $pattern['description'],
			'categories'  => $pattern['categories'],
			'source'      => $pattern['source'],
			'synced'      => $pattern['synced'],
			'post_types'  => $pattern['post_types'],
			'edit_url'    => $edit_url ? $edit_url : null,
			'markup'      => $pattern['markup'],
			'outline'     => is_wp_error( $outline ) ? [
				'rows'      => [],
				'truncated' => false,
			] : $outline,
			'blocks_used' => $used,
			'not_allowed' => $not_allowed,
			'overrides'   => $overrides,
		];
	}

	/**
	 * The distinct block names in blocks and everything inside them.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 *
	 * @return list<string>
	 */
	private static function names( array $blocks ): array {
		$names = [];

		foreach ( $blocks as $block ) {
			$names[] = Block_Tree::name_of( $block );
			array_push( $names, ...self::names( (array) ( $block['innerBlocks'] ?? [] ) ) );
		}

		return array_values( array_unique( $names ) );
	}
}
