<?php
/**
 * Source: Gruppa CMS content types.
 * Uses the Gruppa CMS API (Gruppa_Types, Gruppa_Items, Gruppa_Fields, Gruppa_Transfer::coerce).
 */

defined( 'ABSPATH' ) || exit;

class Gruppa_IE_Source_Gruppa extends Gruppa_IE_Source {

	/** Columns written directly after save (Gruppa_Items::save sets them itself). */
	const DIRECT = array( 'author', 'created', 'modified' );

	private $types = null;

	public function id() {
		return 'gruppa';
	}

	public function label() {
		return 'Gruppa CMS';
	}

	public function available() {
		return class_exists( 'Gruppa_Types' ) && class_exists( 'Gruppa_Items' ) && class_exists( 'Gruppa_Fields' );
	}

	public function id_key() {
		return 'id';
	}

	public function system_keys() {
		return array( 'id', 'status', 'order_index', 'author', 'created', 'modified', 'wp_post_id' );
	}

	private function type( $slug ) {
		if ( null === $this->types ) {
			$this->types = array();
			if ( $this->available() ) {
				foreach ( Gruppa_Types::all() as $t ) {
					$this->types[ $t->slug ] = $t;
				}
			}
		}
		return isset( $this->types[ $slug ] ) ? $this->types[ $slug ] : null;
	}

	/** Fields that hold data (no HTML / virtual fields). */
	private static function own_fields( $type ) {
		$out = array();
		foreach ( $type->fields as $f ) {
			if ( method_exists( 'Gruppa_Fields', 'is_virtual' ) && Gruppa_Fields::is_virtual( $f ) ) {
				continue;
			}
			$out[ $f['key'] ] = $f;
		}
		return $out;
	}

	/** Data links of a Content type (Gruppa CMS 0.22+): [ key => link ]. */
	private static function links( $type ) {
		$out = array();
		if ( class_exists( 'Gruppa_Relations' ) ) {
			foreach ( Gruppa_Relations::links( $type ) as $l ) {
				$out[ $l['key'] ] = $l;
			}
		}
		return $out;
	}

	private static function grouped( $type ) {
		return 'data' === $type->kind && isset( $type->structure ) && 'groups' === $type->structure;
	}

	public function types() {
		$this->type( '' );
		$out = array();
		foreach ( $this->types as $slug => $t ) {
			$out[ $slug ] = array(
				'name'  => $t->name,
				'count' => class_exists( 'Gruppa_DB' ) ? (int) Gruppa_DB::count_items( $slug ) : 0,
			);
		}
		return $out;
	}

	public function fields( $slug ) {
		$type = $this->type( $slug );
		if ( ! $type ) {
			return array();
		}
		$out = array(
			array( 'id', 'id', 'overwrites the item with the same ID', true ),
			array( 'title', 'Title', 'text', true ),
			array( 'slug', 'Slug', 'text', true ),
			array( 'status', 'status', 'publish / draft', true ),
			array( 'order_index', 'order_index', 'number', true ),
		);
		if ( self::grouped( $type ) ) {
			$out[] = array( 'group', 'Group', 'group name, slug or ID', true );
		}
		foreach ( self::own_fields( $type ) as $f ) {
			$out[] = array( $f['key'], $f['label'], 'repeater' === $f['type'] ? 'repeater (JSON rows)' : $f['type'], true );
		}
		foreach ( self::links( $type ) as $k => $l ) {
			$out[] = array( $k, $l['label'] . ' (' . $l['data_name'] . ')', 'link: titles, slugs or IDs', true );
		}
		foreach ( self::DIRECT as $k ) {
			$out[] = array( $k, $k, 'system', false );
		}
		return $out;
	}

	/* ---------- export ---------- */

	public function export( $slug, $status, $system ) {
		global $wpdb;
		$type = $this->type( $slug );
		if ( ! $type ) {
			return new WP_Error( 'type', 'Type does not exist.' );
		}
		$table = Gruppa_DB::data_table( $slug );
		$where = in_array( $status, array( 'publish', 'draft' ), true ) ? $wpdb->prepare( 'WHERE status = %s', $status ) : '';
		$rows  = (array) $wpdb->get_results( "SELECT * FROM `{$table}` {$where} ORDER BY order_index ASC, id ASC", ARRAY_A ); // phpcs:ignore

		$fields = array();
		foreach ( self::own_fields( $type ) as $k => $f ) {
			if ( ! Gruppa_Fields::is_password( $f ) ) {
				$fields[ $k ] = $f;
			}
		}
		$links   = self::links( $type );
		$grouped = self::grouped( $type );

		$cols = $system ? array( 'id', 'title', 'slug', 'status', 'order_index' ) : array( 'title', 'slug' );
		if ( $grouped ) {
			$cols[] = 'group';
		}
		$cols = array_merge( $cols, array_keys( $fields ), array_keys( $links ) );
		if ( $system ) {
			$cols = array_merge( $cols, array( 'author', 'created', 'modified', 'wp_post_id' ) );
		}

		// Linked values: titles (readable, portable between sites).
		if ( $links && class_exists( 'Gruppa_Relations' ) ) {
			$objs = array_map( function ( $r ) { return (object) $r; }, $rows );
			Gruppa_Relations::attach( $type, $objs );
			foreach ( $objs as $i => $o ) {
				foreach ( $links as $k => $l ) {
					$ids    = Gruppa_Fields::relation_ids( Gruppa_Relations::field( $l ), isset( $o->{$k} ) ? $o->{$k} : '' );
					$names  = Gruppa_Relations::options( $l );
					$rows[ $i ][ $k ] = array_values( array_filter( array_map( function ( $id ) use ( $names ) {
						return isset( $names[ $id ] ) ? preg_replace( '/ \(draft\)$/', '', $names[ $id ] ) : '';
					}, $ids ), 'strlen' ) );
				}
			}
		}

		$items = array();
		foreach ( $rows as $r ) {
			$item = array();
			foreach ( $cols as $c ) {
				$v = isset( $r[ $c ] ) ? $r[ $c ] : '';
				if ( 'group' === $c && $grouped ) {
					$g = ! empty( $r['group_id'] ) ? Gruppa_Relations::group( $type, (int) $r['group_id'] ) : null;
					$v = $g ? $g['slug'] : ( isset( $r['group_slug'] ) ? (string) $r['group_slug'] : '' );
				} elseif ( isset( $links[ $c ] ) ) {
					$v = 'one' === $links[ $c ]['choice'] ? ( $v ? $v[0] : '' ) : $v;
				} elseif ( isset( $fields[ $c ] ) ) {
					$f = $fields[ $c ];
					if ( in_array( $f['type'], array( 'repeater', 'time' ), true ) ) {
						$v = Gruppa_Fields::decode( $f, $v );
					} elseif ( Gruppa_Fields::is_multi( $f ) ) {
						$v = Gruppa_Fields::decode( $f, $v );
					} elseif ( 'number' === $f['type'] && '' !== $v && null !== $v ) {
						$v = $v + 0;
					}
				} elseif ( in_array( $c, array( 'id', 'order_index', 'author', 'wp_post_id' ), true ) ) {
					$v = (int) $v;
				}
				$item[ $c ] = null === $v ? '' : $v;
			}
			$items[] = $item;
		}
		return array( $cols, $items );
	}

	/* ---------- import ---------- */

	/** Group of a Data type by ID, slug or name. */
	private static function find_group( $type, $v ) {
		$v = trim( (string) $v );
		if ( '' === $v || ! class_exists( 'Gruppa_Relations' ) ) {
			return null;
		}
		$low = function_exists( 'mb_strtolower' ) ? mb_strtolower( $v ) : strtolower( $v );
		foreach ( $type->groups as $g ) {
			$name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $g['name'] ) : strtolower( $g['name'] );
			if ( (string) $g['id'] === $v || $g['slug'] === sanitize_key( $v ) || $name === $low ) {
				return $g;
			}
		}
		return null;
	}

	public function import( $slug, $mapping, $rows, $offset, $author, $keep ) {
		global $wpdb;
		$type = $this->type( $slug );
		if ( ! $type ) {
			return new WP_Error( 'type', 'Type does not exist.' );
		}
		$table  = Gruppa_DB::data_table( $slug );
		$fields  = self::own_fields( $type );
		$links   = self::links( $type );
		$grouped = self::grouped( $type );
		$core    = array( 'id', 'title', 'slug', 'status', 'order_index' );
		if ( $grouped ) {
			$core[] = 'group';
		}

		$map = array();
		foreach ( $mapping as $col => $target ) {
			if ( in_array( $target, $core, true ) || in_array( $target, self::DIRECT, true ) || isset( $fields[ $target ] ) || isset( $links[ $target ] ) ) {
				$map[ $col ] = $target;
			}
		}

		$res = self::result();

		foreach ( $rows as $i => $src ) {
			$n = $offset + $i + 1;
			if ( ! is_array( $src ) ) {
				$res['skipped']++;
				continue;
			}

			$row    = array();
			$sys    = array();
			$errors = array();
			$id     = 0;
			$picks  = array(); // link key => values

			foreach ( $map as $col => $target ) {
				if ( ! array_key_exists( $col, $src ) ) {
					continue;
				}
				$v = $src[ $col ];
				switch ( $target ) {
					case 'id':
						$id = absint( self::scalar( $v ) );
						break;
					case 'title':
						$row['title'] = sanitize_text_field( self::scalar( $v ) );
						break;
					case 'slug':
						$s = sanitize_title( self::scalar( $v ) );
						if ( '' !== $s ) {
							$row['slug'] = $s;
						}
						break;
					case 'status':
						$s             = strtolower( self::scalar( $v ) );
						$row['status'] = in_array( $s, array( 'draft', 'koncept' ), true ) ? 'draft' : 'publish';
						break;
					case 'order_index':
						if ( '' !== self::scalar( $v ) ) {
							$row['order_index'] = max( 0, (int) self::scalar( $v ) );
						}
						break;
					case 'group':
						$g = self::find_group( $type, self::scalar( $v ) );
						if ( $g ) {
							$row = array_merge( $row, Gruppa_Relations::group_columns( $type, $g['id'] ) );
						} elseif ( '' !== self::scalar( $v ) ) {
							$errors[] = 'Group: "' . self::scalar( $v ) . '" does not exist';
						}
						break;
					case 'author':
						$u = self::user_id( $v );
						if ( $u ) {
							$sys['author'] = $u;
						}
						break;
					case 'created':
					case 'modified':
						$d = self::date( $v );
						if ( $d ) {
							$sys[ $target ] = $d;
						}
						break;
					default:
						if ( isset( $links[ $target ] ) ) {
							$picks[ $target ] = self::to_list( $v );
							break;
						}
						$f = $fields[ $target ];
						if ( 'repeater' === $f['type'] && is_string( $v ) ) {
							$v = self::maybe_json( $v );
							$v = is_array( $v ) ? $v : array();
						}
						if ( Gruppa_Fields::keep_password( $f, $v, true ) ) {
							break;
						}
						// Repeater rows: Gruppa CMS 0.27+ converts each cell; older versions would join the rows into text.
						$cms_rows = defined( 'GRUPPA_CMS_VERSION' ) && version_compare( GRUPPA_CMS_VERSION, '0.27.0', '>=' );
						$raw      = class_exists( 'Gruppa_Transfer' ) && ( 'repeater' !== $f['type'] || $cms_rows ) ? Gruppa_Transfer::coerce( $f, $v ) : $v;
						list( $val, $err ) = Gruppa_Fields::sanitize_value( $f, $raw );
						if ( $err ) {
							$errors[] = $err;
						}
						$row[ $target ] = $val;
				}
			}

			$existing = $id ? Gruppa_Items::get( $type, $id ) : null;
			if ( ! $existing ) {
				$id = 0;
				if ( empty( $row['title'] ) ) {
					$errors[] = 'Title: required';
				}
				if ( $grouped && empty( $row['group_id'] ) ) {
					$errors[] = 'Group: required';
				}
				if ( ! isset( $row['status'] ) ) {
					$row['status'] = 'publish';
				}
				if ( ! isset( $sys['author'] ) ) {
					$sys['author'] = $author;
				}
			} else {
				if ( isset( $row['title'] ) && '' === $row['title'] ) {
					unset( $row['title'] );
				}
				if ( ! isset( $sys['author'] ) && ! $keep ) {
					$sys['author'] = $author;
				}
			}

			if ( $errors ) {
				$res['errors'][] = sprintf( 'Row %d: %s', $n, implode( '; ', $errors ) );
				$res['skipped']++;
				continue;
			}
			if ( ! $row && ! $sys && ! $picks ) {
				$res['skipped']++;
				continue;
			}

			$saved = $row || ! $id ? Gruppa_Items::save( $type, $row, $id ) : $id;
			if ( is_wp_error( $saved ) ) {
				$res['errors'][] = sprintf( 'Row %d: %s', $n, $saved->get_error_message() );
				$res['skipped']++;
				continue;
			}

			if ( $sys ) {
				$wpdb->update( $table, $sys, array( 'id' => (int) $saved ) );
				if ( $type->has_single && $type->post_type && isset( $sys['author'] ) ) {
					Gruppa_Items::sync_post( $type, (int) $saved );
				}
			}

			// Data links: titles, slugs or IDs of the linked values.
			foreach ( $picks as $k => $vals ) {
				$l = $links[ $k ];
				$ok = Gruppa_Relations::resolve( $l, $vals );
				if ( count( $ok ) < count( array_filter( $vals, 'strlen' ) ) ) {
					$res['errors'][] = sprintf( 'Row %d: %s — some values were not found and were left out', $n, $l['label'] );
				}
				Gruppa_Relations::set( $type, (int) $saved, $l, $ok );
			}

			$existing ? $res['updated']++ : $res['created']++;
		}

		return $res;
	}
}
