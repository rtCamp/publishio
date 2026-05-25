<?php
/**
 * OAuth consent screen template.
 *
 * Variables provided by Authorize::render_consent_screen():
 *   $client_name   string  Display name of the OAuth client.
 *   $site_name     string  WordPress site name.
 *   $site_url      string  Home URL.
 *   $display_name  string  Current user display name.
 *   $user_email    string  Current user email.
 *   $scopes        string  Comma-separated scope string.
 *   $action_url    string  Form action URL.
 *   $hidden_fields string  Pre-rendered hidden input fields (unescaped).
 *   $css_url       string  URL to the consent stylesheet.
 *   $resource_url  string  MCP resource URL.
 *   $server_name   string  MCP server display name.
 *
 * @package rtCamp\Publish_With_AI
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var string $client_name
 * @var string $site_name
 * @var string $site_url
 * @var string $display_name
 * @var string $user_email
 * @var string $css_url
 * @var string $action_url
 * @var string $hidden_fields
 * @var string $server_name
 * @var string $resource_url
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( sprintf( 'Authorize %s — %s', $client_name, $site_name ) ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>" /> <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>
</head>
<body>
	<div class="consent-card">
		<div class="consent-header">
			<div class="app-icon" aria-hidden="true">
				<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
					<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
				</svg>
			</div>
			<h1><?php esc_html_e( 'Authorize Application', 'rtcamp-publish-with-ai' ); ?></h1>
			<p class="subtitle">
				<strong><?php echo esc_html( $client_name ); ?></strong>
				<?php esc_html_e( 'is requesting access to', 'rtcamp-publish-with-ai' ); ?>
				<a href="<?php echo esc_url( $site_url ); ?>" class="site-link" target="_blank" rel="noopener">
					<?php echo esc_html( $site_name ); ?>
				</a>
			</p>
		</div>
		<div class="consent-body">
			<div class="section">
				<div class="section-label"><?php esc_html_e( 'Signed in as', 'rtcamp-publish-with-ai' ); ?></div>
				<div class="user-info">
					<div class="user-avatar" aria-hidden="true">
						<?php echo get_avatar( $user_email, 36, '', esc_attr( $display_name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<div class="user-details">
						<div class="user-name"><?php echo esc_html( $display_name ); ?></div>
						<div class="user-email"><?php echo esc_html( $user_email ); ?></div>
					</div>
				</div>
			</div>
			<?php if ( ! empty( $resource_url ) ) : ?>
			<div class="section">
				<div class="section-label"><?php esc_html_e( 'MCP Server', 'rtcamp-publish-with-ai' ); ?></div>
				<div class="server-info">
					<div class="server-icon" aria-hidden="true">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
							<rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/>
						</svg>
					</div>
					<div class="server-details">
						<div class="server-name"><?php echo esc_html( $server_name ); ?></div>
						<div class="server-url"><code><?php echo esc_html( $resource_url ); ?></code></div>
					</div>
				</div>
			</div>
			<?php endif; ?>
			<div class="section">
				<div class="section-label"><?php esc_html_e( 'Permissions', 'rtcamp-publish-with-ai' ); ?></div>
				<p class="scope-note">
					<?php
					// translators: %s: client application name wrapped in <strong>.
					printf( esc_html__( '%s will have access based on your WordPress role and capabilities.', 'rtcamp-publish-with-ai' ), '<strong>' . esc_html( $client_name ) . '</strong>' );
					?>
				</p>
			</div>
		</div>
		<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="consent-footer">
			<?php echo $hidden_fields; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php wp_nonce_field( 'rtpwai_oauth_consent', '_wpnonce', false ); ?>
			<div class="buttons">
				<button type="submit" name="consent" value="deny" class="btn btn-deny">
					<?php esc_html_e( 'Deny', 'rtcamp-publish-with-ai' ); ?>
				</button>
				<button type="submit" name="consent" value="approve" class="btn btn-allow">
					<?php esc_html_e( 'Allow Access', 'rtcamp-publish-with-ai' ); ?>
				</button>
			</div>
			<p class="footer-note">
				<?php esc_html_e( 'You can revoke access from your WordPress profile page.', 'rtcamp-publish-with-ai' ); ?>
			</p>
		</form>
	</div>
</body>
</html>
