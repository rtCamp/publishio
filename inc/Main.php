<?php
/**
 * The main plugin file.
 *
 * @package rtCamp\Publishio
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio;

use rtCamp\Publishio\Framework\Contracts\Traits\Singleton;

/**
 * Class - Main
 */
final class Main {
	use Singleton;

	/**
	 * Registrable classes are entrypoints that "hook" into WordPress.
	 * They should implement the Registrable interface.
	 *
	 * @var class-string<\rtCamp\Publishio\Framework\Contracts\Interfaces\Registrable>[]
	 */
	private const REGISTRABLE_CLASSES = [
		Core\Assets::class,
		Modules\Settings\Menu_Loader::class,
		Modules\Settings\Connections\REST_Controller::class,
		Modules\MCP\OAuth\OAuth::class,
		Modules\MCP\Abilities\Abilities::class,
		Modules\MCP\Server\Server::class,
	];

	/**
	 * Database schema version. Bump it when adding a step to upgrade_schema(), so the step runs on deploy without waiting for a plugin release.
	 */
	private const DB_VERSION = 2;

	/**
	 * {@inheritDoc}
	 */
	public static function get_instance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Setup the plugin.
	 */
	private function setup(): void {
		// Load the plugin classes.
		$this->load();

		// Register activation and deactivation hooks.
		register_activation_hook( PUBLISHIO_FILE, [ self::class, 'activate' ] );
		register_deactivation_hook( PUBLISHIO_FILE, [ self::class, 'deactivate' ] );

		// Run schema migrations when the plugin version changes.
		add_action( 'plugins_loaded', [ self::class, 'maybe_upgrade' ] );

		// Do other stuff here like dep-checking, telemetry, etc.
	}

	/**
	 * Load the plugin classes.
	 */
	private function load(): void {
		foreach ( self::REGISTRABLE_CLASSES as $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						/* translators: %s: class name */
						esc_html__( 'Publishio: Class %s not found. Skipping registration.', 'publishio' ),
						$class_name //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					),
					PUBLISHIO_VERSION // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
				continue;
			}

			$instance = new $class_name();
			$instance->register_hooks();
		}

		// Do other generalizable stuff here.
	}

	/**
	 * Runs on successful plugin activation.
	 *
	 * @internal description
	 */
	public static function activate(): void {
		self::run_migrations();
	}

	/**
	 * Runs migrations when the stored plugin or database schema version is older than the current one.
	 */
	public static function maybe_upgrade(): void {
		$stored_version    = (string) get_option( 'publishio_version', '0.0.0' );
		$stored_db_version = (int) get_option( 'publishio_db_version', 1 );

		if ( version_compare( $stored_version, PUBLISHIO_VERSION, '<' ) || $stored_db_version < self::DB_VERSION ) {
			self::run_migrations();
		}
	}

	/**
	 * Create / update all DB tables, apply schema upgrades and record the current versions.
	 */
	private static function run_migrations(): void {
		Modules\MCP\OAuth\Storage\Token_Store::create_table();
		self::upgrade_schema( (int) get_option( 'publishio_db_version', 1 ) );

		update_option( 'publishio_version', PUBLISHIO_VERSION );
		update_option( 'publishio_db_version', self::DB_VERSION );

		self::maybe_flush_rewrite_rules();
	}

	/**
	 * Apply schema changes made since the given database version.
	 *
	 * @param int $from Stored database schema version.
	 */
	private static function upgrade_schema( int $from ): void {
		if ( $from < 2 ) {
			// Dynamic client registration was replaced by Client ID Metadata Documents.
			global $wpdb;

			Modules\MCP\OAuth\Storage\Token_Store::delete_dcr_tokens();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'publishio_oauth_clients' ) );
		}
	}

	/**
	 * Register and flush rewrite rules, deferring to `init` if it hasn't fired
	 * yet (e.g. on `plugins_loaded`), since `$wp_rewrite` isn't ready until then.
	 */
	public static function maybe_flush_rewrite_rules(): void {
		if ( ! did_action( 'init' ) ) {
			add_action( 'init', [ self::class, 'maybe_flush_rewrite_rules' ], 20 );
			return;
		}

		self::register_rewrite_rules();
		flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
	}

	/**
	 * Register custom rewrite rules so a flush before `init` still persists them.
	 */
	private static function register_rewrite_rules(): void {
		( new Modules\MCP\OAuth\Well_Known\Auth_Server_Metadata() )->add_rewrite_rules();
		( new Modules\MCP\OAuth\Well_Known\Protected_Resource() )->add_rewrite_rules();
		( new Modules\MCP\OAuth\Endpoint\Authorize() )->add_rewrite_rules();
	}

	/**
	 * Runs on successful plugin deactivation.
	 *
	 * @internal description
	 */
	public static function deactivate(): void {
		flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
	}
}
