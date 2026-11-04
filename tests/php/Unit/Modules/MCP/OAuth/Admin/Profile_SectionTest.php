<?php
/**
 * Tests for the connections section on the profile screen.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Admin
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use rtCamp\Publishio\Modules\MCP\OAuth\Admin\Profile_Section;
use rtCamp\Publishio\Modules\MCP\OAuth\Storage\Token_Store;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Profile_SectionTest
 */
#[CoversClass( Profile_Section::class )]
class Profile_SectionTest extends TestCase {
	/**
	 * A user with one connection.
	 */
	private \WP_User $user;

	/**
	 * Create the user and their connection.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->user = self::factory()->user->create_and_get( [ 'role' => 'editor' ] );
		Token_Store::issue( $this->user->ID, 'https://app.example.com/client.json', 'Example App', 'mcp:read', 'https://example.org/wp-json/mcp/publishio' );
	}

	/**
	 * Restore the screen.
	 */
	public function tear_down(): void {
		set_current_screen( 'front' );

		parent::tear_down();
	}

	/**
	 * A single site keeps the plain heading and button.
	 */
	public function test_single_site_lists_connections(): void {
		$this->skipWithMultisite();

		$html = $this->render();

		$this->assertStringContainsString( '<h2>Publishio MCP Connections</h2>', $html );
		$this->assertStringContainsString( 'Remove All Connections', $html );
		$this->assertStringContainsString( '<td>Active</td>', $html );
	}

	/**
	 * On multisite the heading names the site, and removing all connections says it covers this site only.
	 */
	public function test_multisite_names_the_site(): void {
		$this->skipWithoutMultisite();

		$html = $this->render();

		$this->assertStringContainsString( '<h2>Publishio MCP Connections on ' . esc_html( get_bloginfo( 'name' ) ) . '</h2>', $html );
		$this->assertStringContainsString( 'Remove All Connections on This Site', $html );
	}

	/**
	 * A connection approved before the user's last revocation shows as ended, as it stays behind on other sites of a network.
	 */
	public function test_revoked_connection_shows_as_ended(): void {
		update_user_meta( $this->user->ID, 'publishio_tokens_revoked_at', time() );

		$this->assertStringContainsString( '<td>Ended</td>', $this->render() );
	}

	/**
	 * Network admin and the user dashboard belong to no site, so they show a note instead of a list.
	 *
	 * @param string $screen Admin screen ID.
	 */
	#[DataProvider( 'screens_without_a_site' )]
	public function test_screens_without_a_site_show_a_note( string $screen ): void {
		$this->skipWithoutMultisite();

		set_current_screen( $screen );
		$html = $this->render();

		$this->assertStringContainsString( 'Connections are kept per site.', $html );
		$this->assertStringNotContainsString( 'Example App', $html );
	}

	/**
	 * Profile screens in network admin and the user dashboard.
	 *
	 * @return array<string, array{string}>
	 */
	public static function screens_without_a_site(): array {
		return [
			'network admin'  => [ 'profile-network' ],
			'user dashboard' => [ 'profile-user' ],
		];
	}

	/**
	 * Render the section for the user.
	 */
	private function render(): string {
		ob_start();
		( new Profile_Section() )->render_section( $this->user );

		return (string) ob_get_clean();
	}
}
