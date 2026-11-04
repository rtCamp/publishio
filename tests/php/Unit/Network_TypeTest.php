<?php
/**
 * Checks that a multisite run is on the network type it asked for.
 *
 * @package rtCamp\Publishio\Tests\Unit
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Network_TypeTest
 */
#[CoversNothing]
#[Group( 'ms-required' )]
class Network_TypeTest extends TestCase {
	/**
	 * WP_TESTS_SUBDOMAIN=1 makes a subdomain network, so CI's subdomain job can't pass on a subdirectory one.
	 */
	public function test_runs_on_the_requested_network_type(): void {
		$this->skipWithoutMultisite();

		$this->assertSame( '1' === getenv( 'WP_TESTS_SUBDOMAIN' ), is_subdomain_install() );
	}
}
