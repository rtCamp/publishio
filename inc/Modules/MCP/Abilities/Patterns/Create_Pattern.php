<?php
/**
 * Create pattern ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Patterns
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Patterns;

use rtCamp\Publishio\Modules\MCP\Abilities\Categories\Patterns as Patterns_Category;
use rtCamp\Publishio\Modules\MCP\Abilities\Content\Block_Edit_Ability;
use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Path;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Tree;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Validator;
use rtCamp\Publishio\Modules\MCP\Editing\Errors;
use rtCamp\Publishio\Modules\MCP\Editing\Outline;
use rtCamp\Publishio\Modules\MCP\Editing\Pattern_Library;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Writer;

/**
 * Class - Create_Pattern
 */
final class Create_Pattern extends Block_Edit_Ability {
	public const MAX_PATHS = 50;

	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'create-pattern';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Create Pattern', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Saves blocks as a new pattern in the user\'s pattern library, from markup or from blocks in a post. paths must be blocks next to each other under the same parent, as when they are selected together in the editor. A synced pattern stays the same everywhere it is used; made from a post, its blocks there are replaced with a reference to it, as the editor does. Call it only when the user agreed to save the pattern.', 'publishio' );
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
			'required'             => [ 'title', 'synced' ],
			'properties'           => [
				'title'          => [
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => 200,
					'description' => 'The pattern\'s title, as the user will see it in the inserter.',
				],
				'synced'         => [
					'type'        => 'boolean',
					'description' => 'true for a synced pattern, which stays the same everywhere it is used. false for an unsynced one, which each post copies and can change.',
				],
				'categories'     => [
					'type'        => 'array',
					'maxItems'    => 10,
					'uniqueItems' => true,
					'description' => 'Pattern category names. Missing ones are created.',
					'items'       => [
						'type'      => 'string',
						'minLength' => 1,
						'maxLength' => 100,
					],
				],
				'markup'         => Schema::markup( 'The pattern\'s block markup. Send this or post_id with paths.' ),
				'post_id'        => Schema::post_id( 'The post that has the blocks to save, with paths.' ),
				'paths'          => [
					'type'        => 'array',
					'minItems'    => 1,
					'maxItems'    => self::MAX_PATHS,
					'uniqueItems' => true,
					'description' => 'The blocks to save, next to each other under the same parent.',
					'items'       => Schema::path( 'A block to save in the pattern.' ),
				],
				'approval_token' => Schema::approval_token(),
			],
			'additionalProperties' => false,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'required'   => [ 'name', 'title', 'synced', 'edit_url' ],
			'properties' => [
				'name'     => [
					'type'        => 'string',
					'description' => 'The new pattern\'s name, for insert-pattern.',
				],
				'title'    => [ 'type' => 'string' ],
				'synced'   => [ 'type' => 'boolean' ],
				'edit_url' => [ 'type' => [ 'string', 'null' ] ],
				'post_id'  => [ 'type' => 'integer' ],
				'changed'  => [
					'type'        => 'array',
					'description' => 'The path of the reference that replaced the saved blocks in the post.',
					'items'       => [ 'type' => 'string' ],
				],
				'outline'  => Schema::outline(),
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function preview(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute( array $input ): array|\WP_Error {
		$has_markup = isset( $input['markup'] );
		$has_paths  = isset( $input['post_id'] ) || isset( $input['paths'] );

		if ( $has_markup === $has_paths ) {
			return Errors::make( 'invalid_source', __( 'Send the pattern\'s blocks as markup, or as post_id with paths, but not both.', 'publishio' ) );
		}

		$categories = Pattern_Library::check_categories( array_map( 'strval', (array) ( $input['categories'] ?? [] ) ) );

		if ( is_wp_error( $categories ) ) {
			return $categories;
		}

		$title  = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		$synced = (bool) ( $input['synced'] ?? false );

		if ( $has_markup ) {
			$blocks = self::blocks( $input['markup'] );

			if ( is_wp_error( $blocks ) ) {
				return $blocks;
			}

			$problems = Block_Validator::for_post_type( 'wp_block' )->check( $blocks );

			if ( [] !== $problems ) {
				return Errors::from_problems( $problems );
			}

			$pattern = Post_Writer::create_pattern( $title, $blocks, $synced, $categories );

			return is_wp_error( $pattern ) ? $pattern : self::result( $pattern, $synced );
		}

		return self::from_post( $input, $title, $synced, $categories );
	}

	/**
	 * Save blocks from a post as a pattern. A synced pattern replaces them in the post with a reference.
	 *
	 * @param array<string, mixed> $input      The input.
	 * @param string               $title      The pattern's title.
	 * @param bool                 $synced     Whether the pattern is synced.
	 * @param array<int, string>   $categories The pattern's category names.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function from_post( array $input, string $title, bool $synced, array $categories ): array|\WP_Error {
		if ( $synced ) {
			$opened = self::open( $input );
		} else {
			$post   = Post_Writer::load( (int) ( $input['post_id'] ?? 0 ) );
			$opened = is_wp_error( $post ) ? $post : [
				'post' => $post,
				'tree' => Block_Tree::from_markup( $post->post_content ),
			];
		}

		if ( is_wp_error( $opened ) ) {
			return $opened;
		}

		$paths = self::selection( $opened['tree'], (array) ( $input['paths'] ?? [] ) );

		if ( is_wp_error( $paths ) ) {
			return $paths;
		}

		$blocks = [];

		foreach ( $paths as $path ) {
			$block = $opened['tree']->get( $path );

			if ( is_wp_error( $block ) ) {
				return $block;
			}

			$blocks[] = $block;
		}

		$problems = Block_Validator::for_post_type( 'wp_block' )->check( $blocks, [], true );

		if ( [] !== $problems ) {
			return Errors::from_problems( $problems );
		}

		$pattern = Post_Writer::create_pattern( $title, $blocks, $synced, $categories );

		if ( is_wp_error( $pattern ) || ! $synced ) {
			return is_wp_error( $pattern ) ? $pattern : self::result( $pattern, $synced );
		}

		$replaced = self::replace_with_reference( $opened, $paths, $pattern, $input );

		if ( is_wp_error( $replaced ) ) {
			wp_delete_post( $pattern->ID, true );

			return $replaced;
		}

		return self::result( $pattern, $synced ) + $replaced;
	}

	/**
	 * Replace the saved blocks in the post with a reference to the new synced pattern, and save the post.
	 *
	 * @param array{post: \WP_Post, tree: \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree} $opened  The post and its blocks.
	 * @param array<int, \rtCamp\Publishio\Modules\MCP\Editing\Block_Path>                  $paths   The saved blocks, in order.
	 * @param \WP_Post                                                                      $pattern The new pattern.
	 * @param array<string, mixed>                                                          $input   The input.
	 *
	 * @return array{post_id: int, changed: list<string>, outline: mixed}|\WP_Error
	 */
	private static function replace_with_reference( array $opened, array $paths, \WP_Post $pattern, array $input ): array|\WP_Error {
		$tree      = $opened['tree'];
		$parent    = $paths[0]->parent();
		$reference = [ Pattern_Library::reference( $pattern->ID ) ];
		$problems  = ( new Block_Validator( $opened['post'] ) )->check( $reference, $tree->names_down_to( $parent ) );

		if ( [] !== $problems ) {
			return Errors::from_problems( $problems );
		}

		$removed = $tree->remove( $paths );

		if ( is_wp_error( $removed ) ) {
			return $removed;
		}

		$inserted = $tree->insert_at( $parent, $paths[0]->index(), $reference );

		if ( is_wp_error( $inserted ) ) {
			return $inserted;
		}

		$saved = Post_Writer::save( $opened['post'], $tree, (string) ( $input['approval_token'] ?? '' ) );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return [
			'post_id' => $opened['post']->ID,
			'changed' => array_map( static fn ( Block_Path $path ): string => $path->to_string(), $inserted ),
			'outline' => Outline::after_change( $saved, $parent, $inserted ),
		];
	}

	/**
	 * Parse paths that must be blocks next to each other under one parent, and sort them in document order.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree $tree  The post's blocks.
	 * @param array<int, mixed>                                $input The paths given.
	 *
	 * @return list<\rtCamp\Publishio\Modules\MCP\Editing\Block_Path>|\WP_Error
	 */
	private static function selection( Block_Tree $tree, array $input ): array|\WP_Error {
		if ( [] === $input ) {
			return Errors::invalid_path( '', __( 'Send the paths of the blocks to save.', 'publishio' ) );
		}

		$paths = [];

		foreach ( $input as $value ) {
			$path = self::path( $value );

			if ( is_wp_error( $path ) ) {
				return $path;
			}

			$found = $tree->get( $path );

			if ( is_wp_error( $found ) ) {
				return $found;
			}

			$paths[] = $path;
		}

		usort( $paths, static fn ( Block_Path $a, Block_Path $b ): int => $a->index() <=> $b->index() );

		$parent = $paths[0]->parent()->to_string();

		foreach ( $paths as $i => $path ) {
			if ( $path->parent()->to_string() !== $parent || $path->index() !== $paths[0]->index() + $i ) {
				return Errors::make( 'invalid_selection', __( 'The paths must be blocks next to each other under the same parent, as when they are selected together in the editor.', 'publishio' ) );
			}
		}

		return $paths;
	}

	/**
	 * The result for a saved pattern.
	 *
	 * @param \WP_Post $pattern The pattern.
	 * @param bool     $synced  Whether it's synced.
	 *
	 * @return array{name: string, title: string, synced: bool, edit_url: ?string}
	 */
	private static function result( \WP_Post $pattern, bool $synced ): array {
		$edit_url = get_edit_post_link( $pattern->ID, 'raw' );

		return [
			'name'     => Pattern_Library::USER_PREFIX . $pattern->ID,
			'title'    => $pattern->post_title,
			'synced'   => $synced,
			'edit_url' => $edit_url ? $edit_url : null,
		];
	}
}
