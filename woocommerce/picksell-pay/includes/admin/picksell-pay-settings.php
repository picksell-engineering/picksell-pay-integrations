<?php

if (!defined('ABSPATH')) {
	exit;
}

return apply_filters(
	'wc_picksell_pay_settings',
	array(
		'enabled' => array(
			'title' => __('Enable/Disable', 'woocommerce'),
			'type' => 'checkbox',
			'label' => 'Enable payment with Picksell.Pay',
			'default' => 'no',
			'description' => '<a href="https://picksell.eu" target="_blank">Additional information</a>',
		),
		'title' => array(
			'title' => __('Title', 'woocommerce'),
			'type' => 'text',
			'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
			'default' => 'Picksell Pay',
			'desc_tip' => true,
		),
		'webhook' => array(
			'title' => 'Webhook Endpoints',
			'type' => 'title',
			'description' => 'Add this webhook url to Picksell Token Settings: ' . '<strong>' . get_option('siteUrl') . '?wc-api=wc_picksell_pay' . '</strong>',
		),
		'prod_token' => array(
			'title' => 'Merchant token (PROD)',
			'description' => 'You can get the token in your personal account',
			'type' => 'text',
			'default' => '',
		),
		'prod_private_key' => array(
			'title' => 'Private key (PROD)',
			'description' => 'You can get the private key in your personal account',
			'type' => 'text',
			'default' => '',
		),
		'dev_token' => array(
			'title' => 'Merchant token (DEV)',
			'description' => 'You can get the token in your personal account',
			'type' => 'text',
			'default' => '',
		),

		'dev_private_key' => array(
			'title' => 'Private key (DEV)',
			'description' => 'You can get the token in your personal account',
			'type' => 'text',
			'default' => '',
		),
		'dev_mode' => array(
			'title' => __('Enable/Disable', 'woocommerce'),
			'type' => 'checkbox',
			'label' => 'Use DEV environment on Picksell Pay',
			'default' => 'no',
			'description' => 'Use for tests only ',
		),
	)
);
