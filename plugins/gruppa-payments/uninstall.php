<?php
/**
 * Gruppa Payments — runs when the plugin is deleted in WordPress → Plugins.
 * Deletes data ONLY when "Delete the data of a Gruppa Tools add-on when it is deleted"
 * is on (Gruppa CMS → Dashboard → Data). Otherwise everything stays for a reinstall.
 * Payments themselves stay in Stripe / PayPal. Also used by the Gruppa Tools bundle.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! function_exists( 'gruppa_payments_uninstall' ) ) {
	function gruppa_payments_uninstall() {
		global $wpdb;
		wp_clear_scheduled_hook( 'gruppa_payments_daily' );
		if ( ! get_option( 'gruppa_tools_delete_data' ) ) {
			return;
		}
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'gcms_payments`' ); // phpcs:ignore
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'gcms_payment_events`' ); // phpcs:ignore
		foreach ( array( 'gruppa_payments_settings', 'gruppa_payments_db_version', 'gruppa_payments_paypal_plans', 'gruppa_payments_stripe_products' ) as $opt ) {
			delete_option( $opt );
		}
		$wpdb->query( "DELETE FROM `{$wpdb->usermeta}` WHERE meta_key LIKE '\\_gruppa\\_stripe\\_customer\\_%'" ); // phpcs:ignore
		$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE option_name LIKE '\\_transient%gruppa\\_pay\\_%'" ); // phpcs:ignore
	}
}

if ( ! defined( 'GRUPPA_TOOLS_UNINSTALLING' ) ) {
	if ( is_multisite() ) {
		foreach ( get_sites( array( 'fields' => 'ids' ) ) as $gruppa_site ) {
			switch_to_blog( $gruppa_site );
			gruppa_payments_uninstall();
			restore_current_blog();
		}
	} else {
		gruppa_payments_uninstall();
	}
}
