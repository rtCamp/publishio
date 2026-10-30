<?php
/**
 * How a post shows in search results and in shared links.
 *
 * @package rtCamp\Publishio\Modules\MCP\Apps\Preview
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Apps\Preview;

/**
 * Class - Page_Details
 *
 * With Yoast SEO, the values come from Yoast's meta for the post, which applies the same fallbacks Yoast uses on the live page. Without it, they come from the post's title, excerpt and featured image. Read them before anything calls wp_head(): where Yoast doesn't save indexables, such as on local sites, it reuses the first meta it built in the request.
 *
 * @phpstan-type Details array{search: array{address: string, title: string, description: ?string, hidden: bool}, social: array{image: ?string, site: string, title: string, description: ?string}}
 */
final class Page_Details {
	/**
	 * The search result and shared-link card for a post.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return Details
	 */
	public static function of( \WP_Post $post ): array {
		$yoast       = self::yoast( $post );
		$host        = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$path        = trim( (string) wp_parse_url( self::address( $post ), PHP_URL_PATH ), '/' );
		$title       = self::text( $yoast['title'] ?? null ) ?? self::default_title( $post );
		$description = self::text( $yoast['description'] ?? null ) ?? self::text( $post->post_excerpt );
		$image       = self::text( $yoast['image'] ?? null ) ?? self::featured_image( $post );

		return [
			'search' => [
				'address'     => implode( ' › ', array_filter( [ $host, ...explode( '/', $path ) ] ) ),
				'title'       => $title,
				'description' => $description,
				'hidden'      => ( $yoast['noindex'] ?? false ) || '0' === (string) get_option( 'blog_public' ),
			],
			'social' => [
				'image'       => $image,
				'site'        => $host,
				'title'       => self::text( $yoast['social_title'] ?? null ) ?? $title,
				'description' => self::text( $yoast['social_description'] ?? null ) ?? $description,
			],
		];
	}

	/**
	 * The post's address, or for a post that isn't public yet, the address it gets when published.
	 *
	 * @param \WP_Post $post The post.
	 */
	public static function address( \WP_Post $post ): string {
		if ( in_array( $post->post_status, [ 'publish', 'private' ], true ) ) {
			return (string) get_permalink( $post );
		}

		if ( ! function_exists( 'get_sample_permalink' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant -- Core's own file.
		}

		[ $template, $name ] = get_sample_permalink( $post );

		return str_replace( [ '%postname%', '%pagename%' ], (string) $name, (string) $template );
	}

	/**
	 * The values Yoast SEO would use for the post, when Yoast is active.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return array{title?: mixed, description?: mixed, social_title?: mixed, social_description?: mixed, image?: mixed, noindex?: bool}
	 */
	private static function yoast( \WP_Post $post ): array {
		if ( ! function_exists( 'YoastSEO' ) ) {
			return [];
		}

		try {
			$meta = YoastSEO()->meta->for_post( $post->ID );

			if ( ! is_object( $meta ) ) {
				return [];
			}

			$read   = static fn ( string $key ): mixed => $meta->{$key};
			$images = $read( 'open_graph_images' );
			$robots = $read( 'robots' );
			$first  = is_array( $images ) ? reset( $images ) : null;

			return [
				'title'              => $read( 'title' ),
				'description'        => $read( 'description' ),
				'social_title'       => $read( 'open_graph_title' ),
				'social_description' => $read( 'open_graph_description' ),
				'image'              => is_array( $first ) ? ( $first['url'] ?? null ) : null,
				'noindex'            => is_array( $robots ) && 'noindex' === ( $robots['index'] ?? null ),
			];
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	/**
	 * The title WordPress gives the page without an SEO plugin, such as "About us - Harbor Bakery".
	 *
	 * @param \WP_Post $post The post.
	 */
	private static function default_title( \WP_Post $post ): string {
		$separator = (string) apply_filters( 'document_title_separator', '-' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter, applied as wp_get_document_title() applies it.

		return implode( ' ' . $separator . ' ', array_filter( [ Preview_Card::post_title( $post ), wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) ] ) );
	}

	/**
	 * The featured image's URL.
	 *
	 * @param \WP_Post $post The post.
	 */
	private static function featured_image( \WP_Post $post ): ?string {
		$url = get_the_post_thumbnail_url( $post, 'large' );

		return is_string( $url ) && '' !== $url ? $url : null;
	}

	/**
	 * A plain, trimmed string, or null when there's no text.
	 *
	 * @param mixed $value The value.
	 */
	private static function text( mixed $value ): ?string {
		$text = is_string( $value ) ? trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) : '';

		return '' === $text ? null : $text;
	}
}
