<?php
/**
 * Gruppa Tools → Theme Builder (otvorí Prispôsobiť na paneli Gruppa – dizajn).
 *
 * S pluginom Gruppa Tools (hub): téma sa zaregistruje ako modul cez Gruppa_Tools_Hub::add_module(), order 20 (hneď za Import/Export). Theme Builder je len pri aktívnej téme Gruppa — kód témy sa inak nenačíta.
 * Bez neho: téma vytvorí rovnaké menu „Gruppa Tools“ sama – rovnaký slug, ikona aj pozícia
 * (26, s Gruppa CMS hneď pod ním). Po inštalácii Gruppa Tools menu prevezme hub, nič sa nezdvojí.
 */

defined( 'ABSPATH' ) || exit;

const GRUPPA_THEME_BUILDER = 'gruppa-theme-builder';

/** Prispôsobiť otvorené na paneli Gruppa – dizajn, „zavrieť“ vráti späť do administrácie. */
function gruppa_theme_builder_url() {
	return add_query_arg( 'autofocus[panel]', 'gruppa_design', admin_url( 'customize.php' ) );
}

/** Stránka Theme Builder = presmerovanie do Prispôsobiť (keby presmerovanie nezbehlo, aspoň odkaz). */
function gruppa_theme_builder_page() {
	echo '<div class="wrap"><p><a class="button button-primary" href="' . esc_url( gruppa_theme_builder_url() ) . '">Theme Builder</a></p></div>';
}

add_action( 'after_setup_theme', function () {
	if ( class_exists( 'Gruppa_Tools_Hub' ) ) {
		Gruppa_Tools_Hub::add_module( array(
			'slug'     => GRUPPA_THEME_BUILDER,
			'title'    => 'Theme Builder',
			'callback' => 'gruppa_theme_builder_page',
			'order'    => 20,
			'cap'      => 'edit_theme_options',
			'version'  => GRUPPA_THEME_VERSION,
			'tutorial' => '',
		) );
		return;
	}

	// Bez Gruppa Tools – vlastné menu v rovnakej podobe ako hub.
	add_action( 'admin_menu', function () {
		add_menu_page( 'Gruppa Tools', 'Gruppa Tools', 'edit_theme_options', GRUPPA_THEME_BUILDER, 'gruppa_theme_builder_page', 'dashicons-admin-tools', 26 );
		add_submenu_page( GRUPPA_THEME_BUILDER, 'Theme Builder ‹ Gruppa Tools', 'Theme Builder', 'edit_theme_options', GRUPPA_THEME_BUILDER, 'gruppa_theme_builder_page' );
	}, 20 );

	add_filter( 'custom_menu_order', '__return_true' );
	add_filter( 'menu_order', function ( $order ) {
		$cms = array_search( 'gruppa-types', (array) $order, true );
		$pos = array_search( GRUPPA_THEME_BUILDER, (array) $order, true );
		if ( false === $cms || false === $pos ) {
			return $order;
		}
		unset( $order[ $pos ] );
		$order = array_values( $order );
		array_splice( $order, array_search( 'gruppa-types', $order, true ) + 1, 0, array( GRUPPA_THEME_BUILDER ) );
		return $order;
	}, 99 );
} );

/** Klik na Theme Builder → rovno Prispôsobiť. */
add_action( 'admin_init', function () {
	if ( isset( $_GET['page'] ) && GRUPPA_THEME_BUILDER === $_GET['page'] && current_user_can( 'edit_theme_options' ) ) { // phpcs:ignore
		wp_safe_redirect( gruppa_theme_builder_url() );
		exit;
	}
} );
