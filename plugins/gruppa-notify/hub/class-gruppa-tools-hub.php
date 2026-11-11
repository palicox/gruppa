<?php
/**
 * Gruppa Tools hub — shared admin menu and UI for all Gruppa Tools add-ons.
 *
 * - One "Gruppa Tools" menu for the separate Gruppa plugins, right below "Gruppa CMS" (or on its own without it).
 *   Fixed order (1.5.0, self::ORDER): Import/Export, Theme Builder (Gruppa theme, only while it is active),
 *   Table Builder, SEO Builder, Payments, Notify. Unknown modules follow by their own 'order'.
 * - Every installed plugin adds one submenu item.
 * - The Gruppa CMS admin look (gruppa-wrap, gruppa-card, gruppa-tabs, gruppa-table …):
 *   with Gruppa CMS active its own stylesheet is used, otherwise the bundled copy.
 */

defined( 'ABSPATH' ) || exit;

final class Gruppa_Tools_Hub {

	const MENU_TITLE = 'Gruppa Tools';
	const CMS_MENU   = 'gruppa-types';
	const CAP        = 'manage_options';
	const ICON       = 'dashicons-admin-tools';

	/** Menu position without Gruppa CMS (Gruppa CMS uses 25). */
	const POSITION = 26;

	/**
	 * Menu order of the known modules (1.5.0). Wins over the 'order' a plugin passes, so plugins with an older
	 * hub copy still land in the right place.
	 */
	const ORDER = array(
		'gruppa-import-export' => 10,
		'gruppa-theme-builder' => 20,
		'gruppa-table-builder' => 30,
		'gruppa-seo'           => 40,
		'gruppa-payments'      => 50,
		'gruppa-notify'        => 60,
	);

	private static $version = '';
	private static $url     = '';
	private static $modules = array();

	public static function boot( $version, $url ) {
		self::$version = $version;
		self::$url     = $url;

		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_filter( 'custom_menu_order', '__return_true' );
		add_filter( 'menu_order', array( __CLASS__, 'menu_order' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_action( 'admin_notices', array( __CLASS__, 'legacy_notice' ) );
	}

	public static function version() {
		return self::$version;
	}

	/* ---------- Modules ---------- */

	/**
	 * @param array $m {
	 *     @type string   $slug       Admin page slug (required).
	 *     @type string   $title      Menu label (required).
	 *     @type callable $callback   Page renderer (required).
	 *     @type string   $page_title Browser title. Default: "$title ‹ Gruppa Tools".
	 *     @type int      $order      Position in the menu (ignored for the modules in self::ORDER). Default 100.
	 *     @type string   $cap        Capability. Default manage_options.
	 *     @type string   $version    Add-on version (informative).
	 * }
	 */
	public static function add_module( array $m ) {
		if ( empty( $m['slug'] ) || empty( $m['title'] ) || empty( $m['callback'] ) ) {
			return;
		}
		$m = wp_parse_args(
			$m,
			array(
				'page_title' => $m['title'] . ' ‹ ' . self::MENU_TITLE,
				'order'      => 100,
				'cap'        => self::CAP,
				'version'    => '',
			)
		);
		if ( isset( self::ORDER[ $m['slug'] ] ) ) {
			$m['order'] = self::ORDER[ $m['slug'] ];
		}
		self::$modules[ $m['slug'] ] = $m;
	}

	/** @return array slug => module, sorted by order, then title. */
	public static function modules() {
		$mods = self::$modules;
		uasort(
			$mods,
			function ( $a, $b ) {
				return (int) $a['order'] === (int) $b['order'] ? strcasecmp( $a['title'], $b['title'] ) : (int) $a['order'] - (int) $b['order'];
			}
		);
		return $mods;
	}

	public static function has_module( $slug ) {
		return isset( self::$modules[ $slug ] );
	}

	/** Slug of the top-level menu = first module. */
	public static function menu_slug() {
		$mods = self::modules();
		return $mods ? key( $mods ) : '';
	}

	public static function url( $slug, $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
	}

	public static function cms_active() {
		return class_exists( 'Gruppa_Types' );
	}

	/* ---------- Menu ---------- */

	public static function menu() {
		$mods = self::modules();
		if ( ! $mods ) {
			return;
		}
		$first = reset( $mods );
		add_menu_page( self::MENU_TITLE, self::MENU_TITLE, $first['cap'], $first['slug'], $first['callback'], self::ICON, self::POSITION );
		foreach ( $mods as $m ) {
			add_submenu_page( $first['slug'], $m['page_title'], $m['title'], $m['cap'], $m['slug'], $m['callback'] );
		}
	}

	/** Keep "Gruppa Tools" right below "Gruppa CMS". */
	public static function menu_order( $order ) {
		$mine = self::menu_slug();
		if ( ! is_array( $order ) || ! $mine ) {
			return $order;
		}
		$cms = array_search( self::CMS_MENU, $order, true );
		$pos = array_search( $mine, $order, true );
		if ( false === $cms || false === $pos ) {
			return $order;
		}
		unset( $order[ $pos ] );
		$order = array_values( $order );
		$cms   = array_search( self::CMS_MENU, $order, true );
		array_splice( $order, $cms + 1, 0, array( $mine ) );
		return $order;
	}

	/* ---------- UI ---------- */

	public static function is_module_page() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
		return $page && isset( self::$modules[ $page ] );
	}

	/** Gruppa CMS admin stylesheet (handle gruppa-admin) — the bundled copy when Gruppa CMS is not active. */
	public static function assets() {
		if ( ! self::is_module_page() ) {
			return;
		}
		if ( ! wp_style_is( 'gruppa-admin', 'registered' ) ) {
			wp_register_style( 'gruppa-admin', self::$url . 'gruppa-ui.css', array(), self::$version );
		}
		wp_enqueue_style( 'gruppa-admin' );
		// 1.6.0: box titles inside the boxes (same script as Gruppa CMS 0.32.3+; Gruppa CMS enqueues its own copy).
		if ( ! wp_script_is( 'gruppa-boxes', 'enqueued' ) && ! wp_script_is( 'gruppa-boxes', 'registered' ) ) {
			wp_enqueue_script( 'gruppa-boxes', self::$url . 'boxes.js', array(), self::$version, true );
		}
		// Help bubbles + "Help" switch (same script as Gruppa CMS; the first copy registered is used).
		wp_enqueue_script( 'gruppa-help', self::$url . 'gruppa-help.js', array(), self::$version, true );
		wp_localize_script( 'gruppa-help', 'gruppaHelp', array( 'tutorial' => esc_url_raw( self::tutorial_url( sanitize_key( wp_unslash( $_GET['page'] ) ) ) ) ) ); // phpcs:ignore
	}

	/**
	 * "Tutorial" link in the header of an add-on page: the module's own 'tutorial' URL, otherwise
	 * https://gruppa.cloud/{slug_with_underscores}_tutorial.pdf (e.g. gruppa_import_export_tutorial.pdf).
	 * Filter gruppa_tools/tutorial_url — return '' to hide the link.
	 */
	public static function tutorial_url( $slug ) {
		$m   = isset( self::$modules[ $slug ] ) ? self::$modules[ $slug ] : array();
		$url = ! empty( $m['tutorial'] ) ? $m['tutorial'] : 'https://gruppa.cloud/' . str_replace( '-', '_', $slug ) . '_tutorial.pdf';
		return (string) apply_filters( 'gruppa_tools/tutorial_url', $url, $slug );
	}

	/* ---------- Old Gruppa Tools plugins ---------- */

	/**
	 * Old all-in-one Gruppa Tools 1.x–3.x, and the Gruppa Tools 4–6 bundle (no longer developed: every
	 * tool is a separate plugin).
	 */
	public static function legacy_notice() {
		if ( ! defined( 'GRUPPA_TOOLS_VERSION' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$plugins = esc_url( admin_url( 'plugins.php' ) );
		if ( version_compare( GRUPPA_TOOLS_VERSION, '4', '<' ) ) {
			echo '<div class="notice notice-warning"><p><strong>Gruppa Tools ' . esc_html( GRUPPA_TOOLS_VERSION ) . '</strong> is the old all-in-one version. '
				. 'Install the separate plugins you use (' . esc_html( self::plugin_names() ) . '), then remove the old version in <a href="' . $plugins . '">Plugins</a> — settings, keys and registered devices stay.</p></div>';
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
		global $pagenow;
		if ( 'plugins.php' !== $pagenow && ! isset( self::$modules[ $page ] ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>Gruppa Tools ' . esc_html( GRUPPA_TOOLS_VERSION ) . '</strong> (the bundle) is no longer developed. '
			. 'Every tool is a separate plugin: ' . esc_html( self::plugin_names() ) . '. An active separate plugin runs instead of the copy in the bundle — install the ones you use, then delete Gruppa Tools in <a href="' . $plugins . '">Plugins</a>. Settings and data stay.</p></div>';
	}

	private static function plugin_names() {
		return 'Gruppa Import/Export, Gruppa Table Builder, Gruppa SEO Builder, Gruppa Payments, Gruppa Notify';
	}
}
