<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Draw {

    public function __construct() {
        add_action( 'wp_ajax_raffle_draw', array( $this, 'handle_draw' ) );
    }

    public function handle_draw( $raffle_id = null ) {
        // Support both AJAX and programmatic calls
        $is_ajax = wp_doing_ajax();

        if ( $is_ajax ) {
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
        }

        $raffle_id = absint( $raffle_id );
        if ( ! $raffle_id ) {
            return;
        }

        global $wpdb;
        $table_raffles  = $wpdb->prefix . 'raffles';
        $table_tickets  = $wpdb->prefix . 'raffle_tickets';

        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_raffles} WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            return $is_ajax ? wp_send_json_error( array( 'message' => 'Raffle not found.' ) ) : false;
        }

        if ( (int) $raffle->sold_tickets === 0 ) {
            return $is_ajax ? wp_send_json_error( array( 'message' => 'No tickets have been sold yet.' ) ) : false;
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
            return $is_ajax ? wp_send_json_error( array( 'message' => 'This raffle already has a winner selected.' ) ) : false;
        }

        // Multi-winner support
        if ( ! empty( $raffle->multi_winner ) && (int) $raffle->number_of_winners > 1 ) {
            $wpdb->query( 'COMMIT' );
            $results = Raffle_Prizes::draw_multiple_winners( $raffle_id, (int) $raffle->number_of_winners );
            if ( $is_ajax ) {
                if ( is_wp_error( $results ) ) {
                    wp_send_json_error( array( 'message' => $results->get_error_message() ) );
                }
                wp_send_json_success( array( 'message' => 'Multiple winners selected!', 'winners' => $results ) );
            }
            return $results;
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
            return $is_ajax ? wp_send_json_error( array( 'message' => 'No tickets sold.' ) ) : false;
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

        Raffle_Audit::log( $raffle_id, 'draw_completed', array(
            'ticket' => $winner_ticket->ticket_number,
            'proof'  => $proof,
        ), $is_ajax ? 'admin' : 'cron' );

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

        $result = array(
            'message'       => 'Winner selected!',
            'ticket_number' => str_pad( $winner_ticket->ticket_number, $total_digits, '0', STR_PAD_LEFT ),
            'buyer_name'    => $winner_ticket->buyer_name,
            'buyer_email'   => $winner_ticket->buyer_email,
        );

        if ( $is_ajax ) {
            wp_send_json_success( $result );
        }

        return $result;
    }
}
