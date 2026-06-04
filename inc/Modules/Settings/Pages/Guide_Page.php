<?php
/**
 * Admin page definition for the Guide (main) page.
 *
 * @package rtCamp\Publish_With_AI\Modules\Settings\Pages
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Settings\Pages;

use rtCamp\Publish_With_AI\Core\Assets;
use rtCamp\Publish_With_AI\Core\Templates;
use rtCamp\Publish_With_AI\Framework\Contracts\Abstracts\Abstract_Admin_Page;

/**
 * Class - Guide_Page
 */
class Guide_Page extends Abstract_Admin_Page {
	public const SLUG = 'publish-with-ai';

	/**
	 * {@inheritDoc}
	 */
	public function register(): string {
		$hook_suffix = add_menu_page(
			__( 'Publish With AI', 'publish-with-ai' ),
			__( 'Publish With AI', 'publish-with-ai' ),
			'edit_posts',
			self::SLUG,
			[ $this, 'render' ]
		);

		add_submenu_page(
			self::SLUG,
			__( 'Guide', 'publish-with-ai' ),
			__( 'Guide', 'publish-with-ai' ),
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
		wp_localize_script( Assets::ADMIN_HANDLE, 'rtPublishWithAIAdmin', $localized_data );
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
