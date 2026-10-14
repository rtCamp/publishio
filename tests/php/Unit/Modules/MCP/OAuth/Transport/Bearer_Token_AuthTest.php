<?php
/**
 * Tests for scope enforcement on MCP tool calls.
 *
 * @package rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Transport
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Tests\Unit\Modules\MCP\OAuth\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use WP\MCP\Core\McpServer;
use WP\MCP\Domain\Tools\McpTool;
use WP\MCP\Handlers\Tools\ToolsHandler;
use WP\McpSchema\Server\Tools\DTO\CallToolResult;
use rtCamp\Publishio\Modules\MCP\Abilities\Categories\Posts as Posts_Category;
use rtCamp\Publishio\Modules\MCP\OAuth\Config;
use rtCamp\Publishio\Modules\MCP\OAuth\Storage\Token_Store;
use rtCamp\Publishio\Modules\MCP\OAuth\Transport\Bearer_Token_Auth;
use rtCamp\Publishio\Modules\MCP\Server\Server;
use rtCamp\Publishio\Tests\TestCase;

/**
 * Class - Bearer_Token_AuthTest
 */
#[CoversClass( Bearer_Token_Auth::class )]
class Bearer_Token_AuthTest extends TestCase {
	private const READ_ABILITY   = 'publishio/get-post';
	private const WRITE_ABILITY  = 'publishio/create-post';
	private const DELETE_ABILITY = 'publishio/delete-block-at';
	private const TEST_ABILITY   = 'publishio-test/ability';

	/**
	 * The rest_route query var before the test.
	 *
	 * @var mixed
	 */
	private $original_rest_route;

	/**
	 * Point the request at the MCP route.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->original_rest_route               = $GLOBALS['wp']->query_vars['rest_route'] ?? null;
		$GLOBALS['wp']->query_vars['rest_route'] = '/' . Config::get_mcp_endpoint_path();
	}

	/**
	 * Restore the request and remove the test ability.
	 */
	protected function tearDown(): void {
		$GLOBALS['wp']->query_vars['rest_route'] = $this->original_rest_route;
		unset( $_SERVER['HTTP_AUTHORIZATION'] );

		if ( wp_get_ability( self::TEST_ABILITY ) ) {
			\WP_Abilities_Registry::get_instance()->unregister( self::TEST_ABILITY );
		}

		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * A read-only token can call a readonly ability but not one that changes the site.
	 */
	public function test_read_token_is_limited_to_readonly_abilities(): void {
		$auth = $this->authenticate( 'mcp:read' );

		$this->assertSame( [ 'id' => 1 ], $auth->enforce_token_scope( [ 'id' => 1 ], 'get-post', $this->tool( self::READ_ABILITY ), $this->publishio_server() ) );

		$result = $auth->enforce_token_scope( [], 'create-post', $this->tool( self::WRITE_ABILITY ), $this->publishio_server() );
		$this->assertWPError( $result );
		$this->assertSame( 'insufficient_scope', $result->get_error_code() );
	}

	/**
	 * Deleting needs mcp:write.
	 */
	public function test_read_token_cannot_call_delete_ability(): void {
		$auth = $this->authenticate( 'mcp:read' );

		$this->assertWPError( $auth->enforce_token_scope( [], 'delete-block-at', $this->tool( self::DELETE_ABILITY ), $this->publishio_server() ) );
	}

	/**
	 * An ability annotated destructive counts as a write, even if it is also annotated readonly.
	 */
	public function test_destructive_ability_counts_as_write_even_if_marked_readonly(): void {
		$this->register_test_ability(
			[
				'annotations' => [
					'readonly'    => true,
					'destructive' => true,
				],
			]
		);

		$auth = $this->authenticate( 'mcp:read' );

		$this->assertWPError( $auth->enforce_token_scope( [], 'ability', $this->tool( self::TEST_ABILITY ), $this->publishio_server() ) );
	}

	/**
	 * A token with mcp:write can call abilities that change the site.
	 */
	public function test_write_token_can_call_write_abilities(): void {
		$auth = $this->authenticate( 'mcp:read mcp:write' );

		$this->assertSame( [], $auth->enforce_token_scope( [], 'create-post', $this->tool( self::WRITE_ABILITY ), $this->publishio_server() ) );
	}

	/**
	 * A token stored with an empty scope gets no write access.
	 */
	public function test_empty_scope_token_cannot_write(): void {
		$auth = $this->authenticate( '' );

		$this->assertWPError( $auth->enforce_token_scope( [], 'create-post', $this->tool( self::WRITE_ABILITY ), $this->publishio_server() ) );
	}

	/**
	 * An ability without an explicit readonly annotation counts as a write.
	 */
	public function test_missing_readonly_annotation_counts_as_write(): void {
		$this->register_test_ability( [] );

		$auth = $this->authenticate( 'mcp:read' );

		$this->assertWPError( $auth->enforce_token_scope( [], 'ability', $this->tool( self::TEST_ABILITY ), $this->publishio_server() ) );
	}

	/**
	 * Tool calls on other MCP servers are left alone.
	 */
	public function test_other_mcp_servers_are_not_checked(): void {
		$other = new McpServer( 'scope-test-other', 'scope-test', 'other', 'Other', 'Another MCP server on the site.', '1.0.0', [], null, null );

		$auth = $this->authenticate( 'mcp:read' );

		$this->assertSame( [], $auth->enforce_token_scope( [], 'create-post', $this->tool( self::WRITE_ABILITY ), $other ) );
	}

	/**
	 * Requests not authenticated by an access token are not limited by scope.
	 */
	public function test_request_without_token_is_not_limited(): void {
		$auth = new Bearer_Token_Auth();

		$this->assertNull( $auth->authenticate_bearer_token( null ) );
		$this->assertSame( [], $auth->enforce_token_scope( [], 'create-post', $this->tool( self::WRITE_ABILITY ), $this->publishio_server() ) );
	}

	/**
	 * The bundled MCP adapter runs the scope check when it handles a tools/call request.
	 */
	public function test_adapter_refuses_write_tool_for_read_token(): void {
		$server = $this->publishio_server();
		$auth   = $this->authenticate( 'mcp:read' );
		$auth->register();
		// A careless filter at a lower priority that rebuilds the arguments must not undo the refusal.
		add_filter( 'mcp_adapter_pre_tool_call', static fn () => [ 'title' => 'Written by a read-only token' ], 20 );

		$result = ( new ToolsHandler( $server ) )->call_tool(
			[
				'name'      => 'publishio-create-post',
				'arguments' => [ 'title' => 'Written by a read-only token' ],
			]
		);

		$this->assertInstanceOf( CallToolResult::class, $result );
		$data = $result->toArray();
		$this->assertTrue( $data['isError'] ?? false );
		$this->assertStringContainsString( 'read-only access', wp_json_encode( $data['content'] ) ?: '' );
		$this->assertSame(
			[],
			get_posts(
				[
					'title'       => 'Written by a read-only token',
					'post_status' => 'any',
				]
			)
		);
	}

	/**
	 * The challenge points at the home URL, which the rewrite rules match, when WordPress is in its own directory.
	 */
	public function test_challenge_uses_the_home_url(): void {
		// A filter, since wp-env's WP_SITEURL constant overrides the option.
		add_filter( 'option_siteurl', static fn (): string => home_url( '/wp' ), PHP_INT_MAX );
		$request = new \WP_REST_Request( 'POST', '/' . Config::get_mcp_endpoint_path() );

		$response = ( new Bearer_Token_Auth() )->add_www_authenticate_header( new \WP_REST_Response( null, 401 ), rest_get_server(), $request );

		$this->assertStringStartsWith(
			'Bearer resource_metadata="' . home_url( '/.well-known/oauth-protected-resource/wp-json/' . Config::get_mcp_endpoint_path() ) . '"',
			$response->get_headers()['WWW-Authenticate']
		);
	}

	/**
	 * Every Publishio ability says whether it is read-only, since that decides which tokens may call it.
	 */
	public function test_every_publishio_ability_declares_readonly(): void {
		$abilities = array_filter( wp_get_abilities(), static fn ( \WP_Ability $ability ) => str_starts_with( $ability->get_name(), 'publishio/' ) );
		$this->assertNotEmpty( $abilities );

		foreach ( $abilities as $ability ) {
			$this->assertIsBool( $ability->get_meta()['annotations']['readonly'] ?? null, $ability->get_name() );
		}
	}

	/**
	 * No Publishio ability is annotated both destructive and readonly.
	 */
	public function test_destructive_abilities_are_not_readonly(): void {
		foreach ( wp_get_abilities() as $ability ) {
			$annotations = $ability->get_meta()['annotations'] ?? [];

			if ( str_starts_with( $ability->get_name(), 'publishio/' ) && true === ( $annotations['destructive'] ?? null ) ) {
				$this->assertFalse( $annotations['readonly'] ?? null, $ability->get_name() );
			}
		}
	}

	/**
	 * Authenticate a request with a fresh access token carrying the given scope.
	 *
	 * @param string $scope Granted scope.
	 */
	private function authenticate( string $scope ): Bearer_Token_Auth {
		$tokens = Token_Store::issue( self::factory()->user->create( [ 'role' => 'editor' ] ), 'https://app.example.com/client.json', 'Example App', $scope, Config::get_mcp_resource_claim() );
		$this->assertIsArray( $tokens );

		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tokens['access_token'];

		$auth = new Bearer_Token_Auth();
		$this->assertTrue( $auth->authenticate_bearer_token( null ) );

		return $auth;
	}

	/**
	 * The Publishio MCP server, booting the adapter without its default server.
	 */
	private function publishio_server(): McpServer {
		add_filter( 'mcp_adapter_create_default_server', '__return_false' );
		rest_get_server();

		$server = Server::get_server();
		$this->assertInstanceOf( McpServer::class, $server );

		return $server;
	}

	/**
	 * Register the test ability with the given meta.
	 *
	 * @param array<string, mixed> $meta Ability meta.
	 */
	private function register_test_ability( array $meta ): void {
		\WP_Abilities_Registry::get_instance()->register(
			self::TEST_ABILITY,
			[
				'label'               => 'Test ability',
				'description'         => 'An ability registered by the test.',
				'category'            => Posts_Category::SLUG,
				'execute_callback'    => static fn () => true,
				'permission_callback' => '__return_true',
				'meta'                => $meta,
			]
		);
	}

	/**
	 * Build the MCP tool for a registered ability.
	 *
	 * @param string $ability_name Ability name.
	 */
	private function tool( string $ability_name ): McpTool {
		$ability = wp_get_ability( $ability_name );
		$this->assertInstanceOf( \WP_Ability::class, $ability );

		$tool = McpTool::fromAbility( $ability );
		$this->assertInstanceOf( McpTool::class, $tool );

		return $tool;
	}
}
