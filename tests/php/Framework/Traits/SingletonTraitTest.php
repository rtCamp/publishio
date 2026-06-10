<?php
/**
 * Unit tests for Singleton trait.
 *
 * @package rtCamp\Publishio\Tests\Framework\Traits
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Framework\Traits;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Framework\Contracts\Traits\Singleton;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Test double for our trait.
 */
final class SingletonTraitTestDouble {
	use Singleton;

	/**
	 * Expose a simple property so instances are distinguishable if needed.
	 *
	 * @var string
	 */
	public $marker = 'singleton-test';
}

/**
 * Class - SingletonTraitTest
 */
#[CoversClass( \rtCamp\Publishio\Framework\Contracts\Traits\Singleton::class )]
class SingletonTraitTest extends TestCase {
	/**
	 * Tests that get_instance() returns the same instance each time.
	 */
	public function test_get_instance_returns_same_instance(): void {
		$a = SingletonTraitTestDouble::get_instance();
		$b = SingletonTraitTestDouble::get_instance();

		$this->assertInstanceOf( SingletonTraitTestDouble::class, $a );
		$this->assertSame( $a, $b );
		$this->assertSame( 'singleton-test', $a->marker );
	}

	/**
	 * Tests that cloning triggers an incorrect usage notice.
	 */
	public function test_clone_triggers_doing_it_wrong(): void {
		$this->setExpectedIncorrectUsage( '__clone' );

		$calls = [];
		add_action(
			'doing_it_wrong_run',
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- it's compacted
			static function ( string $called_function, string $message, string $version ) use ( &$calls ): void {
				$calls[] = compact( 'called_function', 'message', 'version' );
			},
			10,
			3
		);

		// Cloning should call the trait's __clone which uses _doing_it_wrong().
		clone SingletonTraitTestDouble::get_instance();

		$this->assertNotEmpty( $calls );
		$this->assertStringContainsString( 'should not be cloned', strtolower( $calls[0]['message'] ) );
	}

	/**
	 * Tests that unserializing triggers an incorrect usage notice via __wakeup().
	 */
	public function test_wakeup_triggers_doing_it_wrong(): void {
		$this->setExpectedIncorrectUsage( '__wakeup' );

		$calls = [];
		add_action(
			'doing_it_wrong_run',
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- it's compacted
			static function ( string $called_function, string $message, string $version ) use ( &$calls ): void {
				$calls[] = compact( 'called_function', 'message', 'version' );
			},
			10,
			3
		);

		$class      = SingletonTraitTestDouble::class;
		$serialized = sprintf( 'O:%d:"%s":0:{}', strlen( $class ), $class );
		@unserialize( $serialized );

		$this->assertNotEmpty( $calls );
		$this->assertStringContainsString( 'de-serializing', strtolower( $calls[0]['message'] ) );
	}
}
