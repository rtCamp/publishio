<?php
/**
 * Insert blocks ability.
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
 * Class - Insert_Blocks
 */
final class Insert_Blocks extends Block_Edit_Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'insert-blocks';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Insert Blocks', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Adds new blocks to a post from block markup. Send one section per call, never several at once, so a refusal costs one section. Place them before or after a target block, or at the start or end inside it; without a target they go at the start or end of the post (default end). Every block is checked before saving and all problems come back in one refusal. For a registered pattern use insert-pattern instead of copying its markup. Returns the new blocks\' paths and the outline of where they went.', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'required'             => [ 'post_id', 'markup' ],
			'properties'           => [
				'post_id'        => Schema::post_id(),
				'markup'         => Schema::markup( 'Serialized block markup for one section, such as <!-- wp:heading --><h2 class="wp-block-heading">Our story</h2><!-- /wp:heading -->.' ),
				'target'         => Schema::path( 'The block to place the new blocks next to or inside. Leave out to add to the post itself.' ),
				'placement'      => Schema::placement() + [ 'default' => 'end' ],
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

		$target = self::target( $input['target'] ?? null );
		$blocks = self::blocks( $input['markup'] ?? '' );

		if ( is_wp_error( $target ) ) {
			return $target;
		}

		if ( is_wp_error( $blocks ) ) {
			return $blocks;
		}

		$tree = $opened['tree'];
		$spot = $tree->spot( $target, (string) ( $input['placement'] ?? 'end' ) );

		if ( is_wp_error( $spot ) ) {
			return $spot;
		}

		$validator = new Block_Validator( $opened['post'] );
		$problems  = $validator->check( $blocks, $tree->names_down_to( $spot['container'] ) );
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
}
