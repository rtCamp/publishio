<?php
/**
 * Approval tokens for changes to published, scheduled and private posts.
 *
 * @package rtCamp\Publishio\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Editing;

/**
 * Class - Live_Approval
 *
 * A token is `{expires}.{signature}`, signed with the site's auth salt over the post, the user and the expiry, so nothing is stored. The server never sees the user's answer in the chat; the token proves the AI received the warning before the change was made. A token can't be revoked before it expires, except by changing the salts.
 */
final class Live_Approval {
	public const TTL = 30 * MINUTE_IN_SECONDS;

	public const LIVE_STATUSES = [ 'publish', 'future', 'private' ];

	private const PURPOSE = 'publishio-live-edit';
	private const PATTERN = '/^(\d{10,12})\.([a-f0-9]{64})$/';

	/**
	 * Whether a post's changes are seen by visitors, now or when it publishes.
	 *
	 * @param \WP_Post $post The post.
	 */
	public static function is_live( \WP_Post $post ): bool {
		return in_array( $post->post_status, self::LIVE_STATUSES, true );
	}

	/**
	 * Issue a token for a post and user.
	 *
	 * @param int $post_id The post ID.
	 * @param int $user_id The user ID.
	 */
	public static function issue( int $post_id, int $user_id ): string {
		$expires = time() + self::TTL;

		return $expires . '.' . self::sign( $post_id, $user_id, $expires );
	}

	/**
	 * Whether a token is valid for a post and user and hasn't expired.
	 *
	 * @param string $token   The token.
	 * @param int    $post_id The post ID.
	 * @param int    $user_id The user ID.
	 */
	public static function verify( string $token, int $post_id, int $user_id ): bool {
		if ( $post_id < 1 || $user_id < 1 || ! preg_match( self::PATTERN, $token, $matches ) ) {
			return false;
		}

		$expires = (int) $matches[1];

		if ( $expires < time() || $expires > time() + self::TTL ) {
			return false;
		}

		return hash_equals( self::sign( $post_id, $user_id, $expires ), $matches[2] );
	}

	/**
	 * The signature over a post, user and expiry.
	 *
	 * @param int $post_id The post ID.
	 * @param int $user_id The user ID.
	 * @param int $expires The expiry timestamp.
	 */
	private static function sign( int $post_id, int $user_id, int $expires ): string {
		return hash_hmac( 'sha256', self::PURPOSE . '|' . $post_id . '|' . $user_id . '|' . $expires, wp_salt( 'auth' ) );
	}
}
