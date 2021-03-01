<?php

if (!defined('ABSPATH')) {
	exit;
}

class WC_Webhook_Handler_Picksell_Pay extends WC_Gateway_Picksell_Pay {
	public function __construct() {
		$this->private_key = get_option('woocommerce_picksell_pay_private_key');

		add_action('woocommerce_api_wc_picksell_pay', array($this, 'check_for_webhook'));
	}

	public function check_for_webhook() {
		if (('POST' !== $_SERVER['REQUEST_METHOD'])
			|| !isset($_GET['wc-api'])
			|| ('wc_picksell_pay' !== $_GET['wc-api'])
		) {
			return;
		}

		$raw_request_body = file_get_contents('php://input');
		$request_headers = array_change_key_case($this->get_request_headers(), CASE_UPPER);

		if ($this->is_valid_request($request_headers, $raw_request_body)) {
			$this->process_webhook($raw_request_body);
			status_header(200);
		} else {
			status_header(400);
		}

		exit;
	}

	public function is_valid_request($request_headers, $raw_request_body) {
		if (empty($request_headers) || empty($raw_request_body)) {
			return false;
		}

		if (empty($request_headers['PICKSELL-SIGNATURE'])) {
			return false;
		}

		$expected_signature = hash_hmac('sha256', $raw_request_body, $this->private_key);

		if (hash_equals($request_headers['PICKSELL-SIGNATURE'], $expected_signature)) {
			return true;
		}

		return false;
	}

	public function fail_payment($picksell_order_id) {
		$order = $this->get_order_by_picksell_id($picksell_order_id);

		if (!$order) {
			return;
		}

		$message = 'This payment is failed';
		$order->update_status('failed', $message);
		$order->save();
	}

	public function success_payment($picksell_order_id) {
		$order = $this->get_order_by_picksell_id($picksell_order_id);

		if (!$order) {
			return;
		}

		$order->payment_complete();
		$order->save();
	}

	public function process_webhook($request_raw_body) {
		$request_body = json_decode($request_raw_body);

		$status = $request_body->status;
		$picksell_order_id = $request_body->picksellOrderId;

		switch ($status) {
		case 'success':
			$this->success_payment($picksell_order_id);
			break;
		case 'fail':
			$this->fail_payment($picksell_order_id);
			break;
		}
	}

	public function get_order_by_picksell_id($picksell_order_id) {
		global $wpdb;

		$order_id = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $picksell_order_id, 'picksell_order_id'));

		if (!empty($order_id)) {
			return wc_get_order($order_id);
		}

		return false;
	}

	public function get_request_headers() {
		if (!function_exists('getallheaders')) {
			$headers = array();

			foreach ($_SERVER as $name => $value) {
				if ('HTTP_' === substr($name, 0, 5)) {
					$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
				}
			}
			return $headers;

		} else {

			return getallheaders();
		}
	}
}

new WC_Webhook_Handler_Picksell_Pay();
