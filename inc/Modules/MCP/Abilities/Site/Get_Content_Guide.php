<?php
/**
 * Get content guide ability.
 *
 * @package rtCamp\Publishio\Modules\MCP\Abilities\Site
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Abilities\Site;

use rtCamp\Publishio\Modules\MCP\Abilities\Ability;
use rtCamp\Publishio\Modules\MCP\Resources\Content_Guide\Content_Guide;
use rtCamp\Publishio\Modules\Settings\Pages\Guide_Page;

/**
 * Class - Get_Content_Guide
 */
final class Get_Content_Guide extends Ability {
	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'get-content-guide';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Get Content Guide', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Call before creating or editing any content. Returns the Publishio content guide: how to plan, build and finish content on this site. If the user wants to install the guide as a skill, send them to guide_page_url, where they can download SKILL.md. Never write the file yourself.', 'publishio' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema(): array {
		return [
			'type'       => 'object',
			'required'   => [ 'guide', 'guide_page_url' ],
			'properties' => [
				'guide'          => [ 'type' => 'string' ],
				'guide_page_url' => [ 'type' => 'string' ],
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
	protected function execute( array $input ): array { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter -- No input.
		return [
			'guide'          => Content_Guide::guide(),
			'guide_page_url' => admin_url( 'admin.php?page=' . Guide_Page::SLUG ),
		];
	}
}
