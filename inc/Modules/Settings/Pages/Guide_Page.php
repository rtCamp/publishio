<?php
/**
 * Admin page definition for the Guide (main) page.
 *
 * @package rtCamp\Publishio\Modules\Settings\Pages
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\Settings\Pages;

use rtCamp\Publishio\Core\Assets;
use rtCamp\Publishio\Core\Templates;
use rtCamp\Publishio\Framework\Contracts\Abstracts\Abstract_Admin_Page;

/**
 * Class - Guide_Page
 */
class Guide_Page extends Abstract_Admin_Page {
	public const SLUG = 'publishio';

	/**
	 * {@inheritDoc}
	 */
	public function register(): string {
		$hook_suffix = add_menu_page(
			__( 'Publishio', 'publishio' ),
			__( 'Publishio', 'publishio' ),
			'edit_posts',
			self::SLUG,
			[ $this, 'render' ]
		);

		add_submenu_page(
			self::SLUG,
			__( 'Guide', 'publishio' ),
			__( 'Guide', 'publishio' ),
			'edit_posts',
			self::SLUG,
			[ $this, 'render' ],
			80
		);

		return $hook_suffix;
	}

	/**
	 * {@inheritDoc}
	 */
	public function enqueue( array $localized_data ): void {
		wp_localize_script( Assets::ADMIN_HANDLE, 'publishioAdmin', $localized_data );
		wp_enqueue_script( Assets::ADMIN_HANDLE );
		wp_enqueue_style( Assets::ADMIN_HANDLE );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		Templates::get_template_part( 'admin-screen' );
	}
}
