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
use rtCamp\Publish_With_AI\Modules\MCP\Abilities\Resources\Content_Generation_Guide;

/**
 * Class - Server
 */
class Server implements Registrable {
	private const SERVER_ID = 'rt-publish-with-ai';

	private const TOOLS = [
		'rtpwai/get-patterns',
		'rtpwai/get-pattern',
		'rtpwai/get-pattern-schema',
		'rtpwai/apply-pattern-schema',
		'rtpwai/get-custom-blocks',
		'rtpwai/get-block',
		'rtpwai/render-block',
		'rtpwai/create-post',
		'rtpwai/get-post',
		'rtpwai/update-post',
		'rtpwai/set-featured-image',
		'rtpwai/append-blocks',
		'rtpwai/insert-blocks-at',
		'rtpwai/append-pattern',
		'rtpwai/insert-pattern-at',
		'rtpwai/delete-block-at',
		'rtpwai/search-posts',
		'rtpwai/search-attachments',
		'rtpwai/upload-media',
	];

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
		$guide_config = ( new Content_Generation_Guide() )->add_resource( [] );
		$resources    = $guide_config['resources'] ?? [];

		$adapter->create_server(
			self::SERVER_ID,
			'mcp',
			self::SERVER_ID,
			__( 'Publish with AI', 'rtcamp-publish-with-ai' ),
			__( 'MCP server for the Publish with AI plugin.', 'rtcamp-publish-with-ai' ),
			RTCAMP_PUBLISH_WITH_AI_VERSION,
			[ HttpTransport::class ],
			ErrorLogMcpErrorHandler::class,
			null,
			self::TOOLS,
			$resources,
		);
	}
}
