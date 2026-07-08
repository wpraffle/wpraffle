<?php
/**
 * WooCommerce Smart Coupons compatibility — registers "Smart Coupon" as an
 * instant-win prize type so an operator can award a transferable store-credit
 * coupon as an instant prize. Handles assign + reverse via the Phase 1
 * wpraffle_instant_win_assign_{type} / reverse_{type} filters.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Compat_Smart_Coupons extends Raffle_Compatibility {

    public static function name() {
        return __( 'WooCommerce Smart Coupons', 'wpraffle' );
    }

    public static function is_available() {
        return class_exists( 'WC_Smart_Coupons' ) || defined( 'WC_SMART_COUPONS_VERSION' );
    }

    public function register_hooks() {
        // Register the prize type + its label in the admin selector.
        add_filter( 'wpraffle_instant_win_prize_types', array( $this, 'add_prize_type' ) );
        add_filter( 'wpraffle_instant_win_assign_smart_coupon', array( $this, 'assign' ), 10, 4 );
        add_filter( 'wpraffle_instant_win_reverse_smart_coupon', array( $this, 'reverse' ), 10, 3 );
    }

    public function add_prize_type( $types ) {
        $types['smart_coupon'] = __( 'Smart Coupon (store credit)', 'wpraffle' );
        return $types;
    }

    /**
     * Assign a Smart Coupon: generate a store-credit coupon and email it.
     *
     * @param true|WP_Error $result
     * @param object        $win
     * @param object        $order
     * @param array         $buyer
     * @param array         $config   (passed by reference via the filter's 5th arg)
     */
    public function assign( $result, $win, $order, $buyer, $config = null ) {
        if ( ! is_array( $config ) ) {
            $config = json_decode( isset( $win->prize_config ) ? $win->prize_config : '', true );
            $config = is_array( $config ) ? $config : array();
        }
        $amount = isset( $config['amount'] ) ? (float) $config['amount'] : 0;
        if ( $amount <= 0 || ! class_exists( 'WC_Coupon' ) ) {
            return $result;
        }
        $code    = 'SC-' . strtoupper( substr( md5( uniqid( '', true ) ), 0, 8 ) );
        $coupon  = new WC_Coupon();
        $coupon->set_code( $code );
        $coupon->set_discount_type( 'smart_coupon' ); // Store credit type.
        $coupon->set_amount( $amount );
        $coupon->set_individual_use( false );
        $coupon->set_usage_limit( 1 );
        $coupon_id = 0;
        try {
            $coupon_id = $coupon->save();
        } catch ( Exception $e ) {
            return new WP_Error( 'smart_coupon_failed', $e->getMessage() );
        }
        $config['coupon_id']   = $coupon_id;
        $config['coupon_code'] = $code;
        // Persist back onto the win row.
        if ( ! empty( $win->id ) ) {
            global $wpdb;
            $wpdb->update( $wpdb->prefix . 'raffle_instant_wins', array( 'prize_config' => wp_json_encode( $config ) ), array( 'id' => $win->id ), array( '%s' ), array( '%d' ) );
        }
        return true;
    }

    public function reverse( $result, $win, $order_id, $config = null ) {
        if ( ! is_array( $config ) ) {
            $config = json_decode( isset( $win->prize_config ) ? $win->prize_config : '', true );
            $config = is_array( $config ) ? $config : array();
        }
        if ( empty( $config['coupon_id'] ) ) {
            return true;
        }
        $coupon = new WC_Coupon( $config['coupon_id'] );
        if ( $coupon->get_id() && $coupon->get_usage_count() === 0 ) {
            wp_trash_post( $coupon->get_id() );
        }
        return true;
    }
}
