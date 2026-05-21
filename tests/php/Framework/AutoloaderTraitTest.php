<?php
/**
 * Unit tests for AutoloaderTrait.
 *
 * @package rtCamp\Publish_With_AI\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Tests\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publish_With_AI\Framework\AutoloaderTrait;
use rtCamp\Publish_With_AI\Tests\TestCase;

/**
 * Test double for our trait.
 */
final class AutoloaderTraitTestDouble {
	use AutoloaderTrait;

	/**
	 * {@inheritDoc}
	 */
	private static function get_autoloader_error_message(): string {
		return 'Trait test autoloader message';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function call_require_autoloader( string $autoloader_file ): bool {
		return self::require_autoloader( $autoloader_file );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function call_missing_autoloader_notice(): void {
		self::missing_autoloader_notice();
	}
}

/**
 * Class - AutoloaderTraitTest
 */
#[CoversClass( \rtCamp\Publish_With_AI\Framework\AutoloaderTrait::class )]
class AutoloaderTraitTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'network_admin_notices' );

		parent::tearDown();
	}

	/**
	 * Tests `require_autoloader()` returns true on success.
	 */
	public function test_require_autoloader_returns_true_for_readable_autoloader_file(): void {
		// We don't reuse vendor/autoload.php so it can be portable to other plugins.
		$autoloader_file = tempnam( sys_get_temp_dir(), 'autoload-trait-' );
		file_put_contents( $autoloader_file, '<?php return true;' );

		$this->assertTrue( AutoloaderTraitTestDouble::call_require_autoloader( $autoloader_file ) );

		unlink( $autoloader_file );

		// Tests that the cache is working by calling again with the same file, even though it's now missing.
		$this->assertTrue( AutoloaderTraitTestDouble::call_require_autoloader( $autoloader_file ) );
	}

	/**
	 * Tests `require_autoloader()` returns false and registers notices when the file is missing.
	 */
	public function test_require_autoloader_returns_false_and_registers_notices_when_file_is_missing(): void {
		$this->assertFalse( AutoloaderTraitTestDouble::call_require_autoloader( '/tmp/does-not-exist/autoload.php' ) );
		$this->assertNotFalse( has_action( 'admin_notices' ) );
		$this->assertNotFalse( has_action( 'network_admin_notices' ) );
	}

	/**
	 * Tests the `missing_autoloader_notice outputs the correct notices
	 */
	public function test_missing_autoloader_notice_emits_expected_notice_and_doing_it_wrong(): void {
		$this->setExpectedIncorrectUsage( AutoloaderTraitTestDouble::class );

		$calls = [];
		add_action(
			'doing_it_wrong_run',
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- it's compacted
			static function ( string $passed_function, string $message, string $version ) use ( &$calls ): void {
				$calls[] = compact( 'passed_function', 'message', 'version' );
			},
			10,
			3
		);

		AutoloaderTraitTestDouble::call_missing_autoloader_notice();

		$this->expectOutputRegex( '/Trait test autoloader message/' );
		do_action( 'admin_notices' );

		$this->assertNotEmpty( $calls );
		$this->assertSame( AutoloaderTraitTestDouble::class, $calls[0]['passed_function'] );
	}
}
