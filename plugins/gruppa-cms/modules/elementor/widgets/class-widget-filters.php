<?php
defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/trait-gruppa-form-style.php';

/**
 * Elementor widget: Gruppa Search — a filter group that controls Gruppa Listing widgets.
 */
class Gruppa_Widget_Filters extends \Elementor\Widget_Base {

	use Gruppa_Form_Style;

	public function get_name() {
		return 'gruppa-search-filters';
	}

	public function get_title() {
		return 'Gruppa Search';
	}

	public function get_icon() {
		return class_exists( 'Gruppa_Elementor' ) ? Gruppa_Elementor::icon( 'search' ) : 'eicon-search';
	}

	public function get_categories() {
		return array( Gruppa_Elementor::CATEGORY );
	}

	public function get_keywords() {
		return array( 'gruppa', 'filter', 'search', 'filtre', 'hladat' );
	}

	public function get_style_depends() {
		return array( 'gruppa-form', 'gruppa-listing' );
	}

	public function get_script_depends() {
		return array( 'gruppa-filters', 'gruppa-listing' );
	}

	protected function register_controls() {
		$options = array( '' => '— Select filter group —' );
		foreach ( Gruppa_Filters::all() as $g ) {
			$options[ $g->slug ] = $g->name . ( 'publish' === $g->status ? '' : ' (draft)' );
		}
		$this->start_controls_section( 'content', array( 'label' => 'Filter group' ) );
		$this->add_control( 'group', array(
			'label'       => 'Filter group',
			'type'        => \Elementor\Controls_Manager::SELECT,
			'options'     => $options,
			'default'     => '',
			'label_block' => true,
			'description' => 'Create groups in Gruppa CMS → Filters. In the Gruppa Listing widget choose the same group under "Filter group".',
		) );
		$this->add_control( 'inline', array(
			'label'        => 'Filters in a row',
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'description'  => 'Side by side (e.g. above the listing). Off = under each other (sidebar).',
			'selectors'    => array(
				'{{WRAPPER}} .gruppa-filters .gruppa-form-field' => 'flex: 1 1 180px; max-width: none;',
				'{{WRAPPER}} .gruppa-filters'                    => 'display: flex; flex-wrap: wrap; align-items: flex-end; gap: 0 var(--gf-gap-x, var(--gf-gap));',
				'{{WRAPPER}} .gruppa-filters .gruppa-form-fields' => 'flex: 1 1 auto;',
				'{{WRAPPER}} .gruppa-filters .gruppa-filters-actions' => 'margin-bottom: var(--gf-gap-y, var(--gf-gap));',
			),
		) );
		$this->end_controls_section();

		$this->style_form();
		$this->style_label();
		$this->style_text();
		$this->style_select();
		$this->style_choice( 'checkbox' );
		$this->style_button();
		$this->style_reset();
	}

	private function style_reset() {
		$cm = '\Elementor\Controls_Manager';
		$r  = '{{WRAPPER}} .gruppa-form .gruppa-filters-reset';
		$rh = $r . ':hover, ' . $r . ':focus-visible';
		$this->start_controls_section( 'style_reset', array( 'label' => 'Reset button', 'tab' => $cm::TAB_STYLE ) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'reset_typography', 'selector' => $r ) );

		$this->add_control( 'reset_decoration', array(
			'label'     => 'Underline',
			'type'      => $cm::SELECT,
			'default'   => '',
			'options'   => array( '' => 'Default', 'none' => 'None', 'underline' => 'Underline' ),
			'selectors' => array( $r => 'text-decoration: {{VALUE}};' ),
		) );

		$this->start_controls_tabs( 'reset_tabs', array( 'separator' => 'before' ) );
		foreach ( array( 'normal' => 'Normal', 'hover' => 'Hover' ) as $state => $title ) {
			$sel = 'normal' === $state ? $r : $rh;
			$p   = 'normal' === $state ? 'reset_' : 'reset_hover_';
			$this->start_controls_tab( 'reset_tab_' . $state, array( 'label' => $title ) );
			$this->add_control( 'normal' === $state ? 'reset_color' : 'reset_hover', array(
				'label'     => 'Text color',
				'type'      => $cm::COLOR,
				'selectors' => array( $sel => 'color: {{VALUE}}; opacity: 1;' ),
			) );
			$this->add_control( $p . 'bg', array(
				'label'     => 'Background color',
				'type'      => $cm::COLOR,
				'selectors' => array( $sel => 'background-color: {{VALUE}};' ),
			) );
			$this->add_control( $p . 'border_color', array(
				'label'     => 'Border color',
				'type'      => $cm::COLOR,
				'selectors' => array( $sel => 'border-color: {{VALUE}};' ),
			) );
			$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array( 'name' => $p . 'shadow', 'selector' => $sel ) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'      => 'reset_border',
			'selector'  => $r,
			'separator' => 'before',
			'exclude'   => array( 'color' ), // Color per state in the tabs above (reset_border_color).
		) );
		$box = '{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}';
		$this->add_responsive_control( 'reset_radius', array(
			'label'      => 'Border radius',
			'type'       => $cm::DIMENSIONS,
			'size_units' => array( 'px', '%', 'em' ),
			'selectors'  => array( $r => "border-radius: $box;" ),
		) );
		$this->add_responsive_control( 'reset_padding', array(
			'label'      => 'Padding',
			'type'       => $cm::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( $r => "padding: $box;" ),
		) );
		$this->add_responsive_control( 'reset_margin', array(
			'label'      => 'Margin',
			'type'       => $cm::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( $r => "margin: $box;" ),
		) );
		$this->add_responsive_control( 'reset_gap', array(
			'label'      => 'Space between Filter and Reset',
			'type'       => $cm::SLIDER,
			'size_units' => array( 'px', 'em', 'rem' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'selectors'  => array( '{{WRAPPER}} .gruppa-form .gruppa-filters-actions' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo Gruppa_Filters::render( isset( $s['group'] ) ? $s['group'] : '' ); // phpcs:ignore
	}
}
