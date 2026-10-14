<?php
/**
 * Bootstrap the PHPUnit tests.
 *
 * @package rtCamp\Publishio\Tests
 *
 * phpcs:disable WordPressVIPMinimum.Files.IncludingFile.UsingVariable
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
 */

declare( strict_types = 1 );

define( 'TESTS_REPO_ROOT_DIR', dirname( __DIR__, 2 ) );

// Load Composer dependencies if applicable.
if ( file_exists( TESTS_REPO_ROOT_DIR . '/vendor/autoload.php' ) ) {
	require_once TESTS_REPO_ROOT_DIR . '/vendor/autoload.php';
}

// Detect where to load the WordPress tests environment from.
if ( false !== getenv( 'WP_TESTS_DIR' ) ) {
	$_test_root = getenv( 'WP_TESTS_DIR' );
} elseif ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$_test_root = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} elseif ( false !== getenv( 'WP_PHPUNIT__DIR' ) ) {
	$_test_root = getenv( 'WP_PHPUNIT__DIR' );
} elseif ( file_exists( TESTS_REPO_ROOT_DIR . '/../../../../../tests/phpunit/includes/functions.php' ) ) {
	$_test_root = TESTS_REPO_ROOT_DIR . '/../../../../../tests/phpunit';
} else { // Fallback.
	$_test_root = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function.
require_once $_test_root . '/includes/functions.php';

// Activate the plugin, and Yoast SEO for the Yoast suite.
tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		if ( defined( 'PUBLISHIO_TESTS_WITH_YOAST' ) ) {
			require WP_PLUGIN_DIR . '/wordpress-seo/wp-seo.php';
		}

		// Require ( to bypass require_once ).
		require TESTS_REPO_ROOT_DIR . '/publishio.php';
	}
);

// Start up the WP testing environment.
require $_test_root . '/includes/bootstrap.php';

if ( defined( 'PUBLISHIO_TESTS_WITH_YOAST' ) ) {
	// Activation creates Yoast's options and gives roles its capabilities, such as wpseo_edit_advanced_metadata for editors.
	wpseo_activate();
}
