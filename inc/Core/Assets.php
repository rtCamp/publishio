<?php
/**
 * Enqueue plugin assets, like styles, scripts, and blocks.
 *
 * @package rtCamp\Publish_With_AI\Core
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Core;

use rtCamp\Publish_With_AI\Framework\AssetLoaderTrait;
use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;

/**
 * Class Assets
 */
final class Assets implements Registrable {
	use AssetLoaderTrait;

	/**
	 * Prefix for all asset handles.
	 */
	private const PREFIX = 'rtpwai-';

	/**
	 * Asset handles
	 */
	public const ADMIN_HANDLE             = self::PREFIX . 'admin';
	public const ADMIN_CONNECTIONS_HANDLE = self::PREFIX . 'admin-connections';
	public const ADMIN_CREDENTIALS_HANDLE = self::PREFIX . 'admin-credentials';
	public const ADMIN_MENU_ICON_HANDLE   = self::PREFIX . 'admin-menu-icon';

	/**
	 * Assets to defer for better performance.
	 */
	private const DEFERRED_ASSETS = [
		self::ADMIN_HANDLE,
		self::ADMIN_CONNECTIONS_HANDLE,
		self::ADMIN_CREDENTIALS_HANDLE,
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->plugin_dir = (string) RTCAMP_PUBLISH_WITH_AI_PATH;
		$this->plugin_url = (string) RTCAMP_PUBLISH_WITH_AI_URL;
		$this->assets_dir = 'build';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'register_admin_assets' ] );

		// Add defer attribute to certain plugin bundles to improve admin load performance.
		add_filter( 'script_loader_tag', [ $this, 'defer_scripts' ], 10, 2 );
	}

	/**
	 * Register assets for admin.
	 *
	 * Assets are registered once centrally, and enqueued in the modules that need them.
	 */
	public function register_admin_assets(): void {
		// Guide page (main menu).
		$this->register_script( self::ADMIN_HANDLE, 'admin' );
		$this->register_style( self::ADMIN_HANDLE, 'admin', [ 'wp-components' ] );

		// Connections page (sub-menu).
		$this->register_script( self::ADMIN_CONNECTIONS_HANDLE, 'admin-connections' );
		$this->register_style( self::ADMIN_CONNECTIONS_HANDLE, 'admin-connections', [ 'wp-components' ] );

		// Credentials page (sub-menu).
		$this->register_script( self::ADMIN_CREDENTIALS_HANDLE, 'admin-credentials' );
		$this->register_style( self::ADMIN_CREDENTIALS_HANDLE, 'admin-credentials', [ 'wp-components' ] );

		// Menu icon style (CSS-only entry, loaded on all admin pages).
		$this->register_style( self::ADMIN_MENU_ICON_HANDLE, 'admin-menu-icon' );
	}

	/**
	 * Add defer attribute to certain plugin bundle scripts to improve loading performance.
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The script handle.
	 *
	 * @return string Modified script tag.
	 */
	public function defer_scripts( string $tag, string $handle ): string {
		// Bail if we don't need to defer.
		if ( ! in_array( $handle, self::DEFERRED_ASSETS, true ) || false !== strpos( $tag, ' defer' ) ) {
			return $tag;
		}

		return str_replace( ' src', ' defer src', $tag );
	}
}
