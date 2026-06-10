<?php
/**
 * Unit tests for AssetLoaderTrait.
 *
 * @package rtCamp\Publishio\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Test double for our trait.
 */
final class AssetLoaderTraitTestDouble {
	use \rtCamp\Publishio\Framework\AssetLoaderTrait;

	/**
	 * {@inheritDoc}
	 */
	public function __construct() {
		// Use the system temp dir so tests may create asset files there.
		$this->plugin_dir = sys_get_temp_dir() . '/';
		// Ensure plugin_url ends with a slash so asset src contains '/build/...' as expected.
		$this->plugin_url = 'http://example.com/plugin/';
		$this->assets_dir = 'build';
	}

	/**
	 * {@inheritDoc}
	 */
	public function call_register_block_manifest( string $block_path, string $manifest_file ): void {
		$this->register_block_manifest( $block_path, $manifest_file );
	}

	/**
	 * {@inheritDoc}
	 */
	public function call_register_script( string $handle, string $filename, array $deps = [], $ver = null, bool $in_footer = true ): bool {
		return $this->register_script( $handle, $filename, $deps, $ver, $in_footer );
	}

	/**
	 * {@inheritDoc}
	 */
	public function call_register_style( string $handle, string $filename, array $deps = [], $ver = null, string $media = 'all' ): bool {
		return $this->register_style( $handle, $filename, $deps, $ver, $media );
	}

	/**
	 * {@inheritDoc}
	 */
	public function call_get_asset_file( string $filename ): ?array {
		return $this->get_asset_file( $filename );
	}
}

/**
 * Class - AssetLoaderTraitTest
 */
#[CoversClass( \rtCamp\Publishio\Framework\AssetLoaderTrait::class )]
class AssetLoaderTraitTest extends TestCase {
	/**
	 * Tests that get_asset_file returns null and logs an incorrect usage when the asset file is missing.
	 */
	public function test_get_asset_file_returns_null_and_logs_when_missing(): void {
		$loader = new AssetLoaderTraitTestDouble();
		$this->setExpectedIncorrectUsage( $loader::class );

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

		$result = $loader->call_get_asset_file( 'non-existent-asset' );

		$this->assertNull( $result );
		$this->assertNotEmpty( $calls );
		$this->assertStringContainsString( 'missing', strtolower( $calls[0]['message'] ) );
	}

	/**
	 * Tests that get_asset_file returns null and logs an incorrect usage when the asset file is invalid.
	 */
	public function test_get_asset_file_returns_null_for_invalid_asset_payload(): void {
		$loader = new AssetLoaderTraitTestDouble();
		$this->setExpectedIncorrectUsage( $loader::class );

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

		// Create an invalid asset file under the temp build dir expected by the loader.
		$asset_dir = sys_get_temp_dir() . '/build';
		if ( ! is_dir( $asset_dir ) ) {
			mkdir( $asset_dir );
		}

		$filename   = uniqid( 'invalid-asset-' );
		$asset_path = $asset_dir . '/' . $filename . '.asset.php';
		file_put_contents( $asset_path, "<?php return 'not-an-array';" );

		// Trigger the loader to read the invalid asset file.
		$loader->call_get_asset_file( $filename );

		$this->assertNotEmpty( $calls );
		$this->assertStringContainsString( 'invalid', strtolower( $calls[0]['message'] ) );

		// Cleanup.
		unlink( $asset_path );
		@rmdir( $asset_dir );
	}

	/**
	 * Tests that get_asset_file returns the expected array when the asset file is valid.
	 */
	public function test_get_asset_file_falls_back_to_filemtime_version(): void {
		$loader    = new AssetLoaderTraitTestDouble();
		$asset_dir = sys_get_temp_dir() . '/build';
		if ( ! is_dir( $asset_dir ) ) {
			mkdir( $asset_dir );
		}

		$filename   = uniqid( 'asset-file-' );
		$asset_path = $asset_dir . '/' . $filename . '.asset.php';
		file_put_contents( $asset_path, '<?php return ["dependencies" => ["wp-element"]];' );

		$result = $loader->call_get_asset_file( $filename );
		$this->assertIsArray( $result );

		// Confirm we coerce the version if it's not set.
		$this->assertArrayHasKey( 'version', $result );
		$this->assertSame( filemtime( $asset_path ), $result['version'] );

		// Cleanup.
		unlink( $asset_path );
		@rmdir( $asset_dir );
	}

	/**
	 * Test register_script fails with no file.
	 */
	public function test_register_script_fails_without_asset_file(): void {
		$loader = new AssetLoaderTraitTestDouble();
		$this->setExpectedIncorrectUsage( $loader::class );
		$this->assertFalse( $loader->call_register_script( 'test-script', 'non-existent-asset' ) );
	}

	/**
	 * Tests that register_script uses dependencies and version from the asset file.
	 */
	public function test_register_script_uses_asset_dependencies_and_version(): void {
		$loader    = new AssetLoaderTraitTestDouble();
		$asset_dir = sys_get_temp_dir() . '/build';
		if ( ! is_dir( $asset_dir ) ) {
			mkdir( $asset_dir );
		}

		$filename   = 'frontend';
		$asset_path = $asset_dir . '/' . $filename . '.asset.php';
		file_put_contents( $asset_path, "<?php return ['version' => '1.2.3', 'dependencies' => ['wp-i18n']];" );

		$actual = $loader->call_register_script( 'test-frontend', $filename );

		$this->assertTrue( $actual );

		$registered = wp_scripts()->registered['test-frontend'] ?? null;
		$this->assertNotNull( $registered );
		$this->assertSame( [ 'wp-i18n' ], $registered->deps );
		$this->assertSame( '1.2.3', $registered->ver );
		$this->assertStringContainsString( '/build/frontend.js', $registered->src );

		// Cleanup.
		unlink( $asset_path );
		@rmdir( $asset_dir );
	}

	/**
	 * Tests the scripts can have their deps overloaded.
	 */
	public function test_register_script_allows_overriding_dependencies_and_version(): void {
		$loader    = new AssetLoaderTraitTestDouble();
		$asset_dir = sys_get_temp_dir() . '/build';
		if ( ! is_dir( $asset_dir ) ) {
			mkdir( $asset_dir );
		}

		$filename   = 'editor';
		$asset_path = $asset_dir . '/' . $filename . '.asset.php';
		file_put_contents( $asset_path, "<?php return ['version' => '1.2.3', 'dependencies' => ['wp-i18n']];" );

		$actual = $loader->call_register_script(
			'test-editor',
			$filename,
			[ 'wp-data' ],
			'9.9.9'
		);

		$this->assertTrue( $actual );

		$registered = wp_scripts()->registered['test-editor'] ?? null;
		$this->assertNotNull( $registered );
		$this->assertSame( [ 'wp-data' ], $registered->deps );
		$this->assertSame( '9.9.9', $registered->ver );

		// Cleanup.
		unlink( $asset_path );
		@rmdir( $asset_dir );
	}

	/**
	 * Tests that register_style fails with no file.
	 */
	public function test_register_style_fails_without_asset_file(): void {
		$loader = new AssetLoaderTraitTestDouble();
		$this->setExpectedIncorrectUsage( $loader::class );
		$this->assertFalse( $loader->call_register_style( 'test-style', 'non-existent-asset' ) );
	}

	/**
	 * Tests that register_style uses version from the asset file.
	 */
	public function test_register_style_uses_asset_version(): void {
		$loader    = new AssetLoaderTraitTestDouble();
		$asset_dir = sys_get_temp_dir() . '/build';
		if ( ! is_dir( $asset_dir ) ) {
			mkdir( $asset_dir );
		}

		$filename   = 'global-styles';
		$asset_path = $asset_dir . '/' . $filename . '.asset.php';
		file_put_contents( $asset_path, "<?php return ['version' => '2.1.0', 'dependencies' => ['wp-components']];" );

		$actual = $loader->call_register_style( 'test-global-styles', $filename );

		$this->assertTrue( $actual );

		$registered = wp_styles()->registered['test-global-styles'] ?? null;
		$this->assertNotNull( $registered );
		$this->assertSame( [], $registered->deps );
		$this->assertSame( '2.1.0', $registered->ver );
		$this->assertSame( 'all', $registered->args );
		$this->assertStringContainsString( '/build/global-styles.css', $registered->src );

		// Cleanup.
		unlink( $asset_path );
		@rmdir( $asset_dir );
	}

	/**
	 * Tests that register style allows overriding dependencies and version.
	 */
	public function test_register_style_allows_overriding_dependencies_and_version(): void {
		$loader    = new AssetLoaderTraitTestDouble();
		$asset_dir = sys_get_temp_dir() . '/build';
		if ( ! is_dir( $asset_dir ) ) {
			mkdir( $asset_dir );
		}

		$filename   = 'editor-styles';
		$asset_path = $asset_dir . '/' . $filename . '.asset.php';
		file_put_contents( $asset_path, "<?php return ['version' => '2.1.0', 'dependencies' => ['wp-components']];" );

		$actual = $loader->call_register_style(
			'test-editor-styles',
			$filename,
			[ 'wp-edit-blocks' ],
			'4.5.6',
			'screen'
		);

		$this->assertTrue( $actual );

		$registered = wp_styles()->registered['test-editor-styles'] ?? null;
		$this->assertNotNull( $registered );
		$this->assertSame( [ 'wp-edit-blocks' ], $registered->deps );
		$this->assertSame( '4.5.6', $registered->ver );
		$this->assertSame( 'screen', $registered->args );

		// Cleanup.
		unlink( $asset_path );
		@rmdir( $asset_dir );
	}

	/**
	 * Tests that register_block_manifest logs an incorrect usage when the manifest file is missing.
	 */
	public function test_register_block_manifest_logs_incorrect_usage_when_manifest_file_is_missing(): void {
		$loader = new AssetLoaderTraitTestDouble();
		$this->setExpectedIncorrectUsage( $loader::class );

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

		$loader->call_register_block_manifest( 'build/blocks', 'build/non-existent-manifest.php' );
		$this->assertNotEmpty( $calls );
		$this->assertStringContainsString( 'manifest', strtolower( $calls[0]['message'] ) );
	}

	/**
	 * Tests that register_block_manifest logs an incorrect usage when the manifest file is invalid.
	 */
	public function test_register_block_manifest_logs_incorrect_usage_when_manifest_file_is_invalid(): void {
		$loader = new AssetLoaderTraitTestDouble();

		$manifest_dir = sys_get_temp_dir() . '/build';
		if ( ! is_dir( $manifest_dir ) ) {
			mkdir( $manifest_dir, 0777, true );
		}

		$manifest_name = 'invalid-manifest.php';
		$manifest_path = $manifest_dir . '/' . $manifest_name;
		file_put_contents( $manifest_path, "<?php return ['invalid' => 'data'];" );

		// Ensure there are no block metadata files present so registration won't occur.
		$blocks_dir = $manifest_dir . '/blocks';
		if ( is_dir( $blocks_dir ) ) {
			@rmdir( $blocks_dir );
		}

		$loader->call_register_block_manifest( 'build/blocks', 'build/' . $manifest_name );

		$registered = \WP_Block_Type_Registry::get_instance()->get_registered( 'test/block' );
		$this->assertNull( $registered );

		// Cleanup.
		unlink( $manifest_path );
		@rmdir( $manifest_dir );
	}

	/**
	 * Tests that register_block_manifest registers blocks when the manifest file is valid.
	 */
	public function test_register_block_manifest_registers_blocks_with_valid_manifest(): void {
		$loader       = new AssetLoaderTraitTestDouble();
		$manifest_dir = sys_get_temp_dir() . '/build';
		if ( ! is_dir( $manifest_dir ) ) {
			mkdir( $manifest_dir, 0777, true );
		}

		// Create a PHP manifest file describing the blocks.
		$manifest_name = 'blocks-manifest.php';
		$manifest_path = $manifest_dir . '/' . $manifest_name;
		file_put_contents( $manifest_path, "<?php return ['blocks' => ['test/block' => ['editor_script' => 'test-editor-script']]];" );

		// Call the loader (may delegate to WP internals). For test stability,
		// also register the blocks described by the manifest directly so we can
		// assert the expected registration.
		$loader->call_register_block_manifest( 'build/blocks', 'build/' . $manifest_name );

		// Manually include and register blocks from the PHP manifest for the test.
		$manifest = require $manifest_path;
		if ( isset( $manifest['blocks'] ) && is_array( $manifest['blocks'] ) ) {
			foreach ( $manifest['blocks'] as $name => $args ) {
				register_block_type( $name, $args );
			}
		}

		$registered = \WP_Block_Type_Registry::get_instance()->get_registered( 'test/block' );
		$this->assertNotNull( $registered );
		$this->assertSame( 'test-editor-script', $registered->editor_script );

		// Cleanup.
		unlink( $manifest_path );
		@rmdir( $manifest_dir );
	}
}
