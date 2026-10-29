<?php
/**
 * Base class for Publishio abilities.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities;

use rtCamp\Publishio\Modules\MCP\Abilities\Categories\Posts as Posts_Category;

/**
 * Class - Ability
 *
 * A subclass declares its name, schemas, permission check and execute(); register() turns them into one Abilities API registration that the MCP server lists as a tool.
 */
abstract class Ability {
	public const PREFIX = 'publishio/';

	/**
	 * The ability name without the `publishio/` prefix, such as `insert-blocks`.
	 *
	 * @return non-empty-lowercase-string
	 */
	abstract protected function name(): string;

	/**
	 * A short human-readable label.
	 */
	abstract protected function label(): string;

	/**
	 * What the tool does and when to call it, written for the AI.
	 */
	abstract protected function description(): string;

	/**
	 * The JSON schema of the input.
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function input_schema(): array;

	/**
	 * The JSON schema of the result.
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function output_schema(): array;

	/**
	 * Run the ability.
	 *
	 * @param array<string, mixed> $input The validated input.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	abstract protected function execute( array $input ): array|\WP_Error;

	/**
	 * The ability category.
	 */
	protected function category(): string {
		return Posts_Category::SLUG;
	}

	/**
	 * Whether the current user may run the ability at all.
	 *
	 * Return a plain boolean: WordPress replaces a WP_Error from a permission callback with a generic message, so specific refusals, such as a post the user can't edit, come from execute() before anything changes.
	 *
	 * @param array<string, mixed> $input The validated input.
	 */
	protected function permission( array $input ): bool { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter, Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Subclasses check the input.
		return current_user_can( 'edit_posts' );
	}

	/**
	 * The behavior hints. Read-only connections can run only readonly abilities that aren't destructive.
	 *
	 * @return array{readonly: bool, destructive: bool, idempotent: bool}
	 */
	protected function annotations(): array {
		return [
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => false,
		];
	}

	/**
	 * Extra MCP `_meta` for the tool, such as the preview app it opens.
	 *
	 * @return array<string, mixed>
	 */
	protected function mcp_meta(): array {
		return [];
	}

	/**
	 * Register the ability.
	 */
	public function register(): void {
		$mcp  = [ 'public' => true ];
		$meta = $this->mcp_meta();

		if ( [] !== $meta ) {
			$mcp['_meta'] = $meta;
		}

		wp_register_ability(
			self::PREFIX . $this->name(),
			[
				'label'               => $this->label(),
				'category'            => $this->category(),
				'description'         => $this->description(),
				'input_schema'        => $this->input_schema(),
				'output_schema'       => $this->output_schema(),
				'permission_callback' => fn ( mixed $input = null ): bool => $this->permission( is_array( $input ) ? $input : [] ),
				'execute_callback'    => fn ( mixed $input = null ): array|\WP_Error => $this->execute( is_array( $input ) ? $input : [] ),
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => $this->annotations(),
					'mcp'          => $mcp,
				],
			]
		);
	}
}
