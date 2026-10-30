<?php
/**
 * Preview post ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Content
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Content;

use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Apps\Preview\Page_Details;
use rtCamp\Publishio\Modules\MCP\Apps\Preview\Preview_Card;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Writer;

/**
 * Class - Preview_Post
 */
final class Preview_Post extends Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'preview-post';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Preview Post', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Shows the user the whole post, with how it looks in search results and as a shared link, in apps that show previews. Call it once the content and all details are set, again after significant changes, and when the user asks. Share the links it returns: url is the post\'s address, or the address it gets when published, and preview_url shows a draft to a signed-in user.', 'publishio' );
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
			'required'   => [ 'post_id', 'title', 'status', 'url', 'preview_url', 'edit_url' ],
			'properties' => [
				'post_id'     => [ 'type' => 'integer' ],
				'title'       => $string,
				'status'      => $string,
				'url'         => $string,
				'preview_url' => $string,
				'edit_url'    => [ 'type' => [ 'string', 'null' ] ],
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
	protected function preview(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute( array $input ): array|\WP_Error {
		$post = Post_Writer::load( (int) ( $input['post_id'] ?? 0 ) );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$edit_url = get_edit_post_link( $post->ID, 'raw' );

		return [
			'post_id'     => $post->ID,
			'title'       => Preview_Card::post_title( $post ),
			'status'      => $post->post_status,
			'url'         => Page_Details::address( $post ),
			'preview_url' => (string) get_preview_post_link( $post ),
			'edit_url'    => $edit_url ? $edit_url : null,
		];
	}
}
