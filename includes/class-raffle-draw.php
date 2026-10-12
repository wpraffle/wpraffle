<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Draw {

    public function __construct() {
        add_action( 'wp_ajax_raffle_draw', array( $this, 'handle_draw' ) );
    }

    /**
     * AJAX entrypoint only. Verifies capability + nonce, then delegates to
     * do_draw(). Programmatic callers (cron, internals) must call do_draw()
     * directly — handle_draw() no longer silently skips auth for non-AJAX.
     */
    public function handle_draw() {
        // Only admins can draw via AJAX
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to perform the draw.' ) );
        }

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_draw_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }

        $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;

        if ( ! $raffle_id ) {
            wp_send_json_error( array( 'message' => 'Invalid raffle.' ) );
        }

        $result = self::do_draw( $raffle_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Multi-winner path returns an array of winners; single-winner returns
        // a result array. Normalise for the JSON response.
        if ( isset( $result['winners'] ) ) {
            wp_send_json_success( array( 'message' => 'Multiple winners selected!', 'winners' => $result['winners'] ) );
        }
        wp_send_json_success( $result );
    }

    /**
     * Execute the draw for a raffle. Capability/nonce checks are NOT performed
     * here — they are the caller's responsibility (handle_draw for AJAX,
     * raffle_system_handle_auto_draws for cron). Safe to call programmatically.
     *
     * @param int $raffle_id
     * @return array|WP_Error Result array on success, WP_Error on failure.
     */
    public static function do_draw( $raffle_id ) {
        $raffle_id = absint( $raffle_id );
        if ( ! $raffle_id ) { return new WP_Error( 'invalid_raffle', 'Invalid raffle.' ); }
        global $wpdb;
        $result = Raffle_Transactions::run( array(), static function() use ( $wpdb, $raffle_id ) {
            $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d FOR UPDATE", $raffle_id ) );
            if ( ! $raffle ) { return new WP_Error( 'not_found', 'Raffle not found.' ); }
            if ( $raffle->winner_ticket_id ) { return new WP_Error( 'already_drawn', 'This raffle already has a winner selected.' ); }
            if ( ! in_array( $raffle->status, array( 'active','finished' ), true ) ) { return new WP_Error( 'not_drawable', 'This competition is not available for a draw.' ); }
            if ( (int) $raffle->sold_tickets < 1 ) { return new WP_Error( 'no_tickets', 'No tickets have been sold yet.' ); }
            // Evaluate thresholds while the same row lock excludes allocation.
            $reason = class_exists( 'Raffle_Lifecycle' ) ? Raffle_Lifecycle::threshold_reason( $raffle ) : '';
            if ( $reason ) {
                if ( false === $wpdb->update( $wpdb->prefix . 'raffles', array( 'status' => 'failed', 'fail_reason' => $reason ), array( 'id' => $raffle_id ), array( '%s','%s' ), array( '%d' ) ) ) { return new WP_Error( 'fail_save_failed', 'Could not record the failed competition.' ); }
                return array( 'failed' => $reason, 'raffle' => $raffle );
            }
            if ( ! empty( $raffle->multi_winner ) && (int) $raffle->number_of_winners > 1 ) {
                $winners = Raffle_Prizes::draw_multiple_winners( $raffle_id, (int) $raffle->number_of_winners );
                return is_wp_error( $winners ) ? $winners : array( 'winners' => $winners, 'raffle' => $raffle );
            }
            $tickets = $wpdb->get_results( $wpdb->prepare( "SELECT t.*, p.buyer_name FROM {$wpdb->prefix}raffle_tickets t JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id WHERE t.raffle_id = %d AND p.payment_status = 'completed' ORDER BY t.id", $raffle_id ) );
            if ( ! $tickets ) { return new WP_Error( 'no_tickets', 'No eligible tickets sold.' ); }
            $proof = Raffle_Audit::generate_draw_proof( $raffle_id, array( 'ticket_count' => count( $tickets ) ) );
            $selection = class_exists( 'Raffle_Draw_Receipts' ) ? Raffle_Draw_Receipts::select( $raffle_id, $tickets, 1 ) : null;
            if ( is_wp_error( $selection ) ) { return $selection; }
            $winner = $selection ? $selection['winners'][0] : $tickets[ random_int( 0, count( $tickets ) - 1 ) ];
            if ( false === $wpdb->update( $wpdb->prefix . 'raffles', array( 'winner_ticket_id' => $winner->id, 'status' => 'finished' ), array( 'id' => $raffle_id ), array( '%d','%s' ), array( '%d' ) ) ) { return new WP_Error( 'draw_save_failed', 'Could not save the winner.' ); }
            if ( class_exists( 'Raffle_Draw_Receipts' ) && ! Raffle_Draw_Receipts::persist( $raffle_id, $tickets, array( $winner ), $selection ?: array() ) ) { return new WP_Error( 'receipt_failed', 'Could not record the draw result.' ); }
            return array( 'winner' => $winner, 'raffle' => $raffle, 'proof' => $proof );
        } );
        if ( is_wp_error( $result ) ) { return $result; }
        if ( function_exists( 'wpraffle_flush_raffle_cache' ) ) { wpraffle_flush_raffle_cache( $raffle_id ); }
        if ( isset( $result['failed'] ) ) {
            Raffle_Audit::log( $raffle_id, 'raffle_failed', array( 'reason' => $result['failed'] ), 'system' );
            do_action( 'wpraffle_raffle_failed', $raffle_id, $result['failed'] );
            return new WP_Error( 'under_subscribed', 'Raffle failed: ' . $result['failed'] . ' threshold not met.' );
        }
        $raffle = $result['raffle'];
        if ( isset( $result['winners'] ) ) {
            foreach ( $result['winners'] as $winner ) { if ( class_exists( 'Raffle_Email' ) ) { Raffle_Email::send_winner_notification( $winner['buyer_email'], $winner['buyer_name'], $raffle, $winner['ticket_number'] ); } }
            if ( $result['winners'] ) {
                $first = (object) $result['winners'][0]; $first->id = $first->ticket_id;
                do_action( 'raffle_draw_completed', $raffle_id, $first );
            }
            return array( 'winners' => $result['winners'] );
        }
        $winner = $result['winner'];
        do_action( 'raffle_draw_completed', $raffle_id, $winner );
        Raffle_Audit::log( $raffle_id, 'draw_completed', array( 'ticket' => $winner->ticket_number, 'proof' => $result['proof'] ), wp_doing_ajax() ? 'admin' : 'cron' );
        if ( class_exists( 'Raffle_Email' ) ) { Raffle_Email::send_winner_notification( $winner->buyer_email, $winner->buyer_name, $raffle, $winner->ticket_number ); }
        return array( 'message' => 'Winner selected!', 'ticket_id' => $winner->id, 'ticket_number' => str_pad( $winner->ticket_number, strlen( (string) $raffle->total_tickets ), '0', STR_PAD_LEFT ), 'buyer_name' => $winner->buyer_name, 'buyer_email' => $winner->buyer_email );
    }
}
