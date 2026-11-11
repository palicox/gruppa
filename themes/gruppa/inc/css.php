<?php
/**
 * Gruppa – schéma nastavení a skladanie CSS.
 *
 * Načítava sa len v Prispôsobiť a pri prestavbe CSS súboru.
 * Bežná návšteva webu tento súbor nenačíta – dostane hotový CSS súbor z uploads/gruppa/.
 *
 * Prázdne pole = hodnota témy (style.css). Do CSS idú len zmenené hodnoty.
 */

defined( 'ABSPATH' ) || exit;

/** Záložný stack písma. */
const GRUPPA_FONT_STACK = "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";

/** Selektory nadpisov pre písmo nadpisov. */
const GRUPPA_HEADINGS = 'h1,h2,h3,h4,h5,h6,.big,.big .elementor-heading-title';

/**
 * Selektory tlačidiel a polí formulárov.
 */
function gruppa_selectors( $group, $suffix = '' ) {
	$list = array(
		'button' => array( '.elementor-button', '.button', '.wp-element-button', 'input[type="submit"]', '.jet-form-builder__action-button' ),
		'field'  => array( 'input[type="text"]', 'input[type="email"]', 'input[type="tel"]', 'input[type="number"]', 'input[type="password"]', 'input[type="url"]', 'input[type="search"]', 'input[type="date"]', 'textarea', 'select' ),
	);
	return implode( ',', array_map( function ( $s ) use ( $suffix ) {
		return $s . $suffix;
	}, $list[ $group ] ) );
}

/**
 * Schéma: sekcie → riadky → polia.
 *
 * Pole:
 *   type   color | unit | select | text | box | font | weights
 *   def    hodnota témy (zobrazí sa ako placeholder)
 *   always pravidlo sa vypíše aj s hodnotou témy (pre prvky, ktoré style.css nemá)
 *   var    CSS premenná v :root
 *   css    [ selektor, vlastnosť, breakpoint ('' | t | d), pevná hodnota ]
 */
function gruppa_schema() {
	static $schema = null;
	if ( null !== $schema ) {
		return $schema;
	}

	$weights = array( '' => '' );
	foreach ( range( 100, 900, 100 ) as $w ) {
		$weights[ (string) $w ] = (string) $w;
	}
	$transform = array( '' => '', 'none' => 'none', 'uppercase' => 'UPPERCASE', 'lowercase' => 'lowercase', 'capitalize' => 'Capitalize' );
	$style     = array( '' => '', 'normal' => 'normal', 'italic' => 'italic' );

	$size_units = array( 'rem', 'em', 'px', '%', 'vw' );
	$lh_units   = array( '', 'em', 'rem', 'px' );
	$ls_units   = array( 'em', 'rem', 'px' );

	/* ----- Farby ----- */
	$colors = array(
		'title' => __( 'Farby', 'gruppa' ),
		'panel' => 'design',
		'rows'  => array(
			array( 'label' => __( 'Pozadie', 'gruppa' ), 'fields' => array( 'color_bg' => array( 'type' => 'color', 'def' => '#FFFFFF', 'css' => array( array( 'body', 'background-color' ) ) ) ) ),
			array( 'label' => __( 'Nadpisy', 'gruppa' ), 'fields' => array( 'color_heading' => array( 'type' => 'color', 'def' => '#040506', 'var' => '--gruppa-black' ) ) ),
			array( 'label' => __( 'Text', 'gruppa' ), 'fields' => array( 'color_text' => array( 'type' => 'color', 'def' => '#575770', 'var' => '--gruppa-dark-gray' ) ) ),
			array( 'label' => __( 'Accent', 'gruppa' ), 'fields' => array( 'color_accent' => array( 'type' => 'color', 'def' => '#2952EA', 'var' => '--gruppa-blue', 'focus' => true ) ) ),
			array( 'label' => __( 'Hover', 'gruppa' ), 'fields' => array( 'color_hover' => array( 'type' => 'color', 'def' => '#1234B5', 'var' => '--gruppa-hover' ) ) ),
			array( 'label' => __( 'Oddeľovače', 'gruppa' ), 'fields' => array( 'color_divider' => array( 'type' => 'color', 'def' => '#B0B0C3', 'var' => '--gruppa-divider' ) ) ),
			array( 'heading' => __( 'Ďalšie farby palety', 'gruppa' ) ),
			array( 'label' => __( 'Tmavá', 'gruppa' ), 'fields' => array( 'color_dark' => array( 'type' => 'color', 'def' => '#00193C', 'var' => '--gruppa-dark' ) ) ),
			array( 'label' => __( 'Svetlomodrá', 'gruppa' ), 'fields' => array( 'color_light_blue' => array( 'type' => 'color', 'def' => '#D5E5FE', 'var' => '--gruppa-light-blue' ) ) ),
			array( 'label' => __( 'Svetlosivá', 'gruppa' ), 'fields' => array( 'color_light_gray' => array( 'type' => 'color', 'def' => '#F6F7FB', 'var' => '--gruppa-light-gray' ) ) ),
			array( 'label' => __( 'Biela', 'gruppa' ), 'fields' => array( 'color_white' => array( 'type' => 'color', 'def' => '#FFFFFF', 'var' => '--gruppa-white' ) ) ),
		),
	);

	/* ----- Písma ----- */
	$sources = array(
		'google' => __( 'Google Fonts', 'gruppa' ),
		'system' => __( 'Systémové', 'gruppa' ),
		'custom' => __( 'Načítané inde', 'gruppa' ),
	);
	$fonts = array(
		'title' => __( 'Písma', 'gruppa' ),
		'panel' => 'design',
		'desc'  => __( '„Načítané inde“ = písmo načíta iný plugin (napr. Elementor → Vlastné písma), téma ho len použije.', 'gruppa' ),
		'rows'  => array(
			array( 'heading' => __( 'Text', 'gruppa' ) ),
			array( 'label' => __( 'Zdroj', 'gruppa' ), 'fields' => array( 'font_body_src' => array( 'type' => 'select', 'options' => $sources, 'std' => 'google' ) ) ),
			array( 'label' => __( 'Písmo', 'gruppa' ), 'fields' => array( 'font_body_name' => array( 'type' => 'font', 'def' => 'Google Sans Flex' ) ) ),
			array( 'label' => __( 'Rezy', 'gruppa' ), 'fields' => array( 'font_body_w' => array( 'type' => 'weights', 'def' => '100..900' ) ) ),
			array( 'heading' => __( 'Nadpisy', 'gruppa' ) ),
			array( 'label' => __( 'Zdroj', 'gruppa' ), 'fields' => array( 'font_head_src' => array( 'type' => 'select', 'options' => $sources, 'std' => 'google' ) ) ),
			array( 'label' => __( 'Písmo', 'gruppa' ), 'fields' => array( 'font_head_name' => array( 'type' => 'font', 'def' => __( 'ako text', 'gruppa' ) ) ) ),
			array( 'label' => __( 'Rezy', 'gruppa' ), 'fields' => array( 'font_head_w' => array( 'type' => 'weights', 'def' => '100..900' ) ) ),
			array( 'note' => __( 'Rezy pre Google Fonts: variabilné písmo 100..900, statické napr. 400;700;800.', 'gruppa' ) ),
		),
	);

	/* ----- Typografia ----- */
	$typo = array(
		'big'   => array( 'Big', '.big,.big .elementor-heading-title', array( '2.5rem', '3.5rem', '4.5rem', '800', '1.1', '-0.03em' ) ),
		'h1'    => array( 'H1', 'h1,h1.elementor-heading-title', array( '2rem', '2.25rem', '2.5rem', '800', '1.2', '-0.02em' ) ),
		'h2'    => array( 'H2', 'h2,h2.elementor-heading-title', array( '1.625rem', '1.875rem', '2rem', '400', '1.3', '-0.015em' ) ),
		'h3'    => array( 'H3', 'h3,h3.elementor-heading-title', array( '1.375rem', '1.5rem', '1.625rem', '700', '1.4', '-0.01em' ) ),
		'h4'    => array( 'H4', 'h4,h4.elementor-heading-title', array( '1.25rem', '', '1.375rem', '400', '1.5', '' ) ),
		'h5'    => array( 'H5', 'h5,h5.elementor-heading-title', array( '1.125rem', '', '', '700', '1.6', '' ) ),
		'h6'    => array( 'H6', 'h6,h6.elementor-heading-title', array( '1rem', '', '', '600', '1.7', '' ) ),
		'p'     => array( __( 'P – odstavec', 'gruppa' ), 'p', array( '1rem', '', '', '400', '1.7', '' ) ),
		'bold'  => array( __( 'Bold – tučné', 'gruppa' ), 'strong,b,.bold,.bold p,.bold li', array( null, null, null, '700', null, '' ) ),
		'small' => array( __( 'Small – malé', 'gruppa' ), '.small,.small p', array( '0.875rem', '', '', '', '1.6', '' ) ),
		'li'    => array( __( 'Zoznamy – ul / ol / li', 'gruppa' ), 'ul:not([class])>li,ol:not([class])>li', array( '', '', '', '', '', '' ) ),
	);

	$typo_sections = array();
	foreach ( $typo as $key => $t ) {
		list( $title, $sel, $d ) = $t;
		$rows = array();
		if ( null !== $d[0] ) {
			$rows[] = array( 'label' => __( 'Veľkosť', 'gruppa' ), 'fields' => array( "typo_{$key}_size" => array( 'type' => 'unit', 'units' => $size_units, 'def' => $d[0], 'css' => array( array( $sel, 'font-size' ) ) ) ) );
			$rows[] = array( 'label' => __( '· tablet', 'gruppa' ), 'fields' => array( "typo_{$key}_size_t" => array( 'type' => 'unit', 'units' => $size_units, 'def' => $d[1], 'css' => array( array( $sel, 'font-size', 't' ) ) ) ) );
			$rows[] = array( 'label' => __( '· desktop', 'gruppa' ), 'fields' => array( "typo_{$key}_size_d" => array( 'type' => 'unit', 'units' => $size_units, 'def' => $d[2], 'css' => array( array( $sel, 'font-size', 'd' ) ) ) ) );
		}
		$rows[] = array( 'label' => __( 'Váha', 'gruppa' ), 'fields' => array( "typo_{$key}_weight" => array( 'type' => 'select', 'options' => $weights, 'def' => $d[3], 'css' => array( array( $sel, 'font-weight' ) ) ) ) );
		$rows[] = array( 'label' => __( 'Transformácia', 'gruppa' ), 'fields' => array( "typo_{$key}_transform" => array( 'type' => 'select', 'options' => $transform, 'def' => '', 'css' => array( array( $sel, 'text-transform' ) ) ) ) );
		$rows[] = array( 'label' => __( 'Štýl', 'gruppa' ), 'fields' => array( "typo_{$key}_style" => array( 'type' => 'select', 'options' => $style, 'def' => '', 'css' => array( array( $sel, 'font-style' ) ) ) ) );
		if ( null !== $d[4] ) {
			$rows[] = array( 'label' => __( 'Výška riadku', 'gruppa' ), 'fields' => array( "typo_{$key}_lh" => array( 'type' => 'unit', 'units' => $lh_units, 'def' => $d[4], 'css' => array( array( $sel, 'line-height' ) ) ) ) );
		}
		$rows[] = array( 'label' => __( 'Rozostup', 'gruppa' ), 'fields' => array( "typo_{$key}_ls" => array( 'type' => 'unit', 'units' => $ls_units, 'def' => $d[5], 'css' => array( array( $sel, 'letter-spacing' ) ) ) ) );

		$typo_sections[ 'typo_' . $key ] = array(
			'title' => $title,
			'panel' => 'typo',
			'rows'  => $rows,
		);
	}
	$typo_sections['typo_h1']['desc'] = __( 'Veľkosť bez breakpointu platí pre mobil. Tablet od 768 px, desktop od 1024 px. Pomlčka = preberá menší breakpoint.', 'gruppa' );

	/* ----- Tlačidlá ----- */
	$btn   = gruppa_selectors( 'button' );
	$btn_h = gruppa_selectors( 'button', ':hover' );

	$buttons = array(
		'title'  => __( 'Tlačidlá', 'gruppa' ),
		'panel'  => 'design',
		'desc'   => __( 'Tlačidlo Elementoru, triedy .button a .wp-element-button, odoslanie formulára (WP, JetFormBuilder). Nastavenie priamo vo widgete alebo v Elementor → Nastavenia stránky má prednosť.', 'gruppa' ),
		'static' => array(
			array( $btn, 'border-style:solid;transition:background-color .2s,border-color .2s,color .2s' ),
			array( '.button', 'display:inline-block;text-decoration:none' ),
		),
		'rows'   => array(
			array( 'columns' => true ),
			array( 'label' => __( 'Pozadie', 'gruppa' ), 'pair' => true, 'fields' => array(
				'btn_bg'   => array( 'type' => 'color', 'def' => 'var(--gruppa-blue)', 'ph' => 'accent', 'always' => true, 'css' => array( array( $btn, 'background-color' ) ) ),
				'btn_bg_h' => array( 'type' => 'color', 'def' => 'var(--gruppa-hover)', 'ph' => 'hover', 'always' => true, 'css' => array( array( $btn_h, 'background-color' ) ) ),
			) ),
			array( 'label' => __( 'Text', 'gruppa' ), 'pair' => true, 'fields' => array(
				'btn_color'   => array( 'type' => 'color', 'def' => 'var(--gruppa-white)', 'ph' => 'biela', 'always' => true, 'css' => array( array( $btn, 'color' ) ) ),
				'btn_color_h' => array( 'type' => 'color', 'def' => 'var(--gruppa-white)', 'ph' => 'biela', 'always' => true, 'css' => array( array( $btn_h, 'color' ) ) ),
			) ),
			array( 'label' => __( 'Okraj', 'gruppa' ), 'pair' => true, 'fields' => array(
				'btn_border'   => array( 'type' => 'color', 'def' => 'var(--gruppa-blue)', 'ph' => 'accent', 'always' => true, 'css' => array( array( $btn, 'border-color' ) ) ),
				'btn_border_h' => array( 'type' => 'color', 'def' => 'var(--gruppa-hover)', 'ph' => 'hover', 'always' => true, 'css' => array( array( $btn_h, 'border-color' ) ) ),
			) ),
			array( 'label' => __( 'Hrúbka okraja', 'gruppa' ), 'pair' => true, 'fields' => array(
				'btn_bw'   => array( 'type' => 'unit', 'units' => array( 'px', 'rem', 'em' ), 'def' => '0px', 'always' => true, 'css' => array( array( $btn, 'border-width' ) ) ),
				'btn_bw_h' => array( 'type' => 'unit', 'units' => array( 'px', 'rem', 'em' ), 'def' => '', 'css' => array( array( $btn_h, 'border-width' ) ) ),
			) ),
			array( 'label' => __( 'Padding', 'gruppa' ), 'pair' => true, 'fields' => array(
				'btn_pad'   => array( 'type' => 'box', 'def' => '0.875rem 1.75rem', 'always' => true, 'css' => array( array( $btn, 'padding' ) ) ),
				'btn_pad_h' => array( 'type' => 'box', 'def' => '', 'css' => array( array( $btn_h, 'padding' ) ) ),
			) ),
			array( 'label' => __( 'Margin', 'gruppa' ), 'pair' => true, 'fields' => array(
				'btn_mar'   => array( 'type' => 'box', 'def' => '', 'css' => array( array( $btn, 'margin' ) ) ),
				'btn_mar_h' => array( 'type' => 'box', 'def' => '', 'css' => array( array( $btn_h, 'margin' ) ) ),
			) ),
			array( 'label' => __( 'Zaoblenie', 'gruppa' ), 'pair' => true, 'fields' => array(
				'btn_rad'   => array( 'type' => 'box', 'def' => '0', 'always' => true, 'css' => array( array( $btn, 'border-radius' ) ) ),
				'btn_rad_h' => array( 'type' => 'box', 'def' => '', 'css' => array( array( $btn_h, 'border-radius' ) ) ),
			) ),
			array( 'note' => __( 'Padding, margin a zaoblenie: 1 až 4 hodnoty ako v CSS, napr. 0.875rem 1.75rem. Prázdne hover pole = rovnaké ako accent.', 'gruppa' ) ),
		),
	);

	/* ----- Formuláre ----- */
	$fld   = gruppa_selectors( 'field' );
	$fld_h = gruppa_selectors( 'field', ':hover' );

	$forms = array(
		'title' => __( 'Formuláre', 'gruppa' ),
		'panel' => 'design',
		'desc'  => __( 'Textové polia, textarea a select. Pri kliknutí do poľa (focus) ostáva okraj v accent farbe s jemným tieňom.', 'gruppa' ),
		'rows'  => array(
			array( 'columns' => true ),
			array( 'label' => __( 'Pozadie', 'gruppa' ), 'pair' => true, 'fields' => array(
				'form_bg'   => array( 'type' => 'color', 'def' => '', 'css' => array( array( $fld, 'background-color' ) ) ),
				'form_bg_h' => array( 'type' => 'color', 'def' => '', 'css' => array( array( $fld_h, 'background-color' ) ) ),
			) ),
			array( 'label' => __( 'Okraj', 'gruppa' ), 'pair' => true, 'fields' => array(
				'form_border'   => array( 'type' => 'color', 'def' => 'var(--gruppa-dark-gray)', 'ph' => 'text', 'css' => array( array( $fld, 'border-color' ) ) ),
				'form_border_h' => array( 'type' => 'color', 'def' => 'var(--gruppa-hover)', 'ph' => 'hover', 'css' => array( array( $fld_h, 'border-color' ) ) ),
			) ),
			array( 'label' => __( 'Hrúbka okraja', 'gruppa' ), 'pair' => true, 'fields' => array(
				'form_bw'   => array( 'type' => 'unit', 'units' => array( 'px', 'rem', 'em' ), 'def' => '1px', 'css' => array( array( $fld, 'border-width' ) ) ),
				'form_bw_h' => array( 'type' => 'unit', 'units' => array( 'px', 'rem', 'em' ), 'def' => '', 'css' => array( array( $fld_h, 'border-width' ) ) ),
			) ),
			array( 'label' => __( 'Text', 'gruppa' ), 'pair' => true, 'fields' => array(
				'form_color'   => array( 'type' => 'color', 'def' => 'var(--gruppa-black)', 'ph' => 'nadpisy', 'css' => array( array( $fld, 'color' ) ) ),
				'form_color_h' => array( 'type' => 'color', 'def' => '', 'css' => array( array( $fld_h, 'color' ) ) ),
			) ),
			array( 'label' => __( 'Placeholder', 'gruppa' ), 'pair' => true, 'fields' => array(
				'form_ph'   => array( 'type' => 'color', 'def' => '', 'css' => array( array( gruppa_selectors( 'field', '::placeholder' ), 'color' ) ) ),
				'form_ph_h' => array( 'type' => 'color', 'def' => '', 'css' => array( array( gruppa_selectors( 'field', ':hover::placeholder' ), 'color' ) ) ),
			) ),
			array( 'label' => __( 'Padding', 'gruppa' ), 'pair' => true, 'fields' => array(
				// select si nechá miesto na šípku vpravo
				'form_pad'   => array( 'type' => 'box', 'def' => '', 'css' => array( array( $fld, 'padding' ), array( 'select', 'padding-right', '', '2.75rem' ) ) ),
				'form_pad_h' => array( 'type' => 'box', 'def' => '', 'css' => array( array( $fld_h, 'padding' ), array( 'select:hover', 'padding-right', '', '2.75rem' ) ) ),
			) ),
			array( 'label' => __( 'Margin', 'gruppa' ), 'pair' => true, 'fields' => array(
				'form_mar'   => array( 'type' => 'box', 'def' => '', 'css' => array( array( $fld, 'margin' ) ) ),
				'form_mar_h' => array( 'type' => 'box', 'def' => '', 'css' => array( array( $fld_h, 'margin' ) ) ),
			) ),
			array( 'label' => __( 'Zaoblenie', 'gruppa' ), 'pair' => true, 'fields' => array(
				'form_rad'   => array( 'type' => 'box', 'def' => '', 'css' => array( array( $fld, 'border-radius' ) ) ),
				'form_rad_h' => array( 'type' => 'box', 'def' => '', 'css' => array( array( $fld_h, 'border-radius' ) ) ),
			) ),
		),
	);

	$schema = array_merge(
		array(
			'colors'  => $colors,
			'fonts'   => $fonts,
			'buttons' => $buttons,
			'forms'   => $forms,
		),
		$typo_sections
	);

	return $schema;
}

/**
 * Plochý zoznam polí: id => definícia.
 */
function gruppa_fields() {
	static $fields = null;
	if ( null === $fields ) {
		$fields = array();
		foreach ( gruppa_schema() as $section ) {
			foreach ( $section['rows'] as $row ) {
				if ( ! empty( $row['fields'] ) ) {
					foreach ( $row['fields'] as $id => $f ) {
						$fields[ $id ] = $f;
					}
				}
			}
		}
	}
	return $fields;
}

/**
 * Kompaktná mapa pre generátor (PHP aj živý náhľad v JS).
 */
function gruppa_css_map() {
	$map = array( 'fields' => array(), 'static' => array() );
	foreach ( gruppa_fields() as $id => $f ) {
		if ( empty( $f['var'] ) && empty( $f['css'] ) ) {
			continue;
		}
		$map['fields'][] = array(
			'id'     => 'gruppa_' . $id,
			'def'    => isset( $f['def'] ) ? $f['def'] : '',
			'always' => ! empty( $f['always'] ),
			'var'    => isset( $f['var'] ) ? $f['var'] : '',
			'focus'  => ! empty( $f['focus'] ),
			'css'    => isset( $f['css'] ) ? $f['css'] : array(),
		);
	}
	foreach ( gruppa_schema() as $section ) {
		if ( ! empty( $section['static'] ) ) {
			foreach ( $section['static'] as $s ) {
				$map['static'][] = $s;
			}
		}
	}
	return $map;
}

/** Hodnota nastavenia (theme_mod). */
function gruppa_mod( $id ) {
	$v = get_theme_mod( 'gruppa_' . $id, '' );
	return is_string( $v ) ? trim( $v ) : '';
}

/** Focus tieň z accent farby (15 %). */
function gruppa_focus_shadow( $color ) {
	if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color, $m ) ) {
		$h = $m[1];
		if ( 3 === strlen( $h ) ) {
			$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
		}
		return sprintf( '0 0 0 3px rgba(%d, %d, %d, 0.15)', hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ) );
	}
	return '0 0 0 3px color-mix(in srgb, ' . $color . ' 15%, transparent)';
}

/**
 * Písma: font-family pre text a nadpisy + URL pre Google Fonts.
 */
function gruppa_fonts() {
	$body_src  = gruppa_mod( 'font_body_src' ) ?: 'google';
	$body_name = gruppa_mod( 'font_body_name' );
	$head_src  = gruppa_mod( 'font_head_src' ) ?: 'google';
	$head_name = gruppa_mod( 'font_head_name' );

	$family = function ( $src, $name ) {
		if ( 'system' === $src ) {
			return GRUPPA_FONT_STACK;
		}
		return '' !== $name ? "'" . $name . "', " . GRUPPA_FONT_STACK : '';
	};

	$google = array();
	$body_g = '';
	if ( 'google' === $body_src ) {
		$body_g   = '' !== $body_name ? $body_name : 'Google Sans Flex';
		$google[] = gruppa_google_family( $body_g, gruppa_mod( 'font_body_w' ) ?: '100..900' );
	}
	if ( 'google' === $head_src && '' !== $head_name && $head_name !== $body_g ) {
		$google[] = gruppa_google_family( $head_name, gruppa_mod( 'font_head_w' ) ?: '100..900' );
	}

	return array(
		'body' => $family( $body_src, $body_name ),
		'head' => $family( $head_src, $head_name ),
		'url'  => $google ? 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $google ) . '&display=swap' : '',
	);
}

function gruppa_google_family( $name, $weights ) {
	return str_replace( ' ', '+', $name ) . ( '' !== $weights ? ':wght@' . $weights : '' );
}

/**
 * CSS z nastavení: :root premenné + zmenené pravidlá.
 */
function gruppa_css_dynamic() {
	$map    = gruppa_css_map();
	$root   = array();
	$blocks = array( '' => array(), 't' => array(), 'd' => array() );

	$add = function ( $mq, $sel, $decl ) use ( &$blocks ) {
		$blocks[ $mq ][ $sel ][] = $decl;
	};

	foreach ( $map['static'] as $s ) {
		$add( '', $s[0], $s[1] );
	}

	$fonts = gruppa_fonts();
	if ( '' !== $fonts['body'] ) {
		$add( '', 'body', 'font-family:' . $fonts['body'] );
	}
	if ( '' !== $fonts['head'] ) {
		$add( '', GRUPPA_HEADINGS, 'font-family:' . $fonts['head'] );
	}

	foreach ( $map['fields'] as $f ) {
		$v = get_theme_mod( $f['id'], '' );
		$v = is_string( $v ) ? trim( $v ) : '';

		if ( $f['var'] ) {
			$v      = '' !== $v ? $v : $f['def'];
			$root[] = $f['var'] . ':' . $v;
			if ( $f['focus'] ) {
				$root[] = '--gruppa-focus:' . gruppa_focus_shadow( $v );
			}
			continue;
		}
		if ( '' === $v && ! $f['always'] ) {
			continue;
		}
		$v = '' !== $v ? $v : $f['def'];
		if ( '' === $v ) {
			continue;
		}
		foreach ( $f['css'] as $r ) {
			$add( isset( $r[2] ) ? $r[2] : '', $r[0], $r[1] . ':' . ( isset( $r[3] ) ? $r[3] : $v ) );
		}
	}

	$css = ':root{' . implode( ';', $root ) . "}\n";
	foreach ( array( '' => '', 't' => '(min-width: 768px)', 'd' => '(min-width: 1024px)' ) as $mq => $query ) {
		if ( empty( $blocks[ $mq ] ) ) {
			continue;
		}
		$rules = '';
		foreach ( $blocks[ $mq ] as $sel => $decls ) {
			$rules .= $sel . '{' . implode( ';', $decls ) . "}\n";
		}
		$css .= $query ? '@media ' . $query . "{\n" . $rules . "}\n" : $rules;
	}
	return $css;
}

/**
 * CSS témy zo style.css – bez hlavičky témy a bez bloku FARBY
 * (farby sa nastavujú v Prispôsobiť a vypisujú sa ako :root).
 */
function gruppa_css_theme() {
	$css = (string) file_get_contents( get_template_directory() . '/style.css' );
	$css = preg_replace( '#^\s*/\*.*?\*/\s*#s', '', $css, 1 );
	$css = preg_replace( '#/\*\s*=+\s*FARBY\s*=+\s*\*/\s*:root\s*\{[^}]*\}\s*#s', '', $css, 1 );
	return trim( $css ) . "\n";
}

/** Aktuálne CSS témy – upravené v Prispôsobiť alebo zo style.css. */
function gruppa_css_base() {
	$edited = get_theme_mod( 'gruppa_base_css', '' );
	return is_string( $edited ) && '' !== trim( $edited ) ? $edited : gruppa_css_theme();
}

/** Jednoduchá minifikácia. */
function gruppa_css_minify( $css ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );
	$css = preg_replace( '/\s+/', ' ', $css );
	$css = preg_replace( '/\s*([{};,>])\s*/', '$1', $css );
	$css = str_replace( array( ': ', ';}' ), array( ':', '}' ), $css );
	return trim( $css );
}

/**
 * Zostaví CSS a uloží ho do uploads/gruppa/gruppa-{hash}.css.
 * Keď zápis zlyhá, CSS sa vypíše inline.
 */
function gruppa_css_build() {
	$fonts  = gruppa_fonts();
	$upload = wp_upload_dir( null, false );
	$dir    = trailingslashit( $upload['basedir'] ) . 'gruppa';
	$ok_dir = empty( $upload['error'] ) && wp_mkdir_p( $dir );

	// Google písma uložené na webe – @font-face ide priamo do CSS súboru.
	$local = false;
	if ( $fonts['url'] && $ok_dir && get_theme_mod( 'gruppa_font_local', true ) ) {
		$local = gruppa_fonts_local( $fonts['url'], $dir );
	}

	$css   = gruppa_css_minify( ( $local ? $local['css'] : '' ) . gruppa_css_base() . gruppa_css_dynamic() );
	$hash  = substr( md5( $css ), 0, 10 );
	$build = array(
		'file'    => '',
		'inline'  => '',
		'fonts'   => $local ? '' : $fonts['url'],
		'preload' => $local ? $local['preload'] : array(),
		'theme'   => GRUPPA_THEME_VERSION,
		'mtime'   => (int) filemtime( get_template_directory() . '/style.css' ),
	);

	$name = 'gruppa-' . $hash . '.css';

	if ( $ok_dir ) {
		foreach ( (array) glob( $dir . '/gruppa-*.css' ) as $old ) {
			if ( $old && basename( $old ) !== $name ) {
				wp_delete_file( $old );
			}
		}
		if ( file_exists( $dir . '/' . $name ) || false !== file_put_contents( $dir . '/' . $name, $css ) ) {
			$build['file'] = $name;
		}
	}
	if ( ! $build['file'] ) {
		// Bez CSS súboru nefungujú relatívne cesty k písmam → písma z Google.
		$build['inline']  = gruppa_css_minify( gruppa_css_base() . gruppa_css_dynamic() );
		$build['fonts']   = $fonts['url'];
		$build['preload'] = array();
	}

	update_option( 'gruppa_css_build', $build, true );
	return $build;
}

/**
 * Stiahne Google písma do uploads/gruppa/fonts/ a vráti @font-face s relatívnymi cestami.
 *
 * Sťahuje sa len pri zmene písma (výsledok je uložený v option gruppa_fonts_local).
 * Pri chybe vráti false a web načíta písma z Google ako doteraz.
 *
 * @return array{css:string,preload:string[]}|false
 */
function gruppa_fonts_local( $url, $dir ) {
	$fonts_dir = $dir . '/fonts';
	$cached    = get_option( 'gruppa_fonts_local' );

	if ( is_array( $cached ) && ( $cached['url'] ?? '' ) === $url && ! empty( $cached['files'] ) ) {
		$complete = true;
		foreach ( $cached['files'] as $file ) {
			if ( ! file_exists( $fonts_dir . '/' . $file ) ) {
				$complete = false;
				break;
			}
		}
		if ( $complete ) {
			return array( 'css' => $cached['css'], 'preload' => $cached['preload'] );
		}
	}

	// Moderný prehliadač → Google vráti woff2.
	$response = wp_remote_get( $url, array(
		'timeout'    => 15,
		'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
	) );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return false;
	}
	$css = wp_remote_retrieve_body( $response );
	if ( ! preg_match_all( '#(?:/\*\s*([\w-]+)\s*\*/\s*)?@font-face\s*\{[^}]*\}#', $css, $faces, PREG_SET_ORDER ) || ! wp_mkdir_p( $fonts_dir ) ) {
		return false;
	}

	$out     = '';
	$files   = array();
	$preload = array();

	foreach ( $faces as $face ) {
		$block  = $face[0];
		$subset = isset( $face[1] ) ? $face[1] : '';

		if ( ! preg_match_all( '#url\((https://fonts\.gstatic\.com/[^)\s]+)\)#', $block, $urls ) ) {
			continue;
		}
		foreach ( array_unique( $urls[1] ) as $src ) {
			$ext  = strtolower( pathinfo( wp_parse_url( $src, PHP_URL_PATH ), PATHINFO_EXTENSION ) ) ?: 'woff2';
			$file = substr( md5( $src ), 0, 12 ) . '.' . $ext;

			if ( ! file_exists( $fonts_dir . '/' . $file ) ) {
				$font = wp_remote_get( $src, array( 'timeout' => 15 ) );
				if ( is_wp_error( $font ) || 200 !== wp_remote_retrieve_response_code( $font ) || false === file_put_contents( $fonts_dir . '/' . $file, wp_remote_retrieve_body( $font ) ) ) {
					return false;
				}
			}
			$block   = str_replace( $src, 'fonts/' . $file, $block );
			$files[] = $file;

			// Preload: latin a latin-ext (slovenské znaky), max. 4 súbory.
			if ( in_array( $subset, array( 'latin', 'latin-ext' ), true ) && count( $preload ) < 4 && ! in_array( $file, $preload, true ) ) {
				$preload[] = $file;
			}
		}
		$out .= $block . "\n";
	}

	if ( '' === $out ) {
		return false;
	}

	// Staré súbory písma, ktoré už nie sú použité.
	foreach ( (array) glob( $fonts_dir . '/*' ) as $old ) {
		if ( $old && ! in_array( basename( $old ), $files, true ) ) {
			wp_delete_file( $old );
		}
	}

	update_option( 'gruppa_fonts_local', array(
		'url'     => $url,
		'css'     => $out,
		'files'   => array_values( array_unique( $files ) ),
		'preload' => $preload,
	), false );

	return array( 'css' => $out, 'preload' => $preload );
}
