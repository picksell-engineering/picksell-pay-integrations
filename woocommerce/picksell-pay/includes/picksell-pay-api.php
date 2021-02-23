<?php

if (!defined('ABSPATH')) {
	exit;
}

class WC_PicksellPay_API {
	const SKD_URL_DEV = 'https://sdk.psd2.club/orders';
	const SKD_URL_PROD = 'https://sdk.psd2.club/orders'; // todo: change on prod url
	const SHOPPING_URL_DEV = 'https://shopping.psd2.club';
	const SHOPPING_URL_PROD = 'https://shopping.psd2.club'; // todo: change on prod url

	private static $secret_key = '';
	private static $dev_mode = true;

	public static function set_secret_key($secret_key) {
		self::$secret_key = $secret_key;
	}

	public static function set_environment($dev_mode) {
		self::$dev_mode = $dev_mode == 'yes' ? true : false;
	}

	public static function get_headers() {
		return array(
			'Authorization' => 'Basic ' . base64_encode(self::$secret_key),
			'Content-Type' => 'application/json; charset=utf-8',
		);
	}

	public static function createOrder($order) {
		/*
			todo:
			1. check available currency
			2. make description?
		*/
		$request_body = array(
			'totalAmount' => $order->get_total(),
			'currency' => 'EUR',
			'description' => 'WC Order id ' . $order->get_id(),
			'callbackUrl' => get_option('siteUrl') . '?wc-api=wc_picksell_pay',
			'returnUrl' => get_option('siteUrl') . '?page_id=8&view-order=' . $order->get_id(),
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
}
