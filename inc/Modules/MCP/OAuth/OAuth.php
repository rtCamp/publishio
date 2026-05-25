<?php
/**
 * OAuth module entry point.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth;

use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Admin\Profile_Section;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Admin\Settings_Page;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Endpoint\Authorize;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Endpoint\Register;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Endpoint\Token;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Transport\Bearer_Token_Auth;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Well_Known\Auth_Server_Metadata;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Well_Known\Protected_Resource;

/**
 * Class - OAuth
 */
final class OAuth implements Registrable {
	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		( new Protected_Resource() )->register_hooks();
		( new Auth_Server_Metadata() )->register_hooks();
		( new Authorize() )->register_hooks();
		( new Token() )->register_hooks();
		( new Register() )->register_hooks();
		( new Bearer_Token_Auth() )->register();

		if ( is_admin() ) {
			( new Settings_Page() )->register();
			( new Profile_Section() )->register();
		}
	}
}
