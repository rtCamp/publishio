<?php
/**
 * Registers custom meta to the Example post type.
 *
 * @package rtCamp\Publish_With_AI\Modules\Example
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\Example;

use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;

/**
 * Class - Example_Post_Type_Meta
 */
final class Example_Post_Type_Meta implements Registrable {
	/**
	 * Example meta key.
	 */
	public const EXAMPLE_META_KEY = '_example_meta_key';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_meta' ] );
	}

	/**
	 * Register post meta for the example post type.
	 */
	public function register_meta(): void {
		$meta = [
			self::EXAMPLE_META_KEY => [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static fn (): bool => current_user_can( 'edit_posts' ),
			],
		];

		foreach ( $meta as $meta_key => $meta_args ) {
			register_post_meta( Example_Post_Type::get_slug(), $meta_key, $meta_args );
		}
	}
}
