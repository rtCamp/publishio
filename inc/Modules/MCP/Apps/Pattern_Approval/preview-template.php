<?php
/**
 * Full-page HTML template used by the Render_Pattern ability.
 *
 * Available variables (injected by the calling execute_callback before require):
 *
 * @var string $html Rendered block HTML from do_blocks().
 * @package rtCamp\Publish_With_AI\Modules\MCP\Abilities\Patterns
 */

declare( strict_types = 1 );

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<base href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank">
	<?php wp_head(); ?>
	<style>
		body { background: transparent !important; margin: 0; padding: 0; }
		html { margin-top: 0 !important; }
		<?php printf( '%s { display: none !important; }', '#wpadminbar' ); ?>
	</style>
</head>
<body <?php body_class(); ?>>
	<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised by do_blocks() ?>
	<?php wp_footer(); ?>
</body>
</html>
