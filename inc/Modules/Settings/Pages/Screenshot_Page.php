<?php
/**
 * Admin page definition for the Screenshot settings sub-page.
 *
 * @package rtCamp\Publish_With_AI\Modules\Settings\Pages
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Settings\Pages;

use rtCamp\Publish_With_AI\Core\Assets;
use rtCamp\Publish_With_AI\Core\Templates;
use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_Admin_Page;

/**
 * Class - Screenshot_Page
 */
class Screenshot_Page extends Abstract_Admin_Page {
	public const SLUG = 'rtcamp-publish-with-ai-screenshot';

	/**
	 * {@inheritDoc}
	 */
	public function register(): string|false {
		return add_submenu_page(
			Guide_Page::SLUG,
			__( 'Screenshots', 'rtcamp-publish-with-ai' ),
			__( 'Screenshots', 'rtcamp-publish-with-ai' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function enqueue( array $localized_data ): void {
		wp_localize_script( Assets::ADMIN_SCREENSHOT_HANDLE, 'rtPublishWithAIAdmin', $localized_data );
		wp_enqueue_script( Assets::ADMIN_SCREENSHOT_HANDLE );
		wp_enqueue_style( Assets::ADMIN_SCREENSHOT_HANDLE );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		Templates::get_template_part( 'screenshot-screen' );
	}
}
