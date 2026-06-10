<?php
/**
 * Admin page definition for the Connections sub-page.
 *
 * @package rtCamp\Publishio\Modules\Settings\Pages
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\Settings\Pages;

use rtCamp\Publishio\Core\Assets;
use rtCamp\Publishio\Core\Templates;
use rtCamp\Publishio\Framework\Contracts\Abstracts\Abstract_Admin_Page;

/**
 * Class - Connections_Page
 */
class Connections_Page extends Abstract_Admin_Page {
	public const SLUG = 'publishio-connections';

	/**
	 * {@inheritDoc}
	 */
	public function register(): string|false {
		return add_submenu_page(
			Guide_Page::SLUG,
			__( 'Connections', 'publishio' ),
			__( 'Connections', 'publishio' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function enqueue( array $localized_data ): void {
		wp_localize_script( Assets::ADMIN_CONNECTIONS_HANDLE, 'rtPublishioAdmin', $localized_data );
		wp_enqueue_script( Assets::ADMIN_CONNECTIONS_HANDLE );
		wp_enqueue_style( Assets::ADMIN_CONNECTIONS_HANDLE );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		Templates::get_template_part( 'connections-screen' );
	}
}
