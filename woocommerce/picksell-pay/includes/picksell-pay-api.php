<?php

if (!defined('ABSPATH')) {
	exit;
}

class WC_PicksellPay_API {
	const SKD_URL_DEV = 'https://sdk.psd2.club/transactions';
	const SKD_URL_PROD = 'https://sdk.picksell.eu/transactions';
	const SHOPPING_URL_DEV = 'https://psd2.club';
	const SHOPPING_URL_PROD = 'https://picksell.eu';

	private static $token = '';
	private static $dev_mode = false;

	public static function set_token($token) {
		self::$token = $token;
	}

	public static function set_environment($dev_mode) {
		self::$dev_mode = $dev_mode == 'yes';
	}

	public static function get_headers() {
		return array(
			'Authorization' => 'Basic ' . base64_encode(self::$token),
			'Content-Type' => 'application/json; charset=utf-8',
		);
	}

	public static function create_picksell_order($order) {
		$total_amount = $order->get_total();
		$currency = $order->get_currency();
		$order_id = $order->get_id();

		$request_body = array(
			'totalAmount' => $total_amount,
			'currency' => $currency,
			'description' => 'New WooCommerce Order ' . $order_id,
			'returnUrl' => self::get_return_url($order_id),
		);

		$response = wp_remote_post(
			self::get_sdk_url(),
			array(
				'method' => 'POST',
				'headers' => self::get_headers(),
				'body' => json_encode($request_body),
				'timeout' => 15,
			)
		);

		$response_body = json_decode($response['body']);

		if (property_exists($response_body, 'payload')) {
			return $response_body->payload;
		}

		return null;
	}

	public static function get_sdk_url() {
		return self::$dev_mode ? self::SKD_URL_DEV : self::SKD_URL_PROD;
	}

	public static function get_return_url($order_id) {
		return wc_get_account_endpoint_url('view-order') . $order_id;
	}
}
