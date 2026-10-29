<?php
/**
 * Duplicate block ability.
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
 * Class - Duplicate_Block
 */
final class Duplicate_Block extends Block_Edit_Ability {
	public const MAX_COPIES = 10;

	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'duplicate-block';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Duplicate Block', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Copies a block, with everything inside it, right after itself, such as to add a card to a row of cards. Change the copies afterwards with replace-block.', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'required'             => [ 'post_id', 'path' ],
			'properties'           => [
				'post_id'        => Schema::post_id(),
				'path'           => Schema::path( 'The block to copy.' ),
				'count'          => [
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => self::MAX_COPIES,
					'default'     => 1,
					'description' => 'How many copies.',
				],
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

		$path = self::path( $input['path'] ?? null );

		if ( is_wp_error( $path ) ) {
			return $path;
		}

		$tree  = $opened['tree'];
		$paths = $tree->duplicate( $path, max( 1, min( self::MAX_COPIES, (int) ( $input['count'] ?? 1 ) ) ) );

		if ( is_wp_error( $paths ) ) {
			return $paths;
		}

		$problems = ( new Block_Validator( $opened['post'] ) )->check_single_use( $tree, Block_Tree::from_markup( $opened['post']->post_content ) );

		if ( [] !== $problems ) {
			return Errors::from_problems( $problems );
		}

		return self::finish( $opened['post'], $tree, $input, $paths, $path->parent() );
	}
}
