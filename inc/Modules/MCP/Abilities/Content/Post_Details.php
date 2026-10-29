<?php
/**
 * The post details create-post and update-post set.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Content
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Content;

use rtCamp\Publishio\Modules\MCP\Editing\Errors;

/**
 * Class - Post_Details
 */
final class Post_Details {
	/**
	 * The schema properties for title, slug, excerpt and parent.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function properties(): array {
		return [
			'title'     => [
				'type'        => 'string',
				'minLength'   => 1,
				'maxLength'   => 500,
				'description' => 'The title.',
			],
			'slug'      => [
				'type'        => 'string',
				'maxLength'   => 200,
				'description' => 'The URL slug, such as "about-us".',
			],
			'excerpt'   => [
				'type'        => 'string',
				'maxLength'   => 2000,
				'description' => 'A short summary, shown in some themes and used by search engines when there\'s no meta description.',
			],
			'parent_id' => [
				'type'        => 'integer',
				'minimum'     => 0,
				'description' => 'The parent page, for hierarchical types such as pages. 0 for none.',
			],
		];
	}

	/**
	 * The post fields to save from the input, with a refusal for an invalid parent.
	 *
	 * @param array<string, mixed> $input     The input.
	 * @param string               $post_type The post type.
	 * @param int                  $post_id   The post being updated, or 0 for a new one.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function fields( array $input, string $post_type, int $post_id = 0 ): array|\WP_Error {
		$fields = [];

		if ( isset( $input['title'] ) ) {
			$fields['post_title'] = (string) $input['title'];
		}

		if ( isset( $input['slug'] ) ) {
			$fields['post_name'] = sanitize_title( (string) $input['slug'] );
		}

		if ( isset( $input['excerpt'] ) ) {
			$fields['post_excerpt'] = (string) $input['excerpt'];
		}

		if ( isset( $input['parent_id'] ) ) {
			$parent = self::check_parent( (int) $input['parent_id'], $post_type, $post_id );

			if ( is_wp_error( $parent ) ) {
				return $parent;
			}

			$fields['post_parent'] = (int) $input['parent_id'];
		}

		return $fields;
	}

	/**
	 * Refuse a parent that doesn't exist, isn't readable, is of another type, or is the post or one of its children.
	 *
	 * @param int    $parent_id The parent ID, or 0 for none.
	 * @param string $post_type The post type.
	 * @param int    $post_id   The post being updated, or 0 for a new one.
	 *
	 * @return true|\WP_Error
	 */
	private static function check_parent( int $parent_id, string $post_type, int $post_id ): bool|\WP_Error {
		if ( 0 === $parent_id ) {
			return true;
		}

		if ( ! is_post_type_hierarchical( $post_type ) ) {
			/* translators: %s: post type slug */
			return Errors::make( 'invalid_parent_post', sprintf( __( '%s items can\'t have a parent. Leave out parent_id.', 'publishio' ), $post_type ) );
		}

		$parent = get_post( $parent_id );

		if ( ! $parent instanceof \WP_Post || $parent->post_type !== $post_type || ! current_user_can( 'read_post', $parent_id ) ) {
			/* translators: 1: parent ID, 2: post type slug */
			return Errors::make( 'invalid_parent_post', sprintf( __( 'There\'s no %2$s with ID %1$d to use as the parent. Use search-posts to find one.', 'publishio' ), $parent_id, $post_type ) );
		}

		if ( $post_id > 0 && ( $parent_id === $post_id || in_array( $post_id, get_post_ancestors( $parent ), true ) ) ) {
			return Errors::make( 'invalid_parent_post', __( 'A page can\'t be its own parent or the parent of one of its ancestors.', 'publishio' ) );
		}

		return true;
	}
}
