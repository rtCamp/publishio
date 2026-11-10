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
	 * A post's address.
	 *
	 * @return array<string, mixed>
	 */
	public static function post_url(): array {
		return [
			'type'        => [ 'string', 'null' ],
			'description' => 'Null for post types visitors can\'t view: share edit_url instead.',
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
	 * A post's custom fields, from Post_Fields::describe().
	 *
	 * @return array<string, mixed>
	 */
	public static function custom_fields(): array {
		return [
			'type'                 => 'object',
			'description'          => 'The custom fields update-post can set, by key, such as a product\'s price: each with its value, its type and, when the site gives them, a title, description and allowed values (enum). A block bound to a field (core/post-meta) shows its value. Left out when there are none, and when get-post is called with path.',
			'additionalProperties' => [
				'type'       => 'object',
				'required'   => [ 'value', 'type' ],
				'properties' => [
					'value'       => self::any_value(),
					'type'        => [ 'type' => 'string' ],
					'title'       => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
					'enum'        => [ 'type' => 'array' ],
				],
			],
		];
	}

	/**
	 * Custom field values by key, as update-post takes and returns them.
	 *
	 * @param string $description What the values are.
	 *
	 * @return array<string, mixed>
	 */
	public static function field_values( string $description ): array {
		return [
			'type'                 => 'object',
			'description'          => $description,
			'additionalProperties' => self::any_value(),
		];
	}

	/**
	 * Any JSON value, for a custom field whose type its own schema gives.
	 *
	 * @return array<string, mixed>
	 */
	private static function any_value(): array {
		return [ 'type' => [ 'string', 'number', 'integer', 'boolean', 'array', 'object', 'null' ] ];
	}

	/**
	 * A post's or block's outline, from Outline::of().
	 *
	 * @return array<string, mixed>
	 */
	public static function outline(): array {
		return [
			'type'        => 'object',
			'description' => 'Each block with its path and a short text. Use these paths in the next call. After a change at the top level of the post, it lists the post\'s top-level blocks and every block inside the ones that changed.',
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
							'bindings'  => [
								'type'                 => 'object',
								'description'          => 'Bound attributes and where their values come from, such as "core/post-meta: price".',
								'additionalProperties' => [ 'type' => 'string' ],
							],
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
					'description' => 'Whether rows were left out. Call get-post with path to see the rows inside one block.',
				],
			],
		];
	}
}
