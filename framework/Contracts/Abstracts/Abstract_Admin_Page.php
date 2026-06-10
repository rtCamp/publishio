<?php
/**
 * Abstract base class for plugin admin pages.
 *
 * Mirrors the Abstract_REST_Controller pattern: the abstract class owns nothing
 * except the three method contracts. Each concrete page implements them in full —
 * no individual property getters, no shared template logic.
 *
 * Menu_Loader calls these methods at the right WordPress hooks.
 *
 * @package rtCamp\Publishio\Framework\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Framework\Contracts\Abstracts;

/**
 * Class - Abstract_Admin_Page
 */
abstract class Abstract_Admin_Page {
	/**
	 * Register this page with WordPress via add_menu_page() or add_submenu_page().
	 *
	 * Must pass [$this, 'render'] as the callback so Menu_Loader can resolve
	 * the hook suffix back to the correct page instance for asset enqueueing.
	 *
	 * @return string|false Hook suffix on success, false if the current user
	 *                      lacks the required capability.
	 */
	abstract public function register(): string|false;

	/**
	 * Localize and enqueue this page's script and style bundles.
	 *
	 * @param array<string, mixed> $localized_data Shared plugin data to expose
	 *                                              via wp_localize_script.
	 */
	abstract public function enqueue( array $localized_data ): void;

	/**
	 * Render the page HTML (usually a single Templates::get_template_part call).
	 */
	abstract public function render(): void;
}
