<?php
/**
 * Loads and saves the posts the editing tools change.
 *
 * @package rtCamp\Publishio\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Editing;

/**
 * Class - Post_Writer
 *
 * The only code that saves post content. It refuses posts the user can't edit, post types without the block editor, posts open in the editor, and changes to live posts without the user's approval.
 */
final class Post_Writer {
	private const LOCK_WINDOW = 150;

	private const EXCLUDED_TYPES = [
		'attachment',
		'custom_css',
		'customize_changeset',
		'nav_menu_item',
		'oembed_cache',
		'revision',
		'user_request',
		'wp_block',
		'wp_font_face',
		'wp_font_family',
		'wp_global_styles',
		'wp_navigation',
		'wp_template',
		'wp_template_part',
	];

	/**
	 * Whether the editing tools work on a post type.
	 *
	 * Saved patterns, templates and other site content have their own tools or the Site Editor.
	 *
	 * @param string $post_type The post type.
	 */
	public static function is_editable_type( string $post_type ): bool {
		return post_type_exists( $post_type ) && ! in_array( $post_type, self::EXCLUDED_TYPES, true ) && use_block_editor_for_post_type( $post_type );
	}

	/**
	 * Load a post the current user can edit with the block editor.
	 *
	 * @param int $post_id The post ID.
	 */
	public static function load( int $post_id ): \WP_Post|\WP_Error {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || in_array( $post->post_status, [ 'trash', 'auto-draft' ], true ) ) {
			return Errors::post_not_found( $post_id );
		}

		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			return Errors::post_not_found( $post_id );
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return Errors::forbidden( $post );
		}

		if ( ! self::is_editable_type( $post->post_type ) || ! use_block_editor_for_post( $post ) ) {
			return Errors::no_block_editor( $post );
		}

		return $post;
	}

	/**
	 * Refuse a change to a post that is open in the editor, or that is live without the user's approval.
	 *
	 * Tools call this before working out a change, so the AI learns about the lock or the approval first.
	 *
	 * @param \WP_Post $post           The post.
	 * @param string   $approval_token The token from a live_post_needs_approval refusal, if any.
	 *
	 * @return true|\WP_Error
	 */
	public static function guard( \WP_Post $post, string $approval_token = '' ): bool|\WP_Error {
		$holder = self::lock_holder( $post );

		if ( null !== $holder ) {
			return Errors::post_locked( $post, $holder );
		}

		$user_id = get_current_user_id();

		if ( Live_Approval::is_live( $post ) && ! Live_Approval::verify( $approval_token, $post->ID, $user_id ) ) {
			return Errors::live_post_needs_approval( $post, Live_Approval::issue( $post->ID, $user_id ) );
		}

		return true;
	}

	/**
	 * Save a post's new blocks.
	 *
	 * The post is read again just before writing, so a change made by another call since load() is refused instead of overwritten. Kses and other save filters can change what is stored, so the result is the tree as saved.
	 *
	 * @param \WP_Post                                         $post           The post, from load().
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree $tree           The post's blocks after the change.
	 * @param string                                           $approval_token The token from a live_post_needs_approval refusal, if any.
	 *
	 * @return \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree|\WP_Error The post's blocks as saved.
	 */
	public static function save( \WP_Post $post, Block_Tree $tree, string $approval_token = '' ): Block_Tree|\WP_Error {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return Errors::forbidden( $post );
		}

		$guard = self::guard( $post, $approval_token );

		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$markup = $tree->to_markup();

		if ( $markup === $post->post_content ) {
			return $tree;
		}

		clean_post_cache( $post->ID );

		if ( get_post_field( 'post_content', $post->ID, 'raw' ) !== $post->post_content ) {
			return Errors::post_changed( $post );
		}

		$result = wp_update_post(
			wp_slash(
				[
					'ID'           => $post->ID,
					'post_content' => $markup,
				]
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return Errors::make( 'save_failed', $result->get_error_message(), 500 );
		}

		return Block_Tree::from_markup( (string) get_post_field( 'post_content', $post->ID, 'raw' ) );
	}

	/**
	 * The user who has the post open in the editor, including the current user.
	 *
	 * WordPress's wp_check_post_lock() ignores the current user's own lock, but the editor in another tab would still overwrite the change when it saves.
	 *
	 * @param \WP_Post $post The post.
	 */
	private static function lock_holder( \WP_Post $post ): ?int {
		$lock = (string) get_post_meta( $post->ID, '_edit_lock', true );

		if ( '' === $lock ) {
			return null;
		}

		[ $time, $user ] = array_pad( explode( ':', $lock, 2 ), 2, '' );
		$user_id         = '' !== $user ? (int) $user : (int) get_post_meta( $post->ID, '_edit_last', true );
		$window          = (int) apply_filters( 'wp_check_post_lock_window', self::LOCK_WINDOW ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter, applied as wp_check_post_lock() applies it.

		return $user_id > 0 && (int) $time > time() - $window ? $user_id : null;
	}
}
