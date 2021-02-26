<?php

if (!defined('ABSPATH')) {
	exit;
}

class WC_Webhook_Handler_Picksell_Pay extends WC_Gateway_Picksell_Pay {
	public function __construct() {
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

		if ($this->is_valid_request($raw_request_body)) {
			$this->process_webhook($raw_request_body);
			status_header(200);
		} else {
			status_header(400);
		}

		exit;
	}

	public function is_valid_request($raw_request_body) {
		//todo: implement this in the future
		return true;
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

	public function process_webhook($raw_request_body) {
		$body = json_decode($raw_request_body);

		$status = $body->status;
		$picksell_order_id = $body->picksellOrderId;

		switch ($status) {
		case 'success':
			$this->success_payment($picksell_order_id);
			break;
		case 'fail':
			$this->fail_payment($picksell_order_id);
			break;
		}
	}

	public static function get_order_by_picksell_id($picksell_order_id) {
		global $wpdb;

		$order_id = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $picksell_order_id, '_picksell_pay_order_id'));

		if (!empty($order_id)) {
			return wc_get_order($order_id);
		}

		return false;
	}
}

new WC_Webhook_Handler_Picksell_Pay();
