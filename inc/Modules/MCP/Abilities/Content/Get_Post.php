<?php
/**
 * Get post ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Content
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Content;

use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Path;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Tree;
use rtCamp\Publishio\Modules\MCP\Editing\Live_Approval;
use rtCamp\Publishio\Modules\MCP\Editing\Outline;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Writer;

/**
 * Class - Get_Post
 */
final class Get_Post extends Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'get-post';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Get Post', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Reads a post\'s details and its block outline: every block\'s path and a short text, plus each synced pattern\'s override fields. Returns no markup; use get-post-content for a block\'s markup. is_live means changes are seen by visitors, so edits need the user\'s approval.', 'publishio' );
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
				'path'    => Schema::path( 'Outline only this block and what\'s inside it, such as for a long post whose outline was truncated.' ),
			],
			'additionalProperties' => false,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema(): array {
		$string = [ 'type' => 'string' ];

		return [
			'type'       => 'object',
			'required'   => [ 'post_id', 'title', 'post_type', 'status', 'is_live', 'block_editor', 'outline' ],
			'properties' => [
				'post_id'        => [ 'type' => 'integer' ],
				'title'          => $string,
				'post_type'      => $string,
				'status'         => $string,
				'is_live'        => [ 'type' => 'boolean' ],
				'block_editor'   => [
					'type'        => 'boolean',
					'description' => 'Whether the post uses the block editor. The block tools work only on posts that do.',
				],
				'slug'           => $string,
				'excerpt'        => $string,
				'parent_id'      => [ 'type' => 'integer' ],
				'template'       => $string,
				'date'           => $string,
				'modified'       => $string,
				'url'            => $string,
				'edit_url'       => $string,
				'featured_image' => [
					'type'       => [ 'object', 'null' ],
					'properties' => [
						'id'  => [ 'type' => 'integer' ],
						'url' => $string,
						'alt' => $string,
					],
				],
				'outline'        => Schema::outline(),
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

		$under = isset( $input['path'] ) ? Block_Path::parse( (string) $input['path'] ) : Block_Path::root();

		if ( is_wp_error( $under ) ) {
			return $under;
		}

		$outline = Outline::of( Block_Tree::from_markup( $post->post_content ), $under );

		if ( is_wp_error( $outline ) ) {
			return $outline;
		}

		return [
			'post_id'        => $post->ID,
			'title'          => $post->post_title,
			'post_type'      => $post->post_type,
			'status'         => $post->post_status,
			'is_live'        => Live_Approval::is_live( $post ),
			'block_editor'   => use_block_editor_for_post( $post ),
			'slug'           => $post->post_name,
			'excerpt'        => $post->post_excerpt,
			'parent_id'      => (int) $post->post_parent,
			'template'       => (string) get_page_template_slug( $post ),
			'date'           => $post->post_date,
			'modified'       => $post->post_modified,
			'url'            => (string) get_permalink( $post ),
			'edit_url'       => (string) get_edit_post_link( $post, 'raw' ),
			'featured_image' => self::featured_image( $post ),
			'outline'        => $outline,
		];
	}

	/**
	 * The featured image's ID, URL and alt text, or null.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return array{id: int, url: string, alt: string}|null
	 */
	private static function featured_image( \WP_Post $post ): ?array {
		$id = (int) get_post_thumbnail_id( $post );

		if ( ! $id ) {
			return null;
		}

		return [
			'id'  => $id,
			'url' => (string) wp_get_attachment_url( $id ),
			'alt' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
		];
	}
}
