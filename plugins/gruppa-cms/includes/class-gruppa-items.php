<?php
defined( 'ABSPATH' ) || exit;

class Gruppa_Items {

	private static $syncing = false;

	public static function init() {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'enforce_post_data' ), 99, 2 );
	}

	/**
	 * CCT is the source of truth: title + slug of a linked WP post
	 * always come from its CCT item, whoever saves the post.
	 */
	public static function enforce_post_data( $data, $postarr ) {
		if ( self::$syncing || empty( $postarr['ID'] ) ) {
			return $data;
		}
		$slug = get_post_meta( (int) $postarr['ID'], '_gruppa_type', true );
		$iid  = (int) get_post_meta( (int) $postarr['ID'], '_gruppa_item', true );
		if ( ! $slug || ! $iid ) {
			return $data;
		}
		$type = Gruppa_Types::get_by_slug( $slug );
		$item = $type ? self::get( $type, $iid ) : null;
		if ( $item ) {
			$data['post_title'] = $item->title;
			$data['post_name']  = $item->slug;
		}
		return $data;
	}

	public static function query( $type, $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args( $args, array( 'per_page' => 50, 'page' => 1, 'status' => '', 's' => '', 'where' => array() ) );
		$table = Gruppa_DB::data_table( $type->slug );

		$where = array( '1=1' );
		if ( $args['status'] ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}
		if ( '' !== $args['s'] ) {
			$like    = '%' . $wpdb->esc_like( $args['s'] ) . '%';
			$where[] = $wpdb->prepare( '(title LIKE %s OR slug LIKE %s)', $like, $like );
		}
		foreach ( (array) $args['where'] as $w ) {
			$where[] = $w; // Already prepared SQL (e.g. Gruppa_Listing::condition()).
		}
		$where = implode( ' AND ', $where );

		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE {$where}" ); // phpcs:ignore
		$offset = max( 0, ( (int) $args['page'] - 1 ) * (int) $args['per_page'] );
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE {$where} ORDER BY order_index ASC, id ASC LIMIT %d OFFSET %d", $args['per_page'], $offset ) ); // phpcs:ignore

		return array( Gruppa_Relations::attach( $type, (array) $rows ), $total, $offset );
	}

	/**
	 * Item as a typed array (REST / JSON export).
	 */
	public static function format( $type, $r ) {
		$r   = (array) $r;
		$out = array(
			'id'          => (int) $r['id'],
			'title'       => $r['title'],
			'slug'        => $r['slug'],
			'status'      => $r['status'],
			'order_index' => (int) $r['order_index'],
		);
		foreach ( $type->fields as $f ) {
			if ( Gruppa_Fields::is_password( $f ) || Gruppa_Fields::is_virtual( $f ) ) {
				continue; // Never expose password hashes; HTML has no value.
			}
			$v = isset( $r[ $f['key'] ] ) ? $r[ $f['key'] ] : null;
			if ( 'relation' === $f['type'] ) {
				$ids = Gruppa_Fields::relation_ids( $f, $v );
				$v   = Gruppa_Fields::is_multi( $f ) ? $ids : ( $ids ? $ids[0] : null );
			} elseif ( 'repeater' === $f['type'] ) {
				$v = self::format_rows( $f, $v );
			} elseif ( 'time' === $f['type'] ) {
				$v = '' === Gruppa_Fields::decode( $f, $v ) ? null : Gruppa_Fields::decode( $f, $v );
			} elseif ( Gruppa_Fields::is_multi( $f ) ) {
				$v = Gruppa_Fields::decode( $f, $v );
				if ( 'media' === $f['type'] && 'id' === $f['return'] ) {
					$v = array_map( 'intval', $v );
				}
			} elseif ( 'number' === $f['type'] ) {
				$v = null === $v ? null : $v + 0;
			} elseif ( 'switcher' === $f['type'] ) {
				$v = (bool) $v;
			} elseif ( 'media' === $f['type'] && 'id' === $f['return'] ) {
				$v = $v ? (int) $v : null;
			} else {
				$v = Gruppa_Fields::decode( $f, $v );
			}
			$out[ $f['key'] ] = $v;
		}
		if ( 'data' === $type->kind && 'groups' === $type->structure ) {
			$out['group_id']    = isset( $r['group_id'] ) ? (int) $r['group_id'] : 0;
			$out['group_title'] = isset( $r['group_title'] ) ? $r['group_title'] : '';
			$out['group_slug']  = isset( $r['group_slug'] ) ? $r['group_slug'] : '';
		}
		$links = Gruppa_Relations::links( $type );
		if ( $links ) {
			$out['relations'] = array();
			foreach ( $links as $l ) {
				$f    = Gruppa_Relations::field( $l );
				$ids  = Gruppa_Fields::relation_ids( $f, isset( $r[ $l['key'] ] ) ? $r[ $l['key'] ] : '' );
				$opts = Gruppa_Relations::options( $l );
				$list = array();
				foreach ( $ids as $vid ) {
					if ( isset( $opts[ $vid ] ) ) {
						$list[] = array( 'id' => $vid, 'title' => preg_replace( '/ \(draft\)$/', '', $opts[ $vid ] ) );
					}
				}
				$out['relations'][ $l['key'] ] = $list;
			}
		}
		$out['author']     = (int) $r['author'];
		$out['created']    = $r['created'];
		$out['modified']   = $r['modified'];
		$out['wp_post_id'] = $r['wp_post_id'] ? (int) $r['wp_post_id'] : null;
		if ( $out['wp_post_id'] && 'publish' === $r['status'] ) {
			$out['permalink'] = get_permalink( $out['wp_post_id'] );
		}
		return $out;
	}

	/**
	 * Build a DB row from API-style data (keys = system keys + field keys).
	 * Create: missing fields get their default, required is enforced.
	 * Update: only keys present in $data are touched.
	 *
	 * @return array [ row, errors (field key => message) ]
	 */
	public static function from_data( $type, $data, $create ) {
		$row    = array();
		$errors = array();
		$data   = (array) $data;

		if ( array_key_exists( 'title', $data ) ) {
			$row['title'] = sanitize_text_field( (string) $data['title'] );
		}
		if ( $create && empty( $row['title'] ) ) {
			$errors['title'] = __( 'Title: required', 'gruppa-cms' );
		} elseif ( isset( $row['title'] ) && '' === $row['title'] ) {
			$errors['title'] = 'Title: cannot be empty';
		}
		if ( ! empty( $data['slug'] ) ) {
			$row['slug'] = sanitize_title( (string) $data['slug'] );
		}
		if ( array_key_exists( 'status', $data ) ) {
			$row['status'] = 'draft' === $data['status'] ? 'draft' : 'publish';
		} elseif ( $create ) {
			$row['status'] = 'publish';
		}
		if ( isset( $data['order_index'] ) && '' !== $data['order_index'] ) {
			$row['order_index'] = max( 0, (int) $data['order_index'] );
		}
		if ( 'data' === $type->kind && 'groups' === $type->structure ) {
			$g = null;
			if ( isset( $data['group_id'] ) && '' !== (string) $data['group_id'] ) {
				$g = Gruppa_Relations::group( $type, (int) $data['group_id'] );
			} elseif ( isset( $data['group'] ) && '' !== (string) $data['group'] ) {
				$g = ctype_digit( (string) $data['group'] ) ? Gruppa_Relations::group( $type, (int) $data['group'] ) : Gruppa_Relations::group_by_slug( $type, sanitize_key( $data['group'] ) );
				if ( ! $g ) {
					foreach ( $type->groups as $cand ) {
						if ( 0 === strcasecmp( $cand['name'], (string) $data['group'] ) ) {
							$g = $cand;
						}
					}
				}
			}
			if ( $g ) {
				$row = array_merge( $row, Gruppa_Relations::group_columns( $type, $g['id'] ) );
			} elseif ( $create || isset( $data['group'] ) || isset( $data['group_id'] ) ) {
				$errors['group'] = 'Group: required';
			}
		}

		foreach ( $type->fields as $f ) {
			if ( Gruppa_Fields::is_virtual( $f ) ) {
				continue;
			}
			if ( array_key_exists( $f['key'], $data ) ) {
				$raw = $data[ $f['key'] ];
				if ( Gruppa_Fields::keep_password( $f, $raw, ! $create ) ) {
					continue;
				}
				if ( 'switcher' === $f['type'] && is_bool( $raw ) ) {
					$raw = $raw ? 1 : 0;
				}
			} elseif ( $create ) {
				$raw = Gruppa_Fields::default_value( $f );
			} else {
				continue;
			}
			list( $val, $err ) = Gruppa_Fields::sanitize_value( $f, Gruppa_Transfer::coerce( $f, $raw ) );
			if ( $err ) {
				$errors[ $f['key'] ] = $err;
			}
			$row[ $f['key'] ] = $val;
		}
		return array( $row, $errors );
	}

	/** Repeater rows for REST / JSON: typed values (number, switcher, media ID). */
	public static function format_rows( $f, $raw ) {
		$out = array();
		foreach ( Gruppa_Fields::decode( $f, $raw ) as $row ) {
			$r = array();
			foreach ( $f['sub'] as $sd ) {
				$v = isset( $row[ $sd['key'] ] ) ? $row[ $sd['key'] ] : null;
				if ( 'number' === $sd['type'] ) {
					$v = null === $v || '' === $v ? null : $v + 0;
				} elseif ( 'switcher' === $sd['type'] ) {
					$v = (bool) $v;
				} elseif ( 'media' === $sd['type'] ) {
					$v = $v ? (int) $v : null;
				}
				$r[ $sd['key'] ] = $v;
			}
			$out[] = $r;
		}
		return $out;
	}

	public static function next_order_preview( $type ) {
		return Gruppa_DB::next_order( $type->slug );
	}

	public static function counts( $type ) {
		global $wpdb;
		$table = Gruppa_DB::data_table( $type->slug );
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) c FROM `{$table}` GROUP BY status" ); // phpcs:ignore
		$out   = array( 'all' => 0, 'publish' => 0, 'draft' => 0 );
		foreach ( (array) $rows as $r ) {
			$out[ $r->status ] = (int) $r->c;
			$out['all']       += (int) $r->c;
		}
		return $out;
	}

	/** Item row; for Content types with data links the picked values are attached (see Gruppa_Relations::attach). */
	public static function get( $type, $id ) {
		global $wpdb;
		$table = Gruppa_DB::data_table( $type->slug );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) ); // phpcs:ignore
		if ( $row ) {
			Gruppa_Relations::attach( $type, array( $row ) );
		}
		return $row;
	}

	private static function unique_slug( $type, $slug, $id ) {
		global $wpdb;
		$table = Gruppa_DB::data_table( $type->slug );
		$base  = $slug;
		$n     = 2;
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE slug = %s AND id <> %d LIMIT 1", $slug, $id ) ) ) { // phpcs:ignore
			$slug = $base . '-' . $n++;
		}
		return $slug;
	}

	/**
	 * Validate posted data.
	 *
	 * @return array [ row, errors ]
	 */
	public static function prepare( $type, $post ) {
		$errors = array();
		$row    = array();

		$row['title'] = sanitize_text_field( isset( $post['title'] ) ? $post['title'] : '' );
		if ( '' === $row['title'] ) {
			$errors[] = __( 'Title: required', 'gruppa-cms' );
		}
		$row['slug']   = sanitize_title( ! empty( $post['slug'] ) ? $post['slug'] : $row['title'] );
		$row['status'] = isset( $post['status'] ) && 'draft' === $post['status'] ? 'draft' : 'publish';
		if ( isset( $post['order_index'] ) && '' !== $post['order_index'] ) {
			$row['order_index'] = max( 0, (int) $post['order_index'] );
		}
		if ( 'data' === $type->kind && 'groups' === $type->structure ) {
			$gid = isset( $post['group_id'] ) ? (int) $post['group_id'] : 0;
			if ( Gruppa_Relations::group( $type, $gid ) ) {
				$row = array_merge( $row, Gruppa_Relations::group_columns( $type, $gid ) );
			} else {
				$errors[] = 'Group: required';
			}
		}

		$values = isset( $post['gf'] ) && is_array( $post['gf'] ) ? $post['gf'] : array();
		foreach ( $type->fields as $f ) {
			if ( Gruppa_Fields::is_virtual( $f ) ) {
				continue;
			}
			$raw = isset( $values[ $f['key'] ] ) ? $values[ $f['key'] ] : '';
			if ( Gruppa_Fields::keep_password( $f, $raw, ! empty( $post['id'] ) ) ) {
				continue;
			}
			list( $val, $err ) = Gruppa_Fields::sanitize_value( $f, $raw );
			$row[ $f['key'] ] = $val;
			if ( $err ) {
				$errors[] = $err;
			}
		}
		return array( $row, $errors );
	}

	/**
	 * @return int|WP_Error
	 */
	public static function save( $type, $row, $id = 0 ) {
		global $wpdb;
		$table  = Gruppa_DB::data_table( $type->slug );
		$now    = current_time( 'mysql' );
		$is_new = ! $id;

		if ( $id && ! isset( $row['slug'] ) ) {
			// Partial update (e.g. import): keep the current slug.
		} else {
			$base = ! empty( $row['slug'] ) ? $row['slug'] : sanitize_title( isset( $row['title'] ) ? $row['title'] : '' );
			$row['slug'] = self::unique_slug( $type, $base ? $base : 'item', $id );
		}
		$row['modified'] = $now;
		$row             = Gruppa_Relations::strip( $type, $row );

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ) );
		} else {
			$row['author']      = get_current_user_id();
			$row['created']     = $now;
			if ( ! isset( $row['order_index'] ) ) {
				$row['order_index'] = Gruppa_DB::next_order( $type->slug );
			}
			if ( false === $wpdb->insert( $table, $row ) ) {
				return new WP_Error( 'db', $wpdb->last_error );
			}
			$id = (int) $wpdb->insert_id;
		}

		if ( $type->has_single && $type->post_type ) {
			self::sync_post( $type, $id );
		}

		/**
		 * After an item was saved (admin, Form Builder, Data Tools import, REST, PHP).
		 *
		 * @param object $type   Entity.
		 * @param int    $id     Item ID.
		 * @param bool   $is_new True for a new item.
		 */
		do_action( 'gruppa_cms/item_saved', $type, (int) $id, $is_new );
		if ( $is_new ) {
			/** A new item was created (e.g. Gruppa Notify sends a push). */
			do_action( 'gruppa_cms/item_created', $type, (int) $id );
		}
		return $id;
	}

	public static function sync_post( $type, $id ) {
		global $wpdb;
		$item = self::get( $type, $id );
		if ( ! $item ) {
			return;
		}

		$postarr = array(
			'post_type'   => $type->post_type,
			'post_title'  => $item->title,
			'post_name'   => $item->slug,
			'post_status' => 'publish' === $item->status ? 'publish' : 'draft',
			'post_author' => (int) $item->author,
		);

		self::$syncing = true;
		$pid           = (int) $item->wp_post_id;
		if ( $pid && get_post( $pid ) ) {
			$postarr['ID'] = $pid;
			wp_update_post( $postarr );
		} else {
			$pid = wp_insert_post( $postarr );
		}
		self::$syncing = false;
		if ( ! $pid || is_wp_error( $pid ) ) {
			return;
		}

		update_post_meta( $pid, '_gruppa_type', $type->slug );
		update_post_meta( $pid, '_gruppa_item', (int) $id );

		// WP may make the slug unique (produkt-2); keep the item in sync.
		$update    = array( 'wp_post_id' => $pid );
		$post_name = get_post_field( 'post_name', $pid );
		if ( $post_name && $post_name !== $item->slug ) {
			$update['slug'] = $post_name;
		}
		$wpdb->update( Gruppa_DB::data_table( $type->slug ), $update, array( 'id' => (int) $id ) );
	}

	public static function set_status( $type, $id, $status ) {
		global $wpdb;
		$wpdb->update(
			Gruppa_DB::data_table( $type->slug ),
			array(
				'status'   => 'draft' === $status ? 'draft' : 'publish',
				'modified' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id )
		);
		if ( $type->has_single && $type->post_type ) {
			self::sync_post( $type, $id );
		}
	}

	/**
	 * Copy of an item as a Draft: same values, title "… (copy)", new slug, own single post.
	 *
	 * @return int|WP_Error new ID
	 */
	public static function duplicate( $type, $id ) {
		$item = self::get( $type, $id );
		if ( ! $item ) {
			return new WP_Error( 'missing', 'Not found' );
		}
		$row = (array) $item;
		foreach ( array( 'id', 'wp_post_id', 'created', 'modified', 'author', 'slug', 'order_index' ) as $k ) {
			unset( $row[ $k ] );
		}
		$row['title']  = $item->title . ' (copy)';
		$row['status'] = 'draft';
		$new           = self::save( $type, $row );
		if ( ! is_wp_error( $new ) ) {
			Gruppa_Relations::copy_item( $type, $id, $new );
		}
		return $new;
	}

	public static function delete( $type, $id ) {
		global $wpdb;
		$item = self::get( $type, $id );
		if ( ! $item ) {
			return;
		}
		if ( $item->wp_post_id && get_post( (int) $item->wp_post_id ) ) {
			wp_delete_post( (int) $item->wp_post_id, true );
		}
		$wpdb->delete( Gruppa_DB::data_table( $type->slug ), array( 'id' => (int) $id ) );
		Gruppa_Relations::forget_item( $type, (int) $id );
		do_action( 'gruppa_cms/item_deleted', $type, (int) $id );
	}
}
