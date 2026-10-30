<?php
/**
 * The header text of a preview card.
 *
 * @package rtCamp\Publishio\Modules\MCP\Apps\Preview
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Apps\Preview;

use rtCamp\Publishio\Modules\MCP\Editing\Block_Path;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Tree;
use rtCamp\Publishio\Modules\MCP\Editing\Outline;
use rtCamp\Publishio\Modules\MCP\Editing\Pattern_Library;

/**
 * Class - Preview_Card
 *
 * Each card has a title, a status word with its color, a subtitle, and the message shown when only the preview fails.
 *
 * @phpstan-import-type Pattern from Pattern_Library
 * @phpstan-type Card array{title: string, status: array{word: string, tone: string}, subtitle: string, edit_url: ?string, error: string}
 */
final class Preview_Card {
	/**
	 * The card for blocks just inserted into a post.
	 *
	 * @param \WP_Post                                                     $post      The post.
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree             $tree      The post's blocks.
	 * @param array<int, \rtCamp\Publishio\Modules\MCP\Editing\Block_Path> $paths     The inserted blocks, side by side and in order.
	 * @param string                                                       $placement The placement the insert used.
	 * @param bool                                                         $targeted  Whether the insert had a target block.
	 * @param ?string                                                      $pattern   The inserted pattern's title, if a pattern was inserted.
	 *
	 * @return Card
	 */
	public static function blocks( \WP_Post $post, Block_Tree $tree, array $paths, string $placement, bool $targeted, ?string $pattern ): array {
		$page = self::post_title( $post );

		return [
			'title'    => self::blocks_title( $tree, $paths, $pattern ),
			'status'   => [
				'word' => __( 'Added', 'publishio' ),
				'tone' => 'ok',
			],
			/* translators: 1: page title, 2: where the blocks went, such as "after Our story" */
			'subtitle' => sprintf( __( '%1$s, %2$s', 'publishio' ), $page, self::where( $tree, $paths, $placement, $targeted ) ),
			'edit_url' => self::edit_url( $post->ID ),
			'error'    => 1 === count( $paths )
				/* translators: %s: page title */
				? sprintf( __( 'The block was added to %s. Only the preview didn\'t load.', 'publishio' ), $page )
				/* translators: %s: page title */
				: sprintf( __( 'The blocks were added to %s. Only the preview didn\'t load.', 'publishio' ), $page ),
		];
	}

	/**
	 * The card for a pattern just saved to the pattern library.
	 *
	 * @param array<string, mixed> $pattern The pattern.
	 * @param bool                 $updated Whether it was updated rather than created.
	 * @phpstan-param Pattern $pattern
	 *
	 * @return Card
	 */
	public static function pattern( array $pattern, bool $updated ): array {
		$parts      = [ $pattern['synced'] ? __( 'Synced pattern', 'publishio' ) : __( 'Unsynced pattern', 'publishio' ) ];
		$categories = Pattern_Library::categories( [ $pattern ] );

		if ( [] !== $categories ) {
			$parts[] = $categories[0]['label'];
		}

		return [
			'title'    => $pattern['title'],
			'status'   => [
				'word' => $updated ? __( 'Updated', 'publishio' ) : __( 'Saved', 'publishio' ),
				'tone' => $updated ? 'info' : 'ok',
			],
			'subtitle' => implode( ' · ', $parts ),
			'edit_url' => $pattern['id'] > 0 ? self::edit_url( $pattern['id'] ) : null,
			'error'    => $updated
				? __( 'The pattern was updated. Only the preview didn\'t load.', 'publishio' )
				: __( 'The pattern was saved. Only the preview didn\'t load.', 'publishio' ),
		];
	}

	/**
	 * The card for a whole post.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return Card
	 */
	public static function page( \WP_Post $post ): array {
		$title = self::post_title( $post );

		return [
			'title'    => $title,
			'status'   => self::post_status( $post ),
			'subtitle' => (string) preg_replace( '#^https?://#', '', untrailingslashit( Page_Details::address( $post ) ) ),
			'edit_url' => self::edit_url( $post->ID ),
			/* translators: %s: page title */
			'error'    => sprintf( __( '%s is unchanged. Only the preview didn\'t load.', 'publishio' ), $title ),
		];
	}

	/**
	 * A post's title as WordPress shows it in lists.
	 *
	 * @param \WP_Post $post The post.
	 */
	public static function post_title( \WP_Post $post ): string {
		$title = trim( wp_strip_all_tags( $post->post_title ) );

		return '' === $title ? __( '(no title)', 'publishio' ) : $title;
	}

	/**
	 * The title for inserted blocks: their first heading, else one block's text, else the pattern's title, else the first block's name.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree             $tree    The post's blocks.
	 * @param array<int, \rtCamp\Publishio\Modules\MCP\Editing\Block_Path> $paths   The inserted blocks.
	 * @param ?string                                                      $pattern The inserted pattern's title.
	 */
	private static function blocks_title( Block_Tree $tree, array $paths, ?string $pattern ): string {
		foreach ( $paths as $path ) {
			$heading = self::heading_in( $tree, $path );

			if ( null !== $heading ) {
				return $heading;
			}
		}

		if ( 1 === count( $paths ) ) {
			$block = $tree->get( $paths[0] );
			$text  = is_wp_error( $block ) ? '' : Outline::text( $block );

			if ( '' !== $text ) {
				return $text;
			}
		}

		if ( null !== $pattern && '' !== $pattern ) {
			return $pattern;
		}

		return [] === $paths ? '' : self::type_title( $paths[0] );
	}

	/**
	 * Where inserted blocks went, named by their neighbor: "after Our story", "inside Our story", "at the top".
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree             $tree      The post's blocks.
	 * @param array<int, \rtCamp\Publishio\Modules\MCP\Editing\Block_Path> $paths     The inserted blocks.
	 * @param string                                                       $placement The placement the insert used.
	 * @param bool                                                         $targeted  Whether the insert had a target block.
	 */
	private static function where( Block_Tree $tree, array $paths, string $placement, bool $targeted ): string {
		$first  = $paths[0] ?? Block_Path::root();
		$last   = $paths[ count( $paths ) - 1 ] ?? $first;
		$parent = $first->parent();

		if ( ! $targeted || ( $parent->is_root() && in_array( $placement, [ 'start', 'end' ], true ) ) ) {
			return 'start' === $placement ? __( 'at the top', 'publishio' ) : __( 'at the end', 'publishio' );
		}

		if ( 'before' === $placement || 'after' === $placement ) {
			$index    = 'before' === $placement ? $last->index() + 1 : $first->index() - 1;
			$neighbor = $index >= 0 ? self::child_at( $tree, $parent, $index ) : null;

			if ( null !== $neighbor ) {
				return 'before' === $placement
					/* translators: %s: the next block's name */
					? sprintf( __( 'before %s', 'publishio' ), self::name_of( $tree, $neighbor ) )
					/* translators: %s: the previous block's name */
					: sprintf( __( 'after %s', 'publishio' ), self::name_of( $tree, $neighbor ) );
			}
		}

		/* translators: %s: the containing block's name */
		return $parent->is_root() ? __( 'at the end', 'publishio' ) : sprintf( __( 'inside %s', 'publishio' ), self::name_of( $tree, $parent ) );
	}

	/**
	 * A block's name for the card: its first heading, else its text, else its type.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree $tree The post's blocks.
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $path The block.
	 */
	private static function name_of( Block_Tree $tree, Block_Path $path ): string {
		$heading = self::heading_in( $tree, $path );

		if ( null !== $heading ) {
			return $heading;
		}

		$block = $tree->get( $path );
		$text  = is_wp_error( $block ) ? '' : Outline::text( $block );

		return '' === $text ? self::type_title( $path ) : $text;
	}

	/**
	 * The text of the first heading in a block, the block included.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree $tree The post's blocks.
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $path The block.
	 */
	private static function heading_in( Block_Tree $tree, Block_Path $path ): ?string {
		$outline = Outline::of( $tree, $path );

		if ( is_wp_error( $outline ) ) {
			return null;
		}

		foreach ( $outline['rows'] as $row ) {
			$step = Block_Path::parse( $row['path'] );

			if ( ! is_wp_error( $step ) && 'core/heading' === ( $step->last()['name'] ?? '' ) && '' !== $row['text'] ) {
				return $row['text'];
			}
		}

		return null;
	}

	/**
	 * The path of a container's child at an index, if there is one.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree $tree      The post's blocks.
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $container The container.
	 * @param int                                              $index     The child's index.
	 */
	private static function child_at( Block_Tree $tree, Block_Path $container, int $index ): ?Block_Path {
		$parent   = $tree->get( $container );
		$children = is_wp_error( $parent ) ? [] : array_values( (array) ( $parent['innerBlocks'] ?? [] ) );

		return isset( $children[ $index ] ) ? $container->child( $index, Block_Tree::name_of( $children[ $index ] ) ) : null;
	}

	/**
	 * A block type's title, such as "Image".
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $path The block.
	 */
	private static function type_title( Block_Path $path ): string {
		$name = (string) ( $path->last()['name'] ?? '' );
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( $name );

		return $type && '' !== (string) $type->title ? (string) $type->title : Block_Path::short_name( $name );
	}

	/**
	 * The status word and its color for a post's status.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return array{word: string, tone: string}
	 */
	private static function post_status( \WP_Post $post ): array {
		return match ( $post->post_status ) {
			'publish' => [
				'word' => __( 'Published', 'publishio' ),
				'tone' => 'ok',
			],
			'future'  => [
				/* translators: %s: the publish date, such as "4 Nov" */
				'word' => sprintf( __( 'Scheduled for %s', 'publishio' ), (string) get_the_date( _x( 'j M', 'scheduled date on the preview card', 'publishio' ), $post ) ),
				'tone' => 'info',
			],
			'pending' => [
				'word' => __( 'Pending review', 'publishio' ),
				'tone' => 'warn',
			],
			'private' => [
				'word' => __( 'Private', 'publishio' ),
				'tone' => 'muted',
			],
			default   => [
				'word' => __( 'Draft', 'publishio' ),
				'tone' => 'warn',
			],
		};
	}

	/**
	 * A post's editor link, if the user can edit it.
	 *
	 * @param int $post_id The post ID.
	 */
	private static function edit_url( int $post_id ): ?string {
		$url = get_edit_post_link( $post_id, 'raw' );

		return $url ? $url : null;
	}
}
