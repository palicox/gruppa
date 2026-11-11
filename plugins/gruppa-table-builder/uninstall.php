<?php
/**
 * Gruppa Table Builder — runs when the plugin is deleted in WordPress → Plugins.
 * Deletes data ONLY when "Delete the data of a Gruppa Tools add-on when it is deleted"
 * is on (Gruppa CMS → Dashboard → Data). Otherwise everything stays for a reinstall.
 * Also used by the Gruppa Tools bundle (see gruppa-tools/uninstall.php).
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! function_exists( 'gruppa_tb_uninstall' ) ) {
	function gruppa_tb_uninstall() {
		global $wpdb;
		if ( ! get_option( 'gruppa_tools_delete_data' ) ) {
			return;
		}
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'gcms_tables`' ); // phpcs:ignore
		delete_option( 'gruppa_tb_db_version' );
	}
}

if ( ! defined( 'GRUPPA_TOOLS_UNINSTALLING' ) ) {
	if ( is_multisite() ) {
		foreach ( get_sites( array( 'fields' => 'ids' ) ) as $gruppa_site ) {
			switch_to_blog( $gruppa_site );
			gruppa_tb_uninstall();
			restore_current_blog();
		}
	} else {
		gruppa_tb_uninstall();
	}
}
