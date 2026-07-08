<?php
/**
 * Stripe gateway compatibility — adds the raffle product type to Stripe's
 * payment-request (Apple Pay / Google Pay) supported product types so express
 * checkout works for raffle products.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Compat_Stripe extends Raffle_Compatibility {

    public static function name() {
        return __( 'WooCommerce Stripe Gateway', 'wpraffle' );
    }

    public static function is_available() {
        return class_exists( 'WC_Stripe' ) || defined( 'WC_STRIPE_VERSION' );
    }

    public function register_hooks() {
        add_filter( 'wc_stripe_payment_request_supported_types', array( $this, 'add_raffle_type' ) );
        add_filter( 'wc_stripe_display_order_button', array( $this, 'maybe_hide_place_order' ) );
    }

    public function add_raffle_type( $types ) {
        if ( ! is_array( $types ) ) {
            $types = array();
        }
        $types[] = 'raffle';
        return array_unique( $types );
    }

    public function maybe_hide_place_order( $show ) {
        // Raffle products use their own purchase flow; keep Stripe's native
        // button available but defer place-order visibility to WC core.
        return $show;
    }
}
