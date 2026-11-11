<?php
defined( 'ABSPATH' ) || exit;

/**
 * Elementor widget: Gruppa Table — a table from Table Builder with its own design.
 *
 * Content: table, column widths (Automatic / Equal / Custom), per column width + hide on tablet / mobile,
 *          mobile layout (cards or horizontal scroll), minimum width.
 * Style:   Table (background, outer border, lines, shadow), Header, Cells, Rows, Mobile cards, Pagination.
 */
class Gruppa_TB_Widget extends \Elementor\Widget_Base {

	const SEL = '{{WRAPPER}} .gruppa-table';

	public function get_name() {
		return 'gruppa-table';
	}

	public function get_title() {
		return 'Gruppa Table';
	}

	public function get_icon() {
		return class_exists( 'Gruppa_Elementor' ) ? Gruppa_Elementor::icon( 'table' ) : 'eicon-table';
	}

	public function get_categories() {
		return array( class_exists( 'Gruppa_Elementor' ) ? Gruppa_Elementor::CATEGORY : 'general' );
	}

	public function get_keywords() {
		return array( 'gruppa', 'table', 'tabulka', 'repeater', 'cennik' );
	}

	public function get_style_depends() {
		return array( 'gruppa-table' );
	}

	public function get_script_depends() {
		return array( 'gruppa-table' );
	}

	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	/** Border + radius controls for one element (outer frame, header cells, body cells). */
	private function border_controls( $prefix, $selector, $label = 'Border' ) {
		$cm  = '\Elementor\Controls_Manager';
		$box = '{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}';
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'           => $prefix . '_border',
				'label'          => $label,
				'selector'       => $selector,
				'fields_options' => array( 'border' => array( 'label' => $label . ' type' ) ),
			)
		);
		$this->add_responsive_control( $prefix . '_radius', array(
			'label'      => 'Border radius',
			'type'       => $cm::DIMENSIONS,
			'size_units' => array( 'px', '%', 'em', 'rem' ),
			'selectors'  => array( $selector => "border-radius: $box;" ),
		) );
	}

	protected function register_controls() {
		$cm  = '\Elementor\Controls_Manager';
		$sel = self::SEL;
		$box = '{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}';

		$options = array( '' => '— Select table —' );
		foreach ( Gruppa_TB_DB::all() as $t ) {
			$options[ $t->slug ] = $t->name . ( 'publish' === $t->status ? '' : ' (draft)' );
		}

		/* ================= Content ================= */

		$this->start_controls_section( 'content', array( 'label' => 'Table' ) );
		$this->add_control( 'table', array(
			'label'       => 'Table',
			'type'        => $cm::SELECT,
			'options'     => $options,
			'default'     => '',
			'label_block' => true,
			'description' => 'Create tables in Gruppa Tools → Table Builder.',
		) );
		$this->end_controls_section();

		$this->start_controls_section( 'content_columns', array( 'label' => 'Columns' ) );
		$this->add_control( 'width_mode', array(
			'label'       => 'Column width',
			'type'        => $cm::SELECT,
			'default'     => '',
			'options'     => array(
				''       => 'Automatic (by content)',
				'equal'  => 'Equal (same for all)',
				'custom' => 'Custom (set below)',
			),
			'description' => 'Automatic uses the widths from Table Builder, if set. Custom: columns without a width share the rest of the space.',
		) );
		$rep = new \Elementor\Repeater();
		$rep->add_control( 'col', array(
			'label'   => 'Column',
			'type'    => $cm::NUMBER,
			'min'     => 1,
			'max'     => 50,
			'default' => 1,
			'description' => '1 = first column from the left.',
		) );
		$rep->add_control( 'width', array(
			'label'       => 'Width',
			'type'        => $cm::SLIDER,
			'size_units'  => array( 'px', '%', 'rem', 'em' ),
			'range'       => array(
				'px'  => array( 'min' => 20, 'max' => 800 ),
				'%'   => array( 'min' => 1, 'max' => 100 ),
				'rem' => array( 'min' => 1, 'max' => 50 ),
				'em'  => array( 'min' => 1, 'max' => 50 ),
			),
			'description' => 'Used when Column width = Custom.',
		) );
		$rep->add_control( 'hide_tablet', array(
			'label'        => 'Hide on tablet',
			'type'         => $cm::SWITCHER,
			'return_value' => 'yes',
		) );
		$rep->add_control( 'hide_mobile', array(
			'label'        => 'Hide on mobile',
			'type'         => $cm::SWITCHER,
			'return_value' => 'yes',
		) );
		$this->add_control( 'columns', array(
			'label'       => 'Column settings',
			'type'        => $cm::REPEATER,
			'fields'      => $rep->get_controls(),
			'title_field' => 'Column {{{ col }}}{{{ width && width.size ? " · " + width.size + width.unit : "" }}}{{{ hide_tablet ? " · no tablet" : "" }}}{{{ hide_mobile ? " · no mobile" : "" }}}',
			'prevent_empty' => false,
			'default'     => array(),
		) );
		$this->end_controls_section();

		$this->start_controls_section( 'content_mobile', array( 'label' => 'Mobile' ) );
		$this->add_control( 'mobile_mode', array(
			'label'       => 'On mobile',
			'type'        => $cm::SELECT,
			'default'     => '',
			'options'     => array(
				''       => 'As set in Table Builder',
				'cards'  => 'Cards (every row as a card)',
				'scroll' => 'Table, horizontal scroll',
			),
			'description' => 'Cards: on phones (≤ 767 px) every row becomes a box with "column name : value" lines under each other. Horizontal scroll: the table stays a table and moves sideways with a finger.',
		) );
		$this->add_responsive_control( 'min_width', array(
			'label'       => 'Minimum table width',
			'type'        => $cm::SLIDER,
			'size_units'  => array( 'px', 'rem', 'em' ),
			'range'       => array( 'px' => array( 'min' => 0, 'max' => 2000 ) ),
			'description' => 'With horizontal scroll: the table is never narrower than this — it scrolls instead of squeezing the columns.',
			'selectors'   => array( "$sel table" => 'min-width: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ================= Style: Table ================= */

		$this->start_controls_section( 'style_table', array( 'label' => 'Table', 'tab' => $cm::TAB_STYLE ) );
		$this->add_control( 'table_bg', array(
			'label'     => 'Background',
			'type'      => $cm::COLOR,
			'selectors' => array( "$sel table" => 'background-color: {{VALUE}};' ),
		) );

		$this->add_control( 'outer_heading', array( 'label' => 'Outer border', 'type' => $cm::HEADING, 'separator' => 'before' ) );
		$this->border_controls( 'outer', "$sel .gruppa-table-scroll" );
		$this->add_responsive_control( 'outer_padding', array(
			'label'      => 'Padding',
			'type'       => $cm::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem', '%' ),
			'selectors'  => array( "$sel .gruppa-table-scroll" => "padding: $box;" ),
		) );
		$this->add_responsive_control( 'outer_margin', array(
			'label'      => 'Margin',
			'type'       => $cm::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem', '%' ),
			'selectors'  => array( "$sel .gruppa-table-scroll" => "margin: $box;" ),
		) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array( 'name' => 'table_shadow', 'selector' => "$sel .gruppa-table-scroll" ) );

		$this->add_control( 'lines_heading', array( 'label' => 'Lines inside', 'type' => $cm::HEADING, 'separator' => 'before' ) );
		$this->add_control( 'h_lines', array(
			'label'        => 'Horizontal lines',
			'type'         => $cm::SWITCHER,
			'label_on'     => 'On',
			'label_off'    => 'Off',
			'return_value' => 'yes',
			'default'      => 'yes',
		) );
		$this->add_control( 'col_lines', array(
			'label'        => 'Vertical lines',
			'type'         => $cm::SWITCHER,
			'label_on'     => 'On',
			'label_off'    => 'Off',
			'return_value' => 'yes',
			'selectors'    => array( $sel => '--gt-line-v: var(--gt-line-w, 1px);' ),
		) );
		$this->add_control( 'border_color', array(
			'label'     => 'Lines color',
			'type'      => $cm::COLOR,
			'selectors' => array( $sel => '--gt-border: {{VALUE}};' ),
		) );
		$this->add_control( 'line_width', array(
			'label'      => 'Lines width',
			'type'       => $cm::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 1, 'max' => 6 ) ),
			'selectors'  => array( $sel => '--gt-line-w: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'line_style', array(
			'label'     => 'Lines type',
			'type'      => $cm::SELECT,
			'default'   => '',
			'options'   => array( '' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double' ),
			'selectors' => array( $sel => '--gt-line-style: {{VALUE}};' ),
		) );
		$this->end_controls_section();

		/* ================= Style: Header ================= */

		$th = "$sel thead th";
		$this->start_controls_section( 'style_head', array( 'label' => 'Header', 'tab' => $cm::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'head_typography', 'selector' => $th ) );
		$this->add_control( 'head_color', array( 'label' => 'Text color', 'type' => $cm::COLOR, 'selectors' => array( $th => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'head_bg', array( 'label' => 'Background', 'type' => $cm::COLOR, 'selectors' => array( $sel => '--gt-head-bg: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'head_padding', array(
			'label'      => 'Padding',
			'type'       => $cm::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( $th => "padding: $box;" ),
		) );
		$this->add_responsive_control( 'head_align', array(
			'label'     => 'Text align',
			'type'      => $cm::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => 'Left', 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => 'Center', 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => 'Right', 'icon' => 'eicon-text-align-right' ),
			),
			'description' => 'Empty = as set per column in Table Builder.',
			'selectors' => array( $th => 'text-align: {{VALUE}};' ),
		) );
		$this->add_control( 'head_valign', array(
			'label'     => 'Vertical align',
			'type'      => $cm::SELECT,
			'default'   => '',
			'options'   => array( '' => 'Top', 'middle' => 'Middle', 'bottom' => 'Bottom' ),
			'selectors' => array( $th => 'vertical-align: {{VALUE}};' ),
		) );
		$this->add_control( 'head_wrap', array(
			'label'        => 'Wrap long names',
			'type'         => $cm::SWITCHER,
			'return_value' => 'yes',
			'selectors'    => array( $th => 'white-space: normal;' ),
		) );
		$this->add_control( 'head_border_heading', array( 'label' => 'Border', 'type' => $cm::HEADING, 'separator' => 'before' ) );
		$this->border_controls( 'head', $th );
		$this->end_controls_section();

		/* ================= Style: Cells ================= */

		$td = "$sel tbody td";
		$this->start_controls_section( 'style_cells', array( 'label' => 'Cells', 'tab' => $cm::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'cell_typography', 'selector' => $td ) );
		$this->add_control( 'cell_color', array( 'label' => 'Text color', 'type' => $cm::COLOR, 'selectors' => array( $td => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'link_color', array( 'label' => 'Link color', 'type' => $cm::COLOR, 'selectors' => array( "$td a" => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'cell_bg', array( 'label' => 'Background', 'type' => $cm::COLOR, 'selectors' => array( $sel => '--gt-cell-bg: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'cell_padding', array(
			'label'      => 'Padding',
			'type'       => $cm::DIMENSIONS,
			'size_units' => array( 'px', 'em', 'rem' ),
			'selectors'  => array( $td => "padding: $box;" ),
		) );
		$this->add_responsive_control( 'cell_align', array(
			'label'     => 'Text align',
			'type'      => $cm::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => 'Left', 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => 'Center', 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => 'Right', 'icon' => 'eicon-text-align-right' ),
			),
			'description' => 'Empty = as set per column in Table Builder.',
			'selectors' => array( $td => 'text-align: {{VALUE}};' ),
		) );
		$this->add_control( 'valign', array(
			'label'     => 'Vertical align',
			'type'      => $cm::SELECT,
			'default'   => '',
			'options'   => array( '' => 'Top', 'middle' => 'Middle', 'bottom' => 'Bottom' ),
			'selectors' => array( $td => 'vertical-align: {{VALUE}};' ),
		) );
		$this->add_control( 'cell_nowrap', array(
			'label'        => 'Keep text on one line',
			'type'         => $cm::SWITCHER,
			'return_value' => 'yes',
			'selectors'    => array( $td => 'white-space: nowrap;' ),
		) );
		$this->add_control( 'cell_border_heading', array( 'label' => 'Border', 'type' => $cm::HEADING, 'separator' => 'before' ) );
		$this->border_controls( 'cell', $td );
		$this->add_control( 'img_heading', array( 'label' => 'Images', 'type' => $cm::HEADING, 'separator' => 'before' ) );
		$this->add_responsive_control( 'img_size', array(
			'label'      => 'Image size',
			'type'       => $cm::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 24, 'max' => 300 ) ),
			'selectors'  => array( "$sel .gruppa-table-img" => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'img_radius', array(
			'label'      => 'Image radius',
			'type'       => $cm::SLIDER,
			'size_units' => array( 'px', '%' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
			'selectors'  => array( "$sel .gruppa-table-img" => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ================= Style: Rows ================= */

		$this->start_controls_section( 'style_rows', array( 'label' => 'Rows', 'tab' => $cm::TAB_STYLE ) );
		$this->add_control( 'zebra', array(
			'label'       => 'Every second row',
			'type'        => $cm::COLOR,
			'description' => 'Rows 2, 4, 6 … below the header. The other rows use the cell background.',
			'selectors'   => array( $sel => '--gt-row-even: {{VALUE}};' ),
		) );
		$this->add_control( 'row_hover', array(
			'label'     => 'Row on hover',
			'type'      => $cm::COLOR,
			'selectors' => array( $sel => '--gt-row-hover: {{VALUE}};' ),
		) );
		$this->add_control( 'row_hover_text', array(
			'label'     => 'Text on hover',
			'type'      => $cm::COLOR,
			'selectors' => array( "$sel tbody tr:hover > td" => 'color: {{VALUE}};' ),
		) );
		$this->end_controls_section();

		/* ================= Style: Mobile cards ================= */

		$this->start_controls_section( 'style_cards', array( 'label' => 'Mobile cards', 'tab' => $cm::TAB_STYLE ) );
		$this->add_control( 'cards_note', array(
			'type'            => $cm::RAW_HTML,
			'raw'             => 'Only when the table shows as cards on phones (Content → Mobile → On mobile = Cards, ≤ 767 px). Every row is a box with "column name : value" lines.',
			'content_classes' => 'elementor-descriptor',
		) );
		$this->add_control( 'card_bg', array( 'label' => 'Card background', 'type' => $cm::COLOR, 'selectors' => array( $sel => '--gt-card-bg: {{VALUE}};' ) ) );
		$this->add_control( 'card_label_color', array( 'label' => 'Column name color', 'type' => $cm::COLOR, 'selectors' => array( $sel => '--gt-card-label: {{VALUE}};' ) ) );
		$this->add_control( 'card_radius', array(
			'label'      => 'Card radius',
			'type'       => $cm::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'  => array( $sel => '--gt-card-radius: {{SIZE}}{{UNIT}};' ),
		) );
		$this->add_control( 'card_gap', array(
			'label'      => 'Space between cards',
			'type'       => $cm::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors'  => array( $sel => '--gt-card-gap: {{SIZE}}{{UNIT}};' ),
		) );
		$this->end_controls_section();

		/* ================= Style: Pagination ================= */

		$this->start_controls_section( 'style_pages', array( 'label' => 'Pagination', 'tab' => $cm::TAB_STYLE ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'pages_typography', 'selector' => "$sel .gruppa-table-pagination .page-numbers" ) );
		$this->add_control( 'pages_color', array( 'label' => 'Color', 'type' => $cm::COLOR, 'selectors' => array( "$sel .gruppa-table-pagination .page-numbers" => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'pages_active', array( 'label' => 'Current page background', 'type' => $cm::COLOR, 'selectors' => array( "$sel .gruppa-table-pagination .current" => 'background-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'pages_align', array(
			'label'     => 'Alignment',
			'type'      => $cm::CHOOSE,
			'options'   => array(
				'flex-start' => array( 'title' => 'Left', 'icon' => 'eicon-text-align-left' ),
				'center'     => array( 'title' => 'Center', 'icon' => 'eicon-text-align-center' ),
				'flex-end'   => array( 'title' => 'Right', 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( "$sel .gruppa-table-pagination" => 'justify-content: {{VALUE}};' ),
		) );
		$this->end_controls_section();
	}

	/** Elementor breakpoints (max width in px) for tablet and mobile. */
	private static function breakpoints() {
		$out = array( 'tablet' => 1024, 'mobile' => 767 );
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->breakpoints ) && method_exists( \Elementor\Plugin::$instance->breakpoints, 'get_breakpoints' ) ) {
			foreach ( array( 'tablet', 'mobile' ) as $k ) {
				$bp = \Elementor\Plugin::$instance->breakpoints->get_breakpoints( $k );
				if ( $bp && method_exists( $bp, 'get_value' ) ) {
					$out[ $k ] = (int) $bp->get_value();
				}
			}
		}
		return $out;
	}

	/** CSS for things a selector cannot express: lines off, per column width / hide. */
	private function inline_css( $s ) {
		$w   = '.elementor-element-' . $this->get_id() . ' .gruppa-table';
		$css = '';
		if ( empty( $s['h_lines'] ) ) {
			$css .= "$w{--gt-line-h:0px}";
		} else {
			$css .= "$w{--gt-line-h:var(--gt-line-w,1px)}";
		}
		$bp   = self::breakpoints();
		$tab  = array();
		$mob  = array();
		foreach ( isset( $s['columns'] ) && is_array( $s['columns'] ) ? $s['columns'] : array() as $c ) {
			$n = isset( $c['col'] ) ? (int) $c['col'] : 0;
			if ( $n < 1 || $n > 50 ) {
				continue;
			}
			if ( 'custom' === $s['width_mode'] && ! empty( $c['width']['size'] ) ) {
				$unit = isset( $c['width']['unit'] ) && in_array( $c['width']['unit'], array( 'px', '%', 'rem', 'em' ), true ) ? $c['width']['unit'] : 'px';
				$css .= sprintf( '%1$s col:nth-child(%2$d){width:%3$s%4$s!important}', $w, $n, (float) $c['width']['size'], $unit );
			}
			$cells = sprintf( '%1$s th:nth-child(%2$d),%1$s td:nth-child(%2$d),%1$s col:nth-child(%2$d)', $w, $n );
			if ( ! empty( $c['hide_tablet'] ) ) {
				$tab[] = $cells;
			}
			if ( ! empty( $c['hide_mobile'] ) ) {
				$mob[] = $cells;
			}
		}
		if ( $tab ) {
			$css .= sprintf( '@media (max-width:%dpx) and (min-width:%dpx){%s{display:none!important}}', $bp['tablet'], $bp['mobile'] + 1, implode( ',', $tab ) );
		}
		if ( $mob ) {
			$css .= sprintf( '@media (max-width:%dpx){%s{display:none!important}}', $bp['mobile'], implode( ',', $mob ) );
		}
		return '<style>' . $css . '</style>';
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$s = wp_parse_args( $s, array( 'table' => '', 'width_mode' => '', 'mobile_mode' => '', 'h_lines' => 'yes', 'columns' => array() ) );
		echo $this->inline_css( $s ); // phpcs:ignore
		echo Gruppa_TB::render( // phpcs:ignore
			$s['table'],
			array(
				'mobile' => $s['mobile_mode'],
				'width'  => $s['width_mode'] ? $s['width_mode'] : 'auto',
			)
		);
	}
}
