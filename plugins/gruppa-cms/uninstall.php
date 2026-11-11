<?php
/**
 * Gruppa CMS — runs when the plugin is deleted in WordPress → Plugins.
 *
 * Deletes data ONLY when "Delete all data when Gruppa CMS is deleted" is on
 * (Gruppa CMS → Dashboard → Data). Otherwise everything stays for a reinstall.
 *
 * Removed: content / data types and their item tables, forms, listings (+ their Elementor
 * templates), filter groups, posts of Custom Types, Gruppa CMS options.
 * Kept: normal WordPress posts / pages that items were linked to (they stay as plain
 * posts), users, media, and the data of Gruppa Tools (Table Builder, SEO Builder, Notify, Payments —
 * each plugin removes its own data when it is deleted, see Dashboard → Data).
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

function gruppa_cms_uninstall_site() {
	global $wpdb;
	if ( ! get_option( 'gruppa_cms_delete_data' ) ) {
		return;
	}
	$p      = $wpdb->prefix;
	$exists = function ( $table ) use ( $wpdb ) {
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	};
	// 0.28+: system tables wp_gcms_*, older installs wp_gruppa_types.
	$types_table = $exists( $p . 'gcms_types' ) ? $p . 'gcms_types' : $p . 'gruppa_types';

	// Content types: item tables + their posts.
	if ( $exists( $types_table ) ) {
		$types = (array) $wpdb->get_results( "SELECT slug, has_single, post_type, settings FROM `{$types_table}`" ); // phpcs:ignore
		foreach ( $types as $t ) {
			$slug = preg_replace( '/[^a-z0-9_]/', '', (string) $t->slug );
			if ( '' === $slug ) {
				continue;
			}
			$table    = $exists( $p . 'gcms_type_' . $slug ) ? $p . 'gcms_type_' . $slug : $p . 'gruppa_' . $slug; // 0.28.1+ / older
			$settings = json_decode( (string) $t->settings, true );
			$custom   = is_array( $settings ) && ! empty( $settings['custom_pt'] );
			if ( $exists( $table ) && $t->has_single && $t->post_type ) {
				$ids = $wpdb->get_col( "SELECT wp_post_id FROM `{$table}` WHERE wp_post_id > 0" ); // phpcs:ignore
				foreach ( $ids as $pid ) {
					if ( $custom ) {
						wp_delete_post( (int) $pid, true ); // Its post type disappears with the plugin.
					} else {
						delete_post_meta( (int) $pid, '_gruppa_type' ); // Post / Page stays as a plain post.
						delete_post_meta( (int) $pid, '_gruppa_item' );
					}
				}
			}
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore
		}
	}

	// Listing templates (Elementor).
	$tpl = get_posts( array( 'post_type' => 'gruppa_listing', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $tpl as $pid ) {
		wp_delete_post( (int) $pid, true );
	}

	// System tables of Gruppa CMS. Tables of Gruppa Tools (wp_gcms_tables, wp_gcms_seo, wp_gcms_notify_subs,
	// wp_gcms_payments …) are not touched.
	foreach ( array( 'types', 'forms', 'listings', 'filters', 'relations' ) as $name ) {
		$wpdb->query( "DROP TABLE IF EXISTS `{$p}gcms_{$name}`" ); // phpcs:ignore
	}

	foreach ( array( 'gruppa_cms_db_version', 'gruppa_cms_flush_rewrite', 'gruppa_cms_delete_data' ) as $opt ) {
		delete_option( $opt );
	}
	$wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE option_name LIKE '\\_transient%gruppa\\_cms\\_%'" ); // phpcs:ignore
	flush_rewrite_rules( false );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
		switch_to_blog( (int) $site_id );
		gruppa_cms_uninstall_site();
		restore_current_blog();
	}
} else {
	gruppa_cms_uninstall_site();
}
