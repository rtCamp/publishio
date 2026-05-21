<?php
/**
 * Trait for WordPress template loading with theme override support.
 *
 * Allows plugins to provide template files that themes can override.
 * Follows WordPress template hierarchy: Child Theme > Parent Theme > Plugin.
 *
 * @package rtCamp\Publish_With_AI\Framework
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Framework;

/**
 * Trait - TemplateLoaderTrait.
 *
 * Usage:
 *   class My_Template_Loader {
 *       use TemplateLoaderTrait;
 *
 *       public function __construct() {
 *           $this->hook_prefix        = 'my_plugin';
 *           $this->template_theme_dir = 'my-plugin';
 *           $this->template_dir       = MY_PLUGIN_DIR . 'templates';
 *       }
 *   }
 *
 *   $loader->get_template_part( 'content', 'card', [ 'title' => 'Hello' ] );
 */
trait TemplateLoaderTrait {
	/**
	 * Prefix for filter names.
	 *
	 * Usually the plugin slug in snake_case.
	 *
	 * @var non-empty-string
	 */
	private string $hook_prefix;

	/**
	 * Separator between the hook prefix and hook name.
	 *
	 * E.g. if '/', the hook would be "my_plugin/get_template_part_content".
	 *
	 * @var non-empty-string
	 */
	private string $hook_separator = '/';

	/**
	 * Absolute path to the plugin's own template directory.
	 *
	 * @var non-empty-string
	 */
	private string $template_dir;

	/**
	 * Directory name where custom templates should be found in the theme.
	 *
	 * E.g., 'my-plugin' means themes can override at: my-theme/my-plugin/content.php
	 *
	 * @var non-empty-string
	 */
	private string $template_theme_dir;

	/**
	 * Cache for located template paths during a single request.
	 *
	 * @var array<string, string|false>
	 */
	private static array $template_location_cache = [];

	/**
	 * Retrieve a template part.
	 *
	 * @param string               $slug Template slug.
	 * @param string|null          $name Optional. Template variation name.
	 * @param array<string, mixed> $args Optional. Data to pass to the template.
	 * @param bool                 $load Optional. Whether to load the template. Default false.
	 * @return string|false Located template path, or false if not found.
	 */
	public function get_template_part( string $slug, ?string $name = null, array $args = [], bool $load = false ): string|false {
		/**
		 * Fires when a template part is requested.
		 *
		 * @param string      $slug Template slug.
		 * @param string|null $name Template variation name.
		 * @param array       $args Data to pass to the template.
		 */
		do_action(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Set based on the plugin.
			"{$this->hook_prefix}{$this->hook_separator}get_template_part_$slug",
			$slug,
			$name,
			$args
		);

		// Build template file candidates.
		$templates = $this->get_template_file_names( $slug, $name );

		// Allow filtering of args before template load.
		/**
		 * Filters the arguments passed to the template.
		 *
		 * @param array<string,mixed> $args Data to pass to the template.
		 * @param string              $slug Template slug.
		 * @param string|null         $name Template variation name.
		 */
		$args = apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Set based on the plugin.
			"{$this->hook_prefix}{$this->hook_separator}template_args",
			$args,
			$slug,
			$name
		);

		$located = $this->locate_template( $templates );

		if ( $load && $located ) {
			load_template( $located, false, $args );
		}

		return $located;
	}

	/**
	 * Build template file names from slug and name.
	 *
	 * @param string      $slug Template slug.
	 * @param string|null $name Template variation name.
	 * @return string[] List of template file names.
	 */
	private function get_template_file_names( string $slug, ?string $name ): array {
		$templates = [];

		if ( null !== $name && '' !== $name ) {
			$templates[] = "{$slug}-{$name}.php";
		}

		$templates[] = "{$slug}.php";

		/**
		 * Filters the list of template file names to search for.
		 *
		 * Useful for adding additional template variations or modifying the search order.
		 *
		 * @param string[]    $templates Template file names.
		 * @param string      $slug      Template slug.
		 * @param string|null $name      Template variation name.
		 */
		$templates = apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Set based on the plugin.
			"{$this->hook_prefix}{$this->hook_separator}template_file_names",
			$templates,
			$slug,
			$name
		);

		return $templates;
	}

	/**
	 * Retrieve the highest priority template file that exists.
	 *
	 * Searches: Child Theme > Parent Theme > Plugin.
	 *
	 * @param array<int, string> $templates Template files to search for, in order of preference.
	 * @return string|false Located template path, or false if not found.
	 */
	private function locate_template( array $templates ): string|false {
		// Sanitize templates array.
		$templates = array_filter( array_map( 'sanitize_file_name', $templates ) );

		if ( empty( $templates ) ) {
			return false;
		}

		$located = $this->find_template( $templates );

		/**
		 * Filters the located template path.
		 *
		 * @param string|false  $located   Full path to the located template.
		 * @param string[]      $templates Template files that were searched for.
		 */
		$located = apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Set based on the plugin.
			"{$this->hook_prefix}{$this->hook_separator}located_template",
			$located,
			$templates
		);

		return $located;
	}

	/**
	 * Get ordered list of template search paths.
	 *
	 * Priority: 1 = Child Theme, 10 = Parent Theme, 100 = Plugin.
	 *
	 * @return array<int, string> Ordered paths (already trailing-slashed).
	 */
	private function get_template_paths(): array {
		$paths = [];

		// Child theme (only if different from parent).
		if ( is_child_theme() ) {
			$paths[1] = get_stylesheet_directory() . '/' . $this->template_theme_dir;
		}

		// Parent theme.
		$paths[10] = get_template_directory() . '/' . $this->template_theme_dir;

		// Plugin fallback.
		$paths[100] = $this->template_dir;

		/**
		 * Filters the template search paths.
		 *
		 * @param array<int, string> $paths Paths keyed by priority.
		 */
		$paths = apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Set based on the plugin.
			"{$this->hook_prefix}{$this->hook_separator}template_paths",
			$paths
		);

		// Sort by priority.
		ksort( $paths, SORT_NUMERIC );

		return array_map( 'trailingslashit', $paths );
	}

	/**
	 * Find the first existing template from a list.
	 *
	 * @param array<int, string> $templates Template files to search for.
	 * @return string|false Full path to the template, or false if none found.
	 */
	private function find_template( array $templates ): string|false {
		// Use first template as cache key.
		$cache_key = $templates[0];

		if ( isset( self::$template_location_cache[ $cache_key ] ) ) {
			return self::$template_location_cache[ $cache_key ];
		}

		$search_paths = $this->get_template_paths();

		$found_path = false;

		foreach ( $templates as $template ) {
			$template = ltrim( $template, '/\\' );

			foreach ( $search_paths as $path ) {
				$full_path = $path . $template;

				if ( file_exists( $full_path ) ) {
					$found_path = $full_path;
					break 2; // Break both loops.
				}
			}
		}

		// Cache the result (even if false).
		self::$template_location_cache[ $cache_key ] = $found_path;

		return $found_path;
	}
}
