<?php
defined( 'ABSPATH' ) || exit;

/**
 * SEO Builder — SEO records for items with a page (one record per item).
 *
 * Owner (Gruppa_SEO_Owners) = item of a Gruppa CMS Content entity ("{entity slug}" + item ID), of a
 * JetEngine CCT with a single page ("jet:{cct slug}" + _ID) or a WordPress page / post ("wp:{post type}" + ID).
 *
 * The same file runs in Gruppa CMS (built-in SEO Builder, includes/) and in the separate plugin
 * Gruppa SEO Builder (includes/). Gruppa CMS loads its copy only when the plugin is not active
 * or is older (Gruppa_Modules::SEO_VERSION). Output on the item's single page:
 * document title, robots, canonical (WordPress filters), description, keywords,
 * Open Graph, Twitter / X, JSON-LD, verification codes, sitemap exclusion.
 * Switched off automatically when Yoast SEO, Rank Math, AIOSEO or SEOPress is active.
 */
class Gruppa_SEO {

	const OPTION = 'gruppa_seo_settings';

	const ROBOTS = array(
		'index,follow'     => 'index, follow',
		'index,nofollow'   => 'index, nofollow',
		'noindex,follow'   => 'noindex, follow',
		'noindex,nofollow' => 'noindex, nofollow',
	);

	const OG_TYPES = array(
		'website' => 'Website',
		'article' => 'Article',
		'product' => 'Product',
		'profile' => 'Profile',
	);

	const CARDS = array(
		'summary_large_image' => 'Summary with large image',
		'summary'             => 'Summary',
	);

	/** Editable columns: key => [ label, kind ]. */
	public static function fields() {
		return array(
			'seo_robots'              => array( 'Robots', 'robots' ),
			'seo_title'               => array( 'SEO title', 'text' ),
			'seo_description'         => array( 'Meta description', 'textarea' ),
			'seo_keywords'            => array( 'Keywords', 'text' ),
			'seo_canonical'           => array( 'Canonical URL', 'url' ),
			'seo_og_title'            => array( 'OG title', 'text' ),
			'seo_og_description'      => array( 'OG description', 'textarea' ),
			'seo_og_image'            => array( 'OG image', 'image' ),
			'seo_og_type'             => array( 'OG type', 'og_type' ),
			'seo_twitter_card'        => array( 'Twitter card', 'card' ),
			'seo_twitter_title'       => array( 'Twitter title', 'text' ),
			'seo_twitter_description' => array( 'Twitter description', 'textarea' ),
			'seo_twitter_image'       => array( 'Twitter image', 'image' ),
			'seo_schema'              => array( 'Custom JSON-LD', 'json' ),
		);
	}

	public static function table() {
		global $wpdb;
		return class_exists( 'Gruppa_DB' ) ? Gruppa_DB::seo_table() : $wpdb->prefix . 'gcms_seo';
	}

	/* ======================================================
	 * Settings
	 * ==================================================== */

	public static function setting_defaults() {
		return array(
			'title_template' => '{title} {sep} {site}',
			'separator'      => '–',
			'og_image'       => 0,
			'twitter_site'   => '',
			'org_name'       => '',
			'org_logo'       => 0,
			'google_verify'  => '',
			'bing_verify'    => '',
			'post_types'     => array( 'page', 'post' ), // 1.3.0: WordPress post types with SEO.
			'taxonomies'     => array( 'category', 'post_tag' ), // 1.4.0: term archives with SEO.
			'types'          => array(), // 1.4.0: owner_type => [ title, description, robots, image ] — defaults per type.
			'sitemap'        => 1,       // WordPress sitemap /wp-sitemap.xml on / off.
			'sitemap_users'  => 0,       // Author pages in the sitemap.
			'sitemap_only'   => 0,       // Only the post types and taxonomies with SEO.
			'breadcrumbs'    => 1,       // BreadcrumbList in JSON-LD.
			'bc_home'        => 'Home',
			'bc_sep'         => '›',
		);
	}

	public static function settings() {
		$s = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $s ) ? $s : array(), self::setting_defaults() );
	}

	public static function save_settings( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$tw  = ltrim( sanitize_text_field( isset( $raw['twitter_site'] ) ? $raw['twitter_site'] : '' ), '@' );
		$s   = array(
			'title_template' => sanitize_text_field( isset( $raw['title_template'] ) && '' !== trim( $raw['title_template'] ) ? $raw['title_template'] : '{title} {sep} {site}' ),
			'separator'      => sanitize_text_field( isset( $raw['separator'] ) ? $raw['separator'] : '–' ),
			'og_image'       => absint( isset( $raw['og_image'] ) ? $raw['og_image'] : 0 ),
			'twitter_site'   => '' !== $tw ? '@' . preg_replace( '/[^A-Za-z0-9_]/', '', $tw ) : '',
			'org_name'       => sanitize_text_field( isset( $raw['org_name'] ) ? $raw['org_name'] : '' ),
			'org_logo'       => absint( isset( $raw['org_logo'] ) ? $raw['org_logo'] : 0 ),
			'google_verify'  => self::verify_code( isset( $raw['google_verify'] ) ? $raw['google_verify'] : '' ),
			'bing_verify'    => self::verify_code( isset( $raw['bing_verify'] ) ? $raw['bing_verify'] : '' ),
			'post_types'     => array_values( array_filter( array_map( 'sanitize_key', isset( $raw['post_types'] ) ? (array) $raw['post_types'] : array() ), 'post_type_exists' ) ),
			'taxonomies'     => array_values( array_filter( array_map( 'sanitize_key', isset( $raw['taxonomies'] ) ? (array) $raw['taxonomies'] : array() ), 'taxonomy_exists' ) ),
			'types'          => self::clean_types( isset( $raw['types'] ) ? $raw['types'] : array() ),
			'sitemap'        => empty( $raw['sitemap'] ) ? 0 : 1,
			'sitemap_users'  => empty( $raw['sitemap_users'] ) ? 0 : 1,
			'sitemap_only'   => empty( $raw['sitemap_only'] ) ? 0 : 1,
			'breadcrumbs'    => empty( $raw['breadcrumbs'] ) ? 0 : 1,
			'bc_home'        => sanitize_text_field( isset( $raw['bc_home'] ) && '' !== trim( $raw['bc_home'] ) ? $raw['bc_home'] : 'Home' ),
			'bc_sep'         => sanitize_text_field( isset( $raw['bc_sep'] ) && '' !== trim( $raw['bc_sep'] ) ? $raw['bc_sep'] : '›' ),
		);
		update_option( self::OPTION, $s );
		return $s;
	}

	/** Templates per owner type: only filled values are kept. */
	private static function clean_types( $raw ) {
		$out = array();
		foreach ( (array) $raw as $otype => $t ) {
			$otype = Gruppa_SEO_Owners::sanitize_type( $otype );
			if ( '' === $otype || ! is_array( $t ) ) {
				continue;
			}
			$row = array_filter( array(
				'title'       => isset( $t['title'] ) ? sanitize_text_field( $t['title'] ) : '',
				'description' => isset( $t['description'] ) ? sanitize_textarea_field( $t['description'] ) : '',
				'robots'      => isset( $t['robots'] ) && isset( self::ROBOTS[ $t['robots'] ] ) ? $t['robots'] : '',
				'image'       => isset( $t['image'] ) ? absint( $t['image'] ) : 0,
			) );
			if ( $row ) {
				$out[ $otype ] = $row;
			}
		}
		return $out;
	}

	/** Template of one owner type: [ title, description, robots, image ] ('' / 0 = not set). */
	public static function type_template( $otype ) {
		$t = self::settings()['types'];
		$t = isset( $t[ $otype ] ) && is_array( $t[ $otype ] ) ? $t[ $otype ] : array();
		return wp_parse_args( $t, array( 'title' => '', 'description' => '', 'robots' => '', 'image' => 0 ) );
	}

	/** Accepts the code or the whole <meta … content="code"> tag. */
	private static function verify_code( $v ) {
		$v = trim( (string) $v );
		if ( preg_match( '/content=["\']([^"\']+)["\']/', $v, $m ) ) {
			$v = $m[1];
		}
		return preg_replace( '/[^A-Za-z0-9_\-]/', '', $v );
	}

	/** Another SEO plugin is active → Gruppa SEO stays silent. */
	public static function conflict() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'Yoast SEO';
		}
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return 'Rank Math';
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			return 'All in One SEO';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return 'SEOPress';
		}
		return '';
	}

	/* ======================================================
	 * Owners
	 * ==================================================== */

	/** @return array [ owner type => [ 'name' => …, 'source' => …, 'items' => [ id => title ], 'posts' => [ id => wp post ID ] ] ] */
	public static function owners() {
		return Gruppa_SEO_Owners::groups();
	}

	/** @return array|null [ type, item ] */
	public static function owner( $type_slug, $item_id ) {
		return Gruppa_SEO_Owners::get( $type_slug, $item_id );
	}

	public static function owner_url( $type_slug, $item_id ) {
		if ( Gruppa_SEO_Owners::is_tax( $type_slug ) ) {
			$link = get_term_link( (int) $item_id, substr( $type_slug, strlen( Gruppa_SEO_Owners::TAX ) ) );
			return is_wp_error( $link ) ? '' : $link;
		}
		$o = self::owner( $type_slug, $item_id );
		return $o && $o[1]->wp_post_id && get_post( (int) $o[1]->wp_post_id ) ? get_permalink( (int) $o[1]->wp_post_id ) : '';
	}

	/* ======================================================
	 * CRUD
	 * ==================================================== */

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . self::table() . '` WHERE id = %d', $id ) ); // phpcs:ignore
	}

	public static function get_by_owner( $type_slug, $item_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . self::table() . '` WHERE owner_type = %s AND owner_id = %d', $type_slug, $item_id ) ); // phpcs:ignore
	}

	public static function all( $s = '' ) {
		global $wpdb;
		$where = '';
		if ( '' !== $s ) {
			$like  = '%' . $wpdb->esc_like( $s ) . '%';
			$where = $wpdb->prepare( 'WHERE seo_title LIKE %s OR seo_description LIKE %s OR owner_type LIKE %s', $like, $like, $like );
		}
		return (array) $wpdb->get_results( 'SELECT * FROM `' . self::table() . "` {$where} ORDER BY owner_type ASC, owner_id ASC" ); // phpcs:ignore
	}

	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . self::table() . '`' ); // phpcs:ignore
	}

	/** Robots value from free text ("noindex, follow", "NOINDEX nofollow"…). */
	public static function robots( $v ) {
		$v      = strtolower( (string) $v );
		$index  = false === strpos( $v, 'noindex' );
		$follow = false === strpos( $v, 'nofollow' );
		return ( $index ? 'index' : 'noindex' ) . ',' . ( $follow ? 'follow' : 'nofollow' );
	}

	/**
	 * Clean values for the editable columns.
	 *
	 * @return array [ row, errors ]
	 */
	public static function sanitize( $data ) {
		$row    = array();
		$errors = array();
		foreach ( self::fields() as $k => $def ) {
			if ( ! array_key_exists( $k, $data ) ) {
				continue;
			}
			$v = $data[ $k ];
			switch ( $def[1] ) {
				case 'robots':
					$row[ $k ] = self::robots( $v );
					break;
				case 'textarea':
					$row[ $k ] = sanitize_textarea_field( (string) $v );
					break;
				case 'url':
					$v         = trim( (string) $v );
					$row[ $k ] = '' === $v ? '' : esc_url_raw( $v );
					break;
				case 'image':
					$row[ $k ] = self::attachment( $v );
					break;
				case 'og_type':
					$row[ $k ] = isset( self::OG_TYPES[ $v ] ) ? $v : '';
					break;
				case 'card':
					$row[ $k ] = isset( self::CARDS[ $v ] ) ? $v : '';
					break;
				case 'json':
					$v = trim( (string) $v );
					if ( '' !== $v ) {
						$v = preg_replace( '#^\s*<script[^>]*>|</script>\s*$#i', '', $v );
						if ( null === json_decode( $v ) ) {
							$errors[ $k ] = 'Custom JSON-LD is not valid JSON.';
							break;
						}
						$v = wp_json_encode( json_decode( $v ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
					}
					$row[ $k ] = $v;
					break;
				default:
					$row[ $k ] = sanitize_text_field( (string) $v );
			}
		}
		return array( $row, $errors );
	}

	/** Attachment ID from an ID or a URL of the media library. */
	public static function attachment( $v ) {
		$v = trim( (string) ( is_scalar( $v ) ? $v : '' ) );
		if ( ctype_digit( $v ) ) {
			return 'attachment' === get_post_type( (int) $v ) ? (int) $v : 0;
		}
		return $v && filter_var( $v, FILTER_VALIDATE_URL ) ? (int) attachment_url_to_postid( $v ) : 0;
	}

	/**
	 * @return int|WP_Error record ID
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$now     = current_time( 'mysql' );
		$current = $id ? self::get( $id ) : null;
		if ( $id && ! $current ) {
			return new WP_Error( 'missing', 'Not found.' );
		}

		$otype = isset( $data['owner_type'] ) ? Gruppa_SEO_Owners::sanitize_type( $data['owner_type'] ) : ( $current ? $current->owner_type : '' );
		$oid   = isset( $data['owner_id'] ) ? absint( $data['owner_id'] ) : ( $current ? (int) $current->owner_id : 0 );
		if ( ! self::owner( $otype, $oid ) ) {
			return new WP_Error( 'owner', 'Select an item (owner).' );
		}
		$taken = self::get_by_owner( $otype, $oid );
		if ( $taken && (int) $taken->id !== (int) $id ) {
			return new WP_Error( 'owner_taken', 'This item already has SEO.', array( 'id' => (int) $taken->id ) );
		}

		list( $row, $errors ) = self::sanitize( $data );
		if ( $errors ) {
			return new WP_Error( 'invalid', implode( ' ', $errors ) );
		}
		$row['owner_type'] = $otype;
		$row['owner_id']   = $oid;
		$row['modified']   = $now;

		if ( $current ) {
			$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$row = array_merge( array( 'seo_robots' => 'index,follow' ), $row, array( 'author' => get_current_user_id(), 'created' => $now ) );
		if ( false === $wpdb->insert( self::table(), $row ) ) {
			return new WP_Error( 'db', $wpdb->last_error );
		}
		return (int) $wpdb->insert_id;
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/**
	 * Replaces the source "seo" of Gruppa Import/Export (separate plugin or Data Tools in Gruppa CMS)
	 * with Gruppa_SEO_IE_Source — same columns, owners of every source, also without Gruppa CMS.
	 */
	public static function ie_source( $sources ) {
		if ( ! class_exists( 'Gruppa_IE_Source_SEO' ) ) {
			return $sources;
		}
		require_once __DIR__ . '/class-gruppa-seo-ie-source.php';
		foreach ( (array) $sources as $i => $src ) {
			if ( $src instanceof Gruppa_IE_Source_SEO && ! $src instanceof Gruppa_SEO_IE_Source ) {
				$sources[ $i ] = new Gruppa_SEO_IE_Source();
			}
		}
		return $sources;
	}

	/* ======================================================
	 * Frontend
	 * ==================================================== */

	public static function init() {
		// Gruppa Import/Export / Data Tools: SEO source with JetEngine CCT and WordPress owners.
		add_filter( 'gruppa_ie/sources', array( __CLASS__, 'ie_source' ), 20 );

		add_action( 'gruppa_cms/item_deleted', function ( $type, $id ) {
			global $wpdb;
			$wpdb->delete( self::table(), array( 'owner_type' => $type->slug, 'owner_id' => (int) $id ) );
		}, 10, 2 );

		add_action( 'delete_term', function ( $term_id, $tt_id, $taxonomy ) {
			global $wpdb;
			$wpdb->delete( self::table(), array( 'owner_type' => Gruppa_SEO_Owners::TAX . $taxonomy, 'owner_id' => (int) $term_id ) );
		}, 10, 3 );

		if ( is_admin() ) {
			return;
		}
		add_action( 'wp', array( __CLASS__, 'boot' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap_args' ), 10, 2 );
		// Sitemap settings (1.4.0).
		add_filter( 'wp_sitemaps_enabled', array( __CLASS__, 'sitemap_enabled' ) );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'sitemap_provider' ), 10, 2 );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'sitemap_post_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( __CLASS__, 'sitemap_taxonomies' ) );
		add_filter( 'wp_sitemaps_taxonomies_query_args', array( __CLASS__, 'sitemap_term_args' ), 10, 2 );
	}

	public static function boot() {
		if ( self::conflict() ) {
			return;
		}
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 20 );
		add_filter( 'wp_robots', array( __CLASS__, 'wp_robots' ), 20 );
		add_filter( 'get_canonical_url', array( __CLASS__, 'canonical' ), 20, 2 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 1 );
	}

	private static $ctx = false;

	/** Current page = single page of a Gruppa item? [ type, item, seo|null ] */
	public static function context() {
		if ( false !== self::$ctx ) {
			return self::$ctx;
		}
		self::$ctx = null;
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( ! $term instanceof WP_Term || ! in_array( $term->taxonomy, Gruppa_SEO_Owners::wp_taxonomies(), true ) ) {
				return null;
			}
			$otype = Gruppa_SEO_Owners::TAX . $term->taxonomy;
			$o     = self::owner( $otype, $term->term_id );
			if ( $o ) {
				self::$ctx = array( $o[0], $o[1], self::get_by_owner( $otype, $term->term_id ), 0 );
			}
			return self::$ctx;
		}
		if ( ! is_singular() ) {
			return null;
		}
		$pid = (int) get_queried_object_id();
		$ref = Gruppa_SEO_Owners::from_post( $pid );
		if ( ! $ref ) {
			return null;
		}
		list( $tslug, $iid ) = $ref;
		$o = self::owner( $tslug, $iid );
		if ( ! $o ) {
			return null;
		}
		self::$ctx = array( $o[0], $o[1], self::get_by_owner( $tslug, $iid ), $pid );
		return self::$ctx;
	}

	private static function site_name() {
		return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/** Final values with fallbacks. */
	public static function resolved() {
		$c = self::context();
		if ( ! $c ) {
			return null;
		}
		list( $type, $item, $seo, $pid ) = $c;
		$set   = self::settings();
		$tpl   = self::type_template( $type->slug );
		$v     = function ( $k ) use ( $seo ) {
			return $seo && isset( $seo->{$k} ) ? $seo->{$k} : '';
		};
		// Own value → template of the type (1.4.0) → global title template.
		if ( '' !== trim( $v( 'seo_title' ) ) ) {
			$title = $v( 'seo_title' );
		} else {
			$title = self::fill( '' !== $tpl['title'] ? $tpl['title'] : $set['title_template'], $type, $item, $pid );
		}
		$desc = $v( 'seo_description' );
		if ( '' === trim( $desc ) && '' !== $tpl['description'] ) {
			$desc = self::fill( $tpl['description'], $type, $item, $pid );
		}
		// Image: own → type → featured image of the page → default.
		$image = (int) $v( 'seo_og_image' );
		if ( ! $image ) {
			$image = (int) $tpl['image'];
		}
		if ( ! $image && $pid && has_post_thumbnail( $pid ) ) {
			$image = (int) get_post_thumbnail_id( $pid );
		}
		if ( ! $image ) {
			$image = (int) $set['og_image'];
		}
		$robots = $seo ? self::robots( $seo->seo_robots ) : ( '' !== $tpl['robots'] ? $tpl['robots'] : 'index,follow' );
		$url    = $pid ? get_permalink( $pid ) : self::owner_url( $type->slug, $item->id );
		$og_t  = '' !== $v( 'seo_og_title' ) ? $v( 'seo_og_title' ) : $title;
		$og_d  = '' !== $v( 'seo_og_description' ) ? $v( 'seo_og_description' ) : $desc;

		return array(
			'type'        => $type,
			'item'        => $item,
			'post_id'     => $pid,
			'robots'      => $robots,
			'title'       => $title,
			'description' => $desc,
			'keywords'    => $v( 'seo_keywords' ),
			'canonical'   => '' !== $v( 'seo_canonical' ) ? $v( 'seo_canonical' ) : $url,
			'url'         => $url,
			'og_title'    => $og_t,
			'og_desc'     => $og_d,
			'og_image'    => $image,
			'og_type'     => '' !== $v( 'seo_og_type' ) ? $v( 'seo_og_type' ) : ( 'post' === $type->post_type && $pid && ! is_front_page() ? 'article' : 'website' ),
			'tw_card'     => '' !== $v( 'seo_twitter_card' ) ? $v( 'seo_twitter_card' ) : 'summary_large_image',
			'tw_title'    => '' !== $v( 'seo_twitter_title' ) ? $v( 'seo_twitter_title' ) : $og_t,
			'tw_desc'     => '' !== $v( 'seo_twitter_description' ) ? $v( 'seo_twitter_description' ) : $og_d,
			'tw_image'    => (int) $v( 'seo_twitter_image' ) ? (int) $v( 'seo_twitter_image' ) : $image,
			'schema'      => $v( 'seo_schema' ),
		);
	}

	/**
	 * Placeholders of the templates: {title} {sep} {site} {type} {excerpt} {description} {date} {year}
	 * and {field:key} — field of a Gruppa CMS item, column of a CCT item, post meta / ACF field, term meta.
	 */
	public static function fill( $text, $type, $item, $pid ) {
		$set  = self::settings();
		$name = preg_replace( '/ · (Gruppa CMS|JetEngine|WordPress|Taxonomy)$/', '', (string) $type->name );
		$out  = preg_replace_callback( '/\{([a-z_]+)(?::([A-Za-z0-9_\-]+))?\}/', function ( $m ) use ( $set, $name, $type, $item, $pid ) {
			switch ( $m[1] ) {
				case 'title':
					return (string) $item->title;
				case 'sep':
					return (string) $set['separator'];
				case 'site':
					return self::site_name();
				case 'type':
					return $name;
				case 'year':
					return wp_date( 'Y' );
				case 'date':
					return $item->created ? mysql2date( get_option( 'date_format' ), $item->created ) : '';
				case 'description':
					return isset( $item->description ) ? wp_strip_all_tags( (string) $item->description ) : '';
				case 'excerpt':
					if ( isset( $item->description ) ) {
						return wp_trim_words( wp_strip_all_tags( (string) $item->description ), 30, '…' );
					}
					$p = $pid ? get_post( $pid ) : null;
					if ( ! $p ) {
						return '';
					}
					$t = '' !== $p->post_excerpt ? $p->post_excerpt : strip_shortcodes( $p->post_content );
					return wp_trim_words( wp_strip_all_tags( $t ), 30, '…' );
				case 'field':
					return isset( $m[2] ) ? self::field_text( $type, $item, $pid, $m[2] ) : '';
			}
			return $m[0];
		}, (string) $text );
		return trim( preg_replace( '/\s+/', ' ', $out ) );
	}

	/** Plain text of a field for {field:key}. */
	private static function field_text( $type, $item, $pid, $key ) {
		global $wpdb;
		$v = '';
		if ( 'tax' === $type->source ) {
			$v = get_term_meta( (int) $item->id, $key, true );
		} elseif ( 'jet' === $type->source ) {
			$slug  = substr( $type->slug, strlen( Gruppa_SEO_Owners::JET ) );
			$table = $wpdb->prefix . 'jet_cct_' . preg_replace( '/[^A-Za-z0-9_\-]/', '', $slug );
			$cols  = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" ); // phpcs:ignore
			if ( in_array( $key, $cols, true ) ) {
				$v = $wpdb->get_var( $wpdb->prepare( "SELECT `{$key}` FROM `{$table}` WHERE _ID = %d", (int) $item->id ) ); // phpcs:ignore — column checked above
			}
		} elseif ( 'gruppa' === $type->source ) {
			$v = isset( $item->{$key} ) ? $item->{$key} : '';
		} elseif ( $pid ) {
			$v = function_exists( 'get_field' ) ? get_field( $key, $pid, false ) : null;
			if ( null === $v || false === $v || '' === $v ) {
				$v = get_post_meta( $pid, $key, true );
			}
		}
		if ( is_array( $v ) ) {
			$v = implode( ', ', array_filter( array_map( function ( $x ) {
				return is_scalar( $x ) ? (string) $x : '';
			}, $v ) ) );
		}
		return is_scalar( $v ) ? wp_strip_all_tags( (string) $v ) : '';
	}

	public static function document_title( $title ) {
		$r = self::resolved();
		return $r ? $r['title'] : $title;
	}

	public static function wp_robots( $robots ) {
		$r = self::resolved();
		if ( ! $r ) {
			return $robots;
		}
		$noindex  = 0 === strpos( $r['robots'], 'noindex' );
		$nofollow = false !== strpos( $r['robots'], 'nofollow' );
		if ( $noindex ) {
			$robots['noindex'] = true;
			unset( $robots['index'], $robots['max-image-preview'] );
		} elseif ( empty( $robots['noindex'] ) ) { // Site set to "discourage search engines" wins.
			$robots['index']             = true;
			$robots['max-image-preview'] = 'large';
		}
		if ( $nofollow ) {
			$robots['nofollow'] = true;
			unset( $robots['follow'] );
		} elseif ( empty( $robots['nofollow'] ) ) {
			$robots['follow'] = true;
		}
		return $robots;
	}

	public static function canonical( $url, $post ) {
		$r = self::resolved();
		return $r && (int) $post->ID === (int) $r['post_id'] ? $r['canonical'] : $url;
	}

	private static function meta( $attr, $name, $content ) {
		if ( '' !== (string) $content ) {
			printf( "<meta %s=\"%s\" content=\"%s\">\n", $attr, esc_attr( $name ), esc_attr( $content ) ); // phpcs:ignore
		}
	}

	private static function image( $aid ) {
		if ( ! $aid ) {
			return null;
		}
		$src = wp_get_attachment_image_src( $aid, 'full' );
		if ( ! $src ) {
			return null;
		}
		return array(
			'url'    => $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
			'alt'    => (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ),
			'type'   => (string) get_post_mime_type( $aid ),
		);
	}

	public static function head() {
		$set = self::settings();
		if ( is_front_page() ) {
			self::meta( 'name', 'google-site-verification', $set['google_verify'] );
			self::meta( 'name', 'msvalidate.01', $set['bing_verify'] );
		}
		$r = self::resolved();
		if ( ! $r ) {
			return;
		}
		echo "\n<!-- Gruppa CMS SEO -->\n";
		if ( ! is_singular() && '' !== $r['canonical'] ) {
			printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( $r['canonical'] ) );
		}
		self::meta( 'name', 'description', $r['description'] );
		self::meta( 'name', 'keywords', $r['keywords'] );

		// Open Graph.
		self::meta( 'property', 'og:locale', get_locale() );
		self::meta( 'property', 'og:type', $r['og_type'] );
		self::meta( 'property', 'og:title', $r['og_title'] );
		self::meta( 'property', 'og:description', $r['og_desc'] );
		self::meta( 'property', 'og:url', $r['canonical'] );
		self::meta( 'property', 'og:site_name', self::site_name() );
		$img = self::image( $r['og_image'] );
		if ( $img ) {
			self::meta( 'property', 'og:image', $img['url'] );
			self::meta( 'property', 'og:image:width', $img['width'] );
			self::meta( 'property', 'og:image:height', $img['height'] );
			self::meta( 'property', 'og:image:type', $img['type'] );
			self::meta( 'property', 'og:image:alt', $img['alt'] );
		}
		if ( 'article' === $r['og_type'] ) {
			self::meta( 'property', 'article:published_time', $r['item']->created ? mysql2date( 'c', $r['item']->created ) : '' );
			self::meta( 'property', 'article:modified_time', $r['item']->modified ? mysql2date( 'c', $r['item']->modified ) : '' );
		}

		// Twitter / X.
		self::meta( 'name', 'twitter:card', $r['tw_card'] );
		self::meta( 'name', 'twitter:site', $set['twitter_site'] );
		self::meta( 'name', 'twitter:title', $r['tw_title'] );
		self::meta( 'name', 'twitter:description', $r['tw_desc'] );
		$timg = self::image( $r['tw_image'] );
		if ( $timg ) {
			self::meta( 'name', 'twitter:image', $timg['url'] );
			self::meta( 'name', 'twitter:image:alt', $timg['alt'] );
		}

		// Structured data.
		$json = '' !== trim( $r['schema'] ) ? $r['schema'] : wp_json_encode( self::schema( $r, $set, $img ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		echo '<script type="application/ld+json">' . str_replace( '</', '<\/', $json ) . "</script>\n"; // phpcs:ignore
		echo "<!-- / Gruppa CMS SEO -->\n\n";
	}

	private static function schema( $r, $set, $img ) {
		$home  = home_url( '/' );
		$graph = array();
		$org   = null;
		if ( '' !== $set['org_name'] ) {
			$org  = array( '@type' => 'Organization', '@id' => $home . '#organization', 'name' => $set['org_name'], 'url' => $home );
			$logo = self::image( (int) $set['org_logo'] );
			if ( $logo ) {
				$org['logo'] = array( '@type' => 'ImageObject', 'url' => $logo['url'], 'width' => $logo['width'], 'height' => $logo['height'] );
			}
			$graph[] = $org;
		}
		$site = array( '@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => self::site_name(), 'inLanguage' => str_replace( '_', '-', get_locale() ) );
		if ( $org ) {
			$site['publisher'] = array( '@id' => $org['@id'] );
		}
		$graph[] = $site;

		$page = array(
			'@type'         => 'tax' === $r['type']->source ? 'CollectionPage' : 'WebPage',
			'@id'           => $r['canonical'] . '#webpage',
			'url'           => $r['canonical'],
			'name'          => $r['title'],
			'isPartOf'      => array( '@id' => $home . '#website' ),
			'inLanguage'    => str_replace( '_', '-', get_locale() ),
			'datePublished' => $r['item']->created ? mysql2date( 'c', $r['item']->created ) : null,
			'dateModified'  => $r['item']->modified ? mysql2date( 'c', $r['item']->modified ) : null,
		);
		$crumbs = ! empty( $set['breadcrumbs'] ) && ! is_front_page() ? Gruppa_SEO_Breadcrumbs::trail() : array();
		if ( count( $crumbs ) > 1 ) {
			$page['breadcrumb'] = array( '@id' => $r['canonical'] . '#breadcrumb' );
		}
		if ( '' !== $r['description'] ) {
			$page['description'] = $r['description'];
		}
		if ( $img ) {
			$page['primaryImageOfPage'] = array( '@type' => 'ImageObject', 'url' => $img['url'], 'width' => $img['width'], 'height' => $img['height'] );
		}
		$graph[] = array_filter( $page, function ( $x ) {
			return null !== $x;
		} );
		if ( count( $crumbs ) > 1 ) {
			$list = array();
			foreach ( array_values( $crumbs ) as $i => $c ) {
				$list[] = array_filter( array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'], 'item' => '' !== $c['url'] ? $c['url'] : null ) );
			}
			$graph[] = array( '@type' => 'BreadcrumbList', '@id' => $r['canonical'] . '#breadcrumb', 'itemListElement' => $list );
		}

		if ( 'article' === $r['og_type'] && ! is_front_page() ) {
			$art = array(
				'@type'            => 'Article',
				'@id'              => $r['canonical'] . '#article',
				'headline'         => $r['og_title'],
				'mainEntityOfPage' => array( '@id' => $r['canonical'] . '#webpage' ),
				'datePublished'    => $page['datePublished'],
				'dateModified'     => $page['dateModified'],
			);
			if ( $img ) {
				$art['image'] = $img['url'];
			}
			if ( $org ) {
				$art['publisher'] = array( '@id' => $org['@id'] );
			}
			$author = get_userdata( (int) $r['item']->author );
			if ( $author ) {
				$art['author'] = array( '@type' => 'Person', 'name' => $author->display_name );
			}
			$graph[] = array_filter( $art, function ( $x ) {
				return null !== $x;
			} );
		}
		return array( '@context' => 'https://schema.org', '@graph' => $graph );
	}

	/* ======================================================
	 * Sitemap settings (1.4.0)
	 * ==================================================== */

	public static function sitemap_enabled( $on ) {
		return self::conflict() ? $on : ( $on && ! empty( self::settings()['sitemap'] ) );
	}

	public static function sitemap_provider( $provider, $name ) {
		if ( 'users' === $name && ! self::conflict() && empty( self::settings()['sitemap_users'] ) ) {
			return false;
		}
		return $provider;
	}

	/** Without types set to noindex; "only with SEO" keeps the post types of the SEO owners. */
	public static function sitemap_post_types( $types ) {
		if ( self::conflict() ) {
			return $types;
		}
		$set  = self::settings();
		$keep = array();
		if ( ! empty( $set['sitemap_only'] ) ) {
			$keep = Gruppa_SEO_Owners::wp_post_types();
			if ( Gruppa_SEO_Owners::cms() ) {
				foreach ( Gruppa_Types::all() as $t ) {
					if ( 'content' === $t->kind && $t->post_type ) {
						$keep[] = $t->post_type;
					}
				}
			}
		}
		foreach ( array_keys( $types ) as $pt ) {
			if ( $keep && ! in_array( $pt, $keep, true ) ) {
				unset( $types[ $pt ] );
				continue;
			}
			if ( 0 === strpos( self::type_template( Gruppa_SEO_Owners::WP . $pt )['robots'], 'noindex' ) ) {
				unset( $types[ $pt ] );
			}
		}
		return $types;
	}

	public static function sitemap_taxonomies( $taxonomies ) {
		if ( self::conflict() ) {
			return $taxonomies;
		}
		$only = ! empty( self::settings()['sitemap_only'] );
		$seo  = Gruppa_SEO_Owners::wp_taxonomies();
		foreach ( array_keys( $taxonomies ) as $tx ) {
			if ( ( $only && ! in_array( $tx, $seo, true ) ) || 0 === strpos( self::type_template( Gruppa_SEO_Owners::TAX . $tx )['robots'], 'noindex' ) ) {
				unset( $taxonomies[ $tx ] );
			}
		}
		return $taxonomies;
	}

	/** Terms with noindex SEO stay out of the sitemap. */
	public static function sitemap_term_args( $args, $taxonomy ) {
		global $wpdb;
		if ( self::conflict() ) {
			return $args;
		}
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT owner_id FROM `' . self::table() . "` WHERE owner_type = %s AND seo_robots LIKE 'noindex%%'", Gruppa_SEO_Owners::TAX . $taxonomy ) ) ); // phpcs:ignore
		if ( $ids ) {
			$args['exclude'] = array_merge( isset( $args['exclude'] ) ? (array) $args['exclude'] : array(), $ids );
		}
		return $args;
	}

	/** noindex items stay out of the WordPress sitemap. */
	public static function sitemap_args( $args, $post_type ) {
		if ( self::conflict() ) {
			return $args;
		}
		global $wpdb;
		$exclude = array();
		$rows    = (array) $wpdb->get_results( 'SELECT owner_type, owner_id FROM `' . self::table() . "` WHERE seo_robots LIKE 'noindex%'" ); // phpcs:ignore
		foreach ( $rows as $r ) {
			$o = self::owner( $r->owner_type, (int) $r->owner_id );
			if ( $o && $o[0]->post_type === $post_type && $o[1]->wp_post_id ) {
				$exclude[] = (int) $o[1]->wp_post_id;
			}
		}
		if ( $exclude ) {
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $exclude );
		}
		return $args;
	}
}
