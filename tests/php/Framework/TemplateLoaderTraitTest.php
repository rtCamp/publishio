<?php
/**
 * Unit tests for TemplateLoaderTrait.
 *
 * @package rtCamp\Publish_With_AI\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Tests\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publish_With_AI\Framework\TemplateLoaderTrait;
use rtCamp\Publish_With_AI\Tests\TestCase;

/**
 * Test double for our trait.
 */
final class TemplateLoaderTraitTestDouble {
	use TemplateLoaderTrait;

	/**
	 * {@inheritDoc}
	 */
	public function __construct() {
		$this->hook_prefix        = 'test_plugin';
		$this->hook_separator     = '/';
		$this->template_dir       = sys_get_temp_dir() . '/test-plugin-templates';
		$this->template_theme_dir = 'test-plugin';
	}

	/**
	 * Expose get_template_part for testing.
	 *
	 * @param string               $slug Template slug.
	 * @param string|null          $name Optional. Template variation name.
	 * @param array<string, mixed> $args Optional. Data to pass to the template.
	 * @param bool                 $load Optional. Whether to load the template.
	 */
	public function call_get_template_part( string $slug, ?string $name = null, array $args = [], bool $load = false ): string|false {
		return $this->get_template_part( $slug, $name, $args, $load );
	}

	/**
	 * Expose get_template_file_names for testing via reflection.
	 *
	 * @param string      $slug Template slug.
	 * @param string|null $name Template variation name.
	 * @return array<string>
	 */
	public function call_get_template_file_names( string $slug, ?string $name = null ): array {
		$method = new \ReflectionMethod( self::class, 'get_template_file_names' );
		$method->setAccessible( true );
		return $method->invoke( $this, $slug, $name );
	}

	/**
	 * Expose locate_template for testing via reflection.
	 *
	 * @param array<int, string> $templates Template files to search for.
	 */
	public function call_locate_template( array $templates ): string|false {
		$method = new \ReflectionMethod( self::class, 'locate_template' );
		$method->setAccessible( true );
		return $method->invoke( $this, $templates );
	}

	/**
	 * Expose get_template_paths for testing via reflection.
	 *
	 * @return array<int, string>
	 */
	public function call_get_template_paths(): array {
		$method = new \ReflectionMethod( self::class, 'get_template_paths' );
		$method->setAccessible( true );
		return $method->invoke( $this );
	}

	/**
	 * Expose find_template for testing via reflection.
	 *
	 * @param array<int, string> $templates Template files to search for.
	 */
	public function call_find_template( array $templates ): string|false {
		$method = new \ReflectionMethod( self::class, 'find_template' );
		$method->setAccessible( true );
		return $method->invoke( $this, $templates );
	}

	/**
	 * Clear the template location cache.
	 */
	public function clear_cache(): void {
		$prop = new \ReflectionProperty( self::class, 'template_location_cache' );
		$prop->setAccessible( true );
		$prop->setValue( null, [] );
	}

	/**
	 * Set the template directory.
	 *
	 * @param string $dir Directory path.
	 */
	public function set_template_dir( string $dir ): void {
		$this->template_dir = $dir;
	}
}

/**
 * Class - TemplateLoaderTraitTest
 */
#[CoversClass( \rtCamp\Publish_With_AI\Framework\TemplateLoaderTrait::class )]
class TemplateLoaderTraitTest extends TestCase {
	/**
	 * Test double instance.
	 */
	private TemplateLoaderTraitTestDouble $loader;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->loader = new TemplateLoaderTraitTestDouble();
		$this->loader->clear_cache();
	}

	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		$this->loader->clear_cache();
		remove_all_filters( 'test_plugin/get_template_part' );
		remove_all_filters( 'test_plugin/template_args' );
		remove_all_filters( 'test_plugin/located_template' );
		remove_all_filters( 'test_plugin/template_paths' );
		remove_all_actions( 'test_plugin/get_template_part_content' );

		parent::tearDown();
	}

	/**
	 * Tests that get_template_part returns false when no template found.
	 */
	public function test_get_template_part_returns_false_when_no_template_found(): void {
		$temp_dir = sys_get_temp_dir() . '/test-no-templates-' . uniqid();
		$this->loader->set_template_dir( $temp_dir );

		$result = $this->loader->call_get_template_part( 'nonexistent' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests that get_template_part returns path when template found in plugin.
	 */
	public function test_get_template_part_returns_path_when_template_found_in_plugin(): void {
		$temp_dir = sys_get_temp_dir() . '/test-plugin-templates-' . uniqid();
		mkdir( $temp_dir, 0777, true );
		file_put_contents( $temp_dir . '/content.php', '<?php // Test template' );

		$this->loader->set_template_dir( $temp_dir );

		$result = $this->loader->call_get_template_part( 'content' );

		$this->assertNotFalse( $result );
		$this->assertStringContainsString( 'content.php', $result );
		$this->assertStringContainsString( $temp_dir, $result );

		// Cleanup.
		unlink( $temp_dir . '/content.php' );
		rmdir( $temp_dir );
	}

	/**
	 * Tests that get_template_part with name variant returns correct template.
	 */
	public function test_get_template_part_with_name_variant_returns_correct_template(): void {
		$temp_dir = sys_get_temp_dir() . '/test-plugin-templates-' . uniqid();
		mkdir( $temp_dir, 0777, true );
		file_put_contents( $temp_dir . '/content-card.php', '<?php // Card template' );
		file_put_contents( $temp_dir . '/content.php', '<?php // Base template' );

		$this->loader->set_template_dir( $temp_dir );

		$result = $this->loader->call_get_template_part( 'content', 'card' );

		$this->assertNotFalse( $result );
		$this->assertStringContainsString( 'content-card.php', $result );

		// Cleanup.
		unlink( $temp_dir . '/content-card.php' );
		unlink( $temp_dir . '/content.php' );
		rmdir( $temp_dir );
	}

	/**
	 * Tests that get_template_part outputs the correct template.
	 */
	public function test_get_template_part_outputs_correct_template(): void {
		$temp_dir = sys_get_temp_dir() . '/test-plugin-templates-' . uniqid();
		mkdir( $temp_dir, 0777, true );
		$success_content = 'Card Template';
		file_put_contents( $temp_dir . '/content-card.php', '<?php echo $args["success_content"];' );
		file_put_contents( $temp_dir . '/content.php', '<?php echo "Base Template";' );

		$this->loader->set_template_dir( $temp_dir );

		ob_start();
		$this->loader->call_get_template_part( 'content', 'card', [ 'success_content' => $success_content ], true );
		$output = ob_get_clean();

		$this->assertSame( $success_content, $output );
		// Cleanup.
		unlink( $temp_dir . '/content-card.php' );
		unlink( $temp_dir . '/content.php' );
		rmdir( $temp_dir );
	}

	/**
	 * Tests that get_template_part falls back to base template when name variant not found.
	 */
	public function test_get_template_part_falls_back_to_base_template(): void {
		$temp_dir = sys_get_temp_dir() . '/test-plugin-templates-' . uniqid();
		mkdir( $temp_dir, 0777, true );
		file_put_contents( $temp_dir . '/content.php', '<?php // Base template' );

		$this->loader->set_template_dir( $temp_dir );

		$result = $this->loader->call_get_template_part( 'content', 'card' );

		$this->assertNotFalse( $result );
		$this->assertStringContainsString( 'content.php', $result );
		$this->assertStringNotContainsString( 'content-card.php', $result );

		// Cleanup.
		unlink( $temp_dir . '/content.php' );
		rmdir( $temp_dir );
	}

	/**
	 * Tests that get_template_part fires action hook.
	 */
	public function test_get_template_part_fires_action_hook(): void {
		$called = [];
		add_action(
			'test_plugin/get_template_part_content',
			static function ( string $slug, ?string $name, array $args ) use ( &$called ): void {
				$called[] = [
					'slug' => $slug,
					'name' => $name,
					'args' => $args,
				];
			},
			10,
			3
		);

		$this->loader->call_get_template_part( 'content', 'card', [ 'test' => 'value' ] );

		$this->assertNotEmpty( $called );
		$this->assertSame( 'content', $called[0]['slug'] );
		$this->assertSame( 'card', $called[0]['name'] );
		$this->assertSame( [ 'test' => 'value' ], $called[0]['args'] );
	}

	/**
	 * Tests that get_template_part applies template args filter.
	 */
	public function test_get_template_part_applies_template_args_filter(): void {
		$filter_called = false;
		$filtered_args = [];

		add_filter(
			'test_plugin/template_args',
			static function ( array $args, string $slug, ?string $name ) use ( &$filter_called, &$filtered_args ): array {
				$filter_called    = true;
				$filtered_args    = [
					'slug'          => $slug,
					'name'          => $name,
					'original_args' => $args,
				];
				$args['filtered'] = true;
				return $args;
			},
			10,
			3
		);

		$this->loader->call_get_template_part( 'content', 'card', [ 'test' => 'value' ] );

		$this->assertTrue( $filter_called, 'Filter should be called' );
		$this->assertSame( 'content', $filtered_args['slug'] );
		$this->assertSame( 'card', $filtered_args['name'] );
		$this->assertSame( [ 'test' => 'value' ], $filtered_args['original_args'] );
	}

	/**
	 * Tests that get_template_file_names generates basic template.
	 */
	public function test_get_template_file_names_generates_basic_template(): void {
		$result = $this->loader->call_get_template_file_names( 'content', null );

		$this->assertSame( [ 'content.php' ], $result );
	}

	/**
	 * Tests that get_template_file_names generates name variant first.
	 */
	public function test_get_template_file_names_generates_name_variant_first(): void {
		$result = $this->loader->call_get_template_file_names( 'content', 'card' );

		$this->assertSame( [ 'content-card.php', 'content.php' ], $result );
	}

	/**
	 * Tests that get_template_file_names applies filter.
	 */
	public function test_get_template_file_names_applies_filter(): void {
		add_filter(
			'test_plugin/template_file_names',
			static function ( array $templates, string $slug ): array {
				$templates[] = 'custom-' . $slug . '.php';
				return $templates;
			},
			10,
			2
		);

		$result = $this->loader->call_get_template_file_names( 'content', null );

		$this->assertContains( 'content.php', $result );
		$this->assertContains( 'custom-content.php', $result );
	}

	/**
	 * Tests that locate_template returns false for empty templates.
	 */
	public function test_locate_template_returns_false_for_empty_templates(): void {
		$result = $this->loader->call_locate_template( [] );

		$this->assertFalse( $result );
	}

	/**
	 * Tests that locate_template sanitizes template names.
	 */
	public function test_locate_template_sanitizes_template_names(): void {
		$temp_dir = sys_get_temp_dir() . '/test-plugin-templates-' . uniqid();
		mkdir( $temp_dir, 0777, true );

		$this->loader->set_template_dir( $temp_dir );

		// Path traversal attempt should be sanitized.
		$result = $this->loader->call_locate_template( [ '../../../etc/passwd' ] );

		$this->assertFalse( $result );

		// Cleanup.
		rmdir( $temp_dir );
	}

	/**
	 * Tests that locate_template applies located_template filter.
	 */
	public function test_locate_template_applies_located_template_filter(): void {
		$temp_dir = sys_get_temp_dir() . '/test-plugin-templates-' . uniqid();
		mkdir( $temp_dir, 0777, true );
		file_put_contents( $temp_dir . '/content.php', '<?php // Test' );

		$this->loader->set_template_dir( $temp_dir );

		add_filter(
			'test_plugin/located_template',
			static function ( $located ) {
				return '/custom/path/' . basename( (string) $located );
			}
		);

		$result = $this->loader->call_locate_template( [ 'content.php' ] );

		$this->assertStringContainsString( '/custom/path/', $result );

		// Cleanup.
		unlink( $temp_dir . '/content.php' );
		rmdir( $temp_dir );
	}

	/**
	 * Tests that find_template caches results.
	 */
	public function test_find_template_caches_results(): void {
		$temp_dir = sys_get_temp_dir() . '/test-plugin-templates-' . uniqid();
		mkdir( $temp_dir, 0777, true );
		file_put_contents( $temp_dir . '/content.php', '<?php // Test' );

		$this->loader->set_template_dir( $temp_dir );

		$result1 = $this->loader->call_find_template( [ 'content.php' ] );

		// Delete the template file.
		unlink( $temp_dir . '/content.php' );

		// Should still return cached result.
		$result2 = $this->loader->call_find_template( [ 'content.php' ] );

		$this->assertSame( $result1, $result2 );

		// Cleanup.
		rmdir( $temp_dir );
	}

	/**
	 * Tests that get_template_paths returns correct priority order.
	 */
	public function test_get_template_paths_returns_correct_priority_order(): void {
		$result = $this->loader->call_get_template_paths();

		// Should have at least plugin path.
		$this->assertNotEmpty( $result );

		// All paths should be trailing-slashed.
		foreach ( $result as $path ) {
			$this->assertStringEndsWith( '/', $path );
		}
	}

	/**
	 * Tests that get_template_paths applies filter.
	 */
	public function test_get_template_paths_applies_filter(): void {
		add_filter(
			'test_plugin/template_paths',
			static function ( array $paths ): array {
				$paths[5] = '/custom/path/';
				return $paths;
			}
		);

		$result = $this->loader->call_get_template_paths();

		$this->assertArrayHasKey( 5, $result );
		$this->assertSame( '/custom/path/', $result[5] );
	}

	/**
	 * Tests that get_template_part returns false when slug is empty after sanitization.
	 */
	public function test_locate_template_returns_false_for_invalid_template_names(): void {
		// Empty strings should be filtered out.
		$result = $this->loader->call_locate_template( [ '', '  ' ] );

		$this->assertFalse( $result );
	}
}
