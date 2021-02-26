<?php

if (!defined('ABSPATH')) {
	exit;
}

class WC_PicksellPay_API {
	const SKD_URL_DEV = 'https://sdk.psd2.club/orders';
	const SKD_URL_PROD = 'https://sdk.psd2.club/orders'; // todo: change on prod url
	const SHOPPING_URL_DEV = 'https://shopping.psd2.club';
	const SHOPPING_URL_PROD = 'https://shopping.psd2.club'; // todo: change on prod url

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
		$request_body = array(
			'totalAmount' => $order->get_total(),
			'currency' => $order->get_currency(),
			'description' => 'WC Order id ' . $order->get_id(),
			'callbackUrl' => self::get_callbacl_url(),
			'returnUrl' => self::get_return_url($order),
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
			return $response_body->payload->id;
		}

		return null;
	}

	public static function get_sdk_url() {
		return self::$dev_mode ? self::SKD_URL_DEV : self::SKD_URL_PROD;
	}

	public static function get_order_page_url($picksell_order_id) {
		return (self::$dev_mode ? self::SHOPPING_URL_DEV : self::SHOPPING_URL_PROD) . '/orders/' . $picksell_order_id;
	}

	public static function get_callbacl_url() {
		return get_option('siteUrl') . '?wc-api=wc_picksell_pay';
	}

	public static function get_return_url($order) {
		return get_option('siteUrl') . '?page_id=8&view-order=' . $order->get_id();
	}
}
