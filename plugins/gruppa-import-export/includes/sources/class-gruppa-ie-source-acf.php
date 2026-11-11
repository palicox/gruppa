<?php
/**
 * Source: WordPress post types + ACF fields.
 * Works without ACF too (core post fields only).
 */

defined( 'ABSPATH' ) || exit;

class Gruppa_IE_Source_ACF extends Gruppa_IE_Source {

	const EXCLUDE = array(
		'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request',
		'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_font_family', 'wp_font_face',
		'acf-field-group', 'acf-field', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page',
		'elementor_library', 'e-landing-page', 'e-floating-buttons',
		'jet-engine', 'jet-engine-booking', 'jet-form-builder', 'jet-smart-filters', 'jet-popup', 'jet-theme-core', 'jet-menu',
	);

	const STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );

	/** Core post fields: key => [ label, postarr key, needs support ]. */
	const CORE = array(
		'post_title'   => array( 'Title', 'title' ),
		'post_content' => array( 'Content', 'editor' ),
		'post_excerpt' => array( 'Excerpt', 'excerpt' ),
		'post_name'    => array( 'Slug', '' ),
		'post_status'  => array( 'Status', '' ),
		'menu_order'   => array( 'Order', '' ),
		'featured_image' => array( 'Featured image', 'thumbnail' ),
	);

	private $acf_cache = array();

	public function id() {
		return 'acf';
	}

	public function label() {
		return 'WordPress — posts, pages, post types' . ( function_exists( 'acf_get_field_groups' ) ? ' + ACF' : '' );
	}

	public function available() {
		return true;
	}

	public function id_key() {
		return 'ID';
	}

	public function system_keys() {
		return array( 'ID', 'post_status', 'menu_order', 'post_author', 'post_date' );
	}

	public function types() {
		$out = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $pt ) {
			if ( in_array( $pt->name, self::EXCLUDE, true ) ) {
				continue;
			}
			$counts = wp_count_posts( $pt->name );
			$n      = 0;
			foreach ( self::STATUSES as $s ) {
				$n += isset( $counts->$s ) ? (int) $counts->$s : 0;
			}
			$out[ $pt->name ] = array(
				'name'  => $pt->labels->name,
				'count' => $n,
			);
		}
		return $out;
	}

	/** Core field keys available for the post type. */
	private function core_keys( $pt ) {
		$out = array();
		foreach ( self::CORE as $key => $c ) {
			if ( '' === $c[1] || post_type_supports( $pt, $c[1] ) ) {
				$out[ $key ] = $c[0];
			}
		}
		return $out;
	}

	/** Taxonomies of the post type: column key (tax_name) => WP_Taxonomy. */
	public function taxonomies( $pt ) {
		$out = array();
		foreach ( get_object_taxonomies( $pt, 'objects' ) as $tax ) {
			if ( $tax->show_ui ) {
				$out[ 'tax_' . $tax->name ] = $tax;
			}
		}
		return $out;
	}

	/** @return array name => ACF field array */
	public function acf_fields( $pt ) {
		if ( isset( $this->acf_cache[ $pt ] ) ) {
			return $this->acf_cache[ $pt ];
		}
		$out = array();
		if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
			foreach ( (array) acf_get_field_groups( array( 'post_type' => $pt ) ) as $group ) {
				foreach ( (array) acf_get_fields( $group ) as $f ) {
					if ( empty( $f['name'] ) || in_array( $f['type'], array( 'tab', 'message', 'accordion' ), true ) ) {
						continue;
					}
					if ( ! isset( $out[ $f['name'] ] ) && ! isset( self::CORE[ $f['name'] ] ) ) {
						$out[ $f['name'] ] = $f;
					}
				}
			}
		}
		$this->acf_cache[ $pt ] = $out;
		return $out;
	}

	public function fields( $pt ) {
		$out = array( array( 'ID', 'ID', 'overwrites the post with the same ID', true ) );
		foreach ( $this->core_keys( $pt ) as $key => $label ) {
			$out[] = array( $key, $label, 'post_status' === $key ? 'publish / draft / pending / private' : 'WordPress', true );
		}
		foreach ( $this->taxonomies( $pt ) as $key => $tax ) {
			$out[] = array( $key, $tax->labels->name, 'taxonomy · names or IDs, comma separated', true );
		}
		foreach ( $this->acf_fields( $pt ) as $name => $f ) {
			$out[] = array( $name, $f['label'] ? $f['label'] : $name, 'ACF · ' . $f['type'], true );
		}
		$out[] = array( 'post_author', 'post_author', 'system', false );
		$out[] = array( 'post_date', 'post_date', 'system', false );
		return $out;
	}

	/* ---------- export ---------- */

	public function export( $pt, $status, $system ) {
		$core = $this->core_keys( $pt );
		$acf  = $this->acf_fields( $pt );

		$cols = $system ? array( 'ID' ) : array();
		foreach ( array_keys( $core ) as $k ) {
			if ( $system || ! in_array( $k, array( 'post_status', 'menu_order' ), true ) ) {
				$cols[] = $k;
			}
		}
		$taxes = $this->taxonomies( $pt );
		$cols  = array_merge( $cols, array_keys( $taxes ), array_keys( $acf ) );
		if ( $system ) {
			$cols[] = 'post_author';
			$cols[] = 'post_date';
		}

		$ids = get_posts(
			array(
				'post_type'        => $pt,
				'post_status'      => in_array( $status, self::STATUSES, true ) ? $status : self::STATUSES,
				'posts_per_page'   => -1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		$items = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			$item = array();
			foreach ( $cols as $c ) {
				if ( 'ID' === $c || 'post_author' === $c || 'menu_order' === $c ) {
					$v = (int) $post->$c;
				} elseif ( 'featured_image' === $c ) {
					// URL is portable between sites; import accepts URL or attachment ID.
					$v = (int) get_post_thumbnail_id( $id );
					$v = $v ? wp_get_attachment_url( $v ) : '';
				} elseif ( isset( $taxes[ $c ] ) ) {
					$terms = wp_get_object_terms( $id, $taxes[ $c ]->name, array( 'fields' => 'names' ) );
					$v     = is_wp_error( $terms ) ? '' : implode( ', ', $terms );
				} elseif ( isset( $acf[ $c ] ) ) {
					$v = get_field( $acf[ $c ]['key'], $id, false );
					if ( in_array( $acf[ $c ]['type'], array( 'image', 'file' ), true ) ) {
						$v = self::attachment_url( $v );
					} elseif ( 'gallery' === $acf[ $c ]['type'] ) {
						$v = self::attachment_urls( $v );
					}
				} else {
					$v = $post->$c;
				}
				$item[ $c ] = null === $v || false === $v ? '' : $v;
			}
			$items[] = $item;
		}
		return array( $cols, $items );
	}

	/* ---------- import ---------- */

	public function import( $pt, $mapping, $rows, $offset, $author, $keep ) {
		$core  = $this->core_keys( $pt );
		$acf   = $this->acf_fields( $pt );
		$taxes = $this->taxonomies( $pt );

		$map    = array();
		$id_col = null;
		foreach ( $mapping as $col => $target ) {
			if ( 'ID' === $target ) {
				$id_col = $col;
			} elseif ( isset( $core[ $target ] ) || isset( $acf[ $target ] ) || isset( $taxes[ $target ] ) || in_array( $target, array( 'post_author', 'post_date' ), true ) ) {
				$map[ $col ] = $target;
			}
		}

		$res = self::result();

		foreach ( $rows as $i => $src ) {
			// Media downloads can be slow: stop after the time budget, the browser sends the rest.
			if ( $i > 0 && self::time_up() ) {
				break;
			}
			$res['processed'] = $i + 1;
			$n = $offset + $i + 1;
			if ( ! is_array( $src ) ) {
				$res['skipped']++;
				continue;
			}

			$id       = null !== $id_col && isset( $src[ $id_col ] ) ? absint( self::scalar( $src[ $id_col ] ) ) : 0;
			$existing = $id ? get_post( $id ) : null;
			if ( ! $existing || $existing->post_type !== $pt ) {
				$existing = null;
				$id       = 0;
			}

			$post  = array();
			$meta  = array();
			$terms = array();
			$thumb = null;

			foreach ( $map as $col => $target ) {
				if ( ! array_key_exists( $col, $src ) ) {
					continue;
				}
				$v = $src[ $col ];
				switch ( $target ) {
					case 'post_title':
						$post['post_title'] = sanitize_text_field( self::scalar( $v ) );
						break;
					case 'post_content':
						$post['post_content'] = wp_kses_post( self::scalar( $v ) );
						break;
					case 'post_excerpt':
						$post['post_excerpt'] = sanitize_textarea_field( self::scalar( $v ) );
						break;
					case 'post_name':
						$post['post_name'] = sanitize_title( self::scalar( $v ) );
						break;
					case 'post_status':
						$s                   = strtolower( self::scalar( $v ) );
						$s                   = 'koncept' === $s ? 'draft' : $s;
						$post['post_status'] = in_array( $s, self::STATUSES, true ) ? $s : 'publish';
						break;
					case 'menu_order':
						$post['menu_order'] = (int) self::scalar( $v );
						break;
					case 'featured_image':
						$thumb = self::attachment_id( $v );
						break;
					case 'post_author':
						$u = self::user_id( $v );
						if ( $u ) {
							$post['post_author'] = $u;
						}
						break;
					case 'post_date':
						$d = self::date( $v );
						if ( $d ) {
							$post['post_date']     = $d;
							$post['post_date_gmt'] = get_gmt_from_date( $d );
						}
						break;
					default:
						if ( isset( $taxes[ $target ] ) ) {
							// Numeric = term ID, text = term name (created when missing).
							$terms[ $taxes[ $target ]->name ] = array_map(
								function ( $t ) {
									return ctype_digit( $t ) ? (int) $t : sanitize_text_field( $t );
								},
								self::to_list( $v )
							);
						} else {
							$meta[ $target ] = $this->acf_value( $acf[ $target ], $v );
						}
				}
			}

			if ( ! $post && ! $meta && ! $terms && null === $thumb ) {
				$res['skipped']++;
				continue;
			}

			if ( $existing ) {
				$post['ID'] = $id;
				if ( ! isset( $post['post_author'] ) && ! $keep ) {
					$post['post_author'] = $author;
				}
				$pid = count( $post ) > 1 ? wp_update_post( wp_slash( $post ), true ) : $id;
			} else {
				$post['post_type'] = $pt;
				if ( ! isset( $post['post_status'] ) ) {
					$post['post_status'] = 'publish';
				}
				if ( ! isset( $post['post_author'] ) ) {
					$post['post_author'] = $author;
				}
				if ( empty( $post['post_title'] ) && empty( $post['post_content'] ) ) {
					$post['post_title'] = '#' . $n; // WordPress refuses fully empty posts.
				}
				$pid = wp_insert_post( wp_slash( $post ), true );
			}

			if ( is_wp_error( $pid ) || ! $pid ) {
				$res['errors'][] = sprintf( 'Row %d: %s', $n, is_wp_error( $pid ) ? $pid->get_error_message() : 'the post could not be saved.' );
				$res['skipped']++;
				continue;
			}

			if ( null !== $thumb ) {
				$thumb ? set_post_thumbnail( $pid, $thumb ) : delete_post_thumbnail( $pid );
			}
			foreach ( $terms as $tax => $list ) {
				$set = wp_set_object_terms( $pid, $list, $tax );
				if ( is_wp_error( $set ) ) {
					$res['errors'][] = sprintf( 'Row %d (%s): %s', $n, $tax, $set->get_error_message() );
				}
			}
			foreach ( $meta as $name => $v ) {
				if ( function_exists( 'update_field' ) ) {
					update_field( $acf[ $name ]['key'], $v, $pid );
				} else {
					update_post_meta( $pid, $name, $v );
				}
			}

			$existing ? $res['updated']++ : $res['created']++;
		}

		return $res;
	}

	/** File value → what update_field() expects for the ACF field type. */
	private function acf_value( $f, $v ) {
		$multi = ! empty( $f['multiple'] );
		switch ( $f['type'] ) {
			case 'true_false':
				return self::truthy( $v );
			case 'number':
			case 'range':
				return self::number( $v );
			case 'date_picker':
				$d = self::date( $v, 'Ymd' );
				return $d ? $d : '';
			case 'date_time_picker':
				$d = self::date( $v );
				return $d ? $d : '';
			case 'image':
			case 'file':
				$a = self::attachment_id( $v );
				return $a ? $a : '';
			case 'gallery':
				$ids = array();
				foreach ( self::to_list( $v ) as $item ) {
					$a = self::attachment_id( $item );
					if ( $a ) {
						$ids[] = $a;
					}
				}
				return $ids;
			case 'relationship':
				return array_map( 'intval', self::to_list( $v ) );
			case 'post_object':
			case 'user':
			case 'page_link':
				$list = self::to_list( $v );
				if ( 'page_link' !== $f['type'] ) {
					$list = array_map( 'intval', $list );
				}
				return $multi ? $list : ( $list ? $list[0] : '' );
			case 'taxonomy':
				$list = array_map( 'intval', self::to_list( $v ) );
				return in_array( isset( $f['field_type'] ) ? $f['field_type'] : '', array( 'checkbox', 'multi_select' ), true ) ? $list : ( $list ? $list[0] : '' );
			case 'checkbox':
				return array_map( 'sanitize_text_field', self::to_list( $v ) );
			case 'select':
				$list = array_map( 'sanitize_text_field', self::to_list( $v ) );
				return $multi ? $list : ( $list ? $list[0] : '' );
			case 'repeater':
			case 'group':
			case 'flexible_content':
			case 'google_map':
			case 'link':
				$d = self::maybe_json( $v );
				return is_array( $d ) ? self::sanitize_deep( $d ) : '';
			case 'wysiwyg':
				return wp_kses_post( self::scalar( $v ) );
			case 'textarea':
				return sanitize_textarea_field( self::scalar( $v ) );
			case 'url':
				return esc_url_raw( self::scalar( $v ) );
			case 'email':
				return sanitize_email( self::scalar( $v ) );
			case 'oembed':
				return esc_url_raw( self::scalar( $v ) );
			default:
				$d = self::maybe_json( $v );
				return is_array( $d ) ? self::sanitize_deep( $d ) : sanitize_text_field( self::scalar( $v ) );
		}
	}
}
