<?php
/**
 * Render blocks ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Blocks
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Blocks;

use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Abilities\Categories\Blocks as Blocks_Category;
use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Tree;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Validator;
use rtCamp\Publishio\Modules\MCP\Editing\Errors;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Writer;

/**
 * Class - Render_Blocks
 */
final class Render_Blocks extends Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'render-blocks';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Render Blocks', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'A dry run: checks block markup the way a write would, then renders it to HTML without saving anything. Pass post_id or post_type so the blocks allowed there are checked. errors lists every problem a write would refuse.', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function category(): string {
		return Blocks_Category::SLUG;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'required'             => [ 'markup' ],
			'properties'           => [
				'markup'    => Schema::markup( 'Serialized block markup to check and render.' ),
				'post_id'   => Schema::post_id( 'The post the markup is for. Dynamic blocks render with it as their context.' ),
				'post_type' => [
					'type'        => 'string',
					'maxLength'   => 20,
					'default'     => 'post',
					'description' => 'The post type the markup is for, when there is no post yet.',
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
			'required'   => [ 'valid', 'errors', 'html' ],
			'properties' => [
				'valid'  => [ 'type' => 'boolean' ],
				'errors' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'required'   => [ 'code', 'path', 'message' ],
						'properties' => [
							'code'    => [ 'type' => 'string' ],
							'path'    => [ 'type' => 'string' ],
							'message' => [ 'type' => 'string' ],
						],
					],
				],
				'html'   => [ 'type' => 'string' ],
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
		$post = null;

		if ( isset( $input['post_id'] ) ) {
			$post = Post_Writer::load( (int) $input['post_id'] );

			if ( is_wp_error( $post ) ) {
				return $post;
			}
		}

		$post_type = $post ? $post->post_type : sanitize_key( (string) ( $input['post_type'] ?? 'post' ) );

		if ( ! Post_Writer::is_editable_type( $post_type ) ) {
			/* translators: %s: post type slug */
			return Errors::make( 'no_block_editor', sprintf( __( '"%s" isn\'t a post type that uses the block editor. Use get-post-types to see which do.', 'publishio' ), $post_type ) );
		}

		$blocks    = Block_Tree::parse( (string) ( $input['markup'] ?? '' ) );
		$validator = $post ? new Block_Validator( $post ) : Block_Validator::for_post_type( $post_type );
		$problems  = array_slice( $validator->check( $blocks ), 0, Errors::MAX_PROBLEMS );

		return [
			'valid'  => [] === $problems,
			'errors' => $problems,
			'html'   => self::render( $blocks, $post ),
		];
	}

	/**
	 * Render blocks, with the post as the global post, since some core blocks such as post-title read it instead of their context.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param ?\WP_Post                        $post   The post the blocks are for.
	 */
	public static function render( array $blocks, ?\WP_Post $post ): string {
		$previous = $GLOBALS['post'] ?? null;
		$context  = [];

		if ( $post ) {
			$context = [
				'postId'   => $post->ID,
				'postType' => $post->post_type,
			];

			$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			setup_postdata( $post );
		}

		$html = '';

		foreach ( $blocks as $block ) {
			$html .= ( new \WP_Block( $block, $context ) )->render();
		}

		if ( $post ) {
			$GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

			if ( $previous instanceof \WP_Post ) {
				setup_postdata( $previous );
			}
		}

		return $html;
	}
}
