<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gruppa Payments — core: settings, payment rows, statuses, checkout, return page, daily cron.
 *
 * PHP API (other plugins, e.g. BlueWeb.one later):
 *
 *     $r = Gruppa_Payments::checkout( array(
 *         'provider'    => 'stripe',            // stripe | paypal | '' = default
 *         'amount'      => 19.90,               // in currency units — or 'price_id' => 'price_…' (Stripe)
 *         'currency'    => 'EUR',
 *         'period'      => 'month',             // once | month | year
 *         'title'       => 'Business Web',
 *         'email'       => 'client@example.com',
 *         'object_type' => 'gruppa:webapps',    // gruppa:{slug} | jet:{cct} | post | user
 *         'object_id'   => 12,
 *         'success_url' => home_url( '/ok/' ),
 *         'sync'        => array( 'field' => 'payment_status', 'paid' => 'paid', 'unpaid' => 'blocked', 'until' => 'paid_until', 'publish' => true ),
 *     ) );
 *     if ( ! is_wp_error( $r ) ) { wp_redirect( $r['url'] ); exit; }
 *
 *     Gruppa_Payments::for_object( 'gruppa:webapps', 12 );   // rows, newest first
 *     Gruppa_Payments::is_paid( 'gruppa:webapps', 12 );      // paid one-off or active subscription
 *
 * Hooks: gruppa_payments/status_changed ( $payment, $old, $new ), gruppa_payments/paid ( $payment ),
 *        gruppa_payments/updated ( $payment ), filter gruppa_payments/checkout_args.
 */
class Gruppa_Payments {

	const OPTION = 'gruppa_payments_settings';
	const CRON   = 'gruppa_payments_daily';

	/** Statuses per kind. */
	const PAYMENT_STATUSES      = array( 'pending', 'paid', 'failed', 'canceled', 'refunded', 'expired' );
	const SUBSCRIPTION_STATUSES = array( 'pending', 'active', 'past_due', 'canceled', 'expired' );

	/** Currencies without minor units. */
	const ZERO_DECIMAL = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

	private static $gateways = null;

	public static function init() {
		add_action( 'plugins_loaded', array( 'Gruppa_Pay_DB', 'maybe_install' ), 20 );
		add_action( 'init', array( __CLASS__, 'load_textdomain' ), 1 );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_return' ), 1 );
		add_action( self::CRON, array( __CLASS__, 'daily' ) );
	}

	public static function load_textdomain() {
		load_plugin_textdomain( 'gruppa-payments', false, dirname( plugin_basename( GRUPPA_PAY_FILE ) ) . '/languages' );
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( strtotime( 'tomorrow 03:20' ), 'daily', self::CRON );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/* =========================================================
	 * Settings
	 * ======================================================= */

	public static function defaults() {
		return array(
			'mode'                => 'test',
			'currency'            => 'EUR',
			'default_provider'    => 'stripe',
			'success_page'        => 0,
			'cancel_page'         => 0,
			'brand_name'          => '',
			'grace_days'          => 7,
			'pending_hours'       => 24,
			'stripe_secret_test'  => '',
			'stripe_secret_live'  => '',
			'stripe_webhook_test' => '',
			'stripe_webhook_live' => '',
			'paypal_client_test'  => '',
			'paypal_secret_test'  => '',
			'paypal_webhook_test' => '',
			'paypal_client_live'  => '',
			'paypal_secret_live'  => '',
			'paypal_webhook_live' => '',
		);
	}

	/** Keys that are never printed back in the admin. */
	public static function secret_keys() {
		return array( 'stripe_secret_test', 'stripe_secret_live', 'stripe_webhook_test', 'stripe_webhook_live', 'paypal_secret_test', 'paypal_secret_live' );
	}

	public static function settings() {
		$s = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $s ) ? $s : array(), self::defaults() );
	}

	public static function mode() {
		return 'live' === self::settings()['mode'] ? 'live' : 'test';
	}

	public static function currencies() {
		return (array) apply_filters( 'gruppa_payments/currencies', array( 'EUR', 'CZK', 'USD', 'GBP', 'PLN', 'HUF', 'CHF' ) );
	}

	/* =========================================================
	 * Gateways
	 * ======================================================= */

	/** @return Gruppa_Pay_Gateway[] id => gateway */
	public static function gateways() {
		if ( null === self::$gateways ) {
			self::$gateways = array();
			$classes        = (array) apply_filters( 'gruppa_payments/gateways', array( 'stripe' => 'Gruppa_Pay_Stripe', 'paypal' => 'Gruppa_Pay_PayPal' ) );
			foreach ( $classes as $id => $class ) {
				if ( class_exists( $class ) ) {
					self::$gateways[ $id ] = new $class();
				}
			}
		}
		return self::$gateways;
	}

	/** @return Gruppa_Pay_Gateway|null */
	public static function gateway( $id ) {
		$g = self::gateways();
		return isset( $g[ $id ] ) ? $g[ $id ] : null;
	}

	/** Gateways with keys for the current mode. */
	public static function ready_gateways() {
		return array_filter( self::gateways(), function ( $g ) {
			return $g->ready();
		} );
	}

	public static function enabled() {
		return (bool) self::ready_gateways();
	}

	/* =========================================================
	 * Money
	 * ======================================================= */

	public static function decimals( $currency ) {
		return in_array( strtoupper( $currency ), self::ZERO_DECIMAL, true ) ? 0 : 2;
	}

	/** 19.9 / "19,90" → 1990 */
	public static function to_minor( $amount, $currency ) {
		$amount = (float) str_replace( array( ' ', ',' ), array( '', '.' ), (string) $amount );
		return (int) round( $amount * pow( 10, self::decimals( $currency ) ) );
	}

	/** 1990 → "19.90" (API format, dot, no thousands separator). */
	public static function to_major( $minor, $currency ) {
		$d = self::decimals( $currency );
		return number_format( (int) $minor / pow( 10, $d ), $d, '.', '' );
	}

	/** 1990 → "19,90 EUR" in the site language. */
	public static function format( $minor, $currency ) {
		$d = self::decimals( $currency );
		return number_format_i18n( (int) $minor / pow( 10, $d ), $d ) . ' ' . strtoupper( $currency );
	}

	/* =========================================================
	 * Rows
	 * ======================================================= */

	/** Row with decoded meta (array). */
	private static function prepare( $row ) {
		if ( ! $row ) {
			return null;
		}
		$m         = json_decode( (string) $row->meta, true );
		$row->meta = is_array( $m ) ? $m : array();
		foreach ( array( 'id', 'parent_id', 'amount', 'user_id', 'object_id', 'form_id' ) as $k ) {
			$row->$k = (int) $row->$k;
		}
		return $row;
	}

	public static function get( $id ) {
		global $wpdb;
		return self::prepare( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . Gruppa_Pay_DB::payments() . '` WHERE id = %d', (int) $id ) ) ); // phpcs:ignore
	}

	public static function get_by_uid( $uid ) {
		global $wpdb;
		$uid = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $uid ) );
		if ( 32 !== strlen( $uid ) ) {
			return null;
		}
		return self::prepare( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . Gruppa_Pay_DB::payments() . '` WHERE uid = %s', $uid ) ) ); // phpcs:ignore
	}

	/** First row where $column = $value (provider-scoped). */
	public static function find( $provider, $column, $value, $kind = '' ) {
		global $wpdb;
		if ( '' === (string) $value || ! in_array( $column, array( 'checkout_ref', 'payment_ref', 'subscription_ref', 'customer_ref' ), true ) ) {
			return null;
		}
		$sql = 'SELECT * FROM `' . Gruppa_Pay_DB::payments() . "` WHERE provider = %s AND `{$column}` = %s";
		$arg = array( $provider, (string) $value );
		if ( $kind ) {
			$sql  .= ' AND kind = %s';
			$arg[] = $kind;
		}
		return self::prepare( $wpdb->get_row( $wpdb->prepare( $sql . ' ORDER BY id ASC LIMIT 1', $arg ) ) ); // phpcs:ignore
	}

	/** Payments and subscriptions of an object, newest first. */
	public static function for_object( $object_type, $object_id, $kind = '' ) {
		global $wpdb;
		$sql = 'SELECT * FROM `' . Gruppa_Pay_DB::payments() . '` WHERE object_type = %s AND object_id = %d';
		$arg = array( (string) $object_type, (int) $object_id );
		if ( $kind ) {
			$sql  .= ' AND kind = %s';
			$arg[] = $kind;
		}
		return array_map( array( __CLASS__, 'prepare' ), (array) $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY id DESC', $arg ) ) ); // phpcs:ignore
	}

	/** Paid one-off payment or active / past-due subscription within its paid period. */
	public static function is_paid( $object_type, $object_id ) {
		foreach ( self::for_object( $object_type, $object_id ) as $p ) {
			if ( $p->parent_id ) {
				continue;
			}
			if ( 'payment' === $p->kind && 'paid' === $p->status ) {
				return true;
			}
			if ( 'subscription' === $p->kind && in_array( $p->status, array( 'active', 'past_due' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	public static function children( $parent_id ) {
		global $wpdb;
		return array_map( array( __CLASS__, 'prepare' ), (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM `' . Gruppa_Pay_DB::payments() . '` WHERE parent_id = %d ORDER BY id DESC', (int) $parent_id ) ) ); // phpcs:ignore
	}

	public static function insert( $row ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$row = wp_parse_args( $row, array(
			'uid'      => md5( wp_generate_password( 32, true, true ) . microtime() ),
			'mode'     => self::mode(),
			'kind'     => 'payment',
			'status'   => 'pending',
			'currency' => self::settings()['currency'],
			'period'   => 'once',
			'created'  => $now,
			'modified' => $now,
			'meta'     => array(),
		) );
		$row['meta'] = wp_json_encode( (array) $row['meta'] );
		if ( false === $wpdb->insert( Gruppa_Pay_DB::payments(), $row ) ) {
			return new WP_Error( 'gruppa_pay_db', $wpdb->last_error ? $wpdb->last_error : 'Database error.' );
		}
		return self::get( (int) $wpdb->insert_id );
	}

	/** Update columns; 'meta' is merged into the stored meta. */
	public static function update( $payment, $data ) {
		global $wpdb;
		if ( isset( $data['meta'] ) ) {
			$data['meta'] = wp_json_encode( array_merge( $payment->meta, (array) $data['meta'] ) );
		}
		$data['modified'] = current_time( 'mysql' );
		$wpdb->update( Gruppa_Pay_DB::payments(), $data, array( 'id' => (int) $payment->id ) );
		return self::get( $payment->id );
	}

	/**
	 * Change the status (and other columns). Runs hooks and the object sync.
	 *
	 * @return object updated row
	 */
	public static function set_status( $payment, $status, $data = array() ) {
		$old = $payment->status;
		if ( in_array( $status, array( 'paid', 'active' ), true ) && ! $payment->paid_at && empty( $data['paid_at'] ) ) {
			$data['paid_at'] = current_time( 'mysql' );
		}
		$data['status'] = $status;
		$payment        = self::update( $payment, $data );
		if ( $old !== $status ) {
			do_action( 'gruppa_payments/status_changed', $payment, $old, $status );
			if ( in_array( $status, array( 'paid', 'active' ), true ) && ! in_array( $old, array( 'paid', 'active' ), true ) ) {
				do_action( 'gruppa_payments/paid', $payment );
			}
		}
		do_action( 'gruppa_payments/updated', $payment );
		if ( ! $payment->parent_id ) {
			Gruppa_Pay_Objects::sync( $payment, $old );
		}
		return $payment;
	}

	/**
	 * One paid period of a subscription → child row (kind payment) + parent active with paid_until.
	 * $ref is unique per provider (Stripe invoice ID, PayPal sale ID) — the same period is recorded once.
	 */
	public static function record_period( $parent, $ref, $amount, $currency, $paid_until = '', $extra = array() ) {
		$child = self::find( $parent->provider, 'payment_ref', $ref, 'payment' );
		if ( ! $child && ! empty( $extra['claim_first'] ) ) {
			// PayPal: the period stored on return (without sale ID) gets the ID from the webhook.
			foreach ( self::children( $parent->id ) as $c ) {
				if ( '' === $c->payment_ref || 0 === strpos( $c->payment_ref, 'first:' ) ) {
					$child = self::update( $c, array( 'payment_ref' => $ref ) );
					break;
				}
			}
		}
		unset( $extra['claim_first'] );
		if ( ! $child ) {
			$child = self::insert( array(
				'parent_id'        => $parent->id,
				'provider'         => $parent->provider,
				'mode'             => $parent->mode,
				'kind'             => 'payment',
				'status'           => 'paid',
				'title'            => $parent->title,
				'amount'           => (int) $amount ? (int) $amount : $parent->amount,
				'currency'         => $currency ? strtoupper( $currency ) : $parent->currency,
				'period'           => $parent->period,
				'user_id'          => $parent->user_id,
				'email'            => $parent->email,
				'object_type'      => $parent->object_type,
				'object_id'        => $parent->object_id,
				'form_id'          => $parent->form_id,
				'customer_ref'     => $parent->customer_ref,
				'payment_ref'      => $ref,
				'subscription_ref' => $parent->subscription_ref,
				'paid_at'          => current_time( 'mysql' ),
				'meta'             => $extra,
			) );
			if ( ! is_wp_error( $child ) ) {
				do_action( 'gruppa_payments/paid', $child );
			}
		}
		$data = array();
		if ( $paid_until ) {
			$data['paid_until'] = $paid_until;
		}
		return self::set_status( $parent, 'active', $data );
	}

	/** paid_until for a period started now (fallback when the provider does not say). */
	public static function next_until( $period, $from = 0 ) {
		$from = $from ? $from : time();
		return wp_date( 'Y-m-d', strtotime( 'year' === $period ? '+1 year' : '+1 month', $from ) );
	}

	/* =========================================================
	 * Checkout
	 * ======================================================= */

	/**
	 * @return array|WP_Error [ 'url' => hosted payment page, 'payment' => row ]
	 */
	public static function checkout( $args ) {
		$s = self::settings();
		$d = wp_parse_args( (array) apply_filters( 'gruppa_payments/checkout_args', $args ), array(
			'provider'    => '',
			'amount'      => 0,
			'price_id'    => '',
			'currency'    => $s['currency'],
			'period'      => 'once',
			'title'       => '',
			'product'     => '',   // Stripe product name; '' = title. Keep it fixed (no item data) so one product is reused.
			'email'       => '',
			'name'        => '',   // Customer name for Stripe (e.g. from a form field).
			'user_id'     => get_current_user_id(),
			'object_type' => '',
			'object_id'   => 0,
			'form_id'     => 0,
			'success_url' => '',
			'cancel_url'  => '',
			'sync'        => array(),
			'meta'        => array(),
		) );

		$ready = self::ready_gateways();
		if ( ! $ready ) {
			return new WP_Error( 'gruppa_pay_off', __( 'Online payments are not available right now.', 'gruppa-payments' ) );
		}
		$provider = sanitize_key( $d['provider'] );
		if ( '' === $provider ) {
			$provider = isset( $ready[ $s['default_provider'] ] ) ? $s['default_provider'] : (string) key( $ready );
		}
		if ( ! isset( $ready[ $provider ] ) ) {
			return new WP_Error( 'gruppa_pay_provider', __( 'This payment method is not available right now.', 'gruppa-payments' ) );
		}
		$period   = in_array( $d['period'], array( 'month', 'year' ), true ) ? $d['period'] : 'once';
		$currency = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $d['currency'] ), 0, 3 ) );
		$currency = 3 === strlen( $currency ) ? $currency : $s['currency'];
		$price_id = sanitize_text_field( (string) $d['price_id'] );
		if ( $price_id && 'stripe' !== $provider ) {
			return new WP_Error( 'gruppa_pay_price', 'A Stripe Price ID works with Stripe only — use an amount for PayPal.' );
		}
		$amount = $price_id ? 0 : self::to_minor( $d['amount'], $currency );
		if ( ! $price_id && $amount <= 0 ) {
			return new WP_Error( 'gruppa_pay_amount', __( 'The amount to pay is missing.', 'gruppa-payments' ) );
		}
		$user  = $d['user_id'] ? get_userdata( (int) $d['user_id'] ) : null;
		$email = sanitize_email( (string) $d['email'] );
		if ( ! $email && $user ) {
			$email = $user->user_email;
		}
		$title = trim( wp_strip_all_tags( (string) $d['title'] ) );

		$payment = self::insert( array(
			'provider'    => $provider,
			'kind'        => 'once' === $period ? 'payment' : 'subscription',
			'title'       => '' !== $title ? mb_substr( $title, 0, 191 ) : get_bloginfo( 'name' ),
			'amount'      => $amount,
			'currency'    => $currency,
			'period'      => $period,
			'price_id'    => $price_id,
			'user_id'     => $user ? (int) $user->ID : 0,
			'email'       => $email,
			'object_type' => sanitize_text_field( (string) $d['object_type'] ),
			'object_id'   => (int) $d['object_id'],
			'form_id'     => (int) $d['form_id'],
			'meta'        => array_merge( (array) $d['meta'], array(
				'product'     => mb_substr( trim( wp_strip_all_tags( (string) $d['product'] ) ), 0, 191 ),
				'name'        => mb_substr( trim( wp_strip_all_tags( (string) $d['name'] ) ), 0, 191 ),
				'success_url' => esc_url_raw( (string) $d['success_url'] ),
				'cancel_url'  => esc_url_raw( (string) $d['cancel_url'] ),
				'sync'        => Gruppa_Pay_Objects::clean_sync( $d['sync'] ),
			) ),
		) );
		if ( is_wp_error( $payment ) ) {
			return $payment;
		}

		$url = $ready[ $provider ]->start( $payment, array(
			'return' => self::return_url( $payment, 'ok' ),
			'cancel' => self::return_url( $payment, 'cancel' ),
		) );
		if ( is_wp_error( $url ) ) {
			self::set_status( $payment, 'pending' === $payment->status ? ( 'subscription' === $payment->kind ? 'expired' : 'failed' ) : $payment->status, array( 'meta' => array( 'error' => $url->get_error_message() ) ) );
			return $url;
		}
		return array( 'url' => $url, 'payment' => self::get( $payment->id ) );
	}

	/** Our return endpoint: /?gruppa_pay=return&uid=…&r=ok|cancel */
	public static function return_url( $payment, $result ) {
		return add_query_arg( array( 'gruppa_pay' => 'return', 'uid' => $payment->uid, 'r' => $result ), home_url( '/' ) );
	}

	/** Where the visitor ends after the payment page. */
	public static function result_url( $payment, $canceled = false ) {
		$s   = self::settings();
		$url = $canceled ? ( isset( $payment->meta['cancel_url'] ) ? $payment->meta['cancel_url'] : '' ) : ( isset( $payment->meta['success_url'] ) ? $payment->meta['success_url'] : '' );
		if ( '' === $url ) {
			$page = (int) ( $canceled ? $s['cancel_page'] : $s['success_page'] );
			$url  = $page ? (string) get_permalink( $page ) : '';
		}
		if ( '' === $url && $canceled && ! empty( $payment->meta['success_url'] ) ) {
			$url = $payment->meta['success_url'];
		}
		$url = '' !== $url ? $url : home_url( '/' );
		return add_query_arg( array( 'gruppa_payment' => $payment->uid, 'gruppa_payment_status' => $payment->status ), $url );
	}

	public static function handle_return() {
		if ( ! isset( $_GET['gruppa_pay'] ) || 'return' !== $_GET['gruppa_pay'] ) { // phpcs:ignore
			return;
		}
		$payment = self::get_by_uid( isset( $_GET['uid'] ) ? sanitize_text_field( wp_unslash( $_GET['uid'] ) ) : '' ); // phpcs:ignore
		if ( ! $payment ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		$canceled = isset( $_GET['r'] ) && 'cancel' === $_GET['r']; // phpcs:ignore
		$gateway  = self::gateway( $payment->provider );
		if ( $canceled ) {
			if ( 'pending' === $payment->status ) {
				$payment = self::set_status( $payment, 'canceled' );
			}
		} elseif ( $gateway && 'pending' === $payment->status ) {
			$gateway->confirm( $payment, wp_unslash( $_GET ) ); // phpcs:ignore
			$payment = self::get( $payment->id );
		}
		nocache_headers();
		wp_safe_redirect( self::result_url( $payment, $canceled ) );
		exit;
	}

	/* =========================================================
	 * Cancel subscription
	 * ======================================================= */

	/** Cancel at the provider; the webhook / this call sets the status. @return object|WP_Error */
	public static function cancel( $subscription ) {
		if ( 'subscription' !== $subscription->kind || ! in_array( $subscription->status, array( 'active', 'past_due', 'pending' ), true ) ) {
			return new WP_Error( 'gruppa_pay_cancel', 'Only an active subscription can be canceled.' );
		}
		$g = self::gateway( $subscription->provider );
		if ( $subscription->subscription_ref && $g ) {
			$r = $g->cancel( $subscription );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		return self::set_status( $subscription, 'canceled', array( 'meta' => array( 'canceled_at' => current_time( 'mysql' ) ) ) );
	}

	/* =========================================================
	 * Daily
	 * ======================================================= */

	/**
	 * - pending checkouts older than "Pending payments expire after" → expired
	 * - subscriptions whose paid_until + grace days passed → expired (object gets the unpaid value)
	 * - webhook log older than 90 days → deleted
	 *
	 * @return array counts
	 */
	public static function daily() {
		global $wpdb;
		$t     = Gruppa_Pay_DB::payments();
		$s     = self::settings();
		$hours = max( 1, (int) $s['pending_hours'] );
		$grace = max( 0, (int) $s['grace_days'] );
		$out   = array( 'expired_pending' => 0, 'expired_subscriptions' => 0 );

		$old = wp_date( 'Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS );
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$t}` WHERE status = 'pending' AND created < %s", $old ) ) as $id ) { // phpcs:ignore
			self::set_status( self::get( $id ), 'expired' );
			$out['expired_pending']++;
		}

		$limit = wp_date( 'Y-m-d', time() - $grace * DAY_IN_SECONDS );
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$t}` WHERE kind = 'subscription' AND status IN ('active','past_due') AND paid_until IS NOT NULL AND paid_until < %s", $limit ) ) as $id ) { // phpcs:ignore
			self::set_status( self::get( $id ), 'expired' );
			$out['expired_subscriptions']++;
		}

		$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . Gruppa_Pay_DB::events() . '` WHERE created < %s', wp_date( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) ); // phpcs:ignore
		return $out;
	}

	/* =========================================================
	 * Labels
	 * ======================================================= */

	public static function status_label( $status ) {
		$l = array(
			'pending'  => __( 'Pending', 'gruppa-payments' ),
			'paid'     => __( 'Paid', 'gruppa-payments' ),
			'failed'   => __( 'Failed', 'gruppa-payments' ),
			'canceled' => __( 'Canceled', 'gruppa-payments' ),
			'refunded' => __( 'Refunded', 'gruppa-payments' ),
			'expired'  => __( 'Expired', 'gruppa-payments' ),
			'active'   => __( 'Active', 'gruppa-payments' ),
			'past_due' => __( 'Past due', 'gruppa-payments' ),
		);
		return isset( $l[ $status ] ) ? $l[ $status ] : $status;
	}

	public static function period_label( $period ) {
		$l = array(
			'once'  => __( 'One-off', 'gruppa-payments' ),
			'month' => __( 'Monthly', 'gruppa-payments' ),
			'year'  => __( 'Yearly', 'gruppa-payments' ),
		);
		return isset( $l[ $period ] ) ? $l[ $period ] : $period;
	}
}
