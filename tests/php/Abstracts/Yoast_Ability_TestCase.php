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
 * Loads the Yoast SEO stand-ins with every field shown. Subclasses must run in separate processes.
 */
abstract class Yoast_Ability_TestCase extends Ability_TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__ ) . '/Stubs/yoast-seo.php';

		\WPSEO_Meta::$tabs                    = [
			'general'  => [
				'title'    => [],
				'metadesc' => [],
				'focuskw'  => [],
			],
			'advanced' => [
				'meta-robots-noindex'  => [],
				'meta-robots-nofollow' => [],
				'canonical'            => [],
			],
			'social'   => [
				'opengraph-title'       => [],
				'opengraph-description' => [],
				'twitter-title'         => [],
				'twitter-description'   => [],
			],
			'schema'   => [
				'schema_page_type'    => [],
				'schema_article_type' => [],
			],
		];
		\WPSEO_Utils::$metabox_active         = true;
		\WPSEO_Metabox_Analysis_SEO::$enabled = true;

		parent::setUp();
	}

	/**
	 * Hide a metabox tab from the current user, as Yoast does.
	 *
	 * @param string $tab Metabox tab.
	 */
	protected function hide_tab( string $tab ): void {
		\WPSEO_Meta::$tabs[ $tab ] = [];
	}

	/**
	 * A post written by a new user with the given role, with that user signed in.
	 *
	 * @param string $role User role.
	 */
	protected function own_post_as( string $role ): int {
		$user_id = self::factory()->user->create( [ 'role' => $role ] );
		wp_set_current_user( $user_id );

		return self::factory()->post->create( [ 'post_author' => $user_id ] );
	}
}
