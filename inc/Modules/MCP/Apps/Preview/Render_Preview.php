<?php
/**
 * Render preview ability, called by the Publishio Preview app.
 *
 * @package rtCamp\Publishio\Modules\MCP\Apps\Preview
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Apps\Preview;

use rtCamp\Publishio\Core\Templates;
use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Abilities\Blocks\Render_Blocks;
use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Path;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Tree;
use rtCamp\Publishio\Modules\MCP\Editing\Errors;
use rtCamp\Publishio\Modules\MCP\Editing\Pattern_Library;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Writer;

/**
 * Class - Render_Preview
 *
 * The app asks for the card first, so the header shows while the theme renders the body.
 */
final class Render_Preview extends Ability {
	public const VIEWS = [ 'blocks', 'pattern', 'page' ];

	public const PARTS = [ 'card', 'body' ];

	private const MAX_PATHS = 50;

	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'render-preview';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Render Preview (App Only)', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Called by the Publishio Preview app, not by the AI. Returns a preview card\'s header text, or the theme-styled HTML of the inserted blocks, a pattern, or a whole post with its search and social details.', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'required'             => [ 'view', 'part' ],
			'properties'           => [
				'view'      => [
					'type' => 'string',
					'enum' => self::VIEWS,
				],
				'part'      => [
					'type' => 'string',
					'enum' => self::PARTS,
				],
				'post_id'   => Schema::post_id(),
				'paths'     => [
					'type'     => 'array',
					'minItems' => 1,
					'maxItems' => self::MAX_PATHS,
					'items'    => Schema::path( 'An inserted block.' ),
				],
				'placement' => Schema::placement(),
				'targeted'  => [ 'type' => 'boolean' ],
				'pattern'   => Schema::pattern_name(),
				'updated'   => [ 'type' => 'boolean' ],
			],
			'additionalProperties' => false,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema(): array {
		$string   = [ 'type' => 'string' ];
		$optional = [ 'type' => [ 'string', 'null' ] ];

		return [
			'type'       => 'object',
			'properties' => [
				'title'    => $string,
				'status'   => [
					'type'       => 'object',
					'properties' => [
						'word' => $string,
						'tone' => $string,
					],
				],
				'line'     => $string,
				'edit_url' => $optional,
				'error'    => $string,
				'html'     => $string,
				'search'   => [
					'type'       => 'object',
					'properties' => [
						'address'     => $string,
						'title'       => $string,
						'description' => $optional,
						'hidden'      => [ 'type' => 'boolean' ],
					],
				],
				'social'   => [
					'type'       => 'object',
					'properties' => [
						'image'       => $optional,
						'site'        => $string,
						'title'       => $string,
						'description' => $optional,
					],
				],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function annotations(): array {
		return [
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function mcp_meta(): array {
		return [ 'ui' => [ 'visibility' => [ 'app' ] ] ];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute( array $input ): array|\WP_Error {
		$card = 'card' === ( $input['part'] ?? '' );

		if ( 'pattern' === ( $input['view'] ?? '' ) ) {
			$pattern = Pattern_Library::find( (string) ( $input['pattern'] ?? '' ) );

			if ( is_wp_error( $pattern ) ) {
				return $pattern;
			}

			return $card ? Preview_Card::pattern( $pattern, (bool) ( $input['updated'] ?? false ) ) : [ 'html' => self::document( Pattern_Library::blocks( $pattern ), null ) ];
		}

		$post = Post_Writer::load( (int) ( $input['post_id'] ?? 0 ) );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$tree = Block_Tree::from_markup( $post->post_content );

		if ( 'page' === ( $input['view'] ?? '' ) && $card ) {
			return Preview_Card::page( $post );
		}

		if ( 'page' === ( $input['view'] ?? '' ) ) {
			$details = Page_Details::of( $post );

			return [ 'html' => self::document( $tree->blocks(), $post ) ] + $details;
		}

		$paths  = [];
		$blocks = [];

		foreach ( (array) ( $input['paths'] ?? [] ) as $value ) {
			$path  = Block_Path::parse( (string) $value );
			$block = is_wp_error( $path ) ? $path : $tree->get( $path );

			if ( is_wp_error( $block ) ) {
				return $block;
			}

			$paths[]  = $path;
			$blocks[] = $block;
		}

		if ( [] === $paths ) {
			return Errors::invalid_path( '', __( 'Send the paths of the inserted blocks.', 'publishio' ) );
		}

		if ( ! $card ) {
			return [ 'html' => self::document( $blocks, $post ) ];
		}

		$pattern = isset( $input['pattern'] ) ? Pattern_Library::find( (string) $input['pattern'] ) : null;

		return Preview_Card::blocks( $post, $tree, $paths, (string) ( $input['placement'] ?? 'end' ), (bool) ( $input['targeted'] ?? false ), is_array( $pattern ) ? $pattern['title'] : null );
	}

	/**
	 * A whole HTML document with rendered blocks and the theme's styles, for the app's frame.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param ?\WP_Post                        $post   The post the blocks are in.
	 */
	private static function document( array $blocks, ?\WP_Post $post ): string {
		show_admin_bar( false ); // phpcs:ignore WordPressVIPMinimum.UserExperience.AdminBarRemoval.RemovalDetected -- The preview has no admin bar.
		wp_enqueue_style( 'publishio-preview', PUBLISHIO_URL . 'assets/css/preview.css', [], PUBLISHIO_VERSION );

		$html = Render_Blocks::render( $blocks, $post );

		ob_start();
		Templates::get_template_part( 'preview', null, [ 'html' => $html ] );

		return (string) ob_get_clean();
	}
}
