<?php
/**
 * Get post content ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Content
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Content;

use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Path;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Tree;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Writer;

/**
 * Class - Get_Post_Content
 */
final class Get_Post_Content extends Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'get-post-content';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Get Post Content', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Returns the block markup of a whole post, or of one block with everything inside it. To change a block, edit this markup and send it back with replace-block.', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'required'             => [ 'post_id' ],
			'properties'           => [
				'post_id' => Schema::post_id(),
				'path'    => Schema::path( 'The block to return. Leave out for the whole post.' ),
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
			'required'   => [ 'post_id', 'path', 'markup' ],
			'properties' => [
				'post_id' => [ 'type' => 'integer' ],
				'path'    => [
					'type'        => 'string',
					'description' => 'The block\'s path, or "" for the whole post.',
				],
				'markup'  => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function annotations(): array {
		return [
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute( array $input ): array|\WP_Error {
		$post = Post_Writer::find( (int) ( $input['post_id'] ?? 0 ) );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! is_string( $input['path'] ?? null ) || '' === trim( $input['path'] ) ) {
			return [
				'post_id' => $post->ID,
				'path'    => '',
				'markup'  => $post->post_content,
			];
		}

		$path = Block_Path::parse( $input['path'] );

		if ( is_wp_error( $path ) ) {
			return $path;
		}

		$block = Block_Tree::from_markup( $post->post_content )->get( $path );

		if ( is_wp_error( $block ) ) {
			return $block;
		}

		return [
			'post_id' => $post->ID,
			'path'    => $path->to_string(),
			'markup'  => serialize_block( $block ),
		];
	}
}
