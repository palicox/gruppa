<?php
defined( 'ABSPATH' ) || exit;

/**
 * Elementor widget: Gruppa Listing — items of a Listing in a grid.
 */
class Gruppa_Widget_Listing extends \Elementor\Widget_Base {

	public function get_name() {
		return 'gruppa-listing';
	}

	public function get_title() {
		return 'Gruppa Listing';
	}

	public function get_icon() {
		return class_exists( 'Gruppa_Elementor' ) ? Gruppa_Elementor::icon( 'listing' ) : 'eicon-posts-grid';
	}

	public function get_categories() {
		return array( Gruppa_Elementor::CATEGORY );
	}

	public function get_keywords() {
		return array( 'gruppa', 'listing', 'loop', 'grid', 'zoznam' );
	}

	public function get_style_depends() {
		return array( 'gruppa-listing' );
	}

	public function get_script_depends() {
		return array( 'gruppa-listing' );
	}

	protected function register_controls() {
		$options = array( '' => '— Select listing —' );
		foreach ( Gruppa_Listing::all() as $l ) {
			$options[ $l->slug ] = $l->name . ( 'publish' === $l->status ? '' : ' (draft)' );
		}
		$grid = '{{WRAPPER}} .gruppa-listing';

		$this->start_controls_section( 'content', array( 'label' => 'Listing' ) );
		$this->add_control( 'listing', array(
			'label'       => 'Listing',
			'type'        => \Elementor\Controls_Manager::SELECT,
			'options'     => $options,
			'default'     => '',
			'label_block' => true,
			'description' => 'Create listings and design their item template in Gruppa CMS → Listings.',
		) );
		$groups = array( '' => '— None —' );
		if ( class_exists( 'Gruppa_Filters' ) ) {
			foreach ( Gruppa_Filters::all() as $g ) {
				$groups[ $g->slug ] = $g->name . ( 'publish' === $g->status ? '' : ' (draft)' );
			}
		}
		$this->add_control( 'filters', array(
			'label'       => 'Filter group',
			'type'        => \Elementor\Controls_Manager::SELECT,
			'options'     => $groups,
			'default'     => '',
			'label_block' => true,
			'description' => 'Filter group (same entity) that controls this listing. Put the Gruppa Search widget with the same group anywhere on the page.',
		) );
		$this->add_control( 'per_page', array(
			'label'       => 'Items per page',
			'type'        => \Elementor\Controls_Manager::NUMBER,
			'min'         => 1,
			'max'         => Gruppa_Listing::MAX_PAGE,
			'description' => 'Empty = value from the listing.',
		) );
		$this->add_responsive_control( 'columns', array(
			'label'           => 'Columns',
			'type'            => \Elementor\Controls_Manager::NUMBER,
			'min'             => 1,
			'max'             => 8,
			'default'         => 3,
			'tablet_default'  => 2,
			'mobile_default'  => 1,
			'selectors'       => array( $grid => '--gl-cols: {{VALUE}};' ),
			'separator'       => 'before',
		) );
		$this->add_responsive_control( 'gap_x', array(
			'label'      => 'Column gap',
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em', 'rem' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
			'selectors'  => array( $grid => '--gl-gap-x: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'gap_y', array(
			'label'      => 'Row gap',
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em', 'rem' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
			'selectors'  => array( $grid => '--gl-gap-y: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'equal_height', array(
			'label'        => 'Equal height',
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
			'selectors'    => array( $grid . ' .gruppa-listing-item > .elementor, ' . $grid . ' .gruppa-listing-item > .elementor > .e-con, ' . $grid . ' .gruppa-listing-item > .elementor > .elementor-section' => 'height: 100%;' ),
		) );
		$this->add_control( 'pagination', array(
			'label'     => 'Pagination',
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'numbers',
			'options'   => array( 'none' => 'None', 'numbers' => 'Numbers', 'load_more' => 'Load more button' ),
			'separator' => 'before',
		) );
		$this->add_control( 'load_more', array(
			'label'     => 'Button text',
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => 'Načítať viac',
			'condition' => array( 'pagination' => 'load_more' ),
		) );
		$this->add_control( 'empty_text', array(
			'label'       => 'Empty text',
			'type'        => \Elementor\Controls_Manager::TEXT,
			'description' => 'Empty = text from the listing.',
		) );
		$this->end_controls_section();

		/* Style: pagination / load more */
		$p  = '{{WRAPPER}} .gruppa-listing .gruppa-listing-pagination .page-numbers';
		$pa = '{{WRAPPER}} .gruppa-listing .gruppa-listing-pagination .page-numbers.current';
		$ph = '{{WRAPPER}} .gruppa-listing .gruppa-listing-pagination a.page-numbers:hover';
		$b  = '{{WRAPPER}} .gruppa-listing .gruppa-listing-more .gruppa-listing-more-button';
		$bh = $b . ':hover, ' . $b . ':focus';

		$this->start_controls_section( 'style_pagination', array( 'label' => 'Pagination', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'pag_align', array(
			'label'     => 'Alignment',
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'flex-start' => array( 'title' => 'Left', 'icon' => 'eicon-text-align-left' ),
				'center'     => array( 'title' => 'Center', 'icon' => 'eicon-text-align-center' ),
				'flex-end'   => array( 'title' => 'Right', 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( $grid => '--gl-pag-align: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'pag_space', array(
			'label'      => 'Space above',
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
			'selectors'  => array( '{{WRAPPER}} .gruppa-listing .gruppa-listing-pagination, {{WRAPPER}} .gruppa-listing .gruppa-listing-more' => 'margin-top: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'pag_typography', 'selector' => $p . ', ' . $b, 'separator' => 'before' ) );

		$this->start_controls_tabs( 'pag_tabs' );
		foreach ( array( 'normal' => 'Normal', 'hover' => 'Hover', 'active' => 'Active' ) as $state => $title ) {
			$sel = 'normal' === $state ? $p . ', ' . $b : ( 'hover' === $state ? $ph . ', ' . $bh : $pa );
			$this->start_controls_tab( 'pag_tab_' . $state, array( 'label' => $title ) );
			$this->add_control( 'pag_' . $state . '_color', array( 'label' => 'Text color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( $sel => 'color: {{VALUE}};' ) ) );
			$this->add_control( 'pag_' . $state . '_bg', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( $sel => 'background-color: {{VALUE}};' ) ) );
			$this->add_control( 'pag_' . $state . '_border', array( 'label' => 'Border color', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( $sel => 'border-color: {{VALUE}};' ) ) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array( 'name' => 'pag_border', 'selector' => $p . ', ' . $b, 'separator' => 'before' ) );
		$this->add_responsive_control( 'pag_radius', array(
			'label'      => 'Border radius',
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%', 'em' ),
			'selectors'  => array( $p . ', ' . $b => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'pag_padding', array(
			'label'      => 'Padding',
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( $p . ', ' . $b => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo Gruppa_Listing::render( isset( $s['listing'] ) ? $s['listing'] : '', array( // phpcs:ignore
			'per_page'   => isset( $s['per_page'] ) ? $s['per_page'] : '',
			'pagination' => isset( $s['pagination'] ) ? $s['pagination'] : 'numbers',
			'load_more'  => ! empty( $s['load_more'] ) ? $s['load_more'] : __( 'Load more', 'gruppa-cms' ),
			'empty_text' => isset( $s['empty_text'] ) ? $s['empty_text'] : '',
			'filters'    => isset( $s['filters'] ) ? $s['filters'] : '',
		) );
	}
}
