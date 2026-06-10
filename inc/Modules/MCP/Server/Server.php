<?php
/**
 * Registers the dedicated Publishio MCP server.
 *
 * @package rtCamp\Publishio\Modules\MCP\Server
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\Server;

use WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use WP\MCP\Transport\HttpTransport;
use rtCamp\Publishio\Framework\Contracts\Interfaces\Registrable;
use rtCamp\Publishio\Modules\MCP\Apps\Pattern_Approval\App as Pattern_Approval_App;
use rtCamp\Publishio\Modules\MCP\Resources\Content_Guide\Content_Guide;

/**
 * Class - Server
 */
class Server implements Registrable {
	private const SERVER_ID      = 'publishio';
	private const ABILITY_PREFIX = 'publishio/';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'init_mcp_adapter' ] );
		add_action( 'mcp_adapter_init', [ $this, 'create' ] );
	}

	/**
	 * Ensure the MCP Adapter is initialized so we can register our server.
	 */
	public function init_mcp_adapter(): void {
		if ( class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			\WP\MCP\Core\McpAdapter::instance();
		}
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
			__( 'Publishio — rtCamp', 'publishio' ),
			__( 'MCP server for the Publishio plugin.', 'publishio' ),
			PUBLISHIO_VERSION,
			[ HttpTransport::class ],
			ErrorLogMcpErrorHandler::class,
			null,
			$this->get_tools(),
			$this->get_resources(), // @phpstan-ignore argument.type
		);
	}

	/**
	 * Get this plugin's registered MCP server, if available.
	 */
	public static function get_server(): ?\WP\MCP\Core\McpServer {
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			return null;
		}

		return \WP\MCP\Core\McpAdapter::instance()->get_server( self::SERVER_ID );
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
	 * Discover all abilities registered under the publishio/ namespace.
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
