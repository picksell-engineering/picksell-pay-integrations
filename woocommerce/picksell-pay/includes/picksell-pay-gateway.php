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
		$this->dev_mode = $this->get_option('dev_mode');
		$this->token = $this->dev_mode === 'yes' ? $this->get_option('dev_token') : $this->get_option('prod_token');

		update_option('woocommerce_picksell_pay_private_key', $this->dev_mode === 'yes' ? $this->get_option('dev_private_key') : $this->get_option('prod_private_key'));

		WC_PicksellPay_API::set_token($this->token);
		WC_PicksellPay_API::set_environment($this->dev_mode);

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

		add_filter('woocommerce_order_button_html', array($this, 'custom_order_button_html'));
	}

	public function custom_order_button_html($button) {
		// variable $button is HTML for place order button
		// <button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="Place order" data-value="Place order">Place order</button>

		return $button;
	}

	public function init_form_fields() {
		$this->form_fields = require dirname(__FILE__) . '/admin/picksell-pay-settings.php';
	}

	public function is_available() {
		if (!in_array(get_woocommerce_currency(), $this->get_supported_currency())) {
			return false;
		}

		if (empty($this->token) || empty(get_option('woocommerce_picksell_pay_private_key'))) {
			return false;
		}

		return true;
	}

	public function get_supported_currency() {
		return apply_filters(
			'wc_picksell_pay_supported_currencies',
			array(
				'EUR',
			)
		);
	}

	public function order_amount_is_valid($order) {
		if ($order->get_total() < $this->get_minimum_amount()) {
			return false;
		}

		return true;
	}

	public static function get_minimum_amount() {
		switch (get_woocommerce_currency()) {
		case 'EUR':
			return 0.01;
		default:
			return 0.01;
		}
	}

	public function process_payment($order_id) {
		$order = new WC_Order($order_id);

		if ($this->order_amount_is_valid($order) === false) {
			return array(
				'result' => 'fail',
				'redirect' => '',
			);
		}

		$picksell_order_id = WC_PicksellPay_API::create_picksell_order($order);

		if (!$picksell_order_id) {
			return array(
				'result' => 'fail',
				'redirect' => '',
			);
		};

		$order->update_status('pending', 'Payment pending');

		$order->update_meta_data('picksell_order_id', $picksell_order_id, true);
		$order->save();

		WC()->cart->empty_cart();

		return array(
			'result' => 'success',
			'redirect' => WC_PicksellPay_API::get_order_page_url($picksell_order_id),
		);
	}
}
