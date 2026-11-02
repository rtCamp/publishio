<?php
/**
 * The HTML document the Publishio Preview app shows: rendered blocks with the theme's styles, in the wrapper a block theme puts around post content, so content width and padding match the page.
 *
 * @var array<string, mixed> $args {
 *     @type string $html Rendered block HTML.
 * }
 * @package rtCamp\Publishio
 */

declare(strict_types = 1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	<div class="wp-site-blocks">
		<div class="entry-content wp-block-post-content is-layout-constrained has-global-padding">
			<?php
			echo $args['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered blocks, already escaped by each block's render.
			?>
		</div>
	</div>
	<?php wp_footer(); ?>
</body>

</html>
