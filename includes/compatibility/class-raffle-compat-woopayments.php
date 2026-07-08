<?php
/**
 * WooPayments compatibility — adds raffle products to WCPay payment-request
 * supported types.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Compat_Woopayments extends Raffle_Compatibility {

    public static function name() {
        return __( 'WooPayments', 'wpraffle' );
    }

    public static function is_available() {
        return class_exists( 'WC_Payments' ) || defined( 'WCPAY_VERSION' );
    }

    public function register_hooks() {
        add_filter( 'wcpay_payment_request_supported_types', array( $this, 'add_raffle_type' ) );
        add_filter( 'wcpay_woopay_button_supported_product_types', array( $this, 'add_raffle_type' ) );
    }

    public function add_raffle_type( $types ) {
        if ( ! is_array( $types ) ) {
            $types = array();
        }
        $types[] = 'raffle';
        return array_unique( $types );
    }
}
