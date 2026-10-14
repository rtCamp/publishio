<?php
/**
 * Adds a "Publishio MCP Connections" section to the user profile page, where users can see and remove the AI apps connected to their account.
 *
 * @package rtCamp\Publishio\Modules\MCP\OAuth\Admin
 */

declare( strict_types = 1 );

namespace rtCamp\Publishio\Modules\MCP\OAuth\Admin;

use rtCamp\Publishio\Modules\MCP\OAuth\Client\Client_Metadata;
use rtCamp\Publishio\Modules\MCP\OAuth\Storage\Token_Store;

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
		add_action( 'admin_post_publishio_oauth_revoke', [ $this, 'handle_revoke' ] );
		add_action( 'admin_post_publishio_oauth_revoke_token', [ $this, 'handle_revoke_token' ] );
	}

	/**
	 * Remove all of a user's connections via admin-post.php.
	 */
	public function handle_revoke(): void {
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'publishio_oauth_revoke_' . $user_id )
		) {
			wp_die( esc_html__( 'Invalid or expired request.', 'publishio' ), 403 );
		}

		if ( ! $user_id ) {
			wp_die( esc_html__( 'Invalid user.', 'publishio' ), 400 );
		}

		// Only allow: own tokens, or admins editing other users.
		if ( get_current_user_id() !== $user_id && ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'You do not have permission to remove these connections.', 'publishio' ), 403 );
		}

		Token_Store::revoke_all( $user_id );

		// Redirect back to profile with a success message.
		$redirect = get_current_user_id() === $user_id
			? admin_url( 'profile.php?publishio_oauth_revoked=all' )
			: admin_url( 'user-edit.php?user_id=' . $user_id . '&publishio_oauth_revoked=all' );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Remove one connection via admin-post.php.
	 */
	public function handle_revoke_token(): void {
		$user_id  = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$token_id = isset( $_GET['token_id'] ) ? absint( $_GET['token_id'] ) : 0;

		if ( ! isset( $_GET['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'publishio_oauth_revoke_token_' . $user_id . '_' . $token_id )
		) {
			wp_die( esc_html__( 'Invalid or expired request.', 'publishio' ), 403 );
		}

		if ( ! $user_id || ! $token_id ) {
			wp_die( esc_html__( 'Invalid request.', 'publishio' ), 400 );
		}

		if ( get_current_user_id() !== $user_id && ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'You do not have permission to remove these connections.', 'publishio' ), 403 );
		}

		$deleted = Token_Store::revoke( $token_id, $user_id );
		$result  = match ( true ) {
			false === $deleted => 'error',
			0 === $deleted     => 'missing',
			default            => 'connection',
		};

		$redirect = get_current_user_id() === $user_id
			? admin_url( 'profile.php?publishio_oauth_revoked=' . $result )
			: admin_url( 'user-edit.php?user_id=' . $user_id . '&publishio_oauth_revoked=' . $result );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render the MCP connections section on the profile page.
	 *
	 * @param \WP_User $user The user being viewed.
	 */
	public function render_section( \WP_User $user ): void {
		$connections = Token_Store::get_for_user( $user->ID );
		$now         = time();
		$revoked     = isset( $_GET['publishio_oauth_revoked'] ) ? sanitize_text_field( wp_unslash( $_GET['publishio_oauth_revoked'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<h2><?php esc_html_e( 'Publishio MCP Connections', 'publishio' ); ?></h2>

		<?php if ( 'connection' === $revoked ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Connection removed.', 'publishio' ); ?></p></div>
		<?php elseif ( 'all' === $revoked ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'All connections removed.', 'publishio' ); ?></p></div>
		<?php elseif ( 'missing' === $revoked ) : ?>
			<div class="notice notice-error inline"><p><?php esc_html_e( 'That connection changed since the page loaded, so nothing was removed. Check the list below and try again.', 'publishio' ); ?></p></div>
		<?php elseif ( 'error' === $revoked ) : ?>
			<div class="notice notice-error inline"><p><?php esc_html_e( 'The connection couldn\'t be removed because of a database error. Please try again.', 'publishio' ); ?></p></div>
		<?php endif; ?>

		<?php if ( empty( $connections ) ) : ?>
			<p class="description"><?php esc_html_e( 'No MCP connections.', 'publishio' ); ?></p>
		<?php else : ?>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'App', 'publishio' ); ?></th>
						<th><?php esc_html_e( 'Status', 'publishio' ); ?></th>
						<th><?php esc_html_e( 'Scope', 'publishio' ); ?></th>
						<th><?php esc_html_e( 'Connected', 'publishio' ); ?></th>
						<th><?php esc_html_e( 'Last Active', 'publishio' ); ?></th>
						<th><?php esc_html_e( 'Expires', 'publishio' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'publishio' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $connections as $token ) : ?>
						<?php
						$expires_at       = Token_Store::expires_at( (int) $token['refresh_expires_at'], (int) $token['authorized_at'] );
						$is_expired       = $expires_at <= $now;
						$revoke_token_url = wp_nonce_url(
							add_query_arg(
								[
									'action'   => 'publishio_oauth_revoke_token',
									'user_id'  => $user->ID,
									'token_id' => $token['id'],
								],
								admin_url( 'admin-post.php' )
							),
							'publishio_oauth_revoke_token_' . $user->ID . '_' . $token['id']
						);
						?>
					<tr>
						<td><?php echo esc_html( $token['client_name'] ?: Client_Metadata::host( $token['client_id'] ) ); ?></td>
						<td><?php echo $is_expired ? esc_html__( 'Expired', 'publishio' ) : esc_html__( 'Active', 'publishio' ); ?></td>
						<td><code><?php echo esc_html( $token['scope'] ?: '—' ); ?></code></td>
						<td><?php echo esc_html( (string) wp_date( 'M j, Y g:i A', $token['authorized_at'] ) ); ?></td>
						<td><?php echo esc_html( (string) wp_date( 'M j, Y g:i A', $token['created_at'] ) ); ?></td>
						<td><?php echo esc_html( (string) wp_date( 'M j, Y g:i A', $expires_at ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( $revoke_token_url ); ?>" class="button button-small button-secondary"
								onclick="return confirm('<?php echo esc_js( $is_expired ? __( 'Remove this expired connection? The app can no longer use it.', 'publishio' ) : __( 'Remove this connection? The app loses access through it right away. Other connections for the same app stay active.', 'publishio' ) ); ?>');">
								<?php esc_html_e( 'Remove', 'publishio' ); ?>
							</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$revoke_url = wp_nonce_url(
				add_query_arg(
					[
						'action'  => 'publishio_oauth_revoke',
						'user_id' => $user->ID,
					],
					admin_url( 'admin-post.php' )
				),
				'publishio_oauth_revoke_' . $user->ID
			);
			?>
			<p style="margin-top: 12px;">
				<a href="<?php echo esc_url( $revoke_url ); ?>" class="button button-secondary"
					onclick="return confirm('<?php echo esc_js( __( 'Remove all connections? Each app loses access right away and must be connected again.', 'publishio' ) ); ?>');">
					<?php esc_html_e( 'Remove All Connections', 'publishio' ); ?>
				</a>
			</p>
		<?php endif; ?>
		<?php
	}
}
