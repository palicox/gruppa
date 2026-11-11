<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST API: /wp-json/gruppa/v1
 *
 *   GET    /types                 types available to the current user
 *   GET    /types/{type}          field schema
 *   GET    /{type}                list  (page, per_page, search, status, orderby, order, filter[key]=value)
 *   POST   /{type}                create
 *   GET    /{type}/{id}           read
 *   PUT    /{type}/{id}           update (partial)
 *   PATCH  /{type}/{id}           update (partial)
 *   DELETE /{type}/{id}           delete
 *
 * Auth: cookie + X-WP-Nonce, or Application Passwords (Basic auth).
 */
class Gruppa_REST {

	const NS = 'gruppa/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		// Type slug; never the plugin's own routes (listing/{id}, types, auth/…, form-submit/…).
		$slug = '(?P<type>(?!(?:listing|types|auth|form-submit)\b)[a-z0-9_]+)';

		register_rest_route( self::NS, '/types', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'list_types' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/types/' . $slug, array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_schema' ),
			'permission_callback' => array( __CLASS__, 'perm_read' ),
		) );

		register_rest_route( self::NS, '/' . $slug, array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_items' ),
				'permission_callback' => array( __CLASS__, 'perm_read' ),
				'args'                => array(
					'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
					'search'   => array( 'type' => 'string', 'default' => '' ),
					'status'   => array( 'type' => 'string', 'default' => '' ),
					'orderby'  => array( 'type' => 'string', 'default' => 'order_index' ),
					'order'    => array( 'type' => 'string', 'default' => 'asc', 'enum' => array( 'asc', 'desc', 'ASC', 'DESC' ) ),
				),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_item' ),
				'permission_callback' => array( __CLASS__, 'perm_create' ),
			),
		) );

		register_rest_route( self::NS, '/' . $slug . '/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_item' ),
				'permission_callback' => array( __CLASS__, 'perm_read' ),
			),
			array(
				'methods'             => 'PUT, PATCH',
				'callback'            => array( __CLASS__, 'update_item' ),
				'permission_callback' => array( __CLASS__, 'perm_update' ),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_item' ),
				'permission_callback' => array( __CLASS__, 'perm_delete' ),
			),
		) );
	}

	/* ---------- Helpers ---------- */

	/** Published type with REST enabled, or null. */
	private static function type( $request ) {
		$type = Gruppa_Types::get_by_slug( $request['type'] );
		if ( ! $type || 'publish' !== $type->status ) {
			return null;
		}
		$access = Gruppa_Access::get( $type );
		return $access['rest'] ? $type : null;
	}

	private static function perm( $request, $action ) {
		$type = self::type( $request );
		if ( ! $type ) {
			return new WP_Error( 'gruppa_not_found', 'Type not found.', array( 'status' => 404 ) );
		}
		if ( Gruppa_Access::can( $type, $action ) ) {
			return true;
		}
		return new WP_Error(
			'gruppa_forbidden',
			'Sorry, you are not allowed to ' . $action . ' this type.',
			array( 'status' => is_user_logged_in() ? 403 : 401 )
		);
	}

	public static function perm_read( $r ) {
		return self::perm( $r, 'read' );
	}
	public static function perm_create( $r ) {
		return self::perm( $r, 'create' );
	}
	public static function perm_update( $r ) {
		return self::perm( $r, 'update' );
	}
	public static function perm_delete( $r ) {
		return self::perm( $r, 'delete' );
	}

	/** Can the current user see drafts of this type? */
	private static function sees_drafts( $type ) {
		return Gruppa_Access::can_all( $type, 'update' );
	}

	private static function body( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) || ! $data ) {
			$data = $request->get_body_params();
		}
		return is_array( $data ) ? $data : array();
	}

	private static function invalid( $errors ) {
		$fields = array();
		foreach ( $errors as $k => $m ) {
			$fields[ $k ] = preg_replace( '/^[^:]+:\s*/', '', $m );
		}
		return new WP_Error( 'gruppa_invalid', 'Validation failed.', array(
			'status' => 400,
			'errors' => array_values( $errors ),
			'fields' => $fields,
		) );
	}

	/**
	 * Optional "_gruppa_form" (ID or slug): restrict data to the form's fields
	 * and enforce its hidden values.
	 *
	 * @return array|WP_Error
	 */
	private static function through_form( $request, $type, $data, $mode ) {
		$ref = isset( $data['_gruppa_form'] ) ? $data['_gruppa_form'] : $request->get_param( '_gruppa_form' );
		unset( $data['_gruppa_form'] );
		if ( null === $ref || '' === $ref ) {
			return $data;
		}
		return Gruppa_Forms::apply( Gruppa_Forms::get_by( $ref ), $type, $data, $mode );
	}

	private static function not_found() {
		return new WP_Error( 'gruppa_item_not_found', 'Item not found.', array( 'status' => 404 ) );
	}

	/* ---------- Types ---------- */

	public static function list_types() {
		$out = array();
		foreach ( Gruppa_Types::all( 'publish' ) as $type ) {
			$access = Gruppa_Access::get( $type );
			if ( ! $access['rest'] || ! Gruppa_Access::can( $type, 'read' ) ) {
				continue;
			}
			$out[] = array(
				'slug'  => $type->slug,
				'name'  => $type->name,
				'count' => Gruppa_DB::count_items( $type->slug ),
				'href'  => rest_url( self::NS . '/' . $type->slug ),
			);
		}
		return rest_ensure_response( $out );
	}

	public static function get_schema( $request ) {
		$type   = self::type( $request );
		$fields = array();
		foreach ( $type->fields as $f ) {
			if ( Gruppa_Fields::is_virtual( $f ) ) {
				continue;
			}
			$def = array(
				'key'         => $f['key'],
				'label'       => $f['label'],
				'type'        => $f['type'],
				'required'    => (bool) $f['required'],
				'description' => $f['description'],
				'placeholder' => $f['placeholder'],
				'default'     => Gruppa_Fields::default_value( $f ),
				'multiple'    => Gruppa_Fields::is_multi( $f ),
			);
			if ( 'text' === $f['type'] ) {
				$def['format'] = $f['format'];
				if ( 'password' === $f['format'] ) {
					$def['default'] = '';
				}
			}
			if ( in_array( $f['type'], array( 'text', 'textarea', 'editor' ), true ) && $f['limit'] ) {
				$def['limit'] = (int) $f['limit'];
			}
			if ( 'number' === $f['type'] ) {
				foreach ( array( 'min', 'max', 'step' ) as $k ) {
					$def[ $k ] = '' === $f[ $k ] ? null : $f[ $k ] + 0;
				}
			}
			if ( 'relation' === $f['type'] ) {
				$def['related'] = $f['related'];
			}
			if ( 'repeater' === $f['type'] ) {
				$def['sub'] = $f['sub'];
			}
			if ( Gruppa_Fields::has_options( $f['type'] ) ) {
				$def['options'] = $f['options'];
			}
			if ( 'media' === $f['type'] ) {
				$def['return']     = $f['return'];
				$def['file_types'] = '' === $f['file_types'] ? array() : explode( ',', $f['file_types'] );
				$def['max_files']  = (int) $f['max_files'];
				$def['max_mb']     = (float) $f['max_mb'];
			}
			$fields[] = $def;
		}

		return rest_ensure_response( array(
			'slug'      => $type->slug,
			'name'      => $type->name,
			'kind'      => $type->kind,
			'single'    => $type->has_single ? $type->post_type : null,
			'system'    => array( 'id', 'title', 'slug', 'status', 'order_index', 'author', 'created', 'modified', 'wp_post_id' ),
			'fields'    => $fields,
			'structure' => 'data' === $type->kind ? $type->structure : null,
			'groups'    => 'data' === $type->kind && 'groups' === $type->structure
				? array_map( function ( $g ) {
					return array( 'id' => $g['id'], 'name' => $g['name'], 'slug' => $g['slug'], 'targets' => $g['targets'], 'choice' => $g['choice'] );
				}, $type->groups )
				: null,
			'relations' => array_map( function ( $l ) {
				return array( 'key' => $l['key'], 'label' => $l['label'], 'data' => $l['data'], 'group' => 'values' === $l['group_slug'] ? null : $l['group_slug'], 'choice' => $l['choice'] );
			}, Gruppa_Relations::links( $type ) ),
			'can'       => array(
				'read'   => Gruppa_Access::can( $type, 'read' ),
				'create' => Gruppa_Access::can( $type, 'create' ),
				'update' => Gruppa_Access::can( $type, 'update' ),
				'delete' => Gruppa_Access::can( $type, 'delete' ),
			),
		) );
	}

	/* ---------- Items ---------- */

	public static function list_items( $request ) {
		global $wpdb;
		$type  = self::type( $request );
		$table = Gruppa_DB::data_table( $type->slug );
		$keys  = wp_list_pluck( $type->fields, 'key' );
		if ( 'data' === $type->kind && 'groups' === $type->structure ) {
			$keys = array_merge( $keys, array( 'group_id', 'group_slug' ) );
		}

		$where = array( '1=1' );

		$status = $request['status'];
		if ( ! self::sees_drafts( $type ) ) {
			$uid     = get_current_user_id();
			$where[] = $uid && 'author' === Gruppa_Access::rule( $type, 'update' )
				? $wpdb->prepare( "(status = 'publish' OR author = %d)", $uid ) // Own drafts.
				: "status = 'publish'";
		} elseif ( in_array( $status, array( 'publish', 'draft' ), true ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $status );
		}

		if ( '' !== $request['search'] ) {
			$like    = '%' . $wpdb->esc_like( $request['search'] ) . '%';
			$where[] = $wpdb->prepare( '(title LIKE %s OR slug LIKE %s)', $like, $like );
		}

		$filter = $request->get_param( 'filter' );
		if ( is_array( $filter ) ) {
			foreach ( $filter as $k => $v ) {
				$k    = sanitize_key( $k );
				$link = Gruppa_Types::field( $type, $k );
				if ( Gruppa_Relations::is_link( $link ) && ! is_array( $v ) ) {
					// Data link: ?filter[{data}__{group}]=ID, title or slug of the value.
					$vals    = Gruppa_Listing::readable_to_stored( $link, array( (string) $v ) );
					$where[] = Gruppa_Listing::condition( $k, $link, 'eq', $vals ? $vals[0] : '0' );
					continue;
				}
				if ( ! in_array( $k, array_merge( array( 'id', 'slug', 'author', 'wp_post_id' ), $keys ), true ) || is_array( $v ) ) {
					continue;
				}
				$where[] = $wpdb->prepare( "`{$k}` = %s", (string) $v ); // phpcs:ignore
			}
		}

		$orderby = in_array( $request['orderby'], array_merge( array( 'id', 'title', 'slug', 'order_index', 'created', 'modified' ), $keys ), true ) ? $request['orderby'] : 'order_index';
		$order   = 'desc' === strtolower( $request['order'] ) ? 'DESC' : 'ASC';
		$where   = implode( ' AND ', $where );

		$per   = (int) $request['per_page'];
		$page  = (int) $request['page'];
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE {$where}" ); // phpcs:ignore
		$rows  = Gruppa_Relations::attach( $type, (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE {$where} ORDER BY `{$orderby}` {$order}, id ASC LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ) ) ); // phpcs:ignore

		$items = array();
		foreach ( (array) $rows as $r ) {
			$items[] = Gruppa_Items::format( $type, $r );
		}

		$res = rest_ensure_response( $items );
		$res->header( 'X-WP-Total', $total );
		$res->header( 'X-WP-TotalPages', (int) ceil( $total / $per ) );
		return $res;
	}

	public static function get_item( $request ) {
		$type = self::type( $request );
		$item = Gruppa_Items::get( $type, (int) $request['id'] );
		if ( ! $item || ( 'publish' !== $item->status && ! Gruppa_Access::can( $type, 'update', null, $item ) ) ) {
			return self::not_found();
		}
		return rest_ensure_response( Gruppa_Items::format( $type, $item ) );
	}

	public static function create_item( $request ) {
		$type = self::type( $request );
		$data = self::through_form( $request, $type, self::body( $request ), 'create' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		list( $row, $errors ) = Gruppa_Items::from_data( $type, $data, true );
		if ( $errors ) {
			return self::invalid( $errors );
		}
		$id = Gruppa_Items::save( $type, $row, 0 );
		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'gruppa_db', $id->get_error_message(), array( 'status' => 500 ) );
		}
		self::save_relations( $type, $id, $data );
		$res = rest_ensure_response( Gruppa_Items::format( $type, Gruppa_Items::get( $type, $id ) ) );
		$res->set_status( 201 );
		$res->header( 'Location', rest_url( self::NS . '/' . $type->slug . '/' . $id ) );
		return $res;
	}

	public static function update_item( $request ) {
		$type = self::type( $request );
		$id   = (int) $request['id'];
		$item = Gruppa_Items::get( $type, $id );
		if ( ! $item ) {
			return self::not_found();
		}
		if ( ! Gruppa_Access::can( $type, 'update', null, $item ) ) {
			return new WP_Error( 'gruppa_forbidden', 'Sorry, you can only update your own items.', array( 'status' => 403 ) );
		}
		$data = self::through_form( $request, $type, self::body( $request ), 'update' );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		list( $row, $errors ) = Gruppa_Items::from_data( $type, $data, false );
		if ( $errors ) {
			return self::invalid( $errors );
		}
		if ( $row ) {
			Gruppa_Items::save( $type, $row, $id );
		}
		self::save_relations( $type, $id, $data );
		return rest_ensure_response( Gruppa_Items::format( $type, Gruppa_Items::get( $type, $id ) ) );
	}

	/**
	 * Data links in a request body: { "relations": { "{data}__{group}": [ IDs, titles or slugs ] } }.
	 * A link left out is not changed; an empty list clears it.
	 */
	private static function save_relations( $type, $id, $data ) {
		if ( empty( $data['relations'] ) || ! is_array( $data['relations'] ) ) {
			return;
		}
		foreach ( Gruppa_Relations::links( $type ) as $l ) {
			if ( array_key_exists( $l['key'], $data['relations'] ) ) {
				Gruppa_Relations::set( $type, $id, $l, (array) $data['relations'][ $l['key'] ] );
			}
		}
	}

	public static function delete_item( $request ) {
		$type = self::type( $request );
		$id   = (int) $request['id'];
		$item = Gruppa_Items::get( $type, $id );
		if ( ! $item ) {
			return self::not_found();
		}
		if ( ! Gruppa_Access::can( $type, 'delete', null, $item ) ) {
			return new WP_Error( 'gruppa_forbidden', 'Sorry, you can only delete your own items.', array( 'status' => 403 ) );
		}
		$prev = Gruppa_Items::format( $type, $item );
		Gruppa_Items::delete( $type, $id );
		return rest_ensure_response( array( 'deleted' => true, 'previous' => $prev ) );
	}
}
