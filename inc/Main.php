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
		flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
	}

	/**
	 * Runs schema migrations when the stored plugin version is older than the current one.
	 */
	public static function maybe_upgrade(): void {
		$stored = (string) get_option( 'publishio_version', '0.0.0' );

		if ( version_compare( $stored, PUBLISHIO_VERSION, '<' ) ) {
			self::run_migrations();
		}
	}

	/**
	 * Create / update all DB tables and record the current version.
	 */
	private static function run_migrations(): void {
		Modules\MCP\OAuth\Storage\Token_Store::create_table();
		Modules\MCP\OAuth\Storage\Client_Store::create_table();
		update_option( 'publishio_version', PUBLISHIO_VERSION );
		flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
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
