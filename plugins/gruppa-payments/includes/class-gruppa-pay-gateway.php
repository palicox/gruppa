<?php
defined( 'ABSPATH' ) || exit;

/**
 * Payment gateway. Gateways are registered with the filter gruppa_payments/gateways
 * ( [ 'stripe' => 'Gruppa_Pay_Stripe', … ] ) and work with rows of wp_gcms_payments.
 */
abstract class Gruppa_Pay_Gateway {

	/** @return string stripe | paypal */
	abstract public function id();

	/** @return string Display name. */
	abstract public function label();

	/** Keys for the current mode are filled in. */
	abstract public function ready();

	/**
	 * Start the checkout for a pending payment row.
	 *
	 * @param object $payment Row of wp_gcms_payments.
	 * @param array  $urls    [ 'return' => …, 'cancel' => … ] — our return endpoint.
	 * @return string|WP_Error URL of the hosted payment page.
	 */
	abstract public function start( $payment, $urls );

	/**
	 * Visitor came back from the payment page — confirm the result with the provider
	 * (webhooks may be late or not configured).
	 *
	 * @param object $payment
	 * @param array  $query   $_GET of the return request.
	 */
	abstract public function confirm( $payment, $query );

	/**
	 * Webhook request: verify and handle.
	 *
	 * @return array [ 'event_id', 'type', 'payment_id', 'result' ] or WP_Error (signature)
	 */
	abstract public function webhook( WP_REST_Request $req );

	/** Cancel a subscription at the provider. @return true|WP_Error */
	abstract public function cancel( $subscription );

	/** Test the API keys. @return true|WP_Error */
	abstract public function test();

	/** Link to the payment in the provider's dashboard ('' = none). */
	public function dashboard_url( $payment ) {
		return '';
	}

	/** Settings of the current mode. */
	protected function s( $key ) {
		$s    = Gruppa_Payments::settings();
		$mode = $s['mode'];
		$k    = $this->id() . '_' . $key . '_' . $mode;
		return isset( $s[ $k ] ) ? (string) $s[ $k ] : '';
	}

	/** JSON / form HTTP call. @return array|WP_Error decoded body */
	protected function http( $method, $url, $args ) {
		$args = wp_parse_args( $args, array( 'method' => $method, 'timeout' => 25 ) );
		$res  = wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = (string) wp_remote_retrieve_body( $res );
		$body = '' === $raw ? array() : json_decode( $raw, true );
		if ( $code >= 400 || ! is_array( $body ) ) {
			return new WP_Error( $this->id(), $this->error_message( is_array( $body ) ? $body : array(), $code ), array( 'status' => $code ) );
		}
		return $body;
	}

	protected function error_message( $body, $code ) {
		return $this->label() . ' HTTP ' . $code;
	}
}
