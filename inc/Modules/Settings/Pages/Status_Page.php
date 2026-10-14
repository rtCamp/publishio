<?php
/**
 * Admin page definition for the Status sub-page.
 *
 * @package rtCamp\Publishio\Modules\Settings\Pages
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\Settings\Pages;

use rtCamp\Publishio\Core\Assets;
use rtCamp\Publishio\Core\Templates;
use rtCamp\Publishio\Framework\Contracts\Abstracts\Abstract_Admin_Page;
use rtCamp\Publishio\Modules\MCP\OAuth\Endpoint\Authorize;

/**
 * Class - Status_Page
 */
class Status_Page extends Abstract_Admin_Page {
	public const SLUG = 'publishio-status';

	/**
	 * {@inheritDoc}
	 */
	public function register(): string|false {
		return add_submenu_page(
			Guide_Page::SLUG,
			__( 'Status', 'publishio' ),
			__( 'Status', 'publishio' ),
			Authorize::CAPABILITY, // phpcs:ignore WordPress.WP.Capabilities.Undetermined
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function enqueue( array $localized_data ): void {
		wp_localize_script( Assets::ADMIN_STATUS_HANDLE, 'publishioAdmin', $localized_data );
		wp_enqueue_script( Assets::ADMIN_STATUS_HANDLE );
		wp_enqueue_style( Assets::ADMIN_STATUS_HANDLE );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		Templates::get_template_part( 'status-screen' );
	}
}
