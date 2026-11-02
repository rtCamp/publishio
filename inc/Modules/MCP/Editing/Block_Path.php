<?php
/**
 * A path to one block in a post, such as `1:columns > 0:column > 2:paragraph`.
 *
 * @package rtCamp\Publishio\Modules\MCP\Editing
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Editing;

/**
 * Class - Block_Path
 *
 * Each step is the block's zero-based position among its parent's blocks and its name. The name is a check: a path whose name doesn't match the block at that position is refused, so a stale path fails instead of editing the wrong block. Names without a namespace mean `core/`, since WordPress requires a namespace for every other block.
 */
final class Block_Path {
	public const MAX_DEPTH  = 24;
	public const MAX_LENGTH = 1024;
	public const SEPARATOR  = ' > ';

	private const STEP_PATTERN = '/^(\d{1,4}):((?:[a-z0-9-]+\/)?[a-z0-9-]+)$/';

	/**
	 * The steps, outermost first.
	 *
	 * @var list<array{index: int, name: string}>
	 */
	private array $steps;

	/**
	 * Constructor.
	 *
	 * @param array<int, array{index: int, name: string}> $steps The steps, outermost first, with full block names.
	 */
	private function __construct( array $steps ) {
		$this->steps = array_values( $steps );
	}

	/**
	 * The path of the post itself, which contains the top-level blocks.
	 */
	public static function root(): self {
		return new self( [] );
	}

	/**
	 * Parse a path string.
	 *
	 * @param string $path The path, such as `1:columns > 0:column`.
	 */
	public static function parse( string $path ): self|\WP_Error {
		$path = trim( $path );

		if ( '' === $path || strlen( $path ) > self::MAX_LENGTH ) {
			return Errors::invalid_path( $path, __( 'A path is one or more index:name steps joined by " > ", such as "1:columns > 0:column".', 'publishio' ) );
		}

		$parts = array_map( 'trim', explode( '>', $path ) );

		if ( count( $parts ) > self::MAX_DEPTH ) {
			/* translators: %d: maximum number of steps */
			return Errors::invalid_path( $path, sprintf( __( 'A path can have at most %d steps.', 'publishio' ), self::MAX_DEPTH ) );
		}

		$steps = [];

		foreach ( $parts as $part ) {
			if ( ! preg_match( self::STEP_PATTERN, $part, $matches ) ) {
				/* translators: %s: the step that failed to parse */
				return Errors::invalid_path( $path, sprintf( __( '"%s" isn\'t a step. Each step is a position and a block name, such as "0:heading" or "2:acme/card".', 'publishio' ), $part ) );
			}

			$steps[] = [
				'index' => (int) $matches[1],
				'name'  => self::full_name( $matches[2] ),
			];
		}

		return new self( $steps );
	}

	/**
	 * The block name with `core/` added when it has no namespace.
	 *
	 * @param string $name A block name, such as `heading` or `acme/card`.
	 */
	public static function full_name( string $name ): string {
		return str_contains( $name, '/' ) ? $name : 'core/' . $name;
	}

	/**
	 * The block name as paths show it, without `core/`.
	 *
	 * @param string $name A full block name.
	 */
	public static function short_name( string $name ): string {
		return str_starts_with( $name, 'core/' ) ? substr( $name, 5 ) : $name;
	}

	/**
	 * The steps, outermost first.
	 *
	 * @return list<array{index: int, name: string}>
	 */
	public function steps(): array {
		return $this->steps;
	}

	/**
	 * Whether this is the post itself.
	 */
	public function is_root(): bool {
		return [] === $this->steps;
	}

	/**
	 * The number of steps.
	 */
	public function depth(): int {
		return count( $this->steps );
	}

	/**
	 * The path of the block that contains this one.
	 */
	public function parent(): self {
		return new self( array_slice( $this->steps, 0, -1 ) );
	}

	/**
	 * The last step, or null for the root.
	 *
	 * @return array{index: int, name: string}|null
	 */
	public function last(): ?array {
		return [] === $this->steps ? null : $this->steps[ count( $this->steps ) - 1 ];
	}

	/**
	 * The block's position among its parent's blocks, or -1 for the root.
	 */
	public function index(): int {
		$last = $this->last();

		return null === $last ? -1 : $last['index'];
	}

	/**
	 * The path of a child of this block.
	 *
	 * @param int    $index The child's position.
	 * @param string $name  The child's full block name.
	 */
	public function child( int $index, string $name ): self {
		$steps   = $this->steps;
		$steps[] = [
			'index' => $index,
			'name'  => $name,
		];

		return new self( $steps );
	}

	/**
	 * The same path with the step at a depth moved to another position.
	 *
	 * @param int $depth The zero-based depth of the step to change.
	 * @param int $index The new position.
	 */
	public function with_index_at( int $depth, int $index ): self {
		$steps = array_map(
			static fn ( array $step, int $i ): array => [
				'index' => $i === $depth ? $index : $step['index'],
				'name'  => $step['name'],
			],
			$this->steps,
			array_keys( $this->steps )
		);

		return new self( $steps );
	}

	/**
	 * Whether this path is the other path or inside it.
	 *
	 * @param self $other The possible ancestor.
	 */
	public function is_within( self $other ): bool {
		if ( $other->depth() > $this->depth() ) {
			return false;
		}

		foreach ( $other->steps as $i => $step ) {
			if ( $this->steps[ $i ]['index'] !== $step['index'] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The path as text, with short names.
	 */
	public function to_string(): string {
		return implode(
			self::SEPARATOR,
			array_map(
				static fn ( array $step ): string => $step['index'] . ':' . self::short_name( $step['name'] ),
				$this->steps
			)
		);
	}
}
