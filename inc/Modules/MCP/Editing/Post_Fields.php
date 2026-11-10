<?php
/**
 * Reads and saves a post's custom fields.
 *
 * @package rtCamp\Publishio\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Editing;

/**
 * Class - Post_Fields
 *
 * Only the fields the block editor's REST API offers (registered with show_in_rest, on a type shown in REST that supports custom fields) that aren't protected and that the user can edit. Fields are keyed by meta key, as block bindings name them.
 */
final class Post_Fields extends \WP_REST_Post_Meta_Fields {
	private const SCHEMA_KEYS = [ 'type', 'title', 'description', 'enum', 'format', 'minimum', 'maximum', 'items', 'properties' ];

	// The Footnotes block's own storage, which it rewrites from the content.
	private const BLOCK_STORAGE_KEYS = [ 'footnotes' ];

	/**
	 * Each field's value and what it takes, by meta key.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function describe( \WP_Post $post ): array {
		$fields = self::settable( $post );

		if ( [] === $fields ) {
			return [];
		}

		$values    = ( new self( $post->post_type ) )->get_value( $post->ID, new \WP_REST_Request() );
		$described = [];

		foreach ( $fields as $key => $args ) {
			$schema = array_filter(
				array_intersect_key( $args['schema'], array_flip( self::SCHEMA_KEYS ) ),
				static fn ( mixed $value ): bool => '' !== $value && [] !== $value
			);

			if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) ) {
				$schema['enum'] = array_values( $schema['enum'] );
			}

			$described[ $key ] = [ 'value' => $values[ $args['name'] ] ?? null ] + $schema;
		}

		return $described;
	}

	/**
	 * Check every field by the rules core applies when saving, so a save doesn't stop halfway.
	 *
	 * @param \WP_Post             $post   The post.
	 * @param array<string, mixed> $values New values by meta key. Null resets a field to its default.
	 *
	 * @return array<string, mixed>|\WP_Error The values, ready for save().
	 */
	public static function check( \WP_Post $post, array $values ): array|\WP_Error {
		$fields  = self::settable( $post );
		$checked = [];
		$invalid = [];

		foreach ( $values as $key => $value ) {
			$key = (string) $key;

			if ( ! isset( $fields[ $key ] ) ) {
				return self::unknown( $post, $key, array_keys( $fields ) );
			}

			$problem = self::problem( $post, $key, $fields[ $key ], $value );

			if ( null !== $problem ) {
				$invalid[] = $problem;
				continue;
			}

			$checked[ $key ] = $value;
		}

		if ( [] !== $invalid ) {
			return Errors::make( 'invalid_field_value', implode( ' ', $invalid ) . ' ' . __( 'Nothing was saved. Fix these and send the fields again.', 'publishio' ) );
		}

		return $checked;
	}

	/**
	 * Save checked fields through core, which sanitizes each value and checks the user may edit it, and return them as stored, by meta key.
	 *
	 * @param \WP_Post             $post   The post.
	 * @param array<string, mixed> $values Values by meta key, from check().
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function save( \WP_Post $post, array $values ): array|\WP_Error {
		$fields     = new self( $post->post_type );
		$registered = $fields->get_registered_fields();
		$meta       = [];

		foreach ( $values as $key => $value ) {
			// Nothing to reset, and core would refuse because an empty stored value doesn't fit types such as number.
			if ( null === $value && ! metadata_exists( 'post', $post->ID, (string) $key ) ) {
				continue;
			}

			$meta[ $registered[ $key ]['name'] ] = $value;
		}

		$result = [] === $meta ? null : $fields->update_value( $meta, $post->ID );

		if ( is_wp_error( $result ) ) {
			return Errors::make( 'save_failed', implode( ' ', $result->get_error_messages() ), 500 );
		}

		$stored = $fields->get_value( $post->ID, new \WP_REST_Request() );
		$saved  = [];

		foreach ( array_keys( $values ) as $key ) {
			$saved[ (string) $key ] = $stored[ $registered[ $key ]['name'] ] ?? null;
		}

		return $saved;
	}

	/**
	 * The fields the current user can set on a post, by meta key, with core's REST settings for each.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function settable( \WP_Post $post ): array {
		$type = get_post_type_object( $post->post_type );

		if ( ! $type || ! $type->show_in_rest || ! post_type_supports( $post->post_type, 'custom-fields' ) ) {
			return [];
		}

		$settable = [];

		foreach ( ( new self( $post->post_type ) )->get_registered_fields() as $key => $args ) {
			$key = (string) $key;

			if ( ! in_array( $key, self::BLOCK_STORAGE_KEYS, true ) && ! is_protected_meta( $key, 'post' ) && current_user_can( 'edit_post_meta', $post->ID, $key ) ) {
				$settable[ $key ] = $args;
			}
		}

		return $settable;
	}

	/**
	 * Why core would refuse to save a value, or null.
	 *
	 * @param \WP_Post             $post  The post.
	 * @param string               $key   The meta key.
	 * @param array<string, mixed> $args  Core's REST settings for the field.
	 * @param mixed                $value The new value.
	 */
	private static function problem( \WP_Post $post, string $key, array $args, mixed $value ): ?string {
		$resets = null === $value || ( [] === $value && empty( $args['single'] ) );

		if ( ! $resets ) {
			$valid = rest_validate_value_from_schema( $value, $args['schema'], $key );

			return is_wp_error( $valid ) ? $valid->get_error_message() : null;
		}

		if ( ! current_user_can( 'delete_post_meta', $post->ID, $key ) ) {
			/* translators: %s: field key */
			return sprintf( __( '%s can\'t be reset by your account.', 'publishio' ), $key );
		}

		$stored = ! empty( $args['single'] ) && metadata_exists( 'post', $post->ID, $key ) ? rest_validate_value_from_schema( get_post_meta( $post->ID, $key, true ), $args['schema'] ) : true;

		/* translators: %s: field key */
		return is_wp_error( $stored ) ? sprintf( __( '%s holds a value that isn\'t valid, so it can\'t be reset. Set a valid value instead.', 'publishio' ), $key ) : null;
	}

	/**
	 * The refusal for a field the user can't set, naming the ones they can.
	 *
	 * @param \WP_Post           $post      The post.
	 * @param string             $key       The field given.
	 * @param array<int, string> $available The fields the user can set.
	 */
	private static function unknown( \WP_Post $post, string $key, array $available ): \WP_Error {
		$type  = get_post_type_object( $post->post_type );
		$label = $type ? $type->labels->name : $post->post_type;

		if ( [] === $available ) {
			/* translators: %s: post type name, such as "Products" */
			return Errors::make( 'unknown_field', sprintf( __( 'You can\'t set any custom fields on %s. Leave out fields.', 'publishio' ), $label ) );
		}

		/* translators: 1: field key, 2: post type name, such as "Products", 3: comma-separated field keys */
		return Errors::make( 'unknown_field', sprintf( __( '"%1$s" isn\'t a custom field you can set on %2$s. You can set: %3$s. Nothing was saved.', 'publishio' ), $key, $label, implode( ', ', $available ) ) );
	}
}
