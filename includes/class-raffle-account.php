<?php
/**
 * WPRaffle — My-Account helper.
 *
 * NOTE: We no longer register separate WC endpoints (tickets, wins, etc.)
 * because those require rewrite flushes that are unreliable across hosting setups.
 * Instead, the existing 'my-raffles' endpoint now shows ALL raffle content
 * via internal query-param tabs (?sub=wins, ?sub=responsible-gambling, etc.)
 * — see class-raffle-woocommerce.php::my_raffles_endpoint_content().
 *
 * Credit/Cash are handled by WooWallet's own wallet page.
 *
 * This class now only provides AJAX handlers for Responsible Gambling.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Account {

    public function __construct() {
        // AJAX for RG settings only — no endpoint registration.
        add_action( 'wp_ajax_raffle_rg_save_limit', array( $this, 'ajax_save_limit' ) );
        add_action( 'wp_ajax_raffle_rg_self_exclude', array( $this, 'ajax_self_exclude' ) );
    }

    /* ===================================================================
       AJAX: Responsible Gambling
       =================================================================== */

    public function ajax_save_limit() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }
        check_ajax_referer( 'raffle_rg_nonce', 'nonce' );

        $period = sanitize_text_field( wp_unslash( $_POST['period'] ?? 'month' ) );
        $amount = (float) ( $_POST['amount'] ?? 0 );

        $result = Raffle_Responsible_Gambling::set_spend_limit( get_current_user_id(), $period, $amount );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( array( 'message' => 'Spending limit updated.' ) );
    }

    public function ajax_self_exclude() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }
        check_ajax_referer( 'raffle_rg_nonce', 'nonce' );

        $days = absint( $_POST['days'] ?? 0 );
        if ( $days < 1 ) {
            wp_send_json_error( array( 'message' => 'Invalid exclusion period.' ) );
        }

        $until = date( 'Y-m-d H:i:s', strtotime( "+{$days} days" ) );
        $result = Raffle_Responsible_Gambling::self_exclude( get_current_user_id(), $until );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( array( 'message' => 'You are now self-excluded until ' . date_i18n( 'jS F Y', strtotime( $until ) ) . '.' ) );
    }
}
