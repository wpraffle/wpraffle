<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Referrals {

    public function __construct() {
        add_action( 'wp_ajax_raffle_get_referral', array( $this, 'ajax_get_referral' ) );
        add_action( 'wp_ajax_nopriv_raffle_get_referral', array( $this, 'ajax_get_referral' ) );
        add_action( 'wp_ajax_raffle_track_referral', array( $this, 'ajax_track_referral' ) );
        add_action( 'wp_ajax_nopriv_raffle_track_referral', array( $this, 'ajax_track_referral' ) );
    }

    /**
     * Get or create a referral code for a user + raffle.
     */
    public static function get_referral_code( $raffle_id, $user_email ) {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_referrals';

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE raffle_id = %d AND user_email = %s",
            $raffle_id, $user_email
        ) );

        if ( $existing ) {
            return $existing->referral_code;
        }

        // Generate unique code
        do {
            $code = 'REF-' . strtoupper( bin2hex( random_bytes( 4 ) ) );
            $check = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE referral_code = %s",
                $code
            ) );
        } while ( $check );

        $wpdb->insert( $table, array(
            'raffle_id'    => absint( $raffle_id ),
            'user_email'   => sanitize_email( $user_email ),
            'referral_code' => $code,
            'created_at'   => current_time( 'mysql' ),
        ), array( '%d', '%s', '%s', '%s' ) );

        Raffle_Audit::log( $raffle_id, 'referral_code_created', array(
            'email' => $user_email,
            'code'  => $code,
        ), $user_email );

        return $code;
    }

    /**
     * Track a referral click and award bonus entries.
     */
    public static function track_referral( $raffle_id, $referral_code, $referred_email ) {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_referrals';

        $referral = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE raffle_id = %d AND referral_code = %s",
            $raffle_id, $referral_code
        ) );

        if ( ! $referral ) {
            return new WP_Error( 'invalid_referral', 'Invalid referral code.' );
        }

        // Don't allow self-referral
        if ( $referral->user_email === $referred_email ) {
            return new WP_Error( 'self_referral', 'Cannot refer yourself.' );
        }

        // Rate limiting: prevent duplicate referral tracking per email pair per raffle
        $rate_key = 'raffle_ref_' . md5( $raffle_id . '_' . $referral_code . '_' . $referred_email );
        if ( get_transient( $rate_key ) ) {
            return new WP_Error( 'already_tracked', 'This referral has already been tracked.' );
        }

        // Get raffle settings
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle || ! $raffle->allow_referrals ) {
            return new WP_Error( 'referrals_disabled', 'Referrals not enabled for this raffle.' );
        }

        // Check raffle is still live
        if ( class_exists( 'Raffle_Public' ) && Raffle_Public::get_raffle_state( $raffle ) !== 'live' ) {
            return new WP_Error( 'raffle_not_live', 'This raffle is no longer accepting entries.' );
        }

        $bonus_qty = (int) $raffle->referral_bonus_entries;

        // Update referral stats
        $wpdb->update(
            $table,
            array(
                'referred_email' => sanitize_email( $referred_email ),
                'bonus_entries'  => (int) $referral->bonus_entries + $bonus_qty,
            ),
            array( 'id' => $referral->id ),
            array( '%s', '%d' ),
            array( '%d' )
        );

        // SEC-12 FIX: Actually generate bonus tickets for the referrer
        if ( $bonus_qty > 0 ) {
            // Check ticket availability before creating
            $available = (int) $raffle->total_tickets - (int) $raffle->sold_tickets;
            $bonus_qty = min( $bonus_qty, $available );

            if ( $bonus_qty > 0 ) {
                $wpdb->query( 'START TRANSACTION' );

                // Create a purchase record for the bonus entries
                $wpdb->insert(
                    $wpdb->prefix . 'raffle_purchases',
                    array(
                        'raffle_id'      => $raffle_id,
                        'buyer_name'     => 'Referral Bonus',
                        'buyer_email'    => $referral->user_email,
                        'quantity'       => $bonus_qty,
                        'total_amount'   => 0,
                        'payment_status' => 'completed',
                        'purchase_date'  => current_time( 'mysql' ),
                        'referral_code'  => $referral_code,
                        'entry_type'     => 'referral',
                    ),
                    array( '%d', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s' )
                );
                $purchase_id = $wpdb->insert_id;

                if ( $purchase_id ) {
                    $tickets = Raffle_Tickets::generate_tickets( $raffle_id, $purchase_id, $bonus_qty, $referral->user_email, false );
                    if ( is_wp_error( $tickets ) ) {
                        $wpdb->query( 'ROLLBACK' );
                    } else {
                        $wpdb->query( 'COMMIT' );
                    }
                } else {
                    $wpdb->query( 'ROLLBACK' );
                }
            }
        }

        // Set rate limit to prevent duplicate tracking (24 hours)
        set_transient( $rate_key, '1', DAY_IN_SECONDS );

        Raffle_Audit::log( $raffle_id, 'referral_tracked', array(
            'referral_code' => $referral_code,
            'referred'      => $referred_email,
            'bonus'         => $raffle->referral_bonus_entries,
        ), $referred_email );

        return array(
            'bonus_entries' => (int) $raffle->referral_bonus_entries,
            'referrer'      => $referral->user_email,
        );
    }

    /**
     * Get referral stats for a raffle.
     */
    public static function get_referral_stats( $raffle_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_referrals WHERE raffle_id = %d ORDER BY bonus_entries DESC",
            absint( $raffle_id )
        ) );
    }

    /**
     * AJAX: Get referral code for current user.
     */
    public function ajax_get_referral() {
        // SEC-1 FIX: Verify nonce to prevent CSRF
        if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'raffle_purchase_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security error. Please refresh the page.' ) );
        }

        $raffle_id   = absint( $_GET['raffle_id'] ?? $_POST['raffle_id'] ?? 0 );
        $user_email  = sanitize_email( wp_unslash( $_GET['email'] ?? $_POST['email'] ?? '' ) );

        if ( ! $raffle_id || ! $user_email ) {
            wp_send_json_error( 'Missing parameters' );
        }

        $code = self::get_referral_code( $raffle_id, $user_email );
        $url  = add_query_arg( 'ref', $code, get_permalink( $raffle_id ) );

        wp_send_json_success( array(
            'code' => $code,
            'url'  => $url,
        ) );
    }

    /**
     * AJAX: Track referral click.
     */
    public function ajax_track_referral() {
        // SEC-1 FIX: Verify nonce to prevent CSRF
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_purchase_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security error. Please refresh the page.' ) );
        }

        $raffle_id      = absint( $_POST['raffle_id'] ?? 0 );
        $referral_code  = sanitize_text_field( wp_unslash( $_POST['referral_code'] ?? '' ) );
        $referred_email = sanitize_email( wp_unslash( $_POST['referred_email'] ?? '' ) );

        if ( ! $raffle_id || ! $referral_code || ! $referred_email ) {
            wp_send_json_error( 'Missing parameters' );
        }

        $result = self::track_referral( $raffle_id, $referral_code, $referred_email );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }
}