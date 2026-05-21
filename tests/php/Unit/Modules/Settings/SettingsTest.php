<?php
/**
 * SettingsTest file.
 *
 * @package rtCamp\Publish_With_AI\Tests\Unit\Modules\Settings
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Tests\Unit\Modules\Settings;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publish_With_AI\Modules\Settings\Settings;
use rtCamp\Publish_With_AI\Tests\TestCase;

/**
 * Class - SettingsTest
 */
#[CoversClass( Settings::class )]
class SettingsTest extends TestCase {
	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		delete_option( Settings::EXAMPLE_OPTION_KEY );

		parent::tearDown();
	}

	/**
	 * Test that register_hooks adds callbacks to admin_init and rest_api_init.
	 */
	public function test_register_hooks_adds_settings_callbacks_to_admin_init_and_rest_api_init(): void {
		$settings = new Settings();

		$settings->register_hooks();

		$this->assertNotFalse( has_action( 'admin_init', [ $settings, 'register_settings' ] ) );
		$this->assertNotFalse( has_action( 'rest_api_init', [ $settings, 'register_settings' ] ) );
	}

	/**
	 * Test that register_settings registers the example option with WordPress.
	 */
	public function test_register_settings_registers_example_setting_with_expected_contract(): void {
		$settings = new Settings();
		$settings->register_settings();

		$registered = get_registered_settings();
		$key        = Settings::EXAMPLE_OPTION_KEY;

		$this->assertArrayHasKey( $key, $registered );
		$this->assertSame( Settings::SETTING_GROUP, $registered[ $key ]['group'] );
		$this->assertSame( 'boolean', $registered[ $key ]['type'] );
		$this->assertSame( 'rest_sanitize_boolean', $registered[ $key ]['sanitize_callback'] );
		$this->assertArrayHasKey( 'show_in_rest', $registered[ $key ] );
		$this->assertSame( [ 'type' => 'boolean' ], $registered[ $key ]['show_in_rest']['schema'] );
	}

	/**
	 * Test that is_example_option_enabled returns false when option is not set.
	 */
	public function test_is_example_option_enabled_returns_false_when_option_is_missing(): void {
		delete_option( Settings::EXAMPLE_OPTION_KEY );

		$this->assertFalse( Settings::is_example_option_enabled() );
	}

	/**
	 * Test that is_example_option_enabled returns true when option is enabled.
	 */
	public function test_is_example_option_enabled_returns_true_when_option_is_enabled(): void {
		update_option( Settings::EXAMPLE_OPTION_KEY, true );

		$this->assertTrue( Settings::is_example_option_enabled() );
	}

	/**
	 * Test that is_example_option_enabled returns false when option is disabled.
	 */
	public function test_is_example_option_enabled_returns_false_when_option_is_disabled(): void {
		update_option( Settings::EXAMPLE_OPTION_KEY, false );

		$this->assertFalse( Settings::is_example_option_enabled() );
	}
}
