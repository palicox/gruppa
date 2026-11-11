<?php
defined( 'ABSPATH' ) || exit;

/**
 * Redirects (1.4.0): old address → new address with 301 / 302 / 307, or 410 Gone.
 *
 * Table wp_gcms_seo_redirects. Source = path on this website ("/stara-stranka/"), optionally with a query
 * ("/?p=12"); a trailing "*" matches everything that starts with it ("/blog/*"), and a "*" at the end of the
 * target is replaced with the rest of the address. Matching ignores letter case and the trailing slash.
 * Runs on template_redirect (priority 1) — before WordPress' own canonical redirects; also with another SEO
 * plugin active (the redirects are data of the website, not output of SEO tags).
 * Rules are cached in the option gruppa_seo_redirects_map (rebuilt on every change).
 */
class Gruppa_SEO_Redirects {

	const DB_VERSION = 1;
	const DB_OPTION  = 'gruppa_seo_redirects_db';
	const MAP        = 'gruppa_seo_redirects_map';
	const CODES      = array(
		301 => '301 Moved permanently',
		302 => '302 Found (temporary)',
		307 => '307 Temporary redirect',
		410 => '410 Gone (deleted, no target)',
	);

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'gcms_seo_redirects';
	}

	public static function init() {
		self::maybe_install();
		if ( ! is_admin() ) {
			add_action( 'template_redirect', array( __CLASS__, 'run' ), 1 );
		}
	}

	public static function maybe_install() {
		global $wpdb;
		if ( (int) get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source varchar(500) NOT NULL DEFAULT '',
			target varchar(1000) NOT NULL DEFAULT '',
			code smallint(3) unsigned NOT NULL DEFAULT 301,
			hits bigint(20) unsigned NOT NULL DEFAULT 0,
			last_hit datetime NULL DEFAULT NULL,
			created datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY source (source(191))
		) " . $wpdb->get_charset_collate() . ';' );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/* ---------- Data ---------- */

	/** "/Stara-Stranka/" or "https://web.sk/stara-stranka" → "/stara-stranka" (query kept, trailing slash off). */
	public static function normalize( $path ) {
		$path = trim( (string) $path );
		if ( preg_match( '#^https?://#i', $path ) ) {
			$p    = wp_parse_url( $path );
			$path = ( isset( $p['path'] ) ? $p['path'] : '/' ) . ( isset( $p['query'] ) ? '?' . $p['query'] : '' );
		}
		$path = '/' . ltrim( rawurldecode( $path ), '/' );
		// Subfolder installs: paths are relative to the home address.
		$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $base && 0 === stripos( $path, $base . '/' ) ) {
			$path = substr( $path, strlen( $base ) );
		}
		$q    = '';
		$qpos = strpos( $path, '?' );
		if ( false !== $qpos ) {
			$q    = substr( $path, $qpos );
			$path = substr( $path, 0, $qpos );
		}
		$path = '/' === $path ? '/' : rtrim( $path, '/' );
		return mb_strtolower( $path . $q );
	}

	public static function all( $s = '' ) {
		global $wpdb;
		$where = '';
		if ( '' !== $s ) {
			$like  = '%' . $wpdb->esc_like( $s ) . '%';
			$where = $wpdb->prepare( 'WHERE source LIKE %s OR target LIKE %s', $like, $like );
		}
		return (array) $wpdb->get_results( 'SELECT * FROM `' . self::table() . "` {$where} ORDER BY source ASC" ); // phpcs:ignore
	}

	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . self::table() . '`' ); // phpcs:ignore
	}

	/** @return int|WP_Error */
	public static function add( $source, $target, $code = 301 ) {
		global $wpdb;
		$code   = isset( self::CODES[ (int) $code ] ) ? (int) $code : 301;
		$src    = self::normalize( $source );
		$target = trim( (string) $target );
		if ( '' === trim( (string) $source ) ) {
			return new WP_Error( 'source', 'The old address is missing.' );
		}
		if ( 410 !== $code && '' === $target ) {
			return new WP_Error( 'target', 'The new address is missing for ' . $source . '.' );
		}
		if ( 410 === $code ) {
			$target = '';
		} elseif ( '/' === $target[0] ) {
			$target = '/' . ltrim( $target, '/' );
		} else {
			$target = esc_url_raw( $target );
		}
		if ( 410 !== $code && self::normalize( $target ) === $src && false === strpos( $src, '*' ) ) {
			return new WP_Error( 'loop', 'The old and the new address are the same: ' . $source . '.' );
		}
		$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM `' . self::table() . '` WHERE source = %s', $src ) ); // phpcs:ignore
		$row = array( 'source' => $src, 'target' => $target, 'code' => $code );
		if ( $id ) {
			$wpdb->update( self::table(), $row, array( 'id' => $id ) );
		} else {
			$row['created'] = current_time( 'mysql' );
			$wpdb->insert( self::table(), $row );
			$id = (int) $wpdb->insert_id;
		}
		self::rebuild();
		return $id;
	}

	public static function delete( $ids ) {
		global $wpdb;
		foreach ( (array) $ids as $id ) {
			$wpdb->delete( self::table(), array( 'id' => (int) $id ) );
		}
		self::rebuild();
	}

	/** Cache: [ 'exact' => [ source => [ id, target, code ] ], 'wild' => [ prefix => [ … ] ] ]. */
	public static function rebuild() {
		$map = array( 'exact' => array(), 'wild' => array() );
		foreach ( self::all() as $r ) {
			$rule = array( (int) $r->id, (string) $r->target, (int) $r->code );
			if ( '*' === substr( $r->source, -1 ) ) {
				$map['wild'][ substr( $r->source, 0, -1 ) ] = $rule;
			} else {
				$map['exact'][ $r->source ] = $rule;
			}
		}
		uksort( $map['wild'], function ( $a, $b ) {
			return strlen( $b ) - strlen( $a ); // Longest prefix first.
		} );
		update_option( self::MAP, $map, true );
	}

	/* ---------- Website ---------- */

	public static function run() {
		$map = get_option( self::MAP );
		if ( ! is_array( $map ) || ( empty( $map['exact'] ) && empty( $map['wild'] ) ) ) {
			return;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore
		$full = self::normalize( $uri );
		$path = strtok( $full, '?' );
		$rule = null;
		$rest = '';
		if ( isset( $map['exact'][ $full ] ) ) {
			$rule = $map['exact'][ $full ];
		} elseif ( isset( $map['exact'][ $path ] ) ) {
			$rule = $map['exact'][ $path ];
		} else {
			foreach ( (array) $map['wild'] as $prefix => $r ) {
				if ( 0 === strpos( $full, $prefix ) ) {
					$rule = $r;
					$rest = (string) substr( $full, strlen( $prefix ) );
					break;
				}
			}
		}
		if ( ! $rule ) {
			return;
		}
		list( $id, $target, $code ) = $rule;
		self::hit( $id );
		if ( 410 === $code ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 410 );
			nocache_headers();
			return; // The theme shows its 404 page with status 410.
		}
		if ( '*' === substr( $target, -1 ) ) {
			$target = substr( $target, 0, -1 ) . ltrim( $rest, '/' );
		}
		$url = '/' === $target[0] ? home_url( $target ) : $target;
		if ( self::normalize( $url ) === $full ) {
			return; // Never redirect to itself.
		}
		wp_redirect( $url, $code, 'Gruppa SEO Builder' ); // phpcs:ignore WordPress.Security.SafeRedirect — targets are set by the admin.
		exit;
	}

	private static function hit( $id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE `' . self::table() . '` SET hits = hits + 1, last_hit = %s WHERE id = %d', current_time( 'mysql' ), (int) $id ) ); // phpcs:ignore
	}
}
