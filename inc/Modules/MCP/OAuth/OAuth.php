<?php
/**
 * OAuth module entry point.
 *
 * @package rtCamp\Publishio\Modules\MCP\OAuth
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\OAuth;

use rtCamp\Publishio\Framework\Contracts\Interfaces\Registrable;
use rtCamp\Publishio\Modules\MCP\OAuth\Admin\Profile_Section;
use rtCamp\Publishio\Modules\MCP\OAuth\Endpoint\Authorize;
use rtCamp\Publishio\Modules\MCP\OAuth\Endpoint\Register;
use rtCamp\Publishio\Modules\MCP\OAuth\Endpoint\Token;
use rtCamp\Publishio\Modules\MCP\OAuth\Transport\Bearer_Token_Auth;
use rtCamp\Publishio\Modules\MCP\OAuth\Well_Known\Auth_Server_Metadata;
use rtCamp\Publishio\Modules\MCP\OAuth\Well_Known\Protected_Resource;

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
			( new Profile_Section() )->register();
		}
	}
}
