<?php
/**
 * Data links (0.22): values of a Data type picked in items of Content types.
 *
 * A Data type is a container of values. With structure "groups" its values are split
 * into groups (columns group_id / group_title / group_slug); every group – or the whole
 * Data type with structure "values" – is attached to one or more Content types.
 *
 * Each attachment is exposed to the rest of the plugin as a pseudo field of type
 * "relation" with the key "{data}__{group}" (or "{data}__values"), so listings, filters,
 * dynamic tags and REST treat it like a Relation field. The picked values are stored in
 * wp_gcms_relations (one row = one value picked for one item), never in the item table.
 */

defined( 'ABSPATH' ) || exit;

class Gruppa_Relations {

	const SEP    = '__';
	const VALUES = 'values';

	/** @var array|null content type slug => link defs */
	private static $links = null;

	public static function table() {
		return Gruppa_DB::system_table( 'relations' );
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			item_type varchar(64) NOT NULL DEFAULT '',
			item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			data_type varchar(64) NOT NULL DEFAULT '',
			value_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_index int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY item_value (item_type,item_id,value_id),
			KEY data_value (data_type,value_id),
			KEY item (item_type,item_id)
		) {$wpdb->get_charset_collate()};" );
	}

	public static function reset() {
		self::$links = null;
	}

	/* ---------- Type settings ---------- */

	public static function structures() {
		return array(
			'values' => 'Single records',
			'groups' => 'Grouped records',
		);
	}

	public static function choices() {
		return array(
			'many'   => 'Many (checkboxes)',
			'one'    => 'One (radio)',
			'select' => 'One (select with search)',
		);
	}

	/** Stored choice: many | one | select (= one, picked from a dropdown with search). */
	public static function choice( $v ) {
		return in_array( $v, array( 'one', 'select' ), true ) ? $v : 'many';
	}

	/** Content types a Data type may attach to: [ slug => name ]. */
	public static function target_options( $exclude_slug = '' ) {
		$out = array();
		foreach ( Gruppa_Types::all() as $t ) {
			if ( 'content' === $t->kind && $t->slug !== $exclude_slug ) {
				$out[ $t->slug ] = $t->name;
			}
		}
		return $out;
	}

	private static function clean_targets( $raw, $self_slug ) {
		$valid = self::target_options( $self_slug );
		$out   = array();
		foreach ( (array) $raw as $s ) {
			$s = sanitize_key( $s );
			if ( isset( $valid[ $s ] ) ) {
				$out[] = $s;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Data-type settings from the type form.
	 *
	 * @param array       $data     Posted form.
	 * @param object|null $type     Saved type (null = new).
	 * @param string      $self_slug Slug of the type being saved.
	 * @return array|WP_Error [ settings part, renamed groups (id => title), removed group ids ]
	 */
	public static function sanitize_settings( $data, $type, $self_slug ) {
		$structure = isset( $data['structure'] ) && 'groups' === $data['structure'] ? 'groups' : 'values';
		$link      = isset( $data['link'] ) && is_array( $data['link'] ) ? $data['link'] : array();
		$out       = array(
			'structure' => $structure,
			'link'      => array(
				'targets' => self::clean_targets( isset( $link['targets'] ) ? $link['targets'] : array(), $self_slug ),
				'choice'  => self::choice( isset( $link['choice'] ) ? $link['choice'] : '' ),
				'list'    => empty( $link['list'] ) ? 0 : 1,
			),
			'groups'    => array(),
			'group_seq' => $type && isset( $type->settings['group_seq'] ) ? (int) $type->settings['group_seq'] : 0,
		);

		$old = array();
		foreach ( $type ? $type->groups : array() as $g ) {
			$old[ (int) $g['id'] ] = $g;
		}
		$renamed = array();
		$seen    = array();
		foreach ( (array) ( isset( $data['groups'] ) ? $data['groups'] : array() ) as $g ) {
			$name = sanitize_text_field( isset( $g['name'] ) ? $g['name'] : '' );
			if ( '' === $name ) {
				continue;
			}
			$gid = isset( $g['id'] ) ? (int) $g['id'] : 0;
			if ( $gid && isset( $old[ $gid ] ) ) {
				$slug = $old[ $gid ]['slug']; // Fixed after the first save.
				if ( $old[ $gid ]['name'] !== $name ) {
					$renamed[ $gid ] = $name;
				}
			} else {
				$gid  = ++$out['group_seq'];
				$slug = Gruppa_Types::sanitize_key( ! empty( $g['slug'] ) ? $g['slug'] : $name );
				if ( '' === $slug || self::VALUES === $slug ) {
					$slug = 'group_' . $gid;
				}
			}
			$base = $slug;
			for ( $n = 2; isset( $seen[ $slug ] ); $n++ ) {
				$slug = $base . '_' . $n;
			}
			$seen[ $slug ]   = true;
			$out['groups'][] = array(
				'id'      => $gid,
				'name'    => $name,
				'slug'    => $slug,
				'targets' => self::clean_targets( isset( $g['targets'] ) ? $g['targets'] : array(), $self_slug ),
				'choice'  => self::choice( isset( $g['choice'] ) ? $g['choice'] : '' ),
				'list'    => empty( $g['list'] ) ? 0 : 1,
			);
		}

		$kept    = wp_list_pluck( $out['groups'], 'id' );
		$removed = array_values( array_diff( array_keys( $old ), $kept ) );
		if ( 'groups' !== $structure ) {
			$removed = array();
		}
		return array( $out, $renamed, $removed );
	}

	/** Normalized groups of a type's settings. */
	public static function groups_of( $settings ) {
		$out = array();
		foreach ( (array) ( isset( $settings['groups'] ) ? $settings['groups'] : array() ) as $g ) {
			if ( empty( $g['id'] ) || empty( $g['slug'] ) ) {
				continue;
			}
			$out[] = array(
				'id'      => (int) $g['id'],
				'name'    => (string) ( isset( $g['name'] ) ? $g['name'] : '' ),
				'slug'    => (string) $g['slug'],
				'targets' => array_values( (array) ( isset( $g['targets'] ) ? $g['targets'] : array() ) ),
				'choice'  => self::choice( isset( $g['choice'] ) ? $g['choice'] : '' ),
				'list'    => empty( $g['list'] ) ? 0 : 1,
			);
		}
		return $out;
	}

	public static function link_of( $settings ) {
		$l = isset( $settings['link'] ) && is_array( $settings['link'] ) ? $settings['link'] : array();
		return array(
			'targets' => array_values( (array) ( isset( $l['targets'] ) ? $l['targets'] : array() ) ),
			'choice'  => self::choice( isset( $l['choice'] ) ? $l['choice'] : '' ),
			'list'    => empty( $l['list'] ) ? 0 : 1,
		);
	}

	public static function group( $data_type, $gid ) {
		foreach ( $data_type->groups as $g ) {
			if ( (int) $g['id'] === (int) $gid ) {
				return $g;
			}
		}
		return null;
	}

	public static function group_by_slug( $data_type, $slug ) {
		foreach ( $data_type->groups as $g ) {
			if ( $g['slug'] === $slug ) {
				return $g;
			}
		}
		return null;
	}

	/** Group columns of a value row. */
	public static function group_columns( $data_type, $gid ) {
		$g = 'groups' === $data_type->structure ? self::group( $data_type, $gid ) : null;
		return $g
			? array( 'group_id' => (int) $g['id'], 'group_title' => $g['name'], 'group_slug' => $g['slug'] )
			: array( 'group_id' => 0, 'group_title' => '', 'group_slug' => '' );
	}

	/** After saving a Data type: renamed groups → group_title of their values. */
	public static function sync_group_titles( $data_type, $renamed ) {
		global $wpdb;
		$table = Gruppa_DB::data_table( $data_type->slug );
		foreach ( $renamed as $gid => $name ) {
			$wpdb->update( $table, array( 'group_title' => $name ), array( 'group_id' => (int) $gid ) );
		}
	}

	public static function count_in_group( $data_slug, $gid ) {
		global $wpdb;
		if ( ! Gruppa_DB::data_table_exists( $data_slug ) ) {
			return 0;
		}
		$table = Gruppa_DB::data_table( $data_slug );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE group_id = %d", (int) $gid ) ); // phpcs:ignore
	}

	/* ---------- Links of a Content type ---------- */

	/**
	 * Attachments that show up in items of $type.
	 *
	 * @return array[] key, label, data, group (id, 0 = values only), group_slug, choice, list, item
	 */
	public static function links( $type ) {
		if ( ! $type || 'content' !== $type->kind ) {
			return array();
		}
		if ( null === self::$links ) {
			self::$links = array();
			foreach ( Gruppa_Types::all() as $d ) {
				if ( 'data' !== $d->kind ) {
					continue;
				}
				$sets = array();
				if ( 'groups' === $d->structure ) {
					foreach ( $d->groups as $g ) {
						$sets[] = array( $g['id'], $g['slug'], $g['name'], $g );
					}
				} else {
					$sets[] = array( 0, self::VALUES, $d->name, $d->link );
				}
				foreach ( $sets as $s ) {
					list( $gid, $gslug, $label, $conf ) = $s;
					foreach ( $conf['targets'] as $target ) {
						self::$links[ $target ][] = array(
							'key'        => $d->slug . self::SEP . $gslug,
							'label'      => $label,
							'data'       => $d->slug,
							'data_name'  => $d->name,
							'data_status'=> $d->status,
							'group'      => (int) $gid,
							'group_slug' => $gslug,
							'choice'     => 'many' === $conf['choice'] ? 'many' : 'one',
							'ui'         => 'select' === $conf['choice'] ? 'select' : 'list',
							'list'       => (int) $conf['list'],
							'item'       => $target,
						);
					}
				}
			}
		}
		return isset( self::$links[ $type->slug ] ) ? self::$links[ $type->slug ] : array();
	}

	/** Pseudo relation field of a link. */
	public static function field( $link ) {
		$f = Gruppa_Fields::normalize( array(
			'key'      => $link['key'],
			'label'    => $link['label'],
			'type'     => 'relation',
			'related'  => $link['data'],
			'multiple' => 'many' === $link['choice'],
			'ui'       => isset( $link['ui'] ) && 'select' === $link['ui'] ? 'select' : 'list',
			'list'     => $link['list'],
		) );
		$f['link'] = $link;
		return $f;
	}

	/** Pseudo fields of all links of a Content type. */
	public static function fields( $type ) {
		return array_map( array( __CLASS__, 'field' ), self::links( $type ) );
	}

	public static function is_link( $f ) {
		return is_array( $f ) && ! empty( $f['link'] );
	}

	/** [ id => title ] of the values of a link (group), admin order. */
	public static function options( $link, $published_only = false ) {
		static $cache = array();
		$ck = $link['data'] . '|' . $link['group'] . '|' . ( $published_only ? 1 : 0 );
		if ( isset( $cache[ $ck ] ) ) {
			return $cache[ $ck ];
		}
		global $wpdb;
		$out = array();
		if ( Gruppa_DB::data_table_exists( $link['data'] ) ) {
			$table = Gruppa_DB::data_table( $link['data'] );
			$where = $link['group'] ? $wpdb->prepare( 'WHERE group_id = %d', (int) $link['group'] ) : 'WHERE 1=1';
			if ( $published_only ) {
				$where .= " AND status = 'publish'";
			}
			foreach ( (array) $wpdb->get_results( "SELECT id, title, status FROM `{$table}` {$where} ORDER BY order_index ASC, id ASC" ) as $r ) { // phpcs:ignore
				$out[ (int) $r->id ] = $r->title . ( 'publish' === $r->status || $published_only ? '' : ' (draft)' );
			}
		}
		$cache[ $ck ] = $out;
		return $out;
	}

	/* ---------- Picked values ---------- */

	/**
	 * Put the picked values of every link onto item rows as "{data}__{group}" properties
	 * (JSON list of IDs, or one ID for choice "one"), like a stored Relation field.
	 *
	 * @param object   $type Content type.
	 * @param object[] $rows Item rows (changed in place).
	 */
	public static function attach( $type, $rows ) {
		$links = self::links( $type );
		if ( ! $links || ! $rows ) {
			return $rows;
		}
		global $wpdb;
		$ids = array();
		foreach ( $rows as $r ) {
			if ( is_object( $r ) && isset( $r->id ) ) {
				$ids[] = (int) $r->id;
			}
		}
		$picked = array();
		if ( $ids ) {
			$in   = implode( ',', array_map( 'intval', $ids ) );
			$data = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT item_id, data_type, value_id FROM `' . self::table() . "` WHERE item_type = %s AND item_id IN ({$in}) ORDER BY order_index ASC, id ASC", $type->slug ) ); // phpcs:ignore
			$groups = self::value_groups( $data );
			foreach ( $data as $d ) {
				$gid = isset( $groups[ $d->data_type ][ (int) $d->value_id ] ) ? $groups[ $d->data_type ][ (int) $d->value_id ] : null;
				if ( null === $gid ) {
					continue; // Value deleted meanwhile.
				}
				$picked[ (int) $d->item_id ][ $d->data_type ][ $gid ][] = (int) $d->value_id;
			}
		}
		foreach ( $rows as $r ) {
			if ( ! is_object( $r ) || ! isset( $r->id ) ) {
				continue;
			}
			foreach ( $links as $l ) {
				$list = isset( $picked[ (int) $r->id ][ $l['data'] ] ) ? self::for_link( $picked[ (int) $r->id ][ $l['data'] ], $l ) : array();
				$r->{$l['key']} = 'one' === $l['choice']
					? ( $list ? (string) $list[0] : '' )
					: ( $list ? wp_json_encode( array_map( 'strval', $list ) ) : '' );
			}
		}
		return $rows;
	}

	/** Picked IDs of one data type → those of one link (group 0 = all). */
	private static function for_link( $by_group, $link ) {
		if ( ! $link['group'] ) {
			$all = array();
			foreach ( $by_group as $list ) {
				$all = array_merge( $all, $list );
			}
			return $all;
		}
		return isset( $by_group[ $link['group'] ] ) ? $by_group[ $link['group'] ] : array();
	}

	/** [ data slug => [ value id => group id ] ] for relation rows. */
	private static function value_groups( $rows ) {
		global $wpdb;
		$by = array();
		foreach ( $rows as $r ) {
			$by[ $r->data_type ][] = (int) $r->value_id;
		}
		$out = array();
		foreach ( $by as $slug => $vids ) {
			if ( ! Gruppa_DB::data_table_exists( $slug ) ) {
				continue;
			}
			$table = Gruppa_DB::data_table( $slug );
			$in    = implode( ',', array_map( 'intval', array_unique( $vids ) ) );
			foreach ( (array) $wpdb->get_results( "SELECT id, group_id FROM `{$table}` WHERE id IN ({$in})" ) as $v ) { // phpcs:ignore
				$out[ $slug ][ (int) $v->id ] = (int) $v->group_id;
			}
		}
		return $out;
	}

	/** Picked value IDs of one link for one item. */
	public static function get( $type, $item_id, $link ) {
		$row     = (object) array( 'id' => (int) $item_id );
		$rows    = self::attach( $type, array( $row ) );
		$f       = self::field( $link );
		return Gruppa_Fields::relation_ids( $f, isset( $rows[0]->{$link['key']} ) ? $rows[0]->{$link['key']} : '' );
	}

	/**
	 * Replace the picked values of one link.
	 *
	 * @param int[]|string[] $values IDs (or titles / slugs, see resolve()).
	 */
	public static function set( $type, $item_id, $link, $values ) {
		global $wpdb;
		$ids   = self::resolve( $link, (array) $values );
		if ( 'one' === $link['choice'] ) {
			$ids = array_slice( $ids, 0, 1 );
		}
		$old = self::get( $type, $item_id, $link );
		if ( $old ) {
			$in = implode( ',', array_map( 'intval', $old ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . self::table() . "` WHERE item_type = %s AND item_id = %d AND data_type = %s AND value_id IN ({$in})", $type->slug, (int) $item_id, $link['data'] ) ); // phpcs:ignore
		}
		$i = 0;
		foreach ( $ids as $vid ) {
			$wpdb->insert( self::table(), array(
				'item_type'   => $type->slug,
				'item_id'     => (int) $item_id,
				'data_type'   => $link['data'],
				'value_id'    => (int) $vid,
				'order_index' => $i++,
			) );
		}
	}

	/** IDs, titles or slugs → valid value IDs of the link, in the given order. */
	public static function resolve( $link, $values ) {
		$opts = self::options( $link );
		$out  = array();
		$byname = null;
		foreach ( $values as $v ) {
			if ( is_array( $v ) && isset( $v['id'] ) ) {
				$v = $v['id'];
			}
			$v = trim( (string) $v );
			if ( '' === $v ) {
				continue;
			}
			if ( ctype_digit( $v ) && isset( $opts[ (int) $v ] ) ) {
				$out[] = (int) $v;
				continue;
			}
			if ( null === $byname ) {
				$byname = self::names( $link );
			}
			$k = function_exists( 'mb_strtolower' ) ? mb_strtolower( $v ) : strtolower( $v );
			if ( isset( $byname[ $k ] ) ) {
				$out[] = $byname[ $k ];
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** lowercase title / slug → value ID of a link. */
	public static function names( $link ) {
		global $wpdb;
		$out = array();
		if ( ! Gruppa_DB::data_table_exists( $link['data'] ) ) {
			return $out;
		}
		$table = Gruppa_DB::data_table( $link['data'] );
		$where = $link['group'] ? $wpdb->prepare( 'WHERE group_id = %d', (int) $link['group'] ) : '';
		foreach ( (array) $wpdb->get_results( "SELECT id, title, slug FROM `{$table}` {$where}" ) as $r ) { // phpcs:ignore
			foreach ( array( $r->title, $r->slug ) as $n ) {
				$k = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $n ) : strtolower( (string) $n );
				if ( '' !== $k && ! isset( $out[ $k ] ) ) {
					$out[ $k ] = (int) $r->id;
				}
			}
		}
		return $out;
	}

	/** Save the boxes of the item edit screen: $posted = [ link key => [ ids ] ]. */
	public static function save_posted( $type, $item_id, $posted ) {
		$posted = is_array( $posted ) ? $posted : array();
		foreach ( self::links( $type ) as $l ) {
			if ( array_key_exists( $l['key'], $posted ) ) {
				self::set( $type, $item_id, $l, array_filter( (array) $posted[ $l['key'] ], 'strlen' ) );
			}
		}
	}

	/** Same picks for a copied item. */
	public static function copy_item( $type, $from_id, $to_id ) {
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT data_type, value_id, order_index FROM `' . self::table() . '` WHERE item_type = %s AND item_id = %d', $type->slug, (int) $from_id ) ); // phpcs:ignore
		foreach ( $rows as $r ) {
			$wpdb->insert( self::table(), array(
				'item_type'   => $type->slug,
				'item_id'     => (int) $to_id,
				'data_type'   => $r->data_type,
				'value_id'    => (int) $r->value_id,
				'order_index' => (int) $r->order_index,
			) );
		}
	}

	/** Item of any type deleted: its picks (content) and the picks of it (data value). */
	public static function forget_item( $type, $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'item_type' => $type->slug, 'item_id' => (int) $id ) );
		$wpdb->delete( self::table(), array( 'data_type' => $type->slug, 'value_id' => (int) $id ) );
	}

	/** Strip link properties added by attach() before a row is written back. */
	public static function strip( $type, $row ) {
		foreach ( self::links( $type ) as $l ) {
			unset( $row[ $l['key'] ] );
		}
		return $row;
	}

	/* ---------- Queries ---------- */

	/**
	 * SQL condition on the item table of the link's Content type.
	 *
	 * @param string $op eq | neq | empty | not_empty (others behave like eq).
	 */
	public static function condition( $f, $op, $value ) {
		global $wpdb;
		$l   = $f['link'];
		$rel = self::table();
		$opts = self::options( $l );
		$ids  = $opts ? implode( ',', array_map( 'intval', array_keys( $opts ) ) ) : '0';
		$any  = $wpdb->prepare( "`id` IN ( SELECT item_id FROM `{$rel}` WHERE item_type = %s AND data_type = %s AND value_id IN ({$ids}) )", $l['item'], $l['data'] ); // phpcs:ignore
		switch ( $op ) {
			case 'empty':
				return "NOT {$any}";
			case 'not_empty':
				return $any;
		}
		$one = $wpdb->prepare( "`id` IN ( SELECT item_id FROM `{$rel}` WHERE item_type = %s AND data_type = %s AND value_id = %d )", $l['item'], $l['data'], (int) $value ); // phpcs:ignore
		return 'neq' === $op ? "NOT {$one}" : $one;
	}

	/* ---------- Clean-up ---------- */

	/** A type is deleted: drop its picks and the picks of its values. */
	public static function forget_type( $slug ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'item_type' => $slug ) );
		$wpdb->delete( self::table(), array( 'data_type' => $slug ) );
		self::reset();
	}
}
