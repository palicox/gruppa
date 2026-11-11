<?php
defined( 'ABSPATH' ) || exit;

/**
 * Owners of SEO records (who an SEO record belongs to).
 *
 * - Gruppa CMS: items of Content entities. owner_type = entity slug (e.g. "stranky"), owner_id = item ID.
 * - JetEngine: items of a Custom Content Type with a single page (column cct_single_post_id).
 *   owner_type = "jet:{cct slug}" (e.g. "jet:produkty"), owner_id = _ID of the CCT item.
 * - WordPress: pages, posts and public custom post types chosen in Settings → Post types (1.3.0; filter
 *   gruppa_seo/wp_post_types) that are not the page of a Gruppa CMS item or of a CCT item.
 *   owner_type = "wp:{post type}" (e.g. "wp:page"), owner_id = post ID.
 * - WordPress taxonomies (1.4.0): term archives of the taxonomies in Settings → Taxonomies.
 *   owner_type = "tax:{taxonomy}" (e.g. "tax:category"), owner_id = term ID.
 *
 * The same file runs in Gruppa CMS (built-in SEO Builder) and in the separate plugin Gruppa SEO Builder.
 *
 * owner() returns the same shape for every source: [ type, item ].
 *   type: slug (= owner_type), name, kind ('content'), post_type, has_single, source ('gruppa' | 'jet' | 'wp')
 *   item: id, title, slug, wp_post_id, created, modified, author
 *
 * JetEngine CCT definitions are read straight from {prefix}jet_post_types (status = content-type),
 * as in Gruppa Table Builder and Import/Export — the JetEngine object API differs between versions.
 */
final class Gruppa_SEO_Owners {

	const JET = 'jet:';
	const WP  = 'wp:';
	const TAX = 'tax:';

	/** @var array|null slug => [ 'name' => …, 'table' => … ] */
	private static $ccts = null;

	/** @var array post ID => [ owner_type, owner_id ] | null */
	private static $by_post = array();

	public static function cms() {
		return class_exists( 'Gruppa_Types' ) && class_exists( 'Gruppa_Items' ) && class_exists( 'Gruppa_DB' );
	}

	public static function jet() {
		return function_exists( 'jet_engine' ) || class_exists( 'Jet_Engine' );
	}

	public static function is_jet( $otype ) {
		return 0 === strpos( (string) $otype, self::JET );
	}

	public static function is_wp( $otype ) {
		return 0 === strpos( (string) $otype, self::WP );
	}

	public static function is_tax( $otype ) {
		return 0 === strpos( (string) $otype, self::TAX );
	}

	/** "jet:" / "wp:" / "tax:" / '' (Gruppa CMS type). */
	public static function prefix( $otype ) {
		if ( self::is_tax( $otype ) ) {
			return self::TAX;
		}
		return self::is_jet( $otype ) ? self::JET : ( self::is_wp( $otype ) ? self::WP : '' );
	}

	/** Clean owner_type: entity slug, "jet:{cct slug}" or "wp:{post type}". */
	public static function sanitize_type( $v ) {
		$v   = (string) $v;
		$pre = self::prefix( $v );
		if ( '' !== $pre ) {
			$slug = preg_replace( '/[^A-Za-z0-9_\-]/', '', substr( $v, strlen( $pre ) ) );
			return '' !== $slug ? $pre . $slug : '';
		}
		return sanitize_key( $v );
	}

	/** Post types of the WordPress source (registered ones only): Settings → Post types, then the filter. */
	public static function wp_post_types() {
		$set   = class_exists( 'Gruppa_SEO' ) ? Gruppa_SEO::settings() : array();
		$types = isset( $set['post_types'] ) && is_array( $set['post_types'] ) ? $set['post_types'] : array( 'page', 'post' );
		$types = (array) apply_filters( 'gruppa_seo/wp_post_types', $types );
		return array_values( array_filter( $types, 'post_type_exists' ) );
	}

	/** Taxonomies with SEO for their term archives: Settings → Taxonomies, then the filter. */
	public static function wp_taxonomies() {
		$set = class_exists( 'Gruppa_SEO' ) ? Gruppa_SEO::settings() : array();
		$tax = isset( $set['taxonomies'] ) && is_array( $set['taxonomies'] ) ? $set['taxonomies'] : array( 'category', 'post_tag' );
		$tax = (array) apply_filters( 'gruppa_seo/taxonomies', $tax );
		return array_values( array_filter( $tax, 'taxonomy_exists' ) );
	}

	/** Public taxonomies offered in Settings → Taxonomies: name => label. */
	public static function wp_taxonomy_choices() {
		$skip = array( 'post_format', 'nav_menu', 'link_category', 'wp_pattern_category', 'elementor_library_type', 'elementor_library_category', 'elementor_font_type' );
		$out  = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $name => $obj ) {
			if ( in_array( $name, $skip, true ) || 0 === strpos( $name, 'jet-' ) ) {
				continue;
			}
			$out[ $name ] = $obj->labels->name . ' (' . $name . ')';
		}
		return $out;
	}

	/**
	 * Owner types for templates (Settings → Types), without loading items.
	 *
	 * @return array owner_type => label
	 */
	public static function type_list() {
		$out = array();
		if ( self::cms() ) {
			foreach ( Gruppa_Types::all() as $t ) {
				if ( 'content' === $t->kind && $t->has_single && $t->post_type ) {
					$out[ $t->slug ] = $t->name . ' · Gruppa CMS';
				}
			}
		}
		foreach ( self::ccts() as $slug => $c ) {
			$out[ self::JET . $slug ] = $c['name'] . ' · JetEngine';
		}
		foreach ( self::wp_post_types() as $pt ) {
			$o                     = get_post_type_object( $pt );
			$out[ self::WP . $pt ] = ( $o ? $o->labels->name : $pt ) . ' · WordPress';
		}
		foreach ( self::wp_taxonomies() as $tx ) {
			$o                      = get_taxonomy( $tx );
			$out[ self::TAX . $tx ] = ( $o ? $o->labels->name : $tx ) . ' · Taxonomy';
		}
		return $out;
	}

	/**
	 * Post types offered in Settings → Post types: public ones with a single page, without media and the
	 * template / form post types of builders (Elementor, JetEngine, JetFormBuilder, Gruppa CMS listings).
	 *
	 * @return array name => label
	 */
	public static function wp_post_type_choices() {
		$skip = array( 'attachment', 'elementor_library', 'e-landing-page', 'e-floating-buttons', 'jet-engine', 'jet-form-builder', 'jet-popup', 'jet-theme-core', 'jet-smart-filters', 'jet-woo-builder', 'jet-menu', 'gruppa_listing' );
		if ( self::cms() ) { // Own post types of Gruppa CMS content types: their items are owners already.
			foreach ( Gruppa_Types::all() as $t ) {
				if ( ! empty( $t->custom_pt ) && $t->post_type ) {
					$skip[] = $t->post_type;
				}
			}
		}
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $name => $obj ) {
			if ( in_array( $name, $skip, true ) || 0 === strpos( $name, 'jet-' ) ) {
				continue;
			}
			$out[ $name ] = $obj->labels->name . ' (' . $name . ')';
		}
		return $out;
	}

	/** "stranky:12" / "jet:produkty:5" → [ owner_type, owner_id ]. */
	public static function split( $key ) {
		$key = (string) $key;
		$pos = strrpos( $key, ':' );
		if ( false === $pos ) {
			return array( '', 0 );
		}
		return array( self::sanitize_type( substr( $key, 0, $pos ) ), absint( substr( $key, $pos + 1 ) ) );
	}

	/* ======================================================
	 * JetEngine CCT
	 * ==================================================== */

	private static function decode( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = maybe_unserialize( $raw );
		}
		if ( is_string( $raw ) && '' !== $raw && ( '[' === $raw[0] || '{' === $raw[0] ) ) {
			$j = json_decode( $raw, true );
			if ( is_array( $j ) ) {
				return $j;
			}
		}
		return $raw;
	}

	/** CCTs with a single page: slug => [ name, table ]. */
	public static function ccts() {
		global $wpdb;
		if ( null !== self::$ccts ) {
			return self::$ccts;
		}
		self::$ccts = array();
		$defs       = $wpdb->prefix . 'jet_post_types';
		if ( ! self::jet() || ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $defs ) ) ) ) {
			return self::$ccts;
		}
		foreach ( (array) $wpdb->get_results( "SELECT * FROM `{$defs}` WHERE status = 'content-type' ORDER BY id ASC", ARRAY_A ) as $r ) { // phpcs:ignore
			$args = self::decode( isset( $r['args'] ) ? $r['args'] : '' );
			$args = is_array( $args ) ? $args : array();
			$slug = ! empty( $r['slug'] ) ? $r['slug'] : ( isset( $args['slug'] ) ? $args['slug'] : '' );
			$slug = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			$table = $wpdb->prefix . 'jet_cct_' . $slug;
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
				continue;
			}
			if ( ! in_array( 'cct_single_post_id', (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ), true ) ) { // phpcs:ignore — name is sanitized above
				continue; // No single page → nothing to optimize.
			}
			self::$ccts[ $slug ] = array(
				'name'  => ! empty( $args['name'] ) ? (string) $args['name'] : $slug,
				'table' => $table,
			);
		}
		return self::$ccts;
	}

	private static function jet_type( $slug, $post_id = 0 ) {
		$c = self::ccts();
		return (object) array(
			'slug'       => self::JET . $slug,
			'name'       => $c[ $slug ]['name'],
			'kind'       => 'content',
			'post_type'  => $post_id ? (string) get_post_type( $post_id ) : '',
			'has_single' => 1,
			'source'     => 'jet',
		);
	}

	private static function jet_item( $row ) {
		$pid  = (int) $row->cct_single_post_id;
		$post = $pid ? get_post( $pid ) : null;
		return (object) array(
			'id'         => (int) $row->_ID,
			'title'      => $post ? get_the_title( $post ) : '#' . (int) $row->_ID,
			'slug'       => $post ? $post->post_name : (string) (int) $row->_ID,
			'wp_post_id' => $post ? $pid : 0,
			'created'    => isset( $row->cct_created ) ? $row->cct_created : '',
			'modified'   => isset( $row->cct_modified ) ? $row->cct_modified : '',
			'author'     => isset( $row->cct_author_id ) ? (int) $row->cct_author_id : 0,
			'status'     => isset( $row->cct_status ) ? $row->cct_status : '',
		);
	}

	/* ======================================================
	 * All owners
	 * ==================================================== */

	/**
	 * Items that can have SEO, grouped by owner_type.
	 *
	 * @return array [ owner_type => [ 'name' => …, 'short' => … (JetEngine: list label), 'source' => …, 'items' => [ id => title ], 'posts' => [ id => wp post ID ] ] ]
	 */
	public static function groups() {
		global $wpdb;
		$out = array();
		if ( self::cms() ) {
			foreach ( Gruppa_Types::all() as $t ) {
				if ( 'content' !== $t->kind || ! $t->has_single || ! $t->post_type ) {
					continue;
				}
				$rows  = (array) $wpdb->get_results( 'SELECT id, title, wp_post_id FROM `' . Gruppa_DB::data_table( $t->slug ) . '` ORDER BY order_index ASC, id ASC' ); // phpcs:ignore
				$items = array();
				$posts = array();
				foreach ( $rows as $r ) {
					$items[ (int) $r->id ] = $r->title;
					$posts[ (int) $r->id ] = (int) $r->wp_post_id;
				}
				$out[ $t->slug ] = array( 'name' => $t->name, 'source' => 'gruppa', 'items' => $items, 'posts' => $posts );
			}
		}
		foreach ( self::ccts() as $slug => $c ) {
			$rows = (array) $wpdb->get_results( "SELECT _ID, cct_single_post_id FROM `{$c['table']}` WHERE cct_single_post_id > 0 ORDER BY _ID ASC" ); // phpcs:ignore
			$ids  = array();
			foreach ( $rows as $r ) {
				$ids[] = (int) $r->cct_single_post_id;
			}
			if ( $ids ) {
				_prime_post_caches( $ids, false, false );
			}
			$items = array();
			$posts = array();
			foreach ( $rows as $r ) {
				$post = get_post( (int) $r->cct_single_post_id );
				if ( ! $post ) {
					continue;
				}
				$items[ (int) $r->_ID ] = get_the_title( $post );
				$posts[ (int) $r->_ID ] = (int) $post->ID;
			}
			$out[ self::JET . $slug ] = array( 'name' => $c['name'] . ' · JetEngine', 'short' => $c['name'], 'source' => 'jet', 'items' => $items, 'posts' => $posts );
		}

		// WordPress pages / posts that are not the page of a Gruppa CMS item or of a CCT item.
		$taken = array();
		foreach ( $out as $g ) {
			foreach ( $g['posts'] as $pid ) {
				$taken[ (int) $pid ] = true;
			}
		}
		foreach ( self::wp_post_types() as $pt ) {
			$obj   = get_post_type_object( $pt );
			$posts = get_posts( array(
				'post_type'        => $pt,
				'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'numberposts'      => -1,
				'orderby'          => 'page' === $pt ? 'menu_order title' : 'date',
				'order'            => 'page' === $pt ? 'ASC' : 'DESC',
				'suppress_filters' => true,
			) );
			$items = array();
			$ids   = array();
			foreach ( $posts as $post ) {
				if ( isset( $taken[ $post->ID ] ) || get_post_meta( $post->ID, '_gruppa_item', true ) ) {
					continue;
				}
				$items[ $post->ID ] = '' !== $post->post_title ? $post->post_title : '#' . $post->ID;
				$ids[ $post->ID ]   = $post->ID;
			}
			$label             = $obj ? $obj->labels->name : $pt;
			$out[ self::WP . $pt ] = array( 'name' => $label . ' · WordPress', 'short' => $label, 'source' => 'wp', 'items' => $items, 'posts' => $ids );
		}

		// Term archives (1.4.0).
		foreach ( self::wp_taxonomies() as $tx ) {
			$obj   = get_taxonomy( $tx );
			$items = array();
			$ids   = array();
			foreach ( (array) get_terms( array( 'taxonomy' => $tx, 'hide_empty' => false, 'number' => 1000 ) ) as $term ) {
				if ( $term instanceof WP_Term ) {
					$items[ $term->term_id ] = $term->name;
					$ids[ $term->term_id ]   = 0;
				}
			}
			$label                  = $obj ? $obj->labels->name : $tx;
			$out[ self::TAX . $tx ] = array( 'name' => $label . ' · Taxonomy', 'short' => $label, 'source' => 'tax', 'items' => $items, 'posts' => $ids );
		}
		return $out;
	}

	/** @return array|null [ type, item ] */
	public static function get( $otype, $oid ) {
		global $wpdb;
		$oid = (int) $oid;
		if ( self::is_tax( $otype ) ) {
			$tx   = substr( $otype, strlen( self::TAX ) );
			$term = $oid && in_array( $tx, self::wp_taxonomies(), true ) ? get_term( $oid, $tx ) : null;
			if ( ! $term instanceof WP_Term ) {
				return null;
			}
			$obj  = get_taxonomy( $tx );
			$type = (object) array(
				'slug'       => self::TAX . $tx,
				'name'       => ( $obj ? $obj->labels->name : $tx ) . ' · Taxonomy',
				'kind'       => 'content',
				'post_type'  => '',
				'taxonomy'   => $tx,
				'has_single' => 1,
				'source'     => 'tax',
			);
			$item = (object) array(
				'id'          => (int) $term->term_id,
				'title'       => $term->name,
				'slug'        => $term->slug,
				'wp_post_id'  => 0,
				'created'     => '',
				'modified'    => '',
				'author'      => 0,
				'status'      => 'publish',
				'description' => (string) $term->description,
				'parent'      => (int) $term->parent,
			);
			return array( $type, $item );
		}
		if ( self::is_wp( $otype ) ) {
			$pt   = substr( $otype, strlen( self::WP ) );
			$post = $oid ? get_post( $oid ) : null;
			if ( ! $post || $post->post_type !== $pt || ! in_array( $pt, self::wp_post_types(), true ) ) {
				return null;
			}
			$obj  = get_post_type_object( $pt );
			$type = (object) array(
				'slug'       => self::WP . $pt,
				'name'       => ( $obj ? $obj->labels->name : $pt ) . ' · WordPress',
				'kind'       => 'content',
				'post_type'  => $pt,
				'has_single' => 1,
				'source'     => 'wp',
			);
			$item = (object) array(
				'id'         => (int) $post->ID,
				'title'      => get_the_title( $post ),
				'slug'       => $post->post_name,
				'wp_post_id' => (int) $post->ID,
				'created'    => $post->post_date,
				'modified'   => $post->post_modified,
				'author'     => (int) $post->post_author,
				'status'     => $post->post_status,
			);
			return array( $type, $item );
		}
		if ( self::is_jet( $otype ) ) {
			$slug = substr( $otype, strlen( self::JET ) );
			$c    = self::ccts();
			if ( ! isset( $c[ $slug ] ) || ! $oid ) {
				return null;
			}
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$c[ $slug ]['table']}` WHERE `_ID` = %d", $oid ) ); // phpcs:ignore
			if ( ! $row ) {
				return null;
			}
			$item = self::jet_item( $row );
			return array( self::jet_type( $slug, $item->wp_post_id ), $item );
		}
		if ( ! self::cms() ) {
			return null;
		}
		$type = Gruppa_Types::get_by_slug( $otype );
		if ( ! $type || 'content' !== $type->kind ) {
			return null;
		}
		$item = Gruppa_Items::get( $type, $oid );
		if ( ! $item ) {
			return null;
		}
		if ( ! isset( $type->source ) ) {
			$type->source = 'gruppa';
		}
		return array( $type, $item );
	}

	/** Owner of a WordPress post (single page): [ owner_type, owner_id ] or null. */
	public static function from_post( $pid ) {
		global $wpdb;
		$pid = (int) $pid;
		if ( ! $pid ) {
			return null;
		}
		if ( array_key_exists( $pid, self::$by_post ) ) {
			return self::$by_post[ $pid ];
		}
		self::$by_post[ $pid ] = null;
		if ( self::cms() ) {
			$tslug = get_post_meta( $pid, '_gruppa_type', true );
			$iid   = (int) get_post_meta( $pid, '_gruppa_item', true );
			if ( $tslug && $iid ) {
				self::$by_post[ $pid ] = array( (string) $tslug, $iid );
				return self::$by_post[ $pid ];
			}
		}
		foreach ( self::ccts() as $slug => $c ) {
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT _ID FROM `{$c['table']}` WHERE cct_single_post_id = %d LIMIT 1", $pid ) ); // phpcs:ignore
			if ( $id ) {
				self::$by_post[ $pid ] = array( self::JET . $slug, $id );
				return self::$by_post[ $pid ];
			}
		}
		$pt = get_post_type( $pid );
		if ( $pt && in_array( $pt, self::wp_post_types(), true ) ) {
			self::$by_post[ $pid ] = array( self::WP . $pt, $pid );
		}
		return self::$by_post[ $pid ];
	}

	/** Admin screen where the owner item is edited. */
	public static function edit_url( $otype, $oid ) {
		if ( self::is_tax( $otype ) ) {
			return (string) get_edit_term_link( (int) $oid, substr( $otype, strlen( self::TAX ) ) );
		}
		if ( self::is_wp( $otype ) ) {
			return (string) get_edit_post_link( (int) $oid, 'raw' );
		}
		if ( self::is_jet( $otype ) ) {
			return admin_url( 'admin.php?page=jet-cct-' . substr( $otype, strlen( self::JET ) ) . '&cct_action=edit&item_id=' . (int) $oid );
		}
		$page = class_exists( 'Gruppa_Admin_Items' ) ? Gruppa_Admin_Items::page_slug( $otype ) : 'gruppa-item-' . $otype;
		return admin_url( 'admin.php?page=' . $page . '&view=edit&id=' . (int) $oid );
	}
}
