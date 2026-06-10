<?php
/**
 * Base test case for Abilities API ability tests.
 *
 * @package rtCamp\Publishio\Tests\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Abstracts;

use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Ability_TestCase
 *
 * Shared scaffolding for testing a single registered ability. Concrete classes
 * implement {@see Ability_TestCase::ability_name()} to declare which one.
 */
abstract class Ability_TestCase extends TestCase {
	/**
	 * `map_meta_cap` filters registered during the current test.
	 *
	 * @var array<int, callable>
	 */
	private array $cap_filters = [];

	/**
	 * The fully-qualified ability name under test, e.g. `publishio/set-post-terms`.
	 */
	abstract protected function ability_name(): string;

	/**
	 * Skip the whole class when the ability is not registered.
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wp_get_ability' ) || null === wp_get_ability( $this->ability_name() ) ) {
			$this->markTestSkipped( sprintf( 'Ability "%s" is not available.', $this->ability_name() ) );
		}
	}

	/**
	 * Remove any capability filters and reset the current user.
	 */
	protected function tearDown(): void {
		foreach ( $this->cap_filters as $filter ) {
			remove_filter( 'map_meta_cap', $filter, 10 );
		}
		$this->cap_filters = [];

		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Execute the ability under test with the given input.
	 *
	 * @param array<string, mixed> $input Ability input.
	 *
	 * @return mixed|\WP_Error
	 */
	protected function execute( array $input ) {
		return wp_get_ability( $this->ability_name() )->execute( $input );
	}

	/**
	 * Deny a meta capability for a single object ID for the current user.
	 *
	 * @param string $denied_cap The meta capability to deny, e.g. `assign_term`.
	 * @param int    $object_id  The object ID the capability is denied for.
	 */
	protected function deny_meta_cap( string $denied_cap, int $object_id ): void {
		$filter = static function ( array $caps, string $cap, int $user_id, array $args ) use ( $denied_cap, $object_id ): array { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter,Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
			if ( $denied_cap === $cap && isset( $args[0] ) && (int) $args[0] === $object_id ) {
				return [ 'do_not_allow' ];
			}

			return $caps;
		};

		add_filter( 'map_meta_cap', $filter, 10, 4 );
		$this->cap_filters[] = $filter;
	}

	/**
	 * Assert the result is a WP_Error carrying the expected error code.
	 *
	 * @param mixed  $result        The ability result.
	 * @param string $expected_code The expected WP_Error code.
	 */
	protected function assertAbilityError( $result, string $expected_code ): void {
		$this->assertWPError( $result );
		$this->assertSame( $expected_code, $result->get_error_code() );
	}
}
