<?php
/**
 * The main plugin file.
 *
 * @package rtCamp\Publish_With_AI
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI;

use rtCamp\Publish_With_AI\Framework\Contracts\Traits\Singleton;

/**
 * Class - Main
 */
final class Main {
	use Singleton;

	/**
	 * Registrable classes are entrypoints that "hook" into WordPress.
	 * They should implement the Registrable interface.
	 *
	 * @var class-string<\rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable>[]
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
		register_activation_hook( RTCAMP_PUBLISH_WITH_AI_FILE, [ self::class, 'activate' ] );
		register_deactivation_hook( RTCAMP_PUBLISH_WITH_AI_FILE, [ self::class, 'deactivate' ] );

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
						esc_html__( 'Publish With AI: Class %s not found. Skipping registration.', 'rtcamp-publish-with-ai' ),
						$class_name //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					),
					RTCAMP_PUBLISH_WITH_AI_VERSION // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
		$stored = (string) get_option( 'publish_with_ai_version', '0.0.0' );

		if ( version_compare( $stored, RTCAMP_PUBLISH_WITH_AI_VERSION, '<' ) ) {
			self::run_migrations();
		}
	}

	/**
	 * Create / update all DB tables and record the current version.
	 */
	private static function run_migrations(): void {
		Modules\MCP\OAuth\Storage\Token_Store::create_table();
		Modules\MCP\OAuth\Storage\Client_Store::create_table();
		update_option( 'publish_with_ai_version', RTCAMP_PUBLISH_WITH_AI_VERSION );
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
