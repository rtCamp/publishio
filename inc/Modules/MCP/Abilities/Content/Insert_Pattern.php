<?php
/**
 * Insert pattern ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Content
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Content;

use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Editing\Errors;
use rtCamp\Publishio\Modules\MCP\Editing\Pattern_Library;

/**
 * Class - Insert_Pattern
 */
final class Insert_Pattern extends Block_Edit_Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'insert-pattern';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Insert Pattern', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Adds a pattern to a post without copying its markup, so the blocks are real editor output. All of its root blocks go in, placed like insert-blocks. A synced pattern goes in as a reference and stays the same everywhere it is used; set its override values afterwards with replace-block on its path. Use list-patterns to find one. Returns the new blocks\' paths and the outline of where they went.', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'required'             => [ 'post_id', 'pattern' ],
			'properties'           => [
				'post_id'        => Schema::post_id(),
				'pattern'        => Schema::pattern_name(),
				'target'         => Schema::path( 'The block to place the pattern next to or inside. Leave out to add to the post itself.' ),
				'placement'      => Schema::placement() + [ 'default' => 'end' ],
				'approval_token' => Schema::approval_token(),
			],
			'additionalProperties' => false,
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
		$opened = self::open( $input );

		if ( is_wp_error( $opened ) ) {
			return $opened;
		}

		$pattern = Pattern_Library::find( (string) ( $input['pattern'] ?? '' ) );

		if ( is_wp_error( $pattern ) ) {
			return $pattern;
		}

		$post_type = $opened['post']->post_type;

		if ( ! Pattern_Library::is_for( $pattern, $post_type ) ) {
			/* translators: 1: pattern title, 2: list of post types, 3: post type slug */
			return Errors::make( 'pattern_not_for_post_type', sprintf( __( '"%1$s" is only for %2$s, so it isn\'t offered here. Call list-patterns with post_type: %3$s to see the patterns that are.', 'publishio' ), $pattern['title'], wp_sprintf( '%l', $pattern['post_types'] ), $post_type ) );
		}

		$blocks = Pattern_Library::blocks_to_insert( $pattern );

		if ( [] === $blocks ) {
			/* translators: %s: pattern title */
			return Errors::make( 'empty_pattern', sprintf( __( '"%s" has no blocks to insert.', 'publishio' ), $pattern['title'] ) );
		}

		return self::insert( $opened, $input, $blocks, true );
	}
}
