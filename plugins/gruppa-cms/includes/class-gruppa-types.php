<?php
defined( 'ABSPATH' ) || exit;

class Gruppa_Types {

	/** Keys skipped on last save (reserved / duplicate). */
	public static $skipped = array();

	const RESERVED = array( 'id', 'title', 'slug', 'author', 'created', 'modified', 'status', 'order_index', 'wp_post_id', 'group_id', 'group_title', 'group_slug' );

	public static function all( $status = '' ) {
		global $wpdb;
		$table = Gruppa_DB::types_table();
		$where = $status ? $wpdb->prepare( 'WHERE status = %s', $status ) : '';
		$rows  = $wpdb->get_results( "SELECT * FROM `{$table}` {$where} ORDER BY order_index ASC, id ASC" ); // phpcs:ignore
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	public static function counts() {
		global $wpdb;
		$table = Gruppa_DB::types_table();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) c FROM `{$table}` GROUP BY status" ); // phpcs:ignore
		$out   = array( 'all' => 0, 'publish' => 0, 'draft' => 0 );
		foreach ( (array) $rows as $r ) {
			$out[ $r->status ] = (int) $r->c;
			$out['all']       += (int) $r->c;
		}
		return $out;
	}

	public static function get( $id ) {
		global $wpdb;
		$table = Gruppa_DB::types_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) ); // phpcs:ignore
		return $row ? self::hydrate( $row ) : null;
	}

	public static function get_by_slug( $slug ) {
		global $wpdb;
		$table = Gruppa_DB::types_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE slug = %s", $slug ) ); // phpcs:ignore
		return $row ? self::hydrate( $row ) : null;
	}

	private static function hydrate( $row ) {
		$fields = $row->fields ? json_decode( $row->fields, true ) : array();
		$row->fields = array_map( array( 'Gruppa_Fields', 'normalize' ), is_array( $fields ) ? $fields : array() );
		$settings      = ! empty( $row->settings ) ? json_decode( $row->settings, true ) : array();
		$row->settings = is_array( $settings ) ? $settings : array();
		$row->kind     = isset( $row->settings['kind'] ) && array_key_exists( $row->settings['kind'], self::kinds() )
			? $row->settings['kind']
			: ( $row->has_single ? 'content' : 'data' );
		$row->custom_pt = ! empty( $row->settings['custom_pt'] ) && $row->has_single && $row->post_type;
		// Data types (0.22): values only, or groups of values; attached to Content types.
		$row->structure = 'data' === $row->kind && isset( $row->settings['structure'] ) && 'groups' === $row->settings['structure'] ? 'groups' : 'values';
		$row->groups    = 'data' === $row->kind ? Gruppa_Relations::groups_of( $row->settings ) : array();
		$row->link      = Gruppa_Relations::link_of( 'data' === $row->kind ? $row->settings : array() );
		return $row;
	}

	/**
	 * Content = items with a single page (template).
	 * Data = values without a page, picked in items of Content types (0.22 replaces "Relation").
	 */
	public static function kinds() {
		return array(
			'content' => 'Content',
			'data'    => 'Data',
		);
	}

	/** Fields for reading: own fields + data links (pseudo Relation fields) of a Content type. */
	public static function fields_all( $type ) {
		return $type ? array_merge( $type->fields, Gruppa_Relations::fields( $type ) ) : array();
	}

	/** Field definition by key, data links included. */
	public static function field( $type, $key ) {
		foreach ( self::fields_all( $type ) as $f ) {
			if ( $f['key'] === $key ) {
				return $f;
			}
		}
		return null;
	}

	/** Post type select value for "Gruppa registers its own post type". */
	const CUSTOM = '__custom';

	/** WP post type name for a Custom type: slug (max 20 chars), prefixed if taken. */
	public static function custom_post_type_name( $slug ) {
		$slug  = self::sanitize_key( $slug );
		$taken = function ( $n ) {
			return post_type_exists( $n ) || in_array( $n, array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'action', 'author', 'order', 'theme', 'type', 'types' ), true );
		};
		foreach ( array( substr( $slug, 0, 20 ), substr( 'g_' . $slug, 0, 20 ) ) as $n ) {
			if ( '' !== $n && ! $taken( $n ) ) {
				return $n;
			}
		}
		return '';
	}

	/** Register Custom post types (init). Hidden from the admin menu – items are managed in Gruppa CMS only. */
	public static function register_post_types() {
		foreach ( self::all() as $t ) {
			if ( $t->custom_pt ) {
				self::register_one( $t );
			}
		}
	}

	/** After every plugin registered its rules (wp_loaded). */
	public static function maybe_flush_rewrite() {
		if ( get_option( 'gruppa_cms_flush_rewrite' ) ) {
			delete_option( 'gruppa_cms_flush_rewrite' );
			flush_rewrite_rules( false );
		}
	}

	/** Post type names registered by Gruppa. */
	private static $registered = array();

	public static function register_one( $t ) {
		if ( post_type_exists( $t->post_type ) && ! isset( self::$registered[ $t->post_type ] ) ) {
			return;
		}
		$pt = register_post_type( $t->post_type, array(
			'label'               => $t->name,
			'labels'              => array( 'name' => $t->name, 'singular_name' => $t->name ),
			'public'              => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => true, // Elementor Theme Builder conditions list these.
			'show_in_rest'        => false,
			'has_archive'         => false,
			'hierarchical'        => false,
			'rewrite'             => array( 'slug' => str_replace( '_', '-', $t->slug ), 'with_front' => false ),
			'supports'            => array( 'title', 'elementor' ),
		) );
		if ( ! is_wp_error( $pt ) ) {
			self::$registered[ $t->post_type ] = true;
		}
	}

	/** Post types a Content item may sync to. */
	public static function single_post_types() {
		$names = array( 'post', 'page' );
		$extra = apply_filters( 'gruppa_cms/single_post_types', array() );
		return array_unique( array_merge( $names, array_keys( (array) $extra ) ) );
	}

	/** May a saved field change from $from to $to? Same column storage; Repeater, HTML and Relation stay what they are. */
	public static function type_change_ok( $from, $to ) {
		if ( $from === $to ) {
			return true;
		}
		$fixed = array( 'repeater', 'html', 'relation' );
		if ( in_array( $from, $fixed, true ) || in_array( $to, $fixed, true ) ) {
			return false;
		}
		return Gruppa_DB::column_sql( $from ) === Gruppa_DB::column_sql( $to );
	}

	public static function sanitize_key( $key ) {
		$key = strtolower( remove_accents( (string) $key ) );
		$key = preg_replace( '/[^a-z0-9_]+/', '_', $key );
		$key = trim( $key, '_' );
		if ( '' !== $key && ctype_digit( $key[0] ) ) {
			$key = 'f_' . $key;
		}
		return substr( $key, 0, 40 );
	}

	/**
	 * @return array [ fields, renames(orig=>new) ]
	 */
	public static function sanitize_fields( $raw, $old_fields = array() ) {
		$old = array();
		foreach ( $old_fields as $f ) {
			$old[ $f['key'] ] = $f;
		}

		$out           = array();
		$renames       = array();
		self::$skipped = array();
		$seen    = array();

		foreach ( (array) $raw as $r ) {
			if ( empty( $r['key'] ) && ! empty( $r['label'] ) ) {
				$r['key'] = $r['label'];
			}
			$f = Gruppa_Fields::sanitize_settings( $r );
			if ( '' === $f['key'] ) {
				continue;
			}
			$orig = isset( $r['orig_key'] ) ? self::sanitize_key( $r['orig_key'] ) : '';
			// Reserved keys: refused for new fields; a field saved before a key became reserved (group_* in 0.22) stays.
			$kept = $orig && isset( $old[ $orig ] ) && $orig === $f['key'];
			if ( ( ! $kept && in_array( $f['key'], self::RESERVED, true ) ) || isset( $seen[ $f['key'] ] ) ) {
				self::$skipped[] = $f['key'];
				continue;
			}
			if ( '' === $f['label'] ) {
				$f['label'] = $f['key'];
			}

			if ( $orig && isset( $old[ $orig ] ) ) {
				// Saved field: type may change only within the same column storage (Repeater / HTML never).
				if ( ! self::type_change_ok( $old[ $orig ]['type'], $f['type'] ) ) {
					$f = array_merge( Gruppa_Fields::sanitize_settings( array_merge( $r, array( 'type' => $old[ $orig ]['type'] ) ) ), array( 'key' => $f['key'], 'label' => $f['label'] ) );
				}
				if ( $orig !== $f['key'] ) {
					$renames[ $orig ] = $f['key'];
				}
			}

			$seen[ $f['key'] ] = true;
			$out[]             = $f;
		}

		return array( $out, $renames );
	}

	/**
	 * @return int|WP_Error
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$table = Gruppa_DB::types_table();
		$now   = current_time( 'mysql' );

		$name = sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' );
		if ( '' === $name ) {
			return new WP_Error( 'name', 'Name' );
		}

		$type   = $id ? self::get( $id ) : null;
		if ( $id && ! $type ) {
			return new WP_Error( 'missing', 'Not found' );
		}

		// Slug of an entity without items can change (e.g. a copy "pages_copy" → "products"), 0.29.2.
		if ( $type && isset( $data['slug'] ) && self::sanitize_key( $data['slug'] ) !== $type->slug ) {
			$res = self::rename_slug( $type, $data['slug'] );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$type = self::get( $id );
		}

		list( $fields, $renames ) = self::sanitize_fields(
			isset( $data['fields'] ) ? $data['fields'] : array(),
			$type ? $type->fields : array()
		);

		$status     = ( isset( $data['status'] ) && 'draft' === $data['status'] ) ? 'draft' : 'publish';
		$kind = isset( $data['kind'] ) ? sanitize_key( $data['kind'] ) : '';
		if ( ! $type && '' === $kind && ! isset( $data['has_single'] ) ) {
			return new WP_Error( 'kind', 'Type' ); // New type: Content or Data must be chosen.
		}
		if ( ! array_key_exists( $kind, self::kinds() ) ) {
			$kind = ! empty( $data['has_single'] ) ? 'content' : 'data';
		}
		$has_single = 'content' === $kind ? 1 : 0;

		// Data type: structure, attachment, groups.
		$self_slug = $type ? $type->slug : self::sanitize_key( ! empty( $data['slug'] ) ? $data['slug'] : $name );
		$renamed   = array();
		$data_part = null;
		if ( 'data' === $kind ) {
			list( $data_part, $renamed, $removed ) = Gruppa_Relations::sanitize_settings( $data, $type, $self_slug );
			foreach ( $removed as $gid ) {
				if ( Gruppa_Relations::count_in_group( $self_slug, $gid ) > 0 ) {
					return new WP_Error( 'group_has_values', 'Group has values' );
				}
			}
			if ( $type && 'groups' === $type->structure && 'groups' !== $data_part['structure'] && Gruppa_DB::data_table_exists( $self_slug ) ) {
				global $wpdb;
				if ( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . Gruppa_DB::data_table( $self_slug ) . '` WHERE group_id > 0' ) ) { // phpcs:ignore
					return new WP_Error( 'group_has_values', 'Group has values' );
				}
			}
			if ( 'groups' === $data_part['structure'] && ! $data_part['groups'] ) {
				return new WP_Error( 'groups_empty', 'Groups' );
			}
		}
		$pt_raw    = isset( $data['post_type'] ) ? (string) $data['post_type'] : '';
		$custom_pt = $has_single && self::CUSTOM === $pt_raw;
		$post_type = '';
		if ( $custom_pt ) {
			$post_type = ( $type && $type->custom_pt )
				? $type->post_type
				: self::custom_post_type_name( $type ? $type->slug : self::sanitize_key( ! empty( $data['slug'] ) ? $data['slug'] : $name ) );
			if ( '' === $post_type ) {
				return new WP_Error( 'post_type', 'Post type' );
			}
		} elseif ( $has_single ) {
			$post_type = sanitize_key( $pt_raw );
			if ( ! post_type_exists( $post_type ) || ! in_array( $post_type, self::single_post_types(), true ) ) {
				return new WP_Error( 'post_type', 'Post type' );
			}
		}
		$order = isset( $data['order_index'] ) && '' !== $data['order_index'] ? max( 0, (int) $data['order_index'] ) : null;

		$row = array(
			'name'       => $name,
			'modified'   => $now,
			'status'     => $status,
			'has_single' => $has_single,
			'post_type'  => $post_type,
			'fields'     => wp_json_encode( $fields ),
		);
		$settings           = $type ? $type->settings : array();
		$settings['access'] = Gruppa_Access::sanitize( isset( $data['access'] ) ? $data['access'] : array() );
		$settings['kind']   = $kind;
		$settings['custom_pt'] = $custom_pt ? 1 : 0;
		$icon                  = isset( $data['icon'] ) ? sanitize_key( $data['icon'] ) : '';
		if ( $icon && in_array( $icon, Gruppa_Menu::icons(), true ) && Gruppa_Menu::default_icon( $kind ) !== $icon ) {
			$settings['icon'] = $icon;
		} else {
			unset( $settings['icon'] ); // Default icon of the kind.
		}
		foreach ( array( 'structure', 'link', 'groups', 'group_seq' ) as $k ) {
			unset( $settings[ $k ] );
		}
		if ( $data_part ) {
			$settings = array_merge( $settings, $data_part );
		}
		$row['settings']    = wp_json_encode( $settings );
		if ( null !== $order ) {
			$row['order_index'] = $order;
		}

		if ( $type ) {
			$slug = $type->slug;

			$types_by_key = wp_list_pluck( $fields, 'type', 'key' );
			foreach ( $renames as $from => $to ) {
				Gruppa_DB::rename_column( $slug, $from, $to, $types_by_key[ $to ] );
			}

			$wpdb->update( $table, $row, array( 'id' => $id ) );
		} else {
			$slug = self::sanitize_key( ! empty( $data['slug'] ) ? $data['slug'] : $name );
			if ( '' === $slug ) {
				return new WP_Error( 'slug', 'Slug' );
			}
			if ( Gruppa_DB::is_reserved( $slug ) ) {
				return new WP_Error( 'slug_reserved', 'Slug reserved' );
			}
			if ( self::get_by_slug( $slug ) || Gruppa_DB::data_table_exists( $slug ) ) {
				return new WP_Error( 'slug_exists', 'Slug exists' );
			}

			$wpdb->insert(
				$table,
				array_merge(
					$row,
					array(
						'slug'        => $slug,
						'author'      => get_current_user_id(),
						'created'     => $now,
					),
					null === $order ? array( 'order_index' => Gruppa_DB::next_order( 'types' ) ) : array()
				)
			);
			$id = (int) $wpdb->insert_id;
			if ( ! $id ) {
				return new WP_Error( 'db', $wpdb->last_error );
			}
		}

		Gruppa_DB::create_data_table( $slug, $fields, $kind );
		Gruppa_Relations::reset();

		$saved = self::get( $id );
		if ( $saved && $renamed ) {
			Gruppa_Relations::sync_group_titles( $saved, $renamed );
		}
		if ( $saved && $saved->custom_pt ) {
			self::register_one( $saved );
		}
		$old_pt = $type ? ( $type->has_single ? $type->post_type : '' ) : '';
		$new_pt = $saved && $saved->has_single ? $saved->post_type : '';
		if ( $type && $new_pt && $old_pt !== $new_pt ) {
			// Move existing items' WP posts to the new post type.
			foreach ( (array) $wpdb->get_col( 'SELECT id FROM `' . Gruppa_DB::data_table( $slug ) . '`' ) as $item_id ) { // phpcs:ignore
				Gruppa_Items::sync_post( $saved, (int) $item_id );
			}
		}
		if ( ( $type && $type->custom_pt ) || ( $saved && $saved->custom_pt ) ) {
			update_option( 'gruppa_cms_flush_rewrite', 1 );
		}

		return (int) $id;
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		$wpdb->update(
			Gruppa_DB::types_table(),
			array(
				'status'   => 'draft' === $status ? 'draft' : 'publish',
				'modified' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * Change the slug of an entity that has no items yet (0.29.2): renames its table
	 * wp_gcms_type_{slug}, its own post type (CPT) and every reference — forms, listings,
	 * filter groups, Table Builder tables, data links and menu parents of other entities,
	 * Relation fields, relations and SEO records.
	 *
	 * @return true|WP_Error
	 */
	public static function rename_slug( $type, $new ) {
		global $wpdb;
		$old = $type->slug;
		$new = self::sanitize_key( $new );
		if ( $new === $old ) {
			return true;
		}
		if ( Gruppa_DB::count_items( $old ) > 0 ) {
			return new WP_Error( 'slug_locked', 'Slug locked' );
		}
		if ( '' === $new || strlen( $new ) > 40 ) {
			return new WP_Error( 'slug', 'Slug' );
		}
		if ( Gruppa_DB::is_reserved( $new ) ) {
			return new WP_Error( 'slug_reserved', 'Slug reserved' );
		}
		if ( self::get_by_slug( $new ) || Gruppa_DB::data_table_exists( $new ) ) {
			return new WP_Error( 'slug_exists', 'Slug exists' );
		}

		// Item table: it is empty, so a new one is created instead of renaming (works on every DB engine).
		Gruppa_DB::create_data_table( $new, $type->fields, $type->kind );
		if ( ! Gruppa_DB::data_table_exists( $new ) ) {
			return new WP_Error( 'db', 'Table' );
		}
		Gruppa_DB::drop_data_table( $old );

		// The entity itself (+ its own post type, which has no posts yet).
		$row = array( 'slug' => $new );
		if ( $type->custom_pt ) {
			$pt = self::custom_post_type_name( $new );
			if ( '' !== $pt ) {
				$row['post_type'] = $pt;
			}
			update_option( 'gruppa_cms_flush_rewrite', 1 );
		}
		$wpdb->update( Gruppa_DB::types_table(), $row, array( 'id' => (int) $type->id ) );

		// Builders that point to the entity.
		foreach ( array( Gruppa_DB::forms_table(), Gruppa_DB::listings_table(), Gruppa_DB::filters_table() ) as $table ) {
			$wpdb->update( $table, array( 'type_slug' => $new ), array( 'type_slug' => $old ) );
		}
		if ( class_exists( 'Gruppa_TB_DB' ) ) {
			foreach ( Gruppa_TB_DB::all() as $t ) {
				$origin = isset( $t->settings['origin'] ) ? $t->settings['origin'] : 'gruppa';
				if ( 'gruppa' === $origin && $old === $t->type_slug ) {
					$wpdb->update( Gruppa_TB_DB::table(), array( 'type_slug' => $new ), array( 'id' => (int) $t->id ) );
				}
			}
		}
		$wpdb->update( Gruppa_Relations::table(), array( 'item_type' => $new ), array( 'item_type' => $old ) );
		$wpdb->update( Gruppa_Relations::table(), array( 'data_type' => $new ), array( 'data_type' => $old ) );
		$wpdb->update( Gruppa_DB::seo_table(), array( 'owner_type' => $new ), array( 'owner_type' => $old ) );

		// Other entities: data link targets, menu parents, Relation fields.
		$swap = function ( $list ) use ( $old, $new ) {
			return array_values( array_map( function ( $v ) use ( $old, $new ) {
				return $v === $old ? $new : $v;
			}, (array) $list ) );
		};
		foreach ( self::all() as $o ) {
			if ( (int) $o->id === (int) $type->id ) {
				continue;
			}
			$set     = is_array( $o->settings ) ? $o->settings : array();
			$fields  = $o->fields;
			$changed = false;
			$fchange = false;
			if ( isset( $set['link']['targets'] ) && in_array( $old, (array) $set['link']['targets'], true ) ) {
				$set['link']['targets'] = $swap( $set['link']['targets'] );
				$changed                = true;
			}
			if ( ! empty( $set['groups'] ) ) {
				foreach ( $set['groups'] as $i => $g ) {
					if ( isset( $g['targets'] ) && in_array( $old, (array) $g['targets'], true ) ) {
						$set['groups'][ $i ]['targets'] = $swap( $g['targets'] );
						$changed                        = true;
					}
				}
			}
			if ( isset( $set['menu']['parents'] ) && in_array( $old, (array) $set['menu']['parents'], true ) ) {
				$set['menu']['parents'] = $swap( $set['menu']['parents'] );
				$changed                = true;
			}
			foreach ( $fields as $i => $f ) {
				if ( isset( $f['related'] ) && $old === $f['related'] ) {
					$fields[ $i ]['related'] = $new;
					$fchange                 = true;
				}
			}
			$upd = array();
			if ( $changed ) {
				$upd['settings'] = wp_json_encode( $set );
			}
			if ( $fchange ) {
				$upd['fields'] = wp_json_encode( $fields );
			}
			if ( $upd ) {
				$wpdb->update( Gruppa_DB::types_table(), $upd, array( 'id' => (int) $o->id ) );
			}
		}

		Gruppa_Relations::reset();
		if ( class_exists( 'Gruppa_Menu' ) ) {
			Gruppa_Menu::reset();
		}
		/**
		 * After an entity got a new slug.
		 *
		 * @param string $old Old slug.
		 * @param string $new New slug.
		 */
		do_action( 'gruppa_cms/type_slug_changed', $old, $new );
		return true;
	}

	/**
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;
		$type = self::get( $id );
		if ( ! $type ) {
			return new WP_Error( 'missing', 'Not found' );
		}
		if ( Gruppa_DB::count_items( $type->slug ) > 0 ) {
			return new WP_Error( 'has_items', 'Has items' );
		}
		Gruppa_DB::drop_data_table( $type->slug );
		Gruppa_Relations::forget_type( $type->slug );
		if ( $type->custom_pt ) {
			update_option( 'gruppa_cms_flush_rewrite', 1 );
		}
		$wpdb->delete( Gruppa_DB::types_table(), array( 'id' => (int) $id ) );
		return true;
	}
}
