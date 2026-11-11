<?php
defined( 'ABSPATH' ) || exit;

/**
 * Table Builder source = a family of content types the rows come from.
 *
 *   gruppa  Gruppa CMS content types (Content + Data)
 *   jet     JetEngine Custom Content Types
 *   wp      WordPress posts, pages and post types + ACF / JetEngine meta fields
 *
 * Column meta (what columns() / repeaters() return per key):
 *   label    shown name
 *   type     normalized field type: text textarea editor number date time datetime switcher
 *            select radio checkbox media gallery posts terms url link user
 *   raw      original field type of the plugin (shown in the editor)
 *   options  [ value => label ] for select / radio / checkbox
 */
abstract class Gruppa_TB_Source {

	private static $sources = null;

	/** @return Gruppa_TB_Source[] id => source (all, available or not) */
	public static function all() {
		if ( null === self::$sources ) {
			self::$sources = array();
			$list          = apply_filters( 'gruppa_tb/sources', array( new Gruppa_TB_Source_Gruppa(), new Gruppa_TB_Source_Jet(), new Gruppa_TB_Source_WP() ) );
			foreach ( $list as $s ) {
				if ( $s instanceof Gruppa_TB_Source ) {
					self::$sources[ $s->id() ] = $s;
				}
			}
		}
		return self::$sources;
	}

	/** @return Gruppa_TB_Source[] only sources whose plugin is active */
	public static function available_sources() {
		return array_filter(
			self::all(),
			function ( $s ) {
				return $s->available();
			}
		);
	}

	/** @return Gruppa_TB_Source|null */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/* ---------- identity ---------- */

	abstract public function id();

	abstract public function label();

	abstract public function available();

	/** Message when the source plugin is not active. */
	public function missing_text() {
		return $this->label() . ' is not active.';
	}

	/* ---------- types and columns ---------- */

	/** @return array slug => name */
	abstract public function types();

	public function exists( $slug ) {
		return '' !== (string) $slug && isset( $this->types()[ $slug ] );
	}

	public function type_name( $slug ) {
		$t = $this->types();
		return isset( $t[ $slug ] ) ? $t[ $slug ] : $slug;
	}

	/** Columns for "Items of the content type": key => meta. */
	abstract public function columns( $slug );

	/** Repeater fields: key => [ label, sub => [ key => meta ] ]. */
	abstract public function repeaters( $slug );

	/** Order by: [ [ key, label ], … ]. */
	abstract public function order_options( $slug );

	/** Fields for "Related to current item": [ [ key, label ], … ]. */
	public function context_options( $slug ) {
		return array();
	}

	/** Groups (Gruppa Data types): [ [ id, name ], … ]. */
	public function groups( $slug ) {
		return array();
	}

	/** Repeater item can be found by slug (otherwise only Current item / ID). */
	public function has_slug() {
		return true;
	}

	/** Search Filters of Gruppa CMS filter the table. */
	public function has_filters() {
		return false;
	}

	/* ---------- rows ---------- */

	/**
	 * Items for source "items".
	 *
	 * @param array $s Table settings.
	 * @return array [ items, total ]
	 */
	abstract public function query( $slug, $s, $page, $per );

	/**
	 * Rows of a Repeater field for source "repeater".
	 *
	 * @return array|null rows [ [ sub key => raw ], … ]; null = no current item on this page
	 */
	abstract public function repeater_rows( $slug, $s );

	/** Raw value of an item column. */
	abstract public function value( $slug, $item, $key );

	/** Escaped HTML text of an item column. */
	public function text( $slug, $item, $key, $meta ) {
		return self::format_text( $this->value( $slug, $item, $key ), $meta );
	}

	/** URL of an item column; key "permalink" = page of the item. */
	public function url( $slug, $item, $key ) {
		$raw = $this->value( $slug, $item, $key );
		return self::to_url( $raw );
	}

	/** Attachment IDs / URLs of an item column. */
	public function media( $slug, $item, $key ) {
		return self::media_list( $this->value( $slug, $item, $key ) );
	}

	/* ======================================================
	 * Shared helpers
	 * ==================================================== */

	/** Meta of a column. */
	public static function meta( $label, $type, $raw = '', $options = array() ) {
		return array(
			'label'   => (string) $label,
			'type'    => (string) $type,
			'raw'     => '' !== (string) $raw ? (string) $raw : (string) $type,
			'options' => (array) $options,
		);
	}

	/** Stored value → array (serialized, JSON, comma list stays a string). */
	public static function decode( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = maybe_unserialize( $raw );
		}
		if ( is_string( $raw ) && '' !== $raw && ( '[' === $raw[0] || '{' === $raw[0] ) ) {
			$j = json_decode( $raw, true );
			if ( is_array( $j ) ) {
				return $j;
			}
		}
		return $raw;
	}

	/**
	 * Selected values of a checkbox / multi select.
	 * JetEngine stores checkboxes as [ value => 'true' | 'false' ].
	 */
	public static function selected( $raw ) {
		$raw = self::decode( $raw );
		if ( ! is_array( $raw ) ) {
			return '' === (string) $raw ? array() : array( (string) $raw );
		}
		$out   = array();
		$assoc = array_keys( $raw ) !== range( 0, count( $raw ) - 1 );
		foreach ( $raw as $k => $v ) {
			if ( $assoc && ( 'true' === $v || true === $v || 'false' === $v || false === $v ) ) {
				if ( 'true' === $v || true === $v ) {
					$out[] = (string) $k;
				}
			} elseif ( is_scalar( $v ) && '' !== (string) $v ) {
				$out[] = (string) $v;
			}
		}
		return $out;
	}

	/** Truthy for Yes / No (JetEngine switcher stores "true" / "false"). */
	public static function truthy( $v ) {
		if ( is_bool( $v ) ) {
			return $v;
		}
		$v = strtolower( trim( (string) $v ) );
		return '' !== $v && ! in_array( $v, array( '0', 'false', 'no', 'off', 'null' ), true );
	}

	/**
	 * Date / date-time → "Y-m-d" or "Y-m-d H:i:s".
	 * Accepts MySQL dates, ACF "Ymd", "Y-m-d\TH:i" and Unix timestamps (JetEngine "save as timestamp").
	 */
	public static function normalize_date( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw || '0000-00-00' === substr( $raw, 0, 10 ) ) {
			return '';
		}
		if ( preg_match( '/^(19|20)\d{6}$/', $raw ) ) {
			return substr( $raw, 0, 4 ) . '-' . substr( $raw, 4, 2 ) . '-' . substr( $raw, 6, 2 );
		}
		if ( preg_match( '/^-?\d{9,11}$/', $raw ) ) {
			return gmdate( 'Y-m-d H:i:s', (int) $raw );
		}
		return str_replace( 'T', ' ', $raw );
	}

	/** Attachment IDs / URLs from ID, URL, comma list, [ id, url ] or a list of them. */
	public static function media_list( $raw ) {
		$raw = self::decode( $raw );
		if ( is_array( $raw ) && ( isset( $raw['id'] ) || isset( $raw['url'] ) || isset( $raw['ID'] ) ) ) {
			$raw = array( $raw );
		}
		if ( ! is_array( $raw ) ) {
			$raw = array_map( 'trim', explode( ',', (string) $raw ) );
		}
		$out = array();
		foreach ( $raw as $m ) {
			if ( is_array( $m ) ) {
				$m = ! empty( $m['id'] ) ? $m['id'] : ( ! empty( $m['ID'] ) ? $m['ID'] : ( isset( $m['url'] ) ? $m['url'] : '' ) );
			} elseif ( is_object( $m ) ) {
				$m = isset( $m->ID ) ? $m->ID : '';
			}
			if ( is_scalar( $m ) && '' !== (string) $m && '0' !== (string) $m ) {
				$out[] = is_numeric( $m ) ? (int) $m : (string) $m;
			}
		}
		return $out;
	}

	/** URL from a URL, attachment ID, ACF link [ url ] or post ID. */
	public static function to_url( $raw, $type = '' ) {
		$raw = self::decode( $raw );
		if ( is_array( $raw ) ) {
			$raw = isset( $raw['url'] ) ? $raw['url'] : reset( $raw );
		}
		if ( is_numeric( $raw ) && (int) $raw > 0 ) {
			$id = (int) $raw;
			return 'attachment' === get_post_type( $id ) ? (string) wp_get_attachment_url( $id ) : (string) get_permalink( $id );
		}
		return is_scalar( $raw ) ? trim( (string) $raw ) : '';
	}

	/** Plain text of a value (for sorting, Repeater cells and generic sources). */
	public static function plain( $raw, $meta ) {
		$type = isset( $meta['type'] ) ? $meta['type'] : 'text';
		$opts = isset( $meta['options'] ) ? (array) $meta['options'] : array();
		switch ( $type ) {
			case 'select':
			case 'radio':
			case 'checkbox':
				$vals = self::selected( $raw );
				foreach ( $vals as $i => $v ) {
					if ( isset( $opts[ $v ] ) ) {
						$vals[ $i ] = $opts[ $v ];
					}
				}
				return implode( ', ', $vals );
			case 'switcher':
				return self::truthy( $raw ) ? __( 'Yes' ) : __( 'No' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- WordPress core strings.
			case 'posts':
				$names = array();
				foreach ( self::media_list( $raw ) as $id ) {
					if ( is_int( $id ) ) {
						$names[] = get_the_title( $id );
					}
				}
				return implode( ', ', $names );
			case 'terms':
				$names = array();
				foreach ( self::media_list( $raw ) as $id ) {
					$term = is_int( $id ) ? get_term( $id ) : null;
					if ( $term && ! is_wp_error( $term ) ) {
						$names[] = $term->name;
					}
				}
				return implode( ', ', $names );
			case 'user':
				$names = array();
				foreach ( self::media_list( $raw ) as $id ) {
					$u = is_int( $id ) ? get_userdata( $id ) : null;
					if ( $u ) {
						$names[] = $u->display_name;
					}
				}
				return implode( ', ', $names );
			case 'link':
				$raw = self::decode( $raw );
				if ( is_array( $raw ) ) {
					return ! empty( $raw['title'] ) ? (string) $raw['title'] : ( isset( $raw['url'] ) ? (string) $raw['url'] : '' );
				}
				return (string) $raw;
			case 'date':
				$d = self::normalize_date( $raw );
				return $d ? mysql2date( get_option( 'date_format' ), $d ) : '';
			case 'datetime':
				$d = self::normalize_date( $raw );
				return $d ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $d ) : '';
		}
		$raw = self::decode( $raw );
		if ( is_array( $raw ) ) {
			$flat = array();
			array_walk_recursive(
				$raw,
				function ( $v ) use ( &$flat ) {
					if ( is_scalar( $v ) && '' !== (string) $v ) {
						$flat[] = (string) $v;
					}
				}
			);
			return implode( ', ', $flat );
		}
		return is_scalar( $raw ) ? (string) $raw : '';
	}

	/** Escaped HTML of a value. */
	public static function format_text( $raw, $meta ) {
		$type = isset( $meta['type'] ) ? $meta['type'] : 'text';
		if ( 'editor' === $type ) {
			return wp_kses_post( wpautop( (string) ( is_scalar( $raw ) ? $raw : '' ) ) );
		}
		$text = self::plain( $raw, $meta );
		return 'textarea' === $type ? nl2br( esc_html( $text ) ) : esc_html( $text );
	}

	/** Normalized type of a JetEngine field. */
	public static function jet_type( $t ) {
		$map = array(
			'wysiwyg'        => 'editor',
			'datetime-local' => 'datetime',
			'iconpicker'     => 'text',
			'colorpicker'    => 'text',
		);
		if ( isset( $map[ $t ] ) ) {
			return $map[ $t ];
		}
		return in_array( $t, array( 'text', 'textarea', 'number', 'date', 'time', 'switcher', 'checkbox', 'radio', 'select', 'media', 'gallery', 'posts', 'repeater', 'html' ), true ) ? $t : 'text';
	}

	/** [ value => label ] from JetEngine options ( [ [ key, value ], … ] or [ key => label ] ). */
	public static function jet_options( $f ) {
		$out = array();
		foreach ( isset( $f['options'] ) && is_array( $f['options'] ) ? $f['options'] : array() as $k => $o ) {
			if ( is_array( $o ) && isset( $o['key'] ) ) {
				$out[ (string) $o['key'] ] = isset( $o['value'] ) && '' !== (string) $o['value'] ? (string) $o['value'] : (string) $o['key'];
			} elseif ( is_scalar( $o ) ) {
				$out[ (string) $k ] = (string) $o;
			}
		}
		return $out;
	}

	/**
	 * JetEngine field list → [ key => meta ] and [ key => repeater ] .
	 *
	 * @return array [ columns, repeaters ]
	 */
	public static function jet_fields( $fields ) {
		$cols = array();
		$reps = array();
		foreach ( (array) $fields as $f ) {
			if ( ! is_array( $f ) || empty( $f['name'] ) ) {
				continue;
			}
			if ( isset( $f['object_type'] ) && 'field' !== $f['object_type'] ) {
				continue; // tab, accordion, endpoint
			}
			$raw   = isset( $f['type'] ) ? (string) $f['type'] : 'text';
			$type  = self::jet_type( $raw );
			$label = ! empty( $f['title'] ) ? $f['title'] : $f['name'];
			if ( 'html' === $type ) {
				continue;
			}
			if ( 'repeater' === $type ) {
				$sub = array();
				foreach ( isset( $f['repeater-fields'] ) ? (array) $f['repeater-fields'] : array() as $sf ) {
					if ( ! is_array( $sf ) || empty( $sf['name'] ) ) {
						continue;
					}
					$sraw                 = isset( $sf['type'] ) ? (string) $sf['type'] : 'text';
					$sub[ $sf['name'] ] = self::meta( ! empty( $sf['title'] ) ? $sf['title'] : $sf['name'], self::jet_type( $sraw ), $sraw, self::jet_options( $sf ) );
				}
				$reps[ $f['name'] ] = array( 'label' => $label, 'sub' => $sub );
				continue;
			}
			$cols[ $f['name'] ] = self::meta( $label, $type, $raw, self::jet_options( $f ) );
		}
		return array( $cols, $reps );
	}

	/** JetEngine repeater value → rows. */
	public static function jet_rows( $raw ) {
		$raw = self::decode( $raw );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$rows = array();
		foreach ( $raw as $r ) {
			if ( is_array( $r ) ) {
				$rows[] = $r;
			}
		}
		return $rows;
	}

	/** Current JetEngine listing object (inside a JetEngine Listing Grid), or null. */
	public static function jet_current_object() {
		if ( ! function_exists( 'jet_engine' ) ) {
			return null;
		}
		$je = jet_engine();
		if ( ! $je || empty( $je->listings ) || empty( $je->listings->data ) || ! method_exists( $je->listings->data, 'get_current_object' ) ) {
			return null;
		}
		$obj = $je->listings->data->get_current_object();
		return is_object( $obj ) ? $obj : null;
	}

	/** Allowed column for SQL ORDER BY / WHERE. */
	public static function sql_key( $key ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $key );
	}
}
