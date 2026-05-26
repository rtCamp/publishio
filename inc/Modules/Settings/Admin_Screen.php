<?php
/**
 * Registers the Settings Screen
 *
 * @package rtCamp\Publish_With_AI\Modules\Settings
 */

declare(strict_types = 1);

namespace rtCamp\Publish_With_AI\Modules\Settings;

use rtCamp\Publish_With_AI\Core\Assets;
use rtCamp\Publish_With_AI\Core\Templates;
use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;

/**
 * Class - Admin_Screen
 */
final class Admin_Screen implements Registrable {
	/**
	 * The screen ID for the main (guide) page.
	 */
	public const SCREEN_ID = 'rtcamp-publish-with-ai';

	/**
	 * The screen ID for the connections sub-page.
	 */
	public const CONNECTIONS_SCREEN_ID = 'rtcamp-publish-with-ai-connections';

	/**
	 * Hook suffix returned by add_menu_page() for the guide page.
	 */
	private string $guide_hook = '';

	/**
	 * Hook suffix returned by add_submenu_page() for the connections page.
	 */
	private string $connections_hook = '';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'register_screen' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_menu_icon' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_page_assets' ], 20 );
		add_filter( 'admin_body_class', [ $this, 'add_admin_body_class' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( RTCAMP_PUBLISH_WITH_AI_FILE ), [ $this, 'add_action_links' ], 2 );
	}

	/**
	 * Add action links to the settings on the plugins page.
	 *
	 * @param string[] $links Existing links.
	 *
	 * @return string[]
	 */
	public function add_action_links( $links ): array {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( sprintf( 'admin.php?page=%s', self::SCREEN_ID ) ) ),
			__( 'Settings', 'rtcamp-publish-with-ai' )
		);

		return $links;
	}

	/**
	 * Hook the settings screen into the Admin menu.
	 */
	public function register_screen(): void {
		$hook_suffix = add_menu_page(
			__( 'Publish with AI', 'rtcamp-publish-with-ai' ),
			__( 'Publish with AI', 'rtcamp-publish-with-ai' ),
			'manage_options',
			self::SCREEN_ID,
			[ $this, 'render_screen' ]
		);

		if ( false !== $hook_suffix ) {
			$this->guide_hook = $hook_suffix;
		}

		// Override the auto-generated first submenu entry with a "Guide" label.
		add_submenu_page(
			self::SCREEN_ID,
			__( 'Guide', 'rtcamp-publish-with-ai' ),
			__( 'Guide', 'rtcamp-publish-with-ai' ),
			'manage_options',
			self::SCREEN_ID,
			[ $this, 'render_screen' ]
		);

		$connections_hook = add_submenu_page(
			self::SCREEN_ID,
			__( 'Connections', 'rtcamp-publish-with-ai' ),
			__( 'Connections', 'rtcamp-publish-with-ai' ),
			'edit_posts',
			self::CONNECTIONS_SCREEN_ID,
			[ $this, 'render_connections_screen' ]
		);

		if ( false !== $connections_hook ) {
			$this->connections_hook = $connections_hook;
		}
	}

	/**
	 * Enqueue page-specific scripts and styles based on the current admin page.
	 *
	 * Runs at priority 20 on admin_enqueue_scripts, after assets are registered at priority 10.
	 *
	 * @param string $hook_suffix The hook suffix of the current admin page.
	 */
	public function maybe_enqueue_page_assets( string $hook_suffix ): void {
		if ( $this->guide_hook && $hook_suffix === $this->guide_hook ) {
			$this->enqueue_scripts();
		} elseif ( $this->connections_hook && $hook_suffix === $this->connections_hook ) {
			$this->enqueue_connections_scripts();
		}
	}

	/**
	 * Enqueue scripts and styles for the guide screen.
	 */
	private function enqueue_scripts(): void {
		wp_localize_script( Assets::ADMIN_HANDLE, 'rtPublishWithAIAdmin', self::get_localized_data() );
		wp_enqueue_script( Assets::ADMIN_HANDLE );
		wp_enqueue_style( Assets::ADMIN_HANDLE );
	}

	/**
	 * Enqueue scripts and styles for the connections screen.
	 */
	private function enqueue_connections_scripts(): void {
		wp_localize_script( Assets::ADMIN_CONNECTIONS_HANDLE, 'rtPublishWithAIAdmin', self::get_localized_data() );
		wp_enqueue_script( Assets::ADMIN_CONNECTIONS_HANDLE );
		wp_enqueue_style( Assets::ADMIN_CONNECTIONS_HANDLE );
	}

	/**
	 * Render the guide (main) admin screen.
	 */
	public function render_screen(): void {
		Templates::get_template_part( 'admin-screen' );
	}

	/**
	 * Render the connections admin screen.
	 */
	public function render_connections_screen(): void {
		Templates::get_template_part( 'connections-screen' );
	}

	/**
	 * Enqueue the menu icon stylesheet on all admin pages and set the icon URL via CSS variable.
	 */
	public function enqueue_menu_icon(): void {
		wp_enqueue_style( Assets::ADMIN_MENU_ICON_HANDLE );
		wp_add_inline_style(
			Assets::ADMIN_MENU_ICON_HANDLE,
			sprintf(
				'#toplevel_page_%s { --rtpwai-menu-icon-url: url("%s"); }',
				esc_attr( self::SCREEN_ID ),
				esc_url( plugins_url( 'assets/images/logo.svg', RTCAMP_PUBLISH_WITH_AI_FILE ) )
			)
		);
	}

	/**
	 * Add a shared body class to all plugin admin pages for consistent full-screen styling.
	 *
	 * @param string $classes Space-separated list of body classes.
	 */
	public function add_admin_body_class( string $classes ): string {
		$screen = get_current_screen();

		if ( null === $screen ) {
			return $classes;
		}

		if ( str_contains( $screen->id, self::SCREEN_ID ) ) {
			$classes .= ' rtpwai-admin-page';
		}

		return $classes;
	}

	/**
	 * Localize plugin data for script access.
	 *
	 * Will be available via window.rtPublishWithAIAdmin.
	 *
	 * @return array<string, mixed>
	 */
	private function get_localized_data(): array {
		return [
			'pluginVersion' => RTCAMP_PUBLISH_WITH_AI_VERSION,
			'logoUrl'       => plugins_url( 'assets/images/logo.svg', RTCAMP_PUBLISH_WITH_AI_FILE ),
			'appLogos'      => [
				'claude' => plugins_url( 'assets/claude-logo.svg', RTCAMP_PUBLISH_WITH_AI_FILE ),
				'openai' => plugins_url( 'assets/openai-logo.svg', RTCAMP_PUBLISH_WITH_AI_FILE ),
				'other'  => plugins_url( 'assets/other-apps-logo.svg', RTCAMP_PUBLISH_WITH_AI_FILE ),
			],
		];
	}
}
