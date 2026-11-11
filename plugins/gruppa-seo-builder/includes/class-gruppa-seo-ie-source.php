<?php
/**
 * Import/Export source "SEO Builder" with JetEngine CCT and WordPress owners.
 * The same file runs in Gruppa CMS (built-in SEO Builder) and in the separate plugin Gruppa SEO Builder.
 *
 * Replaces the source of Gruppa Import/Export (Gruppa_IE_Source_SEO, id "seo") through the filter
 * gruppa_ie/sources — the same columns and export, but the owner on import can also be an item of a
 * JetEngine CCT and Gruppa CMS does not have to be active. Works with the separate plugin
 * Gruppa Import/Export and with Data Tools in Gruppa CMS.
 *
 * Owner on import (column mapped to "owner"):
 *   12                      WordPress post ID of the page (Gruppa CMS item or single page of a CCT item)
 *   https://…/domov/        address of the page (also /domov/)
 *   jet:pages:#5            JetEngine CCT slug : _ID of the item
 *   jet:pages:domov         JetEngine CCT slug : slug of its single page (as exported)
 *   wp:page:#12 / wp:page:kontakt   WordPress post type : post ID / slug (pages and posts)
 *   stranky:domov           Gruppa CMS entity slug : item slug
 *   stranky:#5              Gruppa CMS entity slug : item ID
 * An existing SEO of the same owner is updated, otherwise created.
 */

defined( 'ABSPATH' ) || exit;

class Gruppa_SEO_IE_Source extends Gruppa_IE_Source_SEO {

	/** @var array|null owner_type => [ post slug => item ID ] for JetEngine CCT and WordPress */
	private $slugs = null;

	public function label() {
		return Gruppa_SEO_Owners::cms() ? 'Gruppa CMS — SEO Builder' : 'SEO Builder';
	}

	public function fields( $slug ) {
		$out = parent::fields( $slug );
		foreach ( $out as $i => $f ) {
			if ( 'owner' === $f[0] ) {
				$out[ $i ][2] = 'WP post ID · page URL · jet:cct:#ID · jet:cct:slug · wp:page:slug' . ( Gruppa_SEO_Owners::cms() ? ' · type:slug · type:#ID' : '' );
			}
		}
		return $out;
	}

	/** [ owner_type, owner_id ] or null. */
	public function resolve_owner( $v ) {
		$v = trim( (string) ( is_scalar( $v ) ? $v : '' ) );
		if ( '' === $v ) {
			return null;
		}
		// WordPress post ID.
		if ( ctype_digit( $v ) ) {
			return $this->checked( Gruppa_SEO_Owners::from_post( (int) $v ) );
		}
		// Address of the page.
		if ( '/' === $v[0] || preg_match( '#^https?://#i', $v ) ) {
			$url = '/' === $v[0] ? home_url( $v ) : $v;
			$pid = url_to_postid( $url );
			if ( $pid ) {
				return $this->checked( Gruppa_SEO_Owners::from_post( $pid ) );
			}
			return $this->by_url( $url );
		}
		if ( false === strpos( $v, ':' ) ) {
			return null;
		}
		// jet:{cct}:#ID / jet:{cct}:{slug}, wp:{post type}:#ID / wp:{post type}:{slug}
		$pre = Gruppa_SEO_Owners::prefix( $v );
		if ( '' !== $pre ) {
			$rest = substr( $v, strlen( $pre ) );
			if ( false === strpos( $rest, ':' ) ) {
				return null;
			}
			list( $sub, $ref ) = array_map( 'trim', explode( ':', $rest, 2 ) );
			$otype = Gruppa_SEO_Owners::sanitize_type( $pre . $sub );
			if ( '#' === substr( $ref, 0, 1 ) ) {
				return $this->checked( array( $otype, absint( substr( $ref, 1 ) ) ) );
			}
			$map = $this->slugs();
			$ref = sanitize_title( $ref );
			return isset( $map[ $otype ][ $ref ] ) ? array( $otype, $map[ $otype ][ $ref ] ) : null;
		}
		// {entity}:#ID / {entity}:{slug} — Gruppa CMS.
		if ( ! Gruppa_SEO_Owners::cms() ) {
			return null;
		}
		list( $tslug, $ref ) = array_map( 'trim', explode( ':', $v, 2 ) );
		$type = Gruppa_Types::get_by_slug( sanitize_key( $tslug ) );
		if ( ! $type || 'content' !== $type->kind ) {
			return null;
		}
		if ( '#' === substr( $ref, 0, 1 ) ) {
			$item = Gruppa_Items::get( $type, absint( substr( $ref, 1 ) ) );
		} else {
			global $wpdb;
			$id   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM `' . Gruppa_DB::data_table( $type->slug ) . '` WHERE slug = %s', sanitize_title( $ref ) ) ); // phpcs:ignore
			$item = $id ? Gruppa_Items::get( $type, $id ) : null;
		}
		return $item ? array( $type->slug, (int) $item->id ) : null;
	}

	/**
	 * Address that url_to_postid() does not know (e.g. ?produkty=slug without pretty permalinks):
	 * the slug from the query or the last path segment, when exactly one page of an owner has it.
	 */
	private function by_url( $url ) {
		$parts = wp_parse_url( $url );
		$cands = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $q );
			foreach ( (array) $q as $val ) {
				if ( is_string( $val ) && '' !== $val ) {
					$cands[] = sanitize_title( $val );
				}
			}
		}
		if ( ! empty( $parts['path'] ) ) {
			$seg = array_values( array_filter( explode( '/', $parts['path'] ) ) );
			if ( $seg ) {
				$cands[] = sanitize_title( end( $seg ) );
			}
		}
		foreach ( $cands as $slug ) {
			$hits = array();
			foreach ( $this->slugs() as $otype => $map ) {
				if ( isset( $map[ $slug ] ) ) {
					$hits[] = array( $otype, $map[ $slug ] );
				}
			}
			if ( 1 === count( $hits ) ) {
				return $hits[0];
			}
		}
		return null;
	}

	/** The reference only when the owner exists. */
	private function checked( $ref ) {
		return $ref && Gruppa_SEO::owner( $ref[0], $ref[1] ) ? $ref : null;
	}

	private function slugs() {
		if ( null === $this->slugs ) {
			$this->slugs = array();
			foreach ( Gruppa_SEO_Owners::groups() as $otype => $g ) {
				if ( 'gruppa' === $g['source'] ) {
					continue;
				}
				foreach ( $g['posts'] as $iid => $pid ) {
					$post = get_post( $pid );
					if ( $post && '' !== $post->post_name ) {
						$this->slugs[ $otype ][ $post->post_name ] = (int) $iid;
					}
				}
			}
		}
		return $this->slugs;
	}

	/** Same as the parent, with the owner resolver above. */
	public function import( $slug, $mapping, $rows, $offset, $author, $keep ) {
		$res    = self::result();
		$fields = Gruppa_SEO::fields();
		foreach ( $rows as $i => $src ) {
			$n = $offset + $i + 1;
			if ( ! is_array( $src ) ) {
				$res['skipped']++;
				continue;
			}
			$data  = array();
			$id    = 0;
			$owner = null;
			$bad   = false;
			foreach ( $mapping as $col => $target ) {
				if ( ! array_key_exists( $col, $src ) ) {
					continue;
				}
				if ( 'id' === $target ) {
					$id = absint( self::scalar( $src[ $col ] ) );
				} elseif ( 'owner' === $target ) {
					$owner = $this->resolve_owner( $src[ $col ] );
					if ( ! $owner && '' !== self::scalar( $src[ $col ] ) ) {
						$bad             = true;
						$res['errors'][] = sprintf( 'Row %d: owner "%s" not found (WP post ID, page URL, jet:cct:#ID, jet:cct:slug or wp:page:slug of an item with a page).', $n, self::scalar( $src[ $col ] ) );
					}
				} elseif ( isset( $fields[ $target ] ) ) {
					$data[ $target ] = self::scalar( $src[ $col ] );
				}
			}
			$current = $id ? Gruppa_SEO::get( $id ) : null;
			if ( ! $current && $owner ) {
				$current = Gruppa_SEO::get_by_owner( $owner[0], $owner[1] );
			}
			if ( ! $current && ! $owner ) {
				if ( ! $bad ) {
					$res['errors'][] = sprintf( 'Row %d: owner is required.', $n );
				}
				$res['skipped']++;
				continue;
			}
			if ( $owner ) {
				$data['owner_type'] = $owner[0];
				$data['owner_id']   = $owner[1];
			}
			$saved = Gruppa_SEO::save( $data, $current ? (int) $current->id : 0 );
			if ( is_wp_error( $saved ) ) {
				$res['errors'][] = sprintf( 'Row %d: %s', $n, $saved->get_error_message() );
				$res['skipped']++;
				continue;
			}
			$current ? $res['updated']++ : $res['created']++;
		}
		return $res;
	}
}
