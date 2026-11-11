<?php
defined( 'ABSPATH' ) || exit;

/**
 * Breadcrumbs (1.4.0): the trail of the current page for JSON-LD (BreadcrumbList) and the shortcode
 * [gruppa_breadcrumbs] (also in the Elementor Shortcode widget).
 *
 * Trail: Home → parent pages (pages) / first category with its parents (posts) / archive of the post type
 * (custom post types with an archive) / parent terms (term archives) → current page.
 * Settings → Breadcrumbs: JSON-LD on / off, Home label, separator.
 */
class Gruppa_SEO_Breadcrumbs {

	private static $styled = false;

	public static function init() {
		add_shortcode( 'gruppa_breadcrumbs', array( __CLASS__, 'shortcode' ) );
	}

	/** @return array [ [ 'name' => …, 'url' => … ], … ] — last = current page (url = its address). */
	public static function trail() {
		$set   = Gruppa_SEO::settings();
		$home  = array( 'name' => $set['bc_home'], 'url' => home_url( '/' ) );
		$trail = array( $home );
		if ( is_front_page() ) {
			return $trail;
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $aid ) {
					$a = get_term( $aid, $term->taxonomy );
					if ( $a instanceof WP_Term ) {
						$trail[] = array( 'name' => $a->name, 'url' => (string) get_term_link( $a ) );
					}
				}
				$trail[] = array( 'name' => $term->name, 'url' => (string) get_term_link( $term ) );
			}
			return $trail;
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( ! $post instanceof WP_Post ) {
				return $trail;
			}
			if ( is_post_type_hierarchical( $post->post_type ) ) {
				foreach ( array_reverse( get_post_ancestors( $post ) ) as $aid ) {
					$trail[] = array( 'name' => get_the_title( $aid ), 'url' => (string) get_permalink( $aid ) );
				}
			} elseif ( 'post' === $post->post_type ) {
				$blog = (int) get_option( 'page_for_posts' );
				if ( $blog ) {
					$trail[] = array( 'name' => get_the_title( $blog ), 'url' => (string) get_permalink( $blog ) );
				}
				$cats = get_the_category( $post->ID );
				if ( $cats ) {
					$cat = $cats[0];
					foreach ( array_reverse( get_ancestors( $cat->term_id, 'category', 'taxonomy' ) ) as $aid ) {
						$a = get_term( $aid, 'category' );
						if ( $a instanceof WP_Term ) {
							$trail[] = array( 'name' => $a->name, 'url' => (string) get_term_link( $a ) );
						}
					}
					$trail[] = array( 'name' => $cat->name, 'url' => (string) get_term_link( $cat ) );
				}
			} else {
				$obj = get_post_type_object( $post->post_type );
				$arc = $obj && $obj->has_archive ? get_post_type_archive_link( $post->post_type ) : '';
				if ( $arc ) {
					$trail[] = array( 'name' => $obj->labels->name, 'url' => (string) $arc );
				}
			}
			$trail[] = array( 'name' => get_the_title( $post ), 'url' => (string) get_permalink( $post ) );
			return apply_filters( 'gruppa_seo/breadcrumbs', $trail, $post );
		}

		if ( is_post_type_archive() ) {
			$obj     = get_queried_object();
			$trail[] = array( 'name' => $obj && isset( $obj->labels ) ? $obj->labels->name : post_type_archive_title( '', false ), 'url' => (string) get_post_type_archive_link( get_query_var( 'post_type' ) ) );
		} elseif ( is_search() ) {
			$trail[] = array( 'name' => sprintf( '%s: %s', __( 'Search' ), get_search_query() ), 'url' => '' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		} elseif ( is_home() ) {
			$blog    = (int) get_option( 'page_for_posts' );
			$trail[] = array( 'name' => $blog ? get_the_title( $blog ) : wp_strip_all_tags( get_the_archive_title() ), 'url' => $blog ? (string) get_permalink( $blog ) : '' );
		}
		return apply_filters( 'gruppa_seo/breadcrumbs', $trail, null );
	}

	/**
	 * [gruppa_breadcrumbs home="Domov" sep="/" current="1"]
	 * Markup: nav.gruppa-breadcrumbs › ol › li (a | span[aria-current]) with span.gruppa-breadcrumbs-sep between.
	 */
	public static function shortcode( $atts ) {
		$set  = Gruppa_SEO::settings();
		$atts = shortcode_atts( array( 'home' => '', 'sep' => $set['bc_sep'], 'current' => '1' ), $atts, 'gruppa_breadcrumbs' );
		$list = self::trail();
		if ( count( $list ) < 2 ) {
			return '';
		}
		if ( '' !== $atts['home'] ) {
			$list[0]['name'] = $atts['home'];
		}
		if ( '0' === (string) $atts['current'] ) {
			array_pop( $list );
		}
		$last = count( $list ) - 1;
		$html = '';
		if ( ! self::$styled ) { // Layout only — colours and fonts come from the theme.
			self::$styled = true;
			$html        .= '<style>.gruppa-breadcrumbs ol{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:6px}.gruppa-breadcrumbs li{margin:0}</style>';
		}
		$html .= '<nav class="gruppa-breadcrumbs" aria-label="Breadcrumb"><ol>';
		foreach ( $list as $i => $c ) {
			$html .= '<li>';
			if ( $i > 0 ) {
				$html .= '<span class="gruppa-breadcrumbs-sep" aria-hidden="true">' . esc_html( $atts['sep'] ) . '</span> ';
			}
			if ( $i === $last && '0' !== (string) $atts['current'] ) {
				$html .= '<span aria-current="page">' . esc_html( $c['name'] ) . '</span>';
			} elseif ( '' !== $c['url'] ) {
				$html .= '<a href="' . esc_url( $c['url'] ) . '">' . esc_html( $c['name'] ) . '</a>';
			} else {
				$html .= '<span>' . esc_html( $c['name'] ) . '</span>';
			}
			$html .= '</li>';
		}
		return $html . '</ol></nav>';
	}
}
