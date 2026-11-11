<?php
defined( 'ABSPATH' ) || exit;

/**
 * Style controls shared by the Gruppa Form and Gruppa Login widgets
 * (markup classes .gruppa-form, .gruppa-form-label, .gruppa-form-button…).
 */
trait Gruppa_Form_Style {

	/** "{{WRAPPER}} .gruppa-form a, {{WRAPPER}} .gruppa-form b" */
	private static function sel( $list, $suffix = '' ) {
		return implode( ', ', array_map( function ( $s ) use ( $suffix ) {
			return '{{WRAPPER}} .gruppa-form ' . $s . $suffix;
		}, $list ) );
	}

	/** Text-like inputs. Select fields inherit these until they get their own section. */
	protected static function text_list() {
		return array(
			'input[type=text]', 'input[type=email]', 'input[type=tel]', 'input[type=password]',
			'input[type=number]', 'input[type=date]', 'textarea:not(.wp-editor-area)', 'select',
		);
	}

	private function style_form() {
		$f = '{{WRAPPER}} .gruppa-form';
		$this->start_controls_section( 'style', array( 'label' => 'Form', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'form_width', array(
			'label'      => 'Width',
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( '%', 'px', 'vw' ),
			'default'    => array( 'unit' => '%' ),
			'range'      => array( '%' => array( 'min' => 10, 'max' => 100 ), 'px' => array( 'min' => 200, 'max' => 1600 ) ),
			'selectors'  => array( $f => 'width: {{SIZE}}{{UNIT}}; max-width: 100%;' ),
		) );
		$this->add_responsive_control( 'form_align', array(
			'label'                => 'Alignment',
			'type'                 => \Elementor\Controls_Manager::CHOOSE,
			'options'              => array(
				'left'   => array( 'title' => 'Left', 'icon' => 'eicon-h-align-left' ),
				'center' => array( 'title' => 'Center', 'icon' => 'eicon-h-align-center' ),
				'right'  => array( 'title' => 'Right', 'icon' => 'eicon-h-align-right' ),
			),
			'selectors_dictionary' => array(
				'left'   => 'margin-left: 0; margin-right: auto;',
				'center' => 'margin-left: auto; margin-right: auto;',
				'right'  => 'margin-left: auto; margin-right: 0;',
			),
			'selectors'            => array( $f => '{{VALUE}}' ),
		) );
		$this->add_responsive_control( 'gap_x', array(
			'label'      => 'Column gap',
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em', 'rem' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'separator'  => 'before',
			'selectors'  => array( $f => '--gf-gap-x: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'gap_y', array(
			'label'      => 'Row gap',
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em', 'rem' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'selectors'  => array( $f => '--gf-gap-y: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'outline_none', array(
			'label'        => 'Outline none',
			'description'  => 'Removes the browser focus outline from all fields and the button.',
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'separator'    => 'before',
			'selectors'    => array(
				$f . ' input:focus, ' . $f . ' select:focus, ' . $f . ' textarea:focus, ' . $f . ' button:focus, '
				. $f . ' input:focus-visible, ' . $f . ' select:focus-visible, ' . $f . ' textarea:focus-visible, ' . $f . ' button:focus-visible, '
				. $f . ' .gruppa-form-submit .gruppa-form-button:focus, ' . $f . ' .gruppa-form-submit .gruppa-form-button:focus-visible' => 'outline: none;',
			),
		) );
		$this->end_controls_section();
	}

	private function style_label() {
		$l = '{{WRAPPER}} .gruppa-form .gruppa-form-label';
		$this->start_controls_section( 'style_label', array( 'label' => 'Label', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'label_typography',
			'selector' => $l,
		) );
		$this->add_control( 'label_color', array(
			'label'     => 'Color',
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( $l => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'label_required_color', array(
			'label'     => 'Required (*) color',
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( $l . ' .gruppa-form-req' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'label_bg', array(
			'label'     => 'Background color',
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( $l => 'background-color: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'label_align', array(
			'label'     => 'Alignment',
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => 'Left', 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => 'Center', 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => 'Right', 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( $l => 'text-align: {{VALUE}};' ),
		) );
		$this->add_responsive_control( 'label_margin', array(
			'label'      => 'Margin',
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem', '%' ),
			'separator'  => 'before',
			'selectors'  => array( $l => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'label_padding', array(
			'label'      => 'Padding',
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem', '%' ),
			'selectors'  => array( $l => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'      => 'label_border',
			'selector'  => $l,
			'separator' => 'before',
		) );
		$this->add_responsive_control( 'label_radius', array(
			'label'      => 'Border radius',
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%', 'em' ),
			'selectors'  => array( $l => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->end_controls_section();
	}

	private function style_text() {
		$all = self::sel( self::text_list() );
		$this->start_controls_section( 'style_text', array(
			'label' => 'Text fields',
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );
		$this->add_control( 'text_note', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => 'Text, Email, Phone, Password, Number, Date, Textarea. Border and radius also apply to the rich text editor. Select fields inherit this style (own section below).',
			'content_classes' => 'elementor-descriptor',
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'text_typography',
			'selector' => $all,
		) );
		$this->add_responsive_control( 'text_align', array(
			'label'     => 'Text align',
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => 'Left', 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => 'Center', 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => 'Right', 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( $all => 'text-align: {{VALUE}};' ),
		) );

		$this->start_controls_tabs( 'text_tabs' );
		foreach ( array( 'normal' => 'Normal', 'hover' => 'Hover', 'focus' => 'Focus' ) as $state => $title ) {
			$suffix = 'normal' === $state ? '' : ':' . $state;
			$s      = self::sel( self::text_list(), $suffix );
			$p      = 'normal' === $state ? 'text_' : 'text_' . $state . '_';
			$this->start_controls_tab( 'text_tab_' . $state, array( 'label' => $title ) );
			$this->add_control( $p . 'color', array(
				'label'     => 'Text color',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $s => 'color: {{VALUE}};' ),
			) );
			if ( 'normal' === $state ) {
				$this->add_control( 'text_placeholder_color', array(
					'label'     => 'Placeholder color',
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( self::sel( self::text_list(), '::placeholder' ) => 'color: {{VALUE}}; opacity: 1;' ),
				) );
			}
			$this->add_control( $p . 'bg', array(
				'label'     => 'Background color',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $s => 'background-color: {{VALUE}};' ),
			) );
			// The rich text editor box follows the text fields' border.
			$ed = '{{WRAPPER}} .gruppa-form .wp-editor-container' . ( 'normal' === $state ? '' : ( 'hover' === $state ? ':hover' : ':focus-within' ) );
			if ( 'normal' === $state ) {
				$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
					'name'     => 'text_border',
					'selector' => $all . ', ' . $ed,
				) );
			} else {
				$this->add_control( $p . 'border_color', array(
					'label'     => 'Border color',
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $s . ', ' . $ed => 'border-color: {{VALUE}};' ),
				) );
			}
			$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
				'name'     => $p . 'shadow',
				'selector' => $s,
			) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();

		$this->add_responsive_control( 'text_radius', array(
			'label'      => 'Border radius',
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%', 'em' ),
			'separator'  => 'before',
			'selectors'  => array( $all . ', {{WRAPPER}} .gruppa-form .wp-editor-container' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'text_padding', array(
			'label'      => 'Padding',
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem', '%' ),
			'selectors'  => array( $all => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'text_margin', array(
			'label'      => 'Margin',
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem', '%' ),
			'selectors'  => array( $all => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'text_height', array(
			'label'       => 'Field height',
			'description' => 'Single-line fields and selects.',
			'type'        => \Elementor\Controls_Manager::SLIDER,
			'size_units'  => array( 'px', 'em', 'rem' ),
			'range'       => array( 'px' => array( 'min' => 24, 'max' => 120 ) ),
			'selectors'   => array( self::sel( array_diff( self::text_list(), array( 'textarea:not(.wp-editor-area)' ) ) ) => 'height: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'textarea_height', array(
			'label'      => 'Textarea height',
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em', 'rem', 'vh' ),
			'range'      => array( 'px' => array( 'min' => 40, 'max' => 600 ) ),
			// Rich text editor keeps its own height (resizable by its handle).
			'selectors'  => array( '{{WRAPPER}} .gruppa-form textarea:not(.wp-editor-area)' => 'height: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'textarea_resize', array(
			'label'     => 'Textarea resize',
			'type'      => \Elementor\Controls_Manager::SELECT,
			'options'   => array( '' => 'Default', 'vertical' => 'Vertical', 'none' => 'None' ),
			'selectors' => array( '{{WRAPPER}} .gruppa-form textarea:not(.wp-editor-area)' => 'resize: {{VALUE}};' ),
		) );
		$this->end_controls_section();
	}

	private function style_button() {
		$b  = '{{WRAPPER}} .gruppa-form .gruppa-form-submit .gruppa-form-button';
		$bh = $b . ':hover, ' . $b . ':focus';
		$this->start_controls_section( 'style_button', array( 'label' => 'Button', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'button_typography',
			'selector' => $b,
		) );
		$this->add_responsive_control( 'button_align', array(
			'label'       => 'Alignment',
			'description' => 'Overrides the alignment set in the form.',
			'type'        => \Elementor\Controls_Manager::CHOOSE,
			'options'     => array(
				'flex-start' => array( 'title' => 'Left', 'icon' => 'eicon-text-align-left' ),
				'center'     => array( 'title' => 'Center', 'icon' => 'eicon-text-align-center' ),
				'flex-end'   => array( 'title' => 'Right', 'icon' => 'eicon-text-align-right' ),
				'stretch'    => array( 'title' => 'Full width', 'icon' => 'eicon-text-align-justify' ),
			),
			'selectors_dictionary' => array( 'stretch' => 'stretch; --gf-btn-flex: 1' ),
			'selectors'   => array(
				'{{WRAPPER}} .gruppa-form .gruppa-form-submit' => 'justify-content: {{VALUE}};',
				$b => 'flex: var(--gf-btn-flex, 0 0 auto);',
			),
		) );
		$this->add_responsive_control( 'button_width', array(
			'label'      => 'Width',
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', '%' ),
			'range'      => array( 'px' => array( 'min' => 40, 'max' => 600 ), '%' => array( 'min' => 10, 'max' => 100 ) ),
			'selectors'  => array( $b => 'width: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'button_height', array(
			'label'      => 'Height',
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px', 'em' ),
			'range'      => array( 'px' => array( 'min' => 24, 'max' => 120 ) ),
			'selectors'  => array( $b => 'height: {{SIZE}}{{UNIT}};' ),
		) );

		$this->start_controls_tabs( 'button_tabs', array( 'separator' => 'before' ) );
		foreach ( array( 'normal' => 'Normal', 'hover' => 'Hover' ) as $state => $title ) {
			$s = 'normal' === $state ? $b : $bh;
			$p = 'normal' === $state ? 'button_' : 'button_hover_';
			$this->start_controls_tab( 'button_tab_' . $state, array( 'label' => $title ) );
			$this->add_control( $p . 'color', array(
				'label'     => 'Text color',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( $s => 'color: {{VALUE}};' ),
			) );
			$this->add_control( $p . 'bg', array(
				'label'     => 'Background color',
				'type'      => \Elementor\Controls_Manager::COLOR,
				// A set colour replaces the default "darken on hover".
				'selectors' => array( $s => 'background-color: {{VALUE}};' . ( 'hover' === $state ? ' filter: none;' : '' ) ),
			) );
			if ( 'hover' === $state ) {
				$this->add_control( 'button_hover_border_color', array(
					'label'     => 'Border color',
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( $s => 'border-color: {{VALUE}};' ),
				) );
				$this->add_control( 'button_hover_darken', array(
					'label'        => 'Darken on hover',
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'default'      => 'yes',
					'return_value' => 'yes',
					'selectors'    => array( $s => 'filter: brightness(.92);' ),
				) );
			}
			$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
				'name'     => $p . 'shadow',
				'selector' => $s,
			) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'      => 'button_border',
			'selector'  => $b,
			'separator' => 'before',
		) );
		$this->add_responsive_control( 'button_radius', array(
			'label'      => 'Border radius',
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%', 'em' ),
			'selectors'  => array( $b => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'button_padding', array(
			'label'      => 'Padding',
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem', '%' ),
			'selectors'  => array( $b => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_responsive_control( 'button_margin', array(
			'label'      => 'Margin',
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem', '%' ),
			'selectors'  => array( $b => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );
		$this->add_control( 'button_transition', array(
			'label'      => 'Transition (s)',
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 2, 'step' => 0.05 ) ),
			'selectors'  => array( $b => 'transition-duration: {{SIZE}}s;' ),
		) );
		$this->end_controls_section();
	}

	/* ---------- shared helpers ---------- */

	private function color( $id, $label, $selectors ) {
		$this->add_control( $id, array(
			'label'     => $label,
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => $selectors,
		) );
	}

	private function slider( $id, $label, $selectors, $units = array( 'px' ), $max = 100, $responsive = true, $extra = array() ) {
		$args = array_merge( array(
			'label'      => $label,
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => $units,
			'range'      => array( 'px' => array( 'min' => 0, 'max' => $max ) ),
			'selectors'  => $selectors,
		), $extra );
		$responsive ? $this->add_responsive_control( $id, $args ) : $this->add_control( $id, $args );
	}

	private function dims( $id, $label, $selector, $prop, $units = array( 'px', 'em', 'rem', '%' ), $extra = array() ) {
		$this->add_responsive_control( $id, array_merge( array(
			'label'      => $label,
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => $units,
			'selectors'  => array( $selector => $prop . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		), $extra ) );
	}

	private function border_style( $id, $selectors ) {
		$this->add_control( $id, array(
			'label'     => 'Border type',
			'type'      => \Elementor\Controls_Manager::SELECT,
			'options'   => array( '' => 'Default', 'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double', 'none' => 'None' ),
			'selectors' => $selectors,
		) );
	}

	private function note( $id, $text ) {
		$this->add_control( $id, array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => $text,
			'content_classes' => 'elementor-descriptor',
		) );
	}

	/* ---------- Select ---------- */

	private function style_select() {
		$all = '{{WRAPPER}} .gruppa-form .gruppa-form-field select';
		$this->start_controls_section( 'style_select', array( 'label' => 'Select fields', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->note( 'select_note', 'Empty = same as Text fields. Fill in only what should be different.' );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'select_typography',
			'selector' => $all,
		) );
		$this->add_responsive_control( 'select_align', array(
			'label'     => 'Text align',
			'type'      => \Elementor\Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => 'Left', 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => 'Center', 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => 'Right', 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( $all => 'text-align: {{VALUE}};' ),
		) );

		$this->start_controls_tabs( 'select_tabs' );
		foreach ( array( 'normal' => 'Normal', 'hover' => 'Hover', 'focus' => 'Focus' ) as $state => $title ) {
			$s = 'normal' === $state ? $all : $all . ':' . $state;
			$p = 'normal' === $state ? 'select_' : 'select_' . $state . '_';
			$this->start_controls_tab( 'select_tab_' . $state, array( 'label' => $title ) );
			$this->color( $p . 'color', 'Text color', array( $s => 'color: {{VALUE}};' ) );
			$this->color( $p . 'bg', 'Background color', array( $s => 'background-color: {{VALUE}};' ) );
			if ( 'normal' === $state ) {
				$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array( 'name' => 'select_border', 'selector' => $all ) );
			} else {
				$this->color( $p . 'border_color', 'Border color', array( $s => 'border-color: {{VALUE}};' ) );
			}
			$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array( 'name' => $p . 'shadow', 'selector' => $s ) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();

		$this->dims( 'select_radius', 'Border radius', $all, 'border-radius', array( 'px', '%', 'em' ), array( 'separator' => 'before' ) );
		$this->dims( 'select_padding', 'Padding', $all, 'padding' );
		$this->dims( 'select_margin', 'Margin', $all, 'margin' );
		$this->slider( 'select_height', 'Height (single)', array( '{{WRAPPER}} .gruppa-form .gruppa-form-field select:not([multiple])' => 'height: {{SIZE}}{{UNIT}};' ), array( 'px', 'em' ), 120 );
		$this->slider( 'select_multi_height', 'Height (multiple)', array( '{{WRAPPER}} .gruppa-form .gruppa-form-field select[multiple]' => 'height: {{SIZE}}{{UNIT}};' ), array( 'px', 'em' ), 400 );
		$this->end_controls_section();
	}

	/* ---------- Checkbox / Radio ---------- */

	private function style_choice( $kind ) {
		$is_cb = 'checkbox' === $kind;
		$v     = $is_cb ? '--gf-cb-' : '--gf-rd-';
		$p     = $is_cb ? 'cb_' : 'rd_';
		$f     = '{{WRAPPER}} .gruppa-form';
		$opt   = $f . ' label:not(.gruppa-switch):has(> input[type=' . $kind . '])';
		$group = $f . ' fieldset:has(input[type=' . $kind . '])';

		$this->start_controls_section( 'style_' . $kind, array(
			'label' => $is_cb ? 'Checkbox' : 'Radio',
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_responsive_control( $p . 'layout', array(
			'label'                => 'Options layout',
			'type'                 => \Elementor\Controls_Manager::CHOOSE,
			'options'              => array(
				'inline'  => array( 'title' => 'Inline', 'icon' => 'eicon-ellipsis-h' ),
				'stacked' => array( 'title' => 'Stacked', 'icon' => 'eicon-editor-list-ul' ),
			),
			'selectors_dictionary' => array( 'inline' => 'row', 'stacked' => 'column' ),
			'selectors'            => array( $group => 'flex-direction: {{VALUE}};' ),
		) );
		$this->slider( $p . 'spacing', 'Space between options', array( $group => 'gap: {{SIZE}}{{UNIT}};' ), array( 'px', 'em' ), 60 );

		// Text only: the box is hidden (still focusable and read by screen readers), the whole text is the button.
		$input = $f . ' label:not(.gruppa-switch) > input[type=' . $kind . ']';
		$this->add_control( $p . 'hide_box', array(
			'label'        => 'Hide box (text only)',
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'description'  => 'The whole option text is clickable. Style the selected option in the Checked tab (text, background, border).',
			'selectors'    => array(
				$input                                  => 'position: absolute; width: 1px; height: 1px; margin: 0; opacity: 0; pointer-events: none;',
				$opt                                    => 'gap: 0; position: relative; align-self: flex-start;',
				$opt . ':has(> input:focus-visible)'    => 'outline: 2px solid var(--gf-accent); outline-offset: 2px;',
			),
		) );
		$box = array( $p . 'hide_box!' => 'yes' );
		$this->slider( $p . 'size', 'Box size', array( $f => $v . 'size: {{SIZE}}{{UNIT}};' ), array( 'px', 'em' ), 60, true, array( 'condition' => $box ) );
		$this->slider( $p . 'gap', 'Space box ↔ text', array( $opt => 'gap: {{SIZE}}{{UNIT}};' ), array( 'px', 'em' ), 40, true, array( 'condition' => $box ) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'      => $p . 'typography',
			'label'     => 'Option typography',
			'selector'  => $opt,
			'separator' => 'before',
		) );
		$this->dims( $p . 'padding', 'Option padding', $opt, 'padding' );
		$this->dims( $p . 'margin', 'Option margin', $opt, 'margin' );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => $p . 'opt_border',
			'label'    => 'Option border',
			'selector' => $opt,
		) );
		$this->dims( $p . 'opt_radius', 'Option border radius', $opt, 'border-radius', array( 'px', 'em', '%' ) );

		$this->border_style( $p . 'border_style', array( $f => $v . 'bs: {{VALUE}};' ) );
		$this->slider( $p . 'border_width', 'Border width', array( $f => $v . 'bw: {{SIZE}}{{UNIT}};' ), array( 'px' ), 10 );
		if ( $is_cb ) {
			$this->slider( $p . 'radius', 'Border radius', array( $f => $v . 'r: {{SIZE}}{{UNIT}};' ), array( 'px', '%' ), 30 );
		}

		$this->start_controls_tabs( $p . 'tabs', array( 'separator' => 'before' ) );
		foreach ( array( 'normal' => 'Normal', 'hover' => 'Hover', 'checked' => 'Checked' ) as $state => $title ) {
			$sfx = 'normal' === $state ? '' : ( 'hover' === $state ? '-h' : '-on' );
			$this->start_controls_tab( $p . 'tab_' . $state, array( 'label' => $title ) );
			$this->color( $p . $state . '_bg', 'Box background', array( $f => $v . 'bg' . $sfx . ': {{VALUE}};' ) );
			$this->color( $p . $state . '_bc', 'Box border color', array( $f => $v . 'bc' . $sfx . ': {{VALUE}};' ) );
			$tsel = 'normal' === $state ? $opt : ( 'hover' === $state ? $opt . ':hover' : $f . ' label:not(.gruppa-switch):has(> input[type=' . $kind . ']:checked)' );
			$this->color( $p . $state . '_text', 'Text color', array( $tsel => 'color: {{VALUE}};' ) );
			$this->color( $p . $state . '_opt_bg', 'Option background', array( $tsel => 'background-color: {{VALUE}};' ) );
			$this->color( $p . $state . '_opt_bc', 'Option border color', array( $tsel => 'border-color: {{VALUE}};' ) );
			if ( 'checked' === $state ) {
				$this->color( $p . 'check', $is_cb ? 'Check mark color' : 'Dot color', array( $f => $v . 'check: {{VALUE}};' ) );
				if ( ! $is_cb ) {
					$this->slider( $p . 'dot', 'Dot size', array( $f => $v . 'dot: {{SIZE}}{{UNIT}};' ), array( '%' ), 100, false, array( 'range' => array( '%' => array( 'min' => 10, 'max' => 90 ) ) ) );
				}
			}
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	/* ---------- Switcher ---------- */

	private function style_switcher() {
		$f = '{{WRAPPER}} .gruppa-form';
		$this->start_controls_section( 'style_switcher', array( 'label' => 'Switcher', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->slider( 'sw_width', 'Width', array( $f => '--gf-sw-w: {{SIZE}}{{UNIT}};' ), array( 'px' ), 120 );
		$this->slider( 'sw_height', 'Height', array( $f => '--gf-sw-h: {{SIZE}}{{UNIT}};' ), array( 'px' ), 60 );
		$this->slider( 'sw_knob', 'Knob size', array( $f => '--gf-sw-knob: {{SIZE}}{{UNIT}};' ), array( 'px' ), 56 );
		$this->slider( 'sw_radius', 'Track radius', array( $f => '--gf-sw-r: {{SIZE}}{{UNIT}};' ), array( 'px' ), 60 );
		$this->slider( 'sw_knob_radius', 'Knob radius', array( $f => '--gf-sw-knob-r: {{SIZE}}{{UNIT}};' ), array( 'px', '%' ), 60 );

		$this->start_controls_tabs( 'sw_tabs', array( 'separator' => 'before' ) );
		foreach ( array( 'normal' => 'Normal', 'hover' => 'Hover', 'checked' => 'Checked' ) as $state => $title ) {
			$sfx = 'normal' === $state ? '' : ( 'hover' === $state ? '-h' : '-on' );
			$this->start_controls_tab( 'sw_tab_' . $state, array( 'label' => $title ) );
			$this->color( 'sw_' . $state . '_bg', 'Track color', array( $f => '--gf-sw-bg' . $sfx . ': {{VALUE}};' ) );
			$this->color( 'sw_' . $state . '_knob', 'Knob color', array( $f => '--gf-sw-knob-c' . $sfx . ': {{VALUE}};' ) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	/* ---------- Media upload ---------- */

	private function style_media() {
		$b  = '{{WRAPPER}} .gruppa-form .gruppa-form-media .gruppa-form-upload';
		$bh = $b . ':hover, ' . $b . ':focus';
		$this->start_controls_section( 'style_media', array( 'label' => 'Media upload', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->note( 'media_note', 'Upload button looks like your Elementor / theme buttons until you change it here. Button text and note are set in the form builder.' );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'upload_typography', 'selector' => $b ) );
		$this->start_controls_tabs( 'upload_tabs' );
		foreach ( array( 'normal' => 'Normal', 'hover' => 'Hover' ) as $state => $title ) {
			$s = 'normal' === $state ? $b : $bh;
			$this->start_controls_tab( 'upload_tab_' . $state, array( 'label' => $title ) );
			$this->color( 'upload_' . $state . '_color', 'Text color', array( $s => 'color: {{VALUE}};' ) );
			$this->color( 'upload_' . $state . '_bg', 'Background color', array( $s => 'background-color: {{VALUE}};' ) );
			$this->color( 'upload_' . $state . '_bc', 'Border color', array( $s => 'border-color: {{VALUE}};' ) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array( 'name' => 'upload_border', 'selector' => $b, 'separator' => 'before' ) );
		$this->dims( 'upload_radius', 'Border radius', $b, 'border-radius', array( 'px', '%', 'em' ) );
		$this->dims( 'upload_padding', 'Padding', $b, 'padding' );

		$names = '{{WRAPPER}} .gruppa-form .gruppa-form-file-names';
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'      => 'upload_names_typography',
			'label'     => 'File names typography',
			'selector'  => $names,
			'separator' => 'before',
		) );
		$this->color( 'upload_names_color', 'File names color', array( $names => 'color: {{VALUE}};' ) );

		$hint = '{{WRAPPER}} .gruppa-form .gruppa-form-media-hint';
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'      => 'upload_hint_typography',
			'label'     => 'Note typography',
			'selector'  => $hint,
			'separator' => 'before',
		) );
		$this->color( 'upload_hint_color', 'Note color', array( $hint => 'color: {{VALUE}}; opacity: 1;' ) );
		$this->slider( 'upload_preview', 'Preview size', array( '{{WRAPPER}} .gruppa-form .gruppa-form-media-list li' => 'width: {{SIZE}}{{UNIT}};', '{{WRAPPER}} .gruppa-form .gruppa-form-media-list img' => 'height: calc({{SIZE}}{{UNIT}} - 24px);' ), array( 'px' ), 240, true, array( 'separator' => 'before', 'range' => array( 'px' => array( 'min' => 48, 'max' => 240 ) ) ) );
		$this->end_controls_section();
	}

}
