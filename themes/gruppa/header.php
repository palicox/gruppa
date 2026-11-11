<?php defined( 'ABSPATH' ) || exit; ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
wp_body_open();

// Hlavička len z Elementor Theme Buildera – téma vlastnú nevypisuje.
if ( function_exists( 'elementor_theme_do_location' ) ) {
	elementor_theme_do_location( 'header' );
}
