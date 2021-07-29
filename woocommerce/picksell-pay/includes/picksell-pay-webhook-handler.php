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
			$response = $this->process_webhook($raw_request_body);
			wp_send_json($response, $response['status']);
		} else {
			$response = $this->make_response('request is not valid', 400);
			wp_send_json($response, $response['status']);
		}

		exit;
	}

	public function is_valid_request($request_headers, $raw_request_body) {
		if (empty($request_headers) || empty($raw_request_body)) {
			return false;
		}

		if (empty($this->private_key)) {
			return false;
		}

		if (empty($request_headers['PICKSELL-SIGNATURE']) || empty($request_headers['PICKSELL-TIMESTAMP'])) {
			return false;
		}

		$timestamp = intval($request_headers['PICKSELL-TIMESTAMP']);
		if (abs($timestamp - time()) < 5 * MINUTE_IN_SECONDS) {
			return false;
		}

		$signed_payload = $timestamp . '.' . $raw_request_body;
		$expected_signature = hash_hmac('sha256', $signed_payload, $this->private_key);

		if (hash_equals($request_headers['PICKSELL-SIGNATURE'], $expected_signature)) {
			return true;
		}

		return false;
	}

	public function fail_payment($order) {
		$message = 'This payment is failed';
		$order->update_status('failed', $message);
		$order->save();

	}

	public function success_payment($order) {
		$order->payment_complete();
		$order->save();
	}

	public function process_webhook($request_raw_body) {
		$request_body = json_decode($request_raw_body);

		$status = $request_body->status;
		$picksell_order_id = $request_body->transactionId;
		$request_total_amount = $request_body->totalAmount;

		$order = $this->get_order_by_picksell_id($picksell_order_id);

		if (!$order) {
			return $this->make_response('woocommerce order not found', 404);
		}

		if (!$this->check_total_amount($order, $request_total_amount)) {
			return $this->make_response('woocommerce order total amount is not equal picksell order', 400);
		}

		switch ($status) {
		case 'PAYMENT_ACCEPTED':
			WC()->cart->empty_cart();
			break;
		case 'PAYMENT_SUCCESS':
			$this->success_payment($order);
			break;
		case 'PAYMENT_FAILED':
			$this->fail_payment($order);
			break;
		default:
			return $this->make_response('unknown order status', 400);
		}

		return $this->make_response('success request', 200);
	}

	public function make_response($message, $status) {
		return array('result' => $message, 'status' => $status);
	}

	public function check_total_amount($order, $request_total_amount) {
		if ($order->get_total() === $request_total_amount) {
			return true;
		}

		return false;
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
