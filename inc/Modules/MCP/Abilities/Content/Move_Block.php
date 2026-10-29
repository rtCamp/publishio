<?php
/**
 * Move block ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Content
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Content;

use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Validator;
use rtCamp\Publishio\Modules\MCP\Editing\Errors;

/**
 * Class - Move_Block
 */
final class Move_Block extends Block_Edit_Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'move-block';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Move Block', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Moves a block, with everything inside it, before or after another block, or to the start or end inside one. Both paths refer to the post as it is before the move. The move is refused if the block isn\'t allowed in its new place.', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'required'             => [ 'post_id', 'path', 'placement' ],
			'properties'           => [
				'post_id'        => Schema::post_id(),
				'path'           => Schema::path( 'The block to move.' ),
				'target'         => Schema::path( 'Where to move it, relative to placement. Leave out to move it to the start or end of the post.' ),
				'placement'      => Schema::placement(),
				'approval_token' => Schema::approval_token(),
			],
			'additionalProperties' => false,
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
		$target = self::target( $input['target'] ?? null );

		if ( is_wp_error( $path ) ) {
			return $path;
		}

		if ( is_wp_error( $target ) ) {
			return $target;
		}

		$tree  = $opened['tree'];
		$block = $tree->get( $path );
		$spot  = $tree->spot( $target, (string) ( $input['placement'] ?? '' ) );

		if ( is_wp_error( $block ) ) {
			return $block;
		}

		if ( is_wp_error( $spot ) ) {
			return $spot;
		}

		$validator = new Block_Validator( $opened['post'] );
		$problems  = $validator->check( [ $block ], $tree->names_down_to( $spot['container'] ), true, $validator->check( [ $block ], $tree->names_down_to( $path->parent() ), true ) );

		if ( [] !== $problems ) {
			return Errors::from_problems( $problems );
		}

		$paths = $tree->move( $path, $target, (string) ( $input['placement'] ?? '' ) );

		if ( is_wp_error( $paths ) ) {
			return $paths;
		}

		return self::finish( $opened['post'], $tree, $input, $paths, self::scope( [ $path->parent(), ...$paths ] ) );
	}
}
