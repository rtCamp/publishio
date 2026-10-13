<?php
/**
 * Stand-ins for the Yoast SEO API that Yoast_Fields calls, for tests that run without Yoast.
 *
 * Load only in tests that run in separate processes, since these classes and the constant can't be unloaded.
 *
 * @package rtCamp\Publishio\Tests
 *
 * phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
 * phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing
 * phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter, Generic.CodeAnalysis.UnusedFunctionParameter.Found, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
 */

declare( strict_types = 1 );

define( 'WPSEO_VERSION', 'stub' );

/**
 * Field definitions per metabox tab, as Yoast would return them to the current user.
 */
final class WPSEO_Meta {
	/**
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	public static array $tabs = [];

	/**
	 * Like Yoast, the advanced tab is empty unless the global post is set.
	 *
	 * @param string $tab       Metabox tab.
	 * @param string $post_type Post type.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_meta_field_defs( string $tab, string $post_type = 'post' ): array {
		if ( 'advanced' === $tab && ! ( ( $GLOBALS['post'] ?? null ) instanceof \WP_Post ) ) {
			return [];
		}

		return self::$tabs[ $tab ] ?? [];
	}
}

/**
 * Whether Yoast's SEO settings are on for a post type, and Yoast's text cleaner.
 */
final class WPSEO_Utils {
	public static bool $metabox_active = true;

	public static function is_metabox_active( string $identifier, string $type ): bool {
		return self::$metabox_active;
	}

	/**
	 * Copied from Yoast SEO 28.7 (inc/class-wpseo-utils.php). Unlike core's, it keeps replacement variables like %%date%%.
	 *
	 * @param string $value Text to clean.
	 */
	public static function sanitize_text_field( string $value ): string {
		$filtered = wp_check_invalid_utf8( $value );

		if ( strpos( $filtered, '<' ) !== false ) {
			$filtered = wp_pre_kses_less_than( $filtered );
			$filtered = wp_strip_all_tags( $filtered, true );
		} else {
			$filtered = trim( preg_replace( '`[\r\n\t ]+`', ' ', $filtered ) );
		}

		$found = false;
		while ( preg_match( '`[^%](%[a-f0-9]{2})`i', $filtered, $match ) ) {
			$filtered = str_replace( $match[1], '', $filtered );
			$found    = true;
		}

		if ( $found ) {
			$filtered = trim( preg_replace( '` +`', ' ', $filtered ) );
		}

		return apply_filters( 'sanitize_text_field', $filtered, $value );
	}
}

/**
 * Whether SEO analysis is on for the site and the current user.
 */
final class WPSEO_Metabox_Analysis_SEO {
	public static bool $enabled = true;

	public function is_enabled(): bool {
		return self::$enabled;
	}
}
