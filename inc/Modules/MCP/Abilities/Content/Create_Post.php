<?php
/**
 * Create post ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Content
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Content;

use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Editing\Errors;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Writer;

/**
 * Class - Create_Post
 */
final class Create_Post extends Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'create-post';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Create Draft', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Creates an empty draft of any post type that uses the block editor (see get-post-types). Add its content afterwards with insert-pattern or insert-blocks, one section per call. Drafts are never published; the user publishes in WordPress.', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'required'             => [ 'title' ],
			'properties'           => [
				'post_type' => [
					'type'        => 'string',
					'maxLength'   => 20,
					'default'     => 'post',
					'description' => 'The post type slug, such as "post", "page" or "product".',
				],
			] + Post_Details::properties(),
			'additionalProperties' => false,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'required'   => [ 'post_id', 'post_type', 'edit_url' ],
			'properties' => [
				'post_id'   => [ 'type' => 'integer' ],
				'post_type' => [ 'type' => 'string' ],
				'edit_url'  => [ 'type' => 'string' ],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute( array $input ): array|\WP_Error {
		$post_type = sanitize_key( (string) ( $input['post_type'] ?? 'post' ) );
		$type      = get_post_type_object( $post_type );

		if ( ! $type || ! Post_Writer::is_editable_type( $post_type ) ) {
			/* translators: %s: post type slug */
			return Errors::make( 'no_block_editor', sprintf( __( '"%s" isn\'t a post type that uses the block editor. Use get-post-types to see the types you can create.', 'publishio' ), $post_type ) );
		}

		if ( ! current_user_can( $type->cap->create_posts ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- The post type's own capability.
			/* translators: %s: post type name */
			return Errors::make( 'forbidden', sprintf( __( 'Your account can\'t create %s.', 'publishio' ), $type->labels->name ), 403 );
		}

		$fields = Post_Details::fields( $input, $post_type );

		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		$post_id = wp_insert_post(
			wp_slash(
				[
					'post_type'    => $post_type,
					'post_status'  => 'draft',
					'post_content' => '',
				] + $fields
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return Errors::make( 'save_failed', $post_id->get_error_message(), 500 );
		}

		return [
			'post_id'   => $post_id,
			'post_type' => $post_type,
			'edit_url'  => (string) get_edit_post_link( $post_id, 'raw' ),
		];
	}
}
