<?php
/**
 * Admin page definition for the Credentials sub-page.
 *
 * @package rtCamp\Publish_With_AI\Modules\Settings\Pages
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Settings\Pages;

use rtCamp\Publish_With_AI\Core\Assets;
use rtCamp\Publish_With_AI\Core\Templates;
use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_Admin_Page;

/**
 * Class - Credentials_Page
 */
class Credentials_Page extends Abstract_Admin_Page {
	public const SLUG = 'rtcamp-publish-with-ai-credentials';

	/**
	 * {@inheritDoc}
	 */
	public function register(): string|false {
		return add_submenu_page(
			Guide_Page::SLUG,
			__( 'Credentials', 'rtcamp-publish-with-ai' ),
			__( 'Credentials', 'rtcamp-publish-with-ai' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function enqueue( array $localized_data ): void {
		wp_localize_script( Assets::ADMIN_CREDENTIALS_HANDLE, 'rtPublishWithAIAdmin', $localized_data );
		wp_enqueue_script( Assets::ADMIN_CREDENTIALS_HANDLE );
		wp_enqueue_style( Assets::ADMIN_CREDENTIALS_HANDLE );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(): void {
		Templates::get_template_part( 'credentials-screen' );
	}
}
