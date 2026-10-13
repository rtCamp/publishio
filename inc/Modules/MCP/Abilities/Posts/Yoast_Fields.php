<?php
/**
 * Yoast SEO fields shared by the get and set abilities.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Posts
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Posts;

/**
 * Class - Yoast_Fields
 */
final class Yoast_Fields {
	/**
	 * Ability field name => Yoast metabox tab, Yoast field key, and the field's JSON schema in both abilities.
	 *
	 * @var array<string, array{tab: string, key: string, schema: array<string, mixed>}>
	 */
	public const FIELDS = [
		'seo_title'           => [
			'tab'    => 'general',
			'key'    => 'title',
			'schema' => [
				'type'        => 'string',
				'description' => 'SEO title shown in search results. Supports Yoast replacement variables (e.g. %%title%% %%sep%% %%sitename%%).',
			],
		],
		'meta_description'    => [
			'tab'    => 'general',
			'key'    => 'metadesc',
			'schema' => [
				'type'        => 'string',
				'description' => 'Meta description shown in search results (recommended 120–156 characters).',
			],
		],
		'focus_keyphrase'     => [
			'tab'    => 'general',
			'key'    => 'focuskw',
			'schema' => [
				'type'        => 'string',
				'description' => 'The primary keyphrase this post should rank for.',
			],
		],
		'canonical'           => [
			'tab'    => 'advanced',
			'key'    => 'canonical',
			'schema' => [
				'type'        => 'string',
				'description' => 'Canonical URL override, as an absolute http or https URL. Empty means the post\'s permalink is used.',
			],
		],
		'noindex'             => [
			'tab'    => 'advanced',
			'key'    => 'meta-robots-noindex',
			'schema' => [
				'type'        => 'string',
				'enum'        => [ 'default', 'index', 'noindex' ],
				'description' => 'Whether search engines may show the post in search results, as in Yoast\'s editor: "default" follows the post type\'s setting in Yoast, "index" allows it, and "noindex" prevents it.',
			],
		],
		'nofollow'            => [
			'tab'    => 'advanced',
			'key'    => 'meta-robots-nofollow',
			'schema' => [
				'type'        => 'boolean',
				'description' => 'Whether search engines are told not to follow links on the post (a nofollow robots directive).',
			],
		],
		'og_title'            => [
			'tab'    => 'social',
			'key'    => 'opengraph-title',
			'schema' => [
				'type'        => 'string',
				'description' => 'Open Graph title (used by Facebook and other social platforms).',
			],
		],
		'og_description'      => [
			'tab'    => 'social',
			'key'    => 'opengraph-description',
			'schema' => [
				'type'        => 'string',
				'description' => 'Open Graph description.',
			],
		],
		'twitter_title'       => [
			'tab'    => 'social',
			'key'    => 'twitter-title',
			'schema' => [
				'type'        => 'string',
				'description' => 'Twitter card title.',
			],
		],
		'twitter_description' => [
			'tab'    => 'social',
			'key'    => 'twitter-description',
			'schema' => [
				'type'        => 'string',
				'description' => 'Twitter card description.',
			],
		],
		'schema_page_type'    => [
			'tab'    => 'schema',
			'key'    => 'schema_page_type',
			'schema' => [
				'type'        => 'string',
				'description' => 'Yoast Schema page type (e.g. "WebPage", "FAQPage", "ItemPage").',
			],
		],
		'schema_article_type' => [
			'tab'    => 'schema',
			'key'    => 'schema_article_type',
			'schema' => [
				'type'        => 'string',
				'description' => 'Yoast Schema article type (e.g. "Article", "BlogPosting", "NewsArticle").',
			],
		],
	];

	/**
	 * The fields' JSON schemas, keyed by ability field name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schemas(): array {
		return array_map( static fn ( array $field ): array => $field['schema'], self::FIELDS );
	}

	/**
	 * The post meta key of a field.
	 *
	 * @param string $field Ability field name.
	 */
	public static function meta_key( string $field ): string {
		return '_yoast_wpseo_' . self::FIELDS[ $field ]['key'];
	}

	/**
	 * A stored value as the abilities return it.
	 *
	 * @param string $field Ability field name.
	 * @param string $value Stored value.
	 */
	public static function decode( string $field, string $value ): string|bool {
		return match ( $field ) {
			'noindex'  => match ( $value ) {
				'1'     => 'noindex',
				'2'     => 'index',
				default => 'default',
			},
			'nofollow' => '1' === $value,
			default    => $value,
		};
	}

	/**
	 * A field's input value as Yoast stores it.
	 *
	 * @param string          $field Ability field name.
	 * @param bool|string|int $value Input value, as schema validation accepted it.
	 */
	public static function sanitize( string $field, mixed $value ): string|\WP_Error {
		return match ( $field ) {
			'noindex'   => match ( $value ) {
				'noindex' => '1',
				'index'   => '2',
				default   => '0',
			},
			'nofollow'  => rest_sanitize_boolean( $value ) ? '1' : '0',
			'canonical' => self::sanitize_canonical( (string) $value ),
			// Yoast's cleaner keeps replacement variables like %%date%%, which core's strips as percent-encoded octets.
			default     => \WPSEO_Utils::sanitize_text_field( (string) $value ), // @phpstan-ignore class.notFound (visible() has checked Yoast's classes.)
		};
	}

	/**
	 * An absolute http or https URL, or an empty string to remove the override.
	 *
	 * @param string $url Canonical URL.
	 */
	private static function sanitize_canonical( string $url ): string|\WP_Error {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$clean  = in_array( $scheme, [ 'http', 'https' ], true ) && wp_parse_url( $url, PHP_URL_HOST ) ? esc_url_raw( $url, [ 'http', 'https' ] ) : '';

		return '' === $clean
			? new \WP_Error( 'invalid_canonical', __( 'The canonical URL must be an absolute http or https URL. Nothing was saved.', 'publishio' ) )
			: $clean;
	}

	/**
	 * The fields Yoast SEO shows the current user in the editor for a post.
	 *
	 * Asks Yoast, so its permission rules, settings and filters apply. The focus keyphrase is also hidden while SEO analysis is off, as in Yoast's editor.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return list<string>|\WP_Error Ability field names.
	 */
	public static function visible( \WP_Post $post ): array|\WP_Error {
		if ( ! class_exists( 'WPSEO_Meta' ) || ! class_exists( 'WPSEO_Utils' ) || ! class_exists( 'WPSEO_Metabox_Analysis_SEO' ) ) {
			return new \WP_Error( 'yoast_unsupported', __( 'This version of Yoast SEO is not supported.', 'publishio' ) );
		}

		if ( ! \WPSEO_Utils::is_metabox_active( $post->post_type, 'post_type' ) ) {
			return new \WP_Error( 'yoast_disabled_for_post_type', __( 'Yoast SEO settings are turned off for this post type.', 'publishio' ) );
		}

		$tabs = [];

		// Yoast reads the post type for its advanced fields from the global post.
		$had_post        = array_key_exists( 'post', $GLOBALS );
		$previous_post   = $GLOBALS['post'] ?? null;
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		try {
			foreach ( [ 'general', 'advanced', 'social', 'schema' ] as $tab ) {
				$tabs[ $tab ] = \WPSEO_Meta::get_meta_field_defs( $tab, $post->post_type );
			}
		} finally {
			if ( $had_post ) {
				$GLOBALS['post'] = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			} else {
				unset( $GLOBALS['post'] );
			}
		}

		$keyphrase_shown = ( new \WPSEO_Metabox_Analysis_SEO() )->is_enabled();
		$visible         = [];

		foreach ( self::FIELDS as $field => [ 'tab' => $tab, 'key' => $key ] ) {
			if ( isset( $tabs[ $tab ][ $key ] ) && ( 'focus_keyphrase' !== $field || $keyphrase_shown ) ) {
				$visible[] = $field;
			}
		}

		return $visible;
	}
}
