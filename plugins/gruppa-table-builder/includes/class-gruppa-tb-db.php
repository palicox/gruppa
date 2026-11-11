<?php
defined( 'ABSPATH' ) || exit;

/**
 * Table wp_gcms_tables — one row per table definition. Rows of the table are never stored:
 * they are read live (source "items") or from a Repeater field (source "repeater").
 *
 * type_slug  content type inside the data source
 * settings   JSON, see Gruppa_TB::defaults(); settings.origin = gruppa | jet | wp (missing = gruppa)
 */
class Gruppa_TB_DB {

	const DB_VERSION = '1.1.0';
	const OPTION     = 'gruppa_tb_db_version';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'gcms_tables';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			slug varchar(64) NOT NULL DEFAULT '',
			type_slug varchar(64) NOT NULL DEFAULT '',
			author bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NULL DEFAULT NULL,
			modified datetime NULL DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'publish',
			order_index int(11) NOT NULL DEFAULT 0,
			settings longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status)
		) {$charset};" );
		update_option( self::OPTION, self::DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( self::OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	private static function hydrate( $row ) {
		if ( ! $row ) {
			return null;
		}
		$row->id       = (int) $row->id;
		$s             = json_decode( (string) $row->settings, true );
		$row->settings = wp_parse_args( is_array( $s ) ? $s : array(), Gruppa_TB::defaults() );
		return $row;
	}

	public static function get( $id ) {
		global $wpdb;
		$t = self::table();
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id = %d", (int) $id ) ) ); // phpcs:ignore
	}

	/** By ID or slug. */
	public static function get_by( $ref ) {
		if ( is_numeric( $ref ) ) {
			return self::get( (int) $ref );
		}
		global $wpdb;
		$t = self::table();
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE slug = %s", sanitize_key( (string) $ref ) ) ) ); // phpcs:ignore
	}

	public static function all( $status = '' ) {
		global $wpdb;
		$t     = self::table();
		$where = $status ? $wpdb->prepare( ' WHERE status = %s', $status ) : '';
		return array_map( array( __CLASS__, 'hydrate' ), (array) $wpdb->get_results( "SELECT * FROM `{$t}`{$where} ORDER BY order_index ASC, id ASC" ) ); // phpcs:ignore
	}

	public static function counts() {
		global $wpdb;
		$t   = self::table();
		$out = array( 'all' => 0, 'publish' => 0, 'draft' => 0 );
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) n FROM `{$t}` GROUP BY status" ) as $r ) { // phpcs:ignore
			$out[ $r->status ] = (int) $r->n;
			$out['all']       += (int) $r->n;
		}
		return $out;
	}

	private static function unique_slug( $slug, $id = 0 ) {
		global $wpdb;
		$t    = self::table();
		$base = '' !== $slug ? $slug : 'table';
		$try  = $base;
		$n    = 2;
		while ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t}` WHERE slug = %s AND id <> %d", $try, (int) $id ) ) ) { // phpcs:ignore
			$try = substr( $base, 0, 58 ) . '_' . $n++;
		}
		return $try;
	}

	/**
	 * @param array $data name, slug (new only), origin (gruppa | jet | wp), type_slug, status, settings
	 * @return int|WP_Error
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$old  = $id ? self::get( $id ) : null;
		$name = sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' );
		if ( '' === $name ) {
			return new WP_Error( 'name', 'Name' );
		}
		$settings  = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
		$origin    = isset( $data['origin'] ) ? $data['origin'] : ( isset( $settings['origin'] ) ? $settings['origin'] : 'gruppa' );
		$src       = Gruppa_TB_Source::get( sanitize_key( $origin ) );
		$type_slug = Gruppa_TB::key( isset( $data['type_slug'] ) ? $data['type_slug'] : '' );
		if ( ! $src || ! $src->available() || ! $src->exists( $type_slug ) ) {
			return new WP_Error( 'type', 'Content type' );
		}
		$row = array(
			'name'      => $name,
			'type_slug' => $type_slug,
			'status'    => isset( $data['status'] ) && 'draft' === $data['status'] ? 'draft' : 'publish',
			'modified'  => current_time( 'mysql' ),
			'settings'  => wp_json_encode( Gruppa_TB::clean( $settings, $src, $type_slug ) ),
		);
		if ( $old ) {
			$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$slug             = sanitize_key( remove_accents( isset( $data['slug'] ) && '' !== trim( $data['slug'] ) ? $data['slug'] : sanitize_title( $name ) ) );
		$row['slug']      = self::unique_slug( substr( trim( str_replace( '-', '_', $slug ), '_' ), 0, 40 ) );
		$row['author']    = get_current_user_id();
		$row['created']   = $row['modified'];
		$row['order_index'] = (int) $wpdb->get_var( 'SELECT COALESCE(MAX(order_index),0)+1 FROM `' . self::table() . '`' ); // phpcs:ignore
		if ( ! $wpdb->insert( self::table(), $row ) ) {
			return new WP_Error( 'db', $wpdb->last_error ? $wpdb->last_error : 'Database error' );
		}
		return (int) $wpdb->insert_id;
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'status' => 'draft' === $status ? 'draft' : 'publish', 'modified' => current_time( 'mysql' ) ), array( 'id' => (int) $id ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/** Copy as Draft "… (copy)". @return int new ID or 0 */
	public static function duplicate( $id ) {
		$t = self::get( $id );
		if ( ! $t ) {
			return 0;
		}
		$res = self::save( array(
			'name'      => $t->name . ' (copy)',
			'slug'      => $t->slug . '_copy',
			'type_slug' => $t->type_slug,
			'status'    => 'draft',
			'settings'  => $t->settings,
		) );
		return is_wp_error( $res ) ? 0 : (int) $res;
	}
}
