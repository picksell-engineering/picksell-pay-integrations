<?php

/**
 * Plugin Name: Picksell Pay for WooCommerce
 * Description: Accept SEPA bank payments through OpenBanking with minimum fees by Picksell Pay
 * Author:      Picksell LTD
 * Author URI:  https://picksell.eu
 * Version:     1.0.1
 */
if (!defined('ABSPATH')) {
	exit;
}

define('WC_PICKSELL_PAY_MAIN_FILE', __FILE__);
define('WC_PICKSELL_PAY_VERSION', '1.0.1');
define('WC_PICKSELL_PAY_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));

add_action('plugins_loaded', 'woocommerce_gateway_picksell_pay');

function woocommerce_picksell_pay_missing_wc_notice() {
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Picksell Pay requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-picksell-pay' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

function woocommerce_gateway_picksell_pay() {

	static $plugin;

	if (!class_exists('WooCommerce')) {
		add_action( 'admin_notices', 'woocommerce_picksell_pay_missing_wc_notice' );
		return;
	}

	if (!isset($plugin)) {

		class WC_Picksell_Pay {
			private static $instance;

			public static function get_instance() {
				if (self::$instance === null) {
					self::$instance = new self();
				}

				return self::$instance;
			}

			public function __clone() {}
			public function __wakeup() {}

			public function __construct() {
				require_once dirname(WC_PICKSELL_PAY_MAIN_FILE) . '/includes/picksell-pay-api.php';
				require_once dirname(WC_PICKSELL_PAY_MAIN_FILE) . '/includes/picksell-pay-gateway.php';
				require_once dirname(WC_PICKSELL_PAY_MAIN_FILE) . '/includes/picksell-pay-webhook-handler.php';

				add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
			}

			public function add_gateways($gateways) {
				$gateways[] = 'WC_Gateway_Picksell_Pay';
				return $gateways;
			}
		}

		$plugin = WC_Picksell_Pay::get_instance();
	}

	return $plugin;
}