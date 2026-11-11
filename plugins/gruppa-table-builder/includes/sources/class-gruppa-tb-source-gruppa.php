<?php
defined( 'ABSPATH' ) || exit;

/**
 * Source: Gruppa CMS content types (Content + Data). Uses the Gruppa CMS API —
 * Listing query (groups, related to current item, only my items, Search Filters) and Gruppa_Render.
 */
class Gruppa_TB_Source_Gruppa extends Gruppa_TB_Source {

	const SYSTEM = array(
		'title'    => 'Title',
		'slug'     => 'Slug',
		'id'       => 'ID',
		'created'  => 'Created',
		'modified' => 'Modified',
	);

	private $tcache = array();

	public function id() {
		return 'gruppa';
	}

	public function label() {
		return 'Gruppa CMS';
	}

	public function available() {
		return class_exists( 'Gruppa_Types' ) && class_exists( 'Gruppa_Fields' );
	}

	public function has_filters() {
		return true;
	}

	/** Gruppa CMS type object. */
	public function type( $slug ) {
		if ( ! $this->available() || '' === (string) $slug ) {
			return null;
		}
		if ( ! array_key_exists( $slug, $this->tcache ) ) {
			$this->tcache[ $slug ] = Gruppa_Types::get_by_slug( $slug );
		}
		return $this->tcache[ $slug ];
	}

	public function types() {
		$out = array();
		if ( ! $this->available() ) {
			return $out;
		}
		$kinds = method_exists( 'Gruppa_Types', 'kinds' ) ? Gruppa_Types::kinds() : array();
		foreach ( Gruppa_Types::all() as $t ) {
			$out[ $t->slug ] = $t->name . ( isset( $kinds[ $t->kind ] ) ? ' (' . $kinds[ $t->kind ] . ')' : '' );
		}
		return $out;
	}

	public function exists( $slug ) {
		return (bool) $this->type( $slug );
	}

	public function type_name( $slug ) {
		$t = $this->type( $slug );
		return $t ? $t->name : $slug;
	}

	public function columns( $slug ) {
		$type = $this->type( $slug );
		$out  = array();
		if ( ! $type ) {
			return $out;
		}
		foreach ( self::SYSTEM as $k => $l ) {
			$out[ $k ] = self::meta( $l, in_array( $k, array( 'created', 'modified' ), true ) ? 'date' : ( 'id' === $k ? 'number' : 'text' ), 'system' );
		}
		foreach ( Gruppa_Types::fields_all( $type ) as $f ) {
			if ( in_array( $f['type'], array( 'html' ), true ) || Gruppa_Fields::is_password( $f ) ) {
				continue;
			}
			$out[ $f['key'] ] = self::meta( $f['label'], $f['type'], $f['type'] );
		}
		return $out;
	}

	public function repeaters( $slug ) {
		$type = $this->type( $slug );
		$out  = array();
		foreach ( $type ? $type->fields : array() as $f ) {
			if ( 'repeater' !== $f['type'] ) {
				continue;
			}
			$sub = array();
			foreach ( (array) $f['sub'] as $sd ) {
				$opts = array();
				foreach ( isset( $sd['options'] ) ? (array) $sd['options'] : array() as $o ) {
					if ( isset( $o['value'] ) ) {
						$opts[ (string) $o['value'] ] = isset( $o['label'] ) ? $o['label'] : $o['value'];
					}
				}
				$sub[ $sd['key'] ] = self::meta( $sd['label'], $sd['type'], $sd['type'], $opts );
			}
			$out[ $f['key'] ] = array( 'label' => $f['label'], 'sub' => $sub );
		}
		return $out;
	}

	public function order_options( $slug ) {
		$order = array( array( 'order_index', 'Order (drag & drop)' ), array( 'title', 'Title' ), array( 'created', 'Created' ), array( 'modified', 'Modified' ), array( 'id', 'ID' ), array( 'rand', 'Random' ) );
		foreach ( $this->columns( $slug ) as $k => $m ) {
			if ( ! isset( self::SYSTEM[ $k ] ) && in_array( $m['type'], array( 'text', 'number', 'date', 'time', 'select', 'radio', 'switcher' ), true ) ) {
				$order[] = array( $k, $m['label'] );
			}
		}
		return $order;
	}

	public function context_options( $slug ) {
		$type = $this->type( $slug );
		$out  = array();
		foreach ( $type ? Gruppa_Types::fields_all( $type ) : array() as $f ) {
			if ( 'relation' === $f['type'] ) {
				$out[] = array( $f['key'], $f['label'] . ( ! empty( $f['link'] ) ? ' (' . $f['link']['data_name'] . ')' : '' ) );
			}
		}
		return $out;
	}

	public function groups( $slug ) {
		$type = $this->type( $slug );
		$out  = array();
		if ( $type && 'data' === $type->kind && 'groups' === $type->structure ) {
			foreach ( $type->groups as $g ) {
				$out[] = array( (int) $g['id'], $g['name'] );
			}
		}
		return $out;
	}

	public function query( $slug, $s, $page, $per ) {
		$type    = $this->type( $slug );
		$listing = (object) array(
			'id'       => 0,
			'settings' => wp_parse_args(
				array(
					'orderby'       => $s['orderby'],
					'order'         => $s['order'],
					'per_page'      => $per,
					'group'         => $s['group'],
					'context_field' => $s['context_field'],
					'only_mine'     => (bool) $s['only_mine'],
				),
				Gruppa_Listing::defaults()
			),
		);
		return Gruppa_Listing::query( $listing, $type, array( 'page' => $page, 'per_page' => $per, 'filters' => $this->filter_rules( $type ) ) );
	}

	/** Search Filters of the same type whose parameters are in the URL (the page reloads with them). */
	private function filter_rules( $type ) {
		if ( ! class_exists( 'Gruppa_Filters' ) || empty( $_GET ) ) { // phpcs:ignore
			return array();
		}
		foreach ( Gruppa_Filters::all( 'publish' ) as $g ) {
			if ( $g->type_slug === $type->slug && array_intersect( Gruppa_Filters::param_names( $g ), array_keys( $_GET ) ) ) { // phpcs:ignore
				return Gruppa_Filters::rules( $g, $type );
			}
		}
		return array();
	}

	public function repeater_rows( $slug, $s ) {
		$type     = $this->type( $slug );
		$ref_item = Gruppa_Render::item( $type->slug, $s['item_source'], $s['item_value'], 'current' === $s['item_source'] ? (int) get_the_ID() : 0 );
		if ( ! $ref_item ) {
			return 'current' === $s['item_source'] ? null : array();
		}
		$f = Gruppa_Types::field( $type, $s['field'] );
		if ( ! $f || 'repeater' !== $f['type'] ) {
			return array();
		}
		$item = $ref_item[1];
		return (array) Gruppa_Fields::decode( $f, isset( $item->{$f['key']} ) ? $item->{$f['key']} : '' );
	}

	public function value( $slug, $item, $key ) {
		return isset( $item->{$key} ) ? $item->{$key} : '';
	}

	public function text( $slug, $item, $key, $meta ) {
		if ( isset( self::SYSTEM[ $key ] ) ) {
			return esc_html( (string) $this->value( $slug, $item, $key ) );
		}
		return Gruppa_Render::text( $this->type( $slug ), $item, $key );
	}

	public function url( $slug, $item, $key ) {
		return (string) Gruppa_Render::url( $this->type( $slug ), $item, $key );
	}

	public function media( $slug, $item, $key ) {
		$ids = Gruppa_Render::media_ids( $this->type( $slug ), $item, $key );
		return $ids ? $ids : self::media_list( $this->value( $slug, $item, $key ) );
	}
}
