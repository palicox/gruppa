<?php
defined( 'ABSPATH' ) || exit;

/**
 * Elementor widget: Gruppa Text — heading, text or link in one widget.
 * Tag h1–h6 / div / p / span, alignment incl. justify, typography, colors, shadow, stroke,
 * max characters, max width, line clamp, blend mode, margin, padding, background, border, box shadow.
 */
class Gruppa_Widget_Text extends \Elementor\Widget_Base {

	const SEL = '{{WRAPPER}} .gruppa-text';

	public function get_name() {
		return 'gruppa-text';
	}

	public function get_title() {
		return 'Gruppa Text';
	}

	public function get_icon() {
		return class_exists( 'Gruppa_Elementor' ) ? Gruppa_Elementor::icon( 'text' ) : 'eicon-t-letter';
	}

	public function get_categories() {
		return array( Gruppa_Elementor::CATEGORY );
	}

	public function get_script_depends() {
		return array( 'gruppa-text' ); // Active link check in the browser (cached pages, AJAX listings).
	}

	/** The output depends on the page being shown (Active link), so Elementor must not cache it. */
	protected function is_dynamic_content(): bool {
		return true;
	}

	public function get_keywords() {
		return array( 'gruppa', 'text', 'heading', 'title', 'nadpis', 'link', 'odkaz' );
	}

	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	protected function register_controls() {
		$cm  = '\Elementor\Controls_Manager';
		$sel = self::SEL;

		/* ---------- Content ---------- */
		$this->start_controls_section( 'content', array( 'label' => 'Content' ) );

		$this->add_control( 'content_type', array(
			'label'   => 'Type',
			'type'    => $cm::SELECT,
			'default' => 'title',
			'options' => array(
				'title'    => 'Title',
				'textarea' => 'Textarea',
				'link'     => 'Link',
			),
		) );

		$this->add_control( 'title', array(
			'label'       => 'Text',
			'type'        => $cm::TEXT,
			'label_block' => true,
			'dynamic'     => array( 'active' => true ),
			'default'     => 'Heading',
			'condition'   => array( 'content_type' => array( 'title', 'link' ) ),
		) );

		$this->add_control( 'text', array(
			'label'     => 'Text',
			'type'      => $cm::TEXTAREA,
			'rows'      => 6,
			'dynamic'   => array( 'active' => true ),
			'default'   => 'Paragraph text.',
			'condition' => array( 'content_type' => 'textarea' ),
		) );

		$this->add_control( 'link', array(
			'label'       => 'Link',
			'type'        => $cm::URL,
			'dynamic'     => array( 'active' => true ),
			'placeholder' => 'https://',
			'description' => 'Optional for Title.',
			'label_block' => true,
			'condition'   => array( 'content_type' => array( 'title', 'link' ) ),
		) );

		$this->add_control( 'active_match', array(
			'label'       => 'Active link',
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'exact',
			'options'     => array( '' => 'Off', 'exact' => 'This page', 'section' => 'This page and its subpages' ),
			'description' => 'When the link goes to the page you are on, the widget gets the Active colors (Style → Text → Active). Good for menus built from a listing.',
			'condition'   => array( 'content_type' => array( 'title', 'link' ) ),
		) );
		$this->add_control( 'icon', array(
			'label'       => 'Icon',
			'type'        => $cm::ICONS,
			'label_block' => false,
			'skin'        => 'inline',
			'separator'   => 'before',
			'condition'   => array( 'content_type' => array( 'title', 'link' ) ),
		) );

		$inner = self::SEL . ' .gruppa-text__inner';
		$this->add_control( 'icon_position', array(
			'label'                => 'Icon position',
			'type'                 => $cm::CHOOSE,
			'default'              => 'before',
			'toggle'               => false,
			'options'              => array(
				'before' => array( 'title' => 'Before text', 'icon' => 'eicon-h-align-left' ),
				'above'  => array( 'title' => 'Above text', 'icon' => 'eicon-v-align-top' ),
				'after'  => array( 'title' => 'After text', 'icon' => 'eicon-h-align-right' ),
			),
			'selectors_dictionary' => array(
				'before' => 'display: inline-flex; flex-direction: row;',
				'after'  => 'display: inline-flex; flex-direction: row-reverse;',
				'above'  => 'display: block;',
			),
			'selectors'            => array(
				$inner                                                => '{{VALUE}}',
				self::SEL . ' .gruppa-text__icon'                     => 'display: inline-flex; line-height: 1; flex-shrink: 0;',
				self::SEL . '.gruppa-text--icon-above .gruppa-text__icon' => 'display: block;',
				self::SEL . ' .gruppa-text__icon svg'                 => 'width: 1em; height: 1em; fill: currentColor; display: inline-block; vertical-align: middle;',
				self::SEL . ' .gruppa-text__icon i'                   => 'display: inline-block; line-height: 1;',
			),
			'condition'            => array( 'content_type' => array( 'title', 'link' ), 'icon[value]!' => '' ),
		) );

		$this->add_control( 'max_chars', array(
			'label'       => 'Max characters',
			'type'        => $cm::NUMBER,
			'min'         => 0,
			'step'        => 1,
			'description' => 'Empty or 0 = no limit. Longer text is cut and ends with "…".',
		) );

		$this->add_control( 'max_chars_words', array(
			'label'        => 'Cut at whole word',
			'type'         => $cm::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
			'condition'    => array( 'max_chars!' => array( '', 0, '0' ) ),
		) );

		$this->add_control( 'hide_empty', array(
			'label'        => 'Hide when empty',
			'type'         => $cm::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
			'description'  => 'No value (e.g. an empty field in the dynamic tag) = the widget is not shown on the site, without an empty gap.',
			'selectors'    => array( 'body:not(.elementor-editor-active) {{WRAPPER}}:not(:has(.gruppa-text))' => 'display: none;' ),
		) );

		$this->add_control( 'html_tag', array(
			'label'   => 'HTML tag',
			'type'    => $cm::SELECT,
			'default' => 'h2',
			'options' => array(
				'h1'   => 'H1',
				'h2'   => 'H2',
				'h3'   => 'H3',
				'h4'   => 'H4',
				'h5'   => 'H5',
				'h6'   => 'H6',
				'div'  => 'div',
				'p'    => 'p',
				'span' => 'span',
			),
		) );

		$this->add_responsive_control( 'align', array(
			'label'     => 'Alignment',
			'type'      => $cm::CHOOSE,
			'options'   => array(
				'left'    => array( 'title' => 'Left', 'icon' => 'eicon-text-align-left' ),
				'center'  => array( 'title' => 'Center', 'icon' => 'eicon-text-align-center' ),
				'right'   => array( 'title' => 'Right', 'icon' => 'eicon-text-align-right' ),
				'justify' => array( 'title' => 'Justify', 'icon' => 'eicon-text-align-justify' ),
			),
			'selectors' => array( $sel => 'text-align: {{VALUE}};' ),
		) );

		$this->end_controls_section();

		/* ---------- Style: Text ---------- */
		$this->start_controls_section( 'style_text', array( 'label' => 'Text', 'tab' => $cm::TAB_STYLE ) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name'     => 'typography',
			'selector' => $sel,
		) );

		$this->start_controls_tabs( 'color_tabs' );

		$this->start_controls_tab( 'color_tab_normal', array( 'label' => 'Normal' ) );
		$this->add_control( 'color', array(
			'label'     => 'Color',
			'type'      => $cm::COLOR,
			'selectors' => array( "$sel, $sel a" => 'color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();

		$this->start_controls_tab( 'color_tab_hover', array( 'label' => 'Hover' ) );
		$this->add_control( 'color_hover', array(
			'label'     => 'Color',
			'type'      => $cm::COLOR,
			'selectors' => array( "$sel:hover, $sel a:hover" => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'transition', array(
			'label'     => 'Transition (ms)',
			'type'      => $cm::SLIDER,
			'range'     => array( 'px' => array( 'min' => 0, 'max' => 2000, 'step' => 50 ) ),
			'default'   => array( 'size' => 200 ),
			'selectors' => array( "$sel, $sel a" => 'transition: color {{SIZE}}ms, background-color {{SIZE}}ms, border-color {{SIZE}}ms;' ),
		) );
		$this->end_controls_tab();

		$this->start_controls_tab( 'color_tab_active', array( 'label' => 'Active' ) );
		$this->add_control( 'color_active', array(
			'label'       => 'Color',
			'type'        => $cm::COLOR,
			'selectors'   => array( "$sel.is-active, $sel.is-active a, $sel.is-active:hover, $sel.is-active a:hover" => 'color: {{VALUE}};' ),
			'description' => 'Link to the page you are on (Content → Active link).',
		) );
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control( 'link_decoration', array(
			'label'     => 'Link underline',
			'type'      => $cm::SELECT,
			'separator' => 'before',
			'default'   => '',
			'options'   => array(
				''          => 'Default',
				'none'      => 'None',
				'underline' => 'Underline',
			),
			'selectors' => array( "$sel a" => 'text-decoration: {{VALUE}};' ),
		) );

		$this->add_group_control( \Elementor\Group_Control_Text_Shadow::get_type(), array(
			'name'     => 'text_shadow',
			'selector' => $sel,
		) );

		$this->add_group_control( \Elementor\Group_Control_Text_Stroke::get_type(), array(
			'name'     => 'text_stroke',
			'selector' => $sel,
		) );

		$this->add_responsive_control( 'max_width', array(
			'label'      => 'Max width',
			'type'       => $cm::SLIDER,
			'separator'  => 'before',
			'size_units' => array( 'px', '%', 'ch', 'em', 'rem', 'vw' ),
			'range'      => array(
				'px' => array( 'min' => 0, 'max' => 1600 ),
				'%'  => array( 'min' => 0, 'max' => 100 ),
				'ch' => array( 'min' => 10, 'max' => 120 ),
			),
			'selectors'  => array( $sel => 'max-width: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'line_clamp', array(
			'label'       => 'Max lines',
			'type'        => $cm::NUMBER,
			'min'         => 1,
			'max'         => 20,
			'description' => 'Text over the limit ends with "…" — keeps the layout when content is longer.',
			'selectors'   => array( $sel => 'display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: {{VALUE}}; line-clamp: {{VALUE}}; overflow: hidden;' ),
		) );

		$this->add_control( 'blend_mode', array(
			'label'     => 'Blend mode',
			'type'      => $cm::SELECT,
			'default'   => '',
			'options'   => array(
				''            => 'Normal',
				'multiply'    => 'Multiply',
				'screen'      => 'Screen',
				'overlay'     => 'Overlay',
				'darken'      => 'Darken',
				'lighten'     => 'Lighten',
				'color-dodge' => 'Color Dodge',
				'difference'  => 'Difference',
				'exclusion'   => 'Exclusion',
				'hue'         => 'Hue',
				'saturation'  => 'Saturation',
				'color'       => 'Color',
				'luminosity'  => 'Luminosity',
			),
			'selectors' => array( $sel => 'mix-blend-mode: {{VALUE}};' ),
		) );

		$this->end_controls_section();

		/* ---------- Style: Icon ---------- */
		$this->start_controls_section( 'style_icon', array(
			'label'     => 'Icon',
			'tab'       => $cm::TAB_STYLE,
			'condition' => array( 'content_type' => array( 'title', 'link' ), 'icon[value]!' => '' ),
		) );

		$icon = "$sel .gruppa-text__icon";

		$this->add_responsive_control( 'icon_size', array(
			'label'      => 'Size',
			'type'       => $cm::SLIDER,
			'size_units' => array( 'px', 'em', 'rem' ),
			'range'      => array( 'px' => array( 'min' => 6, 'max' => 200 ), 'em' => array( 'min' => 0.2, 'max' => 10, 'step' => 0.05 ) ),
			'default'    => array( 'unit' => 'em', 'size' => 1 ),
			'selectors'  => array( $icon => 'font-size: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'icon_spacing', array(
			'label'      => 'Spacing',
			'type'       => $cm::SLIDER,
			'size_units' => array( 'px', 'em', 'rem' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ), 'em' => array( 'min' => 0, 'max' => 5, 'step' => 0.05 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 8 ),
			'selectors'  => array(
				"$sel .gruppa-text__inner"                         => 'gap: {{SIZE}}{{UNIT}};',
				"$sel.gruppa-text--icon-above .gruppa-text__icon" => 'margin-bottom: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->add_control( 'icon_valign', array(
			'label'     => 'Vertical align',
			'type'      => $cm::CHOOSE,
			'default'   => 'center',
			'options'   => array(
				'flex-start' => array( 'title' => 'Top', 'icon' => 'eicon-v-align-top' ),
				'center'     => array( 'title' => 'Middle', 'icon' => 'eicon-v-align-middle' ),
				'flex-end'   => array( 'title' => 'Bottom', 'icon' => 'eicon-v-align-bottom' ),
				'baseline'   => array( 'title' => 'Baseline', 'icon' => 'eicon-text' ),
			),
			'selectors' => array( "$sel .gruppa-text__inner" => 'align-items: {{VALUE}};' ),
			'condition' => array( 'icon_position!' => 'above' ),
		) );

		$this->start_controls_tabs( 'icon_color_tabs' );
		$this->start_controls_tab( 'icon_color_normal', array( 'label' => 'Normal' ) );
		$this->add_control( 'icon_color', array(
			'label'     => 'Color',
			'type'      => $cm::COLOR,
			'selectors' => array( $icon => 'color: {{VALUE}};', "$icon svg" => 'fill: {{VALUE}};' ),
		) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'icon_color_hover', array( 'label' => 'Hover' ) );
		$this->add_control( 'icon_color_hover', array(
			'label'     => 'Color',
			'type'      => $cm::COLOR,
			'selectors' => array(
				"$sel:hover .gruppa-text__icon"     => 'color: {{VALUE}};',
				"$sel:hover .gruppa-text__icon svg" => 'fill: {{VALUE}};',
			),
		) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'icon_color_active', array( 'label' => 'Active' ) );
		$this->add_control( 'icon_color_active', array(
			'label'     => 'Color',
			'type'      => $cm::COLOR,
			'selectors' => array(
				"$sel.is-active .gruppa-text__icon"     => 'color: {{VALUE}};',
				"$sel.is-active .gruppa-text__icon svg" => 'fill: {{VALUE}};',
			),
		) );
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();

		/* ---------- Style: Box ---------- */
		$this->start_controls_section( 'style_box', array( 'label' => 'Box', 'tab' => $cm::TAB_STYLE ) );

		$units = array( 'px', 'em', 'rem', '%', 'vw' );
		$box   = '{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}';

		$this->add_responsive_control( 'margin', array(
			'label'      => 'Margin',
			'type'       => $cm::DIMENSIONS,
			'size_units' => $units,
			'selectors'  => array( $sel => "margin: $box;" ),
		) );

		$this->add_responsive_control( 'padding', array(
			'label'      => 'Padding',
			'type'       => $cm::DIMENSIONS,
			'size_units' => $units,
			'selectors'  => array( $sel => "padding: $box;" ),
		) );

		$this->add_group_control( \Elementor\Group_Control_Background::get_type(), array(
			'name'      => 'background',
			'types'     => array( 'classic', 'gradient' ),
			'exclude'   => array( 'image' ),
			'selector'  => $sel,
			'separator' => 'before',
		) );

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name'     => 'border',
			'selector' => $sel,
		) );

		$this->add_responsive_control( 'border_radius', array(
			'label'      => 'Border radius',
			'type'       => $cm::DIMENSIONS,
			'size_units' => array( 'px', '%', 'em', 'rem' ),
			'selectors'  => array( $sel => "border-radius: $box;" ),
		) );

		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'box_shadow',
			'selector' => $sel,
		) );

		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$type = isset( $s['content_type'] ) ? $s['content_type'] : 'title';
		$tag  = \Elementor\Utils::validate_html_tag( isset( $s['html_tag'] ) ? $s['html_tag'] : 'h2' );

		$content = 'textarea' === $type ? ( isset( $s['text'] ) ? $s['text'] : '' ) : ( isset( $s['title'] ) ? $s['title'] : '' );
		if ( '' === trim( wp_strip_all_tags( (string) $content ) ) ) {
			// Nothing to show. In the editor a hint keeps the widget visible and selectable.
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="gruppa-text-empty" style="opacity:.5;font-size:12px;padding:4px 0">Gruppa Text: empty' . ( ! empty( $s['hide_empty'] ) ? ' — hidden on the site' : '' ) . '</div>';
			}
			return;
		}

		$content = self::limit( $content, isset( $s['max_chars'] ) ? (int) $s['max_chars'] : 0, ! empty( $s['max_chars_words'] ) );

		$html = wp_kses_post( 'textarea' === $type ? self::paragraphs( $content ) : $content );

		// Icon (Elementor icon library or uploaded SVG): before, after or above the text.
		$pos       = isset( $s['icon_position'] ) && in_array( $s['icon_position'], array( 'before', 'after', 'above' ), true ) ? $s['icon_position'] : 'before';
		$icon_html = '';
		if ( 'textarea' !== $type && ! empty( $s['icon']['value'] ) ) {
			ob_start();
			\Elementor\Icons_Manager::render_icon( $s['icon'], array( 'aria-hidden' => 'true' ) );
			$icon_html = trim( ob_get_clean() );
		}
		if ( '' !== $icon_html ) {
			$html = '<span class="gruppa-text__inner"><span class="gruppa-text__icon">' . $icon_html . '</span><span class="gruppa-text__label">' . $html . '</span></span>';
			$this->add_render_attribute( 'wrapper', 'class', 'gruppa-text--icon-' . $pos );
		}

		if ( 'textarea' !== $type && ! empty( $s['link']['url'] ) ) {
			$this->add_link_attributes( 'link', $s['link'] );
			$this->add_render_attribute( 'link', 'class', 'gruppa-text__link' );
			$match = isset( $s['active_match'] ) ? (string) $s['active_match'] : 'exact';
			if ( in_array( $match, array( 'exact', 'section' ), true ) ) {
				// Checked here and again in the browser (gruppa-text.js) for cached pages and listings loaded by AJAX.
				$this->add_render_attribute( 'wrapper', 'data-gruppa-active', $match );
				if ( self::is_current( $s['link']['url'], $match ) ) {
					$this->add_render_attribute( 'wrapper', 'class', 'is-active' );
					$this->add_render_attribute( 'link', 'aria-current', 'page' );
				}
				wp_enqueue_script( 'gruppa-text' );
			}
			$html = '<a ' . $this->get_render_attribute_string( 'link' ) . '>' . $html . '</a>';
		}

		// elementor-heading-title: theme classes on the widget (e.g. .big) style it like the Elementor Heading widget.
		$this->add_render_attribute( 'wrapper', 'class', array( 'gruppa-text', 'gruppa-text--' . $type, 'elementor-heading-title' ) );

		echo '<' . $tag . ' ' . $this->get_render_attribute_string( 'wrapper' ) . '>' . $html . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- tag validated, content sanitized.
	}

	/**
	 * Does the link go to the page being shown? Path compared without the trailing
	 * slash; query parameters of the link must be in the current URL too.
	 * section = also every page below it (/sluzby/ is active on /sluzby/web/).
	 */
	public static function is_current( $url, $match = 'exact' ) {
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false; // AJAX listing pages: the browser script decides.
		}
		$link = wp_parse_url( (string) $url );
		$cur  = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only compared.
		if ( ! $link || ! $cur ) {
			return false;
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! empty( $link['host'] ) && strtolower( $link['host'] ) !== strtolower( (string) $host ) ) {
			return false;
		}
		if ( empty( $link['path'] ) && empty( $link['query'] ) ) {
			return false; // "#" or an anchor only.
		}
		$lp = '/' . trim( isset( $link['path'] ) ? $link['path'] : '', '/' );
		$cp = '/' . trim( isset( $cur['path'] ) ? rawurldecode( $cur['path'] ) : '', '/' );
		$lp = rawurldecode( $lp );
		$ok = $lp === $cp || ( 'section' === $match && '/' !== $lp && 0 === strpos( $cp . '/', $lp . '/' ) );
		if ( $ok && ! empty( $link['query'] ) ) {
			parse_str( $link['query'], $lq );
			parse_str( isset( $cur['query'] ) ? $cur['query'] : '', $cq );
			foreach ( $lq as $k => $v ) {
				if ( ! isset( $cq[ $k ] ) || (string) $cq[ $k ] !== (string) $v ) {
					return false;
				}
			}
		}
		return $ok;
	}

	/**
	 * Line breaks for plain text only. Content that already has block HTML
	 * (from a WYSIWYG field or a dynamic tag) is left as it is, otherwise
	 * every newline between tags would become an extra <br>.
	 */
	public static function paragraphs( $text ) {
		$text = (string) $text;
		if ( preg_match( '/<(p|div|h[1-6]|ul|ol|li|table|blockquote|pre|figure|section|br|hr)[\s>\/]/i', $text ) ) {
			return $text;
		}
		return nl2br( $text );
	}

	/**
	 * Cut text to $max characters (multibyte safe). HTML is stripped only when the text is cut.
	 */
	public static function limit( $text, $max, $whole_word = true ) {
		if ( $max < 1 ) {
			return $text;
		}
		$plain = trim( preg_replace( '/[ \t]+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
		if ( mb_strlen( $plain ) <= $max ) {
			return $text;
		}
		$cut = mb_substr( $plain, 0, $max );
		if ( $whole_word ) {
			$space = mb_strrpos( $cut, ' ' );
			if ( false !== $space && $space > $max * 0.6 ) {
				$cut = mb_substr( $cut, 0, $space );
			}
		}
		return rtrim( $cut, " \t\n\r,.;:–-" ) . '…';
	}

	protected function content_template() {
		?>
		<#
		var type    = settings.content_type || 'title';
		var tag     = elementor.helpers.validateHTMLTag( settings.html_tag || 'h2' );
		var content = 'textarea' === type ? settings.text : settings.title;

		if ( ! content ) {
			return;
		}

		var max = parseInt( settings.max_chars, 10 ) || 0;
		if ( max > 0 ) {
			var plain = String( content ).replace( /<[^>]*>/g, '' ).replace( /[ \t]+/g, ' ' ).trim();
			var chars = Array.from( plain );
			if ( chars.length > max ) {
				var cut = chars.slice( 0, max ).join( '' );
				if ( 'yes' === settings.max_chars_words ) {
					var space = cut.lastIndexOf( ' ' );
					if ( space > max * 0.6 ) {
						cut = cut.slice( 0, space );
					}
				}
				content = cut.replace( /[\s,.;:–-]+$/, '' ) + '…';
			}
		}

		var html = content;
		if ( 'textarea' === type && ! /<(p|div|h[1-6]|ul|ol|li|table|blockquote|pre|figure|section|br|hr)[\s>\/]/i.test( String( content ) ) ) {
			html = String( content ).replace( /\n/g, '<br>' );
		}

		var classes = [ 'gruppa-text', 'gruppa-text--' + type, 'elementor-heading-title' ];
		if ( 'textarea' !== type && settings.icon && settings.icon.value ) {
			var iconHTML = elementor.helpers.renderIcon( view, settings.icon, { 'aria-hidden': true }, 'i', 'object' );
			if ( iconHTML && iconHTML.rendered ) {
				html = '<span class="gruppa-text__inner"><span class="gruppa-text__icon">' + iconHTML.value + '</span><span class="gruppa-text__label">' + html + '</span></span>';
				classes.push( 'gruppa-text--icon-' + ( settings.icon_position || 'before' ) );
			}
		}

		if ( 'textarea' !== type && settings.link && settings.link.url ) {
			html = '<a class="gruppa-text__link" href="' + _.escape( settings.link.url ) + '">' + html + '</a>';
		}

		view.addRenderAttribute( 'wrapper', 'class', classes );
		#>
		<{{{ tag }}} {{{ view.getRenderAttributeString( 'wrapper' ) }}}>{{{ html }}}</{{{ tag }}}>
		<?php
	}
}
