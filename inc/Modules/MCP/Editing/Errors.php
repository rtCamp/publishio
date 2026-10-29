<?php
/**
 * Builds every refusal the editing tools return.
 *
 * @package rtCamp\Publishio\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Editing;

/**
 * Class - Errors
 *
 * The MCP adapter sends only an error's message to the AI, so each message starts with its code and carries every problem found. The code and the problems are also in the error data for REST callers and tests.
 *
 * @phpstan-type Problem array{code: string, path: string, message: string}
 */
final class Errors {
	public const MAX_PROBLEMS = 20;

	/**
	 * Build an error whose message starts with its code.
	 *
	 * @param string $code    The error code.
	 * @param string $message What is wrong and how to fix it.
	 * @param int    $status  The HTTP status for REST callers.
	 */
	public static function make( string $code, string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( $code, $code . ': ' . $message, [ 'status' => $status ] );
	}

	/**
	 * One problem found in markup.
	 *
	 * @param string $code    The problem's code.
	 * @param string $path    Where it is, as a path within the markup.
	 * @param string $message What is wrong and how to fix it.
	 *
	 * @return Problem
	 */
	public static function problem( string $code, string $path, string $message ): array {
		return [
			'code'    => $code,
			'path'    => $path,
			'message' => $message,
		];
	}

	/**
	 * Build one error listing every problem, up to MAX_PROBLEMS.
	 *
	 * @param array<int, Problem> $problems The problems found.
	 */
	public static function from_problems( array $problems ): \WP_Error {
		$total    = count( $problems );
		$problems = array_slice( $problems, 0, self::MAX_PROBLEMS );

		if ( 1 === $total ) {
			$error = self::make( $problems[0]['code'], $problems[0]['message'] );
			$error->add_data(
				[
					'status'   => 400,
					'problems' => $problems,
				]
			);

			return $error;
		}

		$lines = array_map(
			static fn ( array $problem, int $i ): string => sprintf( '%d. %s at %s: %s', $i + 1, $problem['code'], '' === $problem['path'] ? 'the top level' : $problem['path'], $problem['message'] ),
			$problems,
			array_keys( $problems )
		);

		$summary = $total > self::MAX_PROBLEMS
			/* translators: 1: number of problems found, 2: number listed */
			? sprintf( __( 'The markup has %1$d problems. The first %2$d are listed. Fix them all, then call again.', 'publishio' ), $total, self::MAX_PROBLEMS )
			/* translators: %d: number of problems found */
			: sprintf( __( 'The markup has %d problems. Fix them all, then call again.', 'publishio' ), $total );

		return new \WP_Error(
			'invalid_markup',
			'invalid_markup: ' . $summary . "\n" . implode( "\n", $lines ),
			[
				'status'   => 400,
				'problems' => $problems,
			]
		);
	}

	/**
	 * A path that can't be parsed.
	 *
	 * @param string $path   The path given.
	 * @param string $reason Why it can't be parsed.
	 */
	public static function invalid_path( string $path, string $reason ): \WP_Error {
		/* translators: 1: the path, 2: why it is invalid */
		return self::make( 'invalid_path', sprintf( __( '"%1$s" isn\'t a valid path. %2$s', 'publishio' ), $path, $reason ) );
	}

	/**
	 * A path whose step names a different block than the one at that position.
	 *
	 * @param string $path   The path given.
	 * @param string $actual The full name of the block at that position.
	 */
	public static function path_mismatch( string $path, string $actual ): \WP_Error {
		/* translators: 1: the path, 2: block name found at that position */
		return self::make( 'path_mismatch', sprintf( __( '%1$s doesn\'t exist: that position holds %2$s. Use the paths from the outline your last call returned.', 'publishio' ), $path, $actual ) );
	}

	/**
	 * A path past the last block at some level.
	 *
	 * @param string $path   The path given.
	 * @param string $where  Where the count was taken, as a path or "the post".
	 * @param int    $count  How many blocks that level has.
	 */
	public static function path_not_found( string $path, string $where, int $count ): \WP_Error {
		/* translators: 1: the path, 2: the parent, 3: number of blocks it has */
		return self::make( 'path_not_found', sprintf( _n( '%1$s doesn\'t exist: %2$s has %3$d block. Use the paths from the outline your last call returned.', '%1$s doesn\'t exist: %2$s has %3$d blocks. Use the paths from the outline your last call returned.', $count, 'publishio' ), $path, $where, $count ), 404 );
	}

	/**
	 * A path into the content of a synced pattern.
	 *
	 * @param string $path         The path given.
	 * @param string $pattern_path The path of the synced pattern.
	 * @param string $title        The pattern's title.
	 */
	public static function inside_synced_pattern( string $path, string $pattern_path, string $title ): \WP_Error {
		/* translators: 1: the path, 2: the pattern's title, 3: the pattern's path */
		return self::make( 'inside_synced_pattern', sprintf( __( '%1$s is inside the synced pattern "%2$s", which isn\'t stored in this post. To change its text here, replace %3$s with new values in its content attribute.', 'publishio' ), $path, $title, $pattern_path ) );
	}

	/**
	 * An insert into a container that has no inner blocks yet.
	 *
	 * @param string $path The container's path.
	 */
	public static function empty_container( string $path ): \WP_Error {
		/* translators: %s: the container's path */
		return self::make( 'empty_container', sprintf( __( '%s has no inner blocks, so there\'s no place to insert. Use replace-block with the block and its new inner blocks.', 'publishio' ), $path ) );
	}

	/**
	 * A placement that doesn't fit the target.
	 *
	 * @param string $message Why the placement doesn't fit.
	 */
	public static function invalid_placement( string $message ): \WP_Error {
		return self::make( 'invalid_placement', $message );
	}

	/**
	 * A post that doesn't exist.
	 *
	 * @param int $post_id The ID given.
	 */
	public static function post_not_found( int $post_id ): \WP_Error {
		/* translators: %d: post ID */
		return self::make( 'post_not_found', sprintf( __( 'There\'s no post with ID %d. Use search-posts to find it.', 'publishio' ), $post_id ), 404 );
	}

	/**
	 * A post the current user can't edit.
	 *
	 * @param \WP_Post $post The post.
	 */
	public static function forbidden( \WP_Post $post ): \WP_Error {
		/* translators: %s: post title */
		return self::make( 'forbidden', sprintf( __( 'Your account can\'t edit "%s".', 'publishio' ), self::title( $post ) ), 403 );
	}

	/**
	 * A post type that doesn't use the block editor, or that the editing tools don't change.
	 *
	 * @param \WP_Post $post The post.
	 */
	public static function no_block_editor( \WP_Post $post ): \WP_Error {
		$type  = get_post_type_object( $post->post_type );
		$label = $type ? $type->labels->name : $post->post_type;

		/* translators: %s: post type name, such as "Products" */
		return self::make( 'no_block_editor', sprintf( __( '%s don\'t use the block editor, so their content can\'t be edited here.', 'publishio' ), $label ) );
	}

	/**
	 * A saved pattern, template or other site content, which the post tools don't change.
	 *
	 * @param \WP_Post $post The post.
	 */
	public static function unsupported_type( \WP_Post $post ): \WP_Error {
		$hint = 'wp_block' === $post->post_type
			? __( 'It\'s a saved pattern: use update-pattern to change it.', 'publishio' )
			: __( 'Change it in the Site Editor.', 'publishio' );

		/* translators: 1: post title, 2: what to use instead */
		return self::make( 'unsupported_type', sprintf( __( '"%1$s" isn\'t a post or page, so these tools don\'t change it. %2$s', 'publishio' ), self::title( $post ), $hint ) );
	}

	/**
	 * A post that is open in the block editor.
	 *
	 * @param \WP_Post $post    The post.
	 * @param int      $user_id The user who has it open.
	 */
	public static function post_locked( \WP_Post $post, int $user_id ): \WP_Error {
		$user = get_userdata( $user_id );
		$who  = get_current_user_id() === $user_id || ! $user ? __( 'you, in another tab or window', 'publishio' ) : $user->display_name;

		/* translators: 1: post title, 2: who has it open */
		return self::make( 'post_locked', sprintf( __( '"%1$s" is open in the block editor by %2$s. A change made now would be lost when the editor saves. Ask the user to save and close it there, then try again.', 'publishio' ), self::title( $post ), $who ), 409 );
	}

	/**
	 * A post that changed after the call read it, such as by another call made at the same time.
	 *
	 * @param \WP_Post $post The post.
	 */
	public static function post_changed( \WP_Post $post ): \WP_Error {
		/* translators: %s: post title */
		return self::make( 'post_changed', sprintf( __( '"%s" changed after this call read it, so nothing was saved. Make one change at a time: get the outline again, then repeat this change with the new paths.', 'publishio' ), self::title( $post ) ), 409 );
	}

	/**
	 * A change to a published, scheduled or private post without the user's approval.
	 *
	 * @param \WP_Post $post  The post.
	 * @param string   $token A fresh approval token for this post and user.
	 */
	public static function live_post_needs_approval( \WP_Post $post, string $token ): \WP_Error {
		$state = match ( $post->post_status ) {
			'future'  => __( 'scheduled, so this change goes live when it publishes', 'publishio' ),
			'private' => __( 'private, so this change is seen right away by everyone who can read it', 'publishio' ),
			default   => __( 'published, so this change goes live immediately', 'publishio' ),
		};

		/* translators: 1: post title, 2: what the post's status means for the change, 3: the approval token */
		return self::make( 'live_post_needs_approval', sprintf( __( '"%1$s" is %2$s. Ask the user first, unless they already agreed to changes to this page. If they agree, call again with approval_token: "%3$s".', 'publishio' ), self::title( $post ), $state, $token ), 409 );
	}

	/**
	 * A post title for messages.
	 *
	 * @param \WP_Post $post The post.
	 */
	private static function title( \WP_Post $post ): string {
		$title = wp_strip_all_tags( get_the_title( $post ) );

		return '' === $title ? __( '(no title)', 'publishio' ) : $title;
	}
}
