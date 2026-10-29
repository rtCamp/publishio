<?php
/**
 * Replace block ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Content
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Content;

use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Tree;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Validator;
use rtCamp\Publishio\Modules\MCP\Editing\Errors;

/**
 * Class - Replace_Block
 */
final class Replace_Block extends Block_Edit_Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'replace-block';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Replace Block', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Replaces one block, with everything inside it, by new markup of one or more blocks. This is how text, links, images and attributes change: read the block with get-post-content, edit that markup, and send it back, rather than writing the block from scratch. For a synced pattern (core/block), send its one-line comment with new override values in the content attribute; a field left out shows the pattern\'s own value. Problems the block already had are not reported again.', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'required'             => [ 'post_id', 'path', 'markup' ],
			'properties'           => [
				'post_id'        => Schema::post_id(),
				'path'           => Schema::path( 'The block to replace.' ),
				'markup'         => Schema::markup( 'Serialized block markup that takes the block\'s place.' ),
				'approval_token' => Schema::approval_token(),
			],
			'additionalProperties' => false,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function annotations(): array {
		return [
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute( array $input ): array|\WP_Error {
		$opened = self::open( $input );

		if ( is_wp_error( $opened ) ) {
			return $opened;
		}

		$path   = self::path( $input['path'] ?? null );
		$blocks = self::blocks( $input['markup'] ?? '' );

		if ( is_wp_error( $path ) ) {
			return $path;
		}

		if ( is_wp_error( $blocks ) ) {
			return $blocks;
		}

		$tree     = $opened['tree'];
		$original = $tree->get( $path );

		if ( is_wp_error( $original ) ) {
			return $original;
		}

		$validator = new Block_Validator( $opened['post'] );
		$ancestors = $tree->names_down_to( $path->parent() );
		$problems  = $validator->check( $blocks, $ancestors, false, $validator->check( [ $original ], $ancestors ) );
		$before    = Block_Tree::from_markup( $opened['post']->post_content );
		$paths     = $tree->replace( $path, $blocks );

		if ( is_wp_error( $paths ) ) {
			return $paths;
		}

		$problems = [ ...$problems, ...$validator->check_single_use( $tree, $before ) ];

		if ( [] !== $problems ) {
			return Errors::from_problems( $problems );
		}

		return self::finish( $opened['post'], $tree, $input, $paths, $path->parent() );
	}
}
