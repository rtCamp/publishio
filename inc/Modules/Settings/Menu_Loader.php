<?php
/**
 * Registers all plugin admin pages and wires their shared WordPress hooks.
 *
 * Each page is declared as a concrete Abstract_Admin_Page subclass. This class
 * owns registration, asset enqueueing, menu icon, body class, and the plugin
 * action links — keeping those cross-cutting concerns out of individual pages.
 *
 * @package rtCamp\Publishio\Modules\Settings
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\Settings;

use rtCamp\Publishio\Core\Assets;
use rtCamp\Publishio\Framework\Contracts\Interfaces\Registrable;
use rtCamp\Publishio\Modules\MCP\OAuth\Config as MCP_Config;
use rtCamp\Publishio\Modules\Settings\Pages\Connections_Page;
use rtCamp\Publishio\Modules\Settings\Pages\Guide_Page;

/**
 * Class - Menu_Loader
 */
final class Menu_Loader implements Registrable {
	/**
	 * Ordered list of page classes to register.
	 * Top-level pages must appear before their children.
	 *
	 * @var class-string<\rtCamp\Publishio\Framework\Contracts\Abstracts\Abstract_Admin_Page>[]
	 */
	private const PAGE_CLASSES = [
		Guide_Page::class,
		Connections_Page::class,
	];

	/**
	 * Instantiated pages, keyed by hook suffix after admin_menu fires.
	 *
	 * @var array<string, \rtCamp\Publishio\Framework\Contracts\Abstracts\Abstract_Admin_Page>
	 */
	private array $pages_by_hook = [];

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'register_pages' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_menu_icon' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_page_assets' ], 20 );
		add_filter( 'admin_body_class', [ $this, 'add_admin_body_class' ] );
		add_filter(
			'plugin_action_links_' . plugin_basename( PUBLISHIO_FILE ),
			[ $this, 'add_action_links' ],
			2
		);
	}

	/**
	 * Instantiate and register every page. Called on admin_menu.
	 */
	public function register_pages(): void {
		foreach ( self::PAGE_CLASSES as $class ) {
			$page        = new $class();
			$hook_suffix = $page->register();

			if ( false !== $hook_suffix ) {
				$this->pages_by_hook[ $hook_suffix ] = $page;
			}
		}
	}

	/**
	 * Enqueue the menu icon stylesheet and inject the icon URL as a CSS variable.
	 */
	public function enqueue_menu_icon(): void {
		wp_enqueue_style( Assets::ADMIN_MENU_ICON_HANDLE );
		wp_add_inline_style(
			Assets::ADMIN_MENU_ICON_HANDLE,
			sprintf(
				'#toplevel_page_%s { --publishio-menu-icon-url: url("%s"); }',
				esc_attr( Guide_Page::SLUG ),
				esc_url( plugins_url( 'assets/images/logo.svg', PUBLISHIO_FILE ) )
			)
		);
	}

	/**
	 * Enqueue the page bundle for the currently active plugin page.
	 *
	 * Runs at priority 20, after assets are registered at priority 10.
	 *
	 * @param string $hook_suffix Hook suffix of the current admin page.
	 */
	public function maybe_enqueue_page_assets( string $hook_suffix ): void {
		$page = $this->pages_by_hook[ $hook_suffix ] ?? null;

		if ( null === $page ) {
			return;
		}

		$page->enqueue( $this->get_localized_data() );
	}

	/**
	 * Add a "Settings" link to the plugin row on the Plugins list table.
	 *
	 * @param string[] $links Existing action links.
	 *
	 * @return string[]
	 */
	public function add_action_links( array $links ): array {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . Guide_Page::SLUG ) ),
			__( 'Settings', 'publishio' )
		);

		return $links;
	}

	/**
	 * Append a shared body class to all plugin admin pages.
	 *
	 * @param string $classes Space-separated body class string.
	 */
	public function add_admin_body_class( string $classes ): string {
		$screen = get_current_screen();

		if ( null === $screen ) {
			return $classes;
		}

		if ( str_contains( $screen->id, Guide_Page::SLUG ) ) {
			$classes .= ' publishio-admin-page';
		}

		return $classes;
	}

	/**
	 * Build the data object passed to every page bundle via wp_localize_script.
	 *
	 * @return array<string, mixed>
	 */
	private function get_localized_data(): array {
		return [
			'pluginVersion' => PUBLISHIO_VERSION,
			'logoUrl'       => plugins_url( 'assets/images/logo.svg', PUBLISHIO_FILE ),
			'appLogos'      => [
				'claude' => plugins_url( 'assets/images/provider/claude-logo.svg', PUBLISHIO_FILE ),
				'openai' => plugins_url( 'assets/images/provider/openai-logo.svg', PUBLISHIO_FILE ),
				'other'  => plugins_url( 'assets/images/provider/other-apps-logo.svg', PUBLISHIO_FILE ),
			],
			'mcpServerUrl'  => MCP_Config::get_mcp_resource_url(),
			'guideImages'   => [
				'claude' => [
					'connectorMenu' => plugins_url( 'assets/images/guide/claude/step-connector-menu.png', PUBLISHIO_FILE ),
					'connectorForm' => plugins_url( 'assets/images/guide/claude/step-connector-form.png', PUBLISHIO_FILE ),
					'clickConnect'  => plugins_url( 'assets/images/guide/claude/step-click-connect.png', PUBLISHIO_FILE ),
					'consent'       => plugins_url( 'assets/images/guide/claude/step-consent.png', PUBLISHIO_FILE ),
				],
			],
		];
	}
}
