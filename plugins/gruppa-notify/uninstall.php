<?php
/**
 * Gruppa Notify — runs when the plugin is deleted in WordPress → Plugins.
 * Deletes data ONLY when "Delete the data of a Gruppa Tools add-on when it is deleted"
 * is on (Gruppa CMS → Dashboard → Data). Otherwise everything stays for a reinstall.
 * Also used by the Gruppa Tools bundle (see gruppa-tools/uninstall.php).
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! function_exists( 'gruppa_notify_uninstall' ) ) {
	function gruppa_notify_uninstall() {
		global $wpdb;
		if ( ! get_option( 'gruppa_tools_delete_data' ) ) {
			return;
		}
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'gcms_notify_subs`' ); // phpcs:ignore
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'gcms_notify_log`' ); // phpcs:ignore — 1.3.0
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'gruppa_notify_subs`' ); // phpcs:ignore — before 1.1.0
		foreach ( array( 'gruppa_notify_settings', 'gruppa_notify_vapid', 'gruppa_notify_last_id', 'gruppa_notify_db', 'gruppa_notify_db_version', 'gruppa_tools_notify_db' ) as $opt ) {
			delete_option( $opt );
		}
		wp_clear_scheduled_hook( 'gruppa_notify_sweep' );
	}
}

if ( ! defined( 'GRUPPA_TOOLS_UNINSTALLING' ) ) {
	if ( is_multisite() ) {
		foreach ( get_sites( array( 'fields' => 'ids' ) ) as $gruppa_site ) {
			switch_to_blog( $gruppa_site );
			gruppa_notify_uninstall();
			restore_current_blog();
		}
	} else {
		gruppa_notify_uninstall();
	}
}
