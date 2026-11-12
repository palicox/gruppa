<?php
/**
 * Plugin Name:       Gruppa SEO Builder
 * Plugin URI:        https://gruppa.cloud
 * Description:       SEO title, meta description, robots, canonical, Open Graph, Twitter / X and JSON-LD for WordPress pages, posts, custom post types and term archives, items of JetEngine CCT with a single page and Gruppa CMS content types: templates per type, redirects 301, sitemap settings and breadcrumbs, with Google and Facebook preview. Part of Gruppa Tools; works with or without Gruppa CMS.
 * Version:           1.4.2
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Gruppa Taxonomy
 * Author URI:        https://gruppa.cloud
 * License:           GPLv2 or later
 * Text Domain:       gruppa-seo-builder
 */

defined( 'ABSPATH' ) || exit;

define( 'GRUPPA_SEO_PLUGIN_VERSION', '1.4.2' );
define( 'GRUPPA_SEO_PLUGIN_FILE', __FILE__ );
define( 'GRUPPA_SEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRUPPA_SEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/*
 * Gruppa Tools hub 1.6.0 (identical copy in every Gruppa Tools plugin; the newest copy is used).
 */
require_once GRUPPA_SEO_PLUGIN_DIR . 'hub/loader.php';

/**
 * Gruppa SEO Builder — a Gruppa Tools plugin, with or without Gruppa CMS.
 *
 * Owners: WordPress pages, posts and custom post types ("wp:{post type}"), items of JetEngine CCT with a single
 * page ("jet:{cct}") and, with Gruppa CMS, items of its content types. Data: table wp_gcms_seo, option
 * gruppa_seo_settings, page slug gruppa-seo — the plugin creates the table itself (1.3.0, also with Gruppa CMS).
 *
 * Gruppa CMS 0.31+ has no built-in SEO Builder. Gruppa CMS 0.29.6–0.30.x reads the version of this plugin from
 * its file header and, when it is the same or newer than its built-in copy, loads nothing of SEO Builder.
 * This plugin decides on plugins_loaded (priority 0), when every plugin is loaded.
 */
final class Gruppa_SEO_Plugin {

	const CMS_MIN    = '0.29.6';
	const DB_VERSION = 1;
	const DB_OPTION  = 'gruppa_seo_db_version';

	/** @var string '' = runs, else the reason it does not (old_cms | builtin). */
	public static $state = '';

	/** @var Gruppa_Admin_SEO|null */
	private static $admin = null;

	public static function boot() {
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'start' ), 0 );
	}

	private static function cms() {
		return defined( 'GRUPPA_CMS_VERSION' ) && class_exists( 'Gruppa_DB' );
	}

	public static function start() {
		if ( self::cms() && version_compare( GRUPPA_CMS_VERSION, self::CMS_MIN, '<' ) ) {
			self::$state = 'old_cms'; // Older Gruppa CMS always loads its own SEO Builder.
		} elseif ( defined( 'GRUPPA_SEO_VERSION' ) || class_exists( 'Gruppa_SEO' ) ) {
			self::$state = 'builtin'; // Gruppa CMS has a newer built-in copy (it shows the notice itself).
		}
		if ( '' !== self::$state ) {
			return;
		}

		define( 'GRUPPA_SEO_VERSION', GRUPPA_SEO_PLUGIN_VERSION );
		require_once GRUPPA_SEO_PLUGIN_DIR . 'includes/class-gruppa-seo-owners.php';
		require_once GRUPPA_SEO_PLUGIN_DIR . 'includes/class-gruppa-seo.php';
		require_once GRUPPA_SEO_PLUGIN_DIR . 'includes/class-gruppa-seo-breadcrumbs.php';
		require_once GRUPPA_SEO_PLUGIN_DIR . 'includes/class-gruppa-seo-redirects.php';
		self::maybe_install(); // 1.3.0: also with Gruppa CMS (0.31+ no longer owns the SEO table).
		Gruppa_SEO::init();
		Gruppa_SEO_Breadcrumbs::init();
		Gruppa_SEO_Redirects::init(); // 1.4.0: redirects 301 / 302 / 307 / 410.

		if ( is_admin() ) {
			require_once GRUPPA_SEO_PLUGIN_DIR . 'admin/class-gruppa-admin-seo.php';
			self::$admin = new Gruppa_Admin_SEO();
			add_action( 'plugins_loaded', array( __CLASS__, 'menu' ), 20 ); // Hub boots at priority 1.
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
			add_filter( 'plugin_action_links_' . plugin_basename( GRUPPA_SEO_PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
		}
	}

	/** Table wp_gcms_seo (the same definition Gruppa CMS used up to 0.30). */
	private static function maybe_install() {
		global $wpdb;
		$table = Gruppa_SEO::table();
		if ( (int) get_option( self::DB_OPTION ) === self::DB_VERSION && ! is_admin() ) {
			return;
		}
		if ( (int) get_option( self::DB_OPTION ) === self::DB_VERSION && $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_type varchar(64) NOT NULL DEFAULT '',
			owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
			author bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NULL DEFAULT NULL,
			modified datetime NULL DEFAULT NULL,
			seo_robots varchar(40) NOT NULL DEFAULT 'index,follow',
			seo_title varchar(255) NOT NULL DEFAULT '',
			seo_description text NULL,
			seo_keywords varchar(255) NOT NULL DEFAULT '',
			seo_canonical varchar(500) NOT NULL DEFAULT '',
			seo_og_title varchar(255) NOT NULL DEFAULT '',
			seo_og_description text NULL,
			seo_og_image bigint(20) unsigned NOT NULL DEFAULT 0,
			seo_og_type varchar(20) NOT NULL DEFAULT '',
			seo_twitter_card varchar(30) NOT NULL DEFAULT '',
			seo_twitter_title varchar(255) NOT NULL DEFAULT '',
			seo_twitter_description text NULL,
			seo_twitter_image bigint(20) unsigned NOT NULL DEFAULT 0,
			seo_schema longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY owner (owner_type,owner_id)
		) {$charset};" );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/** Gruppa Tools → SEO Builder. */
	public static function menu() {
		Gruppa_Tools_Hub::add_module(
			array(
				'slug'     => Gruppa_Admin_SEO::PAGE,
				'title'    => 'SEO Builder',
				'callback' => array( self::$admin, 'render' ),
				'version'  => GRUPPA_SEO_PLUGIN_VERSION,
			)
		);
	}

	public static function assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
		if ( Gruppa_Admin_SEO::PAGE !== $page ) {
			return;
		}
		// gruppa-admin (Gruppa CMS admin.css, or the hub copy without Gruppa CMS) and ⓘ Help come from the hub.
		wp_enqueue_style( 'gruppa-seo-builder', GRUPPA_SEO_PLUGIN_URL . 'admin/assets/seo.css', array( 'gruppa-admin' ), GRUPPA_SEO_PLUGIN_VERSION );
		wp_enqueue_media();
		wp_enqueue_script( 'gruppa-seo', GRUPPA_SEO_PLUGIN_URL . 'admin/assets/seo.js', array( 'jquery' ), GRUPPA_SEO_PLUGIN_VERSION, true );
		if ( isset( $_GET['view'] ) && 'edit' === $_GET['view'] ) { // phpcs:ignore
			wp_enqueue_script( 'gruppa-dirty', GRUPPA_SEO_PLUGIN_URL . 'admin/assets/dirty.js', array( 'jquery' ), GRUPPA_SEO_PLUGIN_VERSION, true );
		}
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=gruppa-seo&tab=settings' ) ) . '">Settings</a>' );
		return $links;
	}

	public static function notices() {
		if ( '' === self::$state || 'builtin' === self::$state || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		global $pagenow;
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
		if ( 'plugins.php' !== $pagenow && 0 !== strpos( $page, 'gruppa-' ) ) {
			return;
		}
		$msg = 'needs Gruppa CMS ' . self::CMS_MIN . ' or newer (active: ' . GRUPPA_CMS_VERSION . '). Until you update Gruppa CMS, its built-in SEO Builder is used (same data).';
		printf( '<div class="notice notice-warning"><p><strong>Gruppa SEO Builder</strong> %s</p></div>', esc_html( $msg ) );
	}
}

Gruppa_SEO_Plugin::boot();
