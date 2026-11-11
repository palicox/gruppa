<?php
defined( 'ABSPATH' ) || exit;

/**
 * Search Filters — groups of visitor filters for a content type.
 *
 *   Elementor:  widget "Gruppa Search" + widget "Gruppa Listing" (Search Filters = this group)
 *   Shortcode:  [gruppa_filters group="slug"]  +  [gruppa_listing listing="…" filters="slug"]
 *
 * Filter types: search, select, checkbox, range (number from–to), date (from–to).
 * Values travel in the URL with readable values: ?kategoria=zimna,letna&cena_from=500&q=alpy
 */
class Gruppa_Filters {

	const TYPES = array(
		'search'   => 'Search',
		'select'   => 'Select',
		'checkbox' => 'Checkbox',
		'range'    => 'Range (from – to)',
		'date'     => 'Date (from – to)',
	);

	private static $count = 0;

	public static function defaults() {
		return array(
			'filters'     => array(),
			'apply'       => 'instant', // instant | button
			'button_text' => __( 'Filter', 'gruppa-cms' ),
			'reset'       => true,
			'reset_text'  => __( 'Reset filters', 'gruppa-cms' ),
		);
	}

	public static function filter_defaults( $type ) {
		return array(
			'type'        => $type,
			'label'       => '',
			'field'       => 'search' === $type ? '' : '',
			'fields'      => array( 'title' ),
			'param'       => '',
			'placeholder' => '',
			'all_label'   => __( 'All', 'gruppa-cms' ),
			'from_label'  => __( 'From', 'gruppa-cms' ),
			'to_label'    => __( 'To', 'gruppa-cms' ),
			'step'        => '',
		);
	}

	/* ======================================================
	 * CRUD
	 * ==================================================== */

	public static function table() {
		return Gruppa_DB::filters_table();
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

	/** Field definition of a type, or a pseudo definition for system columns. */
	public static function field( $type, $key ) {
		foreach ( Gruppa_Types::fields_all( $type ) as $f ) {
			if ( $f['key'] === $key ) {
				return $f;
			}
		}
		$sys = array( 'title' => 'text', 'slug' => 'text', 'created' => 'date', 'modified' => 'date' );
		return isset( $sys[ $key ] ) ? array( 'key' => $key, 'label' => ucfirst( $key ), 'type' => $sys[ $key ], 'system' => true ) : null;
	}

	/** Field types each filter type can use. */
	public static function field_kinds( $ftype ) {
		switch ( $ftype ) {
			case 'search':
				return array( 'text', 'textarea', 'editor' );
			case 'select':
			case 'checkbox':
				return array( 'relation', 'select', 'radio', 'checkbox', 'switcher', 'text' );
			case 'range':
				return array( 'number' );
			case 'date':
				return array( 'date' );
		}
		return array();
	}

	public static function sanitize_settings( $raw, $type ) {
		$raw = is_array( $raw ) ? $raw : array();
		$d   = self::defaults();
		$s   = array(
			'filters'     => array(),
			'apply'       => isset( $raw['apply'] ) && 'button' === $raw['apply'] ? 'button' : 'instant',
			'button_text' => sanitize_text_field( isset( $raw['button_text'] ) && '' !== $raw['button_text'] ? $raw['button_text'] : $d['button_text'] ),
			'reset'       => ! empty( $raw['reset'] ),
			'reset_text'  => sanitize_text_field( isset( $raw['reset_text'] ) && '' !== $raw['reset_text'] ? $raw['reset_text'] : $d['reset_text'] ),
		);
		$params = array();
		foreach ( (array) ( isset( $raw['filters'] ) ? $raw['filters'] : array() ) as $f ) {
			$t = isset( $f['type'] ) && isset( self::TYPES[ $f['type'] ] ) ? $f['type'] : '';
			if ( ! $t || ! $type ) {
				continue;
			}
			$def = array_merge( self::filter_defaults( $t ), array(
				'label'       => sanitize_text_field( isset( $f['label'] ) ? $f['label'] : '' ),
				'placeholder' => sanitize_text_field( isset( $f['placeholder'] ) ? $f['placeholder'] : '' ),
				'all_label'   => sanitize_text_field( isset( $f['all_label'] ) && '' !== $f['all_label'] ? $f['all_label'] : __( 'All', 'gruppa-cms' ) ),
				'from_label'  => sanitize_text_field( isset( $f['from_label'] ) && '' !== $f['from_label'] ? $f['from_label'] : 'Od' ),
				'to_label'    => sanitize_text_field( isset( $f['to_label'] ) && '' !== $f['to_label'] ? $f['to_label'] : 'Do' ),
				'step'        => isset( $f['step'] ) && is_numeric( $f['step'] ) ? (string) ( $f['step'] + 0 ) : '',
			) );
			if ( 'search' === $t ) {
				$fields = array();
				foreach ( (array) ( isset( $f['fields'] ) ? $f['fields'] : array() ) as $k ) {
					$fd = self::field( $type, sanitize_key( $k ) );
					if ( $fd && in_array( $fd['type'], self::field_kinds( 'search' ), true ) ) {
						$fields[] = $fd['key'];
					}
				}
				$def['fields'] = $fields ? array_values( array_unique( $fields ) ) : array( 'title' );
				$def['field']  = '';
				$base          = 'q';
			} else {
				$fd = self::field( $type, isset( $f['field'] ) ? sanitize_key( $f['field'] ) : '' );
				if ( ! $fd || ! in_array( $fd['type'], self::field_kinds( $t ), true ) ) {
					continue;
				}
				$def['field']  = $fd['key'];
				$def['fields'] = array();
				// Data link: readable parameter = group slug (?kategorie=…), values only = data type slug.
				$base          = Gruppa_Relations::is_link( $fd )
					? ( Gruppa_Relations::VALUES === $fd['link']['group_slug'] ? $fd['link']['data'] : $fd['link']['group_slug'] )
					: $fd['key'];
			}
			// URL parameter: own or from the field; unique within the group.
			$param = sanitize_key( isset( $f['param'] ) && '' !== $f['param'] ? $f['param'] : $base );
			$param = '' !== $param ? $param : 'f';
			$try   = $param;
			for ( $n = 2; isset( $params[ $try ] ); $n++ ) {
				$try = $param . $n;
			}
			$params[ $try ] = true;
			$def['param']   = $try;
			$s['filters'][] = $def;
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
			'order_index' => Gruppa_DB::next_order( '_filters' ),
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
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/* ======================================================
	 * Options & request values
	 * ==================================================== */

	/**
	 * Choices for select / checkbox: [ readable value => label ].
	 * Relation → slugs of published related items; option fields → their options.
	 */
	public static function options( $type, $def ) {
		$f = self::field( $type, $def['field'] );
		if ( ! $f ) {
			return array();
		}
		global $wpdb;
		switch ( $f['type'] ) {
			case 'relation':
				$rtype = Gruppa_Fields::related_type( $f );
				if ( ! $rtype ) {
					return array();
				}
				$out   = array();
				$group = ! empty( $f['link']['group'] ) ? $wpdb->prepare( ' AND group_id = %d', (int) $f['link']['group'] ) : '';
				foreach ( (array) $wpdb->get_results( 'SELECT id, title, slug FROM `' . Gruppa_DB::data_table( $rtype->slug ) . "` WHERE status = 'publish'{$group} ORDER BY order_index ASC, title ASC" ) as $r ) { // phpcs:ignore
					$out[ '' !== $r->slug ? $r->slug : (string) $r->id ] = $r->title;
				}
				return $out;
			case 'select':
			case 'radio':
			case 'checkbox':
				$out = array();
				foreach ( $f['options'] as $o ) {
					$out[ (string) $o['value'] ] = $o['label'];
				}
				return $out;
			case 'switcher':
				return array( '1' => __( 'Yes', 'gruppa-cms' ), '0' => __( 'No', 'gruppa-cms' ) );
		}
		// Text: distinct values in use.
		$col  = preg_replace( '/[^a-z0-9_]/', '', $f['key'] );
		$vals = (array) $wpdb->get_col( 'SELECT DISTINCT `' . $col . '` FROM `' . Gruppa_DB::data_table( $type->slug ) . "` WHERE status = 'publish' AND `{$col}` <> '' ORDER BY `{$col}` ASC LIMIT 200" ); // phpcs:ignore
		return array_combine( $vals, $vals );
	}

	/** Lowest / highest value of a number field (published items). */
	public static function bounds( $type, $def ) {
		global $wpdb;
		$col = preg_replace( '/[^a-z0-9_]/', '', $def['field'] );
		$row = $wpdb->get_row( 'SELECT MIN(`' . $col . '`) mn, MAX(`' . $col . '`) mx FROM `' . Gruppa_DB::data_table( $type->slug ) . "` WHERE status = 'publish'" ); // phpcs:ignore
		return $row ? array( null === $row->mn ? '' : $row->mn + 0, null === $row->mx ? '' : $row->mx + 0 ) : array( '', '' );
	}

	private static function get_val( $get, $key ) {
		if ( ! isset( $get[ $key ] ) ) {
			return '';
		}
		$v = $get[ $key ];
		if ( is_array( $v ) ) {
			$v = implode( ',', array_filter( $v, 'is_scalar' ) );
		}
		return trim( sanitize_text_field( wp_unslash( (string) $v ) ) );
	}

	/** Current values of a group from the request: [ param => value(s) ]. */
	public static function values( $group, $get = null ) {
		$get = null === $get ? $_GET : $get; // phpcs:ignore
		$out = array();
		foreach ( $group->settings['filters'] as $def ) {
			$p = $def['param'];
			if ( in_array( $def['type'], array( 'range', 'date' ), true ) ) {
				$out[ $p . '_from' ] = self::get_val( $get, $p . '_from' );
				$out[ $p . '_to' ]   = self::get_val( $get, $p . '_to' );
			} elseif ( 'checkbox' === $def['type'] ) {
				$v         = self::get_val( $get, $p );
				$out[ $p ] = '' === $v ? array() : array_values( array_filter( array_map( 'trim', explode( ',', $v ) ), 'strlen' ) );
			} else {
				$out[ $p ] = self::get_val( $get, $p );
			}
		}
		return $out;
	}

	/**
	 * Query rules for Gruppa_Listing::query() from the request.
	 *
	 * @return array rules [ field, op, value | values | fields ]
	 */
	public static function rules( $group, $type, $get = null ) {
		if ( ! $group || $group->type_slug !== $type->slug ) {
			return array();
		}
		$vals  = self::values( $group, $get );
		$rules = array();
		foreach ( $group->settings['filters'] as $def ) {
			$p = $def['param'];
			$f = 'search' === $def['type'] ? null : self::field( $type, $def['field'] );
			switch ( $def['type'] ) {
				case 'search':
					if ( '' !== $vals[ $p ] ) {
						$rules[] = array( 'op' => 'search', 'fields' => $def['fields'], 'value' => $vals[ $p ] );
					}
					break;
				case 'select':
					if ( '' !== $vals[ $p ] && $f ) {
						$v       = Gruppa_Listing::readable_to_stored( $f, array( $vals[ $p ] ) );
						$rules[] = array( 'field' => $def['field'], 'op' => 'any', 'values' => $v );
					}
					break;
				case 'checkbox':
					if ( $vals[ $p ] && $f ) {
						$rules[] = array( 'field' => $def['field'], 'op' => 'any', 'values' => Gruppa_Listing::readable_to_stored( $f, $vals[ $p ] ) );
					}
					break;
				case 'range':
					foreach ( array( 'from' => 'gte', 'to' => 'lte' ) as $end => $op ) {
						$v = str_replace( array( ' ', ',' ), array( '', '.' ), $vals[ $p . '_' . $end ] );
						if ( '' !== $v && is_numeric( $v ) ) {
							$rules[] = array( 'field' => $def['field'], 'op' => $op, 'value' => $v );
						}
					}
					break;
				case 'date':
					foreach ( array( 'from' => 'gte', 'to' => 'lte' ) as $end => $op ) {
						$v = $vals[ $p . '_' . $end ];
						if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
							// created / modified are datetimes: "to" includes the whole day.
							if ( 'to' === $end && in_array( $def['field'], array( 'created', 'modified' ), true ) ) {
								$v .= ' 23:59:59';
							}
							$rules[] = array( 'field' => $def['field'], 'op' => $op, 'value' => $v );
						}
					}
					break;
			}
		}
		return $rules;
	}

	/* ======================================================
	 * Render
	 * ==================================================== */

	public static function init() {
		add_shortcode( 'gruppa_filters', function ( $atts ) {
			$a = shortcode_atts( array( 'group' => '', 'id' => '' ), $atts, 'gruppa_filters' );
			return self::render( '' !== $a['group'] ? $a['group'] : $a['id'] );
		} );
		add_action( 'wp_enqueue_scripts', function () {
			wp_register_script( 'gruppa-filters', GRUPPA_CMS_URL . 'assets/gruppa-filters.js', array(), GRUPPA_CMS_VERSION, true );
		} );
	}

	private static function label( $id, $text ) {
		return '' !== $text ? '<label class="gruppa-form-label" for="' . esc_attr( $id ) . '">' . esc_html( $text ) . '</label>' : '';
	}

	/** @param array $o preview: true = Elementor editor (no live listing needed) */
	public static function render( $ref, $o = array() ) {
		$group = '' !== (string) $ref ? self::get_by( $ref ) : null;
		$type  = $group ? Gruppa_Types::get_by_slug( $group->type_slug ) : null;
		if ( ! $group || 'publish' !== $group->status || ! $type ) {
			return current_user_can( 'edit_posts' ) ? '<div class="gruppa-listing-message">Gruppa Search: ' . ( $ref ? 'group not found or not published.' : 'select a filter group.' ) . '</div>' : '';
		}
		wp_enqueue_style( 'gruppa-form' );
		wp_enqueue_style( 'gruppa-listing' );
		wp_enqueue_script( 'gruppa-filters' );
		wp_enqueue_script( 'gruppa-listing' );
		self::$count++;
		$s    = $group->settings;
		$vals = self::values( $group );
		$uid  = 'gfl-' . (int) $group->id . '-' . self::$count;

		ob_start();
		printf(
			'<div class="gruppa-form gruppa-filters-wrap"><form class="gruppa-filters" method="get" data-group="%d" data-type="%s" data-apply="%s" data-params="%s" role="search">',
			(int) $group->id, esc_attr( $group->type_slug ), esc_attr( $s['apply'] ), esc_attr( implode( ',', self::param_names( $group ) ) )
		);
		echo '<div class="gruppa-form-fields">';
		foreach ( $s['filters'] as $i => $def ) {
			$id = $uid . '-' . $i;
			$p  = $def['param'];
			printf( '<div class="gruppa-form-field gruppa-filter gruppa-filter--%s" data-param="%s">', esc_attr( $def['type'] ), esc_attr( $p ) );
			switch ( $def['type'] ) {
				case 'search':
					echo self::label( $id, $def['label'] ); // phpcs:ignore
					printf( '<input type="search" id="%s" name="%s" value="%s" placeholder="%s" autocomplete="off">', esc_attr( $id ), esc_attr( $p ), esc_attr( $vals[ $p ] ), esc_attr( $def['placeholder'] ) );
					break;

				case 'select':
					echo self::label( $id, $def['label'] ); // phpcs:ignore
					printf( '<select id="%s" name="%s"><option value="">%s</option>', esc_attr( $id ), esc_attr( $p ), esc_html( Gruppa_I18n::text( $def['all_label'], 'All', array( 'Všetky' ) ) ) );
					foreach ( self::options( $type, $def ) as $v => $l ) {
						printf( '<option value="%s" %s>%s</option>', esc_attr( $v ), selected( $vals[ $p ], (string) $v, false ), esc_html( $l ) );
					}
					echo '</select>';
					break;

				case 'checkbox':
					if ( '' !== $def['label'] ) {
						echo '<span class="gruppa-form-label">' . esc_html( $def['label'] ) . '</span>';
					}
					echo '<fieldset>';
					foreach ( self::options( $type, $def ) as $v => $l ) {
						printf(
							'<label><input type="checkbox" name="%s[]" value="%s" %s> %s</label>',
							esc_attr( $p ), esc_attr( $v ), checked( in_array( (string) $v, $vals[ $p ], true ), true, false ), esc_html( $l )
						);
					}
					echo '</fieldset>';
					break;

				case 'range':
				case 'date':
					if ( '' !== $def['label'] ) {
						echo '<span class="gruppa-form-label">' . esc_html( $def['label'] ) . '</span>';
					}
					$is_date = 'date' === $def['type'];
					list( $mn, $mx ) = $is_date ? array( '', '' ) : self::bounds( $type, $def );
					echo '<div class="gruppa-filter-range">';
					foreach ( array( 'from' => Gruppa_I18n::text( $def['from_label'], 'From', array( 'Od' ) ), 'to' => Gruppa_I18n::text( $def['to_label'], 'To', array( 'Do' ) ) ) as $end => $ph ) {
						printf(
							'<input type="%s" name="%s" value="%s" placeholder="%s" aria-label="%s"%s%s>',
							$is_date ? 'date' : 'number', esc_attr( $p . '_' . $end ), esc_attr( $vals[ $p . '_' . $end ] ),
							esc_attr( $is_date ? '' : trim( $ph . ' ' . ( 'from' === $end ? $mn : $mx ) ) ), esc_attr( trim( $def['label'] . ' ' . $ph ) ),
							$is_date ? '' : ' step="' . esc_attr( '' !== $def['step'] ? $def['step'] : 'any' ) . '" inputmode="decimal"',
							$is_date ? '' : ( '' !== $mn ? ' min="' . esc_attr( $mn ) . '"' : '' ) . ( '' !== $mx ? ' max="' . esc_attr( $mx ) . '"' : '' )
						);
					}
					echo '</div>';
					break;
			}
			echo '</div>';
		}
		echo '</div>';

		$buttons = '';
		if ( 'button' === $s['apply'] ) {
			$buttons .= '<button type="submit" class="gruppa-form-button">' . esc_html( Gruppa_I18n::text( $s['button_text'], 'Filter', array( 'Filtrovať' ) ) ) . '</button>';
		}
		if ( $s['reset'] ) {
			$buttons .= '<button type="button" class="gruppa-filters-reset">' . esc_html( Gruppa_I18n::text( $s['reset_text'], 'Reset filters', array( 'Zrušiť filtre' ) ) ) . '</button>';
		}
		if ( $buttons ) {
			echo '<div class="gruppa-form-submit gruppa-filters-actions" data-align="left">' . $buttons . '</div>'; // phpcs:ignore
		}
		echo '</form></div>';
		return ob_get_clean();
	}

	/** All URL parameters a group uses. */
	public static function param_names( $group ) {
		$out = array();
		foreach ( $group->settings['filters'] as $def ) {
			if ( in_array( $def['type'], array( 'range', 'date' ), true ) ) {
				$out[] = $def['param'] . '_from';
				$out[] = $def['param'] . '_to';
			} else {
				$out[] = $def['param'];
			}
		}
		return $out;
	}
}
