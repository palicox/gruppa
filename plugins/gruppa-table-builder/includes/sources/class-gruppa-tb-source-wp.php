<?php
defined( 'ABSPATH' ) || exit;

/**
 * Source: WordPress posts, pages and post types — core fields, taxonomies,
 * ACF fields and JetEngine meta fields (meta boxes and post type fields).
 * Values are read from post meta, so tables keep working even when ACF is switched off.
 */
class Gruppa_TB_Source_WP extends Gruppa_TB_Source {

	const EXCLUDE = array(
		'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request',
		'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_font_family', 'wp_font_face',
		'acf-field-group', 'acf-field', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page',
		'elementor_library', 'e-landing-page', 'e-floating-buttons',
		'jet-engine', 'jet-engine-booking', 'jet-form-builder', 'jet-smart-filters', 'jet-popup', 'jet-theme-core', 'jet-menu', 'jet-woo-builder',
	);

	/** Table Builder key => [ label, type ]. */
	const SYSTEM = array(
		'title'          => array( 'Title', 'text' ),
		'slug'           => array( 'Slug', 'text' ),
		'id'             => array( 'ID', 'number' ),
		'created'        => array( 'Created', 'date' ),
		'modified'       => array( 'Modified', 'date' ),
		'author'         => array( 'Author', 'user' ),
		'excerpt'        => array( 'Excerpt', 'textarea' ),
		'content'        => array( 'Content', 'editor' ),
		'featured_image' => array( 'Featured image', 'media' ),
	);

	const SUPPORTS = array(
		'title'          => 'title',
		'excerpt'        => 'excerpt',
		'content'        => 'editor',
		'featured_image' => 'thumbnail',
	);

	private $fcache = array();
	private $jet_pt = null;

	public function id() {
		return 'wp';
	}

	public function label() {
		$extra = array();
		if ( function_exists( 'acf_get_field_groups' ) ) {
			$extra[] = 'ACF';
		}
		if ( $this->jet_active() ) {
			$extra[] = 'JetEngine';
		}
		return 'WordPress' . ( $extra ? ' + ' . implode( ' / ', $extra ) : '' );
	}

	public function available() {
		return true;
	}

	private function jet_active() {
		return function_exists( 'jet_engine' ) || class_exists( 'Jet_Engine' );
	}

	public function types() {
		$out = array();
		$exclude = apply_filters( 'gruppa_tb/wp_exclude_post_types', self::EXCLUDE );
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $pt ) {
			// Gruppa CMS internals (gruppa_listing …) — Gruppa CMS types are in their own source.
			if ( ! in_array( $pt->name, $exclude, true ) && 0 !== strpos( $pt->name, 'gruppa_' ) ) {
				$out[ $pt->name ] = $pt->labels->name;
			}
		}
		return $out;
	}

	/* ---------- field definitions ---------- */

	/** Normalized type of an ACF field (null = not shown). */
	private static function acf_type( $t ) {
		$map = array(
			'text'             => 'text',
			'email'            => 'text',
			'color_picker'     => 'text',
			'oembed'           => 'url',
			'url'              => 'url',
			'textarea'         => 'textarea',
			'wysiwyg'          => 'editor',
			'number'           => 'number',
			'range'            => 'number',
			'image'            => 'media',
			'file'             => 'url',
			'gallery'          => 'gallery',
			'select'           => 'select',
			'checkbox'         => 'checkbox',
			'radio'            => 'radio',
			'button_group'     => 'radio',
			'true_false'       => 'switcher',
			'date_picker'      => 'date',
			'time_picker'      => 'time',
			'date_time_picker' => 'datetime',
			'post_object'      => 'posts',
			'relationship'     => 'posts',
			'page_link'        => 'posts',
			'taxonomy'         => 'terms',
			'user'             => 'user',
			'link'             => 'link',
			'repeater'         => 'repeater',
		);
		return isset( $map[ $t ] ) ? $map[ $t ] : null;
	}

	private static function acf_meta( $f ) {
		$type = self::acf_type( $f['type'] );
		$opts = array();
		if ( isset( $f['choices'] ) && is_array( $f['choices'] ) ) {
			foreach ( $f['choices'] as $k => $v ) {
				$opts[ (string) $k ] = (string) $v;
			}
		}
		return self::meta( ! empty( $f['label'] ) ? $f['label'] : $f['name'], $type, 'ACF · ' . $f['type'], $opts );
	}

	/** ACF: [ columns, repeaters ]. */
	private function acf_fields( $pt ) {
		$cols = array();
		$reps = array();
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return array( $cols, $reps );
		}
		foreach ( (array) acf_get_field_groups( array( 'post_type' => $pt ) ) as $group ) {
			foreach ( (array) acf_get_fields( $group ) as $f ) {
				if ( empty( $f['name'] ) || null === self::acf_type( $f['type'] ) || isset( $cols[ $f['name'] ] ) || isset( $reps[ $f['name'] ] ) ) {
					continue;
				}
				if ( 'repeater' === $f['type'] ) {
					$sub = array();
					foreach ( isset( $f['sub_fields'] ) ? (array) $f['sub_fields'] : array() as $sf ) {
						if ( ! empty( $sf['name'] ) && null !== self::acf_type( $sf['type'] ) && 'repeater' !== $sf['type'] ) {
							$sub[ $sf['name'] ] = self::acf_meta( $sf );
						}
					}
					$reps[ $f['name'] ] = array( 'label' => ! empty( $f['label'] ) ? $f['label'] : $f['name'], 'sub' => $sub, 'kind' => 'acf' );
					continue;
				}
				$cols[ $f['name'] ] = self::acf_meta( $f );
			}
		}
		return array( $cols, $reps );
	}

	/** JetEngine field lists that apply to a post type. */
	private function jet_lists( $pt ) {
		global $wpdb;
		if ( ! $this->jet_active() ) {
			return array();
		}
		$lists = array();
		// Meta boxes (JetEngine → Meta Boxes).
		foreach ( (array) get_option( 'jet_engine_meta_boxes', array() ) as $box ) {
			$args = isset( $box['args'] ) && is_array( $box['args'] ) ? $box['args'] : array();
			$obj  = isset( $args['object_type'] ) ? $args['object_type'] : 'post';
			$pts  = isset( $args['allowed_post_type'] ) ? (array) $args['allowed_post_type'] : array();
			if ( 'post' === $obj && in_array( $pt, $pts, true ) && ! empty( $box['meta_fields'] ) ) {
				$lists[] = $box['meta_fields'];
			}
		}
		// Fields of post types created or edited in JetEngine → Post Types.
		if ( null === $this->jet_pt ) {
			$this->jet_pt = array();
			$table        = $wpdb->prefix . 'jet_post_types';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
				foreach ( (array) $wpdb->get_results( "SELECT * FROM `{$table}` WHERE status <> 'content-type'", ARRAY_A ) as $r ) { // phpcs:ignore
					$args = self::decode( isset( $r['args'] ) ? $r['args'] : '' );
					$slug = ! empty( $r['slug'] ) ? $r['slug'] : ( is_array( $args ) && isset( $args['slug'] ) ? $args['slug'] : '' );
					$mf   = self::decode( isset( $r['meta_fields'] ) ? $r['meta_fields'] : '' );
					if ( $slug && is_array( $mf ) && $mf ) {
						$this->jet_pt[ $slug ][] = $mf;
					}
				}
			}
		}
		if ( isset( $this->jet_pt[ $pt ] ) ) {
			$lists = array_merge( $lists, $this->jet_pt[ $pt ] );
		}
		return $lists;
	}

	/** All custom fields: [ columns, repeaters ]. */
	private function custom( $pt ) {
		if ( isset( $this->fcache[ $pt ] ) ) {
			return $this->fcache[ $pt ];
		}
		list( $cols, $reps ) = $this->acf_fields( $pt );
		foreach ( $this->jet_lists( $pt ) as $list ) {
			list( $jc, $jr ) = self::jet_fields( $list );
			foreach ( $jc as $k => $m ) {
				if ( ! isset( $cols[ $k ] ) && ! isset( self::SYSTEM[ $k ] ) ) {
					$m['raw']   = 'JetEngine · ' . $m['raw'];
					$cols[ $k ] = $m;
				}
			}
			foreach ( $jr as $k => $r ) {
				if ( ! isset( $reps[ $k ] ) ) {
					$r['kind']  = 'jet';
					$reps[ $k ] = $r;
				}
			}
		}
		$this->fcache[ $pt ] = array( $cols, $reps );
		return $this->fcache[ $pt ];
	}

	public function exists( $pt ) {
		return '' !== (string) $pt && isset( $this->types()[ $pt ] );
	}

	public function columns( $pt ) {
		$out = array();
		if ( ! post_type_exists( $pt ) ) {
			return $out;
		}
		foreach ( self::SYSTEM as $k => $c ) {
			if ( ! isset( self::SUPPORTS[ $k ] ) || post_type_supports( $pt, self::SUPPORTS[ $k ] ) ) {
				$out[ $k ] = self::meta( $c[0], $c[1], 'system' );
			}
		}
		foreach ( get_object_taxonomies( $pt, 'objects' ) as $tax ) {
			if ( $tax->show_ui ) {
				$out[ 'tax_' . $tax->name ] = self::meta( $tax->labels->name, 'text', 'taxonomy' );
			}
		}
		list( $cols ) = $this->custom( $pt );
		return $out + $cols;
	}

	public function repeaters( $pt ) {
		list( , $reps ) = $this->custom( $pt );
		return $reps;
	}

	public function order_options( $pt ) {
		$order = array( array( 'menu_order', 'Order (menu order)' ), array( 'title', 'Title' ), array( 'created', 'Created' ), array( 'modified', 'Modified' ), array( 'id', 'ID' ), array( 'rand', 'Random' ) );
		list( $cols ) = $this->custom( $pt );
		foreach ( $cols as $k => $m ) {
			if ( in_array( $m['type'], array( 'text', 'number', 'date', 'time', 'datetime', 'select', 'radio', 'switcher' ), true ) ) {
				$order[] = array( $k, $m['label'] );
			}
		}
		return $order;
	}

	public function context_options( $pt ) {
		$out = array();
		list( $cols ) = $this->custom( $pt );
		foreach ( $cols as $k => $m ) {
			if ( 'posts' === $m['type'] ) {
				$out[] = array( $k, $m['label'] . ' (Posts)' );
			}
		}
		return $out;
	}

	/* ---------- rows ---------- */

	public function query( $pt, $s, $page, $per ) {
		$args = array(
			'post_type'           => $pt,
			'post_status'         => 'publish',
			'posts_per_page'      => $per,
			'paged'               => $page,
			'ignore_sticky_posts' => true,
			'order'               => 'desc' === $s['order'] ? 'DESC' : 'ASC',
		);
		$map = array(
			'menu_order' => 'menu_order',
			'title'      => 'title',
			'created'    => 'date',
			'modified'   => 'modified',
			'id'         => 'ID',
			'rand'       => 'rand',
		);
		list( $cols ) = $this->custom( $pt );
		if ( isset( $map[ $s['orderby'] ] ) ) {
			$args['orderby'] = array( $map[ $s['orderby'] ] => $args['order'], 'ID' => $args['order'] );
		} elseif ( isset( $cols[ $s['orderby'] ] ) ) {
			$args['meta_key'] = $s['orderby']; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['orderby']  = 'number' === $cols[ $s['orderby'] ]['type'] ? 'meta_value_num' : 'meta_value';
		} else {
			$args['orderby'] = array( 'menu_order' => 'ASC', 'title' => 'ASC' );
		}
		if ( ! empty( $s['only_mine'] ) ) {
			$args['author'] = get_current_user_id() ? get_current_user_id() : -1;
		}
		if ( '' !== $s['context_field'] && isset( $cols[ $s['context_field'] ] ) ) {
			$id                 = (int) get_the_ID();
			$k                  = $s['context_field'];
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'OR',
				array( 'key' => $k, 'value' => (string) $id, 'compare' => '=' ),
				array( 'key' => $k, 'value' => '"' . $id . '"', 'compare' => 'LIKE' ),
				array( 'key' => $k, 'value' => 'i:' . $id . ';', 'compare' => 'LIKE' ),
			);
		}
		$q = new WP_Query( apply_filters( 'gruppa_tb/wp_query_args', $args, $pt, $s ) );
		return array( $q->posts, (int) $q->found_posts );
	}

	/** Current post: the object of a JetEngine Listing Grid, otherwise the post of the page. */
	private function current_post( $pt ) {
		$obj = self::jet_current_object();
		if ( $obj instanceof WP_Post && $obj->post_type === $pt ) {
			return $obj;
		}
		$p = get_post( (int) get_the_ID() );
		return $p && $p->post_type === $pt ? $p : null;
	}

	public function repeater_rows( $pt, $s ) {
		if ( 'current' === $s['item_source'] ) {
			$post = $this->current_post( $pt );
		} elseif ( 'slug' === $s['item_source'] ) {
			$post = get_page_by_path( sanitize_title( $s['item_value'] ), OBJECT, $pt );
		} else {
			$post = get_post( (int) $s['item_value'] );
			$post = $post && $post->post_type === $pt ? $post : null;
		}
		if ( ! $post ) {
			return 'current' === $s['item_source'] ? null : array();
		}
		$reps = $this->repeaters( $pt );
		$key  = $s['field'];
		if ( ! isset( $reps[ $key ] ) ) {
			return array();
		}
		if ( isset( $reps[ $key ]['kind'] ) && 'acf' === $reps[ $key ]['kind'] ) {
			// ACF stores a repeater as count + {name}_{row}_{sub} meta.
			$n    = (int) get_post_meta( $post->ID, $key, true );
			$rows = array();
			for ( $i = 0; $i < $n; $i++ ) {
				$r = array();
				foreach ( array_keys( $reps[ $key ]['sub'] ) as $sk ) {
					$r[ $sk ] = get_post_meta( $post->ID, $key . '_' . $i . '_' . $sk, true );
				}
				$rows[] = $r;
			}
			return $rows;
		}
		return self::jet_rows( get_post_meta( $post->ID, $key, true ) );
	}

	public function value( $pt, $item, $key ) {
		switch ( $key ) {
			case 'id':
				return (int) $item->ID;
			case 'title':
				return $item->post_title;
			case 'slug':
				return $item->post_name;
			case 'created':
				return $item->post_date;
			case 'modified':
				return $item->post_modified;
			case 'author':
				return (int) $item->post_author;
			case 'excerpt':
				return $item->post_excerpt;
			case 'content':
				return $item->post_content;
			case 'featured_image':
				return (int) get_post_thumbnail_id( $item );
		}
		if ( 0 === strpos( $key, 'tax_' ) ) {
			$terms = get_the_terms( $item, substr( $key, 4 ) );
			return $terms && ! is_wp_error( $terms ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '';
		}
		return get_post_meta( $item->ID, $key, true );
	}

	public function text( $pt, $item, $key, $meta ) {
		if ( 'title' === $key ) {
			return esc_html( get_the_title( $item ) );
		}
		return parent::text( $pt, $item, $key, $meta );
	}

	public function url( $pt, $item, $key ) {
		if ( 'permalink' === $key ) {
			return (string) get_permalink( $item );
		}
		return parent::url( $pt, $item, $key );
	}
}
