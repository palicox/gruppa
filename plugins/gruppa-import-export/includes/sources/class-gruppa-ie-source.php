<?php
/**
 * Gruppa Import/Export — base class of an import / export source.
 *
 * A source is a family of content types (JetEngine CCT, Gruppa CMS types,
 * WordPress post types with ACF). Every type is addressed as "source:slug".
 */

defined( 'ABSPATH' ) || exit;

abstract class Gruppa_IE_Source {

	/** Short id used in "id:slug" references. */
	abstract public function id();

	/** Group label in the type select. */
	abstract public function label();

	/** Whether the underlying plugin is active. */
	abstract public function available();

	/** @return array slug => [ 'name' => string, 'count' => int ] */
	abstract public function types();

	/**
	 * All mappable fields of a type, in display order.
	 *
	 * @return array list of [ key, label, hint, auto ]
	 *               hint = field type / description, auto = may be auto-mapped by name.
	 */
	abstract public function fields( $slug );

	/** Key of the ID field (mapping it means "update existing"). */
	abstract public function id_key();

	/** Keys considered system columns (export toggle). */
	abstract public function system_keys();

	/**
	 * @return array|WP_Error [ columns, items ]
	 */
	abstract public function export( $slug, $status, $system );

	/**
	 * Import one chunk.
	 *
	 * @param array $mapping file column => field key
	 * @return array|WP_Error { created, updated, skipped, errors[] }
	 */
	abstract public function import( $slug, $mapping, $rows, $offset, $author, $keep_author );

	public function has_type( $slug ) {
		$types = $this->types();
		return isset( $types[ $slug ] );
	}

	/* ---------- shared helpers ---------- */

	protected static function result() {
		return array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => array(),
		);
	}

	protected static function scalar( $v ) {
		return is_array( $v ) || is_object( $v ) ? '' : trim( (string) $v );
	}

	/** Value → list: arrays, JSON arrays, or "a, b | c". */
	protected static function to_list( $v ) {
		if ( is_array( $v ) ) {
			return array_values( array_filter( array_map( 'strval', array_filter( $v, 'is_scalar' ) ), 'strlen' ) );
		}
		$v = trim( (string) $v );
		if ( '' === $v ) {
			return array();
		}
		if ( '[' === $v[0] ) {
			$d = json_decode( $v, true );
			if ( is_array( $d ) ) {
				return self::to_list( $d );
			}
		}
		return array_values( array_filter( array_map( 'trim', preg_split( '/[,|]/', $v ) ), 'strlen' ) );
	}

	/** JSON string → array when it looks like JSON, otherwise unchanged. */
	protected static function maybe_json( $v ) {
		if ( is_string( $v ) ) {
			$s = trim( $v );
			if ( '' !== $s && in_array( $s[0], array( '[', '{' ), true ) ) {
				$d = json_decode( $s, true );
				if ( is_array( $d ) ) {
					return $d;
				}
			}
		}
		return $v;
	}

	protected static function truthy( $v ) {
		$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( self::scalar( $v ) ) : strtolower( self::scalar( $v ) );
		return in_array( $s, array( '1', 'true', 'yes', 'on', 'ano', 'áno', 'y' ), true ) ? 1 : 0;
	}

	/** Date / datetime string → format, accepting d.m.Y too. Null when invalid. */
	protected static function date( $v, $format = 'Y-m-d H:i:s' ) {
		$s = self::scalar( $v );
		if ( '' === $s ) {
			return null;
		}
		if ( preg_match( '/^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})(.*)$/', $s, $m ) ) {
			$s = sprintf( '%04d-%02d-%02d%s', $m[3], $m[2], $m[1], $m[4] );
		}
		$ts = strtotime( $s );
		return $ts ? gmdate( $format, $ts ) : null;
	}

	protected static function number( $v ) {
		$s = str_replace( array( ' ', "\xC2\xA0" ), '', self::scalar( $v ) );
		if ( preg_match( '/^-?\d+,\d+$/', $s ) ) {
			$s = str_replace( ',', '.', $s );
		}
		return $s;
	}

	/** Meta key with the URL an attachment was downloaded from (prevents duplicates on re-import). */
	const SOURCE_URL_META = '_gruppa_ie_source_url';

	/** @var array URL => attachment ID resolved in this request. */
	private static $media_cache = array();

	/**
	 * Attachment ID from an ID or URL; 0 when unknown.
	 *
	 * A URL of this site resolves to its attachment. A URL of another site is downloaded
	 * into the media library once; repeated imports reuse the same attachment.
	 */
	protected static function attachment_id( $v ) {
		if ( is_array( $v ) ) {
			// JetEngine "both" format { id, url } — the URL is portable, the ID is not.
			$v = ! empty( $v['url'] ) ? $v['url'] : ( isset( $v['id'] ) ? $v['id'] : '' );
		}
		$s = self::scalar( $v );
		if ( '' === $s ) {
			return 0;
		}
		if ( ctype_digit( $s ) ) {
			return (int) $s;
		}
		if ( ! filter_var( $s, FILTER_VALIDATE_URL ) ) {
			return 0;
		}
		if ( isset( self::$media_cache[ $s ] ) ) {
			return self::$media_cache[ $s ];
		}

		$id = (int) attachment_url_to_postid( $s );
		if ( ! $id ) {
			$id = self::local_attachment( $s );
		}
		if ( ! $id ) {
			$found = get_posts(
				array(
					'post_type'        => 'attachment',
					'post_status'      => 'any',
					'meta_key'         => self::SOURCE_URL_META, // phpcs:ignore
					'meta_value'       => $s, // phpcs:ignore
					'fields'           => 'ids',
					'posts_per_page'   => 1,
					'suppress_filters' => true,
				)
			);
			$id = $found ? (int) $found[0] : 0;
		}
		if ( ! $id && apply_filters( 'gruppa_ie/sideload_media', true, $s ) ) {
			$id = self::sideload( $s );
		}

		self::$media_cache[ $s ] = $id;
		return $id;
	}

	/**
	 * Attachment already in this site's media library for a URL of any domain:
	 * 1. the same path inside uploads ("2025/09/a.png", "a.png"),
	 * 2. the same file name anywhere in uploads (uploaded by hand into 2026/11/ …), also "-scaled".
	 */
	private static function local_attachment( $url ) {
		global $wpdb;
		$path = rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '' === $path ) {
			return 0;
		}
		$rel = $path;
		$pos = strpos( $path, '/uploads/' );
		if ( false !== $pos ) {
			$rel = substr( $path, $pos + 9 );
		}
		$rel = ltrim( $rel, '/' );

		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1", $rel ) );
		if ( $id || ! apply_filters( 'gruppa_ie/match_media_by_name', true, $url ) ) {
			return $id;
		}

		$name = wp_basename( $rel );
		if ( '' === $name ) {
			return 0;
		}
		$ext    = pathinfo( $name, PATHINFO_EXTENSION );
		$scaled = $ext ? substr( $name, 0, -strlen( $ext ) - 1 ) . '-scaled.' . $ext : '';
		$names  = array_filter( array( $name, $scaled ) );
		foreach ( $names as $n ) {
			$id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = 'attachment'
					 WHERE m.meta_key = '_wp_attached_file' AND ( m.meta_value = %s OR m.meta_value LIKE %s ) ORDER BY m.post_id DESC LIMIT 1",
					$n,
					'%/' . $wpdb->esc_like( $n )
				)
			);
			if ( $id ) {
				return $id;
			}
		}
		return 0;
	}

	/** Seconds one import request may run before it returns (filter gruppa_ie/time_budget). */
	protected static function time_up() {
		$start  = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true );
		$budget = (float) apply_filters( 'gruppa_ie/time_budget', 20 );
		return microtime( true ) - $start > $budget;
	}

	/** Download a file from a URL into the media library. Attachment ID or 0. */
	private static function sideload( $url ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore
		}

		$tmp = download_url( $url, (int) apply_filters( 'gruppa_ie/download_timeout', 20, $url ) );
		if ( is_wp_error( $tmp ) ) {
			return 0;
		}
		$name = wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$file = array(
			'name'     => $name ? rawurldecode( $name ) : 'file',
			'tmp_name' => $tmp,
		);
		$id = media_handle_sideload( $file, 0 );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp ); // phpcs:ignore
			return 0;
		}
		update_post_meta( $id, self::SOURCE_URL_META, esc_url_raw( $url ) );
		return (int) $id;
	}

	/** Attachment ID(s) → URL; keeps values that are already URLs. */
	protected static function attachment_url( $v ) {
		if ( is_array( $v ) ) {
			if ( ! empty( $v['url'] ) ) {
				return (string) $v['url'];
			}
			$v = isset( $v['id'] ) ? $v['id'] : '';
		}
		$s = self::scalar( $v );
		if ( ctype_digit( $s ) ) {
			$url = wp_get_attachment_url( (int) $s );
			return $url ? (string) $url : '';
		}
		return $s;
	}

	/** Gallery value (IDs "1,2", list, JSON, { id, url } items) → "url1, url2". */
	protected static function attachment_urls( $v ) {
		$v    = self::maybe_json( $v );
		$list = is_array( $v ) && ! isset( $v['url'] ) && ! isset( $v['id'] ) ? $v : ( is_array( $v ) ? array( $v ) : self::to_list( $v ) );
		$out  = array();
		foreach ( $list as $item ) {
			$url = self::attachment_url( $item );
			if ( '' !== $url ) {
				$out[] = $url;
			}
		}
		return implode( ', ', $out );
	}

	protected static function sanitize_deep( $arr ) {
		$out = array();
		foreach ( $arr as $k => $v ) {
			$k         = is_int( $k ) ? $k : sanitize_text_field( (string) $k );
			$out[ $k ] = is_array( $v ) ? self::sanitize_deep( $v ) : ( is_scalar( $v ) || null === $v ? wp_kses_post( (string) $v ) : '' );
		}
		return $out;
	}

	/** Valid author for an imported record (user ID that exists). */
	protected static function user_id( $v ) {
		$id = absint( self::scalar( $v ) );
		return $id && get_userdata( $id ) ? $id : 0;
	}
}
