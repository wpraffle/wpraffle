<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Purchase {

    public function __construct() {
        add_action( 'wp_ajax_raffle_purchase', array( $this, 'handle_purchase' ) );
        add_action( 'wp_ajax_nopriv_raffle_purchase', array( $this, 'handle_purchase' ) );
    }

    public function handle_purchase() {
        // Nonce verification
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_purchase_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security error. Please refresh the page and try again.' ) );
        }

        $raffle_id   = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        $quantity    = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;
        $buyer_name  = isset( $_POST['buyer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['buyer_name'] ) ) : '';
        $buyer_email = isset( $_POST['buyer_email'] ) ? sanitize_email( wp_unslash( $_POST['buyer_email'] ) ) : '';

        // Validate required fields
        if ( ! $raffle_id || ! $quantity || ! $buyer_name || ! $buyer_email ) {
            wp_send_json_error( array( 'message' => 'All fields are required.' ) );
        }

        if ( ! is_email( $buyer_email ) ) {
            wp_send_json_error( array( 'message' => 'Invalid email address.' ) );
        }

        global $wpdb;
        $table_raffles    = $wpdb->prefix . 'raffles';
        $table_purchases  = $wpdb->prefix . 'raffle_purchases';

        // Get active raffle (lightweight read — no lock, for pre-validation only)
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_raffles} WHERE id = %d AND status = 'active'",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            wp_send_json_error( array( 'message' => 'Raffle not found or not active.' ) );
        }

        // SEC-6 FIX: Enforce geo restrictions in purchase flow
        if ( class_exists( 'Raffle_Geo' ) && ! Raffle_Geo::check_eligibility( $raffle ) ) {
            wp_send_json_error( array( 'message' => 'This competition is not available in your region.' ) );
        }

        // Validate max tickets per user (per-purchase limit)
        $max_tickets = isset( $raffle->max_tickets_per_user ) ? (int) $raffle->max_tickets_per_user : 100;
        if ( $quantity < 1 || $quantity > $max_tickets ) {
            wp_send_json_error( array( 'message' => sprintf( 'You can purchase between 1 and %d tickets.', $max_tickets ) ) );
        }

        // Cumulative per-user ticket limit: pre-check (re-verified inside the
        // transaction below to close the TOCTOU window between this SELECT and
        // the START TRANSACTION).
        $existing_tickets = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM {$table_purchases} WHERE raffle_id = %d AND buyer_email = %s AND payment_status IN ('completed','pending')",
            $raffle_id, $buyer_email
        ) );
        if ( ( $existing_tickets + $quantity ) > $max_tickets ) {
            $remaining_allowance = max( 0, $max_tickets - $existing_tickets );
            wp_send_json_error( array( 'message' => sprintf( 'You can only purchase %d more tickets for this raffle (limit: %d per user).', $remaining_allowance, $max_tickets ) ) );
        }

        // Responsible-gambling gate (defense-in-depth on the non-WC path).
        $rg_amount = (float) ( $quantity * $raffle->ticket_price );
        $rg = apply_filters( 'raffle_pre_purchase_check', true, get_current_user_id(), $rg_amount, $buyer_email );
        if ( is_wp_error( $rg ) ) {
            wp_send_json_error( array( 'message' => $rg->get_error_message() ) );
        }

        // Validate skill question
        $enable_question = isset( $raffle->enable_question ) ? (bool) $raffle->enable_question : false;
        if ( $enable_question ) {
            $answer_index = isset( $_POST['answer_index'] ) ? (int) $_POST['answer_index'] : -1;
            $correct_index = isset( $raffle->correct_answer_index ) ? (int) $raffle->correct_answer_index : 0;
            if ( $answer_index !== $correct_index ) {
                wp_send_json_error( array( 'message' => 'Incorrect answer to the skill question. Please try again.' ) );
            }
        }

        // Pre-check availability (actual check happens inside the transaction)
        $available = $raffle->total_tickets - $raffle->sold_tickets;
        if ( $quantity > $available ) {
            wp_send_json_error( array( 'message' => 'Not enough tickets available. ' . $available . ' remaining.' ) );
        }

        $total_amount = $quantity * $raffle->ticket_price;

        // If WooCommerce is available, reject direct purchase — must go through WooCommerce flow
        if ( Raffle_WooCommerce::is_available() ) {
            wp_send_json_error( array( 'message' => 'Payment must be processed through WooCommerce.' ) );
        }

        // SECURITY: Only allow direct purchases for free raffles (ticket_price = 0).
        // Paid raffles MUST go through a payment gateway (WooCommerce).
        if ( (float) $raffle->ticket_price > 0 ) {
            wp_send_json_error( array( 'message' => 'Payment is required for this raffle. Please use the checkout flow.' ) );
        }

        // Rate limiting: prevent spam purchases (max 1 request per 30 seconds per email+IP)
        $client_ip = self::get_client_ip();
        $rate_key  = 'raffle_rate_' . md5( $buyer_email . '_' . $client_ip );
        if ( get_transient( $rate_key ) ) {
            wp_send_json_error( array( 'message' => 'Please wait a moment before trying again.' ) );
        }
        set_transient( $rate_key, '1', 30 );

        // ATOMIC TRANSACTION: purchase + tickets are created together or not at all.
        // Acquire a MySQL advisory lock keyed on the buyer email so two
        // concurrent purchases by the same buyer can't both pass the
        // cumulative-limit check and exceed max_tickets_per_user (TOCTOU).
        $lock_name = 'wpraffle_purchase_' . md5( strtolower( $buyer_email ) );
        $lock_acquired = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock_name ) );
        if ( $lock_acquired !== 1 ) {
            wp_send_json_error( array( 'message' => 'Another purchase is being processed. Please try again shortly.' ) );
        }

        try {
            $wpdb->query( 'START TRANSACTION' );

            // Re-verify the cumulative limit inside the lock.
            $existing_in_lock = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(quantity), 0) FROM {$table_purchases} WHERE raffle_id = %d AND buyer_email = %s AND payment_status IN ('completed','pending')",
                $raffle_id, $buyer_email
            ) );
            if ( ( $existing_in_lock + $quantity ) > $max_tickets ) {
                $wpdb->query( 'ROLLBACK' );
                throw new RuntimeException( sprintf( 'You can only purchase %d more tickets for this raffle (limit: %d per user).', max( 0, $max_tickets - $existing_in_lock ), $max_tickets ) );
            }

        // Direct purchase (for testing or free raffles)
        $inserted = $wpdb->insert( $table_purchases, array(
            'raffle_id'      => $raffle_id,
            'buyer_name'     => $buyer_name,
            'buyer_email'    => $buyer_email,
            'quantity'       => $quantity,
            'total_amount'   => $total_amount,
            'payment_status' => 'completed',
            'purchase_date'  => current_time( 'mysql' ),
        ), array( '%d', '%s', '%s', '%d', '%f', '%s', '%s' ) );

        $purchase_id = $wpdb->insert_id;

        if ( ! $inserted || ! $purchase_id ) {
            $wpdb->query( 'ROLLBACK' );
            wp_send_json_error( array( 'message' => 'Error registering purchase. Please try again.' ) );
        }

            // Generate tickets (transaction managed by the caller)
            $tickets = Raffle_Tickets::generate_tickets( $raffle_id, $purchase_id, $quantity, $buyer_email, false );

            if ( is_wp_error( $tickets ) ) {
                $wpdb->query( 'ROLLBACK' );
                throw new RuntimeException( $tickets->get_error_message() );
            }

            // Check for instant wins (inside transaction so FOR UPDATE lock is effective)
            $instant_wins = Raffle_Instant_Wins::check_for_instant_wins( $raffle_id, $purchase_id, $tickets, $buyer_email );

            $wpdb->query( 'COMMIT' );
        } catch ( RuntimeException $e ) {
            if ( $wpdb->check_database_connection() ) {
                $wpdb->query( 'ROLLBACK' );
            }
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );

        // Audit log
        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( $raffle_id, 'purchase', "Free/direct purchase: {$quantity} ticket(s) by {$buyer_email} ({$buyer_name}). Purchase #{$purchase_id}.", '' );
        }

        // Send confirmation email (outside transaction — must not block the commit)
        Raffle_Email::send_purchase_confirmation( $purchase_id, $raffle, $tickets );

        // Format ticket numbers with leading zeros
        $total_digits = strlen( (string) $raffle->total_tickets );
        $formatted    = array_map( function ( $num ) use ( $total_digits ) {
            return str_pad( $num, $total_digits, '0', STR_PAD_LEFT );
        }, $tickets );

        wp_send_json_success( array(
            'message'     => 'Purchase completed successfully!',
            'tickets'     => $formatted,
            'purchase_id' => $purchase_id,
            'total'       => number_format( $total_amount, 2 ),
        ) );
    }

    /**
     * SEC-11 FIX: Get the real client IP, accounting for reverse proxies.
     */
    private static function get_client_ip() {
        $headers = array(
            'HTTP_CF_CONNECTING_IP', // CloudFlare
            'HTTP_X_FORWARDED_FOR',  // Standard proxy
            'HTTP_X_REAL_IP',        // Nginx proxy
            'REMOTE_ADDR',          // Direct connection
        );

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                // X-Forwarded-For can contain multiple IPs — take the first
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }

        return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
    }
}
