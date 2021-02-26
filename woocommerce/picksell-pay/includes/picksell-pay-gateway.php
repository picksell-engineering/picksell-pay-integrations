<?php

if (!defined('ABSPATH')) {
	exit;
}

class WC_Gateway_Picksell_Pay extends WC_Payment_Gateway {
	public function __construct() {
		$this->id = 'picksell-pay';
		$this->has_fields = false;

		$this->method_title = 'Picksell Pay';
		$this->method_description = 'Accept payment using PicksellPay';

		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->get_option('title');
		$this->token = $this->get_option('token');
		$this->dev_mode = $this->get_option('dev_mode');

		WC_PicksellPay_API::set_token($this->token);
		WC_PicksellPay_API::set_environment($this->dev_mode);

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
	}

	public function init_form_fields() {
		$this->form_fields = require dirname(__FILE__) . '/admin/picksell-pay-settings.php';
	}

	public function get_supported_currency() {
		return apply_filters(
			'wc_picksell_pay_supported_currencies',
			array(
				'EUR',
				'GBP',
			)
		);
	}

	public function is_available() {
		if (!in_array(get_woocommerce_currency(), $this->get_supported_currency())) {
			return false;
		}

		//todo: maybe add othec check, example total amount order

		return true;
	}

	public function process_payment($order_id) {
		$order = new WC_Order($order_id);
		$picksell_order_id = WC_PicksellPay_API::create_picksell_order($order);

		if (!$picksell_order_id) {
			return array(
				'result' => 'fail',
				'redirect' => '',
			);
		};

		$order->update_status('pending', 'Payment pending');

		$order->update_meta_data('_picksell_pay_order_id', $picksell_order_id, true);
		$order->save();

		WC()->cart->empty_cart();

		return array(
			'result' => 'success',
			'redirect' => WC_PicksellPay_API::get_order_page_url($picksell_order_id),
		);
	}
}
