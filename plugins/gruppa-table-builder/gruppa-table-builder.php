<?php
/**
 * Plugin Name:       Gruppa Table Builder
 * Plugin URI:        https://gruppa.cloud
 * Description:       Tables from Gruppa CMS types, JetEngine CCT and WordPress post types with ACF / JetEngine fields, or from the Repeater rows of the current item. Columns with formats, sortable headers, cards on mobile, filters, shortcode and the Elementor widget Gruppa Table. Part of Gruppa Tools; works with or without Gruppa CMS.
 * Version:           0.7.3
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Gruppa Taxonomy
 * Author URI:        https://gruppa.cloud
 * License:           GPLv2 or later
 * Text Domain:       gruppa-table-builder
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'GRUPPA_TB_VERSION' ) ) {
	return; // Another copy is already loaded (e.g. the old Gruppa Tools bundle).
}

define( 'GRUPPA_TB_VERSION', '0.7.3' );
define( 'GRUPPA_TB_FILE', __FILE__ );
define( 'GRUPPA_TB_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRUPPA_TB_URL', plugin_dir_url( __FILE__ ) );

require_once GRUPPA_TB_DIR . 'hub/loader.php';
require_once GRUPPA_TB_DIR . 'includes/sources/class-gruppa-tb-source.php';
require_once GRUPPA_TB_DIR . 'includes/sources/class-gruppa-tb-source-gruppa.php';
require_once GRUPPA_TB_DIR . 'includes/sources/class-gruppa-tb-source-jet.php';
require_once GRUPPA_TB_DIR . 'includes/sources/class-gruppa-tb-source-wp.php';
require_once GRUPPA_TB_DIR . 'includes/class-gruppa-tb-db.php';
require_once GRUPPA_TB_DIR . 'includes/class-gruppa-tb.php';
require_once GRUPPA_TB_DIR . 'admin/class-gruppa-tb-admin.php';

register_activation_hook( __FILE__, array( 'Gruppa_TB_DB', 'install' ) );

add_action(
	'plugins_loaded',
	function () {
		Gruppa_TB_DB::maybe_upgrade(); // Also on the website: tables are read there.
		Gruppa_TB::init();
		if ( is_admin() ) {
			Gruppa_TB_Admin::instance();
		}
	},
	10
);

/*
 * Elementor widget "Gruppa Table". With Gruppa CMS it joins the Gruppa CMS widgets in their order
 * (Text, Search, Listing, Table, Form, Login); without it, it registers on its own.
 */
add_filter(
	'gruppa_cms/elementor/widget_instances',
	function ( $list ) {
		require_once GRUPPA_TB_DIR . 'includes/class-gruppa-tb-widget.php';
		$list[] = new Gruppa_TB_Widget();
		return $list;
	}
);
add_action(
	'elementor/widgets/register',
	function ( $widgets ) {
		if ( class_exists( 'Gruppa_Elementor' ) ) {
			return; // Registered through the filter above.
		}
		require_once GRUPPA_TB_DIR . 'includes/class-gruppa-tb-widget.php';
		$widgets->register( new Gruppa_TB_Widget() );
	}
);
