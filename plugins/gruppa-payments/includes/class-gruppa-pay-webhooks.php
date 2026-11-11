<?php
defined( 'ABSPATH' ) || exit;

/**
 * Webhooks: POST /wp-json/gruppa-payments/v1/{stripe|paypal}
 *
 * The gateway verifies the request and returns the event; each event ID is handled once
 * (unique key in wp_gcms_payment_events — providers resend events). Failed handlers are
 * removed from the log so the provider's retry can run them again.
 */
class Gruppa_Pay_Webhooks {

	const NS = 'gruppa-payments/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function url( $provider ) {
		return rest_url( self::NS . '/' . $provider );
	}

	public static function routes() {
		foreach ( array_keys( Gruppa_Payments::gateways() ) as $id ) {
			register_rest_route( self::NS, '/' . $id, array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $req ) use ( $id ) {
					return Gruppa_Pay_Webhooks::handle( $id, $req );
				},
				'permission_callback' => '__return_true', // Authenticated by the provider signature.
			) );
		}
	}

	public static function handle( $provider, WP_REST_Request $req ) {
		global $wpdb;
		$gateway = Gruppa_Payments::gateway( $provider );
		if ( ! $gateway ) {
			return new WP_REST_Response( array( 'error' => 'unknown provider' ), 404 );
		}
		$ev = $gateway->webhook( $req );
		if ( is_wp_error( $ev ) ) {
			return new WP_REST_Response( array( 'error' => $ev->get_error_message() ), 400 );
		}

		$table    = Gruppa_Pay_DB::events();
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$table}` (provider, event_id, type, created) VALUES (%s, %s, %s, %s)", $provider, $ev['event_id'], mb_substr( $ev['type'], 0, 80 ), current_time( 'mysql' ) ) ); // phpcs:ignore
		if ( ! $inserted ) {
			return new WP_REST_Response( array( 'received' => true, 'duplicate' => true ), 200 );
		}
		try {
			list( $payment_id, $result ) = call_user_func( $ev['handler'] );
		} catch ( Throwable $e ) {
			$wpdb->delete( $table, array( 'provider' => $provider, 'event_id' => $ev['event_id'] ) );
			return new WP_REST_Response( array( 'error' => 'handler failed' ), 500 );
		}
		$wpdb->update( $table, array( 'payment_id' => (int) $payment_id, 'result' => mb_substr( (string) $result, 0, 191 ) ), array( 'provider' => $provider, 'event_id' => $ev['event_id'] ) );
		return new WP_REST_Response( array( 'received' => true, 'result' => $result ), 200 );
	}

	/** Webhook log of a payment (detail page). */
	public static function for_payment( $payment_id ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM `' . Gruppa_Pay_DB::events() . '` WHERE payment_id = %d ORDER BY id DESC LIMIT 50', (int) $payment_id ) ); // phpcs:ignore
	}

	/** Last webhook received from a provider (settings page). */
	public static function last( $provider ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . Gruppa_Pay_DB::events() . '` WHERE provider = %s ORDER BY id DESC LIMIT 1', $provider ) ); // phpcs:ignore
	}
}
