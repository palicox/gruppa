<?php
defined( 'ABSPATH' ) || exit;

/**
 * PayPal — REST API directly (no SDK).
 *
 * One-off:      Orders v2 (intent CAPTURE) → approve page → captured on return (and by webhook).
 * Subscription: Catalog product + Billing plan (created once per amount / currency / period / title and
 *               cached) → Subscription → approve page. Every paid period arrives as PAYMENT.SALE.COMPLETED.
 * Webhooks are verified with POST /v1/notifications/verify-webhook-signature (needs the Webhook ID).
 *
 * Webhook events: CHECKOUT.ORDER.APPROVED, PAYMENT.CAPTURE.COMPLETED, PAYMENT.CAPTURE.DENIED,
 * PAYMENT.CAPTURE.REFUNDED, BILLING.SUBSCRIPTION.ACTIVATED, BILLING.SUBSCRIPTION.SUSPENDED,
 * BILLING.SUBSCRIPTION.CANCELLED, BILLING.SUBSCRIPTION.EXPIRED, BILLING.SUBSCRIPTION.PAYMENT.FAILED,
 * PAYMENT.SALE.COMPLETED.
 */
class Gruppa_Pay_PayPal extends Gruppa_Pay_Gateway {

	const PLANS = 'gruppa_payments_paypal_plans';

	public function id() {
		return 'paypal';
	}

	public function label() {
		return 'PayPal';
	}

	public function ready() {
		return '' !== $this->s( 'client' ) && '' !== $this->s( 'secret' );
	}

	public static function events() {
		return array( 'CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED', 'PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.REFUNDED', 'BILLING.SUBSCRIPTION.ACTIVATED', 'BILLING.SUBSCRIPTION.SUSPENDED', 'BILLING.SUBSCRIPTION.CANCELLED', 'BILLING.SUBSCRIPTION.EXPIRED', 'BILLING.SUBSCRIPTION.PAYMENT.FAILED', 'PAYMENT.SALE.COMPLETED' );
	}

	private function base() {
		return 'live' === Gruppa_Payments::mode() ? 'https://api-m.paypal.com/' : 'https://api-m.sandbox.paypal.com/';
	}

	private function token( $fresh = false ) {
		$key = 'gruppa_pay_pp_token_' . Gruppa_Payments::mode() . '_' . substr( md5( $this->s( 'client' ) ), 0, 8 );
		$tok = $fresh ? '' : (string) get_transient( $key );
		if ( $tok ) {
			return $tok;
		}
		$r = $this->http( 'POST', $this->base() . 'v1/oauth2/token', array(
			'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $this->s( 'client' ) . ':' . $this->s( 'secret' ) ) ), // phpcs:ignore
			'body'    => array( 'grant_type' => 'client_credentials' ),
		) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		set_transient( $key, $r['access_token'], max( 60, (int) $r['expires_in'] - 300 ) );
		return $r['access_token'];
	}

	/** @param array|string|null $body array → JSON, string = raw JSON. @return array|WP_Error */
	public function api( $method, $path, $body = null ) {
		if ( ! $this->ready() ) {
			return new WP_Error( 'paypal_off', 'PayPal is not set up.' );
		}
		$tok = $this->token();
		if ( is_wp_error( $tok ) ) {
			return $tok;
		}
		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $tok,
				'Content-Type'  => 'application/json',
				'Prefer'        => 'return=representation',
			),
		);
		if ( null !== $body ) {
			$args['body'] = is_string( $body ) ? $body : wp_json_encode( $body );
		}
		return $this->http( $method, $this->base() . ltrim( $path, '/' ), $args );
	}

	protected function error_message( $body, $code ) {
		if ( isset( $body['details'][0]['description'] ) ) {
			return 'PayPal: ' . $body['details'][0]['description'];
		}
		if ( isset( $body['message'] ) ) {
			return 'PayPal: ' . $body['message'];
		}
		if ( isset( $body['error_description'] ) ) {
			return 'PayPal: ' . $body['error_description'];
		}
		return 'PayPal HTTP ' . $code;
	}

	public function test() {
		$t = $this->token( true );
		return is_wp_error( $t ) ? $t : true;
	}

	private static function link( $res, $rel ) {
		foreach ( isset( $res['links'] ) ? (array) $res['links'] : array() as $l ) {
			if ( isset( $l['rel'] ) && $rel === $l['rel'] ) {
				return $l['href'];
			}
		}
		return '';
	}

	private function context( $urls ) {
		$s = Gruppa_Payments::settings();
		return array(
			'brand_name'          => mb_substr( '' !== $s['brand_name'] ? $s['brand_name'] : get_bloginfo( 'name' ), 0, 127 ),
			'locale'              => str_replace( '_', '-', get_locale() ),
			'shipping_preference' => 'NO_SHIPPING',
			'return_url'          => $urls['return'],
			'cancel_url'          => $urls['cancel'],
		);
	}

	public function start( $payment, $urls ) {
		$value = Gruppa_Payments::to_major( $payment->amount, $payment->currency );
		$ctx   = $this->context( $urls );

		if ( 'payment' === $payment->kind ) {
			$ctx['user_action'] = 'PAY_NOW';
			$body               = array(
				'intent'         => 'CAPTURE',
				'purchase_units' => array( array(
					'reference_id' => $payment->uid,
					'custom_id'    => $payment->uid,
					'description'  => mb_substr( $payment->title, 0, 127 ),
					'amount'       => array( 'currency_code' => $payment->currency, 'value' => $value ),
				) ),
				'payment_source' => array( 'paypal' => array( 'experience_context' => $ctx ) ),
			);
			if ( $payment->email ) {
				$body['payment_source']['paypal']['email_address'] = $payment->email;
			}
			$order = $this->api( 'POST', 'v2/checkout/orders', apply_filters( 'gruppa_payments/paypal_order', $body, $payment ) );
			if ( is_wp_error( $order ) ) {
				return $order;
			}
			Gruppa_Payments::update( $payment, array( 'checkout_ref' => $order['id'] ) );
			$url = self::link( $order, 'payer-action' );
			return $url ? $url : self::link( $order, 'approve' );
		}

		$plan = $this->plan( $payment );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$ctx['user_action'] = 'SUBSCRIBE_NOW';
		$body               = array(
			'plan_id'             => $plan,
			'custom_id'           => $payment->uid,
			'application_context' => $ctx,
		);
		if ( $payment->email ) {
			$body['subscriber'] = array( 'email_address' => $payment->email );
		}
		$sub = $this->api( 'POST', 'v1/billing/subscriptions', apply_filters( 'gruppa_payments/paypal_subscription', $body, $payment ) );
		if ( is_wp_error( $sub ) ) {
			return $sub;
		}
		Gruppa_Payments::update( $payment, array( 'checkout_ref' => $sub['id'], 'subscription_ref' => $sub['id'], 'meta' => array( 'paypal_plan' => $plan ) ) );
		return self::link( $sub, 'approve' );
	}

	/** Billing plan for amount / currency / period / title — created once per mode and cached. */
	private function plan( $payment ) {
		$mode  = Gruppa_Payments::mode();
		$cache = (array) get_option( self::PLANS, array() );
		$key   = md5( implode( '|', array( $mode, $payment->amount, $payment->currency, $payment->period, $payment->title ) ) );
		if ( ! empty( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}
		$pkey = 'product_' . $mode;
		if ( empty( $cache[ $pkey ] ) ) {
			$prod = $this->api( 'POST', 'v1/catalogs/products', array(
				'name' => mb_substr( get_bloginfo( 'name' ) . ' — Gruppa Payments', 0, 127 ),
				'type' => 'SERVICE',
			) );
			if ( is_wp_error( $prod ) ) {
				return $prod;
			}
			$cache[ $pkey ] = $prod['id'];
		}
		$plan = $this->api( 'POST', 'v1/billing/plans', array(
			'product_id'          => $cache[ $pkey ],
			'name'                => mb_substr( $payment->title . ' — ' . Gruppa_Payments::format( $payment->amount, $payment->currency ) . ' / ' . $payment->period, 0, 127 ),
			'status'              => 'ACTIVE',
			'billing_cycles'      => array( array(
				'frequency'      => array( 'interval_unit' => 'year' === $payment->period ? 'YEAR' : 'MONTH', 'interval_count' => 1 ),
				'tenure_type'    => 'REGULAR',
				'sequence'       => 1,
				'total_cycles'   => 0,
				'pricing_scheme' => array( 'fixed_price' => array( 'value' => Gruppa_Payments::to_major( $payment->amount, $payment->currency ), 'currency_code' => $payment->currency ) ),
			) ),
			'payment_preferences' => array( 'auto_bill_outstanding' => true, 'payment_failure_threshold' => 1 ),
		) );
		if ( is_wp_error( $plan ) ) {
			update_option( self::PLANS, $cache, false );
			return $plan;
		}
		$cache[ $key ] = $plan['id'];
		update_option( self::PLANS, $cache, false );
		return $plan['id'];
	}

	/** Return page: capture the order / read the subscription. */
	public function confirm( $payment, $query ) {
		if ( 'payment' === $payment->kind ) {
			$this->capture( $payment );
			return;
		}
		$sub = $this->api( 'GET', 'v1/billing/subscriptions/' . rawurlencode( $payment->subscription_ref ) );
		if ( ! is_wp_error( $sub ) ) {
			$this->subscription_state( $payment, $sub, true );
		}
	}

	private function capture( $payment ) {
		$res = $this->api( 'POST', 'v2/checkout/orders/' . rawurlencode( $payment->checkout_ref ) . '/capture', '{}' );
		if ( is_wp_error( $res ) ) {
			// Already captured (webhook was first) → read the order.
			$res = $this->api( 'GET', 'v2/checkout/orders/' . rawurlencode( $payment->checkout_ref ) );
			if ( is_wp_error( $res ) ) {
				return $payment;
			}
		}
		$cap = isset( $res['purchase_units'][0]['payments']['captures'][0] ) ? $res['purchase_units'][0]['payments']['captures'][0] : null;
		return $cap ? $this->capture_state( $payment, $cap, isset( $res['payer']['email_address'] ) ? $res['payer']['email_address'] : '', isset( $res['payer']['payer_id'] ) ? $res['payer']['payer_id'] : '' ) : $payment;
	}

	private function capture_state( $payment, $cap, $email = '', $payer = '' ) {
		$data = array( 'payment_ref' => (string) $cap['id'] );
		if ( $email && ! $payment->email ) {
			$data['email'] = sanitize_email( $email );
		}
		if ( $payer ) {
			$data['customer_ref'] = sanitize_text_field( $payer );
		}
		switch ( isset( $cap['status'] ) ? $cap['status'] : '' ) {
			case 'COMPLETED':
				return 'paid' === $payment->status ? Gruppa_Payments::update( $payment, $data ) : Gruppa_Payments::set_status( $payment, 'paid', $data );
			case 'DECLINED':
			case 'FAILED':
				return Gruppa_Payments::set_status( $payment, 'failed', $data );
			case 'REFUNDED':
				return Gruppa_Payments::set_status( $payment, 'refunded', $data );
		}
		return Gruppa_Payments::update( $payment, $data ); // PENDING: PAYMENT.CAPTURE.COMPLETED follows.
	}

	/** Subscription resource → status, paid_until; $first = record the first paid period (return page). */
	private function subscription_state( $payment, $sub, $first = false ) {
		$status = isset( $sub['status'] ) ? $sub['status'] : '';
		$data   = array();
		if ( ! empty( $sub['subscriber']['email_address'] ) && ! $payment->email ) {
			$data['email'] = sanitize_email( $sub['subscriber']['email_address'] );
		}
		if ( ! empty( $sub['subscriber']['payer_id'] ) ) {
			$data['customer_ref'] = $sub['subscriber']['payer_id'];
		}
		$next = ! empty( $sub['billing_info']['next_billing_time'] ) ? wp_date( 'Y-m-d', strtotime( $sub['billing_info']['next_billing_time'] ) ) : '';
		if ( $data ) {
			$payment = Gruppa_Payments::update( $payment, $data );
		}
		if ( 'ACTIVE' === $status ) {
			$last = isset( $sub['billing_info']['last_payment'] ) ? $sub['billing_info']['last_payment'] : null;
			if ( $first && $last && ! Gruppa_Payments::children( $payment->id ) ) {
				// First period; PAYMENT.SALE.COMPLETED later replaces the ref with the sale ID.
				return Gruppa_Payments::record_period( $payment, 'first:' . $payment->subscription_ref, Gruppa_Payments::to_minor( $last['amount']['value'], $last['amount']['currency_code'] ), $last['amount']['currency_code'], $next );
			}
			return Gruppa_Payments::set_status( $payment, 'active', $next ? array( 'paid_until' => $next ) : array() );
		}
		$map = array( 'SUSPENDED' => 'past_due', 'CANCELLED' => 'canceled', 'EXPIRED' => 'expired' );
		if ( isset( $map[ $status ] ) ) {
			return Gruppa_Payments::set_status( $payment, $map[ $status ] );
		}
		return $payment; // APPROVAL_PENDING / APPROVED: activation follows.
	}

	/* ---------- Webhook ---------- */

	public function webhook( WP_REST_Request $req ) {
		$payload = $req->get_body();
		$event   = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['event_type'] ) ) {
			return new WP_Error( 'payload', 'invalid payload', array( 'status' => 400 ) );
		}
		$hook_id = $this->s( 'webhook' );
		if ( '' === $hook_id ) {
			return new WP_Error( 'signature', 'PayPal Webhook ID is not set', array( 'status' => 400 ) );
		}
		// Raw event JSON is spliced in unchanged — re-encoding could break the signature check.
		$head = wp_json_encode( array(
			'auth_algo'         => (string) $req->get_header( 'paypal_auth_algo' ),
			'cert_url'          => (string) $req->get_header( 'paypal_cert_url' ),
			'transmission_id'   => (string) $req->get_header( 'paypal_transmission_id' ),
			'transmission_sig'  => (string) $req->get_header( 'paypal_transmission_sig' ),
			'transmission_time' => (string) $req->get_header( 'paypal_transmission_time' ),
			'webhook_id'        => $hook_id,
		) );
		$check = $this->api( 'POST', 'v1/notifications/verify-webhook-signature', substr( $head, 0, -1 ) . ',"webhook_event":' . $payload . '}' );
		if ( is_wp_error( $check ) || ! isset( $check['verification_status'] ) || 'SUCCESS' !== $check['verification_status'] ) {
			return new WP_Error( 'signature', 'invalid signature', array( 'status' => 400 ) );
		}
		return array(
			'event_id' => (string) $event['id'],
			'type'     => (string) $event['event_type'],
			'handler'  => function () use ( $event ) {
				return $this->handle_event( $event );
			},
		);
	}

	/** @return array [ payment_id, result ] */
	public function handle_event( $event ) {
		$r    = isset( $event['resource'] ) ? (array) $event['resource'] : array();
		$type = $event['event_type'];

		if ( 0 === strpos( $type, 'BILLING.SUBSCRIPTION.' ) ) {
			$p = ! empty( $r['custom_id'] ) ? Gruppa_Payments::get_by_uid( $r['custom_id'] ) : null;
			$p = $p ? $p : Gruppa_Payments::find( 'paypal', 'subscription_ref', isset( $r['id'] ) ? $r['id'] : '', 'subscription' );
			if ( ! $p ) {
				return array( 0, 'no payment' );
			}
			if ( 'BILLING.SUBSCRIPTION.PAYMENT.FAILED' === $type ) {
				Gruppa_Payments::set_status( $p, 'past_due' );
				return array( $p->id, 'past_due' );
			}
			$p = $this->subscription_state( $p, $r );
			return array( $p->id, $p->status );
		}

		if ( 'PAYMENT.SALE.COMPLETED' === $type ) {
			$sid = isset( $r['billing_agreement_id'] ) ? $r['billing_agreement_id'] : '';
			$p   = Gruppa_Payments::find( 'paypal', 'subscription_ref', $sid, 'subscription' );
			if ( ! $p && ! empty( $r['custom'] ) ) {
				$p = Gruppa_Payments::get_by_uid( $r['custom'] );
			}
			if ( ! $p ) {
				return array( 0, 'no payment' );
			}
			$cur   = isset( $r['amount']['currency'] ) ? $r['amount']['currency'] : $p->currency;
			$sub   = $this->api( 'GET', 'v1/billing/subscriptions/' . rawurlencode( $sid ) );
			$until = ! is_wp_error( $sub ) && ! empty( $sub['billing_info']['next_billing_time'] ) ? wp_date( 'Y-m-d', strtotime( $sub['billing_info']['next_billing_time'] ) ) : Gruppa_Payments::next_until( $p->period );
			$p     = Gruppa_Payments::record_period( $p, (string) $r['id'], Gruppa_Payments::to_minor( isset( $r['amount']['total'] ) ? $r['amount']['total'] : 0, $cur ), $cur, $until, array( 'claim_first' => true ) );
			return array( $p->id, 'period paid' );
		}

		if ( 'CHECKOUT.ORDER.APPROVED' === $type ) {
			$p = Gruppa_Payments::find( 'paypal', 'checkout_ref', isset( $r['id'] ) ? $r['id'] : '' );
			if ( ! $p ) {
				return array( 0, 'no payment' );
			}
			if ( 'pending' === $p->status ) {
				$p = $this->capture( $p );
			}
			return array( $p->id, $p->status );
		}

		if ( 0 === strpos( $type, 'PAYMENT.CAPTURE.' ) ) {
			$p = ! empty( $r['custom_id'] ) ? Gruppa_Payments::get_by_uid( $r['custom_id'] ) : null;
			if ( ! $p && 'PAYMENT.CAPTURE.REFUNDED' === $type ) {
				// Resource is the refund: its "up" link points to the capture.
				$up = self::link( $r, 'up' );
				$p  = $up ? Gruppa_Payments::find( 'paypal', 'payment_ref', basename( wp_parse_url( $up, PHP_URL_PATH ) ) ) : null;
			}
			if ( ! $p && isset( $r['id'] ) ) {
				$p = Gruppa_Payments::find( 'paypal', 'payment_ref', $r['id'] );
			}
			if ( ! $p && ! empty( $r['supplementary_data']['related_ids']['order_id'] ) ) {
				$p = Gruppa_Payments::find( 'paypal', 'checkout_ref', $r['supplementary_data']['related_ids']['order_id'] );
			}
			if ( ! $p ) {
				return array( 0, 'no payment' );
			}
			if ( 'PAYMENT.CAPTURE.REFUNDED' === $type ) {
				Gruppa_Payments::set_status( $p, 'refunded', array( 'meta' => array( 'refund_id' => isset( $r['id'] ) ? $r['id'] : '' ) ) );
				return array( $p->id, 'refunded' );
			}
			$p = $this->capture_state( $p, $r );
			return array( $p->id, $p->status );
		}
		return array( 0, 'ignored' );
	}

	public function cancel( $subscription ) {
		$r = $this->api( 'POST', 'v1/billing/subscriptions/' . rawurlencode( $subscription->subscription_ref ) . '/cancel', array( 'reason' => 'Canceled on ' . wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		return is_wp_error( $r ) ? $r : true;
	}

	public function dashboard_url( $payment ) {
		$base = 'test' === $payment->mode ? 'https://www.sandbox.paypal.com/' : 'https://www.paypal.com/';
		if ( 'subscription' === $payment->kind && $payment->subscription_ref ) {
			return $base . 'billing/subscriptions/' . rawurlencode( $payment->subscription_ref );
		}
		return $payment->payment_ref && 0 !== strpos( $payment->payment_ref, 'first:' ) ? $base . 'activity/payment/' . rawurlencode( $payment->payment_ref ) : '';
	}
}
