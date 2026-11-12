<?php
/**
 * Plugin Name:       Gruppa Notify
 * Plugin URI:        https://gruppa.cloud
 * Description:       Gruppa Tools add-on. Native Web Push notifications without external services: notifications after Gruppa CMS Form Builder, JetFormBuilder and Contact Form 7 forms for chosen users and roles, optional push for new items of a Gruppa CMS entity or JetEngine CCT, log of sent notifications, subscribe button via [gruppa_notify_button] and device list.
 * Version:           1.4.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Gruppa Taxonomy
 * Author URI:        https://gruppa.cloud
 * License:           GPLv2 or later
 * Text Domain:       gruppa-notify
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'GRUPPA_NOTIFY_VERSION' ) ) {
	return; // Another copy is already loaded.
}

define( 'GRUPPA_NOTIFY_VERSION', '1.4.3' );
define( 'GRUPPA_NOTIFY_FILE', __FILE__ );
define( 'GRUPPA_NOTIFY_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRUPPA_NOTIFY_URL', plugin_dir_url( __FILE__ ) );

require_once GRUPPA_NOTIFY_DIR . 'hub/loader.php';

if ( ! function_exists( 'gruppa_notify_legacy' ) ) {
	/**
	 * Notify is still running from the old all-in-one Gruppa Tools 3.x (or an old
	 * Gruppa Notify copy in another folder). Same options and tables — load nothing,
	 * ask to remove the old plugin.
	 */
	function gruppa_notify_legacy() {
		if ( defined( 'GRUPPA_TOOLS_VERSION' ) && version_compare( GRUPPA_TOOLS_VERSION, '4', '<' ) ) {
			return 'Gruppa Tools ' . GRUPPA_TOOLS_VERSION;
		}
		if ( class_exists( 'Gruppa_Notify' ) || class_exists( 'GN_WebPush' ) ) {
			return 'Gruppa Notify (old version)';
		}
		return '';
	}

	function gruppa_notify_load() {
		require_once GRUPPA_NOTIFY_DIR . 'includes/class-gruppa-notify-webpush.php';
		require_once GRUPPA_NOTIFY_DIR . 'includes/class-gruppa-notify-module.php';
		require_once GRUPPA_NOTIFY_DIR . 'includes/class-gruppa-notify-form-action.php';
		require_once GRUPPA_NOTIFY_DIR . 'includes/class-gruppa-notify-forms.php';
	}

	/**
	 * Send a push to users and write it to Notify → Log.
	 *
	 *   gruppa_notify( array( 1, 5 ), array( 'body' => 'New request', 'url' => home_url( '/kontakt/' ) ) );
	 *
	 * @param int|int[] $users WordPress user IDs.
	 * @param array     $args  title, body, url, label, ref_type, ref_id.
	 * @return array[] [ user_id, log_id, sent, failed ] per user; empty when Notify is not running.
	 */
	function gruppa_notify( $users, array $args ) {
		if ( ! class_exists( 'Gruppa_Notify_Module' ) ) {
			return array();
		}
		return Gruppa_Notify_Module::notify( (array) $users, $args );
	}
}

add_action(
	'plugins_loaded',
	function () {
		$old = gruppa_notify_legacy();
		if ( $old && defined( 'GRUPPA_TOOLS_VERSION' ) ) {
			return; // Gruppa Tools 3.x — the shared Gruppa Tools notice asks to remove it.
		}
		if ( $old ) {
			add_action(
				'admin_notices',
				function () use ( $old ) {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-error"><p><strong>Gruppa Notify ' . esc_html( GRUPPA_NOTIFY_VERSION ) . '</strong> is waiting: Notify still runs from <strong>' . esc_html( $old ) . '</strong>. '
							. 'Deactivate and delete it in <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Plugins</a> — settings, keys and registered devices stay.</p></div>';
					}
				}
			);
			return;
		}
		gruppa_notify_load();
		Gruppa_Notify_Module::maybe_install(); // Also outside wp-admin: devices register on the website.
		Gruppa_Notify_Module::instance();
		Gruppa_Notify_Form_Action::init();
		Gruppa_Notify_Forms::init();
	},
	10
);

register_activation_hook(
	__FILE__,
	function () {
		if ( gruppa_notify_legacy() ) {
			return;
		}
		gruppa_notify_load();
		Gruppa_Notify_Module::install();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		if ( ! gruppa_notify_legacy() ) {
			wp_clear_scheduled_hook( 'gruppa_notify_sweep' );
		}
	}
);
