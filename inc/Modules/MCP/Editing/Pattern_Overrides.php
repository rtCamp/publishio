<?php
/**
 * The overridable fields of a synced pattern.
 *
 * @package rtCamp\Publishio\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Editing;

/**
 * Class - Pattern_Overrides
 *
 * In a synced pattern, blocks with "Allow overrides" turned on have a name in `metadata.name` and a `core/pattern-overrides` binding. A post sets values for them in the reference's `content` attribute, keyed by that name and then by attribute.
 *
 * @phpstan-import-type Problem from Errors
 */
final class Pattern_Overrides {
	private const SOURCE = 'core/pattern-overrides';

	/**
	 * The override fields of a synced pattern.
	 *
	 * @param int $ref The wp_block post ID.
	 *
	 * @return array<string, array{block: string, attributes: list<string>}>|\WP_Error
	 */
	public static function fields( int $ref ): array|\WP_Error {
		$pattern = self::pattern( $ref );

		if ( is_wp_error( $pattern ) ) {
			return $pattern;
		}

		$fields = [];
		self::collect( parse_blocks( $pattern->post_content ), $fields );

		return $fields;
	}

	/**
	 * The values a reference sets, as override name to attribute values.
	 *
	 * @param array<string, mixed> $block A core/block block.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function values( array $block ): array {
		$content = $block['attrs']['content'] ?? [];

		return is_array( $content ) ? array_filter( $content, 'is_array' ) : [];
	}

	/**
	 * Check a reference's pattern and override values.
	 *
	 * @param array<string, mixed> $block A core/block block.
	 * @param string               $path  Where the block is, for messages.
	 *
	 * @return list<Problem>
	 */
	public static function check( array $block, string $path ): array {
		$ref = $block['attrs']['ref'] ?? null;

		if ( ! is_int( $ref ) || $ref < 1 ) {
			return [ Errors::problem( 'unknown_pattern', $path, __( 'core/block needs a "ref" attribute with the ID of a synced pattern. Use list-patterns with synced: true to find one.', 'publishio' ) ) ];
		}

		$fields = self::fields( $ref );

		if ( is_wp_error( $fields ) ) {
			return [ Errors::problem( 'unknown_pattern', $path, (string) preg_replace( '/^[a-z_]+: /', '', $fields->get_error_message() ) ) ];
		}

		$pattern = get_post( $ref );

		if ( ! $pattern instanceof \WP_Post || 'publish' !== $pattern->post_status || '' !== $pattern->post_password ) {
			/* translators: %s: pattern title */
			return [ Errors::problem( 'unknown_pattern', $path, sprintf( __( 'The synced pattern "%s" isn\'t published, or has a password, so it would show nothing on the page. Publish it first or use another pattern.', 'publishio' ), self::title( $ref ) ) ) ];
		}

		if ( ! array_key_exists( 'content', $block['attrs'] ) ) {
			return [];
		}

		$content = $block['attrs']['content'];
		$title   = self::title( $ref );

		if ( ! is_array( $content ) || ( [] !== $content && array_is_list( $content ) ) ) {
			return [ Errors::problem( 'unknown_override', $path, __( 'content must be an object keyed by override name, such as {"CTA heading":{"content":"Ready?"}}.', 'publishio' ) ) ];
		}

		$problems = [];

		foreach ( $content as $name => $values ) {
			$name = (string) $name;

			if ( ! isset( $fields[ $name ] ) ) {
				$problems[] = Errors::problem( 'unknown_override', $path, self::unknown_name_message( $name, $title, array_keys( $fields ) ) );
				continue;
			}

			if ( ! is_array( $values ) || ( [] !== $values && array_is_list( $values ) ) ) {
				/* translators: %s: override name */
				$problems[] = Errors::problem( 'unknown_override', $path, sprintf( __( 'The value for "%s" must be an object keyed by attribute, such as {"content":"Ready?"}.', 'publishio' ), $name ) );
				continue;
			}

			$field = $fields[ $name ];
			$type  = \WP_Block_Type_Registry::get_instance()->get_registered( $field['block'] );

			foreach ( $values as $attribute => $value ) {
				$attribute = (string) $attribute;

				if ( ! in_array( $attribute, $field['attributes'], true ) ) {
					/* translators: 1: attribute name, 2: override name, 3: block name, 4: list of attributes */
					$problems[] = Errors::problem( 'unknown_override', $path, sprintf( __( '"%1$s" can\'t be overridden on "%2$s" (%3$s). It supports %4$s.', 'publishio' ), $attribute, $name, $field['block'], self::quoted_list( $field['attributes'] ) ) );
					continue;
				}

				$invalid = $type ? Block_Validator::invalid_value( $type, $attribute, $value ) : null;

				if ( null !== $invalid ) {
					/* translators: 1: override name, 2: the reason */
					$problems[] = Errors::problem( 'unknown_override', $path, sprintf( __( 'The value for "%1$s": %2$s', 'publishio' ), $name, $invalid ) );
				}
			}
		}

		return $problems;
	}

	/**
	 * A synced pattern's title, or a placeholder when the current user can't read it.
	 *
	 * @param int $ref The wp_block post ID.
	 */
	public static function title( int $ref ): string {
		if ( $ref < 1 || 'wp_block' !== get_post_type( $ref ) || ! current_user_can( 'read_post', $ref ) ) {
			return __( 'Unavailable pattern', 'publishio' );
		}

		$title = wp_strip_all_tags( get_the_title( $ref ) );

		return '' === $title ? __( 'Untitled pattern', 'publishio' ) : $title;
	}

	/**
	 * The pattern a reference points to, if the current user can read it.
	 *
	 * @param int $ref The wp_block post ID.
	 */
	private static function pattern( int $ref ): \WP_Post|\WP_Error {
		$pattern = get_post( $ref );

		if ( ! $pattern instanceof \WP_Post || 'wp_block' !== $pattern->post_type || 'trash' === $pattern->post_status ) {
			/* translators: %d: post ID */
			return Errors::make( 'unknown_pattern', sprintf( __( 'There\'s no synced pattern with ID %d. Use list-patterns with synced: true to find one.', 'publishio' ), $ref ), 404 );
		}

		if ( ! current_user_can( 'read_post', $ref ) ) {
			/* translators: %d: post ID */
			return Errors::make( 'unknown_pattern', sprintf( __( 'Your account can\'t read the synced pattern with ID %d.', 'publishio' ), $ref ), 403 );
		}

		return $pattern;
	}

	/**
	 * Collect override fields from a pattern's blocks, depth first.
	 *
	 * @param array<int|string, array<string, mixed>>                       $blocks Parsed blocks.
	 * @param array<string, array{block: string, attributes: list<string>}> $fields The fields found so far.
	 */
	private static function collect( array $blocks, array &$fields ): void {
		foreach ( $blocks as $block ) {
			$name     = $block['blockName'] ?? null;
			$metadata = $block['attrs']['metadata'] ?? [];

			if ( is_string( $name ) && is_array( $metadata ) && is_string( $metadata['name'] ?? null ) && '' !== $metadata['name'] ) {
				$attributes = self::bound_attributes( $name, (array) ( $metadata['bindings'] ?? [] ) );

				if ( [] !== $attributes ) {
					$fields[ $metadata['name'] ] = [
						'block'      => $name,
						'attributes' => $attributes,
					];
				}
			}

			self::collect( (array) ( $block['innerBlocks'] ?? [] ), $fields );
		}
	}

	/**
	 * The attributes a block's bindings open to overrides.
	 *
	 * @param string               $name     The block's name.
	 * @param array<string, mixed> $bindings The block's metadata.bindings.
	 *
	 * @return list<string>
	 */
	private static function bound_attributes( string $name, array $bindings ): array {
		$supported = get_block_bindings_supported_attributes( $name );

		if ( self::SOURCE === ( $bindings['__default']['source'] ?? null ) ) {
			return array_values( $supported );
		}

		$bound = [];

		foreach ( $bindings as $attribute => $binding ) {
			if ( is_array( $binding ) && self::SOURCE === ( $binding['source'] ?? null ) && in_array( $attribute, $supported, true ) ) {
				$bound[] = (string) $attribute;
			}
		}

		return $bound;
	}

	/**
	 * The message for an override name the pattern doesn't have.
	 *
	 * @param string             $name  The name given.
	 * @param string             $title The pattern's title.
	 * @param array<int, string> $names The pattern's override names.
	 */
	private static function unknown_name_message( string $name, string $title, array $names ): string {
		if ( [] === $names ) {
			/* translators: 1: override name, 2: pattern title */
			return sprintf( __( '"%1$s" isn\'t an override field of "%2$s". It has no override fields, so its content can\'t change per post.', 'publishio' ), $name, $title );
		}

		/* translators: 1: override name, 2: pattern title, 3: list of override names */
		return sprintf( __( '"%1$s" isn\'t an override field of "%2$s". Its fields are %3$s.', 'publishio' ), $name, $title, self::quoted_list( $names ) );
	}

	/**
	 * Quoted names joined for a sentence.
	 *
	 * @param array<int, string> $names The names.
	 */
	private static function quoted_list( array $names ): string {
		return wp_sprintf( '%l', array_map( static fn ( string $name ): string => '"' . $name . '"', $names ) );
	}
}
