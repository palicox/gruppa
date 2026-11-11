<?php
defined( 'ABSPATH' ) || exit;

/**
 * Source: JetEngine Custom Content Types.
 *
 * Definitions are read straight from {prefix}jet_post_types (status = content-type) — the JetEngine
 * object API differs between versions. Items are read from {prefix}jet_cct_{slug}.
 */
class Gruppa_TB_Source_Jet extends Gruppa_TB_Source {

	/** Table Builder key => CCT column. */
	const SYSTEM = array(
		'id'       => array( '_ID', 'ID', 'number' ),
		'created'  => array( 'cct_created', 'Created', 'date' ),
		'modified' => array( 'cct_modified', 'Modified', 'date' ),
		'author'   => array( 'cct_author_id', 'Author', 'user' ),
	);

	private $defs   = null;
	private $tables = array();
	private $cols   = array();

	public function id() {
		return 'jet';
	}

	public function label() {
		return 'JetEngine CCT';
	}

	public function available() {
		return function_exists( 'jet_engine' ) || class_exists( 'Jet_Engine' );
	}

	public function missing_text() {
		return 'JetEngine is not active.';
	}

	public function has_slug() {
		return false;
	}

	/** slug => [ name, fields, has_single ] */
	private function defs() {
		global $wpdb;
		if ( null !== $this->defs ) {
			return $this->defs;
		}
		$this->defs = array();
		$table      = $wpdb->prefix . 'jet_post_types';
		if ( ! $this->available() || ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return $this->defs;
		}
		foreach ( (array) $wpdb->get_results( "SELECT * FROM `{$table}` WHERE status = 'content-type'", ARRAY_A ) as $r ) { // phpcs:ignore
			$args = self::decode( isset( $r['args'] ) ? $r['args'] : '' );
			$args = is_array( $args ) ? $args : array();
			$slug = ! empty( $r['slug'] ) ? $r['slug'] : ( isset( $args['slug'] ) ? $args['slug'] : '' );
			if ( '' === (string) $slug ) {
				continue;
			}
			$fields              = self::decode( isset( $r['meta_fields'] ) ? $r['meta_fields'] : '' );
			$this->defs[ $slug ] = array(
				'name'   => ! empty( $args['name'] ) ? $args['name'] : $slug,
				'fields' => is_array( $fields ) ? $fields : array(),
			);
		}
		return $this->defs;
	}

	/** Items table or '' when it does not exist. */
	public function table( $slug ) {
		global $wpdb;
		if ( ! isset( $this->tables[ $slug ] ) ) {
			$t                     = $wpdb->prefix . 'jet_cct_' . self::sql_key( $slug );
			$this->tables[ $slug ] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) ) ? $t : '';
		}
		return $this->tables[ $slug ];
	}

	/** Real columns of the items table. */
	private function db_columns( $slug ) {
		global $wpdb;
		if ( ! isset( $this->cols[ $slug ] ) ) {
			$t                   = $this->table( $slug );
			$this->cols[ $slug ] = $t ? (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$t}`" ) : array(); // phpcs:ignore
		}
		return $this->cols[ $slug ];
	}

	public function types() {
		$out = array();
		foreach ( $this->defs() as $slug => $d ) {
			$out[ $slug ] = $d['name'];
		}
		return $out;
	}

	/** [ columns, repeaters ] of a CCT. */
	private function fields( $slug ) {
		$d = $this->defs();
		return isset( $d[ $slug ] ) ? self::jet_fields( $d[ $slug ]['fields'] ) : array( array(), array() );
	}

	public function columns( $slug ) {
		$out = array();
		if ( ! $this->exists( $slug ) ) {
			return $out;
		}
		$db = $this->db_columns( $slug );
		foreach ( self::SYSTEM as $k => $c ) {
			if ( ! $db || in_array( $c[0], $db, true ) ) {
				$out[ $k ] = self::meta( $c[1], $c[2], 'system' );
			}
		}
		list( $cols, $reps ) = $this->fields( $slug );
		foreach ( $cols as $k => $m ) {
			if ( ! $db || in_array( $k, $db, true ) ) {
				$out[ $k ] = $m;
			}
		}
		// Columns added outside the field list (e.g. by import) — shown as text.
		foreach ( $db as $col ) {
			if ( ! isset( $out[ $col ] ) && ! isset( $reps[ $col ] ) && 0 !== strpos( $col, 'cct_' ) && '_ID' !== $col ) {
				$out[ $col ] = self::meta( $col, 'text', 'column' );
			}
		}
		return $out;
	}

	public function repeaters( $slug ) {
		list( , $reps ) = $this->fields( $slug );
		return $reps;
	}

	public function order_options( $slug ) {
		$order = array( array( 'id', 'ID' ), array( 'created', 'Created' ), array( 'modified', 'Modified' ), array( 'rand', 'Random' ) );
		foreach ( $this->columns( $slug ) as $k => $m ) {
			if ( ! isset( self::SYSTEM[ $k ] ) && in_array( $m['type'], array( 'text', 'number', 'date', 'time', 'datetime', 'select', 'radio', 'switcher' ), true ) ) {
				$order[] = array( $k, $m['label'] );
			}
		}
		return $order;
	}

	public function context_options( $slug ) {
		$out = array();
		foreach ( $this->columns( $slug ) as $k => $m ) {
			if ( 'posts' === $m['type'] ) {
				$out[] = array( $k, $m['label'] . ' (Posts)' );
			}
		}
		return $out;
	}

	/** CCT column of a Table Builder key. */
	private function col( $key ) {
		return isset( self::SYSTEM[ $key ] ) ? self::SYSTEM[ $key ][0] : $key;
	}

	public function query( $slug, $s, $page, $per ) {
		global $wpdb;
		$t = $this->table( $slug );
		if ( ! $t ) {
			return array( array(), 0 );
		}
		$db    = $this->db_columns( $slug );
		$where = array();
		if ( in_array( 'cct_status', $db, true ) ) {
			$where[] = "cct_status = 'publish'";
		}
		if ( ! empty( $s['only_mine'] ) ) {
			$where[] = $wpdb->prepare( 'cct_author_id = %d', get_current_user_id() );
		}
		if ( '' !== $s['context_field'] && in_array( $s['context_field'], $db, true ) ) {
			$id      = (int) get_the_ID();
			$c       = '`' . self::sql_key( $s['context_field'] ) . '`';
			$where[] = $wpdb->prepare( "( {$c} = %s OR {$c} LIKE %s OR {$c} LIKE %s OR {$c} LIKE %s )", (string) $id, '%"' . $id . '"%', '%i:' . $id . ';%', '%,' . $id . ',%' ); // phpcs:ignore
		}
		$where_sql = $where ? ' WHERE ' . implode( ' AND ', $where ) : '';

		$cols    = $this->columns( $slug );
		$orderby = isset( $cols[ $s['orderby'] ] ) || 'rand' === $s['orderby'] ? $s['orderby'] : 'id';
		$dir     = 'desc' === $s['order'] ? 'DESC' : 'ASC';
		if ( 'rand' === $orderby ) {
			$order_sql = 'RAND()';
		} else {
			$c         = '`' . self::sql_key( $this->col( $orderby ) ) . '`';
			$order_sql = 'number' === $cols[ $orderby ]['type'] ? "CAST({$c} AS DECIMAL(20,6)) {$dir}" : "{$c} {$dir}";
			$order_sql .= ', `_ID` ' . $dir;
		}

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}`{$where_sql}" ); // phpcs:ignore
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}`{$where_sql} ORDER BY {$order_sql} LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ) ); // phpcs:ignore
		return array( $rows, $total );
	}

	/** One item by _ID, or null. */
	private function item( $slug, $id ) {
		global $wpdb;
		$t = $this->table( $slug );
		return $t && $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE `_ID` = %d", (int) $id ) ) : null; // phpcs:ignore
	}

	/**
	 * Current CCT item: the object of a JetEngine Listing Grid, otherwise the item whose
	 * single page (cct_single_post_id) is being shown.
	 */
	private function current_item( $slug ) {
		global $wpdb;
		$obj = self::jet_current_object();
		if ( $obj && isset( $obj->_ID ) && ( ! isset( $obj->cct_slug ) || $obj->cct_slug === $slug ) ) {
			return $this->item( $slug, (int) $obj->_ID );
		}
		$t   = $this->table( $slug );
		$pid = (int) get_the_ID();
		if ( $t && $pid && in_array( 'cct_single_post_id', $this->db_columns( $slug ), true ) ) {
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE cct_single_post_id = %d LIMIT 1", $pid ) ); // phpcs:ignore
		}
		return null;
	}

	public function repeater_rows( $slug, $s ) {
		$item = 'current' === $s['item_source'] ? $this->current_item( $slug ) : $this->item( $slug, (int) $s['item_value'] );
		if ( ! $item ) {
			return 'current' === $s['item_source'] ? null : array();
		}
		$key = $s['field'];
		return isset( $item->{$key} ) ? self::jet_rows( $item->{$key} ) : array();
	}

	public function value( $slug, $item, $key ) {
		$c = $this->col( $key );
		return isset( $item->{$c} ) ? $item->{$c} : '';
	}

	public function url( $slug, $item, $key ) {
		if ( 'permalink' === $key ) {
			return ! empty( $item->cct_single_post_id ) ? (string) get_permalink( (int) $item->cct_single_post_id ) : '';
		}
		return self::to_url( $this->value( $slug, $item, $key ) );
	}
}
