<?php
/**
 * Square gateway compatibility — adds raffle products to Square's digital
 * wallet supported types for express checkout.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Compat_Square extends Raffle_Compatibility {

    public static function name() {
        return __( 'WooCommerce Square', 'wpraffle' );
    }

    public static function is_available() {
        return class_exists( 'WooCommerce\Square\Gateway' ) || defined( 'WOOCOMMERCE_SQUARE_VERSION' );
    }

    public function register_hooks() {
        add_filter( 'woocommerce_square_digital_wallet_supported_types', array( $this, 'add_raffle_type' ) );
    }

    public function add_raffle_type( $types ) {
        if ( ! is_array( $types ) ) {
            $types = array();
        }
        $types[] = 'raffle';
        return array_unique( $types );
    }
}
