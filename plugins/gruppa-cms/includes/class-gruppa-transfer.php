<?php
defined( 'ABSPATH' ) || exit;

/**
 * Value coercion for item fields — turns raw values (forms, imports, CSV / JSON)
 * into what Gruppa_Fields::sanitize_value expects (option labels → values,
 * "1,5" → 1.5, 31. 12. 2026 → 2026-12-31, media URL → attachment ID …).
 *
 * Import/Export itself lives in the Gruppa Import/Export add-on (Gruppa Tools),
 * which uses Gruppa_Transfer::coerce() as well.
 */
class Gruppa_Transfer {

	private static function to_list( $v ) {
		if ( is_array( $v ) ) {
			return array_values( array_filter( array_map( 'strval', $v ), 'strlen' ) );
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

	/** Normalise a raw file value into what Gruppa_Fields::sanitize_value expects. */
	public static function coerce( $f, $v ) {
		switch ( $f['type'] ) {
			case 'radio':
			case 'select':
			case 'checkbox':
				$values = wp_list_pluck( $f['options'], 'value' );
				$labels = array();
				foreach ( $f['options'] as $o ) {
					$labels[ mb_strtolower( $o['label'] ) ] = $o['value'];
				}
				$out = array();
				foreach ( self::to_list( $v ) as $item ) {
					if ( in_array( $item, $values, true ) ) {
						$out[] = $item;
					} elseif ( isset( $labels[ mb_strtolower( $item ) ] ) ) {
						$out[] = $labels[ mb_strtolower( $item ) ];
					} else {
						$out[] = $item;
					}
				}
				return Gruppa_Fields::is_multi( $f ) ? $out : ( $out ? $out[0] : '' );

			case 'relation':
				$opts  = Gruppa_Fields::relation_options( $f );
				$by    = array();
				foreach ( $opts as $oid => $ol ) {
					$by[ mb_strtolower( preg_replace( '/ \(draft\)$/', '', $ol ) ) ] = $oid;
				}
				$out = array();
				foreach ( self::to_list( $v ) as $item ) {
					if ( ctype_digit( (string) $item ) && isset( $opts[ (int) $item ] ) ) {
						$out[] = (int) $item;
					} elseif ( isset( $by[ mb_strtolower( $item ) ] ) ) {
						$out[] = $by[ mb_strtolower( $item ) ];
					}
				}
				return Gruppa_Fields::is_multi( $f ) ? $out : ( $out ? $out[0] : '' );

			case 'switcher':
				$s = mb_strtolower( trim( is_array( $v ) ? '' : (string) $v ) );
				return in_array( $s, array( '1', 'true', 'yes', 'on', 'ano', 'áno', 'y' ), true ) ? 1 : 0;

			case 'number':
				if ( is_array( $v ) ) {
					return '';
				}
				$s = str_replace( array( ' ', "\xC2\xA0" ), '', (string) $v );
				if ( preg_match( '/^-?\d+,\d+$/', $s ) ) {
					$s = str_replace( ',', '.', $s );
				}
				return $s;

			case 'date':
				$s = trim( is_array( $v ) ? '' : (string) $v );
				if ( '' === $s || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ) {
					return $s;
				}
				if ( preg_match( '/^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})$/', $s, $m ) ) {
					return sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
				}
				$ts = strtotime( $s );
				return $ts ? gmdate( 'Y-m-d', $ts ) : $s;

			case 'repeater':
				// Rows stay rows (array or JSON) — never joined into text.
				$rows = is_string( $v ) ? json_decode( $v, true ) : $v;
				$out  = array();
				foreach ( is_array( $rows ) ? $rows : array() as $rk => $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					// Each cell like its own field type (e.g. "7,25" → 7.25, option labels → values).
					foreach ( (array) $f['sub'] as $sd ) {
						if ( array_key_exists( $sd['key'], $row ) && 'media' !== $sd['type'] ) {
							$row[ $sd['key'] ] = self::coerce( Gruppa_Fields::sub_field( $sd, $f['key'] ), $row[ $sd['key'] ] );
						}
					}
					$out[ $rk ] = $row;
				}
				return $out;

			case 'media':
				$ids = array();
				foreach ( self::to_list( $v ) as $item ) {
					if ( ctype_digit( $item ) ) {
						$ids[] = (int) $item;
					} elseif ( filter_var( $item, FILTER_VALIDATE_URL ) ) {
						$aid = attachment_url_to_postid( $item );
						if ( $aid ) {
							$ids[] = $aid;
						}
					}
				}
				return implode( ',', $ids );
		}
		return is_array( $v ) ? implode( ', ', $v ) : (string) $v;
	}
}
