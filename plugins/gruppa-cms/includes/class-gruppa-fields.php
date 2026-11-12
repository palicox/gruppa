<?php
defined( 'ABSPATH' ) || exit;

class Gruppa_Fields {

	public static function types() {
		return array(
			'text'     => 'Text',
			'textarea' => 'Textarea',
			'editor'   => 'Editor',
			'number'   => 'Number',
			'date'     => 'Date',
			'time'     => 'Time',
			'radio'    => 'Radio',
			'select'   => 'Select',
			'checkbox' => 'Checkbox',
			'switcher' => 'Switcher',
			'media'    => 'Media',
			'relation' => 'Relation',
			'repeater' => 'Repeater',
			'icon'     => 'Icon',
			'html'     => 'HTML',
		);
	}

	/**
	 * Conditions (0.33): operators of "Show item … When …".
	 * empty / not_empty need no value.
	 */
	public static function condition_ops() {
		return array(
			'is'           => 'is',
			'is_not'       => 'is not',
			'contains'     => 'contains',
			'not_contains' => 'does not contain',
			'empty'        => 'is empty',
			'not_empty'    => 'is not empty',
			'gt'           => 'greater than',
			'lt'           => 'less than',
		);
	}

	/**
	 * Is the field shown for these values? Conditions are evaluated left to right; each row's join (and / or)
	 * links it with the next row. No condition = always shown.
	 *
	 * @param array $f      Field definition.
	 * @param array $values Raw values keyed by field key (as posted).
	 */
	public static function condition_met( $f, $values ) {
		$conds = isset( $f['conditions'] ) ? (array) $f['conditions'] : array();
		if ( ! $conds ) {
			return true;
		}
		$result = null;
		$join   = 'and';
		foreach ( $conds as $c ) {
			$v = isset( $values[ $c['field'] ] ) ? $values[ $c['field'] ] : '';
			$v = is_array( $v ) ? array_values( array_filter( array_map( 'strval', $v ), 'strlen' ) ) : trim( (string) $v );
			$s = is_array( $v ) ? implode( ',', $v ) : $v;
			$w = (string) $c['value'];
			switch ( $c['op'] ) {
				case 'is':
					$ok = is_array( $v ) ? in_array( $w, $v, true ) : mb_strtolower( $s ) === mb_strtolower( $w );
					break;
				case 'is_not':
					$ok = is_array( $v ) ? ! in_array( $w, $v, true ) : mb_strtolower( $s ) !== mb_strtolower( $w );
					break;
				case 'contains':
					$ok = '' !== $w && false !== mb_stripos( $s, $w );
					break;
				case 'not_contains':
					$ok = '' === $w || false === mb_stripos( $s, $w );
					break;
				case 'empty':
					$ok = '' === $s || '0' === $s;
					break;
				case 'not_empty':
					$ok = '' !== $s && '0' !== $s;
					break;
				case 'gt':
					$ok = is_numeric( $s ) && is_numeric( $w ) && $s + 0 > $w + 0;
					break;
				case 'lt':
					$ok = is_numeric( $s ) && is_numeric( $w ) && $s + 0 < $w + 0;
					break;
				default:
					$ok = true;
			}
			$result = null === $result ? $ok : ( 'or' === $join ? ( $result || $ok ) : ( $result && $ok ) );
			$join   = $c['join'];
		}
		return (bool) $result;
	}

	/** Field types a Repeater row can hold. */
	public static function sub_types() {
		return array(
			// Same order as types() (the Add Field list); types a row cannot hold are left out.
			'text'     => 'Text',
			'textarea' => 'Textarea',
			'editor'   => 'Editor',
			'number'   => 'Number',
			'date'     => 'Date',
			'time'     => 'Time',
			'select'   => 'Select',
			'switcher' => 'Switcher',
			'media'    => 'Media',
			'icon'     => 'Icon',
		);
	}

	/** HTML: shown in the item edit screen only — no column, no value. */
	public static function is_virtual( $f ) {
		return is_array( $f ) && isset( $f['type'] ) && 'html' === $f['type'];
	}

	/** Field definition of one Repeater column. */
	public static function sub_field( $s, $parent_key = '' ) {
		$f = self::normalize( array(
			'key'       => isset( $s['key'] ) ? $s['key'] : '',
			'label'     => isset( $s['label'] ) ? $s['label'] : '',
			'type'      => isset( $s['type'] ) ? $s['type'] : 'text',
			'options'   => isset( $s['options'] ) ? $s['options'] : array(),
			'max_files' => 1,
		) );
		$f['parent'] = $parent_key;
		return $f;
	}

	public static function formats() {
		return array(
			'text'     => 'Text',
			'email'    => 'Email',
			'tel'      => 'Phone',
			'url'      => 'URL',
			'password' => 'Password',
		);
	}

	public static function is_password( $f ) {
		return 'text' === $f['type'] && isset( $f['format'] ) && 'password' === $f['format'];
	}

	/**
	 * Empty password on an existing item means "keep the current one".
	 */
	public static function keep_password( $f, $raw, $is_update ) {
		return $is_update && self::is_password( $f ) && '' === trim( (string) ( is_array( $raw ) ? '' : $raw ) );
	}

	public static function has_options( $type ) {
		return in_array( $type, array( 'radio', 'select', 'checkbox' ), true );
	}

	public static function is_multi( $f ) {
		return 'checkbox' === $f['type'] || ( in_array( $f['type'], array( 'select', 'relation' ), true ) && ! empty( $f['multiple'] ) ) || ( 'media' === $f['type'] && 1 !== (int) $f['max_files'] );
	}

	public static function defaults() {
		return array(
			'key'           => '',
			'label'         => '',
			'type'          => 'text',
			'description'   => '',
			'placeholder'   => '',
			'default'       => '',
			'required'      => false,
			'limit'         => 0,
			'format'        => 'text',
			'min'           => '',
			'max'           => '',
			'step'          => '',
			'options_mode'  => 'manual',
			'options'       => array(),
			'multiple'      => false,
			'save_as_array' => false,
			'return'        => 'id',
			'file_types'    => '',
			'max_files'     => 1,
			'max_mb'        => 0,
			'related'       => '',
			'ui'            => 'select',
			'list'          => false,
			'html'          => '',
			'sub'           => array(),
			'conditions'    => array(),
		);
	}

	/* ---------- Field settings ---------- */

	public static function parse_bulk( $text ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( ':', $line, 2 ) );
			$value = sanitize_text_field( $parts[0] );
			$label = isset( $parts[1] ) && '' !== $parts[1] ? sanitize_text_field( $parts[1] ) : $value;
			if ( '' !== $value ) {
				$out[] = array( 'value' => $value, 'label' => $label );
			}
		}
		return $out;
	}

	public static function sanitize_settings( $f ) {
		$d     = self::defaults();
		$types = self::types();
		$f     = is_array( $f ) ? $f : array();

		$type = isset( $f['type'], $types[ $f['type'] ] ) ? $f['type'] : 'text';
		$out  = array(
			'key'         => Gruppa_Types::sanitize_key( isset( $f['key'] ) ? $f['key'] : '' ),
			'label'       => sanitize_text_field( isset( $f['label'] ) ? $f['label'] : '' ),
			'type'        => $type,
			'description' => sanitize_text_field( isset( $f['description'] ) ? $f['description'] : '' ),
			'placeholder' => sanitize_text_field( isset( $f['placeholder'] ) ? $f['placeholder'] : '' ),
			'default'     => sanitize_text_field( isset( $f['default'] ) ? $f['default'] : '' ),
			'required'    => ! empty( $f['required'] ),
			'list'        => ! empty( $f['list'] ) && ! ( 'text' === $type && isset( $f['format'] ) && 'password' === $f['format'] ),
		);

		// Conditions (0.33): Show item [field] When [op] [value] [and / or].
		$out['conditions'] = array();
		foreach ( (array) ( isset( $f['conditions'] ) ? $f['conditions'] : array() ) as $c ) {
			$cf = Gruppa_Types::sanitize_key( isset( $c['field'] ) ? $c['field'] : '' );
			$op = isset( $c['op'], self::condition_ops()[ $c['op'] ] ) ? $c['op'] : 'is';
			if ( '' === $cf || $cf === $out['key'] ) {
				continue;
			}
			$out['conditions'][] = array(
				'field' => $cf,
				'op'    => $op,
				'value' => in_array( $op, array( 'empty', 'not_empty' ), true ) ? '' : sanitize_text_field( isset( $c['value'] ) ? $c['value'] : '' ),
				'join'  => isset( $c['join'] ) && 'or' === $c['join'] ? 'or' : 'and',
			);
		}

		if ( 'icon' === $type ) {
			$out['default'] = in_array( $out['default'], Gruppa_Menu::icons(), true ) ? $out['default'] : '';
		}

		if ( 'text' === $type ) {
			$out['format'] = isset( $f['format'], self::formats()[ $f['format'] ] ) ? $f['format'] : 'text';
			if ( 'password' === $out['format'] ) {
				$out['default'] = '';
			}
		}

		if ( in_array( $type, array( 'text', 'textarea', 'editor' ), true ) ) {
			$out['limit'] = max( 0, (int) ( isset( $f['limit'] ) ? $f['limit'] : 0 ) );
		}

		if ( 'number' === $type ) {
			foreach ( array( 'min', 'max', 'step' ) as $k ) {
				$out[ $k ] = isset( $f[ $k ] ) && is_numeric( $f[ $k ] ) ? (string) ( $f[ $k ] + 0 ) : '';
			}
		}

		if ( 'switcher' === $type ) {
			$out['default'] = ! empty( $f['default_switch'] ) ? '1' : '0';
		}

		if ( 'time' === $type ) {
			$out['default'] = preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $out['default'] ) ? $out['default'] : '';
		}

		if ( 'html' === $type ) {
			$out['html']        = wp_kses_post( isset( $f['html'] ) ? (string) $f['html'] : '' );
			$out['required']    = false;
			$out['list']        = false;
			$out['default']     = '';
			$out['description'] = '';
		}

		if ( 'repeater' === $type ) {
			$out['default'] = '';
			$out['list']    = ! empty( $f['list'] );
			$sub            = array();
			$seen           = array();
			foreach ( (array) ( isset( $f['sub'] ) ? $f['sub'] : array() ) as $r ) {
				$sl = sanitize_text_field( isset( $r['label'] ) ? $r['label'] : '' );
				$sk = Gruppa_Types::sanitize_key( isset( $r['key'] ) && '' !== $r['key'] ? $r['key'] : $sl );
				$st = isset( $r['type'], self::sub_types()[ $r['type'] ] ) ? $r['type'] : 'text';
				if ( '' === $sk || isset( $seen[ $sk ] ) ) {
					continue;
				}
				$seen[ $sk ] = true;
				$sub[]       = array(
					'key'     => $sk,
					'label'   => '' !== $sl ? $sl : $sk,
					'type'    => $st,
					'options' => 'select' === $st ? self::parse_bulk( isset( $r['options_bulk'] ) ? $r['options_bulk'] : '' ) : array(),
				);
			}
			$out['sub'] = $sub;
		}

		if ( self::has_options( $type ) ) {
			$mode = isset( $f['options_mode'] ) && 'bulk' === $f['options_mode'] ? 'bulk' : 'manual';
			if ( 'bulk' === $mode ) {
				$options = self::parse_bulk( isset( $f['options_bulk'] ) ? $f['options_bulk'] : '' );
			} else {
				$options = array();
				foreach ( (array) ( isset( $f['options'] ) ? $f['options'] : array() ) as $o ) {
					$v = sanitize_text_field( isset( $o['value'] ) ? $o['value'] : '' );
					$l = sanitize_text_field( isset( $o['label'] ) ? $o['label'] : '' );
					if ( '' === $v && '' !== $l ) {
						$v = $l;
					}
					if ( '' !== $v ) {
						$options[] = array( 'value' => $v, 'label' => '' !== $l ? $l : $v );
					}
				}
			}
			$out['options_mode']  = $mode;
			$out['options']       = $options;
			$out['multiple']      = 'select' === $type && ! empty( $f['multiple'] );
			$out['save_as_array'] = ! empty( $f['save_as_array'] );
		}

		if ( 'relation' === $type ) {
			$out['related']  = sanitize_key( isset( $f['related'] ) ? $f['related'] : '' );
			$out['multiple'] = ! empty( $f['multiple'] );
			$out['ui']       = isset( $f['ui'] ) && 'list' === $f['ui'] ? 'list' : 'select';
			$out['default']  = '';
		}

		if ( 'media' === $type ) {
			$out['return']     = isset( $f['return'] ) && 'url' === $f['return'] ? 'url' : 'id';
			$exts              = preg_split( '/[\s,]+/', strtolower( (string) ( isset( $f['file_types'] ) ? $f['file_types'] : '' ) ) );
			$exts              = array_filter( array_map( function ( $e ) {
				return preg_replace( '/[^a-z0-9]/', '', $e );
			}, $exts ) );
			$out['file_types'] = implode( ',', array_unique( $exts ) );
			$out['max_files']  = max( 0, (int) ( isset( $f['max_files'] ) ? $f['max_files'] : 1 ) );
			$out['max_mb']     = max( 0, (float) ( isset( $f['max_mb'] ) ? $f['max_mb'] : 0 ) );
		}

		return wp_parse_args( $out, $d );
	}

	/** Upgrade older stored settings. */
	public static function normalize( $f ) {
		$f = wp_parse_args( is_array( $f ) ? $f : array(), self::defaults() );
		if ( 'image' === $f['type'] ) {
			$f['type'] = 'media';
		}
		$opts = array();
		foreach ( (array) $f['options'] as $o ) {
			$opts[] = is_array( $o ) ? $o : array( 'value' => (string) $o, 'label' => (string) $o );
		}
		$f['options'] = $opts;
		return $f;
	}

	public static function mimes_for( $file_types ) {
		if ( '' === $file_types ) {
			return array();
		}
		$exts  = explode( ',', $file_types );
		$mimes = array();
		foreach ( wp_get_mime_types() as $pattern => $mime ) {
			foreach ( explode( '|', $pattern ) as $ext ) {
				if ( in_array( $ext, $exts, true ) ) {
					$mimes[] = $mime;
				}
			}
		}
		return array_values( array_unique( $mimes ) );
	}

	/* ---------- Relation ---------- */

	/** Related type object of a relation field, or null. */
	public static function related_type( $f ) {
		return ( 'relation' === $f['type'] && $f['related'] ) ? Gruppa_Types::get_by_slug( $f['related'] ) : null;
	}

	/** [ id => title ] of the related type's items (ordered like in the admin list). */
	public static function relation_options( $f ) {
		static $cache = array();
		if ( ! empty( $f['link'] ) ) {
			return Gruppa_Relations::options( $f['link'], ! empty( $f['published_only'] ) ); // Data link: values of one group.
		}
		$rt = self::related_type( $f );
		if ( ! $rt || ! Gruppa_DB::data_table_exists( $rt->slug ) ) {
			return array();
		}
		if ( ! isset( $cache[ $rt->slug ] ) ) {
			global $wpdb;
			$table = Gruppa_DB::data_table( $rt->slug );
			$out   = array();
			foreach ( (array) $wpdb->get_results( "SELECT id, title, status FROM `{$table}` ORDER BY order_index ASC, id ASC" ) as $r ) { // phpcs:ignore
				$out[ (int) $r->id ] = $r->title . ( 'publish' === $r->status ? '' : ' (draft)' );
			}
			$cache[ $rt->slug ] = $out;
		}
		return $cache[ $rt->slug ];
	}

	/** Stored relation value → list of item IDs. */
	public static function relation_ids( $f, $raw ) {
		$list = self::is_multi( $f ) ? self::decode( $f, $raw ) : ( null === $raw || '' === $raw ? array() : array( $raw ) );
		return array_values( array_filter( array_map( 'absint', (array) $list ) ) );
	}

	/* ---------- Values ---------- */

	/** Decode a stored value into what the form needs. */
	public static function decode( $f, $raw ) {
		if ( 'repeater' === $f['type'] ) {
			$rows = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
			return is_array( $rows ) ? array_values( array_filter( $rows, 'is_array' ) ) : array();
		}
		if ( 'time' === $f['type'] ) {
			return null === $raw || '' === (string) $raw ? '' : substr( (string) $raw, 0, 5 );
		}
		if ( self::is_multi( $f ) ) {
			if ( null === $raw || '' === $raw ) {
				return array();
			}
			$arr = json_decode( (string) $raw, true );
			if ( ! is_array( $arr ) ) {
				$arr = array_map( 'trim', explode( ',', (string) $raw ) );
			}
			return array_values( array_filter( array_map( 'strval', $arr ), 'strlen' ) );
		}
		if ( self::has_options( $f['type'] ) && is_string( $raw ) && 0 === strpos( $raw, '[' ) ) {
			$arr = json_decode( $raw, true );
			return is_array( $arr ) && $arr ? (string) reset( $arr ) : '';
		}
		return $raw;
	}

	public static function default_value( $f ) {
		if ( 'repeater' === $f['type'] ) {
			return array();
		}
		if ( self::is_multi( $f ) ) {
			return '' === $f['default'] ? array() : array_map( 'trim', explode( ',', $f['default'] ) );
		}
		return $f['default'];
	}

	private static function strlen( $s ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $s ) : strlen( $s );
	}

	/**
	 * Sanitize + validate posted value.
	 *
	 * @return array [ db_value, error_string|null ]
	 */
	public static function sanitize_value( $f, $raw ) {
		$label = $f['label'] ? $f['label'] : $f['key'];
		$type  = $f['type'];

		switch ( $type ) {
			case 'text':
			case 'textarea':
			case 'editor':
				if ( 'text' === $type && 'text' !== $f['format'] ) {
					return self::sanitize_formatted( $f, $raw, $label );
				}
				if ( 'text' === $type ) {
					$val = sanitize_text_field( (string) $raw );
				} elseif ( 'textarea' === $type ) {
					$val = sanitize_textarea_field( (string) $raw );
				} else {
					$val = wp_kses_post( (string) $raw );
				}
				if ( $f['required'] && '' === trim( wp_strip_all_tags( $val ) ) ) {
					return array( $val, sprintf( __( '%s: required', 'gruppa-cms' ), $label ) );
				}
				if ( $f['limit'] && self::strlen( wp_strip_all_tags( $val ) ) > $f['limit'] ) {
					return array( $val, sprintf( __( '%1$s: max %2$s characters', 'gruppa-cms' ), $label, $f['limit'] ) );
				}
				return array( $val, null );

			case 'number':
				$raw = trim( (string) $raw );
				if ( '' === $raw ) {
					return array( null, $f['required'] ? sprintf( __( '%s: required', 'gruppa-cms' ), $label ) : null );
				}
				if ( ! is_numeric( $raw ) ) {
					return array( null, sprintf( __( '%s: not a number', 'gruppa-cms' ), $label ) );
				}
				$n = $raw + 0;
				if ( '' !== $f['min'] && $n < $f['min'] ) {
					return array( $n, sprintf( __( '%1$s: min %2$s', 'gruppa-cms' ), $label, $f['min'] ) );
				}
				if ( '' !== $f['max'] && $n > $f['max'] ) {
					return array( $n, sprintf( __( '%1$s: max %2$s', 'gruppa-cms' ), $label, $f['max'] ) );
				}
				return array( $n, null );

			case 'date':
				$raw = trim( (string) $raw );
				if ( '' === $raw ) {
					return array( null, $f['required'] ? sprintf( __( '%s: required', 'gruppa-cms' ), $label ) : null );
				}
				$d = DateTime::createFromFormat( 'Y-m-d', $raw );
				if ( ! $d || $d->format( 'Y-m-d' ) !== $raw ) {
					return array( null, sprintf( __( '%s: invalid date', 'gruppa-cms' ), $label ) );
				}
				return array( $raw, null );

			case 'switcher':
				return array( empty( $raw ) ? 0 : 1, null );

			case 'time':
				$raw = trim( is_array( $raw ) ? '' : (string) $raw );
				if ( '' === $raw ) {
					return array( null, $f['required'] ? sprintf( __( '%s: required', 'gruppa-cms' ), $label ) : null );
				}
				if ( ! preg_match( '/^([01]?\d|2[0-3])[:.]([0-5]\d)(?::[0-5]\d)?$/', $raw, $m ) ) {
					return array( null, sprintf( __( '%s: invalid time', 'gruppa-cms' ), $label ) );
				}
				return array( sprintf( '%02d:%s', (int) $m[1], $m[2] ), null );

			case 'html':
				return array( null, null );

			case 'icon':
				$raw = sanitize_key( is_array( $raw ) ? '' : (string) $raw );
				$raw = in_array( $raw, Gruppa_Menu::icons(), true ) ? $raw : '';
				if ( $f['required'] && '' === $raw ) {
					return array( null, sprintf( __( '%s: required', 'gruppa-cms' ), $label ) );
				}
				return array( '' === $raw ? null : $raw, null );

			case 'repeater':
				$rows = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
				$out  = array();
				foreach ( (array) $rows as $rk => $row ) {
					if ( '__r__' === (string) $rk || ! is_array( $row ) ) {
						continue; // Row template.
					}
					$clean = array();
					$empty = true;
					foreach ( $f['sub'] as $sd ) {
						$sf  = self::sub_field( $sd, $f['key'] );
						$sv  = isset( $row[ $sd['key'] ] ) ? $row[ $sd['key'] ] : '';
						list( $val, $err ) = self::sanitize_value( $sf, $sv );
						if ( $err ) {
							return array( null, $label . ' → ' . $err );
						}
						if ( 'switcher' === $sd['type'] ) {
							$val   = (int) $val;
							$empty = $empty && ! $val;
						} elseif ( null !== $val && '' !== (string) $val ) {
							$empty = false;
						}
						if ( 'number' === $sd['type'] && null !== $val ) {
							$val = $val + 0;
						}
						$clean[ $sd['key'] ] = $val;
					}
					if ( ! $empty ) {
						$out[] = $clean;
					}
				}
				if ( $f['required'] && ! $out ) {
					return array( null, sprintf( __( '%s: required', 'gruppa-cms' ), $label ) );
				}
				return array( $out ? wp_json_encode( $out ) : null, null );

			case 'radio':
			case 'select':
			case 'checkbox':
				$allowed = wp_list_pluck( $f['options'], 'value' );
				if ( self::is_multi( $f ) ) {
					$vals = array_values( array_intersect( array_map( 'strval', (array) $raw ), $allowed ) );
					if ( $f['required'] && ! $vals ) {
						return array( null, sprintf( __( '%s: required', 'gruppa-cms' ), $label ) );
					}
					if ( ! $vals ) {
						return array( null, null );
					}
					return array( $f['save_as_array'] ? wp_json_encode( $vals ) : implode( ',', $vals ), null );
				}
				$val = in_array( (string) $raw, $allowed, true ) ? (string) $raw : '';
				if ( $f['required'] && '' === $val ) {
					return array( null, sprintf( __( '%s: required', 'gruppa-cms' ), $label ) );
				}
				if ( '' === $val ) {
					return array( null, null );
				}
				return array( $f['save_as_array'] ? wp_json_encode( array( $val ) ) : $val, null );

			case 'relation':
				$raw     = is_array( $raw ) ? $raw : ( '' === (string) $raw ? array() : explode( ',', (string) $raw ) );
				$allowed = array_keys( self::relation_options( $f ) );
				$ids     = array_values( array_unique( array_intersect( array_map( 'absint', $raw ), $allowed ) ) );
				if ( $f['required'] && ! $ids ) {
					return array( null, sprintf( __( '%s: required', 'gruppa-cms' ), $label ) );
				}
				if ( ! $ids ) {
					return array( null, null );
				}
				return array( self::is_multi( $f ) ? wp_json_encode( $ids ) : (string) $ids[0], null );

			case 'media':
				$ids = array_filter( array_map( 'absint', explode( ',', (string) $raw ) ) );
				$ids = array_values( array_unique( $ids ) );
				if ( $f['required'] && ! $ids ) {
					return array( null, sprintf( __( '%s: required', 'gruppa-cms' ), $label ) );
				}
				if ( $f['max_files'] > 0 && count( $ids ) > $f['max_files'] ) {
					return array( null, sprintf( __( '%1$s: max %2$s files', 'gruppa-cms' ), $label, $f['max_files'] ) );
				}
				$exts = '' !== $f['file_types'] ? explode( ',', $f['file_types'] ) : array();
				$out  = array();
				foreach ( $ids as $id ) {
					if ( 'attachment' !== get_post_type( $id ) ) {
						continue;
					}
					$file = get_attached_file( $id );
					if ( $exts ) {
						$ext = strtolower( pathinfo( $file ? $file : wp_get_attachment_url( $id ), PATHINFO_EXTENSION ) );
						if ( ! in_array( $ext, $exts, true ) ) {
							return array( null, sprintf( __( '%1$s: file type not allowed (%2$s)', 'gruppa-cms' ), $label, $ext ) );
						}
					}
					if ( $f['max_mb'] > 0 && $file && file_exists( $file ) && filesize( $file ) > $f['max_mb'] * MB_IN_BYTES ) {
						return array( null, sprintf( __( '%1$s: max %2$s MB', 'gruppa-cms' ), $label, $f['max_mb'] ) );
					}
					$out[] = 'url' === $f['return'] ? wp_get_attachment_url( $id ) : $id;
				}
				if ( ! $out ) {
					return array( null, null );
				}
				if ( 1 === (int) $f['max_files'] ) {
					return array( (string) $out[0], null );
				}
				return array( wp_json_encode( $out ), null );
		}

		return array( sanitize_text_field( (string) $raw ), null );
	}

	private static function sanitize_formatted( $f, $raw, $label ) {
		$raw = is_array( $raw ) ? '' : trim( (string) $raw );
		if ( '' === $raw ) {
			return array( null, $f['required'] ? sprintf( __( '%s: required', 'gruppa-cms' ), $label ) : null );
		}
		if ( $f['limit'] && 'password' !== $f['format'] && self::strlen( $raw ) > $f['limit'] ) {
			return array( null, sprintf( __( '%1$s: max %2$s characters', 'gruppa-cms' ), $label, $f['limit'] ) );
		}
		switch ( $f['format'] ) {
			case 'email':
				$v = sanitize_email( $raw );
				return is_email( $v ) ? array( $v, null ) : array( null, sprintf( __( '%s: invalid email', 'gruppa-cms' ), $label ) );

			case 'url':
				if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $raw ) ) {
					$raw = 'https://' . $raw;
				}
				$v = esc_url_raw( $raw );
				return $v && filter_var( $v, FILTER_VALIDATE_URL ) ? array( $v, null ) : array( null, sprintf( __( '%s: invalid URL', 'gruppa-cms' ), $label ) );

			case 'tel':
				$v      = preg_replace( '/\s+/', ' ', $raw );
				$digits = preg_replace( '/\D/', '', $v );
				if ( preg_match( '/[^0-9+()\-\/. ]/', $v ) || strlen( $digits ) < 6 || strlen( $digits ) > 15 ) {
					return array( null, sprintf( __( '%s: invalid phone number', 'gruppa-cms' ), $label ) );
				}
				return array( $v, null );

			case 'password':
				if ( self::strlen( $raw ) < 8 ) {
					return array( null, sprintf( __( '%s: min 8 characters', 'gruppa-cms' ), $label ) );
				}
				if ( $f['limit'] && self::strlen( $raw ) > $f['limit'] ) {
					return array( null, sprintf( __( '%1$s: max %2$s characters', 'gruppa-cms' ), $label, $f['limit'] ) );
				}
				return array( wp_hash_password( $raw ), null );
		}
		return array( sanitize_text_field( $raw ), null );
	}

	/* ---------- Render input ---------- */

	public static function render_input( $f, $value ) {
		$name = 'gf[' . $f['key'] . ']';
		$id   = 'gf-' . $f['key'];
		$ph   = $f['placeholder'] ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : '';
		$req  = $f['required'] && ! in_array( $f['type'], array( 'checkbox', 'editor', 'media', 'switcher' ), true ) && ! ( 'relation' === $f['type'] && self::is_multi( $f ) ) ? ' required' : '';

		switch ( $f['type'] ) {
			case 'text':
				$format = isset( $f['format'] ) ? $f['format'] : 'text';
				if ( 'password' === $format ) {
					$has = '' !== (string) $value;
					printf(
						'<input type="password" id="%s" name="%s" class="large-text" value="" autocomplete="new-password"%s%s>',
						esc_attr( $id ), esc_attr( $name ),
						$has ? ' placeholder="•••••••• (unchanged)"' : $ph,
						$has ? '' : $req
					);
					break;
				}
				// URL: lenient text input (server adds https://), URL keyboard on mobile.
				printf(
					'<input type="%s" id="%s" name="%s" class="large-text" value="%s"%s%s%s%s>',
					esc_attr( in_array( $format, array( 'email', 'tel' ), true ) ? $format : 'text' ),
					esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), $ph, $req,
					$f['limit'] ? ' maxlength="' . (int) $f['limit'] . '"' : '',
					'url' === $format ? ' inputmode="url" autocomplete="url"' : ''
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="%s" name="%s" class="large-text" rows="5"%s%s%s>%s</textarea>',
					esc_attr( $id ), esc_attr( $name ), $ph, $req,
					$f['limit'] ? ' maxlength="' . (int) $f['limit'] . '"' : '',
					esc_textarea( (string) $value )
				);
				break;

			case 'editor':
				wp_editor(
					(string) $value,
					'gf_' . $f['key'],
					array(
						'textarea_name' => $name,
						'textarea_rows' => 10,
						'media_buttons' => true,
					)
				);
				break;

			case 'number':
				printf(
					'<input type="number" id="%s" name="%s" class="regular-text" value="%s"%s%s%s%s%s>',
					esc_attr( $id ), esc_attr( $name ), esc_attr( null === $value ? '' : (string) ( is_numeric( $value ) ? $value + 0 : $value ) ), $ph, $req,
					'' !== $f['min'] ? ' min="' . esc_attr( $f['min'] ) . '"' : '',
					'' !== $f['max'] ? ' max="' . esc_attr( $f['max'] ) . '"' : '',
					' step="' . esc_attr( '' !== $f['step'] ? $f['step'] : 'any' ) . '"'
				);
				break;

			case 'date':
				printf( '<input type="date" id="%s" name="%s" value="%s"%s>', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), $req );
				break;

			case 'time':
				printf( '<input type="time" id="%s" name="%s" value="%s" step="60"%s>', esc_attr( $id ), esc_attr( $name ), esc_attr( self::decode( $f, $value ) ), $req );
				break;

			case 'html':
				echo '<div class="gruppa-html-field">' . wp_kses_post( $f['html'] ) . '</div>';
				return;

			case 'icon':
				self::icon_picker( $name, (string) $value, $id );
				break;

			case 'repeater':
				self::render_repeater( $f, $value );
				break;

			case 'switcher':
				printf(
					'<input type="hidden" name="%2$s" value="0"><label class="gruppa-switch"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s><span></span></label>',
					esc_attr( $id ), esc_attr( $name ), checked( (int) $value, 1, false )
				);
				break;

			case 'radio':
				$value = is_array( $value ) ? reset( $value ) : $value;
				echo '<fieldset>';
				foreach ( $f['options'] as $o ) {
					printf(
						'<label><input type="radio" name="%s" value="%s" %s%s> %s</label><br>',
						esc_attr( $name ), esc_attr( $o['value'] ), checked( (string) $value, $o['value'], false ), $req, esc_html( $o['label'] )
					);
				}
				echo '</fieldset>';
				break;

			case 'select':
				$multi = ! empty( $f['multiple'] );
				$vals  = array_map( 'strval', (array) $value );
				printf( '<select id="%s" name="%s"%s%s>', esc_attr( $id ), esc_attr( $name . ( $multi ? '[]' : '' ) ), $multi ? ' multiple size="5"' : '', $req );
				if ( ! $multi ) {
					echo '<option value="">' . esc_html( $f['placeholder'] ? $f['placeholder'] : '—' ) . '</option>';
				}
				foreach ( $f['options'] as $o ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $o['value'] ), selected( in_array( $o['value'], $vals, true ), true, false ), esc_html( $o['label'] ) );
				}
				echo '</select>';
				break;

			case 'checkbox':
				$vals = array_map( 'strval', (array) $value );
				echo '<fieldset>';
				foreach ( $f['options'] as $o ) {
					printf(
						'<label><input type="checkbox" name="%s[]" value="%s" %s> %s</label><br>',
						esc_attr( $name ), esc_attr( $o['value'] ), checked( in_array( $o['value'], $vals, true ), true, false ), esc_html( $o['label'] )
					);
				}
				echo '</fieldset>';
				break;

			case 'relation':
				$opts  = self::relation_options( $f );
				$multi = self::is_multi( $f );
				$vals  = array_map( 'intval', (array) $value );
				if ( ! $opts ) {
					$rt = self::related_type( $f );
					echo '<p class="gruppa-muted">' . esc_html( $rt ? 'No items in ' . $rt->name . ' yet.' : 'Related type not set.' ) . '</p>';
					break;
				}
				if ( 'list' === $f['ui'] ) {
					echo '<fieldset class="gruppa-relation-list">';
					if ( $multi ) {
						printf( '<input type="hidden" name="%s[]" value="">', esc_attr( $name ) );
					}
					foreach ( $opts as $oid => $olabel ) {
						printf(
							'<label><input type="%s" name="%s" value="%d" %s%s> %s</label>',
							$multi ? 'checkbox' : 'radio', esc_attr( $name . ( $multi ? '[]' : '' ) ), (int) $oid,
							checked( in_array( (int) $oid, $vals, true ), true, false ), $multi ? '' : $req, esc_html( $olabel )
						);
					}
					echo '</fieldset>';
					break;
				}
				printf( '<select id="%s" name="%s"%s%s>', esc_attr( $id ), esc_attr( $name . ( $multi ? '[]' : '' ) ), $multi ? ' multiple size="' . min( 8, max( 3, count( $opts ) ) ) . '"' : '', $req );
				if ( ! $multi ) {
					echo '<option value="">' . esc_html( $f['placeholder'] ? $f['placeholder'] : '—' ) . '</option>';
				}
				foreach ( $opts as $oid => $olabel ) {
					printf( '<option value="%d" %s>%s</option>', (int) $oid, selected( in_array( (int) $oid, $vals, true ), true, false ), esc_html( $olabel ) );
				}
				echo '</select>';
				break;

			case 'media':
				$ids = array();
				foreach ( (array) $value as $v ) {
					if ( is_numeric( $v ) ) {
						$ids[] = (int) $v;
					} elseif ( $v ) {
						$aid = attachment_url_to_postid( $v );
						if ( $aid ) {
							$ids[] = $aid;
						}
					}
				}
				printf(
					'<div class="gruppa-media" data-max="%d" data-mb="%s" data-types="%s" data-mimes="%s">',
					(int) $f['max_files'], esc_attr( $f['max_mb'] ), esc_attr( $f['file_types'] ), esc_attr( wp_json_encode( self::mimes_for( $f['file_types'] ) ) )
				);
				printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( implode( ',', $ids ) ) );
				echo '<ul class="gruppa-media-list">';
				foreach ( $ids as $aid ) {
					self::media_item( $aid );
				}
				echo '</ul><button type="button" class="button gruppa-media-add">Select</button></div>';
				break;
		}

		if ( $f['description'] ) {
			echo '<p class="description">' . esc_html( $f['description'] ) . '</p>';
		}
	}

	/** Repeater: one card per row, the sub fields side by side; add, remove, drag. */
	private static function render_repeater( $f, $value ) {
		if ( is_array( $value ) ) {
			unset( $value['__r__'] ); // Re-shown after a validation error.
			$rows = array_values( array_filter( $value, 'is_array' ) );
		} else {
			$rows = self::decode( $f, $value );
		}
		printf( '<div class="gruppa-repeater" data-key="%s">', esc_attr( $f['key'] ) );
		if ( ! $f['sub'] ) {
			echo '<p class="gruppa-muted">No columns yet — add them in the field settings of the entity.</p></div>';
			return;
		}
		echo '<div class="gruppa-repeater-rows">';
		foreach ( $rows as $i => $row ) {
			self::repeater_row( $f, $i, $row );
		}
		echo '</div>';
		echo '<script type="text/html" class="gruppa-repeater-tmpl">';
		self::repeater_row( $f, '__r__', array() );
		echo '</script>';
		printf( '<input type="hidden" name="gf[%s][__r__][]" value="">', esc_attr( $f['key'] ) ); // Empty repeater still posts the key.
		echo '<button type="button" class="button gruppa-repeater-add">Add row</button></div>';
	}

	private static function repeater_row( $f, $i, $row ) {
		echo '<div class="gruppa-repeater-row"><span class="dashicons dashicons-menu gruppa-handle" aria-hidden="true"></span><div class="gruppa-repeater-cells">';
		foreach ( $f['sub'] as $sd ) {
			$sf        = self::sub_field( $sd );
			$sf['key'] = $f['key'] . '][' . $i . '][' . $sd['key']; // → name gf[key][i][sub]
			$sv        = isset( $row[ $sd['key'] ] ) ? $row[ $sd['key'] ] : '';
			if ( 'media' === $sd['type'] ) {
				$sv = '' === (string) $sv ? array() : array( $sv );
			}
			printf( '<div class="gruppa-repeater-cell gruppa-repeater-cell--%s"><span class="gruppa-repeater-label">%s</span>', esc_attr( $sd['type'] ), esc_html( $sd['label'] ) );
			if ( 'editor' === $sd['type'] ) {
				// 0.33: rich text in a row — a textarea that items.js turns into a small editor (wp.editor).
				printf( '<textarea class="gruppa-mini-editor" name="gf[%s]" rows="5">%s</textarea>', esc_attr( $sf['key'] ), esc_textarea( (string) $sv ) );
			} else {
				self::render_input( $sf, $sv );
			}
			echo '</div>';
		}
		echo '</div><button type="button" class="button-link gruppa-delete gruppa-repeater-remove" aria-label="Remove row">&times;</button></div>';
	}

	/** Icon field (0.33): Dashicons picker — the same icons as the Menu icon of a type. */
	public static function icon_picker( $name, $value, $id = '' ) {
		$value = in_array( $value, Gruppa_Menu::icons(), true ) ? $value : '';
		?>
		<div class="gruppa-icon-picker gruppa-icon-field">
			<input type="hidden" class="gruppa-icon-value" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<button type="button" class="button gruppa-icon-btn"<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?> aria-expanded="false"><span class="dashicons <?php echo esc_attr( $value ? $value : 'dashicons-marker' ); ?>" aria-hidden="true"></span><span class="gruppa-icon-label"><?php echo $value ? esc_html( substr( $value, 10 ) ) : 'Choose icon'; ?></span></button>
			<button type="button" class="button-link gruppa-icon-reset" <?php echo $value ? '' : 'hidden'; ?>>Remove</button>
			<div class="gruppa-icon-panel" hidden>
				<input type="search" class="gruppa-icon-search" placeholder="Search icons" aria-label="Search icons">
				<div class="gruppa-icon-grid" role="listbox" aria-label="Icon">
					<?php foreach ( Gruppa_Menu::icons() as $ic ) : ?>
						<button type="button" class="gruppa-icon-opt<?php echo $ic === $value ? ' is-current' : ''; ?>" data-icon="<?php echo esc_attr( $ic ); ?>" title="<?php echo esc_attr( substr( $ic, 10 ) ); ?>" role="option"><span class="dashicons <?php echo esc_attr( $ic ); ?>" aria-hidden="true"></span></button>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	public static function media_item( $aid ) {
		$thumb = wp_get_attachment_image( $aid, 'thumbnail', true );
		printf(
			'<li data-id="%d">%s<span class="gruppa-media-name">%s</span><button type="button" class="gruppa-media-remove" aria-label="Remove">&times;</button></li>',
			(int) $aid, $thumb, esc_html( wp_basename( (string) get_attached_file( $aid ) ) )
		);
	}
}
