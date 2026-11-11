<?php
/**
 * Gruppa SEO Builder — runs when the plugin is deleted in WordPress → Plugins.
 *
 * Deletes data ONLY when "Delete the data of a Gruppa Tools plugin when it is deleted" is on
 * (Gruppa CMS → Dashboard → Data, option gruppa_tools_delete_data). Otherwise everything stays for a reinstall.
 * Removed: tables wp_gcms_seo and wp_gcms_seo_redirects, options gruppa_seo_settings, gruppa_seo_db_version,
 * gruppa_seo_redirects_db and gruppa_seo_redirects_map.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

function gruppa_seo_uninstall_site() {
	global $wpdb;
	if ( ! get_option( 'gruppa_tools_delete_data' ) ) {
		return;
	}
	$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'gcms_seo`' ); // phpcs:ignore
	$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'gcms_seo_redirects`' ); // phpcs:ignore
	foreach ( array( 'gruppa_seo_settings', 'gruppa_seo_db_version', 'gruppa_seo_redirects_db', 'gruppa_seo_redirects_map' ) as $opt ) {
		delete_option( $opt );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $gruppa_site ) {
		switch_to_blog( (int) $gruppa_site );
		gruppa_seo_uninstall_site();
		restore_current_blog();
	}
} else {
	gruppa_seo_uninstall_site();
}
