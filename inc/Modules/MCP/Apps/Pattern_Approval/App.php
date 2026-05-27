<?php
/**
 * Pattern Approval MCP App resource.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Apps\Pattern_Approval
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Apps\Pattern_Approval;

use rtCamp\Publish_With_AI\Modules\MCP\Apps\McpAppResource;

/**
 * Class - App
 */
class App extends McpAppResource {
	public const URI = 'ui://rtpwai/pattern-approval';

	/**
	 * {@inheritDoc}
	 */
	public function uri(): string {
		return self::URI;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function title(): string {
		return 'Pattern Approval';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return 'Shows a rendered block-pattern preview and collects user approval before inserting it into a post.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function template_dir(): string {
		return __DIR__;
	}
}
