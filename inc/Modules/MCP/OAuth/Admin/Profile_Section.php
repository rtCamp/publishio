<?php
/**
 * Adds an "MCP OAuth Sessions" section to the user profile page
 * so users can view and revoke their active OAuth tokens.
 *
 * @package rtCamp\Publish_With_AI\Modules\MCP\OAuth\Admin
 */

declare( strict_types = 1 );

namespace rtCamp\Publish_With_AI\Modules\MCP\OAuth\Admin;

use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage\Client_Store;
use rtCamp\Publish_With_AI\Modules\MCP\OAuth\Storage\Token_Store;

/**
 * Class - Profile_Section
 */
class Profile_Section {
	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'show_user_profile', [ $this, 'render_section' ] );
		add_action( 'edit_user_profile', [ $this, 'render_section' ] );
		add_action( 'admin_post_rtpwai_oauth_revoke', [ $this, 'handle_revoke' ] );
		add_action( 'admin_post_rtpwai_oauth_revoke_client', [ $this, 'handle_revoke_client' ] );
	}

	/**
	 * Handle the revoke action via admin-post.php.
	 */
	public function handle_revoke(): void {
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'rtpwai_oauth_revoke_' . $user_id )
		) {
			wp_die( esc_html__( 'Invalid or expired request.', 'rtcamp-publish-with-ai' ), 403 );
		}

		if ( ! $user_id ) {
			wp_die( esc_html__( 'Invalid user.', 'rtcamp-publish-with-ai' ), 400 );
		}

		// Only allow: own tokens, or admins editing other users.
		if ( get_current_user_id() !== $user_id && ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'You do not have permission to revoke these sessions.', 'rtcamp-publish-with-ai' ), 403 );
		}

		Token_Store::revoke_all( $user_id );

		// Redirect back to profile with a success message.
		$redirect = get_current_user_id() === $user_id
			? admin_url( 'profile.php?rtpwai_oauth_revoked=1' )
			: admin_url( 'user-edit.php?user_id=' . $user_id . '&rtpwai_oauth_revoked=1' );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle per-client revocation via admin-post.php.
	 */
	public function handle_revoke_client(): void {
		$user_id   = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$client_id = isset( $_GET['client_id'] ) ? sanitize_text_field( wp_unslash( $_GET['client_id'] ) ) : '';

		if ( ! isset( $_GET['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'rtpwai_oauth_revoke_client_' . $user_id . '_' . $client_id )
		) {
			wp_die( esc_html__( 'Invalid or expired request.', 'rtcamp-publish-with-ai' ), 403 );
		}

		if ( ! $user_id || ! $client_id ) {
			wp_die( esc_html__( 'Invalid request.', 'rtcamp-publish-with-ai' ), 400 );
		}

		if ( get_current_user_id() !== $user_id && ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'You do not have permission to revoke these sessions.', 'rtcamp-publish-with-ai' ), 403 );
		}

		Token_Store::revoke_for_client( $user_id, $client_id );
		Client_Store::delete_by_client_id( $client_id );

		$redirect = get_current_user_id() === $user_id
			? admin_url( 'profile.php?rtpwai_oauth_revoked=client' )
			: admin_url( 'user-edit.php?user_id=' . $user_id . '&rtpwai_oauth_revoked=client' );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render the OAuth sessions section on the profile page.
	 *
	 * @param \WP_User $user The user being viewed.
	 */
	public function render_section( \WP_User $user ): void {
		$active  = Token_Store::get_active_for_user( $user->ID );
		$revoked = isset( $_GET['rtpwai_oauth_revoked'] ) ? sanitize_text_field( wp_unslash( $_GET['rtpwai_oauth_revoked'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<h2><?php esc_html_e( 'MCP OAuth Sessions', 'rtcamp-publish-with-ai' ); ?></h2>

		<?php if ( 'client' === $revoked ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'MCP OAuth session revoked.', 'rtcamp-publish-with-ai' ); ?></p></div>
		<?php elseif ( $revoked ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'All MCP OAuth sessions have been revoked.', 'rtcamp-publish-with-ai' ); ?></p></div>
		<?php endif; ?>

		<?php if ( empty( $active ) ) : ?>
			<p class="description"><?php esc_html_e( 'No active MCP OAuth sessions.', 'rtcamp-publish-with-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Client', 'rtcamp-publish-with-ai' ); ?></th>
						<th><?php esc_html_e( 'Scope', 'rtcamp-publish-with-ai' ); ?></th>
						<th><?php esc_html_e( 'Created', 'rtcamp-publish-with-ai' ); ?></th>
						<th><?php esc_html_e( 'Expires', 'rtcamp-publish-with-ai' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'rtcamp-publish-with-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $active as $token ) : ?>
						<?php
						$revoke_client_url = wp_nonce_url(
							admin_url( 'admin-post.php?action=rtpwai_oauth_revoke_client&user_id=' . $user->ID . '&client_id=' . rawurlencode( $token['client_id'] ) ),
							'rtpwai_oauth_revoke_client_' . $user->ID . '_' . $token['client_id']
						);
						?>
					<tr>
						<td><?php echo esc_html( $token['client_id'] ); ?></td>
						<td><code><?php echo esc_html( $token['scope'] ?: '—' ); ?></code></td>
						<td><?php echo esc_html( (string) wp_date( 'M j, Y g:i A', $token['created_at'] ) ); ?></td>
						<td><?php echo esc_html( (string) wp_date( 'M j, Y g:i A', $token['refresh_expires_at'] ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( $revoke_client_url ); ?>" class="button button-small button-secondary"
								onclick="return confirm('<?php echo esc_js( __( 'Revoke access for this client? It will need to re-authorize.', 'rtcamp-publish-with-ai' ) ); ?>');">
								<?php esc_html_e( 'Revoke', 'rtcamp-publish-with-ai' ); ?>
							</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$revoke_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=rtpwai_oauth_revoke&user_id=' . $user->ID ),
				'rtpwai_oauth_revoke_' . $user->ID
			);
			?>
			<p style="margin-top: 12px;">
				<a href="<?php echo esc_url( $revoke_url ); ?>" class="button button-secondary"
					onclick="return confirm('<?php echo esc_js( __( 'Revoke all MCP OAuth sessions? The connected application will need to re-authorize.', 'rtcamp-publish-with-ai' ) ); ?>');">
					<?php esc_html_e( 'Revoke All Sessions', 'rtcamp-publish-with-ai' ); ?>
				</a>
			</p>
		<?php endif; ?>
		<?php
	}
}
