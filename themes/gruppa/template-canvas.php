<?php
/**
 * Template Name: Canvas
 * Template Post Type: page, post
 *
 * Čisté plátno – bez hlavičky a päty témy, obsah na plnú šírku.
 * Pre vlastné typy obsahu sa šablóna pridáva vo functions.php (filter theme_templates).
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<main id="content" class="site-main">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>
<?php wp_footer(); ?>
</body>
</html>
