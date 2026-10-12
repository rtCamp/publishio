<?php
/**
 * Unit tests for Main.
 *
 * @package rtCamp\Publishio\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use rtCamp\Publishio\Main;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - MainTest
 */
#[CoversClass( \rtCamp\Publishio\Main::class )]
class MainTest extends TestCase {
	/**
	 * Reset the Main singleton instance.
	 */
	private function reset_main_instance(): void {
		$ref  = new \ReflectionClass( Main::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		remove_all_actions( 'init' );
		remove_all_actions( 'admin_enqueue_scripts' );
		remove_all_filters( 'script_loader_tag' );
		delete_option( 'publishio_version' );

		parent::tearDown();
	}

	/**
	 * Test that get_instance returns the same singleton instance.
	 */
	public function test_get_instance_returns_singleton(): void {
		$this->reset_main_instance();
		$a = Main::get_instance();
		$b = Main::get_instance();
		$this->assertSame( $a, $b );
	}

	/**
	 * Test that activate() sets the version option.
	 */
	public function test_activate_sets_version_option(): void {
		$this->reset_main_instance();

		Main::activate();

		$this->assertEquals( PUBLISHIO_VERSION, get_option( 'publishio_version' ) );
	}

	/**
	 * Test that deactivate() runs without errors.
	 */
	public function test_deactivate_runs_without_errors(): void {
		$this->reset_main_instance();

		Main::deactivate();

		// Deactivate is currently a no-op, just verify it doesn't throw.
		$this->assertTrue( true );
	}

	/**
	 * Test that setup() registers activation and deactivation hooks.
	 */
	public function test_setup_registers_activation_deactivation_hooks(): void {
		$this->reset_main_instance();

		Main::get_instance();

		$this->assertNotFalse( has_action( 'activate_' . plugin_basename( PUBLISHIO_FILE ) ) );
		$this->assertNotFalse( has_action( 'deactivate_' . plugin_basename( PUBLISHIO_FILE ) ) );
	}

	/**
	 * Upgrading from schema 1 removes the dynamic client registration data and leaves the schema current.
	 *
	 * The test framework turns CREATE and DROP TABLE into temporary-table statements, so they stay inside the test's transaction.
	 */
	public function test_upgrade_from_schema_1_removes_dcr_data(): void {
		global $wpdb;

		$tokens  = $wpdb->prefix . 'publishio_oauth_tokens';
		$clients = $wpdb->prefix . 'publishio_oauth_clients';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'CREATE TABLE %i ( client_id VARCHAR(255) NOT NULL )', $clients ) );
		foreach ( [ 'dcr_abc123', 'https://app.example.com/client.json' ] as $client_id ) {
			$wpdb->insert(
				$tokens,
				[
					'user_id'            => 1,
					'client_id'          => $client_id,
					'access_token_hash'  => wp_generate_password( 64, false ),
					'refresh_token_hash' => wp_generate_password( 64, false ),
					'access_expires_at'  => time() + 60,
					'refresh_expires_at' => time() + 60,
					'created_at'         => time(),
				]
			);
		}
		update_option( 'publishio_db_version', 1 );

		Main::maybe_upgrade();

		$this->assertSame( [ 'https://app.example.com/client.json' ], $wpdb->get_col( $wpdb->prepare( 'SELECT client_id FROM %i', $tokens ) ) );

		$wpdb->suppress_errors( true );
		$this->assertFalse( $wpdb->query( $wpdb->prepare( 'SELECT 1 FROM %i', $clients ) ), 'The clients table is dropped.' );
		$wpdb->suppress_errors( false );

		$this->assertSame( '2', (string) get_option( 'publishio_db_version' ) );
		$this->assertContains( 'client_name', $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $tokens ) ) );
		$this->assertContains( 'authorized_at', $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $tokens ) ) );
		$this->assertContains( 'created_at', $wpdb->get_col( $wpdb->prepare( 'SHOW INDEX FROM %i', $tokens ), 2 ) );
		$this->assertContains( 'refresh_expires_at', $wpdb->get_col( $wpdb->prepare( 'SHOW INDEX FROM %i', $tokens ), 2 ) );
		// phpcs:enable
	}
}
