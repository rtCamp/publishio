<?php
/**
 * Tests for Live_Approval.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\Editing;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\Editing\Live_Approval;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Live_ApprovalTest
 */
#[CoversClass( Live_Approval::class )]
class Live_ApprovalTest extends TestCase {
	/**
	 * Tokens that must not verify for post 10 and user 20.
	 *
	 * @return array<string, array{string}>
	 */
	public static function rejected_tokens(): array {
		$now = time();

		return [
			'empty'              => [ '' ],
			'no signature'       => [ (string) ( $now + 60 ) ],
			'not hex'            => [ ( $now + 60 ) . '.' . str_repeat( 'z', 64 ) ],
			'wrong signature'    => [ ( $now + 60 ) . '.' . str_repeat( 'a', 64 ) ],
			'expired'            => [ self::signed( 10, 20, $now - 1 ) ],
			'too far ahead'      => [ self::signed( 10, 20, $now + Live_Approval::TTL + 600 ) ],
			'another user'       => [ self::signed( 10, 21, $now + 60 ) ],
			'another post'       => [ self::signed( 11, 20, $now + 60 ) ],
			'extra text'         => [ self::signed( 10, 20, $now + 60 ) . ' ' ],
			'another purpose'    => [ ( $now + 60 ) . '.' . hash_hmac( 'sha256', 'other|10|20|' . ( $now + 60 ), wp_salt( 'auth' ) ) ],
			'another salt'       => [ ( $now + 60 ) . '.' . hash_hmac( 'sha256', 'publishio-live-edit|10|20|' . ( $now + 60 ), 'not-the-salt' ) ],
			'surrounded by json' => [ '{"token":"' . self::signed( 10, 20, $now + 60 ) . '"}' ],
		];
	}

	/**
	 * Forged, expired and misdirected tokens are refused.
	 *
	 * @param string $token The token.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'rejected_tokens' )]
	public function test_refuses_invalid_tokens( string $token ): void {
		$this->assertFalse( Live_Approval::verify( $token, 10, 20 ) );
	}

	/**
	 * An issued token verifies for its post and user only.
	 */
	public function test_issued_token_verifies_for_its_post_and_user(): void {
		$token = Live_Approval::issue( 10, 20 );

		$this->assertMatchesRegularExpression( '/^\d+\.[a-f0-9]{64}$/', $token );
		$this->assertTrue( Live_Approval::verify( $token, 10, 20 ) );
		$this->assertFalse( Live_Approval::verify( $token, 10, 0 ) );
	}

	/**
	 * Published, scheduled and private posts are live.
	 */
	public function test_live_statuses(): void {
		foreach ( [ 'publish', 'future', 'private' ] as $status ) {
			$this->assertTrue(
				Live_Approval::is_live(
					get_post(
						self::factory()->post->create(
							[
								'post_status' => $status,
								'post_date'   => 'future' === $status ? gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) : '',
							]
						)
					)
				)
			);
		}

		$this->assertFalse( Live_Approval::is_live( get_post( self::factory()->post->create( [ 'post_status' => 'draft' ] ) ) ) );
	}

	/**
	 * A token signed the way Live_Approval signs, for any expiry.
	 *
	 * @param int $post_id The post ID.
	 * @param int $user_id The user ID.
	 * @param int $expires The expiry timestamp.
	 */
	private static function signed( int $post_id, int $user_id, int $expires ): string {
		return $expires . '.' . hash_hmac( 'sha256', 'publishio-live-edit|' . $post_id . '|' . $user_id . '|' . $expires, wp_salt( 'auth' ) );
	}
}
