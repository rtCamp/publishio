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
 * The only code that saves post and pattern content. It refuses posts the user can't edit, post types without the block editor, posts open in the editor, and changes to live posts without the user's approval.
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
		return self::is_supported_type( $post_type ) && use_block_editor_for_post_type( $post_type );
	}

	/**
	 * Whether the post tools work on a post type at all, with or without the block editor.
	 *
	 * @param string $post_type The post type.
	 */
	public static function is_supported_type( string $post_type ): bool {
		return post_type_exists( $post_type ) && ! in_array( $post_type, self::EXCLUDED_TYPES, true );
	}

	/**
	 * Find a post the current user can edit, whatever editor its type uses.
	 *
	 * A post the user can't read is reported as not found, so its title isn't revealed.
	 *
	 * @param int $post_id The post ID.
	 */
	public static function find( int $post_id ): \WP_Post|\WP_Error {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || in_array( $post->post_status, [ 'trash', 'auto-draft' ], true ) || ! current_user_can( 'read_post', $post->ID ) ) {
			return Errors::post_not_found( $post_id );
		}

		if ( ! self::is_supported_type( $post->post_type ) ) {
			return Errors::unsupported_type( $post );
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return Errors::forbidden( $post );
		}

		return $post;
	}

	/**
	 * Load a post the current user can edit with the block editor.
	 *
	 * @param int $post_id The post ID.
	 */
	public static function load( int $post_id ): \WP_Post|\WP_Error {
		$post = self::find( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! use_block_editor_for_post_type( $post->post_type ) || ! use_block_editor_for_post( $post ) ) {
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
		$lock = self::check_lock( $post );

		if ( is_wp_error( $lock ) ) {
			return $lock;
		}

		$user_id = get_current_user_id();

		if ( Live_Approval::is_live( $post ) && ! Live_Approval::verify( $approval_token, $post->ID, $user_id ) ) {
			return Errors::live_post_needs_approval( $post, Live_Approval::issue( $post->ID, $user_id ) );
		}

		return true;
	}

	/**
	 * Find a post the current user can edit and that may change now, for tools that change a post's details rather than its blocks.
	 *
	 * @param int    $post_id        The post ID.
	 * @param string $approval_token The token the user's approval unlocked, for a live post.
	 */
	public static function writable( int $post_id, string $approval_token = '' ): \WP_Post|\WP_Error {
		$post = self::find( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$guard = self::guard( $post, $approval_token );

		return is_wp_error( $guard ) ? $guard : $post;
	}

	/**
	 * Refuse a change to a post that is open in the editor.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return true|\WP_Error
	 */
	public static function check_lock( \WP_Post $post ): bool|\WP_Error {
		$holder = self::lock_holder( $post );

		return null === $holder ? true : Errors::post_locked( $post, $holder );
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
	 * Save blocks as a new published pattern in the pattern library.
	 *
	 * @param string                           $title      The pattern's title.
	 * @param array<int, array<string, mixed>> $blocks     The pattern's blocks.
	 * @param bool                             $synced     Whether the pattern is synced.
	 * @param array<int, string>               $categories Pattern category names, created when missing.
	 */
	public static function create_pattern( string $title, array $blocks, bool $synced, array $categories ): \WP_Post|\WP_Error {
		$type = get_post_type_object( 'wp_block' );

		if ( ! $type || ! current_user_can( $type->cap->create_posts ) || ! current_user_can( $type->cap->publish_posts ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- The post type's own capabilities.
			return Errors::make( 'forbidden', __( 'Your account can\'t save patterns.', 'publishio' ), 403 );
		}

		$id = wp_insert_post(
			wp_slash(
				[
					'post_type'    => 'wp_block',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => Block_Tree::of( $blocks )->to_markup(),
					'meta_input'   => $synced ? [] : [ Pattern_Library::SYNC_META => 'unsynced' ],
				]
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			return Errors::make( 'save_failed', $id->get_error_message(), 500 );
		}

		if ( [] !== $categories ) {
			wp_set_object_terms( $id, $categories, Pattern_Library::CATEGORY_TAXONOMY );
		}

		$pattern = get_post( $id );

		return $pattern instanceof \WP_Post ? $pattern : Errors::make( 'save_failed', __( 'The pattern couldn\'t be saved.', 'publishio' ), 500 );
	}

	/**
	 * Save new blocks, and optionally a new title, to a saved pattern.
	 *
	 * @param \WP_Post                         $pattern The wp_block post.
	 * @param array<int, array<string, mixed>> $blocks  The pattern's new blocks.
	 * @param string                           $title   The new title, or empty to keep it.
	 */
	public static function save_pattern( \WP_Post $pattern, array $blocks, string $title = '' ): \WP_Post|\WP_Error {
		if ( 'wp_block' !== $pattern->post_type || ! current_user_can( 'edit_post', $pattern->ID ) ) {
			return Errors::forbidden( $pattern );
		}

		$lock = self::check_lock( $pattern );

		if ( is_wp_error( $lock ) ) {
			return $lock;
		}

		$fields = [
			'ID'           => $pattern->ID,
			'post_content' => Block_Tree::of( $blocks )->to_markup(),
		];

		if ( '' !== $title ) {
			$fields['post_title'] = $title;
		}

		$result = wp_update_post( wp_slash( $fields ), true );

		if ( is_wp_error( $result ) ) {
			return Errors::make( 'save_failed', $result->get_error_message(), 500 );
		}

		clean_post_cache( $pattern->ID );
		$saved = get_post( $pattern->ID );

		return $saved instanceof \WP_Post ? $saved : Errors::make( 'save_failed', __( 'The pattern couldn\'t be saved.', 'publishio' ), 500 );
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
