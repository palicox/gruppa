<?php
defined( 'ABSPATH' ) || exit;

/**
 * Duplicate content types, Dynamic Forms, listings and Search Filters groups.
 * A copy is always a Draft named "… (copy)" with a free slug.
 * (Items: Gruppa_Items::duplicate().)
 */
class Gruppa_Duplicate {

	const KINDS = array( 'type', 'form', 'listing', 'filters' );

	/** Free slug: {slug}_copy, {slug}_copy2 … (max 40 characters). */
	public static function slug( $base, $exists ) {
		$base = substr( Gruppa_Types::sanitize_key( $base ), 0, 33 );
		$slug = $base . '_copy';
		$n    = 2;
		while ( call_user_func( $exists, $slug ) ) {
			$slug = $base . '_copy' . $n++;
		}
		return $slug;
	}

	/** @return int|WP_Error ID of the copy */
	public static function run( $kind, $id ) {
		switch ( $kind ) {
			case 'type':
				return self::type( $id );
			case 'form':
				return self::form( $id );
			case 'listing':
				return self::listing( $id );
			case 'filters':
				return self::filters( $id );
		}
		return new WP_Error( 'missing', 'Not found' );
	}

	/** Content type: settings and fields, without items. */
	public static function type( $id ) {
		$t = Gruppa_Types::get( $id );
		if ( ! $t ) {
			return new WP_Error( 'missing', 'Not found' );
		}
		$fields = array();
		foreach ( $t->fields as $f ) {
			if ( 'switcher' === $f['type'] ) {
				$f['default_switch'] = '1' === (string) $f['default'];
			}
			$f['options_mode'] = 'manual';
			$fields[]          = $f;
		}
		$slug = self::slug( $t->slug, function ( $s ) {
			return Gruppa_Types::get_by_slug( $s ) || Gruppa_DB::data_table_exists( $s ) || Gruppa_DB::is_reserved( $s );
		} );
		return Gruppa_Types::save( array(
			'name'      => $t->name . ' (copy)',
			'slug'      => $slug,
			'kind'      => $t->kind,
			'post_type' => $t->custom_pt ? Gruppa_Types::CUSTOM : $t->post_type,
			'structure' => $t->structure,
			'link'      => $t->link,
			'groups'    => array_map( function ( $g ) {
				unset( $g['id'] ); // New type → new group IDs.
				return $g;
			}, $t->groups ),
			'status'    => 'draft',
			'access'    => Gruppa_Access::get( $t ),
			'fields'    => $fields,
		) );
	}

	public static function form( $id ) {
		$f = Gruppa_Forms::get( $id );
		if ( ! $f ) {
			return new WP_Error( 'missing', 'Not found' );
		}
		return Gruppa_Forms::save( array(
			'name'      => $f->name . ' (copy)',
			'slug'      => self::slug( $f->slug, array( 'Gruppa_Forms', 'get_by' ) ),
			'type_slug' => $f->type_slug,
			'status'    => 'draft',
			'settings'  => $f->settings,
		) );
	}

	/** Listing + a copy of its Elementor template. */
	public static function listing( $id ) {
		$l = Gruppa_Listing::get( $id );
		if ( ! $l ) {
			return new WP_Error( 'missing', 'Not found' );
		}
		$new = Gruppa_Listing::save( array(
			'name'      => $l->name . ' (copy)',
			'slug'      => self::slug( $l->slug, array( 'Gruppa_Listing', 'get_by' ) ),
			'type_slug' => $l->type_slug,
			'status'    => 'draft',
			'settings'  => $l->settings,
		) );
		if ( is_wp_error( $new ) || ! $l->template_id ) {
			return $new;
		}
		$src = get_post( (int) $l->template_id );
		if ( ! $src || Gruppa_Listing::CPT !== $src->post_type ) {
			return $new;
		}
		$pid = wp_insert_post( wp_slash( array(
			'post_type'    => Gruppa_Listing::CPT,
			'post_status'  => 'publish',
			'post_title'   => 'Listing: ' . $l->name . ' (copy)',
			'post_content' => $src->post_content,
		) ) );
		if ( ! $pid || is_wp_error( $pid ) ) {
			return $new;
		}
		foreach ( get_post_meta( $src->ID ) as $key => $values ) {
			if ( in_array( $key, array( '_edit_lock', '_edit_last', '_elementor_css', '_gruppa_listing' ), true ) ) {
				continue;
			}
			foreach ( $values as $v ) {
				add_post_meta( $pid, $key, wp_slash( maybe_unserialize( $v ) ) );
			}
		}
		update_post_meta( $pid, '_gruppa_listing', (int) $new );
		global $wpdb;
		$wpdb->update( Gruppa_Listing::table(), array( 'template_id' => (int) $pid ), array( 'id' => (int) $new ) );
		return $new;
	}

	public static function filters( $id ) {
		$g = Gruppa_Filters::get( $id );
		if ( ! $g ) {
			return new WP_Error( 'missing', 'Not found' );
		}
		return Gruppa_Filters::save( array(
			'name'      => $g->name . ' (copy)',
			'slug'      => self::slug( $g->slug, array( 'Gruppa_Filters', 'get_by' ) ),
			'type_slug' => $g->type_slug,
			'status'    => 'draft',
			'settings'  => $g->settings,
		) );
	}
}
