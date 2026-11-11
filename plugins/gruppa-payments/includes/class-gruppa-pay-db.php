<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tables (system prefix wp_gcms_, shared with Gruppa CMS 0.28+):
 *
 *   wp_gcms_payments        one row per payment or subscription
 *                           kind = payment       one-off payment or one paid period of a subscription (parent_id)
 *                           kind = subscription  the subscription itself (status active / past_due / canceled / expired)
 *   wp_gcms_payment_events  webhook log + idempotency (event_id is unique per provider)
 *
 * Money is stored in minor units (cents) in `amount` with `currency` (ISO 4217).
 */
class Gruppa_Pay_DB {

	const DB_VERSION = '1';
	const OPTION     = 'gruppa_payments_db_version';

	public static function payments() {
		global $wpdb;
		return $wpdb->prefix . 'gcms_payments';
	}

	public static function events() {
		global $wpdb;
		return $wpdb->prefix . 'gcms_payment_events';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$p       = self::payments();
		$e       = self::events();

		dbDelta( "CREATE TABLE {$p} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uid char(32) NOT NULL DEFAULT '',
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			provider varchar(10) NOT NULL DEFAULT '',
			mode varchar(4) NOT NULL DEFAULT 'test',
			kind varchar(12) NOT NULL DEFAULT 'payment',
			status varchar(20) NOT NULL DEFAULT 'pending',
			title varchar(191) NOT NULL DEFAULT '',
			amount bigint(20) NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT 'EUR',
			period varchar(10) NOT NULL DEFAULT 'once',
			price_id varchar(64) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email varchar(191) NOT NULL DEFAULT '',
			object_type varchar(64) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			form_id bigint(20) unsigned NOT NULL DEFAULT 0,
			customer_ref varchar(64) NOT NULL DEFAULT '',
			checkout_ref varchar(128) NOT NULL DEFAULT '',
			payment_ref varchar(128) NOT NULL DEFAULT '',
			subscription_ref varchar(64) NOT NULL DEFAULT '',
			paid_at datetime NULL DEFAULT NULL,
			paid_until date NULL DEFAULT NULL,
			created datetime NULL DEFAULT NULL,
			modified datetime NULL DEFAULT NULL,
			meta longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uid (uid),
			KEY parent_id (parent_id),
			KEY status (status),
			KEY kind_mode (kind,mode),
			KEY object (object_type,object_id),
			KEY user_id (user_id),
			KEY checkout_ref (checkout_ref),
			KEY payment_ref (payment_ref),
			KEY subscription_ref (subscription_ref)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$e} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			provider varchar(10) NOT NULL DEFAULT '',
			event_id varchar(128) NOT NULL DEFAULT '',
			type varchar(80) NOT NULL DEFAULT '',
			payment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			result varchar(191) NOT NULL DEFAULT '',
			created datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event (provider,event_id),
			KEY payment_id (payment_id),
			KEY created (created)
		) {$charset};" );

		update_option( self::OPTION, self::DB_VERSION, false );
		Gruppa_Payments::schedule();
	}

	public static function maybe_install() {
		if ( get_option( self::OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}
}
