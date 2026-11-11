<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gruppa Table Builder — settings, available columns and the frontend output.
 *
 * Origin (settings[origin]) = where the data lives — see Gruppa_TB_Source:
 *   gruppa  Gruppa CMS content types   jet  JetEngine CCT   wp  WordPress post types + ACF / JetEngine meta
 *
 * Rows from (settings[source])
 *   repeater  rows of a Repeater field of one item (Current item / by ID / by slug), columns = its sub-fields
 *   items     items of the content type, columns = fields and system columns
 */
class Gruppa_TB {

	const FORMATS = array(
		'auto'      => 'Automatic',
		'text'      => 'Text',
		'number'    => 'Number',
		'date'      => 'Date',
		'datetime'  => 'Date and time',
		'time'      => 'Time',
		'yesno'     => 'Yes / No',
		'image'     => 'Image',
		'link'      => 'Link (URL)',
		'item_link' => 'Link to the item page',
	);

	private static $count = 0;

	public static function defaults() {
		return array(
			'origin'        => 'gruppa',
			'source'        => 'items',
			'field'         => '',
			'item_source'   => 'current',
			'item_value'    => '',
			'columns'       => array(),
			'orderby'       => 'order_index',
			'order'         => 'asc',
			'per_page'      => 20,
			'group'         => 0,
			'context_field' => '',
			'only_mine'     => 0,
			'header'        => 1,
			'mobile'        => 'cards',
			'empty_text'    => 'Nothing to show.',
		);
	}

	public static function column_defaults() {
		return array(
			'key'      => '',
			'label'    => '',
			'format'   => 'auto',
			'decimals' => '',
			'dec_point'     => '',
			'thousands_sep' => '',
			'prefix'   => '',
			'suffix'   => '',
			'align'    => 'left',
			'width'    => '',
			'sortable' => 1,
		);
	}

	public static function sources() {
		return array(
			'items'    => 'Items of the content type',
			'repeater' => 'Rows of a Repeater field',
		);
	}

	/** Column / field key: letters, digits, _ and - (JetEngine and ACF names keep their case). */
	public static function key( $k ) {
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $k );
	}

	/** Number separator: up to 3 characters, a space is kept (wp_strip_all_tags would trim it). */
	public static function sep( $v ) {
		$v = preg_replace( '/<[^>]*>|[\r\n\t]/', '', (string) $v );
		return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, 3 ) : substr( $v, 0, 3 );
	}

	/* ======================================================
	 * Available columns
	 * ==================================================== */

	/**
	 * Columns a table can show: [ key => meta ] (see Gruppa_TB_Source).
	 *
	 * @param Gruppa_TB_Source|null $src
	 * @param string                $slug     Content type of the source.
	 * @param string                $source   items | repeater
	 * @param string                $repeater Repeater key (source repeater).
	 */
	public static function available( $src, $slug, $source, $repeater = '' ) {
		if ( ! $src || ! $src->available() || '' === (string) $slug ) {
			return array();
		}
		if ( 'repeater' === $source ) {
			$reps = $src->repeaters( $slug );
			return isset( $reps[ $repeater ] ) ? (array) $reps[ $repeater ]['sub'] : array();
		}
		return $src->columns( $slug );
	}

	/** Source of a saved table. */
	public static function source_of( $t ) {
		$origin = isset( $t->settings['origin'] ) ? $t->settings['origin'] : 'gruppa';
		return Gruppa_TB_Source::get( $origin );
	}

	/* ======================================================
	 * Sanitize
	 * ==================================================== */

	public static function clean( $raw, $src, $slug ) {
		$d   = self::defaults();
		$raw = is_array( $raw ) ? $raw : array();
		$s   = array(
			'origin'        => $src ? $src->id() : 'gruppa',
			'source'        => isset( $raw['source'] ) && isset( self::sources()[ $raw['source'] ] ) ? $raw['source'] : 'items',
			'field'         => self::key( isset( $raw['field'] ) ? $raw['field'] : '' ),
			'item_source'   => isset( $raw['item_source'] ) && in_array( $raw['item_source'], array( 'current', 'id', 'slug' ), true ) ? $raw['item_source'] : 'current',
			'item_value'    => sanitize_text_field( isset( $raw['item_value'] ) ? $raw['item_value'] : '' ),
			'columns'       => array(),
			'orderby'       => self::key( isset( $raw['orderby'] ) && '' !== $raw['orderby'] ? $raw['orderby'] : 'order_index' ),
			'order'         => isset( $raw['order'] ) && 'desc' === $raw['order'] ? 'desc' : 'asc',
			'per_page'      => max( 1, min( 500, (int) ( isset( $raw['per_page'] ) ? $raw['per_page'] : $d['per_page'] ) ) ),
			'group'         => (int) ( isset( $raw['group'] ) ? $raw['group'] : 0 ),
			'context_field' => self::key( isset( $raw['context_field'] ) ? $raw['context_field'] : '' ),
			'only_mine'     => empty( $raw['only_mine'] ) ? 0 : 1,
			'header'        => empty( $raw['header'] ) ? 0 : 1,
			'mobile'        => isset( $raw['mobile'] ) && 'scroll' === $raw['mobile'] ? 'scroll' : 'cards',
			'empty_text'    => sanitize_text_field( isset( $raw['empty_text'] ) ? $raw['empty_text'] : $d['empty_text'] ),
		);
		if ( 'slug' === $s['item_source'] && $src && ! $src->has_slug() ) {
			$s['item_source'] = 'id';
		}
		$avail = self::available( $src, $slug, $s['source'], $s['field'] );
		$cols  = isset( $raw['columns'] ) ? $raw['columns'] : array();
		if ( is_string( $cols ) ) {
			$cols = json_decode( wp_unslash( $cols ), true );
		}
		foreach ( (array) $cols as $c ) {
			$key = isset( $c['key'] ) ? self::key( $c['key'] ) : '';
			if ( ! isset( $avail[ $key ] ) ) {
				continue;
			}
			$fmt = isset( $c['format'] ) && isset( self::FORMATS[ $c['format'] ] ) ? $c['format'] : 'auto';
			if ( 'item_link' === $fmt && 'items' !== $s['source'] ) {
				$fmt = 'auto';
			}
			$dec             = isset( $c['decimals'] ) && '' !== (string) $c['decimals'] ? max( 0, min( 6, (int) $c['decimals'] ) ) : '';
			$s['columns'][] = array(
				'key'      => $key,
				'label'    => sanitize_text_field( isset( $c['label'] ) ? $c['label'] : '' ),
				'format'   => $fmt,
				'decimals' => $dec,
				'dec_point'     => self::sep( isset( $c['dec_point'] ) ? $c['dec_point'] : '' ),
				'thousands_sep' => self::sep( isset( $c['thousands_sep'] ) ? $c['thousands_sep'] : '' ),
				'prefix'   => sanitize_text_field( isset( $c['prefix'] ) ? $c['prefix'] : '' ),
				'suffix'   => sanitize_text_field( isset( $c['suffix'] ) ? $c['suffix'] : '' ),
				'align'    => isset( $c['align'] ) && in_array( $c['align'], array( 'left', 'center', 'right' ), true ) ? $c['align'] : 'left',
				'width'    => preg_replace( '/[^0-9.%a-z]/', '', strtolower( isset( $c['width'] ) ? (string) $c['width'] : '' ) ),
				'sortable' => empty( $c['sortable'] ) ? 0 : 1,
			);
		}
		return $s;
	}

	/* ======================================================
	 * Frontend
	 * ==================================================== */

	public static function init() {
		add_shortcode( 'gruppa_table', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'elementor/editor/after_enqueue_styles', array( __CLASS__, 'register_assets' ) );
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		if ( ! wp_style_is( 'gruppa-table', 'registered' ) ) {
			wp_register_style( 'gruppa-table', GRUPPA_TB_URL . 'assets/gruppa-table.css', array(), GRUPPA_TB_VERSION );
			wp_register_script( 'gruppa-table', GRUPPA_TB_URL . 'assets/gruppa-table.js', array(), GRUPPA_TB_VERSION, true );
		}
	}

	public static function shortcode( $atts ) {
		$a = shortcode_atts( array( 'table' => '', 'id' => '' ), $atts, 'gruppa_table' );
		return self::render( '' !== $a['table'] ? $a['table'] : $a['id'] );
	}

	private static function editor_note( $text ) {
		return current_user_can( 'edit_posts' ) ? '<div class="gruppa-table-message">Gruppa Table: ' . esc_html( $text ) . '</div>' : '';
	}

	/**
	 * HTML of a table (by slug or ID).
	 *
	 * @param array $o Display overrides (Elementor widget): mobile = cards | scroll, width = auto | equal | custom.
	 */
	public static function render( $ref, $o = array() ) {
		$t = '' !== (string) $ref ? Gruppa_TB_DB::get_by( $ref ) : null;
		if ( ! $t || ( 'publish' !== $t->status && ! current_user_can( 'manage_options' ) ) ) {
			return self::editor_note( $ref ? 'table not found or not published.' : 'select a table.' );
		}
		$src = self::source_of( $t );
		if ( ! $src ) {
			return self::editor_note( 'unknown data source.' );
		}
		if ( ! $src->available() ) {
			return self::editor_note( $src->missing_text() );
		}
		$slug = $t->type_slug;
		if ( ! $src->exists( $slug ) ) {
			return self::editor_note( 'content type not found.' );
		}
		if ( 'gruppa' === $src->id() ) {
			$type = $src->type( $slug );
			if ( ! $type || 'publish' !== $type->status ) {
				return self::editor_note( 'content type not found.' );
			}
		}
		$s = $t->settings;
		if ( ! $s['columns'] ) {
			return self::editor_note( 'add columns to the table.' );
		}
		self::register_assets();
		wp_enqueue_style( 'gruppa-table' );
		wp_enqueue_script( 'gruppa-table' );
		self::$count++;

		$avail = self::available( $src, $slug, $s['source'], $s['field'] );
		$rows  = array(); // [ [ key => [ html, sort ] ] ]
		$pager = '';

		if ( 'repeater' === $s['source'] ) {
			$data = $src->repeater_rows( $slug, $s );
			if ( null === $data ) {
				if ( current_user_can( 'edit_posts' ) ) {
					return self::editor_note( 'no current item here — the table shows the rows of the item whose page it is on (use it in the single template).' );
				}
				$data = array();
			}
			foreach ( $data as $r ) {
				$cells = array();
				foreach ( $s['columns'] as $c ) {
					$meta               = isset( $avail[ $c['key'] ] ) ? $avail[ $c['key'] ] : Gruppa_TB_Source::meta( $c['key'], 'text' );
					$cells[ $c['key'] ] = self::sub_cell( $meta, is_array( $r ) && isset( $r[ $c['key'] ] ) ? $r[ $c['key'] ] : '', $c );
				}
				$rows[] = $cells;
			}
		} else {
			$per   = (int) $s['per_page'];
			$param = 'tp' . (int) $t->id;
			$page  = isset( $_GET[ $param ] ) ? max( 1, absint( $_GET[ $param ] ) ) : 1; // phpcs:ignore
			list( $items, $total ) = $src->query( $slug, $s, $page, $per );
			foreach ( $items as $item ) {
				$cells = array();
				foreach ( $s['columns'] as $c ) {
					$meta               = isset( $avail[ $c['key'] ] ) ? $avail[ $c['key'] ] : Gruppa_TB_Source::meta( $c['key'], 'text' );
					$cells[ $c['key'] ] = self::item_cell( $src, $slug, $item, $c, $meta );
				}
				$rows[] = $cells;
			}
			$pages = (int) ceil( $total / $per );
			if ( $pages > 1 ) {
				$base  = add_query_arg( $param, '%#%' );
				$links = paginate_links( array(
					'base'      => esc_url_raw( str_replace( '%25%23%25', '%#%', $base ) ),
					'format'    => '',
					'current'   => $page,
					'total'     => $pages,
					'prev_text' => '‹',
					'next_text' => '›',
				) );
				$pager = '<nav class="gruppa-table-pagination" aria-label="Pagination">' . $links . '</nav>';
			}
		}

		$mobile  = isset( $o['mobile'] ) && in_array( $o['mobile'], array( 'cards', 'scroll' ), true ) ? $o['mobile'] : $s['mobile'];
		$classes = 'gruppa-table gruppa-table--' . $mobile;
		if ( isset( $o['width'] ) && in_array( $o['width'], array( 'equal', 'custom' ), true ) ) {
			$classes .= ' gruppa-table--w-' . $o['width'];
		}
		$html    = '<div class="' . esc_attr( $classes ) . '" data-table="' . (int) $t->id . '">';
		if ( 'draft' === $t->status ) {
			$html .= '<div class="gruppa-table-message">Only for admins: this table is a draft.</div>';
		}
		if ( ! $rows ) {
			$html .= '<div class="gruppa-table-empty">' . esc_html( $s['empty_text'] ) . '</div></div>';
			return $html;
		}
		$html .= '<div class="gruppa-table-scroll"><table>';
		$cols  = '';
		foreach ( $s['columns'] as $c ) {
			$cols .= '<col' . ( '' !== $c['width'] ? ' style="width:' . esc_attr( is_numeric( $c['width'] ) ? $c['width'] . 'px' : $c['width'] ) . '"' : '' ) . '>';
		}
		$html .= '<colgroup>' . $cols . '</colgroup>';
		if ( $s['header'] ) {
			$html .= '<thead><tr>';
			foreach ( $s['columns'] as $i => $c ) {
				$label = self::label( $c, $avail );
				$html .= sprintf(
					'<th scope="col" class="gt-align-%s"%s>%s</th>',
					esc_attr( $c['align'] ),
					$c['sortable'] ? ' data-sort="' . (int) $i . '" tabindex="0" aria-sort="none"' : '',
					$c['sortable'] ? '<span class="gruppa-table-sort">' . esc_html( $label ) . '</span>' : esc_html( $label )
				);
			}
			$html .= '</tr></thead>';
		}
		$html .= '<tbody>';
		foreach ( $rows as $cells ) {
			$html .= '<tr>';
			foreach ( $s['columns'] as $c ) {
				list( $cell, $sort ) = $cells[ $c['key'] ];
				$html .= sprintf(
					'<td class="gt-align-%s" data-label="%s" data-sort-value="%s">%s</td>',
					esc_attr( $c['align'] ), esc_attr( self::label( $c, $avail ) ), esc_attr( $sort ), '' === $cell ? '<span class="gruppa-table-none">–</span>' : $cell
				);
			}
			$html .= '</tr>';
		}
		$html .= '</tbody></table></div>' . $pager . '</div>';
		return $html;
	}

	private static function label( $c, $avail ) {
		if ( '' !== $c['label'] ) {
			return $c['label'];
		}
		return isset( $avail[ $c['key'] ] ) ? $avail[ $c['key'] ]['label'] : $c['key'];
	}

	/* ---------- Cells: [ html, sort value ] ---------- */

	private static function fmt_number( $v, $c ) {
		if ( is_string( $v ) && preg_match( '/^-?\d+,\d+$/', trim( $v ) ) ) {
			$v = str_replace( ',', '.', trim( $v ) );
		}
		if ( '' === (string) $v || ! is_numeric( $v ) ) {
			return esc_html( (string) $v );
		}
		$dp = isset( $c['dec_point'] ) ? (string) $c['dec_point'] : '';
		$ts = isset( $c['thousands_sep'] ) ? (string) $c['thousands_sep'] : '';
		if ( ( '' !== $dp || '' !== $ts ) && class_exists( 'Gruppa_Render' ) && method_exists( 'Gruppa_Render', 'number' ) ) {
			return esc_html( Gruppa_Render::number( $v + 0, array( 'decimals' => $c['decimals'], 'dec_point' => $dp, 'thousands_sep' => $ts ) ) );
		}
		$dec = '' === (string) $c['decimals'] ? ( floor( $v ) == $v ? 0 : strlen( substr( strrchr( (string) ( $v + 0 ), '.' ), 1 ) ) ) : (int) $c['decimals']; // phpcs:ignore
		if ( '' !== $dp || '' !== $ts ) {
			global $wp_locale;
			$ldp = isset( $wp_locale->number_format['decimal_point'] ) ? $wp_locale->number_format['decimal_point'] : '.';
			$lts = isset( $wp_locale->number_format['thousands_sep'] ) ? $wp_locale->number_format['thousands_sep'] : ',';
			return esc_html( number_format( (float) $v, $dec, '' !== $dp ? $dp : $ldp, '' !== $ts ? $ts : $lts ) );
		}
		return esc_html( number_format_i18n( (float) $v, $dec ) );
	}

	private static function wrap( $html, $c ) {
		if ( '' === $html ) {
			return '';
		}
		return ( '' !== $c['prefix'] ? esc_html( $c['prefix'] ) . ' ' : '' ) . $html . ( '' !== $c['suffix'] ? ' ' . esc_html( $c['suffix'] ) : '' );
	}

	/** Thumbnail(s) from attachment IDs or URLs; $all = every image (gallery). */
	private static function image( $ids, $all = false ) {
		$out = '';
		foreach ( (array) $ids as $id ) {
			$aid = is_numeric( $id ) ? (int) $id : attachment_url_to_postid( (string) $id );
			if ( $aid ) {
				$img = wp_get_attachment_image( $aid, 'thumbnail', false, array( 'class' => 'gruppa-table-img', 'loading' => 'lazy' ) );
			} elseif ( is_string( $id ) && preg_match( '#^(https?:)?//#', $id ) ) {
				$img = '<img src="' . esc_url( $id ) . '" class="gruppa-table-img" loading="lazy" alt="">';
			} else {
				$img = '';
			}
			if ( '' !== $img ) {
				$out .= $img;
				if ( ! $all ) {
					break;
				}
			}
		}
		return $out;
	}

	private static function link( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$label = preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $url ) );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	private static function yesno( $v ) {
		return Gruppa_TB_Source::truthy( $v ) ? esc_html__( 'Yes' ) : esc_html__( 'No' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- WordPress core strings.
	}

	private static function lower( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
	}

	/** Cells shared by items and Repeater rows; null = plain text (handled by the caller). */
	private static function formatted( $fmt, $raw, $c ) {
		switch ( $fmt ) {
			case 'number':
				$n = is_string( $raw ) ? str_replace( ',', '.', trim( $raw ) ) : $raw;
				return array( self::wrap( self::fmt_number( $raw, $c ), $c ), is_numeric( $n ) ? $n + 0 : '' );
			case 'date':
			case 'datetime':
				$d = Gruppa_TB_Source::normalize_date( is_scalar( $raw ) ? $raw : '' );
				$f = get_option( 'date_format' ) . ( 'datetime' === $fmt ? ' ' . get_option( 'time_format' ) : '' );
				return array( self::wrap( $d ? esc_html( mysql2date( $f, $d ) ) : '', $c ), $d );
			case 'time':
				$v = is_scalar( $raw ) ? substr( trim( (string) $raw ), 0, 5 ) : '';
				return array( self::wrap( '' === $v ? '' : esc_html( date_i18n( get_option( 'time_format' ), strtotime( '2000-01-01 ' . $v ) ) ), $c ), $v );
			case 'yesno':
				return array( self::wrap( self::yesno( $raw ), $c ), Gruppa_TB_Source::truthy( $raw ) ? 1 : 0 );
		}
		return null;
	}

	/** Cell of an item column. */
	private static function item_cell( $src, $slug, $item, $c, $meta ) {
		$key   = $c['key'];
		$ftype = $meta['type'];
		$raw   = $src->value( $slug, $item, $key );
		$fmt   = 'auto' === $c['format'] ? self::auto_format( $ftype ) : $c['format'];
		$cell  = self::formatted( $fmt, $raw, $c );
		if ( null !== $cell ) {
			return $cell;
		}
		switch ( $fmt ) {
			case 'image':
				return array( self::image( $src->media( $slug, $item, $key ), 'gallery' === $ftype ), '' );
			case 'link':
				$url = $src->url( $slug, $item, $key );
				return array( self::link( $url ), (string) $url );
			case 'item_link':
				$text = $src->text( $slug, $item, $key, $meta );
				$url  = $src->url( $slug, $item, 'permalink' );
				return array( $url && '' !== $text ? '<a href="' . esc_url( $url ) . '">' . self::wrap( $text, $c ) . '</a>' : self::wrap( $text, $c ), self::lower( $text ) );
		}
		$text = $src->text( $slug, $item, $key, $meta );
		return array( self::wrap( $text, $c ), self::lower( $text ) );
	}

	/** Cell of a Repeater sub-field. */
	private static function sub_cell( $meta, $raw, $c ) {
		$stype = $meta['type'];
		$fmt   = 'auto' === $c['format'] ? self::auto_format( $stype ) : $c['format'];
		$cell  = self::formatted( $fmt, $raw, $c );
		if ( null !== $cell ) {
			return $cell;
		}
		switch ( $fmt ) {
			case 'image':
				return array( self::image( Gruppa_TB_Source::media_list( $raw ), 'gallery' === $stype ), '' );
			case 'link':
				$url = Gruppa_TB_Source::to_url( $raw );
				return array( self::link( $url ), $url );
		}
		$html = Gruppa_TB_Source::format_text( $raw, $meta );
		return array( self::wrap( $html, $c ), self::lower( $html ) );
	}

	private static function auto_format( $ftype ) {
		switch ( $ftype ) {
			case 'number':
				return 'number';
			case 'date':
				return 'date';
			case 'datetime':
				return 'datetime';
			case 'time':
				return 'time';
			case 'switcher':
				return 'yesno';
			case 'media':
			case 'gallery':
				return 'image';
			case 'url':
				return 'link';
		}
		return 'text';
	}
}
