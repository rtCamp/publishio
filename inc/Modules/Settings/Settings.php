<?php
/**
 * Registers the plugin's admin settings.
 *
 * @package rtCamp\Publish_With_AI\Modules\Settings
 */

declare(strict_types = 1);

namespace rtCamp\Publish_With_AI\Modules\Settings;

use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;

/**
 * Class - Settings
 */
final class Settings implements Registrable {
	/**
	 * The setting prefix.
	 */
	private const SETTING_PREFIX = 'rtcamp-publish-with-ai_';

	/**
	 * The setting group.
	 */
	public const SETTING_GROUP = self::SETTING_PREFIX . 'settings';

	/**
	 * Key for the delete settings on deactivate option.
	 */
	public const EXAMPLE_OPTION_KEY = self::SETTING_PREFIX . 'example_option';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'rest_api_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings(): void {
		// Add your settings here.
		$settings = [
			self::EXAMPLE_OPTION_KEY => [
				'type'              => 'boolean',
				'label'             => __( 'Example Option', 'rtcamp-publish-with-ai' ),
				'description'       => __( 'This is an example option.', 'rtcamp-publish-with-ai' ),
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => [
					'schema' => [
						'type' => 'boolean',
					],
				],
			],
		];

		foreach ( $settings as $key => $args ) {
			register_setting(
				self::SETTING_GROUP,
				$key,
				$args
			);
		}
	}

	/**
	 * Whether example option is enabled.
	 */
	public static function is_example_option_enabled(): bool {
		return (bool) get_option( self::EXAMPLE_OPTION_KEY, false );
	}
}
