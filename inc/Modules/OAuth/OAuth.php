<?php
/**
 * OAuth module entry point.
 *
 * @package rtCamp\Publish_With_AI\Modules\OAuth
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\OAuth;

use rtCamp\Publish_With_AI\Framework\Contracts\Interfaces\Registrable;
use rtCamp\Publish_With_AI\Modules\OAuth\Admin\Profile_Section;
use rtCamp\Publish_With_AI\Modules\OAuth\Admin\Settings_Page;
use rtCamp\Publish_With_AI\Modules\OAuth\CLI\OAuth_Commands;
use rtCamp\Publish_With_AI\Modules\OAuth\Endpoint\Authorize;
use rtCamp\Publish_With_AI\Modules\OAuth\Endpoint\Token;
use rtCamp\Publish_With_AI\Modules\OAuth\Transport\Bearer_Token_Auth;
use rtCamp\Publish_With_AI\Modules\OAuth\Well_Known\Auth_Server_Metadata;
use rtCamp\Publish_With_AI\Modules\OAuth\Well_Known\Protected_Resource;

/**
 * Class - OAuth
 */
final class OAuth implements Registrable {
	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		( new Protected_Resource() )->register();
		( new Auth_Server_Metadata() )->register();
		( new Authorize() )->register();
		( new Token() )->register();
		( new Bearer_Token_Auth() )->register();

		if ( is_admin() ) {
			( new Settings_Page() )->register();
			( new Profile_Section() )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'pwai-oauth', OAuth_Commands::class );
		}
	}
}
