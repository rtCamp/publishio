<?php
/**
 * The patterns a post can use: registered patterns and the user's saved patterns.
 *
 * @package rtCamp\Publishio\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Editing;

/**
 * Class - Pattern_Library
 *
 * Registered patterns come from core, the theme and plugins, and are copied into a post. Saved patterns are wp_block posts named core/block/{id}, as in the editor. A synced one goes into a post as a reference, so it stays the same everywhere it's used.
 *
 * @phpstan-type Pattern array{name: string, id: int, title: string, description: string, categories: list<string>, source: string, synced: bool, post_types: list<string>, markup: string}
 */
final class Pattern_Library {
	public const SOURCES = [ 'theme', 'plugin', 'core', 'user' ];

	public const CATEGORY_TAXONOMY = 'wp_pattern_category';

	public const SYNC_META = 'wp_pattern_sync_status';

	public const USER_PREFIX = 'core/block/';

	private const MAX_SAVED = 500;

	/**
	 * The patterns shown in the inserter, matching the filters.
	 *
	 * @param array{search?: string, category?: string, source?: string, synced?: bool, post_type?: string} $filters The filters.
	 *
	 * @return list<Pattern>
	 */
	public static function matching( array $filters ): array {
		$search   = strtolower( trim( (string) ( $filters['search'] ?? '' ) ) );
		$patterns = [];

		foreach ( [ ...self::registered(), ...self::saved() ] as $pattern ) {
			if ( isset( $filters['category'] ) && ! in_array( $filters['category'], $pattern['categories'], true ) ) {
				continue;
			}

			if ( isset( $filters['source'] ) && $filters['source'] !== $pattern['source'] ) {
				continue;
			}

			if ( isset( $filters['synced'] ) && $filters['synced'] !== $pattern['synced'] ) {
				continue;
			}

			if ( isset( $filters['post_type'] ) && ! self::is_for( $pattern, (string) $filters['post_type'] ) ) {
				continue;
			}

			if ( '' !== $search && ! str_contains( strtolower( implode( ' ', [ $pattern['name'], $pattern['title'], $pattern['description'], ...$pattern['categories'] ] ) ), $search ) ) {
				continue;
			}

			$patterns[] = $pattern;
		}

		return $patterns;
	}

	/**
	 * One pattern by name: a registered name such as "theme/hero", or core/block/{id} for a saved pattern.
	 *
	 * @param string $name The pattern name.
	 *
	 * @return Pattern|\WP_Error
	 */
	public static function find( string $name ): array|\WP_Error {
		if ( str_starts_with( $name, self::USER_PREFIX ) ) {
			$id   = substr( $name, strlen( self::USER_PREFIX ) );
			$post = ctype_digit( $id ) ? get_post( (int) $id ) : null;

			if ( $post instanceof \WP_Post && 'wp_block' === $post->post_type && ! in_array( $post->post_status, [ 'trash', 'auto-draft' ], true ) && current_user_can( 'read_post', $post->ID ) ) {
				return self::from_post( $post );
			}
		} else {
			$registered = \WP_Block_Patterns_Registry::get_instance()->get_registered( $name );

			if ( is_array( $registered ) ) {
				return self::from_registry( $registered );
			}
		}

		/* translators: %s: pattern name */
		return Errors::make( 'unknown_pattern', sprintf( __( '"%s" isn\'t a pattern on this site. Use list-patterns to find one.', 'publishio' ), $name ), 404 );
	}

	/**
	 * The pattern categories used by a list of patterns, with how many patterns are in each.
	 *
	 * @param array<int, Pattern> $patterns The patterns.
	 *
	 * @return list<array{slug: string, label: string, count: int}>
	 */
	public static function categories( array $patterns ): array {
		$counts = array_count_values( array_merge( ...array_map( static fn ( array $pattern ): array => $pattern['categories'], $patterns ) ) );
		$labels = [];

		foreach ( \WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered() as $category ) {
			$labels[ (string) $category['name'] ] = (string) ( $category['label'] ?? $category['name'] );
		}

		$categories = [];

		foreach ( $counts as $slug => $count ) {
			$slug = (string) $slug;

			if ( ! isset( $labels[ $slug ] ) ) {
				$term            = get_term_by( 'slug', $slug, self::CATEGORY_TAXONOMY );
				$labels[ $slug ] = $term instanceof \WP_Term ? $term->name : ucfirst( str_replace( [ '-', '_' ], ' ', $slug ) );
			}

			$categories[] = [
				'slug'  => $slug,
				'label' => $labels[ $slug ],
				'count' => $count,
			];
		}

		return $categories;
	}

	/**
	 * The blocks that inserting a pattern adds: a reference for a synced pattern, otherwise a copy of its blocks with nested patterns resolved, as the editor inserts them.
	 *
	 * @param array<string, mixed> $pattern The pattern.
	 * @phpstan-param Pattern $pattern
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function blocks_to_insert( array $pattern ): array {
		return $pattern['synced'] ? [ self::reference( $pattern['id'] ) ] : self::blocks( $pattern );
	}

	/**
	 * A pattern's own blocks, with nested patterns resolved.
	 *
	 * @param array<string, mixed> $pattern The pattern.
	 * @phpstan-param Pattern $pattern
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function blocks( array $pattern ): array {
		return Block_Tree::parse( serialize_blocks( resolve_pattern_blocks( parse_blocks( $pattern['markup'] ) ) ) );
	}

	/**
	 * A reference block to a synced pattern.
	 *
	 * @param int $id The wp_block post ID.
	 *
	 * @return array<string, mixed>
	 */
	public static function reference( int $id ): array {
		return [
			'blockName'    => 'core/block',
			'attrs'        => [ 'ref' => $id ],
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];
	}

	/**
	 * Check pattern category names for a new pattern. Missing categories are created on save, which needs the right to manage them.
	 *
	 * @param array<int, string> $names Category names or slugs.
	 *
	 * @return list<string>|\WP_Error The names to save.
	 */
	public static function check_categories( array $names ): array|\WP_Error {
		$taxonomy = get_taxonomy( self::CATEGORY_TAXONOMY );
		$names    = array_values( array_unique( array_filter( array_map( static fn ( string $name ): string => sanitize_text_field( $name ), $names ) ) ) );

		if ( [] === $names ) {
			return [];
		}

		if ( ! $taxonomy instanceof \WP_Taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- The taxonomy's own capability.
			return Errors::make( 'forbidden', __( 'Your account can\'t add patterns to categories. Leave categories out.', 'publishio' ), 403 );
		}

		foreach ( $names as $name ) {
			if ( ! term_exists( $name, self::CATEGORY_TAXONOMY ) && ! current_user_can( $taxonomy->cap->edit_terms ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- The taxonomy's own capability.
				/* translators: %s: category name */
				return Errors::make( 'invalid_category', sprintf( __( 'There\'s no pattern category "%s", and your account can\'t add one. Use a category from list-patterns with source: user, or leave it out.', 'publishio' ), $name ) );
			}
		}

		return $names;
	}

	/**
	 * Whether a pattern is offered for a post type. A pattern without post types is offered everywhere.
	 *
	 * @param array<string, mixed> $pattern   The pattern.
	 * @param string               $post_type The post type.
	 * @phpstan-param Pattern $pattern
	 */
	public static function is_for( array $pattern, string $post_type ): bool {
		return [] === $pattern['post_types'] || in_array( $post_type, $pattern['post_types'], true );
	}

	/**
	 * Where a pattern comes from, as a word for messages.
	 *
	 * @param array<string, mixed> $pattern The pattern.
	 * @phpstan-param Pattern $pattern
	 */
	public static function source_label( array $pattern ): string {
		return match ( $pattern['source'] ) {
			'theme' => __( 'the theme', 'publishio' ),
			'core'  => __( 'WordPress', 'publishio' ),
			'user'  => __( 'the pattern library', 'publishio' ),
			default => __( 'a plugin', 'publishio' ),
		};
	}

	/**
	 * Registered patterns shown in the inserter.
	 *
	 * @return list<Pattern>
	 */
	private static function registered(): array {
		$patterns = [];

		foreach ( \WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern ) {
			if ( false !== ( $pattern['inserter'] ?? true ) ) {
				$patterns[] = self::from_registry( $pattern );
			}
		}

		return $patterns;
	}

	/**
	 * The published patterns saved in the pattern library that the user can read.
	 *
	 * @return list<Pattern>
	 */
	private static function saved(): array {
		$query = new \WP_Query(
			[
				'post_type'              => 'wp_block',
				'post_status'            => 'publish',
				'posts_per_page'         => self::MAX_SAVED,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			]
		);

		$patterns = [];

		foreach ( $query->posts as $post ) {
			if ( $post instanceof \WP_Post && current_user_can( 'read_post', $post->ID ) ) {
				$patterns[] = self::from_post( $post );
			}
		}

		return $patterns;
	}

	/**
	 * A registered pattern in the common shape.
	 *
	 * @param array<string, mixed> $pattern The registered pattern.
	 *
	 * @return Pattern
	 */
	private static function from_registry( array $pattern ): array {
		return [
			'name'        => (string) ( $pattern['name'] ?? '' ),
			'id'          => 0,
			'title'       => (string) ( $pattern['title'] ?? '' ),
			'description' => (string) ( $pattern['description'] ?? '' ),
			'categories'  => array_values( array_map( 'strval', (array) ( $pattern['categories'] ?? [] ) ) ),
			'source'      => self::registry_source( $pattern ),
			'synced'      => false,
			'post_types'  => array_values( array_map( 'strval', (array) ( $pattern['postTypes'] ?? [] ) ) ),
			'markup'      => (string) ( $pattern['content'] ?? '' ),
		];
	}

	/**
	 * A saved pattern in the common shape.
	 *
	 * @param \WP_Post $post The wp_block post.
	 *
	 * @return Pattern
	 */
	private static function from_post( \WP_Post $post ): array {
		$terms = get_the_terms( $post, self::CATEGORY_TAXONOMY );

		return [
			'name'        => self::USER_PREFIX . $post->ID,
			'id'          => $post->ID,
			'title'       => Pattern_Overrides::title( $post->ID ),
			'description' => '',
			'categories'  => is_array( $terms ) ? array_values( array_map( static fn ( \WP_Term $term ): string => $term->slug, $terms ) ) : [],
			'source'      => 'user',
			'synced'      => 'unsynced' !== get_post_meta( $post->ID, self::SYNC_META, true ),
			'post_types'  => [],
			'markup'      => $post->post_content,
		];
	}

	/**
	 * Where a registered pattern comes from.
	 *
	 * Core marks its own patterns. A theme's patterns load from its folder or are named after it; any other pattern is a plugin's.
	 *
	 * @param array<string, mixed> $pattern The registered pattern.
	 */
	private static function registry_source( array $pattern ): string {
		$source = (string) ( $pattern['source'] ?? '' );

		if ( 'pattern-directory/theme' === $source ) {
			return 'theme';
		}

		if ( 'core' === $source || str_starts_with( $source, 'pattern-directory' ) ) {
			return 'core';
		}

		$file = wp_normalize_path( (string) ( $pattern['filePath'] ?? '' ) );

		foreach ( array_unique( [ get_stylesheet_directory(), get_template_directory() ] ) as $directory ) {
			if ( '' !== $file && str_starts_with( $file, trailingslashit( wp_normalize_path( $directory ) ) ) ) {
				return 'theme';
			}
		}

		$namespace = strstr( (string) ( $pattern['name'] ?? '' ), '/', true );
		$theme     = wp_get_theme();

		return in_array( $namespace, array_filter( [ get_stylesheet(), get_template(), (string) $theme->get( 'TextDomain' ) ] ), true ) ? 'theme' : 'plugin';
	}
}
