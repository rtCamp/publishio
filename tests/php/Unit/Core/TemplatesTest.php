<?php
/**
 * Unit tests for Templates class.
 *
 * @package rtCamp\Publishio\Tests\Unit\Core
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Core\Templates;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - TemplatesTest
 */
#[CoversClass( \rtCamp\Publishio\Core\Templates::class )]
class TemplatesTest extends TestCase {
	/**
	 * Reset the Templates singleton instance.
	 */
	private function reset_templates_instance(): void {
		$ref = new \ReflectionClass( Templates::class );

		// Reset the instance.
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );

		// Reset the cache.
		$prop = $ref->getProperty( 'template_location_cache' );
		$prop->setAccessible( true );
		$prop->setValue( null, [] );
	}

	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		$this->reset_templates_instance();
		remove_all_filters( 'publishio/template_args' );
		remove_all_filters( 'publishio/located_template' );
		remove_all_filters( 'publishio/template_paths' );
		remove_all_actions( 'publishio/get_template_part_test' );

		parent::tearDown();
	}

	/**
	 * Test that static get_template_part returns false for nonexistent template.
	 */
	public function test_static_get_template_part_returns_false_for_nonexistent(): void {
		$this->reset_templates_instance();

		// Static method should return false when no template exists.
		$static_result = Templates::get_template_part( 'nonexistent', null, [], false );

		$this->assertFalse( $static_result );
	}

	/**
	 * Test that static get_template_part passes correct arguments.
	 */
	public function test_static_get_template_part_passes_correct_arguments(): void {
		$this->reset_templates_instance();

		$captured = [];
		add_action(
			'publishio/get_template_part_test',
			static function ( string $slug, ?string $name, array $args ) use ( &$captured ): void {
				$captured = [
					'slug' => $slug,
					'name' => $name,
					'args' => $args,
				];
			},
			10,
			3
		);

		Templates::get_template_part( 'test', 'variant', [ 'key' => 'value' ] );

		$this->assertSame( 'test', $captured['slug'] );
		$this->assertSame( 'variant', $captured['name'] );
		$this->assertSame( [ 'key' => 'value' ], $captured['args'] );
	}

	/**
	 * Test that get_template_part outputs content correctly.
	 */
	public function test_get_template_part_outputs_content(): void {
		$this->reset_templates_instance();
		// Create a temporary template file.
		$temp_dir = sys_get_temp_dir() . '/test-templates';
		mkdir( $temp_dir );
		$template_path = $temp_dir . '/test-template.php';

		file_put_contents( $template_path, '<?php echo $args["message"];' );

		// Add filter to locate our temp template.
		add_filter(
			'publishio/located_template',
			static function ( $located, $templates ) use ( $template_path ) {
				if ( in_array( 'test-template.php', $templates, true ) ) {
					return $template_path;
				}
				return $located;
			},
			10,
			2
		);

		// Capture the output.
		ob_start();
		Templates::get_template_part( 'test-template', null, [ 'message' => 'Hello, World!' ] );

		$this->assertSame( 'Hello, World!', ob_get_clean() );

		// Try again with new args.
		ob_start();
		Templates::get_template_part( 'test-template', null, [ 'message' => 'Goodbye, World!' ] );
		$this->assertSame( 'Goodbye, World!', ob_get_clean() );

		// Clean up temp file.
		unlink( $template_path );
		rmdir( $temp_dir );
	}
}
