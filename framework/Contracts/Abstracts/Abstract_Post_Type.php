<?php
/**
 * Abstract Post Type class.
 *
 * Class to be extended by all post types in the plugin. It includes the shared hook registration and default args.
 *
 * @package rtCamp\Publish_With_AI\Framework\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Framework\Contracts\Abstracts;

use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;

/**
 * Class - Abstract_Post_Type
 */
abstract class Abstract_Post_Type implements Registrable {
	/**
	 * Get slug of post type.
	 *
	 * @return lowercase-string&non-empty-string
	 */
	abstract public static function get_slug(): string;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	/**
	 * To register post type.
	 */
	abstract public function register_post_type(): void;

	/**
	 * Default post type args inherited by all post types.
	 *
	 * @return array{
	 *   show_in_rest: bool,
	 *   public: bool,
	 *   has_archive: bool,
	 *   menu_position: int,
	 *   supports: list<string>,
	 * }
	 */
	protected function default_args(): array {
		return [
			'show_in_rest'  => true,
			'public'        => true,
			'has_archive'   => true,
			'menu_position' => 6,
			'supports'      => [ 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ],
		];
	}
}
