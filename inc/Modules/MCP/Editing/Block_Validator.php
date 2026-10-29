<?php
/**
 * Checks blocks before they are written to a post.
 *
 * @package rtCamp\Publishio\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Editing;

/**
 * Class - Block_Validator
 *
 * Runs every check on new blocks and returns all the problems found, so the AI can fix them in one retry. Markup that passes can still fail the editor's own check, which rebuilds static blocks with JavaScript; that check runs only in the editor.
 *
 * @phpstan-import-type Problem from Errors
 */
final class Block_Validator {
	private const MAX_COLLECTED = 100;

	private const VOID_TAGS = [ 'AREA', 'BASE', 'BR', 'COL', 'EMBED', 'HR', 'IMG', 'INPUT', 'LINK', 'META', 'PARAM', 'SOURCE', 'TRACK', 'WBR' ];

	private const RAW_TEXT_TAGS = [ 'IFRAME', 'NOEMBED', 'NOFRAMES', 'SCRIPT', 'STYLE', 'TEXTAREA', 'TITLE', 'XMP' ];

	private const URL_ATTRIBUTES = [ 'href', 'src', 'action', 'formaction', 'xlink:href' ];

	private const SCHEMA_TYPES = [ 'string', 'integer', 'number', 'boolean', 'array', 'object', 'null' ];

	/**
	 * The post the blocks are for, which decides the blocks allowed.
	 *
	 * @var \WP_Post
	 */
	private \WP_Post $post;

	/**
	 * The blocks allowed in the post: true for all, false for none, or a list of names.
	 *
	 * @var bool|list<string>|null
	 */
	private bool|array|null $allowed = null;

	/**
	 * The attribute forms the site's registered patterns use, as block name, attribute name and value type.
	 *
	 * @var array<string, array<string, array<string, true>>>|null
	 */
	private ?array $pattern_forms = null;

	/**
	 * The blocks the site's registered patterns put directly inside each block, as parent name and child name.
	 *
	 * @var array<string, array<string, true>>
	 */
	private array $pattern_nesting = [];

	/**
	 * Constructor.
	 *
	 * @param \WP_Post $post The post the blocks are for.
	 */
	public function __construct( \WP_Post $post ) {
		$this->post = $post;
	}

	/**
	 * A validator for a post type, when there's no post yet.
	 *
	 * @param string $post_type The post type.
	 */
	public static function for_post_type( string $post_type ): self {
		return new self(
			new \WP_Post(
				(object) [
					'ID'          => 0,
					'post_type'   => $post_type,
					'post_status' => 'draft',
					'filter'      => 'raw',
				]
			)
		);
	}

	/**
	 * Check new blocks and everything inside them.
	 *
	 * @param array<int, array<string, mixed>> $blocks    The new blocks, from Block_Tree::parse().
	 * @param array<int, string>               $ancestors The full names of the blocks they go inside, outermost first.
	 * @param bool                             $trusted   Whether the markup is a registered pattern's, which skips the HTML and attribute-name checks meant for written markup.
	 * @param array<int, Problem>              $baseline  Problems the replaced blocks already had, from check() on them. They aren't reported again, so content the AI didn't touch can't block a change.
	 *
	 * @return list<Problem>
	 */
	public function check( array $blocks, array $ancestors = [], bool $trusted = false, array $baseline = [] ): array {
		$problems = [];
		$this->visit( $blocks, $ancestors, Block_Path::root(), $trusted, $problems );

		if ( [] === $baseline ) {
			return array_values( $problems );
		}

		$known = array_flip( array_map( [ self::class, 'problem_key' ], $baseline ) );

		return array_values( array_filter( $problems, static fn ( array $problem ): bool => ! isset( $known[ self::problem_key( $problem ) ] ) ) );
	}

	/**
	 * Find blocks that may be used once per post and that the change would make appear more than once.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree $after  The post as it would be saved.
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree $before The post as it is. A duplicate it already has isn't reported.
	 *
	 * @return list<Problem>
	 */
	public function check_single_use( Block_Tree $after, Block_Tree $before ): array {
		$counts_before = self::single_use_counts( $before );
		$problems      = [];

		foreach ( self::single_use_counts( $after ) as $name => $found ) {
			if ( $found['count'] > 1 && $found['count'] > ( $counts_before[ $name ]['count'] ?? 0 ) ) {
				/* translators: %s: block name */
				$problems[] = Errors::problem( 'duplicate_not_allowed', $found['second'], sprintf( __( '%s can be used only once per post, and this change would add another. Remove one of them.', 'publishio' ), $name ) );
			}
		}

		return $problems;
	}

	/**
	 * How often each single-use block appears in a post, and where it appears a second time.
	 *
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Tree $tree The post.
	 *
	 * @return array<string, array{count: int, second: string}>
	 */
	private static function single_use_counts( Block_Tree $tree ): array {
		$counts   = [];
		$registry = \WP_Block_Type_Registry::get_instance();

		$walk = static function ( array $blocks, Block_Path $parent_path ) use ( &$walk, &$counts, $registry ): void {
			foreach ( $blocks as $i => $block ) {
				$name = Block_Tree::name_of( $block );
				$path = $parent_path->child( $i, $name );
				$type = $registry->get_registered( $name );

				if ( $type && false === ( $type->supports['multiple'] ?? true ) ) {
					$count           = ( $counts[ $name ]['count'] ?? 0 ) + 1;
					$counts[ $name ] = [
						'count'  => $count,
						'second' => 2 === $count ? $path->to_string() : ( $counts[ $name ]['second'] ?? '' ),
					];
				}

				$walk( (array) ( $block['innerBlocks'] ?? [] ), $path );
			}
		};

		$walk( $tree->blocks(), Block_Path::root() );

		return $counts;
	}

	/**
	 * What identifies a problem across positions: its code and message.
	 *
	 * @param array<string, string> $problem A problem.
	 */
	private static function problem_key( array $problem ): string {
		return $problem['code'] . '|' . $problem['message'];
	}

	/**
	 * Why a value doesn't fit a block attribute, or null if it fits.
	 *
	 * @param \WP_Block_Type $type      The block type.
	 * @param string         $attribute The attribute name.
	 * @param mixed          $value     The value.
	 */
	public static function invalid_value( \WP_Block_Type $type, string $attribute, mixed $value ): ?string {
		$definition = self::attributes_of( $type )[ $attribute ] ?? null;

		if ( ! is_array( $definition ) ) {
			return null;
		}

		$enum = isset( $definition['enum'] ) && is_array( $definition['enum'] ) ? array_values( $definition['enum'] ) : null;

		if ( ! isset( $definition['type'] ) ) {
			if ( null !== $enum && ! in_array( $value, $enum, true ) ) {
				/* translators: 1: attribute name, 2: allowed values */
				return sprintf( __( '%1$s must be one of %2$s.', 'publishio' ), $attribute, (string) wp_json_encode( $enum ) );
			}

			return null;
		}

		$types = array_map( static fn ( $t ): string => 'rich-text' === $t ? 'string' : (string) $t, (array) $definition['type'] );
		$types = array_values( array_intersect( $types, self::SCHEMA_TYPES ) );

		if ( [] === $types ) {
			return null;
		}

		$schema = [ 'type' => 1 === count( $types ) ? $types[0] : $types ];

		if ( null !== $enum ) {
			$schema['enum'] = $enum;
		}

		$valid = rest_validate_value_from_schema( $value, $schema, $attribute );

		return is_wp_error( $valid ) ? $valid->get_error_message() : null;
	}

	/**
	 * Whether the site's registered patterns use an attribute with this type of value on this block.
	 *
	 * Patterns are editor output, and the editor migrates attributes that a newer version of a block moved or retyped. Those migrations run only in JavaScript, so the registered block type doesn't list them, and the forms patterns use are accepted as they are.
	 *
	 * @param string $block     The block's full name.
	 * @param string $attribute The attribute name.
	 * @param mixed  $value     The attribute value.
	 */
	private function used_in_patterns( string $block, string $attribute, mixed $value ): bool {
		$this->index_patterns();

		return isset( $this->pattern_forms[ $block ][ $attribute ][ self::value_type( $value ) ] );
	}

	/**
	 * Whether the site's registered patterns put a block directly inside another.
	 *
	 * Some containers, such as core/navigation-submenu, set the blocks they take only in JavaScript, so the registered block types don't show that these placements are allowed.
	 *
	 * @param string $parent_name The container's full name.
	 * @param string $child       The block's full name.
	 */
	private function nested_in_patterns( string $parent_name, string $child ): bool {
		$this->index_patterns();

		return isset( $this->pattern_nesting[ $parent_name ][ $child ] );
	}

	/**
	 * Read the attribute forms and nesting of every registered pattern, once.
	 */
	private function index_patterns(): void {
		if ( null !== $this->pattern_forms ) {
			return;
		}

		$this->pattern_forms = [];

		foreach ( \WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern ) {
			$this->collect_forms( parse_blocks( (string) ( $pattern['content'] ?? '' ) ), null );
		}
	}

	/**
	 * Record the attribute forms and nesting of blocks and everything inside them.
	 *
	 * @param array<int|string, array<string, mixed>> $blocks      Parsed blocks.
	 * @param ?string                                 $parent_name The full name of the block they're in.
	 */
	private function collect_forms( array $blocks, ?string $parent_name ): void {
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? null;

			if ( ! is_string( $name ) ) {
				continue;
			}

			foreach ( (array) ( $block['attrs'] ?? [] ) as $attribute => $value ) {
				$this->pattern_forms[ $name ][ (string) $attribute ][ self::value_type( $value ) ] = true;
			}

			if ( null !== $parent_name ) {
				$this->pattern_nesting[ $parent_name ][ $name ] = true;
			}

			$this->collect_forms( (array) ( $block['innerBlocks'] ?? [] ), $name );
		}
	}

	/**
	 * A value's JSON type.
	 *
	 * @param mixed $value The value.
	 */
	private static function value_type( mixed $value ): string {
		return match ( true ) {
			is_string( $value ) => 'string',
			is_int( $value )    => 'integer',
			is_float( $value )  => 'number',
			is_bool( $value )   => 'boolean',
			null === $value     => 'null',
			is_array( $value ) && array_is_list( $value ) => 'array',
			default             => 'object',
		};
	}

	/**
	 * Check blocks at one level, then their inner blocks.
	 *
	 * @param array<int, array<string, mixed>>                 $blocks    The blocks.
	 * @param array<int, string>                               $ancestors Full names of the blocks around them, outermost first.
	 * @param \rtCamp\Publishio\Modules\MCP\Editing\Block_Path $parent_path Their parent's path within the new markup.
	 * @param bool                                             $trusted   Whether the markup is a registered pattern's.
	 * @param array<int, Problem>                              $problems  Problems found so far.
	 */
	private function visit( array $blocks, array $ancestors, Block_Path $parent_path, bool $trusted, array &$problems ): void {
		$registry = \WP_Block_Type_Registry::get_instance();

		foreach ( array_values( $blocks ) as $i => $block ) {
			if ( count( $problems ) >= self::MAX_COLLECTED ) {
				return;
			}

			$name = Block_Tree::name_of( $block );
			$path = $parent_path->child( $i, $name )->to_string();

			if ( null === ( $block['blockName'] ?? null ) ) {
				/* translators: %s: the start of the stray HTML */
				$problems[] = Errors::problem( 'freeform_html', $path, sprintf( __( 'There\'s HTML outside any block: %s. Wrap it in a block, such as core/paragraph or core/html.', 'publishio' ), self::excerpt( (string) ( $block['innerHTML'] ?? '' ) ) ) );
				continue;
			}

			$type = $registry->get_registered( $name );

			if ( ! $type ) {
				/* translators: %s: block name */
				$problems[] = Errors::problem( 'unknown_block', $path, sprintf( __( '%s isn\'t a registered block on this site. Use list-block-types to find the blocks you can use.', 'publishio' ), $name ) );
				continue;
			}

			if ( ! $this->allows( $name ) ) {
				$problems[] = Errors::problem( 'block_not_allowed', $path, $this->not_allowed_message( $name ) );
			}

			$placement = $this->placement_problem( $type, $ancestors );

			if ( null !== $placement ) {
				$problems[] = Errors::problem( 'invalid_parent', $path, $placement );
			}

			$attrs = (array) ( $block['attrs'] ?? [] );

			if ( ! $trusted ) {
				$html = self::html_problem( Block_Tree::own_html( $block ) );

				if ( null !== $html ) {
					/* translators: 1: block name, 2: the HTML problem */
					$problems[] = Errors::problem( 'malformed_html', $path, sprintf( __( 'The HTML of %1$s isn\'t well formed: %2$s', 'publishio' ), $name, $html ) );
				}

				array_push( $problems, ...$this->attribute_problems( $type, $attrs, $path ) );
			}

			$stripped = current_user_can( 'unfiltered_html' ) ? [] : array_values( array_unique( [ ...self::stripped( Block_Tree::own_html( $block ) ), ...self::stripped_from_attributes( $attrs ) ] ) );

			if ( [] !== $stripped ) {
				/* translators: %s: list of removed tags and attributes */
				$problems[] = Errors::problem( 'html_stripped', $path, sprintf( __( 'WordPress would remove %s when saving, because this account can\'t save unfiltered HTML. Leave them out.', 'publishio' ), wp_sprintf( '%l', $stripped ) ) );
			}

			if ( 'core/block' === $name ) {
				array_push( $problems, ...Pattern_Overrides::check( $block, $path ) );
				continue;
			}

			$this->visit( (array) ( $block['innerBlocks'] ?? [] ), [ ...$ancestors, $name ], $parent_path->child( $i, $name ), $trusted, $problems );
		}
	}

	/**
	 * Whether the post allows a block.
	 *
	 * @param string $name The block's full name.
	 */
	private function allows( string $name ): bool {
		if ( null === $this->allowed ) {
			$allowed       = get_allowed_block_types( new \WP_Block_Editor_Context( [ 'post' => $this->post ] ) );
			$this->allowed = is_array( $allowed ) ? array_values( array_map( 'strval', $allowed ) ) : (bool) $allowed;
		}

		return true === $this->allowed || ( is_array( $this->allowed ) && in_array( $name, $this->allowed, true ) );
	}

	/**
	 * The message for a block the post doesn't allow.
	 *
	 * @param string $name The block's full name.
	 */
	private function not_allowed_message( string $name ): string {
		$type  = get_post_type_object( $this->post->post_type );
		$label = $type ? $type->labels->name : $this->post->post_type;

		/* translators: 1: block name, 2: post type name, 3: post type slug */
		return sprintf( __( '%1$s isn\'t allowed in %2$s. Call list-block-types with post_type: %3$s to see what is.', 'publishio' ), $name, $label, $this->post->post_type );
	}

	/**
	 * Why a block can't go where it is, or null if it can.
	 *
	 * As in the editor, a block may go directly inside a container that its parent list names or whose allowed blocks name it; either one is enough when both are set. Wherever the site's registered patterns put it is also accepted.
	 *
	 * @param \WP_Block_Type     $type      The block type.
	 * @param array<int, string> $ancestors Full names of the blocks around it, outermost first.
	 */
	private function placement_problem( \WP_Block_Type $type, array $ancestors ): ?string {
		$parent = [] === $ancestors ? null : $ancestors[ count( $ancestors ) - 1 ];

		if ( null !== $parent && $this->nested_in_patterns( $parent, $type->name ) ) {
			return null;
		}

		$parent_type    = null === $parent ? null : \WP_Block_Type_Registry::get_instance()->get_registered( $parent );
		$parent_takes   = $parent_type && is_array( $parent_type->allowed_blocks ) ? in_array( $type->name, $parent_type->allowed_blocks, true ) : null;
		$parent_listed  = is_array( $type->parent ) && [] !== $type->parent ? in_array( $parent, $type->parent, true ) : null;
		$parent_allowed = null === $parent_takes || null === $parent_listed ? ( $parent_takes ?? $parent_listed ?? true ) : ( $parent_takes || $parent_listed );

		if ( ! $parent_allowed && false === $parent_listed ) {
			/* translators: 1: block name, 2: allowed parents, 3: short name of the first allowed parent */
			return sprintf( __( '%1$s must be directly inside %2$s. Wrap it in a %3$s block.', 'publishio' ), $type->name, wp_sprintf( '%l', (array) $type->parent ), Block_Path::short_name( (string) ( (array) $type->parent )[0] ) );
		}

		if ( ! $parent_allowed && $parent_type ) {
			/* translators: 1: block name, 2: parent block name, 3: the blocks the parent takes */
			return sprintf( __( '%1$s can\'t go inside %2$s, which takes only %3$s.', 'publishio' ), $type->name, $parent_type->name, wp_sprintf( '%l', (array) $parent_type->allowed_blocks ) );
		}

		if ( is_array( $type->ancestor ) && [] !== $type->ancestor && [] === array_intersect( $type->ancestor, $ancestors ) ) {
			/* translators: 1: block name, 2: allowed ancestors */
			return sprintf( __( '%1$s must be somewhere inside %2$s.', 'publishio' ), $type->name, wp_sprintf( '%l', $type->ancestor ) );
		}

		return null;
	}

	/**
	 * Problems with a block's comment attributes.
	 *
	 * @param \WP_Block_Type       $type  The block type.
	 * @param array<string, mixed> $attrs The attributes in the block comment.
	 * @param string               $path  Where the block is.
	 *
	 * @return list<Problem>
	 */
	private function attribute_problems( \WP_Block_Type $type, array $attrs, string $path ): array {
		$definitions = self::attributes_of( $type );
		$problems    = [];

		foreach ( $attrs as $attribute => $value ) {
			$attribute  = (string) $attribute;
			$definition = $definitions[ $attribute ] ?? null;

			if ( $this->used_in_patterns( $type->name, $attribute, $value ) ) {
				continue;
			}

			if ( ! is_array( $definition ) ) {
				$suggestion = self::suggestion( $attribute, array_keys( $definitions ) );
				$problems[] = Errors::problem(
					'invalid_attribute',
					$path,
					null === $suggestion
						/* translators: 1: block name, 2: attribute name */
						? sprintf( __( '%1$s has no attribute "%2$s". Call get-block-type with name: %1$s for its attributes.', 'publishio' ), $type->name, $attribute )
						/* translators: 1: block name, 2: attribute name, 3: suggested attribute */
						: sprintf( __( '%1$s has no attribute "%2$s". Did you mean "%3$s"?', 'publishio' ), $type->name, $attribute, $suggestion )
				);
				continue;
			}

			if ( is_string( $definition['source'] ?? null ) && 'meta' !== $definition['source'] ) {
				/* translators: 1: attribute name, 2: block name, 3: where in the HTML it belongs */
				$problems[] = Errors::problem( 'attribute_in_html', $path, sprintf( __( '"%1$s" on %2$s belongs in the block\'s HTML (%3$s), not in the block comment. Remove it from the comment.', 'publishio' ), $attribute, $type->name, self::source_hint( $definition ) ) );
				continue;
			}

			$invalid = self::invalid_value( $type, $attribute, $value );

			if ( null !== $invalid ) {
				/* translators: 1: block name, 2: the reason */
				$problems[] = Errors::problem( 'invalid_attribute', $path, sprintf( __( '%1$s: %2$s', 'publishio' ), $type->name, $invalid ) );
			}
		}

		return $problems;
	}

	/**
	 * A block type's attributes, including the ones every block has.
	 *
	 * @param \WP_Block_Type $type The block type.
	 *
	 * @return array<string, mixed>
	 */
	private static function attributes_of( \WP_Block_Type $type ): array {
		return (array) $type->get_attributes() + \WP_Block_Type::GLOBAL_ATTRIBUTES;
	}

	/**
	 * Where a sourced attribute lives in the HTML.
	 *
	 * @param array<string, mixed> $definition The attribute definition.
	 */
	private static function source_hint( array $definition ): string {
		$selector = is_string( $definition['selector'] ?? null ) ? $definition['selector'] : '';

		if ( 'attribute' === $definition['source'] && is_string( $definition['attribute'] ?? null ) ) {
			/* translators: 1: CSS selector, 2: HTML attribute */
			return '' === $selector ? sprintf( __( 'its %s attribute', 'publishio' ), $definition['attribute'] ) : sprintf( __( 'the %2$s of %1$s', 'publishio' ), $selector, $definition['attribute'] );
		}

		/* translators: %s: CSS selector */
		return '' === $selector ? __( 'its content', 'publishio' ) : sprintf( __( 'the content of %s', 'publishio' ), $selector );
	}

	/**
	 * The closest known attribute name to one that doesn't exist.
	 *
	 * @param string             $attribute The attribute given.
	 * @param array<int, string> $names     The block's attribute names.
	 */
	private static function suggestion( string $attribute, array $names ): ?string {
		$given = strtolower( $attribute );
		$best  = null;
		$score = PHP_INT_MAX;

		foreach ( $names as $name ) {
			$candidate = strtolower( (string) $name );
			$distance  = strlen( $given ) >= 3 && str_contains( $candidate, $given ) ? 0 : levenshtein( $given, $candidate );

			if ( $distance < $score ) {
				$score = $distance;
				$best  = (string) $name;
			}
		}

		return $score <= 2 ? $best : null;
	}

	/**
	 * What is wrong with a block's own HTML, or null if its tags are balanced.
	 *
	 * @param string $html The block's own HTML.
	 */
	private static function html_problem( string $html ): ?string {
		if ( '' === trim( $html ) ) {
			return null;
		}

		$processor = new \WP_HTML_Tag_Processor( $html );
		$open      = [];

		while ( $processor->next_token() ) {
			if ( '#tag' !== $processor->get_token_type() ) {
				continue;
			}

			$tag = (string) $processor->get_tag();

			if ( in_array( $tag, self::VOID_TAGS, true ) || in_array( $tag, self::RAW_TEXT_TAGS, true ) ) {
				continue;
			}

			if ( $processor->is_tag_closer() ) {
				$top = array_pop( $open );

				if ( null === $top ) {
					/* translators: %s: tag name */
					return sprintf( __( '</%s> has no opening tag.', 'publishio' ), strtolower( $tag ) );
				}

				if ( $top !== $tag ) {
					/* translators: 1: closing tag name, 2: open tag name */
					return sprintf( __( '</%1$s> closes <%2$s>, which is still open.', 'publishio' ), strtolower( $tag ), strtolower( $top ) );
				}

				continue;
			}

			if ( ! $processor->has_self_closing_flag() ) {
				$open[] = $tag;
			}
		}

		if ( $processor->paused_at_incomplete_token() ) {
			return __( 'it ends in the middle of a tag or comment.', 'publishio' );
		}

		if ( [] !== $open ) {
			/* translators: %s: tag name */
			return sprintf( __( '<%s> is never closed.', 'publishio' ), strtolower( $open[ count( $open ) - 1 ] ) );
		}

		return null;
	}

	/**
	 * The tags and attributes kses would remove from HTML in a block's attribute values, such as override text.
	 *
	 * @param array<string, mixed> $attrs The block's attributes.
	 *
	 * @return list<string>
	 */
	private static function stripped_from_attributes( array $attrs ): array {
		$items = [];

		array_walk_recursive(
			$attrs,
			static function ( mixed $value ) use ( &$items ): void {
				if ( is_string( $value ) && str_contains( $value, '<' ) ) {
					array_push( $items, ...self::stripped( $value ) );
				}
			}
		);

		return $items;
	}

	/**
	 * The tags and attributes kses would remove from HTML.
	 *
	 * @param string $html A block's own HTML.
	 *
	 * @return list<string>
	 */
	private static function stripped( string $html ): array {
		if ( '' === trim( $html ) ) {
			return [];
		}

		return array_values( array_diff( self::inventory( $html ), self::inventory( wp_kses_post( $html ) ) ) );
	}

	/**
	 * Every tag and attribute in HTML, with the values of URL attributes.
	 *
	 * @param string $html Some HTML.
	 *
	 * @return list<string>
	 */
	private static function inventory( string $html ): array {
		$processor = new \WP_HTML_Tag_Processor( $html );
		$items     = [];

		while ( $processor->next_tag( [ 'tag_closers' => 'skip' ] ) ) {
			$tag     = strtolower( (string) $processor->get_tag() );
			$items[] = '<' . $tag . '>';

			foreach ( (array) $processor->get_attribute_names_with_prefix( '' ) as $attribute ) {
				$value   = $processor->get_attribute( $attribute );
				$items[] = in_array( $attribute, self::URL_ATTRIBUTES, true ) && is_string( $value )
					/* translators: 1: attribute name, 2: its value, 3: tag name */
					? sprintf( __( '%1$s="%2$s" on <%3$s>', 'publishio' ), $attribute, $value, $tag )
					/* translators: 1: attribute name, 2: tag name */
					: sprintf( __( 'the %1$s attribute on <%2$s>', 'publishio' ), $attribute, $tag );
			}
		}

		return array_values( array_unique( $items ) );
	}

	/**
	 * The start of some HTML, for messages.
	 *
	 * @param string $html The HTML.
	 */
	private static function excerpt( string $html ): string {
		$html = trim( (string) preg_replace( '/\s+/', ' ', $html ) );

		return '"' . ( mb_strlen( $html ) > 60 ? mb_substr( $html, 0, 60 ) . '…' : $html ) . '"';
	}
}
