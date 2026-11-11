<?php
/**
 * Gruppa Notify — native Web Push notifications for JetEngine CCT (Gruppa Tools add-on).
 *
 * Options, tables, REST route and service worker keep the names used by the former
 * Gruppa Notify plugin and the Notify tab of Gruppa Tools 3.x — existing settings,
 * VAPID keys and registered devices keep working.
 */

defined( 'ABSPATH' ) || exit;

final class Gruppa_Notify_Module {

	const DB_VERSION   = '3';
	const OPT_DB       = 'gruppa_notify_db_version';
	const OPT_SETTINGS = 'gruppa_notify_settings';
	const OPT_VAPID    = 'gruppa_notify_vapid';
	const OPT_LAST_ID  = 'gruppa_notify_last_id';
	const CRON_HOOK    = 'gruppa_notify_sweep';
	const PAGE         = 'gruppa-notify';
	const ORDER        = 60; // Gruppa Tools: … Payments 50, Notify 60 (hub 1.5 fixes it).

	/** CCT columns not offered as notification fields. */
	const SYSTEM = array( '_ID', 'cct_status', 'cct_author_id', 'cct_created', 'cct_modified', 'cct_single_post_id' );

	/** System columns of a Gruppa CMS entity table that are not offered as fields ('author' is). */
	const GRUPPA_SYSTEM = array( 'id', 'title', 'slug', 'created', 'modified', 'status', 'order_index', 'wp_post_id', 'group_id', 'group_title', 'group_slug' );

	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::CRON_HOOK, array( $this, 'sweep' ) );

		add_action( 'init', array( $this, 'serve_virtual_files' ), 1 );
		add_action( 'init', array( $this, 'register_cct_hook' ), 20 );
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_head', array( $this, 'manifest_link' ) );
		add_shortcode( 'gruppa_notify_button', array( $this, 'shortcode_button' ) );

		add_action( 'wp_ajax_gruppa_notify_read', array( $this, 'ajax_mark_read' ) );
		add_action( 'wp_ajax_nopriv_gruppa_notify_read', array( $this, 'ajax_mark_read' ) );

		if ( is_admin() ) {
			Gruppa_Tools_Hub::add_module(
				array(
					'slug'     => self::PAGE,
					'title'    => 'Notify',
					'callback' => array( $this, 'render' ),
					'order'    => self::ORDER,
					'version'  => GRUPPA_NOTIFY_VERSION,
				)
			);
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( GRUPPA_NOTIFY_FILE ), array( $this, 'action_links' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_post_gruppa_notify_test', array( $this, 'admin_test' ) );
			add_action( 'admin_post_gruppa_notify_regen', array( $this, 'admin_regen' ) );
			add_action( 'admin_post_gruppa_notify_clear_log', array( $this, 'admin_clear_log' ) );
		}
	}

	/* =========================================================
	 * Settings
	 * ======================================================= */

	public static function defaults() {
		return array(
			'cct'       => '',
			'f_user'    => 'user_id',
			'f_message' => 'notify',
			'f_url'     => 'post_url',
			'f_read'    => 'is_read',
			'title'     => '',
			'icon'      => '',
			'manifest'  => 1,
			'app_name'  => '',
		);
	}

	public static function settings() {
		return wp_parse_args( get_option( self::OPT_SETTINGS, array() ), self::defaults() );
	}

	public static function get( $key ) {
		$s = self::settings();
		return isset( $s[ $key ] ) ? $s[ $key ] : null;
	}

	private static function vapid() {
		$v = get_option( self::OPT_VAPID );
		if ( empty( $v['private_pem'] ) || empty( $v['public'] ) ) {
			$v = Gruppa_Notify_WebPush::generate_vapid();
			if ( $v ) {
				update_option( self::OPT_VAPID, $v, false );
			}
		}
		return $v;
	}

	private function subject() {
		return 'mailto:' . get_option( 'admin_email' );
	}

	/* =========================================================
	 * Install, DB, cron
	 * ======================================================= */

	public static function subs_table() {
		global $wpdb;
		return $wpdb->prefix . 'gcms_notify_subs';
	}

	/** 1.3.0: every sent notification, one row per recipient. */
	public static function log_table() {
		global $wpdb;
		return $wpdb->prefix . 'gcms_notify_log';
	}

	/* ---------- Source (1.2.0): JetEngine CCT or Gruppa CMS entity ---------- */

	/**
	 * Parse a source value: "jet:slug", "gruppa:slug" or a bare slug (= JetEngine CCT, before 1.2.0).
	 *
	 * @return array [ origin, slug ]
	 */
	public static function parse_source( $value ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return array( '', '' ); // 1.3.0: no source — only Form Builder actions and the API send.
		}
		if ( 0 === strpos( $value, 'gruppa:' ) ) {
			return array( 'gruppa', sanitize_key( substr( $value, 7 ) ) );
		}
		if ( 0 === strpos( $value, 'jet:' ) ) {
			$value = substr( $value, 4 );
		}
		return array( 'jet', sanitize_key( $value ) );
	}

	public static function source() {
		return self::parse_source( self::get( 'cct' ) );
	}

	public static function table_for( $value ) {
		global $wpdb;
		list( $origin, $slug ) = self::parse_source( $value );
		if ( '' === $slug ) {
			return '';
		}
		if ( 'gruppa' === $origin ) {
			return class_exists( 'Gruppa_DB' ) ? Gruppa_DB::data_table( $slug ) : $wpdb->prefix . 'gcms_type_' . $slug;
		}
		return $wpdb->prefix . 'jet_cct_' . $slug;
	}

	public static function id_col_for( $value ) {
		list( $origin ) = self::parse_source( $value );
		return 'gruppa' === $origin ? 'id' : '_ID';
	}

	public static function cct_table() {
		return self::table_for( self::get( 'cct' ) );
	}

	private static function id_col() {
		return self::id_col_for( self::get( 'cct' ) );
	}

	/** Highest item ID of a source — new items above it get a push. */
	public static function max_id_for( $value ) {
		global $wpdb;
		$table = self::table_for( $value );
		if ( ! $table || $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return 0;
		}
		return (int) $wpdb->get_var( 'SELECT MAX(`' . self::id_col_for( $value ) . '`) FROM `' . $table . '`' ); // phpcs:ignore
	}

	/** Runs on activation and automatically after switching from Gruppa Notify / Gruppa Tools 3.x. */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::subs_table();

		// 1.1.0: wp_gruppa_notify_subs → wp_gcms_notify_subs (system prefix of Gruppa CMS 0.28).
		$old = $wpdb->prefix . 'gruppa_notify_subs';
		if ( $old === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $old ) ) ) && $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			$wpdb->query( "RENAME TABLE `{$old}` TO `{$table}`" ); // phpcs:ignore
		}
		delete_option( 'gruppa_tools_notify_db' );

		dbDelta( "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			endpoint TEXT NOT NULL,
			endpoint_hash CHAR(64) NOT NULL,
			p256dh VARCHAR(255) NOT NULL,
			auth VARCHAR(255) NOT NULL,
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			created DATETIME NOT NULL,
			last_used DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY endpoint_hash (endpoint_hash),
			KEY user_id (user_id)
		) {$charset};" );

		$log = self::log_table();
		dbDelta( "CREATE TABLE {$log} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			body TEXT NOT NULL,
			url TEXT NOT NULL,
			source VARCHAR(40) NOT NULL DEFAULT '',
			source_label VARCHAR(190) NOT NULL DEFAULT '',
			ref_type VARCHAR(120) NOT NULL DEFAULT '',
			ref_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			sent INT UNSIGNED NOT NULL DEFAULT 0,
			failed INT UNSIGNED NOT NULL DEFAULT 0,
			is_read TINYINT(1) NOT NULL DEFAULT 0,
			created DATETIME NOT NULL,
			read_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY created (created)
		) {$charset};" );

		// Before 1.3.0 the default source was the JetEngine CCT "notify": keep it where it exists and was never saved.
		if ( false === get_option( self::OPT_SETTINGS, false ) ) {
			$jet = $wpdb->prefix . 'jet_cct_notify';
			if ( $jet === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $jet ) ) ) ) {
				update_option( self::OPT_SETTINGS, array_merge( self::defaults(), array( 'cct' => 'notify' ) ) );
			}
		}

		self::vapid();

		// Do not send old CCT items — start at the current maximum.
		if ( false === get_option( self::OPT_LAST_ID, false ) ) {
			update_option( self::OPT_LAST_ID, self::cct_max_id(), false );
		}

		self::schedule();
		update_option( self::OPT_DB, self::DB_VERSION, false );
	}

	public static function maybe_install() {
		if ( self::DB_VERSION !== get_option( self::OPT_DB ) ) {
			self::install();
		}
		self::schedule(); // Deactivating the old plugin clears the cron — restore it.
	}

	private static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
			wp_schedule_event( time() + 300, 'gn_five_minutes', self::CRON_HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public static function cron_schedules( $s ) {
		$s['gn_five_minutes'] = array(
			'interval' => 300,
			'display'  => 'Every 5 minutes',
		);
		return $s;
	}

	private static function cct_exists() {
		global $wpdb;
		$t = self::cct_table();
		return $t && $t === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) );
	}

	private static function cct_max_id() {
		return self::max_id_for( self::get( 'cct' ) );
	}

	/* =========================================================
	 * Send on a new CCT item
	 * ======================================================= */

	public function register_cct_hook() {
		list( $origin, $slug ) = self::source();
		if ( '' === $slug ) {
			return;
		}
		if ( 'gruppa' === $origin ) {
			add_action( 'gruppa_cms/item_created', array( $this, 'on_gruppa_created' ), 20, 2 ); // Gruppa CMS 0.29.4+.
			return;
		}
		add_action( 'jet-engine/custom-content-types/created-item/' . $slug, array( $this, 'on_created' ), 10, 2 );
	}

	public function on_gruppa_created( $type, $item_id ) {
		list( , $slug ) = self::source();
		if ( $type && isset( $type->slug ) && $type->slug === $slug ) {
			$this->process_item( (int) $item_id );
		}
	}

	public function on_created( $item, $item_id ) {
		$item_id = (int) $item_id;
		if ( $item_id ) {
			$this->process_item( $item_id );
		}
	}

	/** Sends the push for the item with this ID (unless already sent). */
	private function process_item( $item_id ) {
		global $wpdb;

		$last = (int) get_option( self::OPT_LAST_ID, 0 );
		if ( $item_id <= $last ) {
			return;
		}
		update_option( self::OPT_LAST_ID, $item_id, false );

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . self::cct_table() . '` WHERE `' . self::id_col() . '` = %d', $item_id ), ARRAY_A ); // phpcs:ignore
		if ( ! $row ) {
			return;
		}

		$read = isset( $row[ self::get( 'f_read' ) ] ) ? $row[ self::get( 'f_read' ) ] : 0;
		if ( in_array( (string) $read, array( '1', 'true' ), true ) ) {
			return;
		}

		$user_id = (int) ( isset( $row[ self::get( 'f_user' ) ] ) ? $row[ self::get( 'f_user' ) ] : 0 );
		if ( ! $user_id ) {
			return;
		}

		$msg = isset( $row[ self::get( 'f_message' ) ] ) ? (string) $row[ self::get( 'f_message' ) ] : '';
		$url = isset( $row[ self::get( 'f_url' ) ] ) ? esc_url_raw( $row[ self::get( 'f_url' ) ] ) : '';

		self::notify(
			array( $user_id ),
			array(
				'body'     => $msg,
				'url'      => $url,
				'source'   => 'entity',
				'label'    => self::source_name(),
				'ref_type' => self::get( 'cct' ),
				'ref_id'   => $item_id,
			)
		);
	}

	/** Safety net: sends items the hook did not fire for. */
	public function sweep() {
		global $wpdb;
		if ( ! self::cct_exists() ) {
			return;
		}
		$last = (int) get_option( self::OPT_LAST_ID, 0 );
		$col  = self::id_col();
		$ids  = $wpdb->get_col( $wpdb->prepare( 'SELECT `' . $col . '` FROM `' . self::cct_table() . '` WHERE `' . $col . '` > %d ORDER BY `' . $col . '` ASC LIMIT 50', $last ) ); // phpcs:ignore
		foreach ( $ids as $id ) {
			$this->process_item( (int) $id );
		}
	}

	/** Sends the payload to all devices of the user. Returns [ sent, failed ]. */
	public function send_to_user( $user_id, array $data ) {
		global $wpdb;

		$vapid = self::vapid();
		if ( ! $vapid ) {
			return array( 0, 0 );
		}

		$data['icon'] = self::get( 'icon' ) ? self::get( 'icon' ) : get_site_icon_url( 192 );
		$payload      = wp_json_encode( $data );

		$subs = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::subs_table() . ' WHERE user_id = %d', $user_id ), ARRAY_A ); // phpcs:ignore

		$sent = 0;
		$fail = 0;
		foreach ( (array) $subs as $sub ) {
			$code = Gruppa_Notify_WebPush::send( $sub, $payload, $vapid, $this->subject() );
			if ( $code >= 200 && $code < 300 ) {
				$sent++;
				$wpdb->update( self::subs_table(), array( 'last_used' => current_time( 'mysql' ) ), array( 'id' => $sub['id'] ) );
			} else {
				$fail++;
				if ( in_array( $code, array( 404, 410 ), true ) ) {
					$wpdb->delete( self::subs_table(), array( 'id' => $sub['id'] ) );
				}
			}
		}
		return array( $sent, $fail );
	}


	/* =========================================================
	 * Public API (1.3.0)
	 * ======================================================= */

	/**
	 * Send a notification to users and write it to the log (one row per user).
	 *
	 * Usage from other plugins: gruppa_notify( array( 1, 5 ), array( 'body' => 'New request', 'url' => '…' ) );
	 *
	 * @param int[] $user_ids
	 * @param array $args title, body, url, source (form|entity|api|test), label, ref_type, ref_id
	 * @return array[] [ [ user_id, log_id, sent, failed ] ]
	 */
	public static function notify( $user_ids, array $args ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'title'    => '',
				'body'     => '',
				'url'      => '',
				'source'   => 'api',
				'label'    => '',
				'ref_type' => '',
				'ref_id'   => 0,
			)
		);

		$title = sanitize_text_field( '' !== $args['title'] ? $args['title'] : ( self::get( 'title' ) ? self::get( 'title' ) : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) );
		$body  = trim( wp_strip_all_tags( (string) $args['body'] ) );
		$url   = esc_url_raw( (string) $args['url'] );
		$url   = $url ? $url : home_url( '/' );

		$out = array();
		foreach ( array_unique( array_filter( array_map( 'intval', (array) $user_ids ) ) ) as $uid ) {
			$wpdb->insert(
				self::log_table(),
				array(
					'user_id'      => $uid,
					'title'        => mb_substr( $title, 0, 255 ),
					'body'         => $body,
					'url'          => $url,
					'source'       => sanitize_key( $args['source'] ),
					'source_label' => mb_substr( sanitize_text_field( $args['label'] ), 0, 190 ),
					'ref_type'     => mb_substr( sanitize_text_field( $args['ref_type'] ), 0, 120 ),
					'ref_id'       => (int) $args['ref_id'],
					'created'      => current_time( 'mysql' ),
				)
			);
			$log_id = (int) $wpdb->insert_id;

			list( $sent, $fail ) = self::instance()->send_to_user(
				$uid,
				array(
					'title' => $title,
					'body'  => $body,
					'url'   => $url,
					'id'    => 'l' . $log_id,
					'l'     => $log_id,
					'lt'    => self::log_token( $log_id ),
				)
			);
			if ( $log_id ) {
				$wpdb->update( self::log_table(), array( 'sent' => $sent, 'failed' => $fail ), array( 'id' => $log_id ) );
			}
			$out[] = array( $uid, $log_id, $sent, $fail );
		}
		do_action( 'gruppa_notify/sent', $out, $args );
		return $out;
	}

	/** Edit screen of a Gruppa CMS item (same as Gruppa_Admin_Items::page_slug()). */
	public static function item_admin_url( $slug, $item_id ) {
		return admin_url( 'admin.php?page=gruppa-item-' . sanitize_key( $slug ) . '&view=edit&id=' . (int) $item_id );
	}

	/** Users with registered devices: user_id => device count. */
	public static function device_counts() {
		global $wpdb;
		$out = array();
		foreach ( (array) $wpdb->get_results( 'SELECT user_id, COUNT(*) AS n FROM ' . self::subs_table() . ' GROUP BY user_id', ARRAY_A ) as $r ) { // phpcs:ignore
			$out[ (int) $r['user_id'] ] = (int) $r['n'];
		}
		return $out;
	}

	/** Name of the configured source (for the log). */
	private static function source_name() {
		$value = self::get( 'cct' );
		foreach ( self::sources() as $list ) {
			if ( isset( $list[ $value ] ) ) {
				return $list[ $value ];
			}
		}
		return (string) $value;
	}

	/* =========================================================
	 * Mark as read (click on the notification)
	 * ======================================================= */

	public static function read_token( $item_id ) {
		return substr( hash_hmac( 'sha256', 'gn-read-' . (int) $item_id, wp_salt( 'auth' ) ), 0, 24 );
	}

	public static function log_token( $log_id ) {
		return substr( hash_hmac( 'sha256', 'gn-log-' . (int) $log_id, wp_salt( 'auth' ) ), 0, 24 );
	}

	public function ajax_mark_read() {
		global $wpdb;
		$log = isset( $_REQUEST['l'] ) ? (int) $_REQUEST['l'] : 0; // phpcs:ignore
		$lt  = isset( $_REQUEST['lt'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['lt'] ) ) : ''; // phpcs:ignore

		// 1.3.0: log row (+ the source item it came from).
		if ( $log ) {
			if ( ! hash_equals( self::log_token( $log ), $lt ) ) {
				wp_send_json_error( null, 403 );
			}
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::log_table() . ' WHERE id = %d', $log ), ARRAY_A ); // phpcs:ignore
			if ( $row && ! $row['is_read'] ) {
				$wpdb->update( self::log_table(), array( 'is_read' => 1, 'read_at' => current_time( 'mysql' ) ), array( 'id' => $log ) );
			}
			if ( $row && 'entity' === $row['source'] && $row['ref_id'] && $row['ref_type'] === self::get( 'cct' ) && self::get( 'f_read' ) ) {
				$wpdb->update( self::cct_table(), array( self::get( 'f_read' ) => 1 ), array( self::id_col() => (int) $row['ref_id'] ) );
			}
			do_action( 'gruppa_notify/read', $row );
			wp_send_json_success();
		}

		// Before 1.3.0: item ID of the source.
		$id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0; // phpcs:ignore
		$t  = isset( $_REQUEST['t'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['t'] ) ) : ''; // phpcs:ignore
		if ( ! $id || ! hash_equals( self::read_token( $id ), $t ) || ! self::cct_exists() ) {
			wp_send_json_error( null, 403 );
		}
		$wpdb->update( self::cct_table(), array( self::get( 'f_read' ) => 1 ), array( self::id_col() => $id ) );
		wp_send_json_success();
	}

	/* =========================================================
	 * Service worker and manifest (virtual files)
	 * ======================================================= */

	public static function sw_url() {
		return add_query_arg( 'gruppa_notify_sw', '1', home_url( '/' ) );
	}

	public static function manifest_url() {
		return add_query_arg( 'gruppa_notify_manifest', '1', home_url( '/' ) );
	}

	public function serve_virtual_files() {
		if ( isset( $_GET['gruppa_notify_sw'] ) ) { // phpcs:ignore
			nocache_headers();
			header( 'Content-Type: application/javascript; charset=utf-8' );
			header( 'Service-Worker-Allowed: /' );
			$js = file_get_contents( GRUPPA_NOTIFY_DIR . 'assets/sw.js' ); // phpcs:ignore
			echo str_replace( '__GN_AJAX__', esc_url_raw( admin_url( 'admin-ajax.php' ) ), $js ); // phpcs:ignore
			exit;
		}

		if ( isset( $_GET['gruppa_notify_manifest'] ) ) { // phpcs:ignore
			header( 'Content-Type: application/manifest+json; charset=utf-8' );
			$name  = self::get( 'app_name' ) ? self::get( 'app_name' ) : get_bloginfo( 'name' );
			$icons = array();
			foreach ( array( 192, 512 ) as $size ) {
				$u = get_site_icon_url( $size );
				if ( $u ) {
					$icons[] = array(
						'src'   => $u,
						'sizes' => "{$size}x{$size}",
						'type'  => 'image/png',
					);
				}
			}
			echo wp_json_encode(
				array(
					'name'             => $name,
					'short_name'       => $name,
					'start_url'        => home_url( '/' ),
					'scope'            => home_url( '/' ),
					'display'          => 'standalone',
					'background_color' => '#ffffff',
					'theme_color'      => '#ffffff',
					'icons'            => $icons,
				)
			);
			exit;
		}
	}

	public function manifest_link() {
		if ( self::get( 'manifest' ) ) {
			echo '<link rel="manifest" href="' . esc_url( self::manifest_url() ) . '">' . "\n";
			echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		}
	}

	/* =========================================================
	 * Frontend: device registration
	 * ======================================================= */

	public function enqueue() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$vapid = self::vapid();
		if ( ! $vapid ) {
			return;
		}
		wp_enqueue_script( 'gruppa-notify', GRUPPA_NOTIFY_URL . 'assets/gruppa-notify.js', array(), GRUPPA_NOTIFY_VERSION, true );
		wp_localize_script(
			'gruppa-notify',
			'GruppaNotify',
			array(
				'swUrl'     => self::sw_url(),
				'publicKey' => $vapid['public'],
				'restUrl'   => esc_url_raw( rest_url( 'gruppa-notify/v1/subscription' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'i18n'      => self::i18n(),
			)
		);
	}

	/** Frontend texts (Slovak by default) — filter gruppa_notify/i18n to change them. */
	public static function i18n() {
		return apply_filters(
			'gruppa_notify/i18n',
			array(
				'enable'      => 'Zapnúť notifikácie',
				'enabled'     => 'Notifikácie sú zapnuté',
				'disable'     => 'Vypnúť notifikácie',
				'denied'      => 'Notifikácie sú v prehliadači zablokované',
				'unsupported' => 'Tento prehliadač notifikácie nepodporuje',
				'ios'         => 'Na iPhone najprv pridaj web na plochu (Zdieľať → Pridať na plochu) a otvor ho odtiaľ',
				'working'     => 'Moment…',
			)
		);
	}

	public function shortcode_button() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$t = self::i18n();
		return '<div class="gn-push"><button type="button" class="gn-push__btn" data-gn-push>' . esc_html( $t['enable'] ) . '</button><div class="gn-push__msg" data-gn-msg></div></div>';
	}

	/* =========================================================
	 * REST: save / delete subscription
	 * ======================================================= */

	public function register_rest() {
		register_rest_route(
			'gruppa-notify/v1',
			'/subscription',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'rest_subscribe' ),
					'permission_callback' => 'is_user_logged_in',
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'rest_unsubscribe' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);
	}

	public function rest_subscribe( WP_REST_Request $req ) {
		global $wpdb;
		$endpoint = esc_url_raw( (string) $req->get_param( 'endpoint' ) );
		$keys     = (array) $req->get_param( 'keys' );
		$p256dh   = sanitize_text_field( isset( $keys['p256dh'] ) ? $keys['p256dh'] : '' );
		$auth     = sanitize_text_field( isset( $keys['auth'] ) ? $keys['auth'] : '' );

		if ( ! $endpoint || 0 !== strpos( $endpoint, 'https://' ) || ! $p256dh || ! $auth ) {
			return new WP_Error( 'gn_invalid', 'Invalid subscription', array( 'status' => 400 ) );
		}

		$hash = hash( 'sha256', $endpoint );
		$ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$data = array(
			'user_id'       => get_current_user_id(),
			'endpoint'      => $endpoint,
			'endpoint_hash' => $hash,
			'p256dh'        => $p256dh,
			'auth'          => $auth,
			'user_agent'    => substr( $ua, 0, 255 ),
		);

		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::subs_table() . ' WHERE endpoint_hash = %s', $hash ) ); // phpcs:ignore
		if ( $exists ) {
			$wpdb->update( self::subs_table(), $data, array( 'id' => $exists ) );
		} else {
			$data['created'] = current_time( 'mysql' );
			$wpdb->insert( self::subs_table(), $data );
		}
		return array( 'ok' => true );
	}

	public function rest_unsubscribe( WP_REST_Request $req ) {
		global $wpdb;
		$endpoint = esc_url_raw( (string) $req->get_param( 'endpoint' ) );
		$wpdb->delete(
			self::subs_table(),
			array(
				'endpoint_hash' => hash( 'sha256', $endpoint ),
				'user_id'       => get_current_user_id(),
			)
		);
		return array( 'ok' => true );
	}

	/* =========================================================
	 * Admin (Gruppa Tools → Notify)
	 * ======================================================= */

	public static function page_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/** @deprecated 1.0.0 Kept for code written against Gruppa Tools 3.x. */
	public static function tab_url( $args = array() ) {
		return self::page_url( $args );
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">Open</a>' );
		return $links;
	}

	public function register_settings() {
		register_setting(
			'gruppa_notify',
			self::OPT_SETTINGS,
			array(
				'sanitize_callback' => function ( $in ) {
					$out = Gruppa_Notify_Module::defaults();
					foreach ( array( 'f_user', 'f_message', 'f_url', 'f_read' ) as $k ) {
						$out[ $k ] = sanitize_key( isset( $in[ $k ] ) ? $in[ $k ] : $out[ $k ] );
					}
					list( $origin, $slug ) = Gruppa_Notify_Module::parse_source( isset( $in['cct'] ) ? $in['cct'] : $out['cct'] );
					$out['cct'] = '' === $slug ? '' : ( 'gruppa' === $origin ? 'gruppa:' : '' ) . $slug;
					// Another source: start at its current maximum, old items get no push.
					if ( Gruppa_Notify_Module::parse_source( Gruppa_Notify_Module::get( 'cct' ) ) !== array( $origin, $slug ) ) {
						update_option( Gruppa_Notify_Module::OPT_LAST_ID, Gruppa_Notify_Module::max_id_for( $out['cct'] ), false );
					}
					$out['title']    = sanitize_text_field( isset( $in['title'] ) ? $in['title'] : '' );
					$out['app_name'] = sanitize_text_field( isset( $in['app_name'] ) ? $in['app_name'] : '' );
					$out['icon']     = esc_url_raw( isset( $in['icon'] ) ? $in['icon'] : '' );
					$out['manifest'] = empty( $in['manifest'] ) ? 0 : 1;
					return $out;
				},
			)
		);
	}

	/**
	 * JetEngine CCTs, read from wp_jet_post_types (reliable on every install),
	 * with the JetEngine module API as a fallback.
	 *
	 * @return array slug => name
	 */
	public static function cct_types() {
		global $wpdb;
		$out   = array();
		$table = $wpdb->prefix . 'jet_post_types';
		if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			$rows = $wpdb->get_results( "SELECT slug, args FROM `{$table}` WHERE status = 'content-type' ORDER BY id ASC", ARRAY_A ); // phpcs:ignore
			foreach ( (array) $rows as $r ) {
				$args = maybe_unserialize( $r['args'] );
				$args = is_array( $args ) ? $args : array();
				$slug = ! empty( $args['slug'] ) ? $args['slug'] : $r['slug'];
				if ( $slug ) {
					$out[ sanitize_key( $slug ) ] = ! empty( $args['name'] ) ? (string) $args['name'] : $slug;
				}
			}
		}
		if ( ! $out && class_exists( '\Jet_Engine\Modules\Custom_Content_Types\Module' ) ) {
			$module  = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
			$manager = $module && isset( $module->manager ) ? $module->manager : ( $module && isset( $module->instance ) ? $module->instance : null );
			if ( $manager && method_exists( $manager, 'get_content_types' ) ) {
				foreach ( (array) $manager->get_content_types() as $slug => $type ) {
					$name         = is_object( $type ) && method_exists( $type, 'get_arg' ) ? $type->get_arg( 'name' ) : '';
					$out[ $slug ] = $name ? $name : $slug;
				}
			}
		}
		return $out;
	}

	/** Published Gruppa CMS entities: slug => name. */
	public static function gruppa_types() {
		$out = array();
		if ( class_exists( 'Gruppa_Types' ) ) {
			foreach ( Gruppa_Types::all( 'publish' ) as $t ) {
				$out[ $t->slug ] = $t->name;
			}
		}
		return $out;
	}

	/**
	 * Every source for the select: [ 'Gruppa CMS' => [ value => name ], 'JetEngine CCT' => [ … ] ].
	 */
	public static function sources() {
		$out = array();
		foreach ( self::gruppa_types() as $slug => $name ) {
			$out['Gruppa CMS'][ 'gruppa:' . $slug ] = $name;
		}
		foreach ( self::cct_types() as $slug => $name ) {
			$out['JetEngine CCT'][ $slug ] = $name;
		}
		return $out;
	}

	/** @return string[] columns of a source table (without system columns). Accepts "gruppa:slug" or a CCT slug. */
	public static function cct_columns( $value ) {
		global $wpdb;
		$table = self::table_for( $value );
		if ( ! $table || $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return array();
		}
		list( $origin ) = self::parse_source( $value );
		$cols = wp_list_pluck( (array) $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`" ), 'Field' ); // phpcs:ignore
		return array_values( array_diff( $cols, 'gruppa' === $origin ? self::GRUPPA_SYSTEM : self::SYSTEM ) );
	}

	public function admin_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
		if ( self::PAGE !== $page ) {
			return;
		}
		$fields = array();
		foreach ( self::sources() as $list ) {
			foreach ( array_keys( $list ) as $value ) {
				$fields[ $value ] = self::cct_columns( $value );
			}
		}
		wp_enqueue_style( 'gruppa-notify-admin', GRUPPA_NOTIFY_URL . 'assets/admin.css', array( 'gruppa-admin' ), GRUPPA_NOTIFY_VERSION );
		wp_enqueue_script( 'gruppa-notify-admin', GRUPPA_NOTIFY_URL . 'assets/admin.js', array( 'jquery' ), GRUPPA_NOTIFY_VERSION, true );
		wp_localize_script( 'gruppa-notify-admin', 'gruppaNotifyAdmin', array( 'fields' => $fields ) );
	}

	private function devices() {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT s.*, u.display_name FROM ' . self::subs_table() . " s LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id ORDER BY s.created DESC LIMIT 200", ARRAY_A ); // phpcs:ignore
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tabs = array( 'settings' => 'Settings' );
		if ( Gruppa_Notify_Forms::available() ) {
			$tabs['forms'] = 'Forms'; // 1.4.0: JetFormBuilder, Contact Form 7.
		}
		$tabs += array(
			'devices' => 'Devices',
			'log'     => 'Log',
		);
		$tab  = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? sanitize_key( $_GET['tab'] ) : 'settings'; // phpcs:ignore
		$subs = $this->devices();
		?>
		<div class="wrap gruppa-wrap gruppa-notify">
			<h1 class="wp-heading-inline">Notify</h1>
			<?php if ( 'forms' === $tab && empty( $_GET['view'] ) ) : // phpcs:ignore ?>
				<a href="<?php echo esc_url( self::page_url( array( 'tab' => 'forms', 'view' => 'edit' ) ) ); ?>" class="page-title-action">Add form</a>
			<?php elseif ( in_array( $tab, array( 'devices', 'log' ), true ) ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gruppa_notify_test&back=' . $tab ), 'gruppa_notify_test' ) ); ?>" class="page-title-action">Send test to me</a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['gn_msg'] ) ) : // phpcs:ignore ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['gn_msg'] ) ) ); // phpcs:ignore ?></p></div>
			<?php elseif ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore ?>
				<div class="notice notice-success is-dismissible"><p>Saved.</p></div>
			<?php endif; ?>

			<?php if ( ! self::vapid() ) : ?>
				<div class="notice notice-error"><p>The server cannot generate VAPID keys (PHP OpenSSL with prime256v1 support is required).<br><code><?php echo esc_html( Gruppa_Notify_WebPush::diagnose() ); ?></code></p></div>
			<?php endif; ?>

			<nav class="gruppa-tabs">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( self::page_url( array( 'tab' => $key ) ) ); ?>" class="<?php echo $key === $tab ? 'is-active' : ''; ?>"><?php echo esc_html( $label ); ?>
						<?php if ( 'devices' === $key ) : ?>
							<span class="gruppa-tab-count"><?php echo count( $subs ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ( 'devices' === $tab ) {
				$this->render_devices( $subs );
			} elseif ( 'log' === $tab ) {
				$this->render_log();
			} elseif ( 'forms' === $tab ) {
				Gruppa_Notify_Forms::render();
			} else {
				$this->render_settings();
			}
			?>
		</div>
		<?php
	}

	private function render_settings() {
		$s     = self::settings();
		$opt   = self::OPT_SETTINGS;
		$types = self::sources();
		$all   = array();
		foreach ( $types as $list ) {
			$all += $list;
		}
		list( $origin, $slug ) = self::source();
		$current               = '' === $slug ? '' : ( 'gruppa' === $origin ? 'gruppa:' : '' ) . $slug;

		$fields = array(
			'f_user'    => array( 'User ID field', 'WordPress user who receives the notification (a Number field with the user ID; for a Gruppa CMS entity also Author).' ),
			'f_message' => array( 'Message field', 'Text of the notification.' ),
			'f_url'     => array( 'URL field', 'Opened on click (home page when empty).' ),
			'f_read'    => array( 'Read field', 'Set to 1 when the notification is clicked.' ),
		);
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'gruppa_notify' ); ?>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="gn-cct">Source</label></th>
						<td>
							<select name="<?php echo esc_attr( $opt ); ?>[cct]" id="gn-cct">
								<option value="" <?php selected( $current, '' ); ?>>— None —</option>
								<?php if ( '' !== $current && ! isset( $all[ $current ] ) ) : ?>
									<option value="<?php echo esc_attr( $current ); ?>" selected><?php echo esc_html( $slug ); ?> (missing)</option>
								<?php endif; ?>
								<?php foreach ( $types as $group => $list ) : ?>
									<optgroup label="<?php echo esc_attr( $group ); ?>">
										<?php foreach ( $list as $value => $name ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $name ); ?></option>
										<?php endforeach; ?>
									</optgroup>
								<?php endforeach; ?>
							</select>
							<p class="description">Optional. A push for every new item of this entity or CCT. Forms send notifications with the Form Builder action <strong>Send notification</strong> — no source needed.</p>
						</td>
					</tr>
					<?php foreach ( $fields as $key => $f ) : ?>
						<tr class="gn-field-row">
							<th><label for="gn-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $f[0] ); ?></label></th>
							<td>
								<select name="<?php echo esc_attr( $opt . '[' . $key . ']' ); ?>" id="gn-<?php echo esc_attr( $key ); ?>" class="gn-field" data-current="<?php echo esc_attr( $s[ $key ] ); ?>">
									<option value="<?php echo esc_attr( $s[ $key ] ); ?>" selected><?php echo esc_html( $s[ $key ] ); ?></option>
								</select>
								<p class="description"><?php echo esc_html( $f[1] ); ?></p>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr class="gruppa-meta-first">
						<th><label for="gn-title">Title</label></th>
						<td><input type="text" id="gn-title" name="<?php echo esc_attr( $opt ); ?>[title]" value="<?php echo esc_attr( $s['title'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></td>
					</tr>
					<tr>
						<th><label for="gn-icon">Icon URL</label></th>
						<td><input type="url" id="gn-icon" name="<?php echo esc_attr( $opt ); ?>[icon]" value="<?php echo esc_attr( $s['icon'] ); ?>" placeholder="Site icon"></td>
					</tr>
					<tr>
						<th><label for="gn-app">App name</label></th>
						<td><input type="text" id="gn-app" name="<?php echo esc_attr( $opt ); ?>[app_name]" value="<?php echo esc_attr( $s['app_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></td>
					</tr>
					<tr>
						<th>PWA manifest</th>
						<td>
							<label class="gruppa-switch"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[manifest]" value="1" <?php checked( $s['manifest'] ); ?>><span></span></label>
							<p class="description">Needed on iPhone — the site must be added to the home screen.</p>
						</td>
					</tr>
					<tr>
						<th>Shortcode</th>
						<td><input type="text" class="gruppa-shortcode" value="[gruppa_notify_button]" readonly onclick="this.select()"></td>
					</tr>
				</table>
			</div>
			<p class="submit"><button type="submit" class="button button-primary">Save</button></p>
		</form>
		<?php
	}

	private function render_devices( $subs ) {
		$vapid = self::vapid();
		?>
		<table class="wp-list-table widefat fixed gruppa-table">
			<thead>
				<tr>
					<th>User</th>
					<th>Device</th>
					<th>Registered</th>
					<th>Last used</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $subs ) : ?>
				<tr><td colspan="4">No devices yet. Add the <code>[gruppa_notify_button]</code> shortcode to a page for logged-in users.</td></tr>
			<?php endif; ?>
			<?php foreach ( $subs as $row ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $row['display_name'] ? $row['display_name'] : '#' . $row['user_id'] ); ?></strong></td>
					<td><?php echo esc_html( self::short_ua( $row['user_agent'] ) ); ?></td>
					<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $row['created'] ) ); ?></td>
					<td><?php echo esc_html( $row['last_used'] ? mysql2date( 'Y/m/d H:i', $row['last_used'] ) : '—' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<p class="gruppa-notify-keys gruppa-muted">
			VAPID public key <code><?php echo esc_html( isset( $vapid['public'] ) ? substr( $vapid['public'], 0, 16 ) . '…' : '—' ); ?></code>
			&nbsp;·&nbsp;
			<a class="gruppa-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gruppa_notify_regen' ), 'gruppa_notify_regen' ) ); ?>" onclick="return confirm('All devices will have to register again. Continue?');">Generate new keys</a>
		</p>
		<?php
	}

	private function render_log() {
		global $wpdb;
		$per   = 50;
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::log_table() ); // phpcs:ignore
		$pages = max( 1, (int) ceil( $total / $per ) );
		$paged = isset( $_GET['paged'] ) ? min( $pages, max( 1, (int) $_GET['paged'] ) ) : 1; // phpcs:ignore
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT l.*, u.display_name FROM ' . self::log_table() . " l LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id ORDER BY l.id DESC LIMIT %d OFFSET %d", $per, ( $paged - 1 ) * $per ), ARRAY_A ); // phpcs:ignore
		?>
		<table class="wp-list-table widefat fixed gruppa-table gn-log">
			<thead>
				<tr>
					<th class="gn-col-date">Date</th>
					<th class="gn-col-user">Recipient</th>
					<th>Notification</th>
					<th class="gn-col-source">Source</th>
					<th class="gn-col-num">Devices</th>
					<th class="gn-col-read">Read</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="6">No notifications yet. They are sent by the Form Builder action <strong>Send notification</strong>, by the source in Settings or by <strong>Send test to me</strong>.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $r ) : ?>
				<tr>
					<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $r['created'] ) ); ?></td>
					<td><strong><?php echo esc_html( $r['display_name'] ? $r['display_name'] : '#' . $r['user_id'] ); ?></strong></td>
					<td>
						<span class="gn-log-title"><?php echo esc_html( $r['title'] ); ?></span>
						<?php if ( '' !== $r['body'] ) : ?>
							<span class="gn-log-body"><?php echo esc_html( wp_trim_words( $r['body'], 30 ) ); ?></span>
						<?php endif; ?>
						<a class="gn-log-url" href="<?php echo esc_url( $r['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( preg_replace( '#^https?://#', '', $r['url'] ) ); ?></a>
					</td>
					<td><?php echo wp_kses_post( self::log_source( $r ) ); ?></td>
					<td><?php echo $r['failed'] ? esc_html( $r['sent'] . ' / ' . ( $r['sent'] + $r['failed'] ) ) : esc_html( $r['sent'] ); ?></td>
					<td>
						<?php if ( $r['is_read'] ) : ?>
							<span class="gn-read" title="<?php echo esc_attr( $r['read_at'] ? mysql2date( 'Y/m/d H:i', $r['read_at'] ) : '' ); ?>"><?php echo esc_html( $r['read_at'] ? mysql2date( 'H:i', $r['read_at'] ) : 'Yes' ); ?></span>
						<?php else : ?>
							<span class="gruppa-muted">—</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<div class="gn-log-foot">
			<span class="gruppa-muted"><?php echo esc_html( sprintf( '%d notifications', $total ) ); ?></span>
			<?php if ( $pages > 1 ) : ?>
				<span class="gn-pages">
					<?php for ( $p = 1; $p <= $pages; $p++ ) : ?>
						<?php if ( $p === $paged ) : ?>
							<span class="is-current"><?php echo (int) $p; ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( self::page_url( array( 'tab' => 'log', 'paged' => $p ) ) ); ?>"><?php echo (int) $p; ?></a>
						<?php endif; ?>
					<?php endfor; ?>
				</span>
			<?php endif; ?>
			<?php if ( $total ) : ?>
				<a class="gruppa-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gruppa_notify_clear_log' ), 'gruppa_notify_clear_log' ) ); ?>" onclick="return confirm('Delete all log entries?');">Clear log</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Source column of the log: form / entity name, linked to the item in the admin. */
	private static function log_source( $r ) {
		$names = array(
			'form'   => 'Form',
			'entity' => 'Entity',
			'test'   => 'Test',
			'api'    => 'API',
		);
		$kind  = isset( $names[ $r['source'] ] ) ? $names[ $r['source'] ] : ucfirst( $r['source'] );
		$label = '' !== $r['source_label'] ? $kind . ': ' . $r['source_label'] : $kind;
		if ( preg_match( '/^(cf7|jfb):/', $r['ref_type'] ) ) {
			$label = $r['source_label']; // "CF7: Contact" / "JetFormBuilder: Contact".
		}
		$out   = esc_html( $label );
		if ( $r['ref_id'] ) {
			$link = '';
			$slug = 0 === strpos( $r['ref_type'], 'gruppa:' ) ? substr( $r['ref_type'], 7 ) : ( 'form' === $r['source'] ? $r['ref_type'] : '' );
			if ( $slug ) {
				$link = self::item_admin_url( $slug, (int) $r['ref_id'] );
			}
			$out .= $link ? ' · <a href="' . esc_url( $link ) . '">#' . (int) $r['ref_id'] . '</a>' : ' · #' . (int) $r['ref_id'];
		}
		return $out;
	}

	private static function short_ua( $ua ) {
		$os  = '';
		$bro = '';
		foreach ( array( 'iPhone', 'iPad', 'Android', 'Windows', 'Mac OS', 'Linux' ) as $o ) {
			if ( false !== stripos( $ua, $o ) ) {
				$os = 'Mac OS' === $o ? 'macOS' : $o;
				break;
			}
		}
		foreach ( array( 'Edg' => 'Edge', 'OPR' => 'Opera', 'Firefox' => 'Firefox', 'Chrome' => 'Chrome', 'Safari' => 'Safari' ) as $k => $b ) {
			if ( false !== strpos( $ua, $k ) ) {
				$bro = $b;
				break;
			}
		}
		$s = trim( $bro . ( $bro && $os ? ' · ' : '' ) . $os );
		return $s ? $s : $ua;
	}

	public function admin_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_notify_test' );

		$res              = self::notify( array( get_current_user_id() ), array( 'body' => 'Test notification', 'url' => home_url( '/' ), 'source' => 'test', 'label' => 'Send test to me' ) );
		list( , , $sent, $fail ) = $res[0];

		$msg = ( $sent || $fail ) ? sprintf( 'Sent: %d, failed: %d.', $sent, $fail ) : 'You have no registered device.';
		$back = isset( $_GET['back'] ) && 'log' === $_GET['back'] ? 'log' : 'devices'; // phpcs:ignore
		wp_safe_redirect( self::page_url( array( 'tab' => $back, 'gn_msg' => rawurlencode( $msg ) ) ) );
		exit;
	}

	public function admin_clear_log() {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_notify_clear_log' );
		$wpdb->query( 'DELETE FROM ' . self::log_table() ); // phpcs:ignore
		wp_safe_redirect( self::page_url( array( 'tab' => 'log', 'gn_msg' => rawurlencode( 'Log cleared.' ) ) ) );
		exit;
	}

	public function admin_regen() {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_notify_regen' );
		delete_option( self::OPT_VAPID );
		self::vapid();
		$wpdb->query( 'TRUNCATE TABLE ' . self::subs_table() ); // phpcs:ignore
		wp_safe_redirect( self::page_url( array( 'tab' => 'devices', 'gn_msg' => rawurlencode( 'New keys generated, devices removed.' ) ) ) );
		exit;
	}
}
