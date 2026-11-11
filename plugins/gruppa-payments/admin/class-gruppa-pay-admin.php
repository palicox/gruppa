<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin: Gruppa Tools → Payments (tabs Payments, Subscriptions, Settings; detail of a payment).
 */
class Gruppa_Pay_Admin {

	const PAGE  = 'gruppa-payments';
	const ORDER = 50; // Gruppa Tools: Import/Export 10, Theme Builder 20, Table Builder 30, SEO Builder 40, Payments 50, Notify 60 (hub 1.5 fixes it).
	const CAP   = 'manage_options';
	const PER   = 30;

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'register' ), 5 );
		add_action( 'admin_post_gruppa_pay_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_gruppa_pay_admin_status', array( __CLASS__, 'manual_status' ) );
		add_action( 'admin_post_gruppa_pay_admin_cancel', array( __CLASS__, 'admin_cancel' ) );
		add_action( 'admin_post_gruppa_pay_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( GRUPPA_PAY_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function register() {
		if ( ! class_exists( 'Gruppa_Tools_Hub' ) ) {
			return;
		}
		Gruppa_Tools_Hub::add_module( array(
			'slug'     => self::PAGE,
			'title'    => 'Payments',
			'callback' => array( __CLASS__, 'render' ),
			'order'    => self::ORDER,
			'version'  => GRUPPA_PAY_VERSION,
		) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url( array( 'tab' => 'settings' ) ) ) . '">Settings</a>' );
		return $links;
	}

	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	public static function assets() {
		if ( ! isset( $_GET['page'] ) || self::PAGE !== $_GET['page'] ) { // phpcs:ignore
			return;
		}
		wp_register_style( 'gruppa-pay-admin', false, array( 'gruppa-admin' ), GRUPPA_PAY_VERSION );
		wp_enqueue_style( 'gruppa-pay-admin' );
		wp_add_inline_style( 'gruppa-pay-admin', '
			.gpa-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:16px 0}
			.gpa-stats .gruppa-card{margin:0}
			.gpa-stat{font-size:24px;font-weight:700;line-height:1.2;margin:4px 0 0}
			.gpa-badge{display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;background:#E5E7EB;color:#1B1340}
			.gpa-badge--paid,.gpa-badge--active{background:#d1fadf;color:#05603a}
			.gpa-badge--pending,.gpa-badge--past_due{background:#fef0c7;color:#93370d}
			.gpa-badge--failed,.gpa-badge--canceled,.gpa-badge--expired,.gpa-badge--refunded{background:#fee4e2;color:#912018}
			.gpa-mode{font-size:11px;text-transform:uppercase;color:#737795;margin-left:4px}
			.gpa-filters{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:12px 0}
			.gpa-dl{display:grid;grid-template-columns:200px 1fr;gap:8px 16px;margin:0}
			.gpa-dl dt{font-weight:600}.gpa-dl dd{margin:0;overflow-wrap:anywhere}
			.gpa-secret{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
			.gpa-events code{font-size:12px}
			.gpa-optional>summary{cursor:pointer;list-style:revert;margin:24px 0 8px}
		' );
	}

	/* =========================================================
	 * Render
	 * ======================================================= */

	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tabs = array(
			'payments'      => 'Payments',
			'subscriptions' => 'Subscriptions',
			'forms'         => 'Forms',
			'settings'      => 'Settings',
		);
		$tab  = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? sanitize_key( $_GET['tab'] ) : 'payments'; // phpcs:ignore
		$view = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0; // phpcs:ignore
		$mode = Gruppa_Payments::mode();
		?>
		<div class="wrap gruppa-wrap gruppa-payments">
			<h1 class="wp-heading-inline">Payments</h1>
			<span class="gpa-badge <?php echo 'live' === $mode ? 'gpa-badge--paid' : 'gpa-badge--pending'; ?>"><?php echo 'live' === $mode ? 'Live' : 'Test mode'; ?></span>
			<?php if ( in_array( $tab, array( 'payments', 'subscriptions' ), true ) && ! $view ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( self::filters( $tab ), array( 'action' => 'gruppa_pay_export', 'tab' => $tab ) ), admin_url( 'admin-post.php' ) ), 'gruppa_pay_export' ) ); ?>" class="page-title-action">Export CSV</a>
			<?php endif; ?>
			<hr class="wp-header-end">
			<?php self::notices(); ?>
			<?php if ( ! Gruppa_Payments::enabled() && 'settings' !== $tab ) : ?>
				<div class="notice notice-warning inline"><p>No payment gateway is set up for the <?php echo esc_html( $mode ); ?> mode. Fill in the Stripe or PayPal keys in <a href="<?php echo esc_url( self::url( array( 'tab' => 'settings' ) ) ); ?>">Settings</a>.</p></div>
			<?php endif; ?>

			<nav class="gruppa-tabs">
				<?php foreach ( $tabs as $k => $l ) : ?>
					<a href="<?php echo esc_url( self::url( array( 'tab' => $k ) ) ); ?>" class="<?php echo $k === $tab ? 'is-active' : ''; ?>"><?php echo esc_html( $l ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			if ( $view ) {
				self::render_detail( $view );
			} elseif ( 'forms' === $tab ) {
				Gruppa_Pay_JFB::render();
			} elseif ( 'settings' === $tab ) {
				self::render_settings();
			} else {
				self::render_list( $tab );
			}
			?>
		</div>
		<?php
	}

	private static function notices() {
		$msg = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : ''; // phpcs:ignore
		$err = (string) get_transient( 'gruppa_pay_admin_err' );
		$map = array(
			'saved'    => array( 'success', 'Saved.' ),
			'conn_ok'  => array( 'success', 'Saved. The connection works: ' . esc_html( isset( $_GET['ok'] ) ? sanitize_text_field( wp_unslash( $_GET['ok'] ) ) : '' ) . '.' ), // phpcs:ignore
			'conn_err' => array( 'error', 'Saved, but the connection test failed: ' . esc_html( $err ) ),
			'status'   => array( 'success', 'Status changed.' ),
			'deleted'  => array( 'success', 'Deleted.' ),
			'canceled' => array( 'success', 'The subscription was canceled.' ),
			'error'    => array( 'error', esc_html( $err ) ),
		);
		if ( isset( $map[ $msg ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $map[ $msg ][0] ), $map[ $msg ][1] ); // phpcs:ignore — escaped above.
		}
	}

	private static function badge( $status ) {
		return '<span class="gpa-badge gpa-badge--' . esc_attr( $status ) . '">' . esc_html( Gruppa_Payments::status_label( $status ) ) . '</span>';
	}

	/* ---------- Lists ---------- */

	/** Current filters from the query string. */
	private static function filters( $tab ) {
		$f = array();
		foreach ( array( 'status', 'provider', 'mode', 'month', 's' ) as $k ) {
			if ( isset( $_GET[ $k ] ) && '' !== $_GET[ $k ] ) { // phpcs:ignore
				$f[ $k ] = sanitize_text_field( wp_unslash( $_GET[ $k ] ) ); // phpcs:ignore
			}
		}
		if ( ! isset( $f['mode'] ) ) {
			$f['mode'] = Gruppa_Payments::mode();
		}
		return $f;
	}

	private static function where( $tab, $f ) {
		global $wpdb;
		$w = array( $wpdb->prepare( 'kind = %s', 'subscriptions' === $tab ? 'subscription' : 'payment' ) );
		if ( ! empty( $f['status'] ) ) {
			$w[] = $wpdb->prepare( 'status = %s', $f['status'] );
		}
		if ( ! empty( $f['provider'] ) ) {
			$w[] = $wpdb->prepare( 'provider = %s', $f['provider'] );
		}
		if ( ! empty( $f['mode'] ) && 'all' !== $f['mode'] ) {
			$w[] = $wpdb->prepare( 'mode = %s', $f['mode'] );
		}
		if ( ! empty( $f['month'] ) && preg_match( '/^\d{4}-\d{2}$/', $f['month'] ) ) {
			$w[] = $wpdb->prepare( 'created >= %s AND created < %s', $f['month'] . '-01', wp_date( 'Y-m-d', strtotime( $f['month'] . '-01 +1 month' ) ) );
		}
		if ( ! empty( $f['s'] ) ) {
			$like = '%' . $wpdb->esc_like( $f['s'] ) . '%';
			$w[]  = $wpdb->prepare( '(title LIKE %s OR email LIKE %s OR payment_ref LIKE %s OR subscription_ref LIKE %s OR uid = %s)', $like, $like, $like, $like, $f['s'] );
		}
		return implode( ' AND ', $w );
	}

	private static function stats() {
		global $wpdb;
		$t    = Gruppa_Pay_DB::payments();
		$mode = Gruppa_Payments::mode();
		$from = wp_date( 'Y-m-01' );
		$rev  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT currency, SUM(amount) total, COUNT(*) n FROM `{$t}` WHERE kind = 'payment' AND status = 'paid' AND mode = %s AND paid_at >= %s GROUP BY currency", $mode, $from ) ); // phpcs:ignore
		$act  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE kind = 'subscription' AND status = 'active' AND mode = %s", $mode ) ); // phpcs:ignore
		$due  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE kind = 'subscription' AND status = 'past_due' AND mode = %s", $mode ) ); // phpcs:ignore
		$sum  = array();
		$n    = 0;
		foreach ( $rev as $r ) {
			$sum[] = Gruppa_Payments::format( (int) $r->total, $r->currency );
			$n    += (int) $r->n;
		}
		?>
		<div class="gpa-stats">
			<div class="gruppa-card"><span class="gruppa-muted">Paid this month</span><p class="gpa-stat"><?php echo esc_html( $sum ? implode( ' · ', $sum ) : '0' ); ?></p><span class="gruppa-muted"><?php echo (int) $n; ?> payments</span></div>
			<div class="gruppa-card"><span class="gruppa-muted">Active subscriptions</span><p class="gpa-stat"><?php echo (int) $act; ?></p></div>
			<div class="gruppa-card"><span class="gruppa-muted">Past due</span><p class="gpa-stat"><?php echo (int) $due; ?></p></div>
		</div>
		<?php
	}

	private static function render_list( $tab ) {
		global $wpdb;
		$t     = Gruppa_Pay_DB::payments();
		$f     = self::filters( $tab );
		$where = self::where( $tab, $f );
		$paged = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 ); // phpcs:ignore
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}` WHERE {$where}" ); // phpcs:ignore
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id FROM `{$t}` WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", self::PER, ( $paged - 1 ) * self::PER ) ); // phpcs:ignore
		$sub   = 'subscriptions' === $tab;
		$sts   = $sub ? Gruppa_Payments::SUBSCRIPTION_STATUSES : Gruppa_Payments::PAYMENT_STATUSES;

		self::stats();
		?>
		<form method="get" class="gpa-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
			<select name="status"><option value="">All statuses</option>
				<?php foreach ( $sts as $s ) : ?><option value="<?php echo esc_attr( $s ); ?>" <?php selected( isset( $f['status'] ) ? $f['status'] : '', $s ); ?>><?php echo esc_html( Gruppa_Payments::status_label( $s ) ); ?></option><?php endforeach; ?>
			</select>
			<select name="provider"><option value="">All gateways</option>
				<?php foreach ( Gruppa_Payments::gateways() as $id => $g ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( isset( $f['provider'] ) ? $f['provider'] : '', $id ); ?>><?php echo esc_html( $g->label() ); ?></option><?php endforeach; ?>
			</select>
			<select name="mode">
				<?php foreach ( array( 'test' => 'Test', 'live' => 'Live', 'all' => 'Test + Live' ) as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f['mode'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
			</select>
			<input type="month" name="month" value="<?php echo esc_attr( isset( $f['month'] ) ? $f['month'] : '' ); ?>">
			<input type="search" name="s" value="<?php echo esc_attr( isset( $f['s'] ) ? $f['s'] : '' ); ?>" placeholder="Title, email, ID">
			<button type="submit" class="button">Filter</button>
		</form>

		<table class="wp-list-table widefat fixed striped gruppa-table">
			<thead><tr>
				<th style="width:150px">Date</th><th>Title</th><th style="width:150px">Amount</th><th style="width:110px">Gateway</th><th style="width:110px">Status</th>
				<?php if ( $sub ) : ?><th style="width:110px">Paid until</th><?php endif; ?>
				<th>Customer</th><th>Object</th>
			</tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="<?php echo $sub ? 8 : 7; ?>">No <?php echo esc_html( $sub ? 'subscriptions' : 'payments' ); ?> yet.</td></tr>
			<?php endif; ?>
			<?php
			foreach ( $rows as $r ) :
				$p   = Gruppa_Payments::get( $r->id );
				$obj = Gruppa_Pay_Objects::describe( $p );
				$g   = Gruppa_Payments::gateway( $p->provider );
				$cus = $p->user_id && ( $u = get_userdata( $p->user_id ) ) ? $u->display_name . ( $p->email ? ' · ' . $p->email : '' ) : $p->email; // phpcs:ignore
				?>
				<tr>
					<td><a href="<?php echo esc_url( self::url( array( 'tab' => $tab, 'view' => $p->id ) ) ); ?>"><?php echo esc_html( mysql2date( 'j. n. Y H:i', $p->created ) ); ?></a></td>
					<td><a href="<?php echo esc_url( self::url( array( 'tab' => $tab, 'view' => $p->id ) ) ); ?>"><strong><?php echo esc_html( $p->title ); ?></strong></a><?php echo $p->parent_id ? ' <span class="gruppa-muted">· subscription period</span>' : ''; ?></td>
					<td><?php echo esc_html( Gruppa_Payments::format( $p->amount, $p->currency ) . ( $sub ? ' / ' . Gruppa_Payments::period_label( $p->period ) : '' ) ); ?></td>
					<td><?php echo esc_html( $g ? $g->label() : $p->provider ); ?><span class="gpa-mode"><?php echo esc_html( $p->mode ); ?></span></td>
					<td><?php echo self::badge( $p->status ); // phpcs:ignore ?></td>
					<?php if ( $sub ) : ?><td><?php echo esc_html( $p->paid_until ? mysql2date( 'j. n. Y', $p->paid_until ) : '—' ); ?></td><?php endif; ?>
					<td><?php echo esc_html( $cus ? $cus : '—' ); ?></td>
					<td><?php echo $obj[1] ? '<a href="' . esc_url( $obj[1] ) . '">' . esc_html( $obj[0] ) . '</a>' : esc_html( $obj[0] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$pages = (int) ceil( $total / self::PER );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links( array( // phpcs:ignore
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $paged,
				'total'   => $pages,
			) ) . '</div></div>';
		}
	}

	/* ---------- Detail ---------- */

	private static function render_detail( $id ) {
		$p = Gruppa_Payments::get( $id );
		if ( ! $p ) {
			echo '<div class="notice notice-error inline"><p>Payment not found.</p></div>';
			return;
		}
		$g    = Gruppa_Payments::gateway( $p->provider );
		$obj  = Gruppa_Pay_Objects::describe( $p );
		$dash = $g ? $g->dashboard_url( $p ) : '';
		$tab  = 'subscription' === $p->kind ? 'subscriptions' : 'payments';
		$sts  = 'subscription' === $p->kind ? Gruppa_Payments::SUBSCRIPTION_STATUSES : Gruppa_Payments::PAYMENT_STATUSES;
		$rows = array(
			'Status'          => self::badge( $p->status ),
			'Title'           => esc_html( $p->title ),
			'Amount'          => esc_html( Gruppa_Payments::format( $p->amount, $p->currency ) . ' · ' . Gruppa_Payments::period_label( $p->period ) ),
			'Gateway'         => esc_html( ( $g ? $g->label() : $p->provider ) . ' · ' . $p->mode ) . ( $dash ? ' · <a href="' . esc_url( $dash ) . '" target="_blank" rel="noopener">Open in ' . esc_html( $g->label() ) . ' ↗</a>' : '' ),
			'Created'         => esc_html( mysql2date( 'j. n. Y H:i:s', $p->created ) ),
			'Paid'            => esc_html( $p->paid_at ? mysql2date( 'j. n. Y H:i:s', $p->paid_at ) : '—' ),
		);
		if ( 'subscription' === $p->kind ) {
			$rows['Paid until'] = esc_html( $p->paid_until ? mysql2date( 'j. n. Y', $p->paid_until ) : '—' ) . ( ! empty( $p->meta['cancel_at_period_end'] ) ? ' <span class="gruppa-muted">(ends at period end)</span>' : '' );
		}
		$rows['Customer'] = esc_html( trim( ( $p->user_id && ( $u = get_userdata( $p->user_id ) ) ? $u->display_name . ' (#' . $p->user_id . ') ' : '' ) . $p->email ) ) . ( $p->customer_ref ? ' <code>' . esc_html( $p->customer_ref ) . '</code>' : '' ); // phpcs:ignore
		$rows['Object']   = $obj[1] ? '<a href="' . esc_url( $obj[1] ) . '">' . esc_html( $obj[0] ) . '</a>' : esc_html( $obj[0] );
		if ( $p->form_id ) {
			$rows['Form'] = '#' . (int) $p->form_id;
		}
		if ( $p->parent_id ) {
			$rows['Subscription'] = '<a href="' . esc_url( self::url( array( 'tab' => 'subscriptions', 'view' => $p->parent_id ) ) ) . '">#' . (int) $p->parent_id . '</a>';
		}
		foreach ( array( 'uid' => 'Payment ID', 'checkout_ref' => 'Checkout', 'payment_ref' => 'Payment / invoice', 'subscription_ref' => 'Subscription ID', 'price_id' => 'Stripe price' ) as $k => $l ) {
			if ( '' !== (string) $p->$k ) {
				$rows[ $l ] = '<code>' . esc_html( $p->$k ) . '</code>';
			}
		}
		if ( ! empty( $p->meta['invoice_url'] ) ) {
			$rows['Invoice'] = '<a href="' . esc_url( $p->meta['invoice_url'] ) . '" target="_blank" rel="noopener">' . esc_html( ! empty( $p->meta['invoice_number'] ) ? $p->meta['invoice_number'] : 'Open' ) . ' ↗</a>';
		}
		if ( ! empty( $p->meta['error'] ) ) {
			$rows['Error'] = esc_html( $p->meta['error'] );
		}
		$sync = isset( $p->meta['sync'] ) ? Gruppa_Pay_Objects::clean_sync( $p->meta['sync'] ) : array();
		if ( ! empty( $sync['field'] ) || ! empty( $sync['until'] ) || ! empty( $sync['publish'] ) ) {
			$parts = array();
			if ( $sync['field'] ) {
				$parts[] = $sync['field'] . ' = ' . $sync['paid'] . ( '' !== $sync['unpaid'] ? ' / ' . $sync['unpaid'] : '' );
			}
			if ( $sync['until'] ) {
				$parts[] = $sync['until'] . ' = paid until';
			}
			if ( $sync['publish'] ) {
				$parts[] = 'publish when paid';
			}
			$rows['Writes to object'] = esc_html( implode( ' · ', $parts ) );
		}
		?>
		<p><a href="<?php echo esc_url( self::url( array( 'tab' => $tab ) ) ); ?>">← <?php echo 'subscription' === $p->kind ? 'Subscriptions' : 'Payments'; ?></a></p>
		<div class="gruppa-card">
			<h2 class="gruppa-fields-title"><?php echo esc_html( ( 'subscription' === $p->kind ? 'Subscription' : 'Payment' ) . ' #' . $p->id ); ?></h2>
			<dl class="gpa-dl">
				<?php foreach ( $rows as $l => $v ) : ?>
					<dt><?php echo esc_html( $l ); ?></dt><dd><?php echo $v; // phpcs:ignore — escaped above. ?></dd>
				<?php endforeach; ?>
			</dl>
		</div>

		<?php if ( 'subscription' === $p->kind ) : $kids = Gruppa_Payments::children( $p->id ); ?>
			<h2 class="gruppa-fields-title">Paid periods</h2>
			<table class="wp-list-table widefat fixed striped gruppa-table">
				<thead><tr><th>Date</th><th>Amount</th><th>Status</th><th>Reference</th></tr></thead>
				<tbody>
				<?php if ( ! $kids ) : ?><tr><td colspan="4">No paid period yet.</td></tr><?php endif; ?>
				<?php foreach ( $kids as $k ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( self::url( array( 'view' => $k->id ) ) ); ?>"><?php echo esc_html( mysql2date( 'j. n. Y H:i', $k->paid_at ? $k->paid_at : $k->created ) ); ?></a></td>
						<td><?php echo esc_html( Gruppa_Payments::format( $k->amount, $k->currency ) ); ?></td>
						<td><?php echo self::badge( $k->status ); // phpcs:ignore ?></td>
						<td><code><?php echo esc_html( $k->payment_ref ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2 class="gruppa-fields-title">Actions</h2>
		<div class="gruppa-card">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gpa-filters">
				<input type="hidden" name="action" value="gruppa_pay_admin_status">
				<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
				<?php wp_nonce_field( 'gruppa_pay_admin_' . $p->id ); ?>
				<label>Set status manually
					<select name="status"><?php foreach ( $sts as $s ) : ?><option value="<?php echo esc_attr( $s ); ?>" <?php selected( $p->status, $s ); ?>><?php echo esc_html( Gruppa_Payments::status_label( $s ) ); ?></option><?php endforeach; ?></select>
				</label>
				<?php if ( 'subscription' === $p->kind ) : ?>
					<label>Paid until <input type="date" name="paid_until" value="<?php echo esc_attr( (string) $p->paid_until ); ?>"></label>
				<?php endif; ?>
				<button type="submit" class="button">Save</button>
				<span class="gruppa-muted">Changes only this record and the linked object — nothing is sent to <?php echo esc_html( $g ? $g->label() : 'the gateway' ); ?>. Use it e.g. for a bank transfer.</span>
			</form>
			<?php if ( 'subscription' === $p->kind && in_array( $p->status, array( 'active', 'past_due' ), true ) && $p->subscription_ref ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Cancel the subscription now in <?php echo esc_js( $g ? $g->label() : '' ); ?>?');">
					<input type="hidden" name="action" value="gruppa_pay_admin_cancel">
					<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
					<?php wp_nonce_field( 'gruppa_pay_admin_' . $p->id ); ?>
					<button type="submit" class="button button-link-delete">Cancel subscription in <?php echo esc_html( $g ? $g->label() : '' ); ?></button>
				</form>
			<?php endif; ?>
		</div>

		<?php $events = Gruppa_Pay_Webhooks::for_payment( $p->id ); ?>
		<h2 class="gruppa-fields-title">Webhook log</h2>
		<table class="wp-list-table widefat fixed striped gruppa-table gpa-events">
			<thead><tr><th style="width:160px">Received</th><th>Event</th><th>Result</th></tr></thead>
			<tbody>
			<?php if ( ! $events ) : ?><tr><td colspan="3">No webhook for this record yet.</td></tr><?php endif; ?>
			<?php foreach ( $events as $e ) : ?>
				<tr><td><?php echo esc_html( mysql2date( 'j. n. Y H:i:s', $e->created ) ); ?></td><td><code><?php echo esc_html( $e->type ); ?></code></td><td><?php echo esc_html( $e->result ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/* ---------- Settings ---------- */

	private static function secret_field( $key, $value, $ph ) {
		$set = '' !== $value;
		printf(
			'<div class="gpa-secret"><input type="password" name="%1$s" value="" autocomplete="new-password" placeholder="%2$s" class="regular-text"> %3$s</div>',
			esc_attr( $key ),
			esc_attr( $set ? 'saved — leave empty to keep, "-" to delete' : $ph ),
			$set ? '<code>' . esc_html( substr( $value, 0, 7 ) . '…' . substr( $value, -4 ) ) . '</code>' : '<span class="gruppa-muted">not set</span>' // phpcs:ignore
		);
	}

	private static function render_settings() {
		$s     = Gruppa_Payments::settings();
		$pages = get_pages( array( 'post_status' => 'publish' ) );
		$page  = function ( $name, $cur ) use ( $pages ) {
			echo '<select name="' . esc_attr( $name ) . '"><option value="0">— page of the form / button —</option>';
			foreach ( $pages as $pg ) {
				echo '<option value="' . (int) $pg->ID . '" ' . selected( (int) $cur, (int) $pg->ID, false ) . '>' . esc_html( $pg->post_title ) . '</option>';
			}
			echo '</select>';
		};
		$last = function ( $provider ) {
			$e = Gruppa_Pay_Webhooks::last( $provider );
			return $e ? 'Last webhook: ' . mysql2date( 'j. n. Y H:i', $e->created ) . ' · ' . $e->type : 'No webhook received yet.';
		};
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gruppa_pay_settings">
			<?php wp_nonce_field( 'gruppa_pay_settings' ); ?>

			<h2 class="gruppa-fields-title">General</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr><th>Mode</th><td>
						<select name="mode"><option value="test" <?php selected( $s['mode'], 'test' ); ?>>Test (Stripe test keys)</option><option value="live" <?php selected( $s['mode'], 'live' ); ?>>Live</option></select>
						<p class="description">Test and live payments are stored apart (column mode); the lists show the current mode.</p>
					</td></tr>
					<tr><th>Default currency</th><td>
						<select name="currency"><?php foreach ( Gruppa_Payments::currencies() as $c ) : ?><option value="<?php echo esc_attr( $c ); ?>" <?php selected( $s['currency'], $c ); ?>><?php echo esc_html( $c ); ?></option><?php endforeach; ?></select>
					</td></tr>
					<?php if ( count( Gruppa_Payments::ready_gateways() ) > 1 ) : ?>
					<tr><th>Default gateway</th><td>
						<select name="default_provider"><?php foreach ( Gruppa_Payments::gateways() as $id => $g ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( $s['default_provider'], $id ); ?>><?php echo esc_html( $g->label() ); ?></option><?php endforeach; ?></select>
						<p class="description">Used when a form action or the API does not choose one. [gruppa_pay] without provider shows a button for every set-up gateway.</p>
					</td></tr>
					<?php endif; ?>
					<tr><th>Success page</th><td><?php $page( 'success_page', $s['success_page'] ); ?></td></tr>
					<tr><th>Cancel page</th><td><?php $page( 'cancel_page', $s['cancel_page'] ); ?>
						<p class="description">Defaults when the form action / button has no URL of its own. The result message appears on the page automatically ([gruppa_pay_status] places it elsewhere).</p></td></tr>
					<tr><th>Brand name</th><td><input type="text" name="brand_name" class="regular-text" value="<?php echo esc_attr( $s['brand_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"><p class="description">Shown on the PayPal page. Stripe uses Settings → Branding in the Stripe dashboard.</p></td></tr>
					<tr><th>Grace period</th><td><input type="number" name="grace_days" min="0" max="60" value="<?php echo (int) $s['grace_days']; ?>" style="width:6rem"> days
						<p class="description">A subscription expires this many days after "paid until" when no payment came (daily check at 3:20).</p></td></tr>
					<tr><th>Pending payments expire after</th><td><input type="number" name="pending_hours" min="1" max="720" value="<?php echo (int) $s['pending_hours']; ?>" style="width:6rem"> hours</td></tr>
				</table>
			</div>

			<h2 class="gruppa-fields-title">Stripe</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr><th>Secret key — test</th><td><?php self::secret_field( 'stripe_secret_test', $s['stripe_secret_test'], 'sk_test_… or rk_test_…' ); ?></td></tr>
					<tr><th>Webhook secret — test</th><td><?php self::secret_field( 'stripe_webhook_test', $s['stripe_webhook_test'], 'whsec_…' ); ?></td></tr>
					<tr><th>Secret key — live</th><td><?php self::secret_field( 'stripe_secret_live', $s['stripe_secret_live'], 'sk_live_… or rk_live_…' ); ?></td></tr>
					<tr><th>Webhook secret — live</th><td><?php self::secret_field( 'stripe_webhook_live', $s['stripe_webhook_live'], 'whsec_…' ); ?></td></tr>
					<tr><th>Webhook URL</th><td><code><?php echo esc_html( Gruppa_Pay_Webhooks::url( 'stripe' ) ); ?></code>
						<p class="description">Stripe → Developers → Webhooks → Add endpoint with these events:<br><code><?php echo esc_html( implode( ', ', Gruppa_Pay_Stripe::events() ) ); ?></code><br><?php echo esc_html( $last( 'stripe' ) ); ?></p></td></tr>
				</table>
			</div>

			<?php $pp_on = '' !== $s['paypal_client_test'] . $s['paypal_client_live']; ?>
			<details class="gpa-optional"<?php echo $pp_on ? ' open' : ''; ?>>
			<summary class="gruppa-fields-title">PayPal (optional)</summary>
			<p class="gruppa-muted">Leave empty to use Stripe only — PayPal is then offered nowhere. PayPal always needs an amount from the website.</p>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr><th>Client ID — sandbox</th><td><input type="text" name="paypal_client_test" class="large-text" value="<?php echo esc_attr( $s['paypal_client_test'] ); ?>" autocomplete="off"></td></tr>
					<tr><th>Secret — sandbox</th><td><?php self::secret_field( 'paypal_secret_test', $s['paypal_secret_test'], 'Secret of the sandbox app' ); ?></td></tr>
					<tr><th>Webhook ID — sandbox</th><td><input type="text" name="paypal_webhook_test" class="regular-text" value="<?php echo esc_attr( $s['paypal_webhook_test'] ); ?>" autocomplete="off"></td></tr>
					<tr><th>Client ID — live</th><td><input type="text" name="paypal_client_live" class="large-text" value="<?php echo esc_attr( $s['paypal_client_live'] ); ?>" autocomplete="off"></td></tr>
					<tr><th>Secret — live</th><td><?php self::secret_field( 'paypal_secret_live', $s['paypal_secret_live'], 'Secret of the live app' ); ?></td></tr>
					<tr><th>Webhook ID — live</th><td><input type="text" name="paypal_webhook_live" class="regular-text" value="<?php echo esc_attr( $s['paypal_webhook_live'] ); ?>" autocomplete="off"></td></tr>
					<tr><th>Webhook URL</th><td><code><?php echo esc_html( Gruppa_Pay_Webhooks::url( 'paypal' ) ); ?></code>
						<p class="description">developer.paypal.com → Apps &amp; Credentials → your app → Webhooks → Add webhook with these events, then copy its Webhook ID above:<br><code><?php echo esc_html( implode( ', ', Gruppa_Pay_PayPal::events() ) ); ?></code><br>One-off payments are confirmed on return even without a webhook; subscription renewals need it. <?php echo esc_html( $last( 'paypal' ) ); ?></p></td></tr>
				</table>
			</div>
			</details>

			<p>
				<button type="submit" class="button button-primary">Save</button>
				<button type="submit" name="test_connection" value="1" class="button">Save and test connection</button>
			</p>
		</form>

		<h2 class="gruppa-fields-title">Use</h2>
		<div class="gruppa-card">
			<dl class="gpa-dl">
				<dt>Form Builder</dt><dd>Gruppa CMS → Form Builder → form → Actions → <strong>Payment</strong>: amount, currency, one-off or subscription, field for the payment state, "paid until", publish when paid.</dd>
				<dt>Button</dt><dd><code>[gruppa_pay amount="19.90" title="Membership"]</code> · subscription <code>period="month"</code> · Stripe price <code>price="price_…"</code> · one gateway <code>provider="paypal"</code></dd>
				<dt>Result</dt><dd><code>[gruppa_pay_status]</code> · subscriptions of the user <code>[gruppa_pay_subscriptions]</code></dd>
				<dt>PHP</dt><dd><code>Gruppa_Payments::checkout( [ 'amount' =&gt; 19.9, 'period' =&gt; 'month', 'object_type' =&gt; 'gruppa:webapps', 'object_id' =&gt; 12 ] )</code> · <code>Gruppa_Payments::is_paid( 'gruppa:webapps', 12 )</code> · hook <code>gruppa_payments/status_changed</code></dd>
			</dl>
		</div>
		<?php
	}

	/* =========================================================
	 * Handlers
	 * ======================================================= */

	private static function guard( $nonce ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden', '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce );
	}

	public static function save_settings() {
		self::guard( 'gruppa_pay_settings' );
		$old = Gruppa_Payments::settings();
		$new = array();
		$in  = function ( $k ) {
			return isset( $_POST[ $k ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) ) : ''; // phpcs:ignore
		};
		$new['mode']             = 'live' === $in( 'mode' ) ? 'live' : 'test';
		$new['currency']         = in_array( $in( 'currency' ), Gruppa_Payments::currencies(), true ) ? $in( 'currency' ) : 'EUR';
		$new['default_provider'] = isset( Gruppa_Payments::gateways()[ $in( 'default_provider' ) ] ) ? $in( 'default_provider' ) : 'stripe';
		$new['success_page']     = (int) $in( 'success_page' );
		$new['cancel_page']      = (int) $in( 'cancel_page' );
		$new['brand_name']       = $in( 'brand_name' );
		$new['grace_days']       = max( 0, min( 60, (int) $in( 'grace_days' ) ) );
		$new['pending_hours']    = max( 1, min( 720, (int) $in( 'pending_hours' ) ) );
		foreach ( array( 'paypal_client_test', 'paypal_webhook_test', 'paypal_client_live', 'paypal_webhook_live' ) as $k ) {
			$new[ $k ] = $in( $k );
		}
		foreach ( Gruppa_Payments::secret_keys() as $k ) {
			$v         = $in( $k );
			$new[ $k ] = '-' === $v ? '' : ( '' === $v ? $old[ $k ] : $v ); // Empty = keep (never printed back).
		}
		update_option( Gruppa_Payments::OPTION, $new, false );

		$args = array( 'tab' => 'settings', 'msg' => 'saved' );
		if ( ! empty( $_POST['test_connection'] ) ) { // phpcs:ignore
			$ok   = array();
			$errs = array();
			foreach ( Gruppa_Payments::gateways() as $g ) {
				if ( ! $g->ready() ) {
					continue;
				}
				$r = $g->test();
				if ( is_wp_error( $r ) ) {
					$errs[] = $r->get_error_message();
				} else {
					$ok[] = $g->label() . ' (' . $new['mode'] . ')';
				}
			}
			if ( $errs ) {
				set_transient( 'gruppa_pay_admin_err', implode( ' · ', $errs ), 60 );
				$args['msg'] = 'conn_err';
			} elseif ( $ok ) {
				$args['msg'] = 'conn_ok';
				$args['ok']  = implode( ', ', $ok );
			} else {
				set_transient( 'gruppa_pay_admin_err', 'no gateway has keys for the ' . $new['mode'] . ' mode', 60 );
				$args['msg'] = 'conn_err';
			}
		}
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	public static function manual_status() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore
		self::guard( 'gruppa_pay_admin_' . $id );
		$p = Gruppa_Payments::get( $id );
		if ( ! $p ) {
			wp_die( 'Not found' );
		}
		$sts    = 'subscription' === $p->kind ? Gruppa_Payments::SUBSCRIPTION_STATUSES : Gruppa_Payments::PAYMENT_STATUSES;
		$status = isset( $_POST['status'] ) && in_array( $_POST['status'], $sts, true ) ? sanitize_key( $_POST['status'] ) : $p->status; // phpcs:ignore
		$data   = array( 'meta' => array( 'manual' => wp_get_current_user()->user_login . ' ' . current_time( 'mysql' ) ) );
		if ( isset( $_POST['paid_until'] ) && 'subscription' === $p->kind ) { // phpcs:ignore
			$d                  = sanitize_text_field( wp_unslash( $_POST['paid_until'] ) ); // phpcs:ignore
			$data['paid_until'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : null;
		}
		Gruppa_Payments::set_status( $p, $status, $data );
		wp_safe_redirect( self::url( array( 'view' => $id, 'msg' => 'status' ) ) );
		exit;
	}

	public static function admin_cancel() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore
		self::guard( 'gruppa_pay_admin_' . $id );
		$p = Gruppa_Payments::get( $id );
		$r = $p ? Gruppa_Payments::cancel( $p ) : new WP_Error( 'x', 'Not found' );
		if ( is_wp_error( $r ) ) {
			set_transient( 'gruppa_pay_admin_err', $r->get_error_message(), 60 );
		}
		wp_safe_redirect( self::url( array( 'view' => $id, 'msg' => is_wp_error( $r ) ? 'error' : 'canceled' ) ) );
		exit;
	}

	public static function export() {
		self::guard( 'gruppa_pay_export' );
		global $wpdb;
		$tab   = isset( $_GET['tab'] ) && 'subscriptions' === $_GET['tab'] ? 'subscriptions' : 'payments'; // phpcs:ignore
		$where = self::where( $tab, self::filters( $tab ) );
		$rows  = (array) $wpdb->get_results( 'SELECT * FROM `' . Gruppa_Pay_DB::payments() . "` WHERE {$where} ORDER BY id DESC", ARRAY_A ); // phpcs:ignore
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=gruppa-' . $tab . '-' . wp_date( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore — UTF-8 BOM for Excel.
		$cols = array( 'id', 'parent_id', 'created', 'paid_at', 'paid_until', 'status', 'kind', 'period', 'title', 'amount', 'currency', 'provider', 'mode', 'email', 'user_id', 'object_type', 'object_id', 'form_id', 'payment_ref', 'subscription_ref', 'customer_ref', 'uid' );
		fputcsv( $out, $cols, ';' );
		foreach ( $rows as $r ) {
			$r['amount'] = Gruppa_Payments::to_major( $r['amount'], $r['currency'] );
			$line        = array();
			foreach ( $cols as $c ) {
				$v      = (string) $r[ $c ];
				$line[] = preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v; // No formulas in Excel.
			}
			fputcsv( $out, $line, ';' );
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}
}
