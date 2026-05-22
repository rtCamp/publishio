<?php
/**
 * Settings page for configuring the OAuth client.
 *
 * Adds a page under Settings → MCP OAuth where the admin can set
 * client_id, client_secret, redirect_uri, and client_name.
 *
 * @package rtCamp\Publish_With_AI\Modules\OAuth\Admin
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\OAuth\Admin;

use rtCamp\Publish_With_AI\Modules\OAuth\Client\Client_Registry;
use rtCamp\Publish_With_AI\Modules\OAuth\Config;

/**
 * Class - Settings_Page
 */
class Settings_Page {
	private const PAGE_SLUG    = 'rt-mcp-oauth';
	private const OPTION_GROUP = 'rt_mcp_oauth_settings';
	private const NONCE_ACTION = 'rt_mcp_oauth_save_client';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'handle_save' ] );
	}

	/**
	 * Add the settings page under Settings.
	 */
	public function add_menu_page(): void {
		add_options_page(
			__( 'MCP OAuth', 'rtcamp-publish-with-ai' ),
			__( 'MCP OAuth', 'rtcamp-publish-with-ai' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Handle form submission.
	 */
	public function handle_save(): void {
		if ( ! isset( $_POST['rt_mcp_oauth_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rt_mcp_oauth_nonce'] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$client_id     = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
		$client_secret = isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '';
		$redirect_uri  = isset( $_POST['redirect_uri'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_uri'] ) ) : '';
		$client_name   = isset( $_POST['client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['client_name'] ) ) : 'MCP Client';

		if ( empty( $client_id ) || empty( $redirect_uri ) ) {
			add_settings_error( self::OPTION_GROUP, 'missing_fields', __( 'Client ID and Redirect URI are required.', 'rtcamp-publish-with-ai' ) );
			return;
		}

		$existing = Client_Registry::get_client();

		// If secret field is empty, keep the existing hash (user didn't change it).
		if ( empty( $client_secret ) && $existing ) {
			$record = [
				'client_id'          => $client_id,
				'client_secret_hash' => $existing['client_secret_hash'],
				'redirect_uris'      => [ $redirect_uri ],
				'client_name'        => $client_name,
			];
			update_option( Config::CLIENT_OPTION_KEY, $record, false );
		} else {
			if ( empty( $client_secret ) ) {
				add_settings_error( self::OPTION_GROUP, 'missing_secret', __( 'Client Secret is required for initial setup.', 'rtcamp-publish-with-ai' ) );
				return;
			}
			Client_Registry::save_client( $client_id, $client_secret, [ $redirect_uri ], $client_name );
		}

		add_settings_error( self::OPTION_GROUP, 'saved', __( 'Client settings saved.', 'rtcamp-publish-with-ai' ), 'success' );
	}

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$client       = Client_Registry::get_client();
		$client_id    = $client['client_id'] ?? '';
		$redirect_uri = ! empty( $client['redirect_uris'] ) ? $client['redirect_uris'][0] : '';
		$client_name  = $client['client_name'] ?? '';
		$has_secret   = ! empty( $client['client_secret_hash'] );

		settings_errors( self::OPTION_GROUP );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MCP OAuth Client Settings', 'rtcamp-publish-with-ai' ); ?></h1>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_ACTION, 'rt_mcp_oauth_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="client_id"><?php esc_html_e( 'Client ID', 'rtcamp-publish-with-ai' ); ?></label>
						</th>
						<td>
							<input type="text" id="client_id" name="client_id"
								value="<?php echo esc_attr( $client_id ); ?>"
								class="regular-text" required />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="client_secret"><?php esc_html_e( 'Client Secret', 'rtcamp-publish-with-ai' ); ?></label>
						</th>
						<td>
							<input type="password" id="client_secret" name="client_secret"
								value="" class="regular-text"
								placeholder="<?php echo $has_secret ? esc_attr__( '••••••••  (leave blank to keep current)', 'rtcamp-publish-with-ai' ) : ''; ?>"
								<?php echo $has_secret ? '' : 'required'; ?> />
							<?php if ( $has_secret ) : ?>
								<p class="description"><?php esc_html_e( 'Leave blank to keep the existing secret.', 'rtcamp-publish-with-ai' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="redirect_uri"><?php esc_html_e( 'Redirect URI', 'rtcamp-publish-with-ai' ); ?></label>
						</th>
						<td>
							<input type="url" id="redirect_uri" name="redirect_uri"
								value="<?php echo esc_attr( $redirect_uri ); ?>"
								class="regular-text" required
								placeholder="https://claude.ai/api/mcp/auth_callback" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="client_name"><?php esc_html_e( 'Client Name', 'rtcamp-publish-with-ai' ); ?></label>
						</th>
						<td>
							<input type="text" id="client_name" name="client_name"
								value="<?php echo esc_attr( $client_name ); ?>"
								class="regular-text"
								placeholder="Claude AI" />
							<p class="description"><?php esc_html_e( 'Shown on the consent screen.', 'rtcamp-publish-with-ai' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Client', 'rtcamp-publish-with-ai' ) ); ?>
			</form>
		</div>
		<?php
	}
}
