<?php
/**
 * The block types a post can use, and what is needed to write them.
 *
 * @package rtCamp\Publishio\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Editing;

/**
 * Class - Block_Catalog
 *
 * @phpstan-type Summary array{name: string, title: string, description: string, category: string, source: string}
 */
final class Block_Catalog {
	public const SOURCES = [ 'core', 'theme', 'plugin' ];

	private const MAX_EXAMPLE = 6000;

	/**
	 * Block types shown in the inserter, matching the filters.
	 *
	 * @param array{search?: string, category?: string, source?: string, post_type?: string} $filters The filters.
	 *
	 * @return list<Summary>
	 */
	public static function types( array $filters ): array {
		$search  = strtolower( trim( (string) ( $filters['search'] ?? '' ) ) );
		$allowed = isset( $filters['post_type'] ) ? self::allowed_for( (string) $filters['post_type'] ) : true;
		$types   = [];

		foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
			if ( false === ( $type->supports['inserter'] ?? true ) ) {
				continue;
			}

			if ( true !== $allowed && ! ( is_array( $allowed ) && in_array( $name, $allowed, true ) ) ) {
				continue;
			}

			$source = self::source_of( $type );

			if ( ( isset( $filters['category'] ) && $filters['category'] !== $type->category ) || ( isset( $filters['source'] ) && $filters['source'] !== $source ) ) {
				continue;
			}

			if ( '' !== $search && ! self::matches( $type, $search ) ) {
				continue;
			}

			$types[] = [
				'name'        => (string) $name,
				'title'       => (string) $type->title,
				'description' => (string) $type->description,
				'category'    => (string) $type->category,
				'source'      => $source,
			];
		}

		return $types;
	}

	/**
	 * The block categories, with how many of the listed types are in each.
	 *
	 * @param array<int, Summary> $types The listed types.
	 *
	 * @return list<array{slug: string, title: string, count: int}>
	 */
	public static function categories( array $types ): array {
		$counts     = array_count_values( array_column( $types, 'category' ) );
		$categories = [];

		foreach ( get_default_block_categories() as $category ) {
			$slug = (string) $category['slug'];

			if ( isset( $counts[ $slug ] ) ) {
				$categories[] = [
					'slug'  => $slug,
					'title' => (string) $category['title'],
					'count' => $counts[ $slug ],
				];
				unset( $counts[ $slug ] );
			}
		}

		foreach ( $counts as $slug => $count ) {
			$categories[] = [
				'slug'  => (string) $slug,
				'title' => ucfirst( (string) $slug ),
				'count' => $count,
			];
		}

		return $categories;
	}

	/**
	 * Everything needed to write a block.
	 *
	 * @param string $name The block's full name.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function type( string $name ): array|\WP_Error {
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( $name );

		if ( ! $type ) {
			/* translators: %s: block name */
			return Errors::make( 'unknown_block', sprintf( __( '%s isn\'t a registered block on this site. Use list-block-types to find the blocks you can use.', 'publishio' ), $name ), 404 );
		}

		$attributes = [];

		foreach ( (array) $type->get_attributes() as $attribute => $definition ) {
			if ( ! is_array( $definition ) || in_array( $attribute, [ 'lock', 'metadata' ], true ) ) {
				continue;
			}

			$attributes[] = array_filter(
				[
					'name'    => (string) $attribute,
					'type'    => $definition['type'] ?? null,
					'enum'    => $definition['enum'] ?? null,
					'default' => $definition['default'] ?? null,
					'in_html' => is_string( $definition['source'] ?? null ) && 'meta' !== $definition['source'],
				],
				static fn ( mixed $value ): bool => null !== $value
			);
		}

		return [
			'name'           => $type->name,
			'title'          => (string) $type->title,
			'description'    => (string) $type->description,
			'category'       => (string) $type->category,
			'source'         => self::source_of( $type ),
			'dynamic'        => $type->is_dynamic(),
			'attributes'     => $attributes,
			'supports'       => (array) $type->supports,
			'parent'         => array_values( (array) $type->parent ),
			'ancestor'       => array_values( (array) $type->ancestor ),
			'allowed_blocks' => is_array( $type->allowed_blocks ) ? array_values( $type->allowed_blocks ) : null,
			'styles'         => array_values( array_filter( array_map( static fn ( $style ): string => is_array( $style ) ? (string) ( $style['name'] ?? '' ) : '', (array) $type->styles ) ) ),
			'variations'     => array_values(
				array_map(
					static fn ( array $variation ): array => [
						'name'  => (string) ( $variation['name'] ?? '' ),
						'title' => (string) ( $variation['title'] ?? '' ),
					],
					array_filter( (array) $type->get_variations(), 'is_array' )
				)
			),
			'example_markup' => self::example( $type->name ),
		];
	}

	/**
	 * The blocks allowed in a post type: true for all, or a list of names.
	 *
	 * @param string $post_type The post type.
	 *
	 * @return true|list<string>
	 */
	public static function allowed_for( string $post_type ): bool|array {
		$allowed = get_allowed_block_types(
			new \WP_Block_Editor_Context(
				[
					'post' => new \WP_Post(
						(object) [
							'ID'        => 0,
							'post_type' => $post_type,
							'filter'    => 'raw',
						]
					),
				]
			)
		);

		if ( is_array( $allowed ) ) {
			return array_values( array_map( 'strval', $allowed ) );
		}

		return true === $allowed ? true : [];
	}

	/**
	 * Where a block comes from: core, the active theme, or a plugin.
	 *
	 * A theme's blocks load their scripts and styles from the theme's folder.
	 *
	 * @param \WP_Block_Type $type The block type.
	 */
	public static function source_of( \WP_Block_Type $type ): string {
		if ( str_starts_with( $type->name, 'core/' ) ) {
			return 'core';
		}

		$handles = array_merge( $type->editor_script_handles, $type->script_handles, $type->view_script_handles, $type->editor_style_handles, $type->style_handles );
		$themes  = array_unique( [ trailingslashit( get_stylesheet_directory_uri() ), trailingslashit( get_template_directory_uri() ) ] );

		foreach ( $handles as $handle ) {
			$asset = wp_scripts()->query( $handle ) ?: wp_styles()->query( $handle );
			$src   = $asset instanceof \_WP_Dependency && is_string( $asset->src ) ? $asset->src : '';

			foreach ( $themes as $theme ) {
				if ( '' !== $src && str_starts_with( $src, $theme ) ) {
					return 'theme';
				}
			}
		}

		return 'plugin';
	}

	/**
	 * Real editor output for a block, copied from the first registered pattern that uses it.
	 *
	 * @param string $name The block's full name.
	 */
	public static function example( string $name ): ?string {
		$needle = '<!-- wp:' . Block_Path::short_name( $name ) . ' ';
		$closed = '<!-- wp:' . Block_Path::short_name( $name ) . ' /-->';
		$bare   = '<!-- wp:' . Block_Path::short_name( $name ) . ' -->';

		foreach ( \WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern ) {
			$content = (string) ( $pattern['content'] ?? '' );

			if ( ! str_contains( $content, $needle ) && ! str_contains( $content, $closed ) && ! str_contains( $content, $bare ) ) {
				continue;
			}

			$block = self::first_named( parse_blocks( $content ), $name );

			if ( null !== $block ) {
				$markup = serialize_block( $block );

				if ( strlen( $markup ) <= self::MAX_EXAMPLE ) {
					return $markup;
				}
			}
		}

		return null;
	}

	/**
	 * Whether a block type matches a search, by name, title, keywords or description.
	 *
	 * @param \WP_Block_Type $type   The block type.
	 * @param string         $search The lowercase search.
	 */
	private static function matches( \WP_Block_Type $type, string $search ): bool {
		$haystack = strtolower( implode( ' ', [ $type->name, (string) $type->title, (string) $type->description, implode( ' ', (array) $type->keywords ) ] ) );

		return str_contains( $haystack, $search );
	}

	/**
	 * The first block with a name, depth first.
	 *
	 * @param array<int|string, array<string, mixed>> $blocks Parsed blocks.
	 * @param string                                  $name   The block's full name.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function first_named( array $blocks, string $name ): ?array {
		foreach ( $blocks as $block ) {
			if ( ( $block['blockName'] ?? null ) === $name ) {
				return $block;
			}

			$found = self::first_named( (array) ( $block['innerBlocks'] ?? [] ), $name );

			if ( null !== $found ) {
				return $found;
			}
		}

		return null;
	}
}
