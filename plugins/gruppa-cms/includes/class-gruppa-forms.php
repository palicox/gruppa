<?php
defined( 'ABSPATH' ) || exit;

/**
 * Dynamic Forms: forms built in Gruppa CMS on top of a Type.
 */
class Gruppa_Forms {

	const WIDTHS = array( 100, 75, 66, 50, 33, 25 );

	/** System elements a form can contain. */
	const SYSTEM = array(
		'title'  => 'Title',
		'slug'   => 'Slug',
		'status' => 'Status',
	);

	/* ======================================================
	 * Settings
	 * ==================================================== */

	public static function defaults() {
		return array(
			'mode'            => 'create',
			'item_source'     => 'param',
			'item_param'      => 'item',
			'submit_text'     => __( 'Send', 'gruppa-cms' ),
			'submit_align'    => 'left',
			'success_message' => __( 'Thank you.', 'gruppa-cms' ),
			'redirect'        => '',
			'after_success'   => 'reset',
			'layout'          => array(),
			'new_status'      => 'publish',
			'confirm_text'    => 'Are you sure?',
			'actions'         => array(),
			'access'          => '', // '' = same as the content type (Create / Update / Delete rule).
		);
	}

	/**
	 * Rule a form gets from its content type (new forms; migration of the former
	 * "Same as content type"). A capability rule is not offered for forms → administrators.
	 */
	public static function inherited_rule( $type, $mode ) {
		$action = isset( self::modes()[ $mode ] ) ? $mode : 'create';
		$rule   = Gruppa_Access::rule( $type, $action );
		return isset( self::access_options( $action )[ $rule ] ) ? $rule : 'role:administrator';
	}

	/** "Who can submit" options for a mode (0.22: always the form's own rule). */
	public static function access_options( $mode, $type = null ) {
		$action  = isset( self::modes()[ $mode ] ) ? $mode : 'create';
		$out     = array();
		foreach ( Gruppa_Access::options( $action ) as $k => $l ) {
			if ( 'cap' !== $k ) {
				$out[ $k ] = 'public' === $k ? 'Everyone (visitors too)' : $l;
			}
		}
		return $out;
	}

	/** Effective rule of a form: its own, or the content type's. */
	public static function rule( $form, $type ) {
		$own = isset( $form->settings['access'] ) ? (string) $form->settings['access'] : '';
		return '' !== $own ? $own : Gruppa_Access::rule( $type, $form->settings['mode'] );
	}

	/**
	 * May the user use the form? Administrators always may.
	 *
	 * @param int|null    $user_id null = current user, 0 = visitor (not logged in).
	 * @param object|null $item    Target item (update / delete; rule "author").
	 */
	public static function can( $form, $type, $item = null, $user_id = null ) {
		$own = isset( $form->settings['access'] ) ? (string) $form->settings['access'] : '';
		if ( '' === $own ) {
			return Gruppa_Access::can( $type, $form->settings['mode'], $user_id, $item );
		}
		return Gruppa_Access::allows( $own, $form->settings['mode'], $user_id, $item );
	}

	public static function modes() {
		return array(
			'create' => 'Create (new item)',
			'update' => 'Update (existing item)',
			'delete' => 'Delete (existing item)',
		);
	}

	/** Status of items created by the form (moderation). */
	public static function new_statuses() {
		return array(
			'publish'      => 'Published',
			'draft_guests' => 'Draft for guests, Published for logged-in users',
			'draft'        => 'Always Draft (review in admin)',
		);
	}

	public static function defaults_element() {
		return array(
			'key'         => '',
			'width'       => 100,
			'label'       => '',
			'placeholder' => '',
			'description' => '',
			'hidden'      => false,
			'value'       => '',
			'display'     => '',
		);
	}

	/**
	 * How a field can be shown in a form: [ value => label ], first = default.
	 * Empty array = only one way.
	 */
	public static function displays( $f ) {
		switch ( isset( $f['type'] ) ? $f['type'] : '' ) {
			case 'textarea':
				return array( 'textarea' => 'Textarea', 'editor' => 'Editor (rich text)' );
			case 'editor':
				return array( 'editor' => 'Editor (rich text)', 'textarea' => 'Textarea' );
			case 'select':
				return ! empty( $f['multiple'] )
					? array( 'select' => 'Select (multiple)', 'checkbox' => 'Checkboxes' )
					: array( 'select' => 'Select', 'radio' => 'Radio buttons' );
			case 'radio':
				return array( 'radio' => 'Radio buttons', 'select' => 'Select' );
			case 'checkbox':
				return array( 'checkbox' => 'Checkboxes', 'select' => 'Select (multiple)' );
			case 'switcher':
				return array( 'switcher' => 'Switcher', 'checkbox' => 'Checkbox' );
			case 'relation':
				$list = Gruppa_Fields::is_multi( $f ) ? 'Checkboxes' : 'Radio buttons';
				return 'list' === $f['ui']
					? array( 'list' => $list, 'select' => 'Select' )
					: array( 'select' => 'Select', 'list' => $list );
		}
		return array();
	}

	/** Elements available for a Type: [ key => [ label, type ] ]. */
	public static function available( $type ) {
		$out = array();
		foreach ( self::SYSTEM as $k => $l ) {
			$out[ $k ] = array( 'label' => $l, 'type' => 'status' === $k ? 'select' : 'text', 'system' => true );
		}
		foreach ( $type->fields as $f ) {
			if ( in_array( $f['type'], array( 'html', 'repeater' ), true ) ) {
				continue; // Not in forms (yet).
			}
			$out[ $f['key'] ] = array( 'label' => $f['label'], 'type' => $f['type'], 'system' => false, 'field' => $f );
		}
		// Data links (values of a Data type / group attached to this Content type).
		foreach ( Gruppa_Relations::fields( $type ) as $f ) {
			$out[ $f['key'] ] = array(
				'label'  => $f['label'],
				'type'   => 'relation',
				'system' => false,
				'field'  => $f,
				'note'   => 'Data · ' . $f['link']['data_name'] . ( $f['link']['group'] ? ' · ' . $f['link']['label'] : '' ),
			);
		}
		return $out;
	}

	public static function sanitize_settings( $raw, $type ) {
		$d   = self::defaults();
		$raw = is_array( $raw ) ? $raw : array();
		$s   = array(
			'mode'            => isset( $raw['mode'] ) && isset( self::modes()[ $raw['mode'] ] ) ? $raw['mode'] : 'create',
			'item_source'     => isset( $raw['item_source'] ) && in_array( $raw['item_source'], array( 'current', 'user' ), true ) ? $raw['item_source'] : 'param',
			'item_param'      => sanitize_key( isset( $raw['item_param'] ) && '' !== $raw['item_param'] ? $raw['item_param'] : $d['item_param'] ),
			'submit_text'     => sanitize_text_field( isset( $raw['submit_text'] ) && '' !== $raw['submit_text'] ? $raw['submit_text'] : $d['submit_text'] ),
			'submit_align'    => isset( $raw['submit_align'] ) && in_array( $raw['submit_align'], array( 'left', 'center', 'right', 'stretch' ), true ) ? $raw['submit_align'] : 'left',
			'success_message' => sanitize_textarea_field( isset( $raw['success_message'] ) ? $raw['success_message'] : $d['success_message'] ),
			'redirect'        => esc_url_raw( isset( $raw['redirect'] ) ? trim( $raw['redirect'] ) : '' ),
			'after_success'   => isset( $raw['after_success'] ) && in_array( $raw['after_success'], array( 'reset', 'hide', 'keep' ), true ) ? $raw['after_success'] : 'reset',
			'layout'          => array(),
			'new_status'      => isset( $raw['new_status'] ) && isset( self::new_statuses()[ $raw['new_status'] ] ) ? $raw['new_status'] : 'publish',
			'confirm_text'    => sanitize_text_field( isset( $raw['confirm_text'] ) ? $raw['confirm_text'] : $d['confirm_text'] ),
			'actions'         => class_exists( 'Gruppa_Form_Actions' ) ? Gruppa_Form_Actions::sanitize( isset( $raw['actions'] ) ? $raw['actions'] : array() ) : array(),
			'access'          => '',
		);
		$acc = isset( $raw['access'] ) ? (string) $raw['access'] : '';
		if ( '' !== $acc && isset( self::access_options( $s['mode'] )[ $acc ] ) ) {
			$s['access'] = $acc;
		} elseif ( $type ) {
			$s['access'] = self::inherited_rule( $type, $s['mode'] );
		}
		// Keep {placeholders} in redirect URLs.
		if ( isset( $raw['redirect'] ) ) {
			$s['redirect'] = preg_replace( '/[^\w\-\.~:\/\?#\[\]@!\$&\'\(\)\*\+,;=%{}]/', '', trim( $raw['redirect'] ) );
		}

		$avail = $type ? self::available( $type ) : array();
		$seen  = array();
		$items = isset( $raw['layout'] ) ? $raw['layout'] : array();
		if ( is_string( $items ) ) {
			$items = json_decode( wp_unslash( $items ), true );
		}
		foreach ( (array) $items as $e ) {
			$key = isset( $e['key'] ) ? sanitize_key( $e['key'] ) : '';
			if ( ! isset( $avail[ $key ] ) || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$w            = isset( $e['width'] ) ? (int) $e['width'] : 100;
			$val          = isset( $e['value'] ) ? $e['value'] : '';
			$disps        = isset( $avail[ $key ]['field'] ) ? self::displays( $avail[ $key ]['field'] ) : array();
			$disp         = isset( $e['display'] ) ? sanitize_key( $e['display'] ) : '';
			$s['layout'][] = array(
				'key'         => $key,
				'width'       => in_array( $w, self::WIDTHS, true ) ? $w : 100,
				'label'       => sanitize_text_field( isset( $e['label'] ) ? $e['label'] : '' ),
				'placeholder' => sanitize_text_field( isset( $e['placeholder'] ) ? $e['placeholder'] : '' ),
				'description' => sanitize_text_field( isset( $e['description'] ) ? $e['description'] : '' ),
				'hidden'      => ! empty( $e['hidden'] ),
				'value'       => is_array( $val ) ? array_map( 'sanitize_text_field', $val ) : sanitize_text_field( (string) $val ),
				'display'     => isset( $disps[ $disp ] ) ? $disp : '',
			);
		}
		return $s;
	}

	/* ======================================================
	 * CRUD
	 * ==================================================== */

	private static function hydrate( $row ) {
		$s             = $row->settings ? json_decode( $row->settings, true ) : array();
		$row->settings = wp_parse_args( is_array( $s ) ? $s : array(), self::defaults() );
		return $row;
	}

	public static function all( $status = '' ) {
		global $wpdb;
		$t     = Gruppa_DB::forms_table();
		$where = $status ? $wpdb->prepare( 'WHERE status = %s', $status ) : '';
		return array_map( array( __CLASS__, 'hydrate' ), (array) $wpdb->get_results( "SELECT * FROM `{$t}` {$where} ORDER BY order_index ASC, id ASC" ) ); // phpcs:ignore
	}

	public static function counts() {
		global $wpdb;
		$t   = Gruppa_DB::forms_table();
		$out = array( 'all' => 0, 'publish' => 0, 'draft' => 0 );
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) c FROM `{$t}` GROUP BY status" ) as $r ) { // phpcs:ignore
			$out[ $r->status ] = (int) $r->c;
			$out['all']       += (int) $r->c;
		}
		return $out;
	}

	public static function get( $id ) {
		global $wpdb;
		$t   = Gruppa_DB::forms_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE id = %d", $id ) ); // phpcs:ignore
		return $row ? self::hydrate( $row ) : null;
	}

	public static function get_by( $id_or_slug ) {
		if ( is_numeric( $id_or_slug ) ) {
			return self::get( (int) $id_or_slug );
		}
		global $wpdb;
		$t   = Gruppa_DB::forms_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE slug = %s", sanitize_key( $id_or_slug ) ) ); // phpcs:ignore
		return $row ? self::hydrate( $row ) : null;
	}

	/** @return int|WP_Error */
	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$t    = Gruppa_DB::forms_table();
		$now  = current_time( 'mysql' );
		$form = $id ? self::get( $id ) : null;
		if ( $id && ! $form ) {
			return new WP_Error( 'missing', 'Not found' );
		}

		$name = sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' );
		if ( '' === $name ) {
			return new WP_Error( 'name', 'Name' );
		}
		$type_slug = sanitize_key( isset( $data['type_slug'] ) ? $data['type_slug'] : '' );
		$type      = $type_slug ? Gruppa_Types::get_by_slug( $type_slug ) : null;
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

		if ( $form ) {
			$wpdb->update( $t, $row, array( 'id' => $id ) );
			return (int) $id;
		}

		$slug = Gruppa_Types::sanitize_key( ! empty( $data['slug'] ) ? $data['slug'] : $name );
		if ( '' === $slug ) {
			return new WP_Error( 'slug', 'Slug' );
		}
		if ( self::get_by( $slug ) ) {
			return new WP_Error( 'slug_exists', 'Slug exists' );
		}
		$row['slug']    = $slug;
		$row['author']  = get_current_user_id();
		$row['created'] = $now;
		if ( ! isset( $row['order_index'] ) ) {
			$row['order_index'] = Gruppa_DB::next_order( '_forms' );
		}
		if ( false === $wpdb->insert( $t, $row ) ) {
			return new WP_Error( 'db', $wpdb->last_error );
		}
		return (int) $wpdb->insert_id;
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		$wpdb->update( Gruppa_DB::forms_table(), array( 'status' => 'draft' === $status ? 'draft' : 'publish', 'modified' => current_time( 'mysql' ) ), array( 'id' => (int) $id ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( Gruppa_DB::forms_table(), array( 'id' => (int) $id ) );
	}

	/* ======================================================
	 * Submission rules (used by REST)
	 * ==================================================== */

	/**
	 * Restrict and complete submitted data according to the form:
	 * only keys present in the layout are accepted, hidden values are enforced.
	 *
	 * @return array|WP_Error
	 */
	public static function apply( $form, $type, $data, $mode ) {
		if ( ! $form || 'publish' !== $form->status || $form->type_slug !== $type->slug ) {
			return new WP_Error( 'gruppa_form', 'Invalid form.', array( 'status' => 400 ) );
		}
		if ( $form->settings['mode'] !== $mode ) {
			return new WP_Error( 'gruppa_form', 'Form mode mismatch.', array( 'status' => 400 ) );
		}
		$out = array();
		foreach ( $form->settings['layout'] as $e ) {
			if ( $e['hidden'] ) {
				$out[ $e['key'] ] = $e['value'];
			} elseif ( array_key_exists( $e['key'], (array) $data ) ) {
				$out[ $e['key'] ] = $data[ $e['key'] ];
			} elseif ( 'create' === $mode ) {
				// Unchecked checkboxes etc. are simply absent.
				$out[ $e['key'] ] = '';
			}
		}
		return $out;
	}
}
