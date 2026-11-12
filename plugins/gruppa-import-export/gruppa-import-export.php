<?php
/**
 * Plugin Name:       Gruppa Import/Export
 * Plugin URI:        https://gruppa.cloud
 * Description:       CSV and JSON import and export for Gruppa CMS types, SEO Builder, JetEngine CCT and WordPress post types with ACF fields: automatic field mapping, overwrite by ID, author choice, media by URL, chunked import. Part of Gruppa Tools; works with or without Gruppa CMS.
 * Version:           1.3.4
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Gruppa Taxonomy
 * Author URI:        https://gruppa.cloud
 * License:           GPLv2 or later
 * Text Domain:       gruppa-import-export
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'GRUPPA_IE_VERSION' ) ) {
	return; // Another copy is already loaded (Gruppa CMS 0.29–0.30 with a newer built-in Data Tools, or the old Gruppa Tools bundle).
}

define( 'GRUPPA_IE_VERSION', '1.3.4' );
define( 'GRUPPA_IE_FILE', __FILE__ );
define( 'GRUPPA_IE_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRUPPA_IE_URL', plugin_dir_url( __FILE__ ) );

require_once GRUPPA_IE_DIR . 'hub/loader.php';

add_action(
	'plugins_loaded',
	function () {
		require_once GRUPPA_IE_DIR . 'includes/sources/class-gruppa-ie-source.php';
		require_once GRUPPA_IE_DIR . 'includes/sources/class-gruppa-ie-source-gruppa.php';
		require_once GRUPPA_IE_DIR . 'includes/sources/class-gruppa-ie-source-seo.php';
		require_once GRUPPA_IE_DIR . 'includes/sources/class-gruppa-ie-source-jet.php';
		require_once GRUPPA_IE_DIR . 'includes/sources/class-gruppa-ie-source-acf.php';
		require_once GRUPPA_IE_DIR . 'includes/class-gruppa-ie.php';
		Gruppa_IE::instance();
	},
	10
);
