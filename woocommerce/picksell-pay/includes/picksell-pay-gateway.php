<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pp_init_gateway_class() {
    if ( !class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    if ( class_exists( 'WC_Gateway_Picksell_Pay' ) ) {
        return;
    }

    class WC_Gateway_Picksell_Pay extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'picksell-pay';
            $this->has_fields = false;

            $this->method_title = 'Picksell Pay';
            $this->method_description = 'Accept payment using Picksell.Pay';

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->secret_key = $this->get_option( 'secret_key' );
            $this->dev_mode = $this->get_option( 'dev_mode' );
            
            WC_PicksellPay_API::set_secret_key( $this->secret_key );
            WC_PicksellPay_API::set_environment( $this->dev_mode );

            $this->form_fields = array(
                'enabled'   => array(
                    'title'       => __( 'Enable/Disable', 'woocommerce' ),
                    'type'        => 'checkbox',
                    'label'       => 'Enable payment with Picksell.Pay',
                    'default'     => 'yes',
                    'description' => '<a href="https://picksell.eu" target="_blank">Additional information</a>',
                ),
                'title'     => array(
                    'title'       => __( 'Title', 'woocommerce' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default'     => 'Picksell Pay',
                    'desc_tip'    => true,
                ),
                'description'     => array(
                    'title'       => 'Info for clients',
                    'type'        => 'textarea',
                    'description' => 'Text',
                ),
                'secret_key'  => array(
                    'title'       => 'Merchant token',
                    'description' => 'You can get a token in your personal account',
                    'type'        => 'text',
                    'default'     => '',
                ),
                'dev_mode'   => array(
                    'title'       => __( 'Enable/Disable', 'woocommerce' ),
                    'type'        => 'checkbox',
                    'label'       => 'Use DEV environment on Picksell Pay',
                    'default'     => 'no',
                    'description' => 'Use for tests only ',
                ),
            );
        

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'pay_for_order' ) );
        }

        public function process_payment( $order_id ) {
            $order = new WC_Order( $order_id );
            return array(
                'result'   => 'success',
                'redirect' => $order->get_checkout_payment_url( true ),
            );
        }
        
        public function pay_for_order( $order_id ) {  
            $order = new WC_Order( $order_id );
            $picksell_order_id = WC_PicksellPay_API::createOrder($order);

            $order->add_order_note( 'The order is placed, the client is prompted to proceed to pay for the order using the Picksell Pay' );
            $order->update_status( 'pending', 'Payment pending' );

            echo '
                <p>You will be redirected to the Picksell Pay to make a payment</p>
				<div style="padding-bottom: 15px;">
				    <a target="_blank" href="' . WC_PicksellPay_API::get_order_page_url($picksell_order_id) .'">Pay</a>
				</div>
            ';

            WC()->cart->empty_cart();
        }
    }
    
    add_filter( 'woocommerce_payment_gateways', function ( $methods ) {
        $methods[] = 'WC_Gateway_Picksell_Pay';
        return $methods;
    } );
}

add_action( 'plugins_loaded', 'pp_init_gateway_class' );