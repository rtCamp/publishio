<?php
/**
 * Abstract Taxonomy class.
 *
 * Class to be extended by all taxonomies in the plugin. It includes the shared hook registration and default args.
 *
 * @package rtCamp\Publish_With_AI\Framework\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Framework\Contracts\Abstracts;

use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;

/**
 * Class - Abstract_Taxonomy
 */
abstract class Abstract_Taxonomy implements Registrable {
	/**
	 * Get slug of post type.
	 *
	 * @return lowercase-string&non-empty-string
	 */
	abstract public static function get_slug(): string;

	/**
	 * Get the object types associated with the taxonomy.
	 *
	 * These are usually the post types.
	 *
	 * @return list<lowercase-string&non-empty-string>
	 */
	abstract public static function get_object_types(): array;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_taxonomy' ] );
	}

	/**
	 * Register taxonomy.
	 */
	abstract public function register_taxonomy(): void;

	/**
	 * Default taxonomy args inherited by all taxonomies.
	 *
	 * @return array{
	 *   hierarchical: bool,
	 *   query_var: bool,
	 *   show_admin_column: bool,
	 *   show_in_rest: bool,
	 *   show_ui: bool,
	 * }
	 */
	protected function default_args(): array {
		return [
			'hierarchical'      => false,
			'query_var'         => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'show_ui'           => true,
		];
	}
}
