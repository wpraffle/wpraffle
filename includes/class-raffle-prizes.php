<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Prizes {

    public function __construct() {
        add_action( 'wp_ajax_raffle_get_prizes', array( $this, 'ajax_get_prizes' ) );
        add_action( 'wp_ajax_raffle_save_prizes', array( $this, 'ajax_save_prizes' ) );
    }

    /**
     * Get prizes for a raffle.
     */
    public static function get_prizes( $raffle_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_prizes WHERE raffle_id = %d ORDER BY position ASC",
            absint( $raffle_id )
        ) );
    }

    /**
     * Save prizes for a raffle (replaces all).
     */
    public static function save_prizes( $raffle_id, $prizes ) {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_prizes';

        // Delete existing
        $wpdb->delete( $table, array( 'raffle_id' => $raffle_id ), array( '%d' ) );

        foreach ( $prizes as $i => $prize ) {
            $wpdb->insert( $table, array(
                'raffle_id'   => absint( $raffle_id ),
                'position'    => absint( $i ),
                'prize_name'  => sanitize_text_field( $prize['prize_name'] ),
                'prize_value' => floatval( $prize['prize_value'] ),
                'prize_image' => esc_url_raw( $prize['prize_image'] ?? '' ),
            ), array( '%d', '%d', '%s', '%f', '%s' ) );
        }

        Raffle_Audit::log( $raffle_id, 'prizes_updated', array( 'count' => count( $prizes ) ), 'admin' );
    }

    /**
     * AJAX: Get prizes for a raffle.
     */
    public function ajax_get_prizes() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        $raffle_id = absint( $_GET['raffle_id'] ?? 0 );
        if ( ! $raffle_id ) {
            wp_send_json_error( 'Invalid raffle' );
        }
        wp_send_json_success( self::get_prizes( $raffle_id ) );
    }

    /**
     * AJAX: Save prizes for a raffle.
     */
    public function ajax_save_prizes() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_save_prizes' ) ) {
            wp_send_json_error( 'Security error' );
        }
        $raffle_id = absint( $_POST['raffle_id'] ?? 0 );
        $prizes_json = sanitize_text_field( wp_unslash( $_POST['prizes'] ?? '[]' ) );
        $prizes = json_decode( $prizes_json, true );
        if ( ! $raffle_id || ! is_array( $prizes ) ) {
            wp_send_json_error( 'Invalid data' );
        }

        self::save_prizes( $raffle_id, $prizes );
        wp_send_json_success( array( 'message' => 'Prizes saved' ) );
    }

    /**
     * Perform multi-winner draw. Returns array of winners.
     */
    private static function get_raffle_for_email( $raffle_id ) {
        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            absint( $raffle_id )
        ) );
        return $raffle ? $raffle : (object) array( 'id' => $raffle_id );
    }

    public static function draw_multiple_winners( $raffle_id, $num_winners ) {
        global $wpdb;

        $tickets = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.*, p.buyer_name, p.buyer_email
             FROM {$wpdb->prefix}raffle_tickets t
             JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id
             WHERE t.raffle_id = %d",
            $raffle_id
        ) );

        if ( count( $tickets ) < $num_winners ) {
            return new WP_Error( 'not_enough_tickets', 'Not enough sold tickets for ' . $num_winners . ' winners.' );
        }

        // Generate fairness proof
        $proof = Raffle_Audit::generate_draw_proof( $raffle_id, array(
            'ticket_count' => count( $tickets ),
            'num_winners'  => $num_winners,
        ) );

        $winners = array();
        $used    = array();

        for ( $i = 0; $i < $num_winners; $i++ ) {
            do {
                $idx = random_int( 0, count( $tickets ) - 1 );
            } while ( isset( $used[ $idx ] ) );
            $used[ $idx ] = true;
            $winners[] = $tickets[ $idx ];
        }

        // Assign winners to prizes
        $prizes = self::get_prizes( $raffle_id );
        $results = array();

        foreach ( $winners as $pos => $winner ) {
            $prize_id = isset( $prizes[ $pos ] ) ? $prizes[ $pos ]->id : 0;

            if ( $prize_id ) {
                $wpdb->update(
                    $wpdb->prefix . 'raffle_prizes',
                    array( 'winner_ticket_id' => $winner->id ),
                    array( 'id' => $prize_id ),
                    array( '%d' ),
                    array( '%d' )
                );
            }

            // First winner gets the main winner_ticket_id field
            if ( $pos === 0 ) {
                $wpdb->update(
                    $wpdb->prefix . 'raffles',
                    array( 'winner_ticket_id' => $winner->id, 'status' => 'finished' ),
                    array( 'id' => $raffle_id ),
                    array( '%d', '%s' ),
                    array( '%d' )
                );
            }

            $results[] = array(
                'position'      => $pos + 1,
                'ticket_id'     => $winner->id,
                'ticket_number' => $winner->ticket_number,
                'buyer_name'    => $winner->buyer_name,
                'buyer_email'   => $winner->buyer_email,
                'prize_id'      => $prize_id,
            );

            // Send winner email
            if ( class_exists( 'Raffle_Email' ) ) {
                Raffle_Email::send_winner_notification(
                    $winner->buyer_email,
                    $winner->buyer_name,
                    self::get_raffle_for_email( $raffle_id ),
                    $winner->ticket_number
                );
            }

            Raffle_Audit::log( $raffle_id, 'winner_drawn', array(
                'position' => $pos + 1,
                'ticket'   => $winner->ticket_number,
                'proof'    => $proof,
            ), 'system' );
        }

        return $results;
    }
}