<?php
/**
 * Update post ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Content
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Content;

use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Editing\Errors;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Fields;
use rtCamp\Publishio\Modules\MCP\Editing\Post_Writer;

/**
 * Class - Update_Post
 */
final class Update_Post extends Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'update-post';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Update Post Details', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Changes a post\'s title, slug, excerpt, parent, template or custom fields. Only the fields given change. Content changes go through the block tools.', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'required'             => [ 'post_id' ],
			'properties'           => [
				'post_id'        => Schema::post_id(),
				'template'       => [
					'type'        => 'string',
					'maxLength'   => 200,
					'description' => 'A template the theme offers for this post type, such as "page-wide". An empty string uses the default template.',
				],
				'fields'         => Schema::field_values( 'Custom fields to set, by the keys get-post lists under fields, such as {"price": 49}. null resets a field to its default. Each field takes the type get-post gives for it.' ),
				'approval_token' => Schema::approval_token(),
			] + Post_Details::properties(),
			'additionalProperties' => false,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema(): array {
		$string = [ 'type' => 'string' ];

		return [
			'type'       => 'object',
			'required'   => [ 'post_id', 'title', 'slug', 'excerpt', 'parent_id', 'template', 'url' ],
			'properties' => [
				'post_id'   => [ 'type' => 'integer' ],
				'title'     => $string,
				'slug'      => $string,
				'excerpt'   => $string,
				'parent_id' => [ 'type' => 'integer' ],
				'template'  => $string,
				'url'       => Schema::post_url(),
				'fields'    => Schema::field_values( 'The custom fields given, as saved. The site may have changed a value, such as rounding a price.' ),
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function annotations(): array {
		return [
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute( array $input ): array|\WP_Error {
		$post = Post_Writer::find( (int) ( $input['post_id'] ?? 0 ) );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$guard = Post_Writer::guard( $post, (string) ( $input['approval_token'] ?? '' ) );

		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$fields = Post_Details::fields( $input, $post->post_type, $post->ID );

		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		if ( isset( $input['template'] ) ) {
			$template = '' === $input['template'] ? 'default' : (string) $input['template'];

			if ( 'default' !== $template && ! isset( wp_get_theme()->get_page_templates( $post )[ $template ] ) ) {
				/* translators: %s: template name */
				return Errors::make( 'invalid_template', sprintf( __( '"%s" isn\'t a template the theme offers for this post type. Leave out template to keep the current one.', 'publishio' ), $template ) );
			}

			$fields['page_template'] = $template;
		}

		$values = Post_Fields::check( $post, is_array( $input['fields'] ?? null ) ? $input['fields'] : [] );

		if ( is_wp_error( $values ) ) {
			return $values;
		}

		$before  = $post;
		$changes = [] !== $fields || [] !== $values;
		$saved   = [];

		// Saved as core's REST API saves a post, so the after-insert hooks and the revision see the new fields too.
		if ( $changes ) {
			$result = wp_update_post( wp_slash( [ 'ID' => $post->ID ] + $fields ), true, false );

			if ( is_wp_error( $result ) ) {
				return Errors::make( 'save_failed', $result->get_error_message(), 500 );
			}

			$saved = [] === $values ? [] : Post_Fields::save( $post, $values );

			if ( is_wp_error( $saved ) ) {
				return self::fields_not_saved( $saved, $before, [] !== $fields );
			}
		}

		$post = get_post( $post->ID );

		if ( ! $post instanceof \WP_Post ) {
			return Errors::post_not_found( (int) $input['post_id'] );
		}

		if ( $changes ) {
			wp_after_insert_post( $post, true, $before );
		}

		return [
			'post_id'   => $post->ID,
			'title'     => $post->post_title,
			'slug'      => $post->post_name,
			'excerpt'   => $post->post_excerpt,
			'parent_id' => (int) $post->post_parent,
			'template'  => (string) get_page_template_slug( $post ),
			'url'       => Post_Details::url( $post ),
		] + ( [] === $saved ? [] : [ 'fields' => $saved ] );
	}

	/**
	 * The refusal for fields core couldn't save. When the post's own details were saved first, the after-insert hooks still run and the message says so.
	 *
	 * @param \WP_Error $error         Core's error.
	 * @param \WP_Post  $before        The post before the change.
	 * @param bool      $details_saved Whether the title, slug or other details were saved.
	 */
	private static function fields_not_saved( \WP_Error $error, \WP_Post $before, bool $details_saved ): \WP_Error {
		$reason = implode( ' ', $error->get_error_messages() );

		if ( ! $details_saved ) {
			return Errors::make( 'save_failed', $reason, 500 );
		}

		$post = get_post( $before->ID );

		if ( $post instanceof \WP_Post ) {
			wp_after_insert_post( $post, true, $before );
		}

		/* translators: %s: why core couldn't save the fields */
		return Errors::make( 'save_failed', sprintf( __( 'The other changes were saved, but the custom fields weren\'t: %s Send only the fields again.', 'publishio' ), $reason ), 500 );
	}
}
