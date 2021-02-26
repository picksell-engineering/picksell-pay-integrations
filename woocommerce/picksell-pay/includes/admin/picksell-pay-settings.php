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
			'default' => 'yes',
			'description' => '<a href="https://picksell.eu" target="_blank">Additional information</a>',
		),
		'title' => array(
			'title' => __('Title', 'woocommerce'),
			'type' => 'text',
			'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
			'default' => 'Picksell Pay',
			'desc_tip' => true,
		),
		'token' => array(
			'title' => 'Merchant token',
			'description' => 'You can get a token in your personal account',
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
