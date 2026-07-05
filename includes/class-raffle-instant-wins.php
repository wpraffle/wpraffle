<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Instant_Wins {

    public function __construct() {
        add_action( 'wp_ajax_raffle_add_instant_win', array( $this, 'ajax_add_instant_win' ) );
        add_action( 'wp_ajax_raffle_delete_instant_win', array( $this, 'ajax_delete_instant_win' ) );
    }

    public function ajax_add_instant_win() {
        check_ajax_referer( 'raffle_draw_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }

        $raffle_id   = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        $prize_name  = isset( $_POST['prize_name'] ) ? sanitize_text_field( wp_unslash( $_POST['prize_name'] ) ) : '';
        $ticket_num  = isset( $_POST['ticket_number'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_number'] ) ) : '';
        $quantity    = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;
        if ( $quantity < 1 ) $quantity = 1;

        if ( ! $raffle_id || empty( $prize_name ) ) {
            wp_send_json_error( array( 'message' => 'Prize name is required.' ) );
        }

        global $wpdb;
        $table_instant = $wpdb->prefix . 'raffle_instant_wins';
        $table_raffles = $wpdb->prefix . 'raffles';

        // Wrap lookup + insert in a transaction with a FOR UPDATE lock on the
        // raffle row so two concurrent admin requests (or a double-click)
        // can't both pass the "already an instant win?" check and insert
        // duplicate rows for the same ticket number.
        $wpdb->query( 'START TRANSACTION' );
        $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_raffles} WHERE id = %d FOR UPDATE", $raffle_id ) );
        if ( ! $raffle ) {
            $wpdb->query( 'ROLLBACK' );
            wp_send_json_error( array( 'message' => 'Raffle not found.' ) );
        }

        if ( empty( $ticket_num ) ) {
            // Generate random unused ticket number
            $taken = $wpdb->get_col( $wpdb->prepare(
                "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE raffle_id = %d UNION SELECT ticket_number FROM {$table_instant} WHERE raffle_id = %d",
                $raffle_id, $raffle_id
            ) );
            $taken_set = array_flip( array_map( 'intval', $taken ) );

            $available = array();
            for ( $i = 1; $i <= $raffle->total_tickets; $i++ ) {
                if ( ! isset( $taken_set[ $i ] ) ) {
                    $available[] = $i;
                }
            }

            if ( empty( $available ) ) {
                $wpdb->query( 'ROLLBACK' );
                wp_send_json_error( array( 'message' => 'No available tickets left for instant wins.' ) );
            }

            $ticket_num = $available[ random_int( 0, count( $available ) - 1 ) ];
        } else {
            $ticket_num = absint( $ticket_num );
            if ( $ticket_num < 1 || $ticket_num > $raffle->total_tickets ) {
                $wpdb->query( 'ROLLBACK' );
                wp_send_json_error( array( 'message' => 'Invalid ticket number.' ) );
            }

            // Check if it's already an instant win or sold
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_instant} WHERE raffle_id = %d AND ticket_number = %d",
                $raffle_id, $ticket_num
            ) );
            if ( $exists ) {
                $wpdb->query( 'ROLLBACK' );
                wp_send_json_error( array( 'message' => 'Ticket is already an instant win.' ) );
            }
        }

        // Support quantity > 1: create multiple rows with different ticket numbers
        $added = 0;
        for ( $q = 0; $q < $quantity; $q++ ) {
            $current_ticket = $ticket_num;

            if ( $quantity > 1 || empty( $current_ticket ) ) {
                // For quantity > 1, always auto-assign ticket numbers
                $taken = $wpdb->get_col( $wpdb->prepare(
                    "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE raffle_id = %d UNION SELECT ticket_number FROM {$table_instant} WHERE raffle_id = %d",
                    $raffle_id, $raffle_id
                ) );
                $taken_set = array_flip( array_map( 'intval', $taken ) );

                $available = array();
                for ( $i = 1; $i <= $raffle->total_tickets; $i++ ) {
                    if ( ! isset( $taken_set[ $i ] ) ) {
                        $available[] = $i;
                    }
                }

                if ( empty( $available ) ) {
                    if ( $added > 0 ) {
                        // Some were added already — commit what we have.
                        $wpdb->query( 'COMMIT' );
                        wp_send_json_success( array( 'message' => $added . ' instant win(s) added. No more available tickets.' ) );
                    }
                    $wpdb->query( 'ROLLBACK' );
                    wp_send_json_error( array( 'message' => 'No available tickets left for instant wins.' ) );
                }

                $current_ticket = $available[ random_int( 0, count( $available ) - 1 ) ];
            }

            $result = $wpdb->insert( $table_instant, array(
                'raffle_id'     => $raffle_id,
                'ticket_number' => $current_ticket,
                'prize_name'    => $prize_name,
                'status'        => 'available',
            ), array( '%d', '%d', '%s', '%s' ) );

            if ( false !== $result ) {
                $added++;
            }
        }

        if ( $added === 0 ) {
            $wpdb->query( 'ROLLBACK' );
            wp_send_json_error( array( 'message' => 'Database error.' ) );
        }

        $wpdb->query( 'COMMIT' );
        wp_send_json_success( array( 'message' => $added . ' instant win(s) added.' ) );
    }

    public function ajax_delete_instant_win() {
        check_ajax_referer( 'raffle_draw_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        global $wpdb;
        $table_instant = $wpdb->prefix . 'raffle_instant_wins';

        $wpdb->delete( $table_instant, array( 'id' => $id ), array( '%d' ) );

        wp_send_json_success( array( 'message' => 'Instant win deleted.' ) );
    }

    public static function get_instant_wins( $raffle_id ) {
        global $wpdb;
        $table_instant = $wpdb->prefix . 'raffle_instant_wins';
        $table_purchases = $wpdb->prefix . 'raffle_purchases';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT iw.*, p.buyer_name as winner_name 
             FROM {$table_instant} iw
             LEFT JOIN {$table_purchases} p ON iw.purchase_id = p.id
             WHERE iw.raffle_id = %d 
             ORDER BY iw.created_at DESC",
            $raffle_id
        ) );
    }

    public static function get_initials( $name ) {
        if ( empty( $name ) ) {
            return '';
        }
        $parts = array_filter( explode( ' ', trim( $name ) ) );
        $initials = array_map( function( $part ) {
            return strtoupper( mb_substr( $part, 0, 1 ) );
        }, $parts );
        return implode( '.', $initials );
    }

    public static function check_for_instant_wins( $raffle_id, $purchase_id, $ticket_numbers, $buyer_email ) {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_instant_wins';
        $won_prizes = array();

        if ( empty( $ticket_numbers ) ) {
            return $won_prizes;
        }

        $placeholders = implode( ',', array_fill( 0, count( $ticket_numbers ), '%d' ) );
        $args = array_merge( array( $raffle_id ), $ticket_numbers );

        $wins = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE raffle_id = %d AND status = 'available' AND ticket_number IN ({$placeholders}) FOR UPDATE",
            $args
        ) );

        foreach ( $wins as $win ) {
            $wpdb->update(
                $table,
                array(
                    'status'       => 'won',
                    'winner_email' => $buyer_email,
                    'purchase_id'  => $purchase_id
                ),
                array( 'id' => $win->id ),
                array( '%s', '%s', '%d' ),
                array( '%d' )
            );
            $won_prizes[] = $win;
        }

        return $won_prizes;
    }
}
