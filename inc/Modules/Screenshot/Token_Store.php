<?php
/**
 * Single-use preview token management backed by WordPress transients.
 *
 * Tokens are created by the MCP ability and consumed exactly once by the
 * Preview_Endpoint. A 10-minute TTL acts as a fallback if the endpoint is
 * never called.
 *
 * @package rtCamp\Publish_With_AI\Modules\Screenshot
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Screenshot;

/**
 * Class - Token_Store
 */
final class Token_Store {
	/**
	 * Transient key prefix.
	 */
	private const PREFIX = 'rtpwai_preview_';

	/**
	 * Token TTL in seconds (10 minutes).
	 */
	private const TTL = 600;

	/**
	 * Create a new single-use preview token for the given post and user.
	 *
	 * @param int $post_id Post ID to preview.
	 * @param int $user_id User ID whose session will render the preview.
	 *
	 * @return string The generated token (UUID v4).
	 */
	public static function create( int $post_id, int $user_id ): string {
		$token = wp_generate_uuid4();

		set_transient(
			self::PREFIX . $token,
			[
				'post_id' => $post_id,
				'user_id' => $user_id,
			],
			self::TTL
		);

		return $token;
	}

	/**
	 * Consume a token — validates it and deletes it atomically.
	 *
	 * Returns the stored payload on success, null if the token does not exist
	 * or has already been consumed/expired.
	 *
	 * @param string $token Token to consume.
	 *
	 * @return array{post_id: int, user_id: int}|null
	 */
	public static function consume( string $token ): ?array {
		$key  = self::PREFIX . sanitize_key( $token );
		$data = get_transient( $key );

		if ( ! is_array( $data ) || empty( $data['post_id'] ) || empty( $data['user_id'] ) ) {
			return null;
		}

		delete_transient( $key );

		return [
			'post_id' => (int) $data['post_id'],
			'user_id' => (int) $data['user_id'],
		];
	}
}
