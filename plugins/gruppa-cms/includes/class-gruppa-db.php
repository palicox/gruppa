<?php
defined( 'ABSPATH' ) || exit;

class Gruppa_DB {

	const DB_VERSION = '12';

	/**
	 * Tables (0.28.1) — everything of Gruppa under wp_gcms_:
	 *   wp_gcms_{name}       = system tables of Gruppa CMS and Gruppa Tools add-ons (types, forms, payments …)
	 *   wp_gcms_type_{slug}  = items of a content type
	 * A content type can therefore never collide with a system or add-on table.
	 */
	const SYSTEM_PREFIX = 'gcms_';
	const DATA_PREFIX   = 'gcms_type_';

	public static function types_table() {
		return self::system_table( 'types' );
	}

	/** System tables of Gruppa CMS (wp_gcms_{name}). */
	const SYSTEM_TABLES = array( 'types', 'forms', 'seo', 'listings', 'filters', 'relations' );

	/** Slugs a content type can never have (system tables now + room for future modules). */
	const RESERVED_SLUGS = array( 'gcms', 'tables', 'payments', 'payment_events', 'notify_subs', 'media', 'css_styles', 'types', 'forms', 'seo', 'listings', 'listing', 'templates', 'filters', 'relations', 'relation', 'queries', 'settings', 'options', 'meta', 'users', 'user', 'log', 'logs', 'actions', 'submissions', 'notify', 'io', 'system', 'cms', 'core' );

	/** wp_gcms_{name} — also used by Gruppa Tools add-ons (tables, notify_subs, payments …). */
	public static function system_table( $name ) {
		global $wpdb;
		return $wpdb->prefix . self::SYSTEM_PREFIX . $name;
	}

	public static function forms_table() {
		return self::system_table( 'forms' );
	}

	public static function seo_table() {
		return self::system_table( 'seo' );
	}

	public static function listings_table() {
		return self::system_table( 'listings' );
	}

	public static function filters_table() {
		return self::system_table( 'filters' );
	}

	public static function is_reserved( $slug ) {
		return in_array( (string) $slug, self::RESERVED_SLUGS, true );
	}

	public static function table_exists( $table ) {
		global $wpdb;
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	public static function data_table( $slug ) {
		global $wpdb;
		return $wpdb->prefix . self::DATA_PREFIX . $slug;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::types_table();
		$charset = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			slug varchar(64) NOT NULL DEFAULT '',
			author bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NULL DEFAULT NULL,
			modified datetime NULL DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'publish',
			order_index int(11) NOT NULL DEFAULT 0,
			has_single tinyint(1) NOT NULL DEFAULT 0,
			post_type varchar(20) NOT NULL DEFAULT '',
			fields longtext NULL,
			settings longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status),
			KEY order_index (order_index)
		) {$charset};" );

		$forms = self::forms_table();
		dbDelta( "CREATE TABLE {$forms} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			slug varchar(64) NOT NULL DEFAULT '',
			author bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NULL DEFAULT NULL,
			modified datetime NULL DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'publish',
			order_index int(11) NOT NULL DEFAULT 0,
			type_slug varchar(64) NOT NULL DEFAULT '',
			settings longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY type_slug (type_slug),
			KEY order_index (order_index)
		) {$charset};" );

		$seo = self::seo_table();
		dbDelta( "CREATE TABLE {$seo} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_type varchar(64) NOT NULL DEFAULT '',
			owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
			author bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NULL DEFAULT NULL,
			modified datetime NULL DEFAULT NULL,
			seo_robots varchar(40) NOT NULL DEFAULT 'index,follow',
			seo_title varchar(255) NOT NULL DEFAULT '',
			seo_description text NULL,
			seo_keywords varchar(255) NOT NULL DEFAULT '',
			seo_canonical varchar(500) NOT NULL DEFAULT '',
			seo_og_title varchar(255) NOT NULL DEFAULT '',
			seo_og_description text NULL,
			seo_og_image bigint(20) unsigned NOT NULL DEFAULT 0,
			seo_og_type varchar(20) NOT NULL DEFAULT '',
			seo_twitter_card varchar(30) NOT NULL DEFAULT '',
			seo_twitter_title varchar(255) NOT NULL DEFAULT '',
			seo_twitter_description text NULL,
			seo_twitter_image bigint(20) unsigned NOT NULL DEFAULT 0,
			seo_schema longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY owner (owner_type,owner_id)
		) {$charset};" );

		$listings = self::listings_table();
		dbDelta( "CREATE TABLE {$listings} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			slug varchar(64) NOT NULL DEFAULT '',
			author bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NULL DEFAULT NULL,
			modified datetime NULL DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'publish',
			order_index int(11) NOT NULL DEFAULT 0,
			type_slug varchar(64) NOT NULL DEFAULT '',
			template_id bigint(20) unsigned NOT NULL DEFAULT 0,
			settings longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY type_slug (type_slug)
		) {$charset};" );

		$filters = self::filters_table();
		dbDelta( "CREATE TABLE {$filters} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			slug varchar(64) NOT NULL DEFAULT '',
			author bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NULL DEFAULT NULL,
			modified datetime NULL DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'publish',
			order_index int(11) NOT NULL DEFAULT 0,
			type_slug varchar(64) NOT NULL DEFAULT '',
			settings longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY type_slug (type_slug)
		) {$charset};" );

		Gruppa_Relations::install();

		// Item tables of existing entities: dbDelta adds missing columns.
		if ( class_exists( 'Gruppa_Types' ) ) {
			foreach ( Gruppa_Types::all() as $type ) {
				self::create_data_table( $type->slug, $type->fields, $type->kind );
			}
		}

		update_option( 'gruppa_cms_db_version', self::DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'gruppa_cms_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function column_sql( $type ) {
		switch ( $type ) {
			case 'number':
				return 'decimal(20,4) NULL DEFAULT NULL';
			case 'date':
				return 'date NULL DEFAULT NULL';
			case 'switcher':
				return 'tinyint(1) NOT NULL DEFAULT 0';
			case 'time':
				return 'time NULL DEFAULT NULL';
			case 'html':
				return ''; // No column.
			default:
				return 'longtext NULL';
		}
	}

	public static function create_data_table( $slug, $fields = array(), $kind = '' ) {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::data_table( $slug );
		$charset = $wpdb->get_charset_collate();

		$cols = '';
		$keys = array();
		foreach ( $fields as $f ) {
			if ( Gruppa_Fields::is_virtual( $f ) ) {
				continue; // HTML: shown in the editor only.
			}
			$cols  .= "\t\t\t" . $f['key'] . ' ' . self::column_sql( $f['type'] ) . ",\n";
			$keys[] = $f['key'];
		}
		// Data entities: group of the value. Skipped when a field already uses the name.
		$group = '';
		if ( 'data' === $kind ) {
			foreach ( array( 'group_id' => 'int(11) NOT NULL DEFAULT 0', 'group_title' => "varchar(191) NOT NULL DEFAULT ''", 'group_slug' => "varchar(64) NOT NULL DEFAULT ''" ) as $gk => $gsql ) {
				if ( ! in_array( $gk, $keys, true ) ) {
					$group .= "\t\t\t{$gk} {$gsql},\n";
				}
			}
		}
		$gidx = false !== strpos( $group, 'group_id ' ) ? "\t\t\tKEY group_id (group_id),\n" : '';

		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(191) NOT NULL DEFAULT '',
			slug varchar(191) NOT NULL DEFAULT '',
			author bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NULL DEFAULT NULL,
			modified datetime NULL DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'publish',
			order_index int(11) NOT NULL DEFAULT 0,
			wp_post_id bigint(20) unsigned NULL DEFAULT NULL,
{$group}{$cols}			PRIMARY KEY  (id),
			KEY slug (slug),
			KEY status (status),
			KEY order_index (order_index),
{$gidx}			KEY wp_post_id (wp_post_id)
		) {$charset};" );

		return self::data_table_exists( $slug );
	}

	public static function columns( $slug ) {
		global $wpdb;
		$table = self::data_table( $slug );
		return (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" ); // phpcs:ignore
	}

	public static function rename_column( $slug, $from, $to, $type ) {
		global $wpdb;
		$cols = self::columns( $slug );
		if ( ! in_array( $from, $cols, true ) || in_array( $to, $cols, true ) ) {
			return false;
		}
		$table = self::data_table( $slug );
		return false !== $wpdb->query( "ALTER TABLE `{$table}` CHANGE `{$from}` `{$to}` " . self::column_sql( $type ) ); // phpcs:ignore
	}

	public static function data_table_exists( $slug ) {
		global $wpdb;
		$table = self::data_table( $slug );
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
	}

	public static function count_items( $slug ) {
		global $wpdb;
		if ( ! self::data_table_exists( $slug ) ) {
			return 0;
		}
		$table = self::data_table( $slug );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore
	}

	public static function drop_data_table( $slug ) {
		global $wpdb;
		$table = self::data_table( $slug );
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore
	}

	/**
	 * Save order: $table_key is 'types' or a type slug, $ids in desired order.
	 */
	public static function reorder( $table_key, $ids, $offset = 0 ) {
		global $wpdb;
		$table = self::table_for( $table_key );
		$i     = (int) $offset;
		foreach ( (array) $ids as $id ) {
			$wpdb->update( $table, array( 'order_index' => $i++ ), array( 'id' => (int) $id ) );
		}
	}

	private static function table_for( $key ) {
		if ( 'types' === $key ) {
			return self::types_table();
		}
		if ( '_forms' === $key ) {
			return self::forms_table();
		}
		if ( '_listings' === $key ) {
			return self::listings_table();
		}
		if ( '_filters' === $key ) {
			return self::filters_table();
		}
		return self::data_table( $key );
	}

	public static function next_order( $table_key ) {
		global $wpdb;
		$table = self::table_for( $table_key );
		return (int) $wpdb->get_var( "SELECT COALESCE(MAX(order_index),-1)+1 FROM `{$table}`" ); // phpcs:ignore
	}
}
