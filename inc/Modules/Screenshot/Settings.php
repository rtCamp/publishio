<?php
/**
 * Screenshot settings — option registration and static accessors.
 *
 * @package rtCamp\Publish_With_AI\Modules\Screenshot
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Screenshot;

use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;

/**
 * Class - Settings
 */
final class Settings implements Registrable {
	/**
	 * Option keys.
	 */
	public const OPTION_ENABLED  = 'rtpwai_screenshot_enabled';
	public const OPTION_PROVIDER = 'rtpwai_screenshot_provider';
	public const OPTION_API_KEY  = 'rtpwai_screenshot_api_key';

	/**
	 * Built-in providers.
	 *
	 * @var array<int, array{id: string, label: string, requires_key: bool, key_label: string}>
	 */
	private const BUILT_IN_PROVIDERS = [
		[
			'id'           => 'microlink',
			'label'        => 'Microlink',
			'requires_key' => false,
			'key_label'    => 'API Key (optional — unlocks higher limits)',
		],
		[
			'id'           => 'screenshotone',
			'label'        => 'ScreenshotOne',
			'requires_key' => true,
			'key_label'    => 'Access Key',
		],
	];

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'rest_api_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register plugin options with WordPress.
	 */
	public function register_settings(): void {
		register_setting(
			'rtpwai_screenshot_group',
			self::OPTION_ENABLED,
			[
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => false,
			]
		);

		register_setting(
			'rtpwai_screenshot_group',
			self::OPTION_PROVIDER,
			[
				'type'              => 'string',
				'default'           => 'microlink',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => false,
			]
		);

		register_setting(
			'rtpwai_screenshot_group',
			self::OPTION_API_KEY,
			[
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => false,
			]
		);
	}

	/**
	 * Whether the screenshot feature is enabled.
	 */
	public static function is_enabled(): bool {
		return (bool) get_option( self::OPTION_ENABLED, false );
	}

	/**
	 * Active provider ID.
	 */
	public static function get_provider(): string {
		return (string) get_option( self::OPTION_PROVIDER, 'microlink' );
	}

	/**
	 * Stored API key (plaintext).
	 */
	public static function get_api_key(): string {
		return (string) get_option( self::OPTION_API_KEY, '' );
	}

	/**
	 * Whether the feature is enabled and all required settings are present.
	 */
	public static function is_configured(): bool {
		if ( ! self::is_enabled() ) {
			return false;
		}

		$provider = self::find_provider( self::get_provider() );

		if ( null === $provider ) {
			return false;
		}

		return ! $provider['requires_key'] || '' !== self::get_api_key();
	}

	/**
	 * All registered providers (built-in + filter).
	 *
	 * @return array<int, array{id: string, label: string, requires_key: bool, key_label: string}>
	 */
	public static function get_providers(): array {
		/**
		 * Filters the list of available screenshot providers.
		 *
		 * Each provider must be an associative array with keys:
		 *   id (string), label (string), requires_key (bool), key_label (string).
		 *
		 * @param array<int, array{id: string, label: string, requires_key: bool, key_label: string}> $providers Built-in providers.
		 */
		return (array) apply_filters( 'publish_with_ai_screenshot_providers', self::BUILT_IN_PROVIDERS );
	}

	/**
	 * Find a provider by ID, or null if not found.
	 *
	 * @param string $id Provider ID.
	 *
	 * @return array{id: string, label: string, requires_key: bool, key_label: string}|null
	 */
	public static function find_provider( string $id ): ?array {
		foreach ( self::get_providers() as $provider ) {
			if ( $provider['id'] === $id ) {
				return $provider;
			}
		}

		return null;
	}
}
