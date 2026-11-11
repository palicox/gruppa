<?php
defined( 'ABSPATH' ) || exit;

/**
 * Stripe — REST API directly (no SDK). Checkout Sessions for one-off payments and subscriptions,
 * Customer Portal, webhooks with Stripe-Signature (HMAC-SHA256, 5 min tolerance).
 *
 * Webhook events: checkout.session.completed, checkout.session.async_payment_succeeded,
 * checkout.session.async_payment_failed, checkout.session.expired, invoice.paid,
 * invoice.payment_failed, customer.subscription.updated, customer.subscription.deleted, charge.refunded.
 */
class Gruppa_Pay_Stripe extends Gruppa_Pay_Gateway {

	const API     = 'https://api.stripe.com/v1/';
	const VERSION = '2024-06-20';
	const META    = 'gruppa_payment';

	public function id() {
		return 'stripe';
	}

	public function label() {
		return 'Stripe';
	}

	public function ready() {
		return '' !== $this->s( 'secret' );
	}

	public static function events() {
		return array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed', 'checkout.session.expired', 'invoice.paid', 'invoice.payment_failed', 'customer.subscription.updated', 'customer.subscription.deleted', 'charge.refunded' );
	}

	/** @return array|WP_Error */
	public function api( $method, $path, $params = array() ) {
		$key = $this->s( 'secret' );
		if ( '' === $key ) {
			return new WP_Error( 'stripe_off', 'Stripe is not set up.' );
		}
		$url  = self::API . ltrim( $path, '/' );
		$args = array(
			'headers' => array(
				'Authorization'  => 'Bearer ' . $key,
				'Stripe-Version' => self::VERSION,
			),
		);
		if ( 'GET' === $method || 'DELETE' === $method ) {
			$url = $params ? $url . '?' . http_build_query( $params ) : $url;
		} else {
			$args['body'] = $params;
		}
		return $this->http( $method, $url, $args );
	}

	protected function error_message( $body, $code ) {
		return isset( $body['error']['message'] ) ? 'Stripe: ' . $body['error']['message'] : 'Stripe HTTP ' . $code;
	}

	public function test() {
		$r = $this->api( 'GET', 'balance' );
		return is_wp_error( $r ) ? $r : true;
	}

	/** Stripe customer of a WordPress user (stored per mode in user meta). */
	private function customer_for_user( $user_id, $email, $name = '' ) {
		$key = '_gruppa_stripe_customer_' . Gruppa_Payments::mode();
		$cus = (string) get_user_meta( $user_id, $key, true );
		if ( $cus ) {
			return $cus;
		}
		$user = get_userdata( $user_id );
		$r    = $this->api( 'POST', 'customers', array(
			'email'    => $email,
			'name'     => '' !== $name ? $name : ( $user ? $user->display_name : '' ),
			'metadata' => array( 'wp_user_id' => (int) $user_id ),
		) );
		if ( is_wp_error( $r ) || empty( $r['id'] ) ) {
			return is_wp_error( $r ) ? $r : new WP_Error( 'stripe_customer', 'Stripe did not return a customer ID.' );
		}
		update_user_meta( $user_id, $key, $r['id'] );
		return $r['id'];
	}

	/**
	 * Stripe customer of a visitor without an account: the existing customer with this email,
	 * otherwise a new one (name + email). So every payment has a customer in Stripe.
	 */
	private function customer_for_email( $email, $name = '' ) {
		$found = $this->api( 'GET', 'customers', array( 'email' => $email, 'limit' => 1 ) );
		if ( ! is_wp_error( $found ) && ! empty( $found['data'][0]['id'] ) ) {
			$id = $found['data'][0]['id'];
			if ( '' !== $name && empty( $found['data'][0]['name'] ) ) {
				$this->api( 'POST', 'customers/' . rawurlencode( $id ), array( 'name' => $name ) );
			}
			return $id;
		}
		$params = array( 'email' => $email, 'metadata' => array( 'site' => wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		if ( '' !== $name ) {
			$params['name'] = $name;
		}
		$r = $this->api( 'POST', 'customers', $params );
		if ( is_wp_error( $r ) || empty( $r['id'] ) ) {
			return is_wp_error( $r ) ? $r : new WP_Error( 'stripe_customer', 'Stripe did not return a customer ID.' );
		}
		return $r['id'];
	}

	public function start( $payment, $urls ) {
		$meta   = array( self::META => $payment->uid );
		$locale = substr( get_locale(), 0, 2 );
		$params = array(
			'mode'                => 'payment' === $payment->kind ? 'payment' : 'subscription',
			'client_reference_id' => $payment->uid,
			'metadata'            => $meta,
			'locale'              => in_array( $locale, array( 'sk', 'cs', 'en', 'de', 'hu', 'pl', 'fr', 'it', 'es' ), true ) ? $locale : 'auto',
			'success_url'         => $urls['return'] . '&session_id={CHECKOUT_SESSION_ID}',
			'cancel_url'          => $urls['cancel'],
			'allow_promotion_codes' => 'true',
		);

		// Price: Stripe Price ID, or our own amount (price_data).
		if ( $payment->price_id ) {
			$price = $this->api( 'GET', 'prices/' . rawurlencode( $payment->price_id ) );
			if ( is_wp_error( $price ) ) {
				return $price;
			}
			$recurring = ! empty( $price['recurring'] );
			if ( $recurring !== ( 'subscription' === $payment->kind ) ) {
				return new WP_Error( 'stripe_price', 'subscription' === $payment->kind ? 'The Stripe price is one-off — choose a recurring price for a subscription.' : 'The Stripe price is recurring — set Billing to Monthly or Yearly.' );
			}
			$params['line_items'] = array( array( 'price' => $payment->price_id, 'quantity' => 1 ) );
			$payment              = Gruppa_Payments::update( $payment, array(
				'amount'   => (int) ( isset( $price['unit_amount'] ) ? $price['unit_amount'] : 0 ),
				'currency' => strtoupper( (string) $price['currency'] ),
				'period'   => $recurring ? ( 'year' === $price['recurring']['interval'] ? 'year' : 'month' ) : 'once',
			) );
		} else {
			// Our own amount. The Stripe product is created once per name and reused, so the
			// Product catalog stays clean and nothing has to be set up in Stripe by hand.
			$pname   = ! empty( $payment->meta['product'] ) ? $payment->meta['product'] : $payment->title;
			$product = $this->product( $pname );
			if ( is_wp_error( $product ) ) {
				return $product;
			}
			$line = array(
				'currency'    => strtolower( $payment->currency ),
				'unit_amount' => (int) $payment->amount,
				'product'     => $product,
			);
			if ( 'subscription' === $payment->kind ) {
				$line['recurring'] = array( 'interval' => 'year' === $payment->period ? 'year' : 'month' );
			}
			$params['line_items'] = array( array( 'price_data' => $line, 'quantity' => 1 ) );
		}

		// Every payment gets a Stripe customer (Customers in the Stripe dashboard).
		$name = isset( $payment->meta['name'] ) ? (string) $payment->meta['name'] : '';
		$cus  = '';
		if ( $payment->user_id && $payment->email ) {
			$cus = $this->customer_for_user( $payment->user_id, $payment->email, $name );
		} elseif ( $payment->email ) {
			$cus = $this->customer_for_email( $payment->email, $name );
		}
		if ( is_wp_error( $cus ) ) {
			return $cus;
		}
		if ( $cus ) {
			$params['customer']        = $cus;
			$params['customer_update'] = array( 'name' => 'auto', 'address' => 'auto' );
			Gruppa_Payments::update( $payment, array( 'customer_ref' => $cus ) );
		} elseif ( 'payment' === $payment->kind ) {
			$params['customer_creation'] = 'always'; // Email asked on the payment page; subscriptions create one anyway.
		}
		if ( 'payment' === $payment->kind ) {
			$params['payment_intent_data'] = array( 'metadata' => $meta, 'description' => $payment->title );
		} else {
			$params['subscription_data'] = array( 'metadata' => $meta, 'description' => $payment->title );
		}
		// Company / VAT ID on the invoice: add 'tax_id_collection' => [ 'enabled' => 'true' ] in this filter.
		$params  = apply_filters( 'gruppa_payments/stripe_checkout_params', $params, $payment );
		$session = $this->api( 'POST', 'checkout/sessions', $params );
		if ( is_wp_error( $session ) && ! $payment->price_id && false !== stripos( $session->get_error_message(), 'product' ) ) {
			// The cached product was deleted in Stripe: create it again and retry once.
			$pname = ! empty( $payment->meta['product'] ) ? $payment->meta['product'] : $payment->title;
			$this->forget_product( $pname );
			$product = $this->product( $pname );
			if ( is_wp_error( $product ) ) {
				return $product;
			}
			$params['line_items'][0]['price_data']['product'] = $product;
			$session = $this->api( 'POST', 'checkout/sessions', $params );
		}
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		Gruppa_Payments::update( $payment, array( 'checkout_ref' => $session['id'] ) );
		return $session['url'];
	}

	/* ---------- Products (created automatically) ---------- */

	const PRODUCTS = 'gruppa_payments_stripe_products';

	private function product_key( $name ) {
		return Gruppa_Payments::mode() . ':' . md5( mb_strtolower( trim( $name ) ) );
	}

	/** Stripe product ID for a name — created on first use, cached per mode. @return string|WP_Error */
	public function product( $name ) {
		$name  = '' !== trim( $name ) ? mb_substr( trim( $name ), 0, 250 ) : get_bloginfo( 'name' );
		$cache = (array) get_option( self::PRODUCTS, array() );
		$key   = $this->product_key( $name );
		if ( ! empty( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}
		$r = $this->api( 'POST', 'products', array(
			'name'     => $name,
			'metadata' => array( 'gruppa_payments' => '1', 'site' => wp_parse_url( home_url(), PHP_URL_HOST ) ),
		) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( empty( $r['id'] ) ) {
			return new WP_Error( 'stripe_product', 'Stripe did not return a product ID.' );
		}
		$cache[ $key ] = $r['id'];
		update_option( self::PRODUCTS, $cache, false );
		return $r['id'];
	}

	private function forget_product( $name ) {
		$cache = (array) get_option( self::PRODUCTS, array() );
		unset( $cache[ $this->product_key( '' !== trim( $name ) ? mb_substr( trim( $name ), 0, 250 ) : get_bloginfo( 'name' ) ) ] );
		update_option( self::PRODUCTS, $cache, false );
	}

	/** Return page: read the session (webhooks may be late or missing). */
	public function confirm( $payment, $query ) {
		$sid = $payment->checkout_ref ? $payment->checkout_ref : ( isset( $query['session_id'] ) ? sanitize_text_field( $query['session_id'] ) : '' );
		if ( '' === $sid ) {
			return;
		}
		$session = $this->api( 'GET', 'checkout/sessions/' . rawurlencode( $sid ), array( 'expand' => array( 'subscription.latest_invoice' ) ) );
		if ( ! is_wp_error( $session ) ) {
			$this->session_completed( $payment, $session );
		}
	}

	/** checkout.session.completed / async_payment_succeeded (also from the return page). */
	private function session_completed( $payment, $session ) {
		$data = array();
		if ( ! empty( $session['customer'] ) ) {
			$data['customer_ref'] = is_array( $session['customer'] ) ? $session['customer']['id'] : $session['customer'];
			if ( $payment->user_id ) {
				update_user_meta( $payment->user_id, '_gruppa_stripe_customer_' . $payment->mode, $data['customer_ref'] );
			}
		}
		if ( ! $payment->email && ! empty( $session['customer_details']['email'] ) ) {
			$data['email'] = sanitize_email( $session['customer_details']['email'] );
		}
		if ( 'payment' === $payment->kind ) {
			if ( isset( $session['payment_status'] ) && 'paid' === $session['payment_status'] ) {
				$data['payment_ref'] = is_array( $session['payment_intent'] ) ? $session['payment_intent']['id'] : (string) $session['payment_intent'];
				if ( isset( $session['amount_total'] ) ) {
					$data['amount'] = (int) $session['amount_total'];
				}
				return 'paid' === $payment->status ? Gruppa_Payments::update( $payment, $data ) : Gruppa_Payments::set_status( $payment, 'paid', $data );
			}
			return $data ? Gruppa_Payments::update( $payment, $data ) : $payment; // Bank transfer etc.: async_payment_succeeded follows.
		}
		// Subscription.
		$sub = isset( $session['subscription'] ) ? $session['subscription'] : '';
		if ( is_array( $sub ) ) {
			$data['subscription_ref'] = $sub['id'];
			$payment                  = Gruppa_Payments::update( $payment, $data );
			$inv                      = isset( $sub['latest_invoice'] ) && is_array( $sub['latest_invoice'] ) ? $sub['latest_invoice'] : null;
			if ( $inv && 'paid' === $inv['status'] ) {
				return $this->invoice_paid( $payment, $inv );
			}
			return $payment;
		}
		if ( $sub ) {
			$data['subscription_ref'] = $sub;
		}
		$payment = Gruppa_Payments::update( $payment, $data );
		if ( isset( $session['payment_status'] ) && 'paid' === $session['payment_status'] && 'pending' === $payment->status ) {
			$payment = Gruppa_Payments::set_status( $payment, 'active' ); // invoice.paid adds the paid period.
		}
		return $payment;
	}

	private function invoice_paid( $parent, $inv ) {
		$end = 0;
		foreach ( isset( $inv['lines']['data'] ) ? (array) $inv['lines']['data'] : array() as $line ) {
			$end = max( $end, (int) ( isset( $line['period']['end'] ) ? $line['period']['end'] : 0 ) );
		}
		$until = $end ? wp_date( 'Y-m-d', $end ) : Gruppa_Payments::next_until( $parent->period );
		$extra = array();
		if ( ! empty( $inv['hosted_invoice_url'] ) ) {
			$extra['invoice_url'] = esc_url_raw( $inv['hosted_invoice_url'] );
		}
		if ( ! empty( $inv['number'] ) ) {
			$extra['invoice_number'] = sanitize_text_field( $inv['number'] );
		}
		return Gruppa_Payments::record_period( $parent, (string) $inv['id'], (int) $inv['amount_paid'], (string) $inv['currency'], $until, $extra );
	}

	/* ---------- Webhook ---------- */

	/** Stripe-Signature: t=…,v1=… — HMAC-SHA256 of "t.payload". */
	public static function verify( $payload, $header, $secret, $tolerance = 300 ) {
		if ( '' === (string) $secret || '' === (string) $header ) {
			return false;
		}
		$t  = 0;
		$v1 = array();
		foreach ( explode( ',', (string) $header ) as $part ) {
			$kv = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $kv ) ) {
				continue;
			}
			if ( 't' === $kv[0] ) {
				$t = (int) $kv[1];
			} elseif ( 'v1' === $kv[0] ) {
				$v1[] = $kv[1];
			}
		}
		if ( ! $t || ! $v1 || abs( time() - $t ) > $tolerance ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $t . '.' . $payload, $secret );
		foreach ( $v1 as $sig ) {
			if ( hash_equals( $expected, $sig ) ) {
				return true;
			}
		}
		return false;
	}

	/** Our payment of a Stripe object: metadata uid, then subscription / session / payment intent ID. */
	private function find_payment( $obj ) {
		foreach ( array(
			array( 'metadata', self::META ),
			array( 'subscription_details', 'metadata', self::META ),
			array( 'parent', 'subscription_details', 'metadata', self::META ),
			array( 'client_reference_id' ),
		) as $path ) {
			$v = $obj;
			foreach ( $path as $k ) {
				$v = is_array( $v ) && isset( $v[ $k ] ) ? $v[ $k ] : null;
			}
			if ( is_string( $v ) && ( $p = Gruppa_Payments::get_by_uid( $v ) ) ) { // phpcs:ignore
				return $p;
			}
		}
		$object = isset( $obj['object'] ) ? $obj['object'] : '';
		$sub    = '';
		if ( 'subscription' === $object ) {
			$sub = $obj['id'];
		} elseif ( ! empty( $obj['subscription'] ) ) {
			$sub = is_array( $obj['subscription'] ) ? $obj['subscription']['id'] : $obj['subscription'];
		} elseif ( ! empty( $obj['parent']['subscription_details']['subscription'] ) ) {
			$sub = $obj['parent']['subscription_details']['subscription'];
		}
		if ( $sub && ( $p = Gruppa_Payments::find( 'stripe', 'subscription_ref', $sub, 'subscription' ) ) ) { // phpcs:ignore
			return $p;
		}
		if ( 'checkout.session' === $object && ( $p = Gruppa_Payments::find( 'stripe', 'checkout_ref', $obj['id'] ) ) ) { // phpcs:ignore
			return $p;
		}
		if ( ! empty( $obj['payment_intent'] ) ) {
			$pi = is_array( $obj['payment_intent'] ) ? $obj['payment_intent']['id'] : $obj['payment_intent'];
			return Gruppa_Payments::find( 'stripe', 'payment_ref', $pi );
		}
		return null;
	}

	public function webhook( WP_REST_Request $req ) {
		$payload = $req->get_body();
		if ( ! self::verify( $payload, $req->get_header( 'stripe_signature' ), $this->s( 'webhook' ) ) ) {
			return new WP_Error( 'signature', 'invalid signature', array( 'status' => 400 ) );
		}
		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['type'] ) ) {
			return new WP_Error( 'payload', 'invalid payload', array( 'status' => 400 ) );
		}
		return array(
			'event_id' => (string) $event['id'],
			'type'     => (string) $event['type'],
			'handler'  => function () use ( $event ) {
				return $this->handle_event( $event );
			},
		);
	}

	/** @return array [ payment_id, result ] */
	public function handle_event( $event ) {
		$obj     = isset( $event['data']['object'] ) ? $event['data']['object'] : array();
		$payment = $this->find_payment( $obj );
		if ( ! $payment ) {
			return array( 0, 'no payment' );
		}
		switch ( $event['type'] ) {
			case 'checkout.session.completed':
			case 'checkout.session.async_payment_succeeded':
				$payment = $this->session_completed( $payment, $obj );
				return array( $payment->id, $payment->status );

			case 'checkout.session.async_payment_failed':
				Gruppa_Payments::set_status( $payment, 'failed' );
				return array( $payment->id, 'failed' );

			case 'checkout.session.expired':
				if ( 'pending' === $payment->status ) {
					Gruppa_Payments::set_status( $payment, 'expired' );
				}
				return array( $payment->id, 'expired' );

			case 'invoice.paid':
			case 'invoice.payment_succeeded':
				if ( 'subscription' !== $payment->kind ) {
					return array( $payment->id, 'ignored' );
				}
				if ( ! $payment->subscription_ref ) {
					$sub     = ! empty( $obj['subscription'] ) ? $obj['subscription'] : ( isset( $obj['parent']['subscription_details']['subscription'] ) ? $obj['parent']['subscription_details']['subscription'] : '' );
					$payment = Gruppa_Payments::update( $payment, array( 'subscription_ref' => is_array( $sub ) ? $sub['id'] : (string) $sub ) );
				}
				$payment = $this->invoice_paid( $payment, $obj );
				return array( $payment->id, 'period paid' );

			case 'invoice.payment_failed':
				if ( 'subscription' === $payment->kind && in_array( $payment->status, array( 'active', 'pending' ), true ) ) {
					Gruppa_Payments::set_status( $payment, 'past_due' );
				}
				return array( $payment->id, 'past_due' );

			case 'customer.subscription.updated':
				$map = array( 'active' => 'active', 'trialing' => 'active', 'past_due' => 'past_due', 'unpaid' => 'past_due', 'canceled' => 'canceled', 'incomplete_expired' => 'expired' );
				$st  = isset( $obj['status'], $map[ $obj['status'] ] ) ? $map[ $obj['status'] ] : '';
				$m   = array( 'cancel_at_period_end' => ! empty( $obj['cancel_at_period_end'] ) );
				if ( $st && $st !== $payment->status && 'pending' !== $payment->status ) {
					Gruppa_Payments::set_status( $payment, $st, array( 'meta' => $m ) );
				} else {
					Gruppa_Payments::update( $payment, array( 'meta' => $m ) );
				}
				return array( $payment->id, $st ? $st : 'updated' );

			case 'customer.subscription.deleted':
				Gruppa_Payments::set_status( $payment, 'canceled' );
				return array( $payment->id, 'canceled' );

			case 'charge.refunded':
				$full = (int) $obj['amount_refunded'] >= (int) $obj['amount'];
				$data = array( 'meta' => array( 'refunded' => (int) $obj['amount_refunded'] ) );
				if ( $full ) {
					Gruppa_Payments::set_status( $payment, 'refunded', $data );
				} else {
					Gruppa_Payments::update( $payment, $data );
				}
				return array( $payment->id, $full ? 'refunded' : 'partial refund' );
		}
		return array( $payment->id, 'ignored' );
	}

	public function cancel( $subscription ) {
		$r = $this->api( 'DELETE', 'subscriptions/' . rawurlencode( $subscription->subscription_ref ) );
		return is_wp_error( $r ) ? $r : true;
	}

	/** Stripe Customer Portal (card, invoices, cancel). @return string|WP_Error */
	public function portal_url( $customer, $return_url ) {
		$s = $this->api( 'POST', 'billing_portal/sessions', array( 'customer' => $customer, 'return_url' => $return_url ) );
		return is_wp_error( $s ) ? $s : $s['url'];
	}

	public function dashboard_url( $payment ) {
		$base = 'https://dashboard.stripe.com/' . ( 'test' === $payment->mode ? 'test/' : '' );
		if ( $payment->subscription_ref && 'subscription' === $payment->kind ) {
			return $base . 'subscriptions/' . rawurlencode( $payment->subscription_ref );
		}
		if ( 0 === strpos( $payment->payment_ref, 'in_' ) ) {
			return $base . 'invoices/' . rawurlencode( $payment->payment_ref );
		}
		if ( $payment->payment_ref ) {
			return $base . 'payments/' . rawurlencode( $payment->payment_ref );
		}
		return '';
	}
}
