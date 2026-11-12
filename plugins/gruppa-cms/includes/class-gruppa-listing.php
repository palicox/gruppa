<?php
defined( 'ABSPATH' ) || exit;

/**
 * Listing Builder — a query over one content type + an Elementor template for one item.
 *
 *   Elementor:  widget "Gruppa Listing"
 *   Shortcode:  [gruppa_listing listing="slug" per_page="6" (0 = all) columns="3" pagination="numbers|load_more|none"]
 *
 * The item template is a hidden post (post type gruppa_listing) edited in Elementor.
 * While an item is rendered, Gruppa dynamic tags with Item = "Current" read that item.
 */
class Gruppa_Listing {

	const CPT      = 'gruppa_listing';
	const MAX_PAGE = 100;

	const OPS = array(
		'eq'        => '=',
		'neq'       => '≠',
		'contains'  => 'contains',
		'gt'        => '>',
		'gte'       => '≥',
		'lt'        => '<',
		'lte'       => '≤',
		'empty'     => 'is empty',
		'not_empty' => 'is not empty',
	);

	/** System columns usable for ordering / filtering. */
	const SYSTEM = array(
		'id'          => 'ID',
		'title'       => 'Title',
		'slug'        => 'Slug',
		'order_index' => 'Order',
		'created'     => 'Created',
		'modified'    => 'Modified',
		'author'      => 'Author (user ID)',
	);

	private static $stack = array();

	/** Listings being rendered right now (a template must not contain its own listing). */
	private static $active = array();
	private static $count = 0;

	public static function defaults() {
		return array(
			'orderby'    => 'order_index',
			'order'      => 'asc',
			'per_page'   => 12,
			'only_mine'     => false,
			'context_field' => '',
			'group'         => 0,
			'empty_text' => __( 'Nothing found.', 'gruppa-cms' ),
		);
	}

	/* ======================================================
	 * CRUD
	 * ==================================================== */

	public static function table() {
		return Gruppa_DB::listings_table();
	}

	private static function hydrate( $row ) {
		$s             = $row->settings ? json_decode( $row->settings, true ) : array();
		$row->settings = wp_parse_args( is_array( $s ) ? $s : array(), self::defaults() );
		return $row;
	}

	public static function all( $status = '' ) {
		global $wpdb;
		$where = $status ? $wpdb->prepare( 'WHERE status = %s', $status ) : '';
		return array_map( array( __CLASS__, 'hydrate' ), (array) $wpdb->get_results( 'SELECT * FROM `' . self::table() . "` {$where} ORDER BY order_index ASC, id ASC" ) ); // phpcs:ignore
	}

	public static function counts() {
		global $wpdb;
		$out = array( 'all' => 0, 'publish' => 0, 'draft' => 0 );
		foreach ( (array) $wpdb->get_results( 'SELECT status, COUNT(*) c FROM `' . self::table() . '` GROUP BY status' ) as $r ) { // phpcs:ignore
			$out[ $r->status ] = (int) $r->c;
			$out['all']       += (int) $r->c;
		}
		return $out;
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . self::table() . '` WHERE id = %d', $id ) ); // phpcs:ignore
		return $row ? self::hydrate( $row ) : null;
	}

	public static function get_by( $ref ) {
		if ( is_numeric( $ref ) ) {
			return self::get( (int) $ref );
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . self::table() . '` WHERE slug = %s', sanitize_key( $ref ) ) ); // phpcs:ignore
		return $row ? self::hydrate( $row ) : null;
	}

	/** Columns of a type usable in queries: key => [ label, field def|null ]. */
	public static function columns( $type ) {
		$out = array();
		foreach ( self::SYSTEM as $k => $l ) {
			$out[ $k ] = array( $l, null );
		}
		foreach ( Gruppa_Types::fields_all( $type ) as $f ) {
			if ( ! Gruppa_Fields::is_password( $f ) && ! in_array( $f['type'], array( 'media', 'repeater', 'html' ), true ) ) {
				$out[ $f['key'] ] = array( $f['label'], $f );
			}
		}
		return $out;
	}

	public static function sanitize_settings( $raw, $type ) {
		$raw  = is_array( $raw ) ? $raw : array();
		$d    = self::defaults();
		$cols = $type ? self::columns( $type ) : array();
		$s    = array(
			'orderby'    => isset( $raw['orderby'] ) && ( 'rand' === $raw['orderby'] || ( isset( $cols[ $raw['orderby'] ] ) && ! Gruppa_Relations::is_link( $cols[ $raw['orderby'] ][1] ) ) ) ? $raw['orderby'] : 'order_index',
			'order'      => isset( $raw['order'] ) && 'desc' === $raw['order'] ? 'desc' : 'asc',
			'per_page'   => max( 0, min( self::MAX_PAGE, isset( $raw['per_page'] ) ? (int) $raw['per_page'] : $d['per_page'] ) ), // 0 = all items
			'only_mine'     => ! empty( $raw['only_mine'] ),
			'context_field' => '',
			'empty_text' => sanitize_text_field( isset( $raw['empty_text'] ) ? $raw['empty_text'] : $d['empty_text'] ),
		);
		$ctx = isset( $raw['context_field'] ) ? sanitize_key( $raw['context_field'] ) : '';
		if ( isset( $cols[ $ctx ] ) && $cols[ $ctx ][1] && 'relation' === $cols[ $ctx ][1]['type'] ) {
			$s['context_field'] = $ctx;
		}
		// Data type with groups: list only the values of one group (0 = all).
		$s['group'] = 0;
		if ( $type && 'data' === $type->kind && 'groups' === $type->structure && ! empty( $raw['group'] ) && Gruppa_Relations::group( $type, (int) $raw['group'] ) ) {
			$s['group'] = (int) $raw['group'];
		}
		return $s;
	}

	/** @return int|WP_Error */
	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$now     = current_time( 'mysql' );
		$current = $id ? self::get( $id ) : null;
		if ( $id && ! $current ) {
			return new WP_Error( 'missing', 'Not found' );
		}
		$name = sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' );
		if ( '' === $name ) {
			return new WP_Error( 'name', 'Name' );
		}
		$type = Gruppa_Types::get_by_slug( sanitize_key( isset( $data['type_slug'] ) ? $data['type_slug'] : '' ) );
		if ( ! $type ) {
			return new WP_Error( 'form_type', 'Type' );
		}
		$row = array(
			'name'      => $name,
			'modified'  => $now,
			'status'    => isset( $data['status'] ) && 'draft' === $data['status'] ? 'draft' : 'publish',
			'type_slug' => $type->slug,
			'settings'  => wp_json_encode( self::sanitize_settings( isset( $data['settings'] ) ? $data['settings'] : array(), $type ) ),
		);
		if ( isset( $data['order_index'] ) && '' !== $data['order_index'] ) {
			$row['order_index'] = max( 0, (int) $data['order_index'] );
		}
		if ( $current ) {
			$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$slug = Gruppa_Types::sanitize_key( ! empty( $data['slug'] ) ? $data['slug'] : $name );
		if ( '' === $slug ) {
			return new WP_Error( 'slug', 'Slug' );
		}
		if ( self::get_by( $slug ) ) {
			return new WP_Error( 'slug_exists', 'Slug exists' );
		}
		$row = array_merge( $row, array(
			'slug'        => $slug,
			'author'      => get_current_user_id(),
			'created'     => $now,
			'order_index' => isset( $row['order_index'] ) ? $row['order_index'] : Gruppa_DB::next_order( '_listings' ),
		) );
		if ( false === $wpdb->insert( self::table(), $row ) ) {
			return new WP_Error( 'db', $wpdb->last_error );
		}
		return (int) $wpdb->insert_id;
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'status' => 'draft' === $status ? 'draft' : 'publish', 'modified' => current_time( 'mysql' ) ), array( 'id' => (int) $id ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		$l = self::get( $id );
		if ( $l && $l->template_id && self::CPT === get_post_type( (int) $l->template_id ) ) {
			wp_delete_post( (int) $l->template_id, true );
		}
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/* ======================================================
	 * Query
	 * ==================================================== */

	/**
	 * SQL condition for one rule. Multi-value fields (checkboxes, multiple select / relation)
	 * are matched as "list contains value" — JSON arrays and comma lists.
	 *
	 * @param array|null $f field definition (null = system column)
	 */
	public static function condition( $col, $f, $op, $value ) {
		global $wpdb;
		if ( Gruppa_Relations::is_link( $f ) ) {
			return Gruppa_Relations::condition( $f, $op, $value ); // Data link: picks live in wp_gcms_relations.
		}
		$c     = '`' . preg_replace( '/[^a-z0-9_]/', '', $col ) . '`';
		$value = (string) $value;
		$multi = $f && ( Gruppa_Fields::is_multi( $f ) || ( 'checkbox' === $f['type'] ) );
		$num   = ( $f && 'number' === $f['type'] ) || in_array( $col, array( 'id', 'order_index', 'author' ), true );
		// Typed columns (decimal, date, tinyint, datetime): comparing with '' would turn 0 into "empty".
		$typed = $num || ( $f && in_array( $f['type'], array( 'date', 'time', 'switcher' ), true ) ) || in_array( $col, array( 'created', 'modified' ), true );

		switch ( $op ) {
			case 'empty':
				return $typed ? "{$c} IS NULL" : "( {$c} IS NULL OR {$c} = '' OR {$c} = '[]' )";
			case 'not_empty':
				return $typed ? "{$c} IS NOT NULL" : "( {$c} IS NOT NULL AND {$c} <> '' AND {$c} <> '[]' )";
			case 'contains':
				return $wpdb->prepare( "{$c} LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' ); // phpcs:ignore
			case 'gt':
			case 'gte':
			case 'lt':
			case 'lte':
				$sym = array( 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<=' )[ $op ];
				if ( $num ) {
					return $wpdb->prepare( "( {$c} IS NOT NULL AND {$c} {$sym} %f )", (float) str_replace( ',', '.', $value ) ); // phpcs:ignore
				}
				if ( is_numeric( str_replace( ',', '.', $value ) ) && ! $typed ) {
					return $wpdb->prepare( "( {$c} IS NOT NULL AND {$c} <> '' AND {$c} + 0 {$sym} %f )", (float) str_replace( ',', '.', $value ) ); // phpcs:ignore
				}
				return $wpdb->prepare( "{$c} {$sym} %s", $value ); // phpcs:ignore
		}

		// eq / neq.
		if ( $multi ) {
			$e   = $wpdb->esc_like( $value );
			$any = $wpdb->prepare(
				"( {$c} = %s OR {$c} LIKE %s OR {$c} = %s OR {$c} LIKE %s OR {$c} LIKE %s OR {$c} LIKE %s OR {$c} LIKE %s OR {$c} LIKE %s )", // phpcs:ignore
				$value, '%"' . $e . '"%', '[' . $value . ']', '[' . $e . ',%', '%,' . $e . ']', '%,' . $e . ',%', $e . ',%', '%,' . $e
			);
			return 'neq' === $op ? "( {$c} IS NULL OR NOT {$any} )" : $any;
		}
		if ( $num && is_numeric( $value ) ) {
			return $wpdb->prepare( "{$c} " . ( 'neq' === $op ? '<>' : '=' ) . ' %f', (float) $value ); // phpcs:ignore
		}
		return 'neq' === $op
			? $wpdb->prepare( "( {$c} IS NULL OR {$c} <> %s )", $value ) // phpcs:ignore
			: $wpdb->prepare( "{$c} = %s", $value ); // phpcs:ignore
	}

	/**
	 * @param array $args page, per_page, filters (extra rules [field, op, value], e.g. from frontend filters)
	 * @return array [ rows, total ]
	 */
	public static function query( $listing, $type, $args = array() ) {
		global $wpdb;
		$s     = $listing->settings;
		$args  = wp_parse_args( $args, array( 'page' => 1, 'per_page' => $s['per_page'], 'filters' => array(), 'ctx' => array() ) );
		$per   = max( 0, min( self::MAX_PAGE, (int) $args['per_page'] ) ); // 0 = all items
		$page  = max( 1, (int) $args['page'] );
		$cols  = self::columns( $type );
		$table = Gruppa_DB::data_table( $type->slug );
		$where = array( "status = 'publish'" );

		if ( $s['only_mine'] ) {
			$uid     = get_current_user_id();
			$where[] = $uid ? $wpdb->prepare( 'author = %d', $uid ) : '1=0';
		}
		if ( ! empty( $s['group'] ) && 'data' === $type->kind ) {
			$where[] = $wpdb->prepare( 'group_id = %d', (int) $s['group'] );
		}
		$ctx = array( 'listing' => $listing, 'type' => $type );
		// "Related to current item": Relation field contains the item whose page this is.
		if ( '' !== $s['context_field'] && isset( $cols[ $s['context_field'] ] ) ) {
			$cur = self::page_item( $args['ctx'] );
			if ( $cur && Gruppa_Fields::related_type( $cols[ $s['context_field'] ][1] ) && $cur[0]->slug === Gruppa_Fields::related_type( $cols[ $s['context_field'] ][1] )->slug ) {
				$where[] = self::condition( $s['context_field'], $cols[ $s['context_field'] ][1], 'eq', (string) $cur[1]->id );
			}
		}
		// Visitors' filters (Search Filters).
		foreach ( (array) $args['filters'] as $rule ) {
			$rop = isset( $rule['op'] ) ? $rule['op'] : 'eq';
			if ( 'search' === $rop ) {
				$like = '%' . $wpdb->esc_like( (string) $rule['value'] ) . '%';
				$or   = array();
				foreach ( (array) $rule['fields'] as $k ) {
					if ( 'title' === $k || 'slug' === $k || ( isset( $cols[ $k ] ) && $cols[ $k ][1] ) ) {
						$or[] = $wpdb->prepare( '`' . preg_replace( '/[^a-z0-9_]/', '', $k ) . '` LIKE %s', $like ); // phpcs:ignore
					}
				}
				if ( $or ) {
					$where[] = '( ' . implode( ' OR ', $or ) . ' )';
				}
				continue;
			}
			if ( empty( $rule['field'] ) || ! isset( $cols[ $rule['field'] ] ) ) {
				continue;
			}
			if ( 'any' === $rop ) {
				// Already stored values (IDs / option values) — any of them matches.
				$any = array();
				foreach ( (array) $rule['values'] as $v ) {
					$any[] = self::condition( $rule['field'], $cols[ $rule['field'] ][1], 'eq', (string) $v );
				}
				$where[] = $any ? '( ' . implode( ' OR ', $any ) . ' )' : '1=0';
				continue;
			}
			$value = isset( $rule['value'] ) ? (string) $rule['value'] : '';
			$op     = isset( $rule['op'] ) ? $rule['op'] : 'eq';
			$values = self::resolve_value( $value, $args['ctx'] );
			if ( null === $values ) {
				// Placeholder without a value (no current item, missing URL parameter): rule is skipped.
				continue;
			}
			if ( in_array( $op, array( 'eq', 'neq' ), true ) ) {
				$values = self::readable_to_stored( $cols[ $rule['field'] ][1], $values );
			}
			if ( count( $values ) > 1 && in_array( $op, array( 'eq', 'contains' ), true ) ) {
				// Current item has several values (e.g. 2 categories): match any of them.
				$any = array();
				foreach ( $values as $v ) {
					$any[] = self::condition( $rule['field'], $cols[ $rule['field'] ][1], $op, $v );
				}
				$where[] = '( ' . implode( ' OR ', $any ) . ' )';
			} elseif ( count( $values ) > 1 && 'neq' === $op ) {
				foreach ( $values as $v ) {
					$where[] = self::condition( $rule['field'], $cols[ $rule['field'] ][1], $op, $v );
				}
			} else {
				$where[] = self::condition( $rule['field'], $cols[ $rule['field'] ][1], $op, $values ? $values[0] : '' );
			}
		}
		$where = implode( ' AND ', apply_filters( 'gruppa_cms/listing_where', $where, $ctx, $args ) );

		if ( 'rand' === $s['orderby'] ) {
			$order = 'RAND()';
		} else {
			$ob    = isset( $cols[ $s['orderby'] ] ) ? $s['orderby'] : 'order_index';
			$f     = $cols[ $ob ][1];
			$dir   = 'desc' === $s['order'] ? 'DESC' : 'ASC';
			$order = ( $f && 'number' === $f['type'] ) ? "`{$ob}` + 0 {$dir}" : "`{$ob}` {$dir}";
			$order .= ', id ASC';
		}

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE {$where}" ); // phpcs:ignore
		// $where is already prepared; LIMIT values are plain integers.
		$limit = $per > 0 ? ' LIMIT ' . (int) $per . ' OFFSET ' . (int) ( ( $page - 1 ) * $per ) : ''; // 0 = all items, no LIMIT.
		$rows  = (array) $wpdb->get_results( "SELECT * FROM `{$table}` WHERE {$where} ORDER BY {$order}{$limit}" ); // phpcs:ignore
		return array( Gruppa_Relations::attach( $type, $rows ), $total );
	}

	/* ======================================================
	 * Placeholders in filter values
	 * ==================================================== */

	/**
	 * Item of the page the listing is on (single page of a Gruppa item), or the item
	 * being rendered when the listing sits inside another listing's template.
	 *
	 * @return array|null [ type, item ]
	 */
	public static function page_item( $ctx = array() ) {
		$loop = self::current();
		if ( $loop ) {
			return $loop;
		}
		$pid = ! empty( $ctx['post_id'] ) ? (int) $ctx['post_id'] : ( is_singular() ? (int) get_queried_object_id() : 0 );
		if ( ! $pid ) {
			return null;
		}
		$tslug = get_post_meta( $pid, '_gruppa_type', true );
		$iid   = (int) get_post_meta( $pid, '_gruppa_item', true );
		$type  = $tslug && $iid ? Gruppa_Types::get_by_slug( $tslug ) : null;
		$item  = $type ? Gruppa_Items::get( $type, $iid ) : null;
		return $item ? array( $type, $item ) : null;
	}

	/**
	 * {user_id} {today} {now}
	 * {current_id}         ID of the current item (the page's item)
	 * {current:key}        value of a field / column of the current item (several values for multi fields)
	 * {get:param}          URL parameter, e.g. ?kategoria=5
	 *
	 * @return string[]|null values, null = a placeholder had no value (skip the rule)
	 */
	public static function resolve_value( $value, $ctx = array() ) {
		$value = str_replace(
			array( '{user_id}', '{today}', '{now}' ),
			array( (string) get_current_user_id(), current_time( 'Y-m-d' ), current_time( 'mysql' ) ),
			(string) $value
		);
		if ( false === strpos( $value, '{' ) ) {
			return array( $value );
		}
		$missing = false;
		$list    = null;
		$value   = preg_replace_callback( '/\{(current_id|current:([a-z0-9_]+)|get:([a-z0-9_\-]+))\}/', function ( $m ) use ( $ctx, &$missing, &$list ) {
			if ( 0 === strpos( $m[1], 'get:' ) ) {
				$src = isset( $ctx['get'] ) && is_array( $ctx['get'] ) ? $ctx['get'] : $_GET; // phpcs:ignore
				$v   = isset( $src[ $m[3] ] ) && is_scalar( $src[ $m[3] ] ) ? sanitize_text_field( wp_unslash( (string) $src[ $m[3] ] ) ) : '';
				if ( '' === $v ) {
					$missing = true;
				}
				return $v;
			}
			$cur = self::page_item( $ctx );
			if ( ! $cur ) {
				$missing = true;
				return '';
			}
			list( $type, $item ) = $cur;
			$key = 'current_id' === $m[1] ? 'id' : $m[2];
			$raw = isset( $item->{$key} ) ? $item->{$key} : null;
			foreach ( Gruppa_Types::fields_all( $type ) as $f ) {
				if ( $f['key'] === $key ) {
					$raw = 'relation' === $f['type'] ? Gruppa_Fields::relation_ids( $f, $raw ) : Gruppa_Fields::decode( $f, $raw );
				}
			}
			if ( is_array( $raw ) ) {
				$raw = array_values( array_filter( array_map( 'strval', $raw ), 'strlen' ) );
				if ( ! $raw ) {
					$missing = true;
					return '';
				}
				if ( count( $raw ) > 1 ) {
					$list = $raw; // Whole value is a list → returned as several values.
				}
				return $raw[0];
			}
			if ( null === $raw || '' === (string) $raw ) {
				$missing = true;
			}
			return (string) $raw;
		}, $value );

		if ( $missing ) {
			return null;
		}
		return $list && 1 === preg_match( '/^\{(current_id|current:[a-z0-9_]+)\}$/', $value ) ? $list : ( $list ?: array( $value ) );
	}

	/**
	 * Lets filters use readable values:
	 * Relation → title or slug of the related item ("Zimná", "zimna") becomes its ID;
	 * Select / Radio / Checkbox → option label ("Zimná dovolenka") becomes its value.
	 * Unknown names stay as they are (= no match).
	 */
	public static function readable_to_stored( $f, $values ) {
		if ( ! $f ) {
			return $values;
		}
		$out = array();
		foreach ( $values as $v ) {
			$v = trim( (string) $v );
			if ( Gruppa_Relations::is_link( $f ) && '' !== $v && ! ctype_digit( $v ) ) {
				$names = Gruppa_Relations::names( $f['link'] );
				$k     = function_exists( 'mb_strtolower' ) ? mb_strtolower( $v ) : strtolower( $v );
				$out[] = isset( $names[ $k ] ) ? (string) $names[ $k ] : '0';
				continue;
			}
			if ( 'relation' === $f['type'] && '' !== $v && ! ctype_digit( $v ) ) {
				$ids = self::related_ids_by_name( $f, $v );
				$out = array_merge( $out, $ids ? array_map( 'strval', $ids ) : array( '0' ) );
				continue;
			}
			if ( Gruppa_Fields::has_options( $f['type'] ) ) {
				$lv = function_exists( 'mb_strtolower' ) ? mb_strtolower( $v ) : strtolower( $v );
				foreach ( $f['options'] as $o ) {
					$ol = function_exists( 'mb_strtolower' ) ? mb_strtolower( $o['label'] ) : strtolower( $o['label'] );
					if ( $o['value'] !== $v && $ol === $lv ) {
						$v = $o['value'];
						break;
					}
				}
			}
			$out[] = $v;
		}
		return array_values( array_unique( $out ) );
	}

	/** IDs of related items whose title or slug matches (case-insensitive). */
	private static function related_ids_by_name( $f, $name ) {
		$rtype = Gruppa_Fields::related_type( $f );
		if ( ! $rtype ) {
			return array();
		}
		global $wpdb;
		$table = Gruppa_DB::data_table( $rtype->slug );
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE LOWER(title) = LOWER(%s) OR slug = %s", $name, sanitize_title( $name ) ) ) ); // phpcs:ignore
	}

	/* ======================================================
	 * Loop context (read by Gruppa dynamic tags)
	 * ==================================================== */

	/** @return array|null [ type, item ] of the item being rendered. */
	public static function current() {
		$top = end( self::$stack );
		return $top ? $top : null;
	}

	public static function in_loop() {
		return (bool) self::$stack;
	}

	/** Item shown while editing a template in Elementor: first item of the listing's type. */
	public static function preview_item( $post_id ) {
		if ( ! $post_id || self::CPT !== get_post_type( $post_id ) ) {
			return null;
		}
		$listing = self::get( (int) get_post_meta( $post_id, '_gruppa_listing', true ) );
		$type    = $listing ? Gruppa_Types::get_by_slug( $listing->type_slug ) : null;
		if ( ! $type ) {
			return null;
		}
		list( $rows ) = self::query( $listing, $type, array( 'per_page' => 1 ) );
		if ( ! $rows ) {
			global $wpdb;
			$rows = Gruppa_Relations::attach( $type, (array) $wpdb->get_results( 'SELECT * FROM `' . Gruppa_DB::data_table( $type->slug ) . '` ORDER BY order_index ASC, id ASC LIMIT 1' ) ); // phpcs:ignore
		}
		return $rows ? array( $type, $rows[0] ) : null;
	}

	/* ======================================================
	 * Render
	 * ==================================================== */

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_shortcode( 'gruppa_listing', array( __CLASS__, 'shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'template_redirect', array( __CLASS__, 'protect_template' ) );
		add_filter( 'wp_sitemaps_post_types', function ( $types ) {
			unset( $types[ self::CPT ] );
			return $types;
		} );
		// Elementor element cache must not reuse one item's output for the next.
		add_filter( 'elementor/element/is_dynamic_content', function ( $dynamic ) {
			return self::in_loop() ? true : $dynamic;
		} );
	}

	public static function register_cpt() {
		register_post_type( self::CPT, array(
			'label'               => 'Listing templates',
			'labels'              => array( 'name' => 'Listing templates', 'singular_name' => 'Listing template' ),
			'public'              => true, // Elementor preview needs a viewable URL; guarded in protect_template().
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => true,
			'capability_type'     => 'page',
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'elementor' ),
		) );
	}

	/** Templates are not pages: only editors may open them (Elementor preview). */
	public static function protect_template() {
		if ( is_singular( self::CPT ) && ! current_user_can( 'edit_post', get_queried_object_id() ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
		}
	}

	/** Creates the template post on first use. @return int post ID */
	public static function ensure_template( $listing ) {
		if ( $listing->template_id && self::CPT === get_post_type( (int) $listing->template_id ) ) {
			return (int) $listing->template_id;
		}
		$pid = wp_insert_post( array(
			'post_type'   => self::CPT,
			'post_status' => 'publish',
			'post_title'  => 'Listing: ' . $listing->name,
		) );
		if ( ! $pid || is_wp_error( $pid ) ) {
			return 0;
		}
		update_post_meta( $pid, '_gruppa_listing', (int) $listing->id );
		update_post_meta( $pid, '_elementor_edit_mode', 'builder' );
		update_post_meta( $pid, '_wp_page_template', 'elementor_canvas' );
		global $wpdb;
		$wpdb->update( self::table(), array( 'template_id' => (int) $pid ), array( 'id' => (int) $listing->id ) );
		return (int) $pid;
	}

	public static function elementor_ready( $template_id ) {
		return $template_id
			&& class_exists( '\Elementor\Plugin' )
			&& get_post( (int) $template_id )
			&& 'builder' === get_post_meta( (int) $template_id, '_elementor_edit_mode', true )
			&& get_post_meta( (int) $template_id, '_elementor_data', true );
	}

	public static function register_assets() {
		wp_register_style( 'gruppa-listing', GRUPPA_CMS_URL . 'assets/gruppa-listing.css', array(), GRUPPA_CMS_VERSION );
		wp_register_script( 'gruppa-listing', GRUPPA_CMS_URL . 'assets/gruppa-listing.js', array(), GRUPPA_CMS_VERSION, true );
		wp_register_script( 'gruppa-text', GRUPPA_CMS_URL . 'assets/gruppa-text.js', array(), GRUPPA_CMS_VERSION, true );
	}

	/** HTML of the items (no wrapper). */
	public static function items_html( $listing, $type, $rows, $with_css = false ) {
		$tid     = (int) $listing->template_id;
		$builder = self::elementor_ready( $tid );
		$html    = '';
		foreach ( $rows as $i => $row ) {
			$item = Gruppa_Items::get( $type, (int) $row->id );
			if ( ! $item ) {
				continue;
			}
			self::$stack[] = array( $type, $item );
			if ( $builder ) {
				$inner = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $tid, $with_css && 0 === $i );
			} else {
				$inner = self::fallback_card( $type, $item );
			}
			array_pop( self::$stack );
			$html .= '<div class="gruppa-listing-item" data-id="' . (int) $item->id . '">' . $inner . '</div>';
		}
		return $html;
	}

	private static function fallback_card( $type, $item ) {
		$url = $item->wp_post_id && get_post( (int) $item->wp_post_id ) ? get_permalink( (int) $item->wp_post_id ) : '';
		$t   = esc_html( $item->title );
		return '<div class="gruppa-listing-card"><h3 class="gruppa-listing-title">' . ( $url ? '<a href="' . esc_url( $url ) . '">' . $t . '</a>' : $t ) . '</h3></div>';
	}

	public static function shortcode( $atts ) {
		$a = shortcode_atts( array( 'listing' => '', 'id' => '', 'per_page' => '', 'columns' => 3, 'pagination' => 'numbers', 'load_more' => __( 'Load more', 'gruppa-cms' ), 'filters' => '' ), $atts, 'gruppa_listing' );
		return self::render( '' !== $a['listing'] ? $a['listing'] : $a['id'], array(
			'per_page'   => $a['per_page'],
			'pagination' => $a['pagination'],
			'load_more'  => $a['load_more'],
			'auto_cols'  => max( 1, min( 6, (int) $a['columns'] ) ),
			'filters'    => $a['filters'],
		) );
	}

	private static function page_param( $listing ) {
		return 'lp' . (int) $listing->id;
	}

	/**
	 * @param array $o per_page, pagination (none|numbers|load_more), load_more (text), empty_text, auto_cols (shortcode)
	 */
	public static function render( $ref, $o = array() ) {
		$o       = wp_parse_args( $o, array( 'per_page' => '', 'pagination' => 'numbers', 'load_more' => __( 'Load more', 'gruppa-cms' ), 'empty_text' => '', 'auto_cols' => 0, 'filters' => '' ) );
		$listing = '' !== (string) $ref ? self::get_by( $ref ) : null;
		$type    = $listing ? Gruppa_Types::get_by_slug( $listing->type_slug ) : null;
		$editor  = current_user_can( 'edit_posts' );
		if ( ! $listing || 'publish' !== $listing->status || ! $type || 'publish' !== $type->status ) {
			return $editor ? '<div class="gruppa-listing-message">Gruppa Listing: ' . ( $ref ? 'listing not found or not published.' : 'select a listing.' ) . '</div>' : '';
		}

		if ( isset( self::$active[ (int) $listing->id ] ) ) {
			return $editor ? '<div class="gruppa-listing-message">Gruppa Listing: a listing cannot be placed inside its own template.</div>' : '';
		}
		wp_enqueue_style( 'gruppa-listing' );
		wp_enqueue_script( 'gruppa-listing' );
		self::$count++;
		self::$active[ (int) $listing->id ] = true;

		// Empty = value from the listing, 0 = all items, number = that many.
		$per   = '' !== trim( (string) $o['per_page'] ) && (int) $o['per_page'] >= 0 ? min( self::MAX_PAGE, (int) $o['per_page'] ) : (int) $listing->settings['per_page'];
		$param = self::page_param( $listing );
		$page  = 'numbers' === $o['pagination'] && isset( $_GET[ $param ] ) ? max( 1, absint( $_GET[ $param ] ) ) : 1; // phpcs:ignore
		$ctx = array( 'post_id' => is_singular() ? (int) get_queried_object_id() : 0 );
		$cur = self::current();
		if ( $cur && ! empty( $cur[1]->wp_post_id ) ) {
			$ctx['post_id'] = (int) $cur[1]->wp_post_id; // Listing inside a listing template.
		}
		// Search Filters group controlling this listing.
		$group = '' !== (string) $o['filters'] && class_exists( 'Gruppa_Filters' ) ? Gruppa_Filters::get_by( $o['filters'] ) : null;
		$group = $group && 'publish' === $group->status && $group->type_slug === $type->slug ? $group : null;
		// No group chosen in the widget: a published group of the same type whose parameters are in the URL
		// (e.g. a Gruppa Search widget on the page that reloaded with ?typ_obsahu=clanok).
		if ( ! $group && '' === (string) $o['filters'] && class_exists( 'Gruppa_Filters' ) && ! empty( $_GET ) ) { // phpcs:ignore
			foreach ( Gruppa_Filters::all( 'publish' ) as $g ) {
				if ( $g->type_slug === $type->slug && array_intersect( Gruppa_Filters::param_names( $g ), array_keys( $_GET ) ) ) { // phpcs:ignore
					$group = $g;
					break;
				}
			}
		}
		$rules = $group ? Gruppa_Filters::rules( $group, $type ) : array();

		list( $rows, $total ) = self::query( $listing, $type, array( 'page' => $page, 'per_page' => $per, 'ctx' => $ctx, 'filters' => $rules ) );
		$pages = $per > 0 ? (int) ceil( $total / $per ) : 1;
		if ( '' !== (string) $o['filters'] && ! $group && $editor ) {
			$warn = '<div class="gruppa-listing-message">Filter group not found, not published or for another entity.</div>';
		}

		$classes = 'gruppa-listing' . ( $o['auto_cols'] ? ' gruppa-listing--auto' : '' );
		$style   = $o['auto_cols'] ? ' style="--gl-cols:' . (int) $o['auto_cols'] . '"' : '';
		$html    = sprintf(
			'<div class="%s"%s data-listing="%d" data-page="%d" data-pages="%d" data-per-page="%d" data-ctx="%d" data-filter-group="%d" data-type="%s" data-pagination="%s" data-more-text="%s" data-empty="%s" data-endpoint="%s">',
			esc_attr( $classes ), $style, (int) $listing->id, $page, $pages, $per, (int) $ctx['post_id'],
			$group ? (int) $group->id : 0, esc_attr( $type->slug ), esc_attr( $o['pagination'] ), esc_attr( Gruppa_I18n::text( $o['load_more'], 'Load more' ) ),
			esc_attr( Gruppa_I18n::text( '' !== $o['empty_text'] ? $o['empty_text'] : $listing->settings['empty_text'], 'Nothing found.' ) ),
			esc_url( rest_url( Gruppa_REST::NS . '/listing/' . (int) $listing->id ) )
		);
		if ( isset( $warn ) ) {
			$html .= $warn;
		}
		if ( ! $rows ) {
			$empty = Gruppa_I18n::text( '' !== $o['empty_text'] ? $o['empty_text'] : $listing->settings['empty_text'], 'Nothing found.' );
			$html .= '<div class="gruppa-listing-empty">' . esc_html( $empty ) . '</div>';
			if ( $editor && ! $rules ) {
				$why = array( 'only published items of ' . $type->name );
				if ( $listing->settings['only_mine'] ) {
					$why[] = '"Only my items" is on';
				}
				if ( '' !== $listing->settings['context_field'] ) {
					$why[] = '"Related to current item" (' . $listing->settings['context_field'] . ') is on';
				}
				$html .= '<div class="gruppa-listing-message">Visible to editors only — no items match: ' . esc_html( implode( ', ', $why ) ) . '.</div>';
			}
		}
		$html .= '<div class="gruppa-listing-grid">' . self::items_html( $listing, $type, $rows, true ) . '</div>';

		if ( $pages > 1 && 'numbers' === $o['pagination'] ) {
			$html .= self::pagination_html( $listing, $page, $pages, null );
		} elseif ( $pages > 1 && 'load_more' === $o['pagination'] ) {
			$html .= '<div class="gruppa-listing-more"><button type="button" class="gruppa-listing-more-button">' . esc_html( Gruppa_I18n::text( $o['load_more'], 'Load more' ) ) . '</button></div>';
		}
		unset( self::$active[ (int) $listing->id ] );
		return $html . '</div>';
	}

	/* ======================================================
	 * REST: load more
	 * ==================================================== */

	public static function routes() {
		register_rest_route( Gruppa_REST::NS, '/listing/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'rest_page' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'page'     => array( 'type' => 'integer', 'default' => 2, 'minimum' => 1 ),
				'per_page' => array( 'type' => 'integer', 'default' => -1, 'minimum' => -1, 'maximum' => self::MAX_PAGE ), // -1 = from the listing, 0 = all
			),
		) );
	}

	/** Page context sent by "Load more": page post ID + the page's URL parameters. */
	private static function request_ctx( $request ) {
		$get = array();
		parse_str( ltrim( (string) $request->get_param( 'qs' ), '?' ), $get );
		return array( 'post_id' => absint( $request->get_param( 'ctx' ) ), 'get' => $get );
	}

	/** Number pagination. $url = page address to build links on (null = current request). */
	public static function pagination_html( $listing, $page, $pages, $url ) {
		$param = self::page_param( $listing );
		$base  = null === $url ? add_query_arg( $param, '%#%' ) : add_query_arg( $param, '%#%', $url );
		$links = paginate_links( array(
			'base'      => esc_url_raw( str_replace( '%25%23%25', '%#%', $base ) ),
			'format'    => '',
			'current'   => $page,
			'total'     => $pages,
			'prev_text' => '‹',
			'next_text' => '›',
			'type'      => 'plain',
		) );
		return '<nav class="gruppa-listing-pagination" aria-label="Pagination">' . $links . '</nav>';
	}

	public static function rest_page( $request ) {
		$listing = self::get( (int) $request['id'] );
		$type    = $listing ? Gruppa_Types::get_by_slug( $listing->type_slug ) : null;
		if ( ! $listing || 'publish' !== $listing->status || ! $type || 'publish' !== $type->status ) {
			return new WP_Error( 'gruppa_listing', __( 'Listing not found.', 'gruppa-cms' ), array( 'status' => 404 ) );
		}
		$per   = (int) $request['per_page'] >= 0 ? (int) $request['per_page'] : (int) $listing->settings['per_page'];
		$ctx   = self::request_ctx( $request );
		$rules = array();
		if ( $request->get_param( 'group' ) && class_exists( 'Gruppa_Filters' ) ) {
			$group = Gruppa_Filters::get( absint( $request->get_param( 'group' ) ) );
			if ( $group && 'publish' === $group->status ) {
				$rules = Gruppa_Filters::rules( $group, $type, $ctx['get'] );
			}
		}
		$rules = apply_filters( 'gruppa_cms/listing_request_filters', $rules, $listing, $type, $request );
		$page  = (int) $request['page'];
		list( $rows, $total ) = self::query( $listing, $type, array(
			'page'     => $page,
			'per_page' => $per,
			'filters'  => $rules,
			'ctx'      => $ctx,
		) );
		$pages = $per > 0 ? (int) ceil( $total / $per ) : 1;
		$url   = esc_url_raw( (string) $request->get_param( 'url' ) );
		$url   = $url && wp_validate_redirect( $url, '' ) ? $url : home_url( '/' );
		return rest_ensure_response( array(
			'html'       => self::items_html( $listing, $type, $rows ),
			'page'       => $page,
			'pages'      => $pages,
			'total'      => $total,
			'pagination' => $pages > 1 ? self::pagination_html( $listing, $page, $pages, remove_query_arg( self::page_param( $listing ), $url ) ) : '',
		) );
	}
}
