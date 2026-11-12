<?php
/**
 * Plugin Name:       Gruppa CMS
 * Plugin URI:        https://gruppa.cloud
 * Description:       Data layer for WordPress: content types and data types in their own database tables with a field builder, listings, filters and forms, REST API, access rules, Elementor widgets, dynamic tags and Block Bindings. Part of the Gruppa ecosystem with Gruppa Tools (Import/Export, Table Builder, SEO Builder, Payments, Notify).
 * Version:           0.32.5
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Gruppa Taxonomy
 * Author URI:        https://gruppa.cloud
 * License:           GPLv2 or later
 * Text Domain:       gruppa-cms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gruppa CMS replaces the former "Gruppa Core" plugin (same classes, same data).
 * If it is still active, switch it off instead of crashing on duplicate classes.
 */
if ( ! function_exists( 'gruppa_cms_retire_core' ) ) {
	function gruppa_cms_retire_core() {
		$active = (array) get_option( 'active_plugins', array() );
		$keep   = array_values( array_filter( $active, function ( $p ) {
			return 'gruppa-core.php' !== basename( $p );
		} ) );
		if ( count( $keep ) !== count( $active ) ) {
			update_option( 'active_plugins', $keep );
			set_transient( 'gruppa_cms_core_retired', 1, HOUR_IN_SECONDS );
		}
	}
}

$gruppa_cms_core_active = false;
foreach ( (array) get_option( 'active_plugins', array() ) as $gruppa_cms_p ) {
	if ( 'gruppa-core.php' === basename( $gruppa_cms_p ) ) {
		$gruppa_cms_core_active = true;
	}
}
if ( $gruppa_cms_core_active || class_exists( 'Gruppa_DB' ) ) {
	gruppa_cms_retire_core();
	// Activation writes the plugin list after loading us – retire again afterwards.
	add_action( 'activated_plugin', 'gruppa_cms_retire_core' );
	add_action( 'shutdown', 'gruppa_cms_retire_core' );
	return;
}

add_action( 'admin_notices', function () {
	if ( get_transient( 'gruppa_cms_core_retired' ) ) {
		delete_transient( 'gruppa_cms_core_retired' );
		echo '<div class="notice notice-info is-dismissible"><p>Gruppa Core was deactivated – Gruppa CMS replaces it. You can delete Gruppa Core.</p></div>';
	}
} );

define( 'GRUPPA_CMS_VERSION', '0.32.5' );
define( 'GRUPPA_CMS_FILE', __FILE__ );
define( 'GRUPPA_CMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRUPPA_CMS_URL', plugin_dir_url( __FILE__ ) );

/* Core */
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-i18n.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-db.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-fields.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-access.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-types.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-relations.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-items.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-transfer.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-rest.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-render.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-forms.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-form-actions.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-form-render.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-login.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-listing.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-filters.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-duplicate.php';
require_once GRUPPA_CMS_DIR . 'includes/class-gruppa-menu.php';

register_activation_hook( __FILE__, array( 'Gruppa_DB', 'install' ) );
add_action( 'plugins_loaded', array( 'Gruppa_DB', 'maybe_upgrade' ) );
Gruppa_I18n::init();
Gruppa_Items::init();
add_action( 'init', array( 'Gruppa_Types', 'register_post_types' ), 5 );
add_action( 'wp_loaded', array( 'Gruppa_Types', 'maybe_flush_rewrite' ) );
Gruppa_REST::init();
Gruppa_Form_Render::init();
Gruppa_Login::init();
Gruppa_Listing::init();
Gruppa_Filters::init();

/* Modules */
require_once GRUPPA_CMS_DIR . 'modules/blocks/class-gruppa-blocks.php';
require_once GRUPPA_CMS_DIR . 'modules/elementor/class-gruppa-elementor.php';
Gruppa_Blocks::init();
Gruppa_Elementor::init();
require_once GRUPPA_CMS_DIR . 'modules/elementor/class-gruppa-visibility.php';
Gruppa_Visibility::init();

/*
 * 0.31: Import/Export, Table Builder and SEO Builder are Gruppa Tools — separate plugins in the Gruppa Tools
 * menu, working with Gruppa CMS, JetEngine and ACF. Gruppa CMS keeps the hooks they use
 * (gruppa_cms/elementor/widget_instances, gruppa_cms/items_header_actions, gruppa_cms/form_action_*, …).
 */

/* Admin */
if ( is_admin() ) {
	require_once GRUPPA_CMS_DIR . 'admin/class-gruppa-admin.php';
	new Gruppa_Admin();
}
