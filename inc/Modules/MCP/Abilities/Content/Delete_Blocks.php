<?php
/**
 * Delete blocks ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Content
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Content;

use rtCamp\Publishio\Modules\MCP\Abilities\Schema;
use rtCamp\Publishio\Modules\MCP\Editing\Block_Path;

/**
 * Class - Delete_Blocks
 */
final class Delete_Blocks extends Block_Edit_Ability {
	public const MAX_PATHS = 50;

	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'delete-blocks';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Delete Blocks', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Removes one or more blocks, with everything inside them. All paths refer to the post as it is before the call, and every path is checked before anything is removed.', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'required'             => [ 'post_id', 'paths' ],
			'properties'           => [
				'post_id'        => Schema::post_id(),
				'paths'          => [
					'type'        => 'array',
					'minItems'    => 1,
					'maxItems'    => self::MAX_PATHS,
					'uniqueItems' => true,
					'description' => 'The blocks to remove.',
					'items'       => Schema::path( 'A block to remove.' ),
				],
				'approval_token' => Schema::approval_token(),
			],
			'additionalProperties' => false,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function annotations(): array {
		return [
			'readonly'    => false,
			'destructive' => true,
			'idempotent'  => false,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute( array $input ): array|\WP_Error {
		$opened = self::open( $input );

		if ( is_wp_error( $opened ) ) {
			return $opened;
		}

		$paths = [];

		foreach ( (array) ( $input['paths'] ?? [] ) as $value ) {
			$path = self::path( $value );

			if ( is_wp_error( $path ) ) {
				return $path;
			}

			$paths[] = $path;
		}

		$tree    = $opened['tree'];
		$removed = $tree->remove( $paths );

		if ( is_wp_error( $removed ) ) {
			return $removed;
		}

		$scope = self::scope( array_map( static fn ( Block_Path $path ): Block_Path => $path->parent(), $paths ) );

		return self::finish( $opened['post'], $tree, $input, [], $scope );
	}
}
