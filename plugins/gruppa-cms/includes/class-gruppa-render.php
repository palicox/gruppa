<?php
defined( 'ABSPATH' ) || exit;

/**
 * Resolve an item and turn its values into output for page builders.
 *
 * Field reference: "{type}:{key}", e.g. "produkty:cena".
 * System keys: id, title, slug, created, modified, permalink.
 *
 * Item source:
 *   current – the item linked to the current post (single template, loop)
 *   id      – a specific item by ID
 *   slug    – a specific item by slug
 */
class Gruppa_Render {

	const SYSTEM = array(
		'title'     => 'Title',
		'slug'      => 'Slug',
		'id'        => 'ID',
		'created'   => 'Created',
		'modified'  => 'Modified',
		'permalink' => 'Permalink',
	);

	private static $cache = array();

	/* ---------- Field list (for editors) ---------- */

	/**
	 * @param string[] $only  field types to include ('system' = system keys)
	 * @return array [ [ 'type' => slug, 'label' => name, 'options' => [ ref => label ] ] ]
	 */
	public static function field_groups( $only = array() ) {
		$groups = array();
		foreach ( Gruppa_Types::all( 'publish' ) as $type ) {
			$opts = array();
			if ( ! $only || in_array( 'system', $only, true ) ) {
				foreach ( self::SYSTEM as $k => $l ) {
					if ( 'permalink' === $k && ! $type->has_single ) {
						continue;
					}
					$opts[ $type->slug . ':' . $k ] = $l;
				}
			}
			foreach ( Gruppa_Types::fields_all( $type ) as $f ) {
				if ( Gruppa_Fields::is_password( $f ) || Gruppa_Fields::is_virtual( $f ) ) {
					continue;
				}
				if ( ! $only || in_array( $f['type'], $only, true ) ) {
					$opts[ $type->slug . ':' . $f['key'] ] = $f['label'];
				}
			}
			if ( $opts ) {
				$groups[] = array( 'type' => $type->slug, 'label' => $type->name, 'options' => $opts );
			}
		}
		return $groups;
	}

	/* ---------- Item resolution ---------- */

	public static function parse_ref( $ref ) {
		$parts = explode( ':', (string) $ref, 2 );
		return 2 === count( $parts ) ? $parts : array( '', '' );
	}

	/**
	 * @return array|null [ type, item ]
	 */
	public static function item( $type_slug, $source = 'current', $value = '', $post_id = 0 ) {
		// Inside a Listing: "current" = the item being rendered (never cached).
		if ( ( 'current' === $source || '' === (string) $source ) && class_exists( 'Gruppa_Listing' ) ) {
			$loop = Gruppa_Listing::current();
			if ( $loop ) {
				return $loop[0]->slug === $type_slug ? $loop : null;
			}
			// Editing a listing template in Elementor: preview with the first item.
			$pid = $post_id ? (int) $post_id : (int) get_the_ID();
			if ( $pid && Gruppa_Listing::CPT === get_post_type( $pid ) ) {
				$prev = Gruppa_Listing::preview_item( $pid );
				return $prev && $prev[0]->slug === $type_slug ? $prev : null;
			}
		}
		$ck = $type_slug . '|' . $source . '|' . $value . '|' . $post_id;
		if ( array_key_exists( $ck, self::$cache ) ) {
			return self::$cache[ $ck ];
		}
		$out  = null;
		$type = Gruppa_Types::get_by_slug( $type_slug );

		if ( $type && 'publish' === $type->status ) {
			$item = null;
			if ( 'id' === $source && $value ) {
				$item = Gruppa_Items::get( $type, (int) $value );
			} elseif ( 'slug' === $source && '' !== (string) $value ) {
				$item = self::by_slug( $type, (string) $value );
			} else {
				$pid = $post_id ? (int) $post_id : (int) get_the_ID();
				if ( $pid && get_post_meta( $pid, '_gruppa_type', true ) === $type->slug ) {
					$item = Gruppa_Items::get( $type, (int) get_post_meta( $pid, '_gruppa_item', true ) );
				}
			}
			// Drafts are only visible to users who may edit them.
			if ( $item && 'publish' !== $item->status && ! Gruppa_Access::can( $type, 'update', null, $item ) ) {
				$item = null;
			}
			if ( $item ) {
				$out = array( $type, $item );
			}
		}
		self::$cache[ $ck ] = $out;
		return $out;
	}

	/**
	 * Item linked to the current item: $via = "type:link_key" of the current item's Relation field
	 * or data link, $want = slug of the linked type the caller expects. First linked item.
	 *
	 * @return array|null [ type, item ]
	 */
	public static function linked_item( $via, $want = '' ) {
		list( $tslug, $key ) = self::parse_ref( $via );
		if ( ! $tslug || ! $key ) {
			return null;
		}
		$cur = self::item( $tslug, 'current', '', (int) get_the_ID() );
		if ( ! $cur ) {
			return null;
		}
		$f  = Gruppa_Types::field( $cur[0], $key );
		$rt = $f && 'relation' === $f['type'] ? Gruppa_Fields::related_type( $f ) : null;
		if ( ! $rt || ( $want && $rt->slug !== $want ) ) {
			return null;
		}
		foreach ( Gruppa_Fields::relation_ids( $f, isset( $cur[1]->{$key} ) ? $cur[1]->{$key} : '' ) as $id ) {
			$it = Gruppa_Items::get( $rt, (int) $id );
			if ( $it && 'publish' === $it->status ) {
				return array( $rt, $it );
			}
		}
		return null;
	}

	private static function by_slug( $type, $slug ) {
		global $wpdb;
		$table = Gruppa_DB::data_table( $type->slug );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE slug = %s LIMIT 1", sanitize_title( $slug ) ) ); // phpcs:ignore
		if ( $row ) {
			Gruppa_Relations::attach( $type, array( $row ) );
		}
		return $row;
	}

	public static function field_def( $type, $key ) {
		foreach ( Gruppa_Types::fields_all( $type ) as $f ) {
			if ( $f['key'] === $key ) {
				return $f;
			}
		}
		return null;
	}

	/* ---------- Values ---------- */

	/** Published related items of a relation field value. */
	public static function related_items( $f, $raw ) {
		$rt  = Gruppa_Fields::related_type( $f );
		$ids = Gruppa_Fields::relation_ids( $f, $raw );
		if ( ! $rt || ! $ids ) {
			return array();
		}
		$out = array();
		foreach ( $ids as $rid ) {
			$ri = Gruppa_Items::get( $rt, $rid );
			if ( $ri && 'publish' === $ri->status ) {
				$out[] = $ri;
			}
		}
		return $out;
	}

	/** Attachment IDs of a media field. */
	public static function media_ids( $type, $item, $key ) {
		$f = self::field_def( $type, $key );
		if ( ! $f || 'media' !== $f['type'] ) {
			return array();
		}
		$raw  = isset( $item->{$key} ) ? $item->{$key} : null;
		$list = Gruppa_Fields::is_multi( $f ) ? Gruppa_Fields::decode( $f, $raw ) : ( $raw ? array( $raw ) : array() );
		$ids  = array();
		foreach ( $list as $v ) {
			$id = is_numeric( $v ) ? (int) $v : attachment_url_to_postid( $v );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/** URL for a field: media → file URL, permalink → post URL, other → value. */
	public static function url( $type, $item, $key ) {
		if ( 'permalink' === $key ) {
			return $item->wp_post_id ? (string) get_permalink( (int) $item->wp_post_id ) : '';
		}
		$f = self::field_def( $type, $key );
		if ( $f && 'media' === $f['type'] ) {
			$ids = self::media_ids( $type, $item, $key );
			return $ids ? (string) wp_get_attachment_url( $ids[0] ) : '';
		}
		if ( $f && 'relation' === $f['type'] ) {
			$rel = self::related_items( $f, isset( $item->{$key} ) ? $item->{$key} : null );
			return ( $rel && $rel[0]->wp_post_id ) ? (string) get_permalink( (int) $rel[0]->wp_post_id ) : '';
		}
		$v = self::text( $type, $item, $key, array( 'html' => false ) );
		return esc_url_raw( $v );
	}

	/**
	 * Text output.
	 *
	 * Options: separator, date_format, true_text, false_text,
	 * html (true = HTML-safe output: editor keeps its markup, everything else is escaped).
	 */
	/**
	 * Number with optional decimals / separators: decimals '' = as stored,
	 * dec_point / thousands_sep '' = plain (1234.5).
	 */
	public static function number( $n, $o = array() ) {
		$dec = isset( $o['decimals'] ) && '' !== (string) $o['decimals'] ? max( 0, min( 6, (int) $o['decimals'] ) ) : null;
		$dp  = isset( $o['dec_point'] ) ? (string) $o['dec_point'] : '';
		$ts  = isset( $o['thousands_sep'] ) ? (string) $o['thousands_sep'] : '';
		if ( null === $dec && '' === $dp && '' === $ts ) {
			return (string) $n;
		}
		if ( null === $dec ) {
			$s   = (string) $n;
			$dec = false === strpos( $s, '.' ) ? 0 : strlen( substr( strrchr( $s, '.' ), 1 ) );
		}
		$ts = ( 'space' === $ts || ' ' === $ts ) ? "\u{00A0}" : $ts;
		return number_format( (float) $n, $dec, '' !== $dp ? $dp : '.', $ts );
	}

	public static function text( $type, $item, $key, $o = array() ) {
		$html = ! isset( $o['html'] ) || $o['html'];
		$f    = isset( self::SYSTEM[ $key ] ) ? null : self::field_def( $type, $key );
		$rich = $f && in_array( $f['type'], array( 'editor', 'textarea', 'repeater' ), true );
		$out  = self::raw_text( $type, $item, $key, $o );
		return ( $html && ! $rich ) ? esc_html( $out ) : $out;
	}

	private static function raw_text( $type, $item, $key, $o = array() ) {
		$o = wp_parse_args( $o, array(
			'separator'   => ', ',
			'date_format' => '',
			'true_text'   => 'Yes',
			'false_text'  => 'No',
			'html'        => true,
		) );

		if ( isset( self::SYSTEM[ $key ] ) ) {
			switch ( $key ) {
				case 'permalink':
					return self::url( $type, $item, $key );
				case 'created':
				case 'modified':
					return $item->{$key} ? mysql2date( $o['date_format'] ? $o['date_format'] : get_option( 'date_format' ), $item->{$key} ) : '';
				default:
					return (string) $item->{$key};
			}
		}

		$f = self::field_def( $type, $key );
		if ( ! $f || Gruppa_Fields::is_password( $f ) ) {
			return '';
		}
		$raw = isset( $item->{$key} ) ? $item->{$key} : null;

		switch ( $f['type'] ) {
			case 'editor':
				if ( ! $o['html'] ) {
					return wp_strip_all_tags( (string) $raw );
				}
				return ( ! isset( $o['autop'] ) || $o['autop'] ) ? wpautop( wp_kses_post( (string) $raw ) ) : wp_kses_post( (string) $raw );

			case 'textarea':
				return $o['html'] ? nl2br( esc_html( (string) $raw ) ) : (string) $raw;

			case 'icon':
				if ( ! $raw ) {
					return '';
				}
				if ( ! $o['html'] ) {
					return (string) $raw;
				}
				wp_enqueue_style( 'dashicons' );
				return '<span class="dashicons ' . esc_attr( (string) $raw ) . '" aria-hidden="true"></span>';

			case 'number':
				if ( null === $raw || '' === $raw || ! is_numeric( $raw ) ) {
					return null === $raw ? '' : (string) $raw;
				}
				return self::number( $raw + 0, $o );

			case 'date':
				return $raw ? mysql2date( $o['date_format'] ? $o['date_format'] : get_option( 'date_format' ), $raw ) : '';

			case 'switcher':
				return $raw ? $o['true_text'] : $o['false_text'];

			case 'time':
				$t = Gruppa_Fields::decode( $f, $raw );
				return '' === $t ? '' : date_i18n( ! empty( $o['time_format'] ) ? $o['time_format'] : get_option( 'time_format' ), strtotime( '2000-01-01 ' . $t ) );

			case 'repeater':
				// One line per row, the row's values joined with " · " (HTML: rows separated by <br>).
				$lines = array();
				foreach ( Gruppa_Fields::decode( $f, $raw ) as $row ) {
					$cells = array();
					foreach ( $f['sub'] as $sd ) {
						$v = isset( $row[ $sd['key'] ] ) ? $row[ $sd['key'] ] : '';
						if ( 'switcher' === $sd['type'] ) {
							$v = $v ? $sd['label'] : '';
						} elseif ( 'select' === $sd['type'] ) {
							foreach ( $sd['options'] as $opt ) {
								if ( (string) $opt['value'] === (string) $v ) {
									$v = $opt['label'];
								}
							}
						} elseif ( 'media' === $sd['type'] ) {
							$v = $v ? (string) wp_get_attachment_url( (int) $v ) : '';
						} elseif ( 'date' === $sd['type'] && $v ) {
							$v = mysql2date( $o['date_format'] ? $o['date_format'] : get_option( 'date_format' ), $v );
						} elseif ( 'number' === $sd['type'] && '' !== (string) $v && null !== $v ) {
							$v = (string) ( $v + 0 );
						}
						if ( '' === (string) $v ) {
							continue;
						}
						if ( 'editor' === $sd['type'] ) {
							$cells[] = $o['html'] ? wp_kses_post( (string) $v ) : wp_strip_all_tags( (string) $v );
						} elseif ( 'icon' === $sd['type'] && $o['html'] ) {
							wp_enqueue_style( 'dashicons' );
							$cells[] = '<span class="dashicons ' . esc_attr( (string) $v ) . '" aria-hidden="true"></span>';
						} else {
							$cells[] = $o['html'] ? esc_html( (string) $v ) : (string) $v;
						}
					}
					if ( $cells ) {
						$lines[] = implode( ' · ', $cells );
					}
				}
				return implode( $o['html'] ? '<br>' : "\n", $lines );

			case 'radio':
			case 'select':
			case 'checkbox':
				$vals   = (array) Gruppa_Fields::decode( $f, $raw );
				$labels = array();
				foreach ( $f['options'] as $opt ) {
					$labels[ $opt['value'] ] = $opt['label'];
				}
				$out = array();
				foreach ( $vals as $v ) {
					if ( '' !== (string) $v ) {
						$out[] = isset( $labels[ $v ] ) ? $labels[ $v ] : $v;
					}
				}
				return implode( $o['separator'], $out );

			case 'media':
				$ids = self::media_ids( $type, $item, $key );
				return implode( $o['separator'], array_map( 'wp_get_attachment_url', $ids ) );

			case 'relation':
				$titles = array();
				foreach ( self::related_items( $f, $raw ) as $ri ) {
					$titles[] = $ri->title;
				}
				return implode( $o['separator'], $titles );
		}
		return (string) $raw;
	}
}
