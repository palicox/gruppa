<?php
/**
 * Gruppa – základná téma pre Elementor.
 */

defined( 'ABSPATH' ) || exit;

define( 'GRUPPA_THEME_VERSION', '1.3.2' );

/**
 * Prispôsobiť – len v administrácii a v náhľade, bežná návšteva webu ho nenačíta.
 */
if ( is_admin() || is_customize_preview() ) {
	require_once get_template_directory() . '/inc/customizer.php';
}
if ( is_admin() ) {
	require_once get_template_directory() . '/inc/admin.php';
}

/**
 * Podpora funkcií témy a menu.
 */
add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'custom-logo', array( 'flex-width' => true, 'flex-height' => true ) );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );

	// Menu umiestnenia pre Elementor Nav Menu / vlastný kód.
	register_nav_menus( array(
		'primary' => __( 'Primary menu', 'gruppa' ),
		'footer'  => __( 'Footer menu', 'gruppa' ),
	) );
} );

/**
 * Šablóna Canvas aj pre vlastné typy obsahu (CPT), nielen stránky a články.
 */
add_filter( 'theme_templates', function ( $templates, $theme, $post, $post_type ) {
	if ( $post_type && ! isset( $templates['template-canvas.php'] ) && is_post_type_viewable( $post_type ) ) {
		$templates['template-canvas.php'] = __( 'Canvas', 'gruppa' );
	}
	return $templates;
}, 10, 4 );

/**
 * Elementor Theme Builder – všetky miesta (hlavička, päta, single, archív).
 */
add_action( 'elementor/theme/register_locations', function ( $manager ) {
	$manager->register_all_core_location();
} );

/**
 * Elementor preberá farby a písma z témy (Prispôsobiť → Gruppa – dizajn → Písma).
 */
function gruppa_sync_elementor() {
	$value = get_theme_mod( 'gruppa_elementor_inherit', true ) ? 'yes' : '';
	update_option( 'elementor_disable_color_schemes', $value );
	update_option( 'elementor_disable_typography_schemes', $value );
}
add_action( 'after_switch_theme', 'gruppa_sync_elementor' );

/**
 * Je aktuálna stránka postavená v Elementore?
 */
function gruppa_is_elementor() {
	if ( ! is_singular() || ! did_action( 'elementor/loaded' ) ) {
		return false;
	}
	$document = \Elementor\Plugin::$instance->documents->get( get_the_ID() );
	return $document && $document->is_built_with_elementor();
}

/**
 * Štýly.
 *
 * Web: jeden hotový CSS súbor z uploads/gruppa/ (CSS témy + nastavenia, minifikované).
 * Prestaví sa po uložení Prispôsobiť, po aktualizácii témy alebo zmene style.css.
 *
 * CSS sa načítava až po CSS Elementora (priorita 999 + závislosť na jeho štýloch).
 * Pri rovnakej váhe pravidiel vyhráva neskôr načítané CSS — inak by Elementor
 * prebil .container, .row, nadpisy a pod.
 */
add_action( 'wp_enqueue_scripts', function () {
	$deps = array();
	foreach ( wp_styles()->queue as $handle ) {
		if ( 0 === strpos( $handle, 'elementor' ) || 0 === strpos( $handle, 'e-' ) ) {
			$deps[] = $handle;
		}
	}

	// Náhľad v Prispôsobiť – CSS inline, aby sa dalo meniť naživo.
	if ( is_customize_preview() ) {
		gruppa_preview_styles( $deps );
		return;
	}

	$build  = get_option( 'gruppa_css_build' );
	$upload = wp_upload_dir( null, false );
	$dir    = trailingslashit( $upload['basedir'] ) . 'gruppa/';

	if ( ! is_array( $build )
		|| ( $build['theme'] ?? '' ) !== GRUPPA_THEME_VERSION
		|| ( $build['mtime'] ?? 0 ) !== (int) filemtime( get_template_directory() . '/style.css' )
		|| ( ! empty( $build['file'] ) && ! file_exists( $dir . $build['file'] ) )
	) {
		require_once get_template_directory() . '/inc/css.php';
		$build = gruppa_css_build();
	}

	// Písma uložené na webe – preload latin / latin-ext
	if ( ! empty( $build['file'] ) && ! empty( $build['preload'] ) ) {
		$fonts_url = set_url_scheme( trailingslashit( $upload['baseurl'] ) . 'gruppa/fonts/' );
		add_action( 'wp_head', function () use ( $build, $fonts_url ) {
			foreach ( $build['preload'] as $file ) {
				printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $fonts_url . $file ) );
			}
		}, 2 );
	}

	if ( ! empty( $build['fonts'] ) ) {
		wp_enqueue_style( 'gruppa-font', $build['fonts'], array(), null );
		$deps[] = 'gruppa-font';
	}

	if ( ! empty( $build['file'] ) ) {
		wp_enqueue_style( 'gruppa', set_url_scheme( trailingslashit( $upload['baseurl'] ) . 'gruppa/' . $build['file'] ), $deps, null );
	} else {
		wp_register_style( 'gruppa', false, $deps, null );
		wp_enqueue_style( 'gruppa' );
		wp_add_inline_style( 'gruppa', $build['inline'] ?? '' );
	}
}, 999 );

/**
 * Na stránkach z Elementora sa nenačítavajú štýly Gutenbergu.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( gruppa_is_elementor() ) {
		foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'global-styles', 'classic-theme-styles' ) as $handle ) {
			wp_dequeue_style( $handle );
		}
	}
}, 100 );

/**
 * Rýchlejšie pripojenie k Google Fonts (len keď sa načítavajú).
 */
add_filter( 'wp_resource_hints', function ( $urls, $type ) {
	if ( 'preconnect' === $type && wp_style_is( 'gruppa-font', 'enqueued' ) ) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $urls;
}, 10, 2 );

/**
 * Upratanie <head>: emoji, generator, RSD, wlwmanifest, shortlink.
 */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
add_filter( 'emoji_svg_url', '__return_false' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
