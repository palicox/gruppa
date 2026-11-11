<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

/**
 * Shared controls: field + item source.
 */
trait Gruppa_Tag_Common {

	public function get_group() {
		return 'gruppa';
	}

	public function is_settings_required() {
		return true;
	}

	/** Field types offered by this tag. */
	abstract protected function field_types();

	/**
	 * Every control of a Gruppa tag gets the class gruppa-tag-ctrl: labels in one
	 * column, inputs on one vertical line (assets/editor.css).
	 */
	public function add_control( $id, array $args, $options = array() ) {
		$args['classes'] = trim( ( isset( $args['classes'] ) ? $args['classes'] : '' ) . ' gruppa-tag-ctrl' );
		return parent::add_control( $id, $args, $options );
	}

	protected function register_common_controls() {
		// Placeholder first: without an empty option Elementor shows a blank select
		// and the first real field looks selected while it is not.
		$groups = array(
			array(
				'label'   => '',
				'options' => array( '' => '— Select field —' ),
			),
		);
		foreach ( Gruppa_Render::field_groups( $this->field_types() ) as $g ) {
			$groups[] = array( 'label' => $g['label'], 'options' => $g['options'] );
		}

		$this->add_control( 'field', array(
			'label'       => 'Field',
			'type'        => Controls_Manager::SELECT,
			'groups'      => $groups,
			'default'     => '',
		) );

		$this->add_control( 'source', array(
			'label'   => 'Item',
			'type'    => Controls_Manager::SELECT,
			'default' => 'current',
			'options' => array(
				'current' => 'Current',
				'link'    => 'Linked item (through a data link)',
				'id'      => 'By ID',
				'slug'    => 'By slug',
			),
		) );

		// Linked item: e.g. field Klienti › Logo of the client picked in the current Projekt.
		$via = array( '' => '— Select link —' );
		foreach ( Gruppa_Types::all( 'publish' ) as $t ) {
			foreach ( Gruppa_Types::fields_all( $t ) as $f ) {
				if ( 'relation' === $f['type'] ) {
					$rt = Gruppa_Fields::related_type( $f );
					$via[ $t->slug . ':' . $f['key'] ] = $t->name . ' › ' . $f['label'] . ( $rt ? ' → ' . $rt->name : '' );
				}
			}
		}
		$this->add_control( 'via', array(
			'label'       => 'Link',
			'type'        => Controls_Manager::SELECT,
			'options'     => $via,
			'default'     => '',
			'label_block' => true,
			'description' => 'The current item\'s link (e.g. Projekty › Klient). Pick the field above from the linked type (e.g. Klienti › Logo). Several linked items: the first one.',
			'condition'   => array( 'source' => 'link' ),
		) );

		$this->add_control( 'item', array(
			'label'     => 'ID / slug',
			'type'      => Controls_Manager::TEXT,
			'condition' => array( 'source!' => 'current' ),
		) );
	}

	/** @return array|null [ type, item, key ] */
	protected function resolve() {
		list( $type_slug, $key ) = Gruppa_Render::parse_ref( $this->get_settings( 'field' ) );
		if ( ! $type_slug ) {
			return null;
		}
		if ( 'link' === $this->get_settings( 'source' ) ) {
			$found = Gruppa_Render::linked_item( (string) $this->get_settings( 'via' ), $type_slug );
		} else {
			$found = Gruppa_Render::item( $type_slug, $this->get_settings( 'source' ), $this->get_settings( 'item' ) );
		}
		return $found ? array( $found[0], $found[1], $key ) : null;
	}
}
