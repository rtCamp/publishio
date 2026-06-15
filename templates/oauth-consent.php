<?php
/**
 * OAuth consent screen template.
 *
 * Loaded via Templates::get_template_part( 'oauth-consent', null, $args ) from
 * Authorize::render_consent_screen(). Data is passed through $args (WordPress
 * load_template() does not extract it into individual variables).
 *
 * @var array<string, mixed> $args {
 *     @type string      $client_name        Display name of the OAuth client.
 *     @type string|null $client_uri         URL to the client's homepage.
 *     @type string|null $logo_uri           URL to the client's logo image.
 *     @type string|null $tos_uri            URL to the client's Terms of Service.
 *     @type string|null $policy_uri         URL to the client's Privacy Policy.
 *     @type string      $site_name          WordPress site name.
 *     @type string      $site_url           Home URL.
 *     @type string      $display_name       Current user display name.
 *     @type string      $user_email         Current user email.
 *     @type string      $action_url         Form action URL.
 *     @type string      $hidden_fields      Pre-rendered hidden input fields (unescaped).
 *     @type string      $scopes             Comma-separated scope string.
 *     @type string      $resource_url       MCP resource URL.
 *     @type string      $server_name        MCP server display name.
 *     @type string      $server_description MCP server description (empty string if none).
 * }
 *
 * @package rtCamp\Publishio
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( sprintf( 'Authorize %s — %s', $args['client_name'], $args['site_name'] ) ); ?></title>
	<?php wp_print_styles( 'publishio-consent' ); ?>
</head>
<body>
	<div class="consent-card">
		<div class="consent-header">
			<?php if ( ! empty( $args['logo_uri'] ) ) : ?>
			<div class="app-icon" aria-hidden="true">
				<img src="<?php echo esc_url( $args['logo_uri'] ); ?>" alt="<?php echo esc_attr( $args['client_name'] ); ?>" style="height:48px;max-width:120px;width:auto;object-fit:contain;" />
			</div>
			<?php endif; ?>
			<h1><?php esc_html_e( 'Authorize Application', 'publishio' ); ?></h1>
			<p class="subtitle">
				<?php if ( ! empty( $args['client_uri'] ) ) : ?>
					<strong><a href="<?php echo esc_url( $args['client_uri'] ); ?>" class="client-link" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $args['client_name'] ); ?></a></strong>
				<?php else : ?>
					<strong><?php echo esc_html( $args['client_name'] ); ?></strong>
				<?php endif; ?>
				<?php esc_html_e( 'is requesting access to', 'publishio' ); ?>
				<a href="<?php echo esc_url( $args['site_url'] ); ?>" class="site-link" target="_blank" rel="noopener">
					<?php echo esc_html( $args['site_name'] ); ?>
				</a>
			</p>
		</div>
		<div class="consent-body">
			<div class="section">
				<div class="section-label"><?php esc_html_e( 'Signed in as', 'publishio' ); ?></div>
				<div class="user-info">
					<div class="user-avatar" aria-hidden="true">
						<?php echo get_avatar( $args['user_email'], 36, '', esc_attr( $args['display_name'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<div class="user-details">
						<div class="user-name"><?php echo esc_html( $args['display_name'] ); ?></div>
						<div class="user-email"><?php echo esc_html( $args['user_email'] ); ?></div>
					</div>
				</div>
			</div>
			<?php if ( ! empty( $args['resource_url'] ) ) : ?>
			<div class="section">
				<div class="section-label"><?php esc_html_e( 'MCP Server', 'publishio' ); ?></div>
				<div class="server-info">
					<div class="server-icon" aria-hidden="true">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
							<rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/>
						</svg>
					</div>
					<div class="server-details">
						<div class="server-name"><?php echo esc_html( $args['server_name'] ); ?></div>
						<?php if ( ! empty( $args['server_description'] ) ) : ?>
						<div class="server-desc"><?php echo esc_html( $args['server_description'] ); ?></div>
						<?php endif; ?>
						<div class="server-url"><code><?php echo esc_html( $args['resource_url'] ); ?></code></div>
					</div>
				</div>
			</div>
			<?php endif; ?>
			<div class="section">
				<div class="section-label"><?php esc_html_e( 'Permissions', 'publishio' ); ?></div>
				<p class="scope-note">
					<?php
					// translators: %s: client application name wrapped in <strong>.
					printf( esc_html__( '%s will have access based on your WordPress role and capabilities.', 'publishio' ), '<strong>' . esc_html( $args['client_name'] ) . '</strong>' );
					?>
				</p>
			</div>
		</div>
		<form method="post" action="<?php echo esc_url( $args['action_url'] ); ?>" class="consent-footer">
			<?php echo $args['hidden_fields']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php wp_nonce_field( 'publishio_oauth_consent', '_wpnonce', false ); ?>
			<div class="buttons">
				<button type="submit" name="consent" value="deny" class="btn btn-deny">
					<?php esc_html_e( 'Deny', 'publishio' ); ?>
				</button>
				<button type="submit" name="consent" value="approve" class="btn btn-allow">
					<?php esc_html_e( 'Allow Access', 'publishio' ); ?>
				</button>
			</div>
			<p class="footer-note">
				<?php esc_html_e( 'You can revoke access from your WordPress profile page.', 'publishio' ); ?>
			</p>
			<?php if ( ! empty( $args['tos_uri'] ) || ! empty( $args['policy_uri'] ) ) : ?>
			<p class="footer-legal">
				<?php if ( ! empty( $args['tos_uri'] ) ) : ?>
					<a href="<?php echo esc_url( $args['tos_uri'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Terms of Service', 'publishio' ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $args['tos_uri'] ) && ! empty( $args['policy_uri'] ) ) : ?>
					<span aria-hidden="true"> · </span>
				<?php endif; ?>
				<?php if ( ! empty( $args['policy_uri'] ) ) : ?>
					<a href="<?php echo esc_url( $args['policy_uri'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Privacy Policy', 'publishio' ); ?></a>
				<?php endif; ?>
			</p>
			<?php endif; ?>
		</form>
	</div>
</body>
</html>
