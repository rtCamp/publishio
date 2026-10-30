<?php
/**
 * Base class for the tools that change a post's blocks.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Content
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Content;

use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Path;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Tree;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Validator;
use rtCamp\Publishio\Modules\MCP\Editing\Errors;
use rtCamp\Publishio\Modules\MCP\Editing\Outline;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Writer;

/**
 * Class - Block_Edit_Ability
 *
 * Each block-edit tool loads the post, refuses early when it is locked or live without approval, changes the block tree, and saves through Post_Writer. The result lists the changed blocks' paths and an outline of the part of the post that changed, read from what was saved.
 */
abstract class Block_Edit_Ability extends Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'required'   => [ 'post_id', 'changed', 'outline' ],
			'properties' => [
				'post_id' => [ 'type' => 'integer' ],
				'changed' => [
					'type'        => 'array',
					'description' => 'Paths of the blocks this call added or changed, as they are now.',
					'items'       => [ 'type' => 'string' ],
				],
				'outline' => Schema::outline(),
			],
		];
	}

	/**
	 * Load the post and its blocks, refusing early when it is locked or live without approval.
	 *
	 * @param array<string, mixed> $input The input.
	 *
	 * @return array{post: \WP_Post, tree: \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree}|\WP_Error
	 */
	protected static function open( array $input ): array|\WP_Error {
		$post = Post_Writer::load( (int) ( $input['post_id'] ?? 0 ) );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$guard = Post_Writer::guard( $post, (string) ( $input['approval_token'] ?? '' ) );

		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		return [
			'post' => $post,
			'tree' => Block_Tree::from_markup( $post->post_content ),
		];
	}

	/**
	 * Save the changed tree and build the result.
	 *
	 * @param \WP_Post                                                     $post    The post.
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree             $tree    The changed blocks.
	 * @param array<string, mixed>                                         $input   The input.
	 * @param array<int, \rtCamp\Publishio\Modules\MCP\Editing\Block_Path> $changed The blocks added or changed.
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path             $scope   The part of the post to outline.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	protected static function finish( \WP_Post $post, Block_Tree $tree, array $input, array $changed, Block_Path $scope ): array|\WP_Error {
		$saved = Post_Writer::save( $post, $tree, (string) ( $input['approval_token'] ?? '' ) );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return [
			'post_id' => $post->ID,
			'changed' => array_map( static fn ( Block_Path $path ): string => $path->to_string(), array_values( $changed ) ),
			'outline' => Outline::after_change( $saved, $scope, $changed ),
		];
	}

	/**
	 * Parse a required path.
	 *
	 * @param mixed $value The input value.
	 */
	protected static function path( mixed $value ): Block_Path|\WP_Error {
		return is_string( $value ) ? Block_Path::parse( $value ) : Errors::invalid_path( '', __( 'A path is required.', 'publishio' ) );
	}

	/**
	 * Parse an optional target path. A missing target means the post itself.
	 *
	 * @param mixed $value The input value.
	 */
	protected static function target( mixed $value ): Block_Path|\WP_Error {
		return is_string( $value ) && '' !== trim( $value ) ? Block_Path::parse( $value ) : Block_Path::root();
	}

	/**
	 * Parse new markup, refusing markup with no blocks.
	 *
	 * @param mixed $markup The input value.
	 *
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	protected static function blocks( mixed $markup ): array|\WP_Error {
		$blocks = is_string( $markup ) ? Block_Tree::parse( $markup ) : [];

		return [] === $blocks ? Errors::missing_markup() : $blocks;
	}

	/**
	 * Insert new blocks at the input's target and placement, then save, refusing with every problem at once.
	 *
	 * @param array{post: \WP_Post, tree: \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree} $opened  The post and its blocks, from open().
	 * @param array<string, mixed>                                                          $input   The input.
	 * @param array<int, array<string, mixed>>                                              $blocks  The new blocks.
	 * @param bool                                                                          $trusted Whether the blocks are stored editor output, such as a pattern's.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	protected static function insert( array $opened, array $input, array $blocks, bool $trusted = false ): array|\WP_Error {
		$target = self::target( $input['target'] ?? null );

		if ( is_wp_error( $target ) ) {
			return $target;
		}

		$tree = $opened['tree'];
		$spot = $tree->spot( $target, (string) ( $input['placement'] ?? 'end' ) );

		if ( is_wp_error( $spot ) ) {
			return $spot;
		}

		$validator = new Block_Validator( $opened['post'] );
		$problems  = $validator->check( $blocks, $tree->names_down_to( $spot['container'] ), $trusted );
		$before    = Block_Tree::from_markup( $opened['post']->post_content );
		$paths     = $tree->insert_at( $spot['container'], $spot['position'], $blocks );

		if ( is_wp_error( $paths ) ) {
			return $paths;
		}

		$problems = [ ...$problems, ...$validator->check_single_use( $tree, $before ) ];

		if ( [] !== $problems ) {
			return Errors::from_problems( $problems );
		}

		return self::finish( $opened['post'], $tree, $input, $paths, $spot['container'] );
	}

	/**
	 * The deepest block that contains every given path.
	 *
	 * @param array<int, \rtCamp\Publishio\Modules\MCP\Editing\Block_Path> $paths The paths.
	 */
	protected static function scope( array $paths ): Block_Path {
		$paths = array_values( $paths );

		if ( [] === $paths ) {
			return Block_Path::root();
		}

		$common = $paths[0]->steps();

		foreach ( array_slice( $paths, 1 ) as $path ) {
			$steps = $path->steps();
			$depth = 0;

			while ( isset( $common[ $depth ], $steps[ $depth ] ) && $common[ $depth ]['index'] === $steps[ $depth ]['index'] ) {
				++$depth;
			}

			$common = array_slice( $common, 0, $depth );
		}

		$scope = Block_Path::root();

		foreach ( $common as $step ) {
			$scope = $scope->child( $step['index'], $step['name'] );
		}

		return $scope;
	}
}
