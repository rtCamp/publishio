<?php
/**
 * Tests for the OAuth module's addresses.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Modules\MCP\OAuth\Config;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - ConfigTest
 */
#[CoversClass( Config::class )]
class ConfigTest extends TestCase {
	/**
	 * The issuer is the home URL, so its metadata address matches the rewrite rules when WordPress is in its own directory.
	 */
	public function test_issuer_is_the_home_url(): void {
		// A filter, since wp-env's WP_SITEURL constant overrides the option.
		add_filter( 'option_siteurl', static fn (): string => home_url( '/wp' ), PHP_INT_MAX );

		$this->assertSame( untrailingslashit( home_url() ), Config::get_issuer_url() );
	}
}
