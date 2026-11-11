<?php
/**
 * Predvolená šablóna – hlavička / obsah na plnú šírku / päta.
 *
 * Téma nevypisuje žiadne vlastné prvky (nadpisy, výpisy, texty).
 * Hlavička, päta, single, archív aj 404 sa stavajú v Elementor Theme Builderi;
 * stránky a články vypíšu iba svoj obsah na plnú šírku.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$gruppa_done = function_exists( 'elementor_theme_do_location' ) && (
	( is_singular() && elementor_theme_do_location( 'single' ) ) ||
	( ( is_archive() || is_home() || is_search() ) && elementor_theme_do_location( 'archive' ) ) ||
	( is_404() && elementor_theme_do_location( 'single' ) )
);

if ( ! $gruppa_done ) {
	echo '<main id="content" class="site-main">';
	if ( is_singular() ) {
		while ( have_posts() ) {
			the_post();
			the_content();
			wp_link_pages();
		}
	}
	echo '</main>';
}

get_footer();
