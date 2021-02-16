<?php

/**
 * Plugin Name: Picksell Pay
 * Description: Acceptance of payments through the system Picksell Pay
 * Author: PicksellPay
 * Version: 0.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/includes/picksell-pay-api.php';
require_once dirname( __FILE__ ) . '/includes/picksell-pay-gateway.php';