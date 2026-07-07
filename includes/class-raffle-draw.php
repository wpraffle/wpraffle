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
        if ( ! $raffle_id ) {
            return new WP_Error( 'invalid_raffle', 'Invalid raffle.' );
        }

        global $wpdb;
        $table_raffles  = $wpdb->prefix . 'raffles';
        $table_tickets  = $wpdb->prefix . 'raffle_tickets';

        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_raffles} WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            return new WP_Error( 'not_found', 'Raffle not found.' );
        }

        if ( (int) $raffle->sold_tickets === 0 ) {
            return new WP_Error( 'no_tickets', 'No tickets have been sold yet.' );
        }

        // TRANSACTION: lock + atomic draw to prevent concurrent draws
        $wpdb->query( 'START TRANSACTION' );

        // Lock raffle row — prevents simultaneous draws
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_raffles} WHERE id = %d FOR UPDATE",
            $raffle_id
        ) );

        if ( $raffle->winner_ticket_id ) {
            $wpdb->query( 'COMMIT' );
            return new WP_Error( 'already_drawn', 'This raffle already has a winner selected.' );
        }

        // Multi-winner support
        if ( ! empty( $raffle->multi_winner ) && (int) $raffle->number_of_winners > 1 ) {
            // SEC-13 FIX: Keep transaction open during multi-winner draw (don't release the lock early)
            $results = Raffle_Prizes::draw_multiple_winners( $raffle_id, (int) $raffle->number_of_winners );
            $wpdb->query( 'COMMIT' );

            // Fire draw-completed action so charity allocations + wallet payouts run.
            if ( ! is_wp_error( $results ) && ! empty( $results ) ) {
                $first_winner = (object) $results[0];
                do_action( 'raffle_draw_completed', $raffle_id, $first_winner );
            }

            if ( is_wp_error( $results ) ) {
                return $results;
            }
            return array( 'winners' => $results );
        }

        // Get all sold tickets
        $tickets = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.*, p.buyer_name
             FROM {$table_tickets} t
             JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id
             WHERE t.raffle_id = %d",
            $raffle_id
        ) );

        if ( empty( $tickets ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'no_tickets', 'No tickets sold.' );
        }

        // Generate fairness proof
        $proof = Raffle_Audit::generate_draw_proof( $raffle_id, array(
            'ticket_count' => count( $tickets ),
        ) );

        // Select random winner using secure random_int()
        $winner_index  = random_int( 0, count( $tickets ) - 1 );
        $winner_ticket = $tickets[ $winner_index ];

        // Save winner and finalize raffle (inside transaction)
        $wpdb->update(
            $table_raffles,
            array(
                'winner_ticket_id' => $winner_ticket->id,
                'status'           => 'finished',
            ),
            array( 'id' => $raffle_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        $wpdb->query( 'COMMIT' );

        // Invalidate the cached raffle row — status flipped to finished + winner set.
        if ( function_exists( 'wpraffle_flush_raffle_cache' ) ) {
            wpraffle_flush_raffle_cache( $raffle_id );
        }

        // Feature expansion: fire action so charity allocations + wallet payouts run.
        do_action( 'raffle_draw_completed', $raffle_id, $winner_ticket );

        Raffle_Audit::log( $raffle_id, 'draw_completed', array(
            'ticket' => $winner_ticket->ticket_number,
            'proof'  => $proof,
        ), wp_doing_ajax() ? 'admin' : 'cron' );

        $total_digits = strlen( (string) $raffle->total_tickets );

        // Send winner notification email
        $winner_purchase = $wpdb->get_row( $wpdb->prepare(
            "SELECT p.buyer_name, p.buyer_email
             FROM {$wpdb->prefix}raffle_purchases p
             JOIN {$wpdb->prefix}raffle_tickets t ON t.purchase_id = p.id
             WHERE t.id = %d",
            $winner_ticket->id
        ) );
        if ( $winner_purchase && class_exists( 'Raffle_Email' ) ) {
            Raffle_Email::send_winner_notification(
                $winner_purchase->buyer_email,
                $winner_purchase->buyer_name,
                $raffle,
                $winner_ticket->ticket_number
            );
        }

        return array(
            'message'       => 'Winner selected!',
            'ticket_number' => str_pad( $winner_ticket->ticket_number, $total_digits, '0', STR_PAD_LEFT ),
            'buyer_name'    => $winner_ticket->buyer_name,
            'buyer_email'   => $winner_ticket->buyer_email,
        );
    }
}
