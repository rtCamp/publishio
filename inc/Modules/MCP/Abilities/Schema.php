<?php
/**
 * JSON schema parts shared by the abilities.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities;

use rtCamp\Publishio\Modules\MCP\Editing\Block_Path;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Tree;

/**
 * Class - Schema
 */
final class Schema {
	public const MAX_MARKUP = 200000;

	/**
	 * A post ID.
	 *
	 * @param string $description What the post is.
	 *
	 * @return array<string, mixed>
	 */
	public static function post_id( string $description = 'ID of the post, page or other item.' ): array {
		return [
			'type'        => 'integer',
			'minimum'     => 1,
			'description' => $description,
		];
	}

	/**
	 * A block path.
	 *
	 * @param string $description What the path points to.
	 *
	 * @return array<string, mixed>
	 */
	public static function path( string $description ): array {
		return [
			'type'        => 'string',
			'minLength'   => 3,
			'maxLength'   => Block_Path::MAX_LENGTH,
			'description' => $description . ' A path from the latest outline, such as "1:columns > 0:column > 2:paragraph".',
		];
	}

	/**
	 * Where new or moved blocks go relative to a target.
	 *
	 * @return array<string, mixed>
	 */
	public static function placement(): array {
		return [
			'type'        => 'string',
			'enum'        => Block_Tree::PLACEMENTS,
			'description' => 'before or after the target, or at the start or end inside it. Without a target, start or end of the post.',
		];
	}

	/**
	 * Serialized block markup.
	 *
	 * @param string $description What the markup is.
	 *
	 * @return array<string, mixed>
	 */
	public static function markup( string $description ): array {
		return [
			'type'        => 'string',
			'minLength'   => 1,
			'maxLength'   => self::MAX_MARKUP,
			'description' => $description,
		];
	}

	/**
	 * A pattern name from list-patterns.
	 *
	 * @return array<string, mixed>
	 */
	public static function pattern_name(): array {
		return [
			'type'        => 'string',
			'minLength'   => 1,
			'maxLength'   => 200,
			'description' => 'The pattern\'s name from list-patterns, such as "twentytwentyfive/hero" or "core/block/12" for a pattern saved in the pattern library.',
		];
	}

	/**
	 * The token a live_post_needs_approval refusal gives.
	 *
	 * @return array<string, mixed>
	 */
	public static function approval_token(): array {
		return [
			'type'        => 'string',
			'maxLength'   => 100,
			'description' => 'Only for published, scheduled or private posts: the token from a live_post_needs_approval refusal, sent after the user agreed to the change.',
		];
	}

	/**
	 * A post's or block's outline, from Outline::of().
	 *
	 * @return array<string, mixed>
	 */
	public static function outline(): array {
		return [
			'type'        => 'object',
			'description' => 'Each block with its path and a short text. Use these paths in the next call.',
			'required'    => [ 'rows', 'truncated' ],
			'properties'  => [
				'rows'      => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'required'   => [ 'path', 'text' ],
						'properties' => [
							'path'      => [ 'type' => 'string' ],
							'text'      => [ 'type' => 'string' ],
							'overrides' => [
								'type'        => 'array',
								'description' => 'A synced pattern\'s override fields and the values this post sets. Change them by replacing the pattern block with new values in its content attribute.',
								'items'       => [
									'type'       => 'object',
									'required'   => [ 'name', 'block', 'attributes', 'values' ],
									'properties' => [
										'name'       => [ 'type' => 'string' ],
										'block'      => [ 'type' => 'string' ],
										'attributes' => [
											'type'  => 'array',
											'items' => [ 'type' => 'string' ],
										],
										'values'     => [ 'type' => 'object' ],
									],
								],
							],
						],
					],
				],
				'truncated' => [
					'type'        => 'boolean',
					'description' => 'Whether rows were left out. Get the outline under a specific block to see the rest.',
				],
			],
		];
	}
}
