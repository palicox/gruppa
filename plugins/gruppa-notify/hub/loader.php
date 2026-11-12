<?php
/**
 * Gruppa Tools hub — loader.
 *
 * Every Gruppa Tools plugin (Import/Export, Table Builder, SEO Builder, Payments, Notify) ships an identical
 * copy of the /hub folder and requires this file. When several are active, only the newest hub version is
 * loaded — once — on plugins_loaded (priority 1). The Gruppa theme adds Theme Builder to the same menu.
 *
 * Plugins register their admin page on plugins_loaded (priority ≥ 2):
 *
 *     Gruppa_Tools_Hub::add_module( array(
 *         'slug'     => 'gruppa-notify',
 *         'title'    => 'Notify',
 *         'callback' => array( $this, 'render' ),
 *     ) );
 */

defined( 'ABSPATH' ) || exit;

$gruppa_tools_hub_version = '1.6.3';

if ( empty( $GLOBALS['gruppa_tools_hub'] ) || version_compare( $GLOBALS['gruppa_tools_hub']['version'], $gruppa_tools_hub_version, '<' ) ) {
	$GLOBALS['gruppa_tools_hub'] = array(
		'version' => $gruppa_tools_hub_version,
		'dir'     => __DIR__ . '/',
		'url'     => plugin_dir_url( __FILE__ ),
	);
}

if ( ! function_exists( 'gruppa_tools_hub_boot' ) ) {
	function gruppa_tools_hub_boot() {
		if ( class_exists( 'Gruppa_Tools_Hub' ) || empty( $GLOBALS['gruppa_tools_hub'] ) ) {
			return;
		}
		$hub = $GLOBALS['gruppa_tools_hub'];
		require_once $hub['dir'] . 'class-gruppa-tools-hub.php';
		Gruppa_Tools_Hub::boot( $hub['version'], $hub['url'] );
	}
	add_action( 'plugins_loaded', 'gruppa_tools_hub_boot', 1 );
}
