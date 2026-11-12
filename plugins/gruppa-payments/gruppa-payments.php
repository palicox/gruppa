<?php
/**
 * Plugin Name:       Gruppa Payments
 * Plugin URI:        https://gruppa.cloud
 * Description:       Gruppa Tools add-on. One-off payments and subscriptions through Stripe and PayPal without SDKs or WooCommerce: Payment action for Gruppa CMS forms and JetFormBuilder forms, [gruppa_pay] button, PHP API, verified webhooks and every payment in its own table wp_gcms_payments.
 * Version:           1.1.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Gruppa Taxonomy
 * Author URI:        https://gruppa.cloud
 * License:           GPLv2 or later
 * Text Domain:       gruppa-payments
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'GRUPPA_PAY_VERSION' ) ) {
	return; // Another copy (standalone or Gruppa Tools bundle) is already loaded.
}

define( 'GRUPPA_PAY_VERSION', '1.1.2' );
define( 'GRUPPA_PAY_FILE', __FILE__ );
define( 'GRUPPA_PAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRUPPA_PAY_URL', plugin_dir_url( __FILE__ ) );

require_once GRUPPA_PAY_DIR . 'hub/loader.php';
require_once GRUPPA_PAY_DIR . 'includes/class-gruppa-pay-db.php';
require_once GRUPPA_PAY_DIR . 'includes/class-gruppa-pay-gateway.php';
require_once GRUPPA_PAY_DIR . 'includes/class-gruppa-pay-stripe.php';
require_once GRUPPA_PAY_DIR . 'includes/class-gruppa-pay-paypal.php';
require_once GRUPPA_PAY_DIR . 'includes/class-gruppa-payments.php';
require_once GRUPPA_PAY_DIR . 'includes/class-gruppa-pay-objects.php';
require_once GRUPPA_PAY_DIR . 'includes/class-gruppa-pay-webhooks.php';
require_once GRUPPA_PAY_DIR . 'includes/class-gruppa-pay-front.php';
require_once GRUPPA_PAY_DIR . 'includes/class-gruppa-pay-forms.php';
require_once GRUPPA_PAY_DIR . 'includes/class-gruppa-pay-jfb.php';

Gruppa_Payments::init();
Gruppa_Pay_Webhooks::init();
Gruppa_Pay_Front::init();
Gruppa_Pay_Forms::init();
Gruppa_Pay_JFB::init(); // 1.1.0: JetFormBuilder forms, also without Gruppa CMS.

if ( is_admin() ) {
	require_once GRUPPA_PAY_DIR . 'admin/class-gruppa-pay-admin.php';
	Gruppa_Pay_Admin::init();
}

register_activation_hook( __FILE__, array( 'Gruppa_Pay_DB', 'install' ) );
register_deactivation_hook( __FILE__, array( 'Gruppa_Payments', 'unschedule' ) );
