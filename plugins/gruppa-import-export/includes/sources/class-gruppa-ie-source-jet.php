<?php
/**
 * Source: JetEngine Custom Content Types.
 */

defined( 'ABSPATH' ) || exit;

class Gruppa_IE_Source_Jet extends Gruppa_IE_Source {

	const SYSTEM = array( '_ID', 'cct_status', 'cct_author_id', 'cct_created', 'cct_modified', 'cct_single_post_id' );

	/** System columns written directly after save (JetEngine overrides them). */
	const DIRECT = array( 'cct_author_id', 'cct_created', 'cct_modified', 'cct_single_post_id' );

	const ARRAY_TYPES = array( 'checkbox', 'repeater', 'gallery', 'posts', 'media', 'select', 'map' );

	private $cache  = null;
	private $tables = array();
	private $fcache = array();

	public function id() {
		return 'jet';
	}

	public function label() {
		return 'JetEngine CCT';
	}

	public function available() {
		return class_exists( '\Jet_Engine\Modules\Custom_Content_Types\Module' );
	}

	public function id_key() {
		return '_ID';
	}

	public function system_keys() {
		return self::SYSTEM;
	}

	/** @return array slug => content type factory */
	private function factories() {
		if ( null !== $this->cache ) {
			return $this->cache;
		}
		$this->cache = array();
		if ( ! $this->available() ) {
			return $this->cache;
		}

		$module  = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
		$manager = null;
		if ( $module && isset( $module->manager ) ) {
			$manager = $module->manager;
		} elseif ( $module && isset( $module->instance ) ) {
			$manager = $module->instance;
		}
		if ( ! $manager || ! method_exists( $manager, 'get_content_types' ) ) {
			return $this->cache;
		}

		$types = $manager->get_content_types();
		foreach ( (array) $types as $slug => $type ) {
			if ( $type ) {
				$this->cache[ $slug ] = $type;
			}
		}
		return $this->cache;
	}

	private function factory( $slug ) {
		$f = $this->factories();
		return isset( $f[ $slug ] ) ? $f[ $slug ] : null;
	}

	public function types() {
		$out = array();
		foreach ( $this->factories() as $slug => $type ) {
			$name = method_exists( $type, 'get_arg' ) ? $type->get_arg( 'name' ) : '';
			$out[ $slug ] = array(
				'name'  => $name ? $name : $slug,
				'count' => $this->count( $slug ),
			);
		}
		return $out;
	}

	public function table( $slug ) {
		global $wpdb;
		if ( isset( $this->tables[ $slug ] ) ) {
			return $this->tables[ $slug ];
		}
		$type  = $this->factory( $slug );
		$table = '';
		if ( $type && isset( $type->db ) && is_object( $type->db ) && method_exists( $type->db, 'table' ) ) {
			$table = $type->db->table();
		}
		if ( ! $table ) {
			$table = $wpdb->prefix . 'jet_cct_' . $slug;
		}
		$exists                = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		$this->tables[ $slug ] = $exists ? $table : '';
		return $this->tables[ $slug ];
	}

	private function count( $slug ) {
		global $wpdb;
		$t = $this->table( $slug );
		return $t ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}`" ) : 0; // phpcs:ignore
	}

	/**
	 * Custom fields: JetEngine definitions + any other table column.
	 *
	 * @return array name => [ title, type ]
	 */
	public function custom_fields( $slug ) {
		if ( isset( $this->fcache[ $slug ] ) ) {
			return $this->fcache[ $slug ];
		}
		$type = $this->factory( $slug );
		$out  = array();
		$add  = function ( $name, $data ) use ( &$out ) {
			if ( ! is_string( $name ) || '' === $name || isset( $out[ $name ] ) || in_array( $name, Gruppa_IE_Source_Jet::SYSTEM, true ) ) {
				return;
			}
			$data         = is_array( $data ) ? $data : array();
			$out[ $name ] = array(
				'title'  => ! empty( $data['title'] ) ? (string) $data['title'] : $name,
				'type'   => ! empty( $data['type'] ) ? (string) $data['type'] : 'text',
				// media / gallery: id, url or both (JetEngine "Value format").
				'format' => ! empty( $data['value_format'] ) ? (string) $data['value_format'] : 'id',
			);
		};

		if ( $type && method_exists( $type, 'get_formatted_fields' ) ) {
			foreach ( (array) $type->get_formatted_fields() as $name => $data ) {
				$add( $name, $data );
			}
		}
		if ( $type && ! $out && method_exists( $type, 'get_fields_list' ) ) {
			foreach ( (array) $type->get_fields_list( 'all', 'blocks' ) as $key => $field ) {
				$name = is_array( $field ) && ! empty( $field['name'] ) ? $field['name'] : ( is_string( $key ) ? $key : '' );
				$add( $name, $field );
			}
		}
		$table = $this->table( $slug );
		if ( $table ) {
			global $wpdb;
			foreach ( (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" ) as $col ) { // phpcs:ignore
				$add( $col, array() );
			}
		}
		$this->fcache[ $slug ] = $out;
		return $out;
	}

	public function fields( $slug ) {
		$out = array(
			array( '_ID', '_ID', 'overwrites the item with the same ID', true ),
			array( 'cct_status', 'cct_status', 'publish / draft', true ),
		);
		foreach ( $this->custom_fields( $slug ) as $name => $f ) {
			$out[] = array( $name, $f['title'], $f['type'], true );
		}
		foreach ( self::DIRECT as $k ) {
			$out[] = array( $k, $k, 'system', false );
		}
		return $out;
	}

	/* ---------- export ---------- */

	public function export( $slug, $status, $system ) {
		global $wpdb;
		$table = $this->table( $slug );
		if ( ! $table ) {
			return new WP_Error( 'table', 'CCT table does not exist.' );
		}

		$where = in_array( $status, array( 'publish', 'draft' ), true ) ? $wpdb->prepare( 'WHERE cct_status = %s', $status ) : '';
		$rows  = (array) $wpdb->get_results( "SELECT * FROM `{$table}` {$where} ORDER BY _ID ASC", ARRAY_A ); // phpcs:ignore

		$fields = $this->custom_fields( $slug );
		$cols   = $system ? array( '_ID', 'cct_status' ) : array();
		$cols   = array_merge( $cols, array_keys( $fields ) );
		if ( $system ) {
			foreach ( self::DIRECT as $c ) {
				if ( ! $rows || array_key_exists( $c, $rows[0] ) ) {
					$cols[] = $c;
				}
			}
		}

		$items = array();
		foreach ( $rows as $r ) {
			$item = array();
			foreach ( $cols as $c ) {
				$v = isset( $r[ $c ] ) ? maybe_unserialize( $r[ $c ] ) : '';
				if ( in_array( $c, array( '_ID', 'cct_author_id', 'cct_single_post_id' ), true ) ) {
					$v = (int) $v;
				} elseif ( isset( $fields[ $c ] ) && 'media' === $fields[ $c ]['type'] ) {
					// URL is portable between sites; import accepts URL or attachment ID.
					$v = self::attachment_url( self::maybe_json( $v ) );
				} elseif ( isset( $fields[ $c ] ) && 'gallery' === $fields[ $c ]['type'] ) {
					$v = self::attachment_urls( $v );
				}
				$item[ $c ] = $v;
			}
			$items[] = $item;
		}
		return array( $cols, $items );
	}

	/* ---------- import ---------- */

	public function import( $slug, $mapping, $rows, $offset, $author, $keep ) {
		global $wpdb;

		$type    = $this->factory( $slug );
		$handler = $type && method_exists( $type, 'get_item_handler' ) ? $type->get_item_handler() : null;
		if ( ! $handler ) {
			return new WP_Error( 'handler', 'JetEngine CCT item handler is not available.' );
		}
		$table  = $this->table( $slug );
		$fields = $this->custom_fields( $slug );
		if ( ! $table ) {
			return new WP_Error( 'table', 'CCT table does not exist.' );
		}

		$map    = array();
		$direct = array();
		$id_col = null;
		foreach ( $mapping as $col => $target ) {
			if ( '_ID' === $target ) {
				$id_col = $col;
			} elseif ( in_array( $target, self::DIRECT, true ) ) {
				$direct[ $col ] = $target;
			} elseif ( 'cct_status' === $target || isset( $fields[ $target ] ) ) {
				$map[ $col ] = $target;
			}
		}

		$res = self::result();

		foreach ( $rows as $i => $src ) {
			// Media downloads can be slow: stop after the time budget, the browser sends the rest.
			if ( $i > 0 && self::time_up() ) {
				break;
			}
			$res['processed'] = $i + 1;
			$n = $offset + $i + 1;
			if ( ! is_array( $src ) ) {
				$res['skipped']++;
				continue;
			}

			$id       = null !== $id_col && isset( $src[ $id_col ] ) ? absint( self::scalar( $src[ $id_col ] ) ) : 0;
			$existing = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE _ID = %d", $id ), ARRAY_A ) : null; // phpcs:ignore
			if ( ! $existing ) {
				$id = 0;
			}

			$record = array();
			foreach ( $map as $col => $target ) {
				if ( ! array_key_exists( $col, $src ) ) {
					continue;
				}
				if ( 'cct_status' === $target ) {
					$s                    = strtolower( self::scalar( $src[ $col ] ) );
					$record['cct_status'] = in_array( $s, array( 'draft', 'koncept' ), true ) ? 'draft' : 'publish';
					continue;
				}
				$record[ $target ] = $this->value( $fields[ $target ], $src[ $col ] );
			}

			$sys = array();
			foreach ( $direct as $col => $target ) {
				if ( ! array_key_exists( $col, $src ) || '' === self::scalar( $src[ $col ] ) ) {
					continue;
				}
				if ( 'cct_author_id' === $target ) {
					$v = self::user_id( $src[ $col ] );
				} elseif ( 'cct_single_post_id' === $target ) {
					$v = absint( self::scalar( $src[ $col ] ) );
				} else {
					$v = self::date( $src[ $col ] );
				}
				if ( $v ) {
					$sys[ $target ] = $v;
				}
			}

			if ( ! $record && ! $sys ) {
				$res['skipped']++;
				continue;
			}

			if ( $existing ) {
				// Merge onto the stored row so unmapped fields are never wiped.
				$full = array();
				foreach ( array_keys( $fields ) as $f ) {
					if ( array_key_exists( $f, $existing ) ) {
						$full[ $f ] = maybe_unserialize( $existing[ $f ] );
					}
				}
				$full['cct_status'] = $existing['cct_status'];
				$record             = array_merge( $full, $record );
				$record['_ID']      = $id;
				if ( ! isset( $sys['cct_author_id'] ) ) {
					$sys['cct_author_id'] = $keep ? (int) $existing['cct_author_id'] : $author;
				}
			} else {
				if ( ! isset( $record['cct_status'] ) ) {
					$record['cct_status'] = 'publish';
				}
				if ( ! isset( $sys['cct_author_id'] ) ) {
					$sys['cct_author_id'] = $author;
				}
			}
			$record['cct_author_id'] = $sys['cct_author_id'];

			try {
				$result = $handler->update_item( $record );
			} catch ( \Throwable $e ) {
				$res['errors'][] = sprintf( 'Row %d: %s', $n, $e->getMessage() );
				$res['skipped']++;
				continue;
			}

			$saved_id = $existing ? $id : absint( $result );
			if ( ! $saved_id ) {
				$res['errors'][] = sprintf( 'Row %d: the item could not be saved.', $n );
				$res['skipped']++;
				continue;
			}

			// JetEngine sets author / dates itself — enforce the requested values.
			$wpdb->update( $table, $sys, array( '_ID' => $saved_id ) );

			$existing ? $res['updated']++ : $res['created']++;
		}

		return $res;
	}

	private function value( $field, $v ) {
		$type = $field['type'];
		if ( 'media' === $type || 'gallery' === $type ) {
			return $this->media_value( $field, $v );
		}
		if ( in_array( $type, self::ARRAY_TYPES, true ) ) {
			$v = self::maybe_json( $v );
		}
		if ( is_array( $v ) ) {
			return self::sanitize_deep( $v );
		}
		$v = (string) $v;
		switch ( $type ) {
			case 'wysiwyg':
			case 'html':
				return wp_kses_post( $v );
			case 'textarea':
				return sanitize_textarea_field( $v );
			default:
				return sanitize_text_field( $v );
		}
	}

	/**
	 * Media / gallery: ID, URL (this or another site — downloaded once), JSON or a list
	 * → value in the field's JetEngine value format.
	 */
	private function media_value( $field, $v ) {
		$v = self::maybe_json( $v );
		if ( is_array( $v ) && ( isset( $v['url'] ) || isset( $v['id'] ) ) ) {
			$v = array( $v );
		}
		$list = is_array( $v ) ? $v : self::to_list( $v );

		$items = array();
		foreach ( $list as $item ) {
			$id = self::attachment_id( $item );
			if ( ! $id ) {
				continue;
			}
			$url = (string) wp_get_attachment_url( $id );
			switch ( $field['format'] ) {
				case 'url':
					$items[] = $url;
					break;
				case 'both':
					$items[] = array(
						'id'  => $id,
						'url' => $url,
					);
					break;
				default:
					$items[] = $id;
			}
			if ( 'media' === $field['type'] ) {
				break;
			}
		}

		if ( 'media' === $field['type'] ) {
			return $items ? $items[0] : '';
		}
		return 'both' === $field['format'] ? $items : implode( ',', $items );
	}
}
