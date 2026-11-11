<?php
/**
 * Gruppa – nastavenia v Vzhľad → Prispôsobiť.
 *
 * Štandardné sekcie WordPressu (Identita webu, Menu, Nastavenia úvodnej stránky)
 * zostávajú tak, ako ich dáva WordPress. Téma pridáva dva panely:
 *   Gruppa – dizajn       farby, písma, tlačidlá, formuláre, CSS témy
 *   Gruppa – typografia   Big, H1–H6, P, Bold, Small, zoznamy
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/css.php';

/* ==============================
   SANITIZÁCIA
   ============================== */

function gruppa_sanitize_color( $v ) {
	$v = trim( (string) $v );
	return preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s.,%\/]+\)|hsla?\([\d\s.,%\/deg]+\)|transparent|currentColor|var\(--gruppa-[a-z-]+\))$/', $v ) ? $v : '';
}

function gruppa_sanitize_unit( $v ) {
	$v = trim( (string) $v );
	return preg_match( '/^-?\d*\.?\d+(px|rem|em|%|vw|vh)?$/', $v ) ? $v : '';
}

function gruppa_sanitize_box( $v ) {
	$v = trim( preg_replace( '/\s+/', ' ', (string) $v ) );
	return preg_match( '/^((-?\d*\.?\d+(px|rem|em|%|vw|vh)?|auto)( |$)){1,4}$/', $v ) ? $v : '';
}

function gruppa_sanitize_font( $v ) {
	$v = trim( preg_replace( '/\s+/', ' ', (string) $v ) );
	return preg_match( '/^[\p{L}0-9 \-]{0,60}$/u', $v ) ? $v : '';
}

function gruppa_sanitize_weights( $v ) {
	$v = preg_replace( '/\s+/', '', (string) $v );
	return preg_match( '/^(\d{3}(\.\.\d{3})?)(;\d{3}(\.\.\d{3})?)*$/', $v ) ? $v : '';
}

function gruppa_sanitize_css( $v ) {
	$v = str_ireplace( '</style', '', (string) $v );
	// Rovnaké ako CSS témy = neuložiť kópiu, nech sa pri aktualizácii témy použije nové style.css.
	return trim( $v ) === trim( gruppa_css_theme() ) ? '' : $v;
}

/**
 * Neupravené CSS sa neukladá – editor aj tak zobrazí aktuálne style.css témy.
 */
add_filter( 'theme_mod_gruppa_base_css', function ( $v ) {
	return is_string( $v ) && '' !== trim( $v ) ? $v : gruppa_css_theme();
}, 20 );

/* ==============================
   REGISTRÁCIA
   ============================== */

add_action( 'customize_register', function ( WP_Customize_Manager $wp_customize ) {

	/**
	 * Riadok: popis vľavo, jedno alebo dve polia (accent / hover) vpravo.
	 * Polia sú na jednej zvislej línii naprieč celou sekciou.
	 */
	class Gruppa_Row_Control extends WP_Customize_Control {
		public $type   = 'gruppa_row';
		public $fields = array();
		public $pair   = false;

		public function render_content() {
			echo '<div class="gruppa-row' . ( $this->pair ? ' is-pair' : '' ) . '">';
			echo '<span class="gruppa-label">' . esc_html( $this->label ) . '</span>';
			foreach ( $this->fields as $key => $f ) {
				$this->field( $f, $this->settings[ $key ]->id, (string) $this->value( $key ) );
			}
			echo '</div>';
		}

		private function field( $f, $id, $value ) {
			$def = isset( $f['def'] ) ? (string) $f['def'] : '';
			$ph  = isset( $f['ph'] ) ? $f['ph'] : ( '' !== $def ? $def : '–' );

			switch ( $f['type'] ) {
				case 'color':
					$swatch = gruppa_resolve_color( '' !== $value ? $value : $def );
					printf(
						'<span class="gruppa-color"><span class="gruppa-swatch" style="background-color:%1$s" data-default="%2$s"><input type="color" value="%3$s" tabindex="-1" aria-hidden="true"></span><input type="text" class="gruppa-in" data-setting="%4$s" value="%5$s" placeholder="%6$s" spellcheck="false"></span>',
						esc_attr( $swatch ? $swatch : 'transparent' ),
						esc_attr( gruppa_resolve_color( $def ) ),
						esc_attr( preg_match( '/^#[0-9a-f]{6}$/i', $swatch ) ? $swatch : '#ffffff' ),
						esc_attr( $id ),
						esc_attr( $value ),
						esc_attr( $ph )
					);
					break;

				case 'unit':
					$units = $f['units'];
					preg_match( '/^(-?\d*\.?\d+)([a-z%]*)$/', $value, $mv );
					preg_match( '/^(-?\d*\.?\d+)([a-z%]*)$/', $def, $md );
					$num  = $mv ? $mv[1] : '';
					$unit = $mv ? $mv[2] : ( $md ? $md[2] : $units[0] );
					if ( ! in_array( $unit, $units, true ) ) {
						$units[] = $unit;
					}
					printf( '<span class="gruppa-unit" data-setting="%s"><input type="number" step="any" class="gruppa-num" value="%s" placeholder="%s">', esc_attr( $id ), esc_attr( $num ), esc_attr( $md ? $md[1] : '–' ) );
					echo '<select class="gruppa-u">';
					foreach ( $units as $u ) {
						printf( '<option value="%s"%s>%s</option>', esc_attr( $u ), selected( $u, $unit, false ), esc_html( '' === $u ? '–' : $u ) );
					}
					echo '</select></span>';
					break;

				case 'select':
					printf( '<select class="gruppa-in" data-setting="%s">', esc_attr( $id ) );
					foreach ( $f['options'] as $k => $label ) {
						$k = (string) $k;
						if ( '' === $k ) {
							$label = '' !== $def ? $def . ' · ' . __( 'téma', 'gruppa' ) : '–';
						}
						printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $k, $value, false ), esc_html( $label ) );
					}
					echo '</select>';
					break;

				default: // text, box, font, weights
					printf( '<input type="text" class="gruppa-in" data-setting="%s" value="%s" placeholder="%s" spellcheck="false">', esc_attr( $id ), esc_attr( $value ), esc_attr( $ph ) );
			}
		}
	}

	/** Nadpis skupiny, hlavička stĺpcov Accent / Hover alebo poznámka. */
	class Gruppa_Note_Control extends WP_Customize_Control {
		public $type = 'gruppa_note';
		public $mode = 'heading';

		public function render_content() {
			if ( 'columns' === $this->mode ) {
				echo '<div class="gruppa-row is-pair gruppa-cols"><span></span><span>Accent</span><span>Hover</span></div>';
			} elseif ( 'note' === $this->mode ) {
				echo '<p class="gruppa-note">' . esc_html( $this->label ) . '</p>';
			} else {
				echo '<h4 class="gruppa-sub">' . esc_html( $this->label ) . '</h4>';
			}
		}
	}

	$wp_customize->add_panel( 'gruppa_design', array(
		'title'    => __( 'Gruppa – dizajn', 'gruppa' ),
		'priority' => 25,
	) );
	$wp_customize->add_panel( 'gruppa_typo', array(
		'title'    => __( 'Gruppa – typografia', 'gruppa' ),
		'priority' => 26,
	) );

	$sanitize = array(
		'color'   => 'gruppa_sanitize_color',
		'unit'    => 'gruppa_sanitize_unit',
		'box'     => 'gruppa_sanitize_box',
		'font'    => 'gruppa_sanitize_font',
		'weights' => 'gruppa_sanitize_weights',
	);

	$priority = 10;
	foreach ( gruppa_schema() as $section_id => $section ) {
		$section_id = 'gruppa_' . $section_id;
		$wp_customize->add_section( $section_id, array(
			'title'       => $section['title'],
			'panel'       => 'gruppa_' . $section['panel'],
			'description' => isset( $section['desc'] ) ? $section['desc'] : '',
			'priority'    => $priority++,
		) );

		foreach ( $section['rows'] as $i => $row ) {
			$control_id = $section_id . '_row' . $i;

			if ( empty( $row['fields'] ) ) {
				$mode = ! empty( $row['columns'] ) ? 'columns' : ( isset( $row['note'] ) ? 'note' : 'heading' );
				$wp_customize->add_control( new Gruppa_Note_Control( $wp_customize, $control_id, array(
					'section'  => $section_id,
					'settings' => array(),
					'label'    => isset( $row['note'] ) ? $row['note'] : ( isset( $row['heading'] ) ? $row['heading'] : '' ),
					'mode'     => $mode,
					'priority' => $i,
				) ) );
				continue;
			}

			$settings = array();
			foreach ( $row['fields'] as $field_id => $f ) {
				$setting_id = 'gruppa_' . $field_id;
				if ( 'select' === $f['type'] ) {
					$options  = $f['options'];
					$callback = function ( $v ) use ( $options, $f ) {
						$v = (string) $v;
						return array_key_exists( $v, $options ) ? $v : ( isset( $f['std'] ) ? $f['std'] : '' );
					};
				} else {
					$callback = $sanitize[ $f['type'] ];
				}
				$wp_customize->add_setting( $setting_id, array(
					'default'           => isset( $f['std'] ) ? $f['std'] : '',
					'transport'         => 'postMessage',
					'sanitize_callback' => $callback,
				) );
				$settings[ $field_id ] = $setting_id;
			}

			$wp_customize->add_control( new Gruppa_Row_Control( $wp_customize, $control_id, array(
				'section'  => $section_id,
				'settings' => $settings,
				'label'    => $row['label'],
				'fields'   => $row['fields'],
				'pair'     => ! empty( $row['pair'] ),
				'priority' => $i,
			) ) );
		}
	}

	/* ----- Písma na webe ----- */
	$wp_customize->add_setting( 'gruppa_font_local', array(
		'default'           => true,
		'transport'         => 'postMessage',
		'sanitize_callback' => 'wp_validate_boolean',
	) );
	$wp_customize->add_control( 'gruppa_font_local', array(
		'section'     => 'gruppa_fonts',
		'type'        => 'checkbox',
		'label'       => __( 'Google písma uložiť na web', 'gruppa' ),
		'description' => __( 'Pri uložení sa písma stiahnu do uploads/gruppa/fonts a načítavajú sa z vlastnej domény – bez pripojenia ku Googlu (GDPR). Náhľad v Prispôsobiť používa Google priamo.', 'gruppa' ),
		'priority'    => 99,
	) );

	/* ----- Elementor ----- */
	$wp_customize->add_setting( 'gruppa_elementor_inherit', array(
		'default'           => true,
		'transport'         => 'postMessage',
		'sanitize_callback' => 'wp_validate_boolean',
	) );
	$wp_customize->add_control( 'gruppa_elementor_inherit', array(
		'section'     => 'gruppa_fonts',
		'type'        => 'checkbox',
		'label'       => __( 'Elementor preberá farby a písma z témy', 'gruppa' ),
		'description' => __( 'Vypne predvolené farby a písma Elementoru. Po vypnutí platia predvolené hodnoty Elementoru – farby a písma sa potom nastavujú v Elementor → Nastavenia stránky.', 'gruppa' ),
		'priority'    => 100,
	) );

	/* ----- CSS témy ----- */
	$wp_customize->add_section( 'gruppa_css', array(
		'title'       => __( 'CSS témy', 'gruppa' ),
		'panel'       => 'gruppa_design',
		'priority'    => 100,
		'description' => __( 'Celé CSS témy na úpravu. Farby, písma, typografia, tlačidlá a formuláre z ostatných sekcií majú prednosť – ich hodnoty sa vypisujú až za týmto CSS. Vymazaním celého obsahu sa vráti pôvodné CSS témy.', 'gruppa' ),
	) );
	$wp_customize->add_setting( 'gruppa_base_css', array(
		'default'           => gruppa_css_theme(),
		'transport'         => 'postMessage',
		'capability'        => 'edit_css',
		'sanitize_callback' => 'gruppa_sanitize_css',
	) );
	$wp_customize->add_control( new WP_Customize_Code_Editor_Control( $wp_customize, 'gruppa_base_css', array(
		'section'   => 'gruppa_css',
		'code_type' => 'text/css',
		'label'     => '',
	) ) );
} );

/**
 * Farba pre náhľadový štvorček – var(--gruppa-*) preloží na hex z nastavení.
 */
function gruppa_resolve_color( $color, $depth = 0 ) {
	if ( preg_match( '/^var\((--gruppa-[a-z-]+)\)$/', $color, $m ) && $depth < 3 ) {
		foreach ( gruppa_fields() as $id => $f ) {
			if ( isset( $f['var'] ) && $f['var'] === $m[1] ) {
				$v = gruppa_mod( $id );
				return gruppa_resolve_color( '' !== $v ? $v : $f['def'], $depth + 1 );
			}
		}
		return '';
	}
	return $color;
}

/* ==============================
   ULOŽENIE → prestavba CSS súboru
   ============================== */

add_action( 'customize_save_after', function () {
	gruppa_css_build();
	gruppa_sync_elementor();
} );

/* ==============================
   OVLÁDACÍ PANEL – skripty a štýly
   ============================== */

add_action( 'customize_controls_enqueue_scripts', function () {
	$uri = get_template_directory_uri() . '/assets/';
	wp_enqueue_style( 'gruppa-customizer', $uri . 'customizer.css', array(), GRUPPA_THEME_VERSION );
	wp_enqueue_script( 'gruppa-customizer', $uri . 'customizer.js', array( 'customize-controls', 'jquery' ), GRUPPA_THEME_VERSION, true );
} );

/* ==============================
   NÁHĽAD – inline CSS + živé zmeny
   ============================== */

/**
 * V náhľade: CSS témy a CSS z nastavení ako dva inline bloky,
 * ktoré preview.js prepisuje pri každej zmene.
 */
function gruppa_preview_styles( $deps ) {
	$fonts = gruppa_fonts();
	if ( $fonts['url'] ) {
		wp_enqueue_style( 'gruppa-font', $fonts['url'], array(), null );
		$deps[] = 'gruppa-font';
	}
	wp_register_style( 'gruppa-base', false, $deps, null );
	wp_enqueue_style( 'gruppa-base' );
	wp_add_inline_style( 'gruppa-base', gruppa_css_base() );

	wp_register_style( 'gruppa', false, array( 'gruppa-base' ), null );
	wp_enqueue_style( 'gruppa' );
	wp_add_inline_style( 'gruppa', gruppa_css_dynamic() );
}

add_action( 'customize_preview_init', function () {
	wp_enqueue_script( 'gruppa-preview', get_template_directory_uri() . '/assets/preview.js', array( 'customize-preview' ), GRUPPA_THEME_VERSION, true );
	wp_localize_script( 'gruppa-preview', 'gruppaPreview', array(
		'map'       => gruppa_css_map(),
		'stack'     => GRUPPA_FONT_STACK,
		'headings'  => GRUPPA_HEADINGS,
		'themeCss'  => gruppa_css_theme(),
	) );
} );
