<?php
defined( 'ABSPATH' ) || exit;

/**
 * Website: shortcodes and their handlers.
 *
 *   [gruppa_pay amount="19.90" title="Membership" period="once|month|year" currency="EUR"
 *               provider="stripe|paypal|" price="price_…" label="Pay now"
 *               object="gruppa:clanky" object_id="5" field="payment_status" paid="paid"
 *               unpaid="" until="" publish="0" success="/thanks/" cancel=""]
 *       provider empty = one button per active gateway (card / PayPal). price = Stripe Price ID.
 *       The settings are signed (HMAC) — visitors cannot change the amount.
 *
 *   [gruppa_pay_status]          result after returning from the payment page (?gruppa_payment=…)
 *   [gruppa_pay_subscriptions]   subscriptions of the logged-in user: manage (Stripe) / cancel (PayPal)
 */
class Gruppa_Pay_Front {

	private static $status_shown = false;

	public static function init() {
		add_shortcode( 'gruppa_pay', array( __CLASS__, 'shortcode_pay' ) );
		add_shortcode( 'gruppa_pay_status', array( __CLASS__, 'shortcode_status' ) );
		add_shortcode( 'gruppa_pay_subscriptions', array( __CLASS__, 'shortcode_subscriptions' ) );
		add_action( 'admin_post_gruppa_pay', array( __CLASS__, 'handle_pay' ) );
		add_action( 'admin_post_nopriv_gruppa_pay', array( __CLASS__, 'handle_pay' ) );
		add_action( 'admin_post_gruppa_pay_portal', array( __CLASS__, 'handle_portal' ) );
		add_action( 'admin_post_gruppa_pay_cancel', array( __CLASS__, 'handle_cancel' ) );
		add_filter( 'the_content', array( __CLASS__, 'auto_status' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'gruppa-pay', GRUPPA_PAY_URL . 'assets/gruppa-pay.css', array(), GRUPPA_PAY_VERSION );
	}

	private static function sign( $cfg ) {
		$json = wp_json_encode( $cfg );
		return array( base64_encode( $json ), hash_hmac( 'sha256', $json, wp_salt( 'auth' ) . 'gruppa_pay' ) ); // phpcs:ignore
	}

	private static function unsign( $b64, $sig ) {
		$json = base64_decode( (string) $b64, true ); // phpcs:ignore
		if ( false === $json || ! hash_equals( hash_hmac( 'sha256', $json, wp_salt( 'auth' ) . 'gruppa_pay' ), (string) $sig ) ) {
			return null;
		}
		$cfg = json_decode( $json, true );
		return is_array( $cfg ) ? $cfg : null;
	}

	/* ---------- [gruppa_pay] ---------- */

	public static function shortcode_pay( $atts ) {
		$a = shortcode_atts( array(
			'amount'    => '',
			'price'     => '',
			'currency'  => Gruppa_Payments::settings()['currency'],
			'period'    => 'once',
			'title'     => '',
			'provider'  => '',
			'label'     => '',
			'object'    => '',
			'object_id' => '',
			'field'     => '',
			'paid'      => '',
			'unpaid'    => '',
			'until'     => '',
			'publish'   => '0',
			'success'   => '',
			'cancel'    => '',
			'class'     => '',
		), $atts, 'gruppa_pay' );

		$ready = Gruppa_Payments::ready_gateways();
		if ( $a['provider'] ) {
			$ready = array_intersect_key( $ready, array( $a['provider'] => 1 ) );
		}
		if ( $a['price'] ) {
			$ready = array_intersect_key( $ready, array( 'stripe' => 1 ) );
		}
		if ( ! $ready ) {
			return current_user_can( 'manage_options' ) ? '<p class="gruppa-pay-note">Gruppa Payments: no payment gateway is set up for the current mode (Gruppa Tools → Payments → Settings).</p>' : '';
		}
		$object_id = 'current' === $a['object_id'] ? get_the_ID() : (int) $a['object_id'];
		$cfg       = array(
			'amount'      => (string) $a['amount'],
			'price_id'    => (string) $a['price'],
			'currency'    => (string) $a['currency'],
			'period'      => (string) $a['period'],
			'title'       => '' !== $a['title'] ? (string) $a['title'] : get_the_title(),
			'object_type' => (string) $a['object'],
			'object_id'   => $object_id,
			'success_url' => $a['success'] ? (string) $a['success'] : (string) get_permalink(),
			'cancel_url'  => $a['cancel'] ? (string) $a['cancel'] : (string) get_permalink(),
			'sync'        => array( 'field' => $a['field'], 'paid' => $a['paid'], 'unpaid' => $a['unpaid'], 'until' => $a['until'], 'publish' => in_array( $a['publish'], array( '1', 'true', 'yes' ), true ) ),
		);
		list( $b64, $sig ) = self::sign( $cfg );

		$amount = $a['price'] ? '' : Gruppa_Payments::format( Gruppa_Payments::to_minor( $a['amount'], $a['currency'] ), $a['currency'] );
		$suffix = 'month' === $a['period'] ? ' / ' . __( 'month', 'gruppa-payments' ) : ( 'year' === $a['period'] ? ' / ' . __( 'year', 'gruppa-payments' ) : '' );
		wp_enqueue_style( 'gruppa-pay' );

		$out  = '<form class="gruppa-pay ' . esc_attr( $a['class'] ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="gruppa_pay"><input type="hidden" name="gp_cfg" value="' . esc_attr( $b64 ) . '"><input type="hidden" name="gp_sig" value="' . esc_attr( $sig ) . '">';
		foreach ( $ready as $id => $g ) {
			if ( '' !== $a['label'] ) {
				$label = count( $ready ) > 1 ? $a['label'] . ' — ' . $g->label() : $a['label'];
			} elseif ( 'paypal' === $id ) {
				$label = __( 'Pay with PayPal', 'gruppa-payments' );
			} else {
				$label = count( $ready ) > 1 ? __( 'Pay by card', 'gruppa-payments' ) : __( 'Pay', 'gruppa-payments' );
			}
			if ( $amount && '' === $a['label'] ) {
				$label .= ' ' . $amount . $suffix;
			}
			$out .= '<button type="submit" name="gp_provider" value="' . esc_attr( $id ) . '" class="gruppa-pay-button gruppa-pay-button--' . esc_attr( $id ) . '">' . esc_html( $label ) . '</button>';
		}
		return $out . '</form>';
	}

	public static function handle_pay() {
		$cfg = self::unsign( isset( $_POST['gp_cfg'] ) ? wp_unslash( $_POST['gp_cfg'] ) : '', isset( $_POST['gp_sig'] ) ? wp_unslash( $_POST['gp_sig'] ) : '' ); // phpcs:ignore
		if ( ! $cfg ) {
			wp_die( esc_html__( 'The payment link is not valid. Reload the page and try again.', 'gruppa-payments' ), '', array( 'response' => 400, 'back_link' => true ) );
		}
		$cfg['provider'] = isset( $_POST['gp_provider'] ) ? sanitize_key( wp_unslash( $_POST['gp_provider'] ) ) : ''; // phpcs:ignore
		$r               = Gruppa_Payments::checkout( $cfg );
		if ( is_wp_error( $r ) ) {
			wp_die( esc_html( $r->get_error_message() ), '', array( 'response' => 400, 'back_link' => true ) );
		}
		wp_redirect( $r['url'] ); // phpcs:ignore — external payment page.
		exit;
	}

	/* ---------- Result ---------- */

	public static function current_result() {
		$uid = isset( $_GET['gruppa_payment'] ) ? sanitize_text_field( wp_unslash( $_GET['gruppa_payment'] ) ) : ''; // phpcs:ignore
		return $uid ? Gruppa_Payments::get_by_uid( $uid ) : null;
	}

	public static function message( $payment ) {
		$m = array(
			'paid'     => __( 'Thank you, the payment was successful.', 'gruppa-payments' ),
			'active'   => __( 'Thank you, your subscription is active.', 'gruppa-payments' ),
			'pending'  => __( 'We are waiting for the payment to be confirmed. This usually takes a moment.', 'gruppa-payments' ),
			'canceled' => __( 'The payment was canceled.', 'gruppa-payments' ),
			'failed'   => __( 'The payment failed. Please try again.', 'gruppa-payments' ),
			'expired'  => __( 'The payment has expired. Please try again.', 'gruppa-payments' ),
			'past_due' => __( 'The payment of your subscription is overdue.', 'gruppa-payments' ),
			'refunded' => __( 'The payment was refunded.', 'gruppa-payments' ),
		);
		return isset( $m[ $payment->status ] ) ? $m[ $payment->status ] : '';
	}

	public static function shortcode_status() {
		$p = self::current_result();
		if ( ! $p ) {
			return '';
		}
		self::$status_shown = true;
		wp_enqueue_style( 'gruppa-pay' );
		$ok   = in_array( $p->status, array( 'paid', 'active' ), true );
		$wait = 'pending' === $p->status;
		return sprintf(
			'<div class="gruppa-pay-result gruppa-pay-result--%1$s" role="status"><p><strong>%2$s</strong></p><p>%3$s · %4$s</p></div>',
			esc_attr( $ok ? 'ok' : ( $wait ? 'wait' : 'error' ) ),
			esc_html( self::message( $p ) ),
			esc_html( $p->title ),
			esc_html( Gruppa_Payments::format( $p->amount, $p->currency ) . ( 'once' !== $p->period ? ' / ' . Gruppa_Payments::period_label( $p->period ) : '' ) )
		);
	}

	/** Result box above the content of the return page when the page has no [gruppa_pay_status]. */
	public static function auto_status( $content ) {
		// Only the content of the page itself (works in classic and block themes).
		if ( self::$status_shown || is_admin() || ! isset( $_GET['gruppa_payment'] ) || ! is_singular() || (int) get_the_ID() !== (int) get_queried_object_id() || has_shortcode( $content, 'gruppa_pay_status' ) ) { // phpcs:ignore
			return $content;
		}
		return self::shortcode_status() . $content;
	}

	/* ---------- [gruppa_pay_subscriptions] ---------- */

	public static function shortcode_subscriptions() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		global $wpdb;
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id FROM `' . Gruppa_Pay_DB::payments() . "` WHERE user_id = %d AND kind = 'subscription' AND status <> 'pending' ORDER BY id DESC", get_current_user_id() ) ); // phpcs:ignore
		if ( ! $rows ) {
			return '<p class="gruppa-pay-note">' . esc_html__( 'You have no subscriptions.', 'gruppa-payments' ) . '</p>';
		}
		wp_enqueue_style( 'gruppa-pay' );
		$back = (string) get_permalink();
		$out  = '<table class="gruppa-pay-subs"><thead><tr><th>' . esc_html__( 'Subscription', 'gruppa-payments' ) . '</th><th>' . esc_html__( 'Price', 'gruppa-payments' ) . '</th><th>' . esc_html__( 'Status', 'gruppa-payments' ) . '</th><th>' . esc_html__( 'Paid until', 'gruppa-payments' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$p   = Gruppa_Payments::get( $r->id );
			$act = '';
			if ( in_array( $p->status, array( 'active', 'past_due' ), true ) ) {
				$form = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'gruppa_pay_sub_' . $p->id, '_wpnonce', true, false ) . '<input type="hidden" name="id" value="' . (int) $p->id . '"><input type="hidden" name="back" value="' . esc_attr( $back ) . '">';
				if ( 'stripe' === $p->provider && $p->customer_ref ) {
					$act = $form . '<input type="hidden" name="action" value="gruppa_pay_portal"><button type="submit" class="gruppa-pay-link">' . esc_html__( 'Manage', 'gruppa-payments' ) . '</button></form>';
				} else {
					$act = $form . '<input type="hidden" name="action" value="gruppa_pay_cancel"><button type="submit" class="gruppa-pay-link" onclick="return confirm(\'' . esc_js( __( 'Cancel this subscription?', 'gruppa-payments' ) ) . '\')">' . esc_html__( 'Cancel', 'gruppa-payments' ) . '</button></form>';
				}
			}
			$out .= sprintf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $p->title ),
				esc_html( Gruppa_Payments::format( $p->amount, $p->currency ) . ' / ' . Gruppa_Payments::period_label( $p->period ) ),
				esc_html( Gruppa_Payments::status_label( $p->status ) ),
				esc_html( $p->paid_until ? mysql2date( get_option( 'date_format' ), $p->paid_until ) : '—' ),
				$act // phpcs:ignore — escaped above.
			);
		}
		return $out . '</tbody></table>';
	}

	/** Subscription of the logged-in user from the posted form. */
	private static function own_subscription() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore
		check_admin_referer( 'gruppa_pay_sub_' . $id );
		$p = Gruppa_Payments::get( $id );
		if ( ! $p || 'subscription' !== $p->kind || ( (int) $p->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) ) {
			wp_die( 'Forbidden', '', array( 'response' => 403 ) );
		}
		return $p;
	}

	private static function back() {
		$url = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : ''; // phpcs:ignore
		return wp_validate_redirect( $url, home_url( '/' ) );
	}

	public static function handle_portal() {
		$p = self::own_subscription();
		$g = Gruppa_Payments::gateway( 'stripe' );
		$u = $g ? $g->portal_url( $p->customer_ref, self::back() ) : new WP_Error( 'stripe', 'Stripe is not available.' );
		if ( is_wp_error( $u ) ) {
			wp_die( esc_html( $u->get_error_message() ), '', array( 'back_link' => true ) );
		}
		wp_redirect( $u ); // phpcs:ignore — Stripe page.
		exit;
	}

	public static function handle_cancel() {
		$p = self::own_subscription();
		$r = Gruppa_Payments::cancel( $p );
		if ( is_wp_error( $r ) ) {
			wp_die( esc_html( $r->get_error_message() ), '', array( 'back_link' => true ) );
		}
		wp_safe_redirect( self::back() );
		exit;
	}
}
