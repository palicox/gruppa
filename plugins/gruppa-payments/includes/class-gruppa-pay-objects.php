<?php
defined( 'ABSPATH' ) || exit;

/**
 * Objects a payment belongs to — object_type / object_id:
 *
 *   gruppa:{slug}  item of a Gruppa CMS content type (wp_gcms_type_{slug})
 *   jet:{slug}     item of a JetEngine CCT (wp_jet_cct_{slug}, _ID)
 *   post           WordPress post (meta)
 *   user           WordPress user (meta)
 *
 * Sync (payment meta "sync", set by the form action or the PHP API):
 *   field   column / meta key that gets the payment state
 *   paid    value when paid or the subscription is active (e.g. "paid")
 *   unpaid  value when a subscription is canceled / expired or the payment is refunded ('' = leave it)
 *   until   date column / meta key for "paid until" (subscriptions)
 *   publish true = item status publish when paid
 * A subscription that is past due keeps the paid value until paid_until + grace days (daily check).
 */
class Gruppa_Pay_Objects {

	public static function clean_sync( $s ) {
		$s = is_array( $s ) ? $s : array();
		return array(
			'field'   => isset( $s['field'] ) ? sanitize_key( $s['field'] ) : '',
			'paid'    => isset( $s['paid'] ) ? sanitize_text_field( (string) $s['paid'] ) : '',
			'unpaid'  => isset( $s['unpaid'] ) ? sanitize_text_field( (string) $s['unpaid'] ) : '',
			'until'   => isset( $s['until'] ) ? sanitize_key( $s['until'] ) : '',
			'publish' => ! empty( $s['publish'] ),
		);
	}

	public static function sync( $payment, $old ) {
		if ( '' === $payment->object_type || ! $payment->object_id ) {
			return;
		}
		$s = self::clean_sync( isset( $payment->meta['sync'] ) ? $payment->meta['sync'] : array() );
		$v = array();
		if ( in_array( $payment->status, array( 'paid', 'active' ), true ) ) {
			if ( $s['field'] && '' !== $s['paid'] ) {
				$v[ $s['field'] ] = $s['paid'];
			}
			if ( $s['until'] && $payment->paid_until ) {
				$v[ $s['until'] ] = $payment->paid_until;
			}
			if ( $s['publish'] ) {
				$v['status'] = 'publish';
			}
		} elseif ( $old !== $payment->status && in_array( $payment->status, array( 'canceled', 'expired', 'refunded' ), true ) && in_array( $old, array( 'paid', 'active', 'past_due' ), true ) ) {
			if ( $s['field'] && '' !== $s['unpaid'] ) {
				$v[ $s['field'] ] = $s['unpaid'];
			}
		}
		$v = (array) apply_filters( 'gruppa_payments/sync_values', $v, $payment, $old );
		if ( $v ) {
			self::update( $payment->object_type, $payment->object_id, $v );
		}
	}

	/** Write values to the object. 'status' = publish / draft status of the item. */
	public static function update( $object_type, $object_id, $values ) {
		global $wpdb;
		$object_id = (int) $object_id;
		list( $kind, $slug ) = array_pad( explode( ':', (string) $object_type, 2 ), 2, '' );
		$slug = sanitize_key( $slug );

		// Own object types: return true / false from the filter (null = not handled here).
		$handled = apply_filters( 'gruppa_payments/update_object', null, $object_type, $object_id, $values );
		if ( null !== $handled ) {
			return (bool) $handled;
		}

		switch ( $kind ) {
			case 'gruppa':
				if ( ! class_exists( 'Gruppa_Types' ) || ! class_exists( 'Gruppa_Items' ) ) {
					return false;
				}
				$type = Gruppa_Types::get_by_slug( $slug );
				if ( ! $type ) {
					return false;
				}
				$cols = Gruppa_DB::columns( $slug );
				$row  = array_intersect_key( $values, array_flip( $cols ) );
				return $row ? ! is_wp_error( Gruppa_Items::save( $type, $row, $object_id ) ) : false;

			case 'jet':
				$table = $wpdb->prefix . 'jet_cct_' . $slug;
				if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
					return false;
				}
				if ( isset( $values['status'] ) ) {
					$values['cct_status'] = $values['status'];
					unset( $values['status'] );
				}
				$cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" ); // phpcs:ignore
				$row  = array_intersect_key( $values, array_flip( $cols ) );
				if ( in_array( 'cct_modified', $cols, true ) ) {
					$row['cct_modified'] = current_time( 'mysql' );
				}
				return $row ? false !== $wpdb->update( $table, $row, array( '_ID' => $object_id ) ) : false;

			case 'post':
				if ( isset( $values['status'] ) ) {
					wp_update_post( array( 'ID' => $object_id, 'post_status' => $values['status'] ) );
					unset( $values['status'] );
				}
				foreach ( $values as $k => $val ) {
					update_post_meta( $object_id, $k, $val );
				}
				return true;

			case 'user':
				unset( $values['status'] );
				foreach ( $values as $k => $val ) {
					update_user_meta( $object_id, $k, $val );
				}
				return true;
		}
		do_action( 'gruppa_payments/update_custom_object', $object_type, $object_id, $values );
		return false;
	}

	/** [ label, admin URL ] of the object. */
	public static function describe( $payment ) {
		if ( '' === $payment->object_type ) {
			return array( '—', '' );
		}
		list( $kind, $slug ) = array_pad( explode( ':', $payment->object_type, 2 ), 2, '' );
		$id = (int) $payment->object_id;
		switch ( $kind ) {
			case 'gruppa':
				$type  = class_exists( 'Gruppa_Types' ) ? Gruppa_Types::get_by_slug( $slug ) : null;
				$item  = $type && class_exists( 'Gruppa_Items' ) ? Gruppa_Items::get( $type, $id ) : null;
				$label = ( $type ? $type->name : $slug ) . ' · ' . ( $item && '' !== $item->title ? $item->title : '#' . $id );
				$url   = $type && class_exists( 'Gruppa_Admin_Items' ) ? add_query_arg( array( 'page' => Gruppa_Admin_Items::page_slug( $slug ), 'view' => 'edit', 'id' => $id ), admin_url( 'admin.php' ) ) : '';
				return array( $label, $url );
			case 'jet':
				return array( 'CCT ' . $slug . ' · #' . $id, '' );
			case 'post':
				return array( get_the_title( $id ) ? get_the_title( $id ) : 'Post #' . $id, (string) get_edit_post_link( $id, 'raw' ) );
			case 'user':
				$u = get_userdata( $id );
				return array( $u ? $u->display_name : 'User #' . $id, $u ? get_edit_user_link( $id ) : '' );
		}
		return array( $payment->object_type . ' #' . $id, '' );
	}
}
