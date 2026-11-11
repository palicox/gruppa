<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gruppa CMS Form Builder (Dynamic Forms before 0.29) → action "Payment".
 *
 * After the item is saved the visitor is sent to the payment page (Stripe Checkout / PayPal).
 * The payment belongs to the saved item (object_type gruppa:{type}); when it is paid the action
 * can write the state to a field of the item, a "paid until" date and publish the item.
 *
 * Needs Gruppa CMS 0.28+ (hooks gruppa_cms/form_action_fields, …_defaults, form_redirect, form_response).
 */
class Gruppa_Pay_Forms {

	/** Checkout URL / error of the current submission. */
	private static $redirect = '';
	private static $error    = '';

	public static function init() {
		add_filter( 'gruppa_cms/form_action_types', array( __CLASS__, 'types' ) );
		add_filter( 'gruppa_cms/form_action_defaults', array( __CLASS__, 'defaults' ), 10, 2 );
		add_filter( 'gruppa_cms/form_action_sanitize', array( __CLASS__, 'sanitize' ), 10, 3 );
		add_filter( 'gruppa_cms/form_action_summary', array( __CLASS__, 'summary' ), 10, 2 );
		add_action( 'gruppa_cms/form_action_fields', array( __CLASS__, 'fields' ), 10, 2 );
		add_action( 'gruppa_cms/form_action_payment', array( __CLASS__, 'run' ), 10, 2 );
		add_filter( 'gruppa_cms/form_redirect', array( __CLASS__, 'redirect' ), 20, 2 );
		add_filter( 'gruppa_cms/form_response', array( __CLASS__, 'response' ), 20, 2 );
	}

	public static function types( $types ) {
		$types['payment'] = 'Payment';
		return $types;
	}

	public static function defaults( $own, $type ) {
		if ( 'payment' !== $type ) {
			return $own;
		}
		return array(
			'provider'     => '',
			'price_source' => 'fixed',
			'amount'       => '',
			'amount_field' => '',
			'price_id'     => '',
			'currency'     => class_exists( 'Gruppa_Payments' ) ? Gruppa_Payments::settings()['currency'] : 'EUR',
			'period'       => 'once',
			'pay_title'    => '',
			'email_field'  => '',
			'name_field'   => '',
			'status_field' => '',
			'paid_value'   => 'paid',
			'unpaid_value' => '',
			'until_field'  => '',
			'publish'      => false,
			'success_url'  => '',
			'cancel_url'   => '',
		);
	}

	public static function sanitize( $s, $raw, $type ) {
		if ( 'payment' !== $type ) {
			return $s;
		}
		$v = function ( $k, $d = '' ) use ( $raw ) {
			return isset( $raw[ $k ] ) ? trim( (string) $raw[ $k ] ) : $d;
		};
		$cur = strtoupper( preg_replace( '/[^A-Za-z]/', '', $v( 'currency', 'EUR' ) ) );
		return array_merge( $s, array(
			'provider'     => in_array( $v( 'provider' ), array( 'stripe', 'paypal' ), true ) ? $v( 'provider' ) : '',
			// 1.0.1: price = Amount (+ optional field). A Stripe Price ID stays only on actions saved with it.
			'price_source' => '' !== sanitize_key( $v( 'amount_field' ) ) ? 'field' : ( 'stripe_price' === $v( 'price_source' ) && '' !== $v( 'price_id' ) ? 'stripe_price' : 'fixed' ),
			'amount'       => preg_replace( '/[^0-9.,]/', '', $v( 'amount' ) ),
			'amount_field' => sanitize_key( $v( 'amount_field' ) ),
			'price_id'     => 'stripe_price' === $v( 'price_source' ) ? sanitize_text_field( $v( 'price_id' ) ) : '',
			'currency'     => 3 === strlen( $cur ) ? $cur : 'EUR',
			'period'       => in_array( $v( 'period' ), array( 'month', 'year' ), true ) ? $v( 'period' ) : 'once',
			'pay_title'    => sanitize_text_field( $v( 'pay_title' ) ),
			'email_field'  => sanitize_key( $v( 'email_field' ) ),
			'name_field'   => sanitize_key( $v( 'name_field' ) ),
			'status_field' => sanitize_key( $v( 'status_field' ) ),
			'paid_value'   => sanitize_text_field( $v( 'paid_value' ) ),
			'unpaid_value' => sanitize_text_field( $v( 'unpaid_value' ) ),
			'until_field'  => sanitize_key( $v( 'until_field' ) ),
			'publish'      => ! empty( $raw['publish'] ),
			'success_url'  => preg_replace( '/[^\w\-\.~:\/\?#\[\]@!\$&\'\(\)\*\+,;=%{}]/', '', $v( 'success_url' ) ),
			'cancel_url'   => preg_replace( '/[^\w\-\.~:\/\?#\[\]@!\$&\'\(\)\*\+,;=%{}]/', '', $v( 'cancel_url' ) ),
		) );
	}

	public static function summary( $text, $a ) {
		if ( 'payment' !== $a['type'] ) {
			return $text;
		}
		$price = 'stripe_price' === $a['price_source'] ? $a['price_id'] : ( 'field' === $a['price_source'] ? '{' . $a['amount_field'] . '}' : $a['amount'] . ' ' . $a['currency'] );
		return trim( ( $a['provider'] ? ucfirst( $a['provider'] ) . ' · ' : '' ) . $price . ( 'once' !== $a['period'] ? ' / ' . $a['period'] : '' ) );
	}

	/** Settings rows in the action card (Gruppa CMS → Form Builder → Actions). */
	public static function fields( $n, $a ) {
		if ( 'payment' !== $a['type'] || ! class_exists( 'Gruppa_Admin_Form_Actions' ) || ! class_exists( 'Gruppa_Payments' ) ) {
			return;
		}
		$A     = 'Gruppa_Admin_Form_Actions';
		$ready = Gruppa_Payments::ready_gateways();
		if ( ! $ready ) {
			$A::row( 'Gateway', function () {
				echo '<span class="gruppa-muted">No gateway is set up yet — fill in the Stripe keys in <a href="' . esc_url( Gruppa_Tools_Hub::url( Gruppa_Pay_Admin::PAGE, array( 'tab' => 'settings' ) ) ) . '">Payments → Settings</a>.</span>';
			} );
		}
		$choose = count( $ready ) > 1 || ( '' !== $a['provider'] && ! isset( $ready[ $a['provider'] ] ) );
		if ( $choose ) {
			$A::row( 'Gateway', function () use ( $A, $n, $a ) {
				$A::select( $n . '[provider]', array( '' => 'Default (Settings)', 'stripe' => 'Stripe', 'paypal' => 'PayPal' ), $a['provider'] );
			} );
		}
		$A::row( 'Amount', function () use ( $A, $n, $a, $choose ) {
			if ( ! $choose ) {
				echo '<input type="hidden" name="' . esc_attr( $n ) . '[provider]" value="">'; // One gateway: no choice to show.
			}
			$A::text( $n . '[amount]', $a['amount'], '9.00' );
			echo ' ';
			$cur = Gruppa_Payments::currencies();
			$A::select( $n . '[currency]', array_combine( $cur, $cur ) + array( $a['currency'] => $a['currency'] ), $a['currency'] );
			echo '<p class="description">The price lives here on the website — nothing has to be created in Stripe.</p>';
		} );
		$A::row( 'Amount from a field', function () use ( $A, $n, $a ) {
			$A::field_select( $n . '[amount_field]', $a['amount_field'], 'any', '— no, use the Amount above —' );
			echo '<p class="description">Optional: different price per item (e.g. a Number field "price"). A hidden field also works: visitors cannot change its value.</p>';
		} );
		if ( 'stripe_price' === $a['price_source'] && '' !== $a['price_id'] ) {
			$A::row( 'Stripe Price ID', function () use ( $A, $n, $a ) {
				echo '<input type="hidden" name="' . esc_attr( $n ) . '[price_source]" value="stripe_price">';
				$A::text( $n . '[price_id]', $a['price_id'], 'price_…' );
				echo '<p class="description">Saved by an older version — the price comes from Stripe. Empty it to use the Amount above.</p>';
			} );
		}
		$A::row( 'Billing', function () use ( $A, $n, $a ) {
			$A::select( $n . '[period]', array( 'once' => 'One-off payment', 'month' => 'Subscription — monthly', 'year' => 'Subscription — yearly' ), $a['period'] );
		} );
		$A::row( 'Product name', function () use ( $A, $n, $a ) {
			$A::text( $n . '[pay_title]', $a['pay_title'], 'Membership — empty = form name' );
			echo '<p class="description">A fixed name, e.g. "Membership" — shown on the Stripe payment page. All payments of this action share one product in Stripe (created automatically). Placeholders like {title} go only to the payment list, not to the product.</p>';
		} );
		$A::row( 'Email field', function () use ( $A, $n, $a ) {
			$A::field_select( $n . '[email_field]', $a['email_field'], 'text', '— logged-in user / asked on the payment page —' );
			echo '<p class="description">Creates (or finds) the customer in Stripe with this email.</p>';
		} );
		$A::row( 'Name field', function () use ( $A, $n, $a ) {
			$A::field_select( $n . '[name_field]', $a['name_field'], 'text', '— none (Title of the item when empty) —' );
			echo '<p class="description">Name of the customer in Stripe, e.g. a field "Meno".</p>';
		} );
		$A::row( 'Payment state field', function () use ( $A, $n, $a ) {
			$A::field_select( $n . '[status_field]', $a['status_field'], 'any', '— none —' );
			echo '<p class="description">Field of the item that gets the values below, e.g. a Select "payment_status".</p>';
		} );
		$A::row( 'Value when paid', function () use ( $A, $n, $a ) {
			$A::text( $n . '[paid_value]', $a['paid_value'], 'paid' );
		} );
		$A::row( 'Value when unpaid', function () use ( $A, $n, $a ) {
			$A::text( $n . '[unpaid_value]', $a['unpaid_value'], 'blocked — empty = leave as is' );
			echo '<p class="description">Written when a subscription is canceled or expires, or a payment is refunded.</p>';
		} );
		$A::row( 'Paid until field', function () use ( $A, $n, $a ) {
			$A::field_select( $n . '[until_field]', $a['until_field'], 'any', '— none —' );
			echo '<p class="description">Subscriptions: a Date field that gets the end of the paid period.</p>';
		} );
		$A::row( 'Publish when paid', function () use ( $A, $n, $a ) {
			$A::check( $n . '[publish]', ! empty( $a['publish'] ), 'Publish the item when it is paid' );
			echo '<p class="description">Tip: let the form save new items as Draft — they go live after the payment.</p>';
		} );
		$A::row( 'After payment', function () use ( $A, $n, $a ) {
			$A::text( $n . '[success_url]', $a['success_url'], 'Success URL — empty = form page' );
			echo '<br>';
			$A::text( $n . '[cancel_url]', $a['cancel_url'], 'Cancel URL — empty = form page' );
			echo '<p class="description">The result message is shown automatically on the page (or place [gruppa_pay_status]).</p>';
		} );
	}

	/** After the item is saved: create the payment and remember the checkout URL. */
	public static function run( $a, $ctx ) {
		if ( 'delete' === $ctx['mode'] || empty( $ctx['item']['id'] ) || ! class_exists( 'Gruppa_Payments' ) ) {
			return;
		}
		$item = (array) $ctx['item'];
		$data = (array) $ctx['data'];
		$val  = function ( $key ) use ( $item, $data ) {
			if ( '' === $key ) {
				return '';
			}
			$v = isset( $item[ $key ] ) ? $item[ $key ] : ( isset( $data[ $key ] ) ? $data[ $key ] : '' );
			return is_scalar( $v ) ? (string) $v : '';
		};
		$page = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$url  = function ( $u ) use ( $ctx, $page ) {
			return '' === $u ? $page : esc_url_raw( Gruppa_Form_Actions::replace( $u, $ctx, 'url' ) );
		};

		$args = array(
			'provider'    => $a['provider'],
			'currency'    => $a['currency'],
			'period'      => $a['period'],
			'title'       => '' !== $a['pay_title'] ? Gruppa_Form_Actions::replace( $a['pay_title'], $ctx ) : $ctx['form']->name,
			// One Stripe product per action: the name without item placeholders.
			'product'     => '' !== $a['pay_title'] && false === strpos( $a['pay_title'], '{' ) ? $a['pay_title'] : $ctx['form']->name,
			'email'       => $val( $a['email_field'] ),
			'name'        => isset( $a['name_field'] ) && '' !== $a['name_field'] ? $val( $a['name_field'] ) : ( isset( $item['title'] ) ? (string) $item['title'] : '' ),
			'object_type' => 'gruppa:' . $ctx['type']->slug,
			'object_id'   => (int) $item['id'],
			'form_id'     => (int) $ctx['form']->id,
			'success_url' => $url( $a['success_url'] ),
			'cancel_url'  => $url( $a['cancel_url'] ),
			'sync'        => array( 'field' => $a['status_field'], 'paid' => $a['paid_value'], 'unpaid' => $a['unpaid_value'], 'until' => $a['until_field'], 'publish' => ! empty( $a['publish'] ) ),
		);
		if ( 'stripe_price' === $a['price_source'] ) {
			$args['price_id'] = $a['price_id'];
		} else {
			$args['amount'] = 'field' === $a['price_source'] ? $val( $a['amount_field'] ) : $a['amount'];
		}
		$r = Gruppa_Payments::checkout( apply_filters( 'gruppa_payments/form_checkout_args', $args, $a, $ctx ) );
		if ( is_wp_error( $r ) ) {
			self::$error = $r->get_error_message();
			return;
		}
		self::$redirect = $r['url'];
	}

	public static function redirect( $url, $ctx ) {
		return '' !== self::$redirect ? self::$redirect : $url;
	}

	public static function response( $res, $ctx ) {
		if ( '' !== self::$error ) {
			$res['message'] = trim( $res['message'] . ' ' . __( 'The payment could not be started:', 'gruppa-payments' ) . ' ' . self::$error );
		}
		return $res;
	}
}
