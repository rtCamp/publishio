<?php
/**
 * Admin_ScreenTest file.
 *
 * @package rtCamp\Publish_With_AI\Tests\Unit\Modules\Admin
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Tests\Unit\Modules\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publish_With_AI\Core\Assets;
use rtCamp\Publish_With_AI\Modules\Settings\Admin_Screen;
use rtCamp\Publish_With_AI\Tests\TestCase;

/**
 * Class - Admin_ScreenTest
 */
#[CoversClass( Admin_Screen::class )]
class Admin_ScreenTest extends TestCase {
	/**
	 * Test that register_hooks adds appropriate actions and filters.
	 */
	public function test_register_hooks_registers_admin_menu_and_plugin_action_links_hooks(): void {
		$screen = new Admin_Screen();

		$screen->register_hooks();

		$this->assertNotFalse( \has_action( 'admin_menu', [ $screen, 'register_screen' ] ) );
		$this->assertNotFalse( \has_filter( 'plugin_action_links_' . \plugin_basename( RTCAMP_PUBLISH_WITH_AI_FILE ), [ $screen, 'add_action_links' ] ) );
	}

	/**
	 * Test that add_action_links appends the settings link.
	 */
	public function test_add_action_links_appends_settings_link(): void {
		$screen = new Admin_Screen();

		$actual = $screen->add_action_links( [ '<a href="plugins.php">Plugins</a>' ] );

		$this->assertCount( 2, $actual );
		$this->assertStringContainsString( 'admin.php?page=rtcamp-publish-with-ai', $actual[1] );
		$this->assertStringContainsString( 'Settings', $actual[1] );
	}

	/**
	 * Test that register_screen adds the settings page and its load hook.
	 */
	public function test_register_screen_adds_settings_page_and_load_hook(): void {
		$screen = new Admin_Screen();

		// Mock current user as administrator so the screen is registered.
		$user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user );

		$screen->register_screen();
		$hook_suffix = get_plugin_page_hookname( Admin_Screen::SCREEN_ID, 'options-general.php' );

		// Register the admin scripts so they exist when enqueue_scripts is called.
		$assets = new Assets();
		$assets->register_admin_assets();

		do_action( 'load-' . $hook_suffix ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		global $submenu;

		$this->assertIsArray( $submenu['options-general.php'] ?? null );
		$this->assertContains(
			[ 'Publish with AI', 'manage_options', Admin_Screen::SCREEN_ID, 'Publish with AI' ],
			$submenu['options-general.php'],
			'Settings page should be added to the admin menu'
		);
		$this->assertTrue( wp_script_is( Assets::ADMIN_HANDLE, 'enqueued' ), 'Admin script should be enqueued' );
	}

	/**
	 * Test that render_screen outputs the correct template.
	 */
	public function test_render_screen_outputs_admin_template_markup(): void {
		$screen = new Admin_Screen();

		ob_start();
		$screen->render_screen();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<div class="wrap">', $output );
		$this->assertStringContainsString( 'rtpwai-content', $output );
	}
}
