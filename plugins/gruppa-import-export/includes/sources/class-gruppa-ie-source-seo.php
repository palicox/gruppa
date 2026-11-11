<?php
/**
 * Import/Export source: SEO Builder.
 *
 * Owner on import (column mapped to "owner"):
 *   12                  WordPress post ID of the item's page (e.g. from a JetEngine SEO CCT)
 *   stranky:domov       type slug : item slug
 *   stranky:#5          type slug : item ID
 * An existing SEO of the same owner is updated, otherwise created.
 */

defined( 'ABSPATH' ) || exit;

class Gruppa_IE_Source_SEO extends Gruppa_IE_Source {

	public function id() {
		return 'seo';
	}

	public function label() {
		return 'Gruppa CMS — SEO Builder';
	}

	public function available() {
		return class_exists( 'Gruppa_SEO' );
	}

	public function id_key() {
		return 'id';
	}

	public function system_keys() {
		return array( 'id', 'owner_post_id', 'modified' );
	}

	public function types() {
		return array( 'seo' => array( 'name' => 'SEO', 'count' => Gruppa_SEO::count() ) );
	}

	public function fields( $slug ) {
		$out = array(
			array( 'id', 'id', 'overwrites the SEO with the same ID', true ),
			array( 'owner', 'owner', 'WP post ID · type:slug · type:#ID', true ),
		);
		foreach ( Gruppa_SEO::fields() as $k => $def ) {
			$hint = 'image' === $def[1] ? 'image ID or URL' : ( 'robots' === $def[1] ? 'index, follow …' : $def[1] );
			$out[] = array( $k, $def[0], $hint, true );
		}
		return $out;
	}

	/** Item for an owner reference, or null: [ type slug, item ID ]. */
	private function resolve_owner( $v ) {
		$v = trim( (string) ( is_scalar( $v ) ? $v : '' ) );
		if ( '' === $v ) {
			return null;
		}
		if ( ctype_digit( $v ) ) {
			$type = get_post_meta( (int) $v, '_gruppa_type', true );
			$iid  = (int) get_post_meta( (int) $v, '_gruppa_item', true );
			return $type && $iid && Gruppa_SEO::owner( $type, $iid ) ? array( $type, $iid ) : null;
		}
		if ( false === strpos( $v, ':' ) ) {
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

	public function export( $slug, $status, $system ) {
		$cols = array_merge( $system ? array( 'id' ) : array(), array( 'owner' ), $system ? array( 'owner_post_id' ) : array(), array_keys( Gruppa_SEO::fields() ), $system ? array( 'modified' ) : array() );
		$out  = array();
		foreach ( Gruppa_SEO::all() as $r ) {
			$o    = Gruppa_SEO::owner( $r->owner_type, (int) $r->owner_id );
			$item = array(
				'id'            => (int) $r->id,
				'owner'         => $o ? $r->owner_type . ':' . $o[1]->slug : $r->owner_type . ':#' . $r->owner_id,
				'owner_post_id' => $o && $o[1]->wp_post_id ? (int) $o[1]->wp_post_id : '',
				'modified'      => $r->modified,
			);
			foreach ( Gruppa_SEO::fields() as $k => $def ) {
				$v = isset( $r->{$k} ) ? $r->{$k} : '';
				if ( 'image' === $def[1] ) {
					$v = $v ? (string) wp_get_attachment_url( (int) $v ) : '';
				} elseif ( 'robots' === $def[1] ) {
					$v = Gruppa_SEO::ROBOTS[ Gruppa_SEO::robots( $v ) ];
				}
				$item[ $k ] = null === $v ? '' : $v;
			}
			$row = array();
			foreach ( $cols as $c ) {
				$row[ $c ] = $item[ $c ];
			}
			$out[] = $row;
		}
		return array( $cols, $out );
	}

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
						$res['errors'][] = sprintf( 'Row %d: owner "%s" not found (WP post ID, type:slug or type:#ID of a Content item).', $n, self::scalar( $src[ $col ] ) );
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
