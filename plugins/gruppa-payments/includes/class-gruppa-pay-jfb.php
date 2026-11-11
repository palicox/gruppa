<?php
defined( 'ABSPATH' ) || exit;

/**
 * JetFormBuilder → payment (1.1.0). Works without Gruppa CMS.
 *
 * Payments → Forms: a rule per JetFormBuilder form. After a successful submission (all form actions done,
 * hook jet-form-builder/form-handler/after-send) the payment is created and the visitor goes to Stripe Checkout
 * or PayPal (response "redirect" — AJAX and page reload submit both follow it).
 *
 * The payment belongs to: the post the form created (Insert / Update Post action), the logged-in user, or nothing.
 * Payment state, "paid until" and publish are written as post / user meta (Gruppa_Pay_Objects::update).
 * Rules: option gruppa_payments_jfb.
 */
class Gruppa_Pay_JFB {

	const OPT = 'gruppa_payments_jfb';

	public static function init() {
		add_action( 'jet-form-builder/form-handler/after-send', array( __CLASS__, 'after_send' ), 30, 2 );
		if ( is_admin() ) {
			add_action( 'admin_post_gruppa_pay_jfb_save', array( __CLASS__, 'admin_save' ) );
			add_action( 'admin_post_gruppa_pay_jfb_delete', array( __CLASS__, 'admin_delete' ) );
		}
	}

	public static function active() {
		return function_exists( 'jet_form_builder' ) || post_type_exists( 'jet-form-builder' );
	}

	/* ======================================================
	 * Rules
	 * ==================================================== */

	public static function rules() {
		$r = get_option( self::OPT, array() );
		return is_array( $r ) ? array_values( $r ) : array();
	}

	public static function rule( $id ) {
		foreach ( self::rules() as $r ) {
			if ( (int) $r['id'] === (int) $id ) {
				return wp_parse_args( $r, self::defaults() );
			}
		}
		return null;
	}

	public static function defaults() {
		return array(
			'id'           => 0,
			'enabled'      => 1,
			'form'         => 0,
			'provider'     => '',
			'amount'       => '',
			'amount_field' => '',
			'currency'     => Gruppa_Payments::settings()['currency'],
			'period'       => 'once',
			'product'      => '',
			'pay_title'    => '',
			'email_field'  => '',
			'name_field'   => '',
			'object'       => 'post',
			'status_field' => '',
			'paid_value'   => 'paid',
			'unpaid_value' => '',
			'until_field'  => '',
			'publish'      => 0,
			'success_url'  => '',
			'cancel_url'   => '',
		);
	}

	private static function clean( $raw, $id ) {
		$v   = function ( $k, $d = '' ) use ( $raw ) {
			return isset( $raw[ $k ] ) ? trim( (string) $raw[ $k ] ) : $d;
		};
		$key = function ( $k ) use ( $v ) {
			return preg_replace( '/[^A-Za-z0-9_\-]/', '', $v( $k ) );
		};
		$url = function ( $k ) use ( $v ) {
			return preg_replace( '/[^\w\-\.~:\/\?#\[\]@!\$&\'\(\)\*\+,;=%{}]/', '', $v( $k ) );
		};
		$cur = strtoupper( preg_replace( '/[^A-Za-z]/', '', $v( 'currency', 'EUR' ) ) );
		return array(
			'id'           => (int) $id,
			'enabled'      => empty( $raw['enabled'] ) ? 0 : 1,
			'form'         => absint( $v( 'form' ) ),
			'provider'     => in_array( $v( 'provider' ), array( 'stripe', 'paypal' ), true ) ? $v( 'provider' ) : '',
			'amount'       => preg_replace( '/[^0-9.,]/', '', $v( 'amount' ) ),
			'amount_field' => $key( 'amount_field' ),
			'currency'     => 3 === strlen( $cur ) ? $cur : 'EUR',
			'period'       => in_array( $v( 'period' ), array( 'month', 'year' ), true ) ? $v( 'period' ) : 'once',
			'product'      => sanitize_text_field( $v( 'product' ) ),
			'pay_title'    => sanitize_text_field( $v( 'pay_title' ) ),
			'email_field'  => $key( 'email_field' ),
			'name_field'   => $key( 'name_field' ),
			'object'       => in_array( $v( 'object' ), array( 'post', 'user', 'none' ), true ) ? $v( 'object' ) : 'post',
			'status_field' => sanitize_key( $v( 'status_field' ) ),
			'paid_value'   => sanitize_text_field( $v( 'paid_value' ) ),
			'unpaid_value' => sanitize_text_field( $v( 'unpaid_value' ) ),
			'until_field'  => sanitize_key( $v( 'until_field' ) ),
			'publish'      => empty( $raw['publish'] ) ? 0 : 1,
			'success_url'  => $url( 'success_url' ),
			'cancel_url'   => $url( 'cancel_url' ),
		);
	}

	/** JetFormBuilder forms: ID => title. */
	public static function forms() {
		$out = array();
		foreach ( get_posts( array( 'post_type' => 'jet-form-builder', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
			$out[ $p->ID ] = '' !== $p->post_title ? $p->post_title : '#' . $p->ID;
		}
		return $out;
	}

	/** Field names of a form (blocks jet-forms/*). */
	public static function fields( $form_id ) {
		$p     = $form_id ? get_post( (int) $form_id ) : null;
		$names = array();
		if ( ! $p ) {
			return $names;
		}
		$walk = function ( $blocks ) use ( &$walk, &$names ) {
			foreach ( $blocks as $b ) {
				if ( ! empty( $b['attrs']['name'] ) && 0 === strpos( (string) $b['blockName'], 'jet-forms/' ) ) {
					$names[] = sanitize_text_field( $b['attrs']['name'] );
				}
				if ( ! empty( $b['innerBlocks'] ) ) {
					$walk( $b['innerBlocks'] );
				}
			}
		};
		$walk( parse_blocks( $p->post_content ) );
		return array_values( array_unique( $names ) );
	}

	/* ======================================================
	 * Submission
	 * ==================================================== */

	public static function after_send( $handler, $is_success = true ) {
		if ( ! $is_success || ! is_object( $handler ) || ! Gruppa_Payments::enabled() ) {
			return;
		}
		$form_id = method_exists( $handler, 'get_form_id' ) ? (int) $handler->get_form_id() : 0;
		$rules   = array_filter( self::rules(), function ( $r ) use ( $form_id ) {
			return ! empty( $r['enabled'] ) && (int) $r['form'] === $form_id;
		} );
		if ( ! $form_id || ! $rules ) {
			return;
		}
		$data = function_exists( 'jet_fb_context' ) ? (array) jet_fb_context()->get_request() : array();
		$rule = wp_parse_args( reset( $rules ), self::defaults() ); // One payment per submission.

		$val = function ( $k ) use ( $data ) {
			$v = '' !== $k && isset( $data[ $k ] ) ? $data[ $k ] : '';
			return is_scalar( $v ) ? trim( (string) $v ) : '';
		};
		$title = get_the_title( $form_id );
		$fill  = function ( $text ) use ( $data, $title ) {
			return preg_replace_callback( '/\{([A-Za-z0-9_\-]+)\}/', function ( $m ) use ( $data, $title ) {
				if ( 'form' === $m[1] ) {
					return $title;
				}
				if ( 'site_name' === $m[1] ) {
					return get_bloginfo( 'name' );
				}
				return isset( $data[ $m[1] ] ) && is_scalar( $data[ $m[1] ] ) ? (string) $data[ $m[1] ] : '';
			}, (string) $text );
		};

		// Object: post created by the form, logged-in user, or none.
		$otype = '';
		$oid   = 0;
		if ( 'post' === $rule['object'] && function_exists( 'jet_fb_action_handler' ) ) {
			$oid   = (int) jet_fb_action_handler()->get_inserted_post_id();
			$otype = $oid ? 'post' : '';
		} elseif ( 'user' === $rule['object'] && get_current_user_id() ) {
			$otype = 'user';
			$oid   = get_current_user_id();
		}

		$page = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$args = array(
			'provider'    => $rule['provider'],
			'amount'      => '' !== $rule['amount_field'] && '' !== $val( $rule['amount_field'] ) ? $val( $rule['amount_field'] ) : $rule['amount'],
			'currency'    => $rule['currency'],
			'period'      => $rule['period'],
			'title'       => '' !== $rule['pay_title'] ? $fill( $rule['pay_title'] ) : ( '' !== $rule['product'] ? $rule['product'] : $title ),
			'product'     => '' !== $rule['product'] ? $rule['product'] : $title, // One Stripe product per rule.
			'email'       => $val( $rule['email_field'] ),
			'name'        => $val( $rule['name_field'] ),
			'object_type' => $otype,
			'object_id'   => $oid,
			'form_id'     => $form_id,
			'success_url' => '' !== $rule['success_url'] ? esc_url_raw( $fill( $rule['success_url'] ) ) : $page,
			'cancel_url'  => '' !== $rule['cancel_url'] ? esc_url_raw( $fill( $rule['cancel_url'] ) ) : $page,
			'sync'        => array( 'field' => $rule['status_field'], 'paid' => $rule['paid_value'], 'unpaid' => $rule['unpaid_value'], 'until' => $rule['until_field'], 'publish' => ! empty( $rule['publish'] ) ),
		);
		$r = Gruppa_Payments::checkout( apply_filters( 'gruppa_payments/jfb_checkout_args', $args, $rule, $data ) );
		if ( is_wp_error( $r ) || empty( $r['url'] ) ) {
			// The submission stays successful (its actions already ran); the visitor stays on the form page.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Gruppa Payments / JetFormBuilder form ' . $form_id . ': ' . ( is_wp_error( $r ) ? $r->get_error_message() : 'no checkout URL' ) ); // phpcs:ignore
			}
			return;
		}
		if ( method_exists( $handler, 'add_response_data' ) ) {
			$handler->add_response_data( array( 'redirect' => $r['url'] ) );
		}
	}

	/* ======================================================
	 * Admin (Payments → Forms)
	 * ==================================================== */

	private static function guard( $nonce ) {
		if ( ! current_user_can( Gruppa_Pay_Admin::CAP ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( $nonce );
	}

	public static function admin_save() {
		self::guard( 'gruppa_pay_jfb_save' );
		$raw   = isset( $_POST['jfb'] ) ? (array) wp_unslash( $_POST['jfb'] ) : array(); // phpcs:ignore
		$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$rules = self::rules();
		if ( ! $id ) {
			$id = 1;
			foreach ( $rules as $r ) {
				$id = max( $id, (int) $r['id'] + 1 );
			}
		}
		$rule  = self::clean( $raw, $id );
		$found = false;
		foreach ( $rules as $i => $r ) {
			if ( (int) $r['id'] === $id ) {
				$rules[ $i ] = $rule;
				$found       = true;
			}
		}
		if ( ! $found ) {
			$rules[] = $rule;
		}
		update_option( self::OPT, array_values( $rules ), false );
		wp_safe_redirect( Gruppa_Pay_Admin::url( array( 'tab' => 'forms', 'edit' => $id, 'msg' => 'saved' ) ) );
		exit;
	}

	public static function admin_delete() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
		self::guard( 'gruppa_pay_jfb_delete_' . $id );
		update_option( self::OPT, array_values( array_filter( self::rules(), function ( $r ) use ( $id ) {
			return (int) $r['id'] !== $id;
		} ) ), false );
		wp_safe_redirect( Gruppa_Pay_Admin::url( array( 'tab' => 'forms', 'msg' => 'deleted' ) ) );
		exit;
	}

	public static function render() {
		$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore
		if ( '' !== $edit ) {
			self::render_edit( 'new' === $edit ? null : self::rule( (int) $edit ) );
			return;
		}
		$forms = self::forms();
		?>
		<p><a href="<?php echo esc_url( Gruppa_Pay_Admin::url( array( 'tab' => 'forms', 'edit' => 'new' ) ) ); ?>" class="button">Add form</a></p>
		<?php if ( ! self::active() ) : ?>
			<div class="notice notice-info inline"><p>JetFormBuilder is not active — rules are kept and work again when it is.</p></div>
		<?php endif; ?>
		<table class="wp-list-table widefat fixed gruppa-table">
			<thead><tr><th>JetFormBuilder form</th><th>Price</th><th>Billing</th><th>Payment belongs to</th><th>Status</th><th class="gruppa-col-actions">Actions</th></tr></thead>
			<tbody>
			<?php if ( ! self::rules() ) : ?>
				<tr><td colspan="6">No form yet. Add a JetFormBuilder form — after it is sent, the visitor pays through Stripe or PayPal.</td></tr>
			<?php endif; ?>
			<?php foreach ( self::rules() as $r ) :
				$r    = wp_parse_args( $r, self::defaults() );
				$edit = Gruppa_Pay_Admin::url( array( 'tab' => 'forms', 'edit' => (int) $r['id'] ) );
				$del  = wp_nonce_url( add_query_arg( array( 'action' => 'gruppa_pay_jfb_delete', 'id' => (int) $r['id'] ), admin_url( 'admin-post.php' ) ), 'gruppa_pay_jfb_delete_' . (int) $r['id'] );
				$obj  = array( 'post' => 'Post created by the form', 'user' => 'Logged-in user', 'none' => '—' );
				?>
				<tr>
					<td><strong><a class="row-title" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( isset( $forms[ $r['form'] ] ) ? $forms[ $r['form'] ] : '#' . (int) $r['form'] ); ?></a></strong></td>
					<td><?php echo esc_html( '' !== $r['amount_field'] ? '{' . $r['amount_field'] . '}' : $r['amount'] . ' ' . $r['currency'] ); ?></td>
					<td><?php echo esc_html( 'once' === $r['period'] ? 'One-off' : ( 'month' === $r['period'] ? 'Monthly' : 'Yearly' ) ); ?></td>
					<td><?php echo esc_html( $obj[ $r['object'] ] ); ?></td>
					<td><?php echo $r['enabled'] ? 'On' : 'Off'; ?></td>
					<td><a href="<?php echo esc_url( $edit ); ?>">Edit</a> | <a class="gruppa-delete" href="<?php echo esc_url( $del ); ?>" onclick="return confirm('Delete this rule?')">Delete</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_edit( $r ) {
		$is_new = ! $r;
		$r      = $r ? $r : self::defaults();
		$forms  = self::forms();
		$names  = self::fields( (int) $r['form'] );
		$ready  = Gruppa_Payments::ready_gateways();
		$cur    = Gruppa_Payments::currencies();
		$text   = function ( $k, $ph = '' ) use ( $r ) {
			printf( '<input type="text" name="jfb[%1$s]" id="gpj-%1$s" value="%2$s" placeholder="%3$s" list="gpj-fields">', esc_attr( $k ), esc_attr( $r[ $k ] ), esc_attr( $ph ) );
		};
		?>
		<p><a href="<?php echo esc_url( Gruppa_Pay_Admin::url( array( 'tab' => 'forms' ) ) ); ?>">← Forms</a></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gruppa_pay_jfb_save">
			<input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
			<?php wp_nonce_field( 'gruppa_pay_jfb_save' ); ?>
			<datalist id="gpj-fields"><?php foreach ( $names as $n ) : ?><option value="<?php echo esc_attr( $n ); ?>"><?php endforeach; ?></datalist>
			<h2 class="gruppa-fields-title">Main Settings</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="gpj-form">Form</label></th>
						<td>
							<select name="jfb[form]" id="gpj-form" required>
								<option value="">— select —</option>
								<?php foreach ( $forms as $fid => $ft ) : ?>
									<option value="<?php echo (int) $fid; ?>" <?php selected( (int) $r['form'], (int) $fid ); ?>><?php echo esc_html( $ft ); ?></option>
								<?php endforeach; ?>
							</select>
							<?php if ( $is_new ) : ?><p class="description">After the first save the field names of the form are offered in the fields below.</p><?php endif; ?>
						</td>
					</tr>
					<?php if ( count( $ready ) > 1 ) : ?>
						<tr>
							<th><label for="gpj-provider">Gateway</label></th>
							<td><select name="jfb[provider]" id="gpj-provider"><option value="">Default (Settings)</option><option value="stripe" <?php selected( $r['provider'], 'stripe' ); ?>>Stripe</option><option value="paypal" <?php selected( $r['provider'], 'paypal' ); ?>>PayPal</option></select></td>
						</tr>
					<?php endif; ?>
					<tr>
						<th><label for="gpj-amount">Amount</label></th>
						<td>
							<input type="text" name="jfb[amount]" id="gpj-amount" value="<?php echo esc_attr( $r['amount'] ); ?>" placeholder="9.00" class="small-text">
							<select name="jfb[currency]" aria-label="Currency"><?php foreach ( array_unique( array_merge( $cur, array( $r['currency'] ) ) ) as $c ) : ?><option value="<?php echo esc_attr( $c ); ?>" <?php selected( $r['currency'], $c ); ?>><?php echo esc_html( $c ); ?></option><?php endforeach; ?></select>
						</td>
					</tr>
					<tr>
						<th><label for="gpj-amount_field">Amount from a field</label></th>
						<td><?php $text( 'amount_field', 'field name — empty = Amount above' ); ?><p class="description">E.g. a hidden or calculated field "price".</p></td>
					</tr>
					<tr>
						<th><label for="gpj-period">Billing</label></th>
						<td><select name="jfb[period]" id="gpj-period"><option value="once" <?php selected( $r['period'], 'once' ); ?>>One-off payment</option><option value="month" <?php selected( $r['period'], 'month' ); ?>>Subscription — monthly</option><option value="year" <?php selected( $r['period'], 'year' ); ?>>Subscription — yearly</option></select></td>
					</tr>
					<tr>
						<th><label for="gpj-product">Product name</label></th>
						<td><input type="text" name="jfb[product]" id="gpj-product" value="<?php echo esc_attr( $r['product'] ); ?>" placeholder="Membership — empty = form name"><p class="description">Shown on the payment page; one product in Stripe for this form.</p></td>
					</tr>
					<tr>
						<th><label for="gpj-pay_title">Payment title</label></th>
						<td><input type="text" name="jfb[pay_title]" id="gpj-pay_title" value="<?php echo esc_attr( $r['pay_title'] ); ?>" placeholder="Membership — {name}"><p class="description">For the payments list: {field}, {form}, {site_name}.</p></td>
					</tr>
					<tr>
						<th><label for="gpj-email_field">Email field</label></th>
						<td><?php $text( 'email_field', 'email' ); ?></td>
					</tr>
					<tr>
						<th><label for="gpj-name_field">Name field</label></th>
						<td><?php $text( 'name_field', 'name' ); ?></td>
					</tr>
					<tr>
						<th><label for="gpj-success_url">After payment</label></th>
						<td>
							<input type="text" name="jfb[success_url]" id="gpj-success_url" value="<?php echo esc_attr( $r['success_url'] ); ?>" placeholder="Success URL — empty = form page"><br>
							<input type="text" name="jfb[cancel_url]" value="<?php echo esc_attr( $r['cancel_url'] ); ?>" placeholder="Cancel URL — empty = form page" aria-label="Cancel URL">
						</td>
					</tr>
					<tr>
						<th>Status</th>
						<td><label><input type="checkbox" name="jfb[enabled]" value="1" <?php checked( ! empty( $r['enabled'] ) ); ?>> Start the payment after the form is sent</label></td>
					</tr>
				</table>
			</div>
			<h2 class="gruppa-fields-title">Payment state</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="gpj-object">Payment belongs to</label></th>
						<td>
							<select name="jfb[object]" id="gpj-object">
								<option value="post" <?php selected( $r['object'], 'post' ); ?>>Post created by the form (Insert / Update Post)</option>
								<option value="user" <?php selected( $r['object'], 'user' ); ?>>Logged-in user</option>
								<option value="none" <?php selected( $r['object'], 'none' ); ?>>Nothing — payment only</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="gpj-status_field">Payment state meta</label></th>
						<td><input type="text" name="jfb[status_field]" id="gpj-status_field" value="<?php echo esc_attr( $r['status_field'] ); ?>" placeholder="payment_status — empty = none"><p class="description">Meta key of the post / user (also a JetEngine meta field).</p></td>
					</tr>
					<tr>
						<th><label for="gpj-paid_value">Value when paid</label></th>
						<td><input type="text" name="jfb[paid_value]" id="gpj-paid_value" value="<?php echo esc_attr( $r['paid_value'] ); ?>" placeholder="paid"></td>
					</tr>
					<tr>
						<th><label for="gpj-unpaid_value">Value when unpaid</label></th>
						<td><input type="text" name="jfb[unpaid_value]" id="gpj-unpaid_value" value="<?php echo esc_attr( $r['unpaid_value'] ); ?>" placeholder="blocked — empty = leave as is"></td>
					</tr>
					<tr>
						<th><label for="gpj-until_field">Paid until meta</label></th>
						<td><input type="text" name="jfb[until_field]" id="gpj-until_field" value="<?php echo esc_attr( $r['until_field'] ); ?>" placeholder="paid_until"></td>
					</tr>
					<tr>
						<th>Publish when paid</th>
						<td><label><input type="checkbox" name="jfb[publish]" value="1" <?php checked( ! empty( $r['publish'] ) ); ?>> Publish the post when it is paid</label></td>
					</tr>
				</table>
			</div>
			<p class="submit"><button type="submit" class="button button-primary"><?php echo $is_new ? 'Add' : 'Update'; ?></button></p>
		</form>
		<?php
	}
}
