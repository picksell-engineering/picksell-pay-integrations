<?php

/**
 * Plugin Name: Picksell Pay
 * Description: Acceptance of payments through the system Picksell Pay
 * Author: PicksellPay
 * Version: 0.0.1
 */
if (!defined('ABSPATH')) {
	exit;
}

define('WC_PICKSELL_PAY_MAIN_FILE', __FILE__);
define('WC_PICKSELL_PAY_VERSION', '1.0.0');
define('WC_PICKSELL_PAY_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));

add_action('plugins_loaded', 'woocommerce_gateway_picksell_pay');

function woocommerce_gateway_picksell_pay() {

	static $plugin;

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