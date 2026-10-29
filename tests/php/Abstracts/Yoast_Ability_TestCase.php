<?php
/**
 * Base class for the Yoast SEO ability tests.
 *
 * @package rtCamp\Publishio\Tests\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Abstracts;

/**
 * Class - Yoast_Ability_TestCase
 *
 * Runs with Yoast SEO active, in the Yoast suite (phpunit-yoast.xml.dist). Each test starts with Yoast's default settings.
 */
abstract class Yoast_Ability_TestCase extends Ability_TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			$this->fail( 'Yoast SEO is not loaded. Run this test with phpunit-yoast.xml.dist (npm run test:php:yoast).' );
		}

		parent::setUp();

		// The database is rolled back after each test, but Yoast also keeps its settings in memory.
		\WPSEO_Options::clear_cache();
	}

	/**
	 * Change a Yoast SEO setting for the current test.
	 *
	 * @param string $key   Setting name, e.g. 'disableadvanced_meta'.
	 * @param mixed  $value Setting value.
	 */
	protected function set_yoast_option( string $key, mixed $value ): void {
		\WPSEO_Options::set( $key, $value );
		\WPSEO_Options::clear_cache();
	}

	/**
	 * A post written by a new user with the given role, with that user signed in.
	 *
	 * @param string $role User role.
	 */
	protected function own_post_as( string $role ): int {
		$user_id = self::factory()->user->create( [ 'role' => $role ] );
		wp_set_current_user( $user_id );

		return self::factory()->post->create(
			[
				'post_author' => $user_id,
				'post_status' => 'draft',
			]
		);
	}
}
