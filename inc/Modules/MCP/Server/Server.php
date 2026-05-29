<?php
/**
 * Registers the dedicated Publish with AI MCP server.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\Server
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\Server;

use WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use WP\MCP\Transport\HttpTransport;
use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;
use rtCamp\Publish_With_AI\Modules\MCP\Apps\Pattern_Approval\App as Pattern_Approval_App;
use rtCamp\Publish_With_AI\Modules\MCP\Resources\Content_Guide\Content_Guide;

/**
 * Class - Server
 */
class Server implements Registrable {
	private const SERVER_ID      = 'rt-publish-with-ai';
	private const ABILITY_PREFIX = 'rtpwai/';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'mcp_adapter_init', [ $this, 'create' ] );
	}

	/**
	 * Create and register the dedicated MCP server.
	 *
	 * @param \WP\MCP\Core\McpAdapter $adapter The MCP Adapter instance.
	 */
	public function create( \WP\MCP\Core\McpAdapter $adapter ): void {
		$adapter->create_server(
			self::SERVER_ID,
			'mcp',
			self::SERVER_ID,
			__( 'Publish With AI — rtCamp', 'rtcamp-publish-with-ai' ),
			__( 'MCP server for the Publish with AI plugin.', 'rtcamp-publish-with-ai' ),
			RTCAMP_PUBLISH_WITH_AI_VERSION,
			[ HttpTransport::class ],
			ErrorLogMcpErrorHandler::class,
			null,
			$this->get_tools(),
			$this->get_resources(), // @phpstan-ignore argument.type
		);
	}

	/**
	 * Build the list of MCP resources for this server.
	 *
	 * @return list<\WP\McpSchema\Server\Resources\DTO\Resource>
	 */
	private function get_resources(): array {
		$config = ( new Content_Guide() )->add_resource( [] );
		$config = ( new Pattern_Approval_App() )->add_resource( $config );
		return $config['resources'] ?? [];
	}

	/**
	 * Discover all abilities registered under the rtpwai/ namespace.
	 *
	 * @return list<string>
	 */
	private function get_tools(): array {
		$tools = [];

		foreach ( wp_get_abilities() as $ability ) {
			if ( str_starts_with( $ability->get_name(), self::ABILITY_PREFIX ) ) {
				$tools[] = $ability->get_name();
			}
		}

		return $tools;
	}
}
