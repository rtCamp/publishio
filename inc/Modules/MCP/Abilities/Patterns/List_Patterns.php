<?php
/**
 * List patterns ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Patterns
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Patterns;

use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Abilities\Categories\Patterns as Patterns_Category;
use rtCamp\Publishio\Modules\MCP\Editing\Pattern_Library;

/**
 * Class - List_Patterns
 */
final class List_Patterns extends Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'list-patterns';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'List Patterns', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Finds patterns to build with: the theme\'s, plugins\', WordPress\'s, and the user\'s own from the pattern library (source "user", named core/block/{id}). A synced pattern stays the same everywhere it is used. With post_type, lists only the patterns offered there. No markup: use get-pattern to see one pattern\'s blocks, or insert-pattern to add it to a post.', 'publishio' );
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
			'properties'           => [
				'search'    => [
					'type'        => 'string',
					'maxLength'   => 100,
					'description' => 'Words to match in a pattern\'s name, title, description or categories.',
				],
				'category'  => [
					'type'        => 'string',
					'maxLength'   => 100,
					'description' => 'A pattern category slug, such as "banner" or "call-to-action".',
				],
				'source'    => [
					'type'        => 'string',
					'enum'        => Pattern_Library::SOURCES,
					'description' => 'Only the theme\'s, plugins\', WordPress\'s, or the user\'s saved patterns.',
				],
				'synced'    => [
					'type'        => 'boolean',
					'description' => 'Only synced, or only unsynced, patterns.',
				],
				'post_type' => [
					'type'        => 'string',
					'maxLength'   => 20,
					'description' => 'Only patterns offered for this post type.',
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
			'required'   => [ 'patterns', 'categories' ],
			'properties' => [
				'patterns'   => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'required'   => [ 'name', 'title', 'description', 'categories', 'source', 'synced' ],
						'properties' => [
							'name'        => $string,
							'title'       => $string,
							'description' => $string,
							'categories'  => $strings,
							'source'      => $string,
							'synced'      => [ 'type' => 'boolean' ],
						],
					],
				],
				'categories' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'required'   => [ 'slug', 'label', 'count' ],
						'properties' => [
							'slug'  => $string,
							'label' => $string,
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
				'category'  => isset( $input['category'] ) ? sanitize_title( (string) $input['category'] ) : null,
				'source'    => isset( $input['source'] ) ? (string) $input['source'] : null,
				'synced'    => isset( $input['synced'] ) ? (bool) $input['synced'] : null,
				'post_type' => isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : null,
			],
			static fn ( mixed $value ): bool => null !== $value && '' !== $value
		);

			$patterns = Pattern_Library::matching( $filters );

			return [
				'patterns'   => array_map(
					static fn ( array $pattern ): array => [
						'name'        => $pattern['name'],
						'title'       => $pattern['title'],
						'description' => $pattern['description'],
						'categories'  => $pattern['categories'],
						'source'      => $pattern['source'],
						'synced'      => $pattern['synced'],
					],
					$patterns
				),
				'categories' => Pattern_Library::categories( $patterns ),
			];
	}
}
