<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gruppa Visibility — Advanced tab of every Elementor container, section, column and widget.
 *
 * Show / hide the element by rules: a field of the current item (also inside a Listing card),
 * the current post / page ID, the visitor (logged in, user ID, role) or a URL parameter. An element that is hidden is not rendered
 * at all (no empty gap, nothing in the page source). In the editor everything stays visible.
 */
class Gruppa_Visibility {

	const OPS = array(
		'eq'        => 'is',
		'neq'       => 'is not',
		'contains'  => 'contains',
		'gt'        => 'greater than',
		'lt'        => 'less than',
		'empty'     => 'is empty',
		'not_empty' => 'exists (is not empty)',
		'on'        => 'is enabled (switcher)',
		'off'       => 'is disabled (switcher)',
	);

	/** Operators for IDs (post, user). */
	const ID_OPS = array(
		'eq'  => 'is',
		'neq' => 'is not',
	);

	public static function init() {
		// Controls.
		add_action( 'elementor/element/common/_section_style/after_section_end', array( __CLASS__, 'controls' ), 10, 2 );
		add_action( 'elementor/element/section/section_advanced/after_section_end', array( __CLASS__, 'controls' ), 10, 2 );
		add_action( 'elementor/element/column/section_advanced/after_section_end', array( __CLASS__, 'controls' ), 10, 2 );
		add_action( 'elementor/element/container/section_layout/after_section_end', array( __CLASS__, 'controls' ), 10, 2 );
		// Rendering.
		foreach ( array( 'widget', 'section', 'column', 'container' ) as $el ) {
			add_filter( 'elementor/frontend/' . $el . '/should_render', array( __CLASS__, 'should_render' ), 10, 2 );
		}
		// Elementor element cache (3.22+): an element with rules depends on the visitor / page, never cache it.
		add_filter( 'elementor/element/is_dynamic_content', array( __CLASS__, 'is_dynamic' ), 10, 2 );
	}

	public static function is_dynamic( $dynamic, $raw ) {
		return $dynamic || ( isset( $raw['settings']['gv_enable'] ) && 'yes' === $raw['settings']['gv_enable'] );
	}

	public static function controls( $element, $args ) {
		$cm = '\Elementor\Controls_Manager';
		if ( $element->get_controls( 'gv_enable' ) ) {
			return; // Already added (several hooks for one element type).
		}
		$element->start_controls_section( 'gruppa_visibility', array(
			'label' => 'Gruppa Visibility',
			'tab'   => $cm::TAB_ADVANCED,
		) );

		$element->add_control( 'gv_enable', array(
			'label'        => 'Enable',
			'type'         => $cm::SWITCHER,
			'return_value' => 'yes',
		) );
		$element->add_control( 'gv_action', array(
			'label'     => 'Action',
			'type'      => $cm::SELECT,
			'default'   => 'show',
			'options'   => array( 'show' => 'Show when', 'hide' => 'Hide when' ),
			'condition' => array( 'gv_enable' => 'yes' ),
		) );
		$element->add_control( 'gv_match', array(
			'label'     => 'Rules',
			'type'      => $cm::SELECT,
			'default'   => 'all',
			'options'   => array( 'all' => 'All rules match', 'any' => 'Any rule matches' ),
			'condition' => array( 'gv_enable' => 'yes' ),
		) );

		$groups = array( array( 'label' => '', 'options' => array( '' => '— Select field —' ) ) );
		foreach ( Gruppa_Render::field_groups() as $g ) {
			$groups[] = array( 'label' => $g['label'], 'options' => $g['options'] );
		}
		$roles = array( '' => '— Select role —' );
		foreach ( wp_roles()->get_names() as $k => $n ) {
			$roles[ $k ] = translate_user_role( $n );
		}

		$rep = new \Elementor\Repeater();
		$rep->add_control( 'source', array(
			'label'   => 'Check',
			'type'    => $cm::SELECT,
			'default' => 'field',
			'options' => array(
				'field'  => 'Field of the current item',
				'post'   => 'Post / page ID',
				'login'  => 'Visitor is logged in',
				'user'   => 'User ID',
				'role'   => 'Visitor role',
				'param'  => 'URL parameter',
			),
		) );
		$rep->add_control( 'id_op', array(
			'label'     => 'Condition',
			'type'      => $cm::SELECT,
			'default'   => 'eq',
			'options'   => self::ID_OPS,
			'condition' => array( 'source' => array( 'post', 'user' ) ),
		) );
		$rep->add_control( 'ids', array(
			'label'       => 'ID',
			'type'        => $cm::TEXT,
			'label_block' => true,
			'placeholder' => 'e.g. 12 or 12, 15',
			'description' => 'Several IDs: comma separated (any of them). Post / page = the page being shown (in a Listing card: the page of the card item).',
			'condition'   => array( 'source' => array( 'post', 'user' ) ),
		) );
		$rep->add_control( 'field', array(
			'label'       => 'Field',
			'type'        => $cm::SELECT,
			'groups'      => $groups,
			'default'     => '',
			'label_block' => true,
			'condition'   => array( 'source' => 'field' ),
		) );
		$rep->add_control( 'param', array(
			'label'     => 'Parameter',
			'type'      => $cm::TEXT,
			'placeholder' => 'e.g. typ',
			'condition' => array( 'source' => 'param' ),
		) );
		$rep->add_control( 'role', array(
			'label'     => 'Role',
			'type'      => $cm::SELECT,
			'default'   => '',
			'options'   => $roles,
			'condition' => array( 'source' => 'role' ),
		) );
		$rep->add_control( 'login', array(
			'label'     => 'Logged in',
			'type'      => $cm::SELECT,
			'default'   => 'yes',
			'options'   => array( 'yes' => 'Yes', 'no' => 'No' ),
			'condition' => array( 'source' => 'login' ),
		) );
		$rep->add_control( 'op', array(
			'label'     => 'Condition',
			'type'      => $cm::SELECT,
			'default'   => 'eq',
			'options'   => self::OPS,
			'condition' => array( 'source' => array( 'field', 'param' ) ),
		) );
		$rep->add_control( 'value', array(
			'label'       => 'Value',
			'type'        => $cm::TEXT,
			'label_block' => true,
			'placeholder' => 'e.g. Akcia, akcia or 5',
			'description' => 'Option value or label, a linked value title / slug / ID, a number. Several values: comma separated (any of them).',
			'condition'   => array( 'source' => array( 'field', 'param' ), 'op!' => array( 'empty', 'not_empty', 'on', 'off' ) ),
		) );

		$element->add_control( 'gv_rules', array(
			'label'         => 'Rules',
			'type'          => $cm::REPEATER,
			'fields'        => $rep->get_controls(),
			'default'       => array( array( 'source' => 'field', 'op' => 'eq' ) ),
			'title_field'   => '{{{ source === "field" ? ( field.split(":")[1] || "field" ) + " " + op + " " + ( value || "" ) : ( source === "param" ? "?" + param + " " + op + " " + ( value || "" ) : ( source === "role" ? "role " + role : ( source === "post" || source === "user" ? source + " ID " + ( id_op === "neq" ? "is not " : "is " ) + ( ids || "" ) : "logged in: " + login ) ) ) }}}',
			'prevent_empty' => false,
			'condition'     => array( 'gv_enable' => 'yes' ),
		) );
		$element->add_control( 'gv_note', array(
			'type'            => $cm::RAW_HTML,
			'raw'             => 'In the editor the element is always visible. "Current item" = the item of the page, or the item of the card inside a Gruppa Listing template.',
			'content_classes' => 'elementor-descriptor',
			'condition'       => array( 'gv_enable' => 'yes' ),
		) );
		$element->end_controls_section();
	}

	public static function should_render( $should, $element ) {
		if ( ! $should ) {
			return $should;
		}
		$s = $element->get_settings();
		if ( empty( $s['gv_enable'] ) || 'yes' !== $s['gv_enable'] || empty( $s['gv_rules'] ) ) {
			return $should;
		}
		if ( class_exists( '\Elementor\Plugin' ) && ( \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode() ) ) {
			return true;
		}
		$pass = self::passes( (array) $s['gv_rules'], isset( $s['gv_match'] ) ? $s['gv_match'] : 'all' );
		return 'hide' === ( isset( $s['gv_action'] ) ? $s['gv_action'] : 'show' ) ? ! $pass : $pass;
	}

	/** Do the rules match? Public for tests and add-ons. */
	public static function passes( $rules, $match = 'all' ) {
		$any = 'any' === $match;
		$n   = 0;
		foreach ( $rules as $r ) {
			$ok = self::rule( $r );
			if ( null === $ok ) {
				continue; // Incomplete rule.
			}
			$n++;
			if ( $any && $ok ) {
				return true;
			}
			if ( ! $any && ! $ok ) {
				return false;
			}
		}
		return $n ? ! $any : true;
	}

	/** @return bool|null null = rule not set up */
	private static function rule( $r ) {
		$src = isset( $r['source'] ) ? $r['source'] : 'field';
		$op  = isset( $r['op'] ) && isset( self::OPS[ $r['op'] ] ) ? $r['op'] : 'eq';
		$val = isset( $r['value'] ) ? trim( (string) $r['value'] ) : '';
		switch ( $src ) {
			case 'login':
				return ( 'no' === ( isset( $r['login'] ) ? $r['login'] : 'yes' ) ) ? ! is_user_logged_in() : is_user_logged_in();
			case 'post':
			case 'user':
				$ids = array_filter( array_map( 'absint', explode( ',', isset( $r['ids'] ) ? (string) $r['ids'] : '' ) ) );
				if ( ! $ids ) {
					return null;
				}
				if ( 'user' === $src ) {
					$cur = get_current_user_id();
				} else {
					$cur = (int) get_the_ID();
					$cur = $cur ? $cur : (int) get_queried_object_id();
				}
				$hit = $cur && in_array( $cur, $ids, true );
				return 'neq' === ( isset( $r['id_op'] ) ? $r['id_op'] : 'eq' ) ? ! $hit : $hit;
			case 'role':
				if ( empty( $r['role'] ) ) {
					return null;
				}
				$u = wp_get_current_user();
				return $u && $u->ID && in_array( $r['role'], (array) $u->roles, true );
			case 'param':
				if ( empty( $r['param'] ) ) {
					return null;
				}
				$p = sanitize_key( $r['param'] );
				$v = isset( $_GET[ $p ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $p ] ) ) : ''; // phpcs:ignore
				return self::compare( '' === $v ? array() : array_map( 'trim', explode( ',', $v ) ), $op, $val );
			case 'field':
				if ( empty( $r['field'] ) ) {
					return null;
				}
				list( $tslug, $key ) = Gruppa_Render::parse_ref( $r['field'] );
				$found = $tslug ? Gruppa_Render::item( $tslug, 'current', '', (int) get_the_ID() ) : null;
				if ( ! $found ) {
					return in_array( $op, array( 'empty', 'neq', 'off' ), true ); // No current item: nothing equals, everything is empty.
				}
				return self::compare( self::values( $found[0], $found[1], $key ), $op, $val );
		}
		return null;
	}

	/** Every form a field value can be compared in: stored values, labels, linked titles / slugs / IDs. */
	private static function values( $type, $item, $key ) {
		$out = array();
		if ( isset( Gruppa_Render::SYSTEM[ $key ] ) ) {
			$v = isset( $item->{$key} ) ? (string) $item->{$key} : '';
			return '' === $v ? array() : array( $v );
		}
		$f = Gruppa_Render::field_def( $type, $key );
		if ( ! $f ) {
			return $out;
		}
		$raw = isset( $item->{$key} ) ? $item->{$key} : null;
		if ( 'relation' === $f['type'] ) {
			foreach ( Gruppa_Fields::relation_ids( $f, $raw ) as $id ) {
				$out[] = (string) $id;
			}
			foreach ( Gruppa_Render::related_items( $f, $raw ) as $rel ) {
				$out[] = (string) $rel->title;
				$out[] = (string) $rel->slug;
			}
			return array_values( array_filter( $out, 'strlen' ) );
		}
		if ( 'switcher' === $f['type'] ) {
			return $raw ? array( '1', 'yes', 'true' ) : array();
		}
		$list = Gruppa_Fields::decode( $f, $raw );
		$list = is_array( $list ) ? $list : ( null === $list || '' === $list ? array() : array( $list ) );
		foreach ( $list as $v ) {
			if ( is_scalar( $v ) && '' !== (string) $v ) {
				$out[] = (string) $v;
				foreach ( (array) ( isset( $f['options'] ) ? $f['options'] : array() ) as $o ) {
					if ( (string) $o['value'] === (string) $v ) {
						$out[] = (string) $o['label'];
					}
				}
			}
		}
		return $out;
	}

	private static function lower( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $s ) : strtolower( (string) $s );
	}

	private static function compare( $have, $op, $want ) {
		$have = array_map( array( __CLASS__, 'lower' ), (array) $have );
		switch ( $op ) {
			case 'on':
				return (bool) array_intersect( $have, array( '1', 'yes', 'true', 'on' ) );
			case 'off':
				return ! array_intersect( $have, array( '1', 'yes', 'true', 'on' ) );
			case 'empty':
				return ! $have;
			case 'not_empty':
				return (bool) $have;
			case 'gt':
			case 'lt':
				if ( ! $have || ! is_numeric( $have[0] ) || ! is_numeric( $want ) ) {
					return false;
				}
				return 'gt' === $op ? (float) $have[0] > (float) $want : (float) $have[0] < (float) $want;
		}
		$wants = array_filter( array_map( 'trim', explode( ',', self::lower( $want ) ) ), 'strlen' );
		if ( ! $wants ) {
			return null === $want ? false : ( 'neq' === $op ? (bool) $have : ! $have );
		}
		$hit = false;
		foreach ( $wants as $w ) {
			foreach ( $have as $h ) {
				if ( 'contains' === $op ? false !== strpos( $h, $w ) : $h === $w ) {
					$hit = true;
					break 2;
				}
			}
		}
		return 'neq' === $op ? ! $hit : $hit;
	}
}
