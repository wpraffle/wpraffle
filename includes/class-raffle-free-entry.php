<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Free_Entry {

    public function __construct() {
        add_action( 'wp_ajax_raffle_free_entry', array( $this, 'handle_free_entry' ) );
        add_action( 'wp_ajax_nopriv_raffle_free_entry', array( $this, 'handle_free_entry' ) );
    }

    /**
     * Handle free entry submission.
     */
    public function handle_free_entry() {
        // SEC-A12 FIX: Frontend JS sends rafflePublic.nonce (action: raffle_purchase_nonce)
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_purchase_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }

        $raffle_id   = absint( $_POST['raffle_id'] ?? 0 );
        $buyer_name  = sanitize_text_field( wp_unslash( $_POST['buyer_name'] ?? '' ) );
        $buyer_email = sanitize_email( wp_unslash( $_POST['buyer_email'] ?? '' ) );
        $answer_index = (int) ( wp_unslash( $_POST['answer_index'] ?? -1 ) );

        if ( ! $raffle_id || ! $buyer_name || ! $buyer_email ) {
            wp_send_json_error( array( 'message' => 'All fields are required.' ) );
        }
        if ( ! is_email( $buyer_email ) ) {
            wp_send_json_error( array( 'message' => 'A valid email address is required.' ) );
        }

        // Centralized rate limiting — keyed on the proxy-aware client IP so
        // an attacker rotating throwaway emails can't mint fresh buckets.
        $client_ip  = function_exists( 'wpraffle_get_client_ip' ) ? wpraffle_get_client_ip() : '';
        $rate_id    = $client_ip . '_' . $buyer_email;
        $rate_check = Raffle_Rate_Limiter::check_or_error( 'free_entry', $rate_id );
        if ( is_wp_error( $rate_check ) ) {
            wp_send_json_error( array( 'message' => $rate_check->get_error_message() ) );
        }

        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d AND status = 'active'",
            $raffle_id
        ) );

        if ( ! $raffle || ! $raffle->allow_free_entry ) {
            wp_send_json_error( array( 'message' => 'Free entries not available for this raffle.' ) );
        }

        // SEC-6 FIX: Enforce geo restrictions in free entry flow
        if ( class_exists( 'Raffle_Geo' ) && ! Raffle_Geo::check_eligibility( $raffle ) ) {
            wp_send_json_error( array( 'message' => 'This competition is not available in your region.' ) );
        }

        // 1.3.0 — Skill-question time limit + attempt limit (compliance).
        // Operators can now require the question to be answered within a
        // countdown and cap the number of wrong attempts per entrant.
        if ( ! empty( $raffle->enable_question ) ) {
            $attempt_key = 'wpraffle_qa_attempts_' . $raffle_id . '_' . md5( strtolower( $buyer_email ) );
            $attempts    = (int) get_transient( $attempt_key );

            // Attempt limit.
            $max_attempts = isset( $raffle->qa_max_attempts ) ? (int) $raffle->qa_max_attempts : 0;
            if ( $max_attempts > 0 && $attempts >= $max_attempts ) {
                wp_send_json_error( array( 'message' => 'You have reached the maximum number of attempts for this question.' ) );
            }

            // Time limit: a per-entrant window starts when they first view the
            // question (recorded by the frontend via a separate transient).
            $time_limit = isset( $raffle->qa_time_limit ) ? (int) $raffle->qa_time_limit : 0;
            if ( $time_limit > 0 ) {
                $started_key = 'wpraffle_qa_started_' . $raffle_id . '_' . md5( strtolower( $buyer_email ) );
                $started     = (int) get_transient( $started_key );
                if ( $started && ( time() - $started ) > $time_limit ) {
                    set_transient( $attempt_key, $max_attempts, DAY_IN_SECONDS ); // Lock further attempts.
                    wp_send_json_error( array( 'message' => 'The time limit for this question has expired.' ) );
                }
            }

            // Validate using main skill question (UK Regulations section)
            if ( ! empty( $raffle->question_answers ) ) {
                $correct_index = (int) $raffle->correct_answer_index;
                if ( $answer_index !== $correct_index ) {
                    // Record a failed attempt.
                    set_transient( $attempt_key, $attempts + 1, DAY_IN_SECONDS );
                    $remaining = ( $max_attempts > 0 ) ? max( 0, $max_attempts - $attempts - 1 ) : null;
                    $msg = $remaining !== null
                        ? sprintf( 'Incorrect answer. %d attempt(s) remaining.', $remaining )
                        : 'Incorrect answer. Please try again.';
                    wp_send_json_error( array( 'message' => $msg ) );
                }
            }
        }

        // Rate limiting: one free entry per client IP per raffle per day.
        // Keyed on IP (not email) so a rotating-email attacker cannot farm
        // entries; the email-scoped check below catches the rare case of one
        // user submitting legitimately from two IPs on the same day.
        $rate_key = 'raffle_free_' . md5( $raffle_id . '_' . $client_ip );
        if ( get_transient( $rate_key ) ) {
            wp_send_json_error( array( 'message' => 'You have already submitted a free entry today. Please try again tomorrow.' ) );
        }
        $email_rate_key = 'raffle_free_email_' . md5( $raffle_id . '_' . strtolower( $buyer_email ) );
        if ( get_transient( $email_rate_key ) ) {
            wp_send_json_error( array( 'message' => 'This email has already been used for a free entry today.' ) );
        }

        // Responsible-gambling gate — free entries still consume ticket slots
        // and self-excluded/locked buyers must not be able to enter.
        $rg_user_id = get_current_user_id();
        $rg = apply_filters( 'raffle_pre_purchase_check', true, $rg_user_id, 0.0, $buyer_email );
        if ( is_wp_error( $rg ) ) {
            wp_send_json_error( array( 'message' => $rg->get_error_message() ) );
        }

        // SEC-14 FIX: Cumulative ticket limit across ALL entry types (paid + free + referral)
        $max_per_user = (int) ( $raffle->max_tickets_per_user ?? 100 );
        $existing_total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM {$wpdb->prefix}raffle_purchases WHERE raffle_id = %d AND buyer_email = %s AND payment_status IN ('completed','pending')",
            $raffle_id, $buyer_email
        ) );

        if ( $existing_total >= $max_per_user ) {
            wp_send_json_error( array( 'message' => sprintf( 'You have reached the maximum of %d entries for this raffle.', $max_per_user ) ) );
        }

        // Check availability
        $available = $raffle->total_tickets - $raffle->sold_tickets;
        if ( $available <= 0 ) {
            wp_send_json_error( array( 'message' => 'No tickets available.' ) );
        }

        // Serialize both buyer quota checks and per-IP daily free entries.
        $result = Raffle_Transactions::run( array(
            'wpraffle_purchase_' . md5( strtolower( $buyer_email ) ),
            'wpraffle_free_ip_' . md5( $raffle_id . '_' . $client_ip ),
        ), static function() use ( $wpdb, $raffle_id, $buyer_name, $buyer_email, $answer_index, $max_per_user, $rate_key, $email_rate_key ) {
            // Pre-lock reads may have cached misses in this PHP request.
            wp_cache_delete( 'notoptions', 'options' );
            foreach ( array( $rate_key, $email_rate_key ) as $key ) {
                wp_cache_delete( $key, 'transient' );
                wp_cache_delete( '_transient_' . $key, 'options' );
                wp_cache_delete( '_transient_timeout_' . $key, 'options' );
            }
            if ( get_transient( $rate_key ) || get_transient( $email_rate_key ) ) {
                return new WP_Error( 'already_entered', 'A free entry has already been submitted today.' );
            }
            $locked = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d FOR UPDATE", $raffle_id ) );
            if ( ! $locked || 'active' !== $locked->status || ! $locked->allow_free_entry ||
                 ( $locked->start_date && $locked->start_date > current_time( 'mysql' ) ) ||
                 ( $locked->draw_date && $locked->draw_date <= current_time( 'mysql' ) ) ) {
                return new WP_Error( 'not_open', 'This competition is not open for free entries.' );
            }
            $existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(quantity),0) FROM {$wpdb->prefix}raffle_purchases WHERE raffle_id = %d AND buyer_email = %s AND payment_status IN ('completed','pending')", $raffle_id, $buyer_email ) );
            if ( $existing >= (int) $locked->max_tickets_per_user ) { return new WP_Error( 'entry_limit', 'The entry limit has been reached.' ); }
            $inserted = $wpdb->insert( $wpdb->prefix . 'raffle_purchases', array( 'raffle_id' => $raffle_id, 'buyer_name' => $buyer_name, 'buyer_email' => $buyer_email, 'quantity' => 1, 'total_amount' => 0, 'payment_status' => 'completed', 'purchase_date' => current_time( 'mysql' ), 'entry_type' => 'free' ), array( '%d','%s','%s','%d','%f','%s','%s','%s' ) );
            if ( ! $inserted || ! $wpdb->insert_id ) { return new WP_Error( 'insert_failed', 'Could not register the free entry.' ); }
            $purchase_id = $wpdb->insert_id;
            $tickets = Raffle_Tickets::generate_tickets( $raffle_id, $purchase_id, 1, $buyer_email, false );
            if ( is_wp_error( $tickets ) ) { return $tickets; }
            $ticket_number = $tickets[0];
            if ( false === $wpdb->insert( $wpdb->prefix . 'raffle_free_entries', array( 'raffle_id' => $raffle_id, 'buyer_name' => $buyer_name, 'buyer_email' => $buyer_email, 'answer_index' => $answer_index, 'ticket_number' => $ticket_number, 'status' => 'completed', 'created_at' => current_time( 'mysql' ) ), array( '%d','%s','%s','%d','%d','%s','%s' ) ) ) { return new WP_Error( 'insert_failed', 'Could not record the free entry.' ); }
            return $ticket_number;
        }, static function() use ( $rate_key, $email_rate_key ) {
            // Only successful commits consume the daily guard, under both locks.
            set_transient( $rate_key, '1', DAY_IN_SECONDS );
            set_transient( $email_rate_key, '1', DAY_IN_SECONDS );
        } );
        if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ) ); }
        $ticket_number = $result;

        // Record in centralized rate limiter
        Raffle_Rate_Limiter::hit( 'free_entry', $rate_id );

        Raffle_Audit::log( $raffle_id, 'free_entry', array(
            'email'         => $buyer_email,
            'ticket_number' => $ticket_number,
        ), $buyer_email );

        $total_digits = strlen( (string) $raffle->total_tickets );

        wp_send_json_success( array(
            'message' => 'Free entry submitted successfully!',
            'ticket'  => str_pad( $ticket_number, $total_digits, '0', STR_PAD_LEFT ),
        ) );
    }
}
