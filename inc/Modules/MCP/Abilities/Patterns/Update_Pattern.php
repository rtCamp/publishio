<?php
/**
 * Update pattern ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Patterns
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Patterns;

use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Abilities\Categories\Patterns as Patterns_Category;
use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Tree;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Validator;
use rtCamp\Publishio\Modules\MCP\Editing\Errors;
use rtCamp\Publishio\Modules\MCP\Editing\Pattern_Library;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Writer;

/**
 * Class - Update_Pattern
 */
final class Update_Pattern extends Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'update-pattern';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Update Pattern', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Saves new markup, and optionally a new title, to an unsynced pattern in the user\'s pattern library. Call it only when the user asked to change that pattern. Posts that already use it keep their own copy. Synced patterns and the theme\'s, plugins\' and WordPress\'s patterns are refused.', 'publishio' );
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
			'required'             => [ 'pattern', 'markup' ],
			'properties'           => [
				'pattern' => Schema::pattern_name(),
				'markup'  => Schema::markup( 'The pattern\'s new block markup, replacing all of it.' ),
				'title'   => [
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => 200,
					'description' => 'A new title. Leave out to keep it.',
				],
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
				'name'     => [ 'type' => 'string' ],
				'title'    => [ 'type' => 'string' ],
				'synced'   => [ 'type' => 'boolean' ],
				'edit_url' => [ 'type' => [ 'string', 'null' ] ],
			],
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
		$pattern = Pattern_Library::find( (string) ( $input['pattern'] ?? '' ) );

		if ( is_wp_error( $pattern ) ) {
			return $pattern;
		}

		if ( 'user' !== $pattern['source'] ) {
			/* translators: 1: pattern title, 2: where it comes from, such as "the theme" */
			return Errors::make( 'pattern_read_only', sprintf( __( '"%1$s" comes from %2$s, so it can\'t be changed. create-pattern can save an edited copy.', 'publishio' ), $pattern['title'], Pattern_Library::source_label( $pattern ) ), 403 );
		}

		$post = get_post( $pattern['id'] );

		if ( ! $post instanceof \WP_Post ) {
			return Errors::make( 'unknown_pattern', __( 'The pattern no longer exists.', 'publishio' ), 404 );
		}

		if ( $pattern['synced'] ) {
			$edit_url = get_edit_post_link( $post->ID, 'raw' );
			$where    = $edit_url
				/* translators: %s: the pattern's edit URL */
				? sprintf( __( 'The user can change it in the editor: %s', 'publishio' ), $edit_url )
				: __( 'The user can change it in the editor.', 'publishio' );

			/* translators: 1: pattern title, 2: where the user can change it */
			return Errors::make( 'synced_pattern_not_supported', sprintf( __( '"%1$s" is a synced pattern, and changing synced patterns isn\'t supported yet. %2$s', 'publishio' ), $pattern['title'], $where ) );
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return Errors::forbidden( $post );
		}

		$blocks = Block_Tree::parse( (string) ( $input['markup'] ?? '' ) );

		if ( [] === $blocks ) {
			return Errors::missing_markup();
		}

		$validator = Block_Validator::for_post_type( 'wp_block' );
		$problems  = $validator->check( $blocks, [], false, $validator->check( Block_Tree::parse( $post->post_content ) ) );

		if ( [] !== $problems ) {
			return Errors::from_problems( $problems );
		}

		$saved = Post_Writer::save_pattern( $post, $blocks, sanitize_text_field( (string) ( $input['title'] ?? '' ) ) );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$edit_url = get_edit_post_link( $saved->ID, 'raw' );

		return [
			'name'     => $pattern['name'],
			'title'    => $saved->post_title,
			'synced'   => false,
			'edit_url' => $edit_url ? $edit_url : null,
		];
	}
}
