<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Duplicates {

    public function __construct() {
        add_action( 'wp_ajax_raffle_check_duplicates', array( $this, 'ajax_check_duplicates' ) );
        add_action( 'wp_ajax_raffle_fix_duplicates', array( $this, 'ajax_fix_duplicates' ) );
        add_action( 'wp_ajax_raffle_toggle_auto_fix', array( $this, 'ajax_toggle_auto_fix' ) );
    }

    /**
     * Detect duplicate tickets for a raffle.
     * Returns array of duplicated ticket_numbers with their ticket IDs.
     */
    public static function find_duplicates( $raffle_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_tickets';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT ticket_number, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ticket_ids
             FROM {$table}
             WHERE raffle_id = %d
             GROUP BY ticket_number
             HAVING cnt > 1",
            $raffle_id
        ) );
    }

    /**
     * Fix duplicate tickets by reassigning new unique numbers.
     * Keeps the first ticket (lowest ID) and reassigns duplicates.
     */
    public static function fix_duplicates( $raffle_id ) {
        global $wpdb;
        $table_tickets = $wpdb->prefix . 'raffle_tickets';
        $table_raffles = $wpdb->prefix . 'raffles';

        // Transaction with lock to avoid race conditions during correction
        $wpdb->query( 'START TRANSACTION' );

        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT total_tickets FROM {$table_raffles} WHERE id = %d FOR UPDATE",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'invalid_raffle', 'Raffle not found.' );
        }

        $duplicates = self::find_duplicates( $raffle_id );

        if ( empty( $duplicates ) ) {
            $wpdb->query( 'COMMIT' );
            return array( 'fixed' => 0, 'message' => 'No duplicates found.' );
        }

        // Get all currently assigned numbers
        $all_assigned = $wpdb->get_col( $wpdb->prepare(
            "SELECT ticket_number FROM {$table_tickets} WHERE raffle_id = %d",
            $raffle_id
        ) );
        $all_assigned = array_map( 'intval', $all_assigned );

        // Build set of taken numbers (without range() to avoid memory consumption)
        $taken_set = array_flip( array_values( array_unique( $all_assigned ) ) );
        $total     = $raffle->total_tickets;

        // Build pool of available numbers by iterating without range()
        $available = array();
        for ( $n = 1; $n <= $total; $n++ ) {
            if ( ! isset( $taken_set[ $n ] ) ) {
                $available[] = $n;
            }
        }

        $fixed_count = 0;

        foreach ( $duplicates as $dup ) {
            $ids = array_map( 'intval', explode( ',', $dup->ticket_ids ) );
            // Keep the first (lowest ID), reassign the rest
            array_shift( $ids );

            foreach ( $ids as $ticket_id ) {
                if ( empty( $available ) ) {
                    // No numbers available — delete the duplicate row
                    $wpdb->delete(
                        $table_tickets,
                        array( 'id' => $ticket_id ),
                        array( '%d' )
                    );
                    $fixed_count++;
                    continue;
                }

                // Pick a new random unique number
                $rand_index = random_int( 0, count( $available ) - 1 );
                $new_number = $available[ $rand_index ];
                array_splice( $available, $rand_index, 1 );

                $wpdb->update(
                    $table_tickets,
                    array( 'ticket_number' => $new_number ),
                    array( 'id' => $ticket_id ),
                    array( '%d' ),
                    array( '%d' )
                );

                $fixed_count++;
            }
        }

        // Recalculate sold_tickets to keep it accurate
        $actual_sold = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_tickets} WHERE raffle_id = %d",
            $raffle_id
        ) );

        $wpdb->update(
            $table_raffles,
            array( 'sold_tickets' => $actual_sold ),
            array( 'id' => $raffle_id ),
            array( '%d' ),
            array( '%d' )
        );

        $wpdb->query( 'COMMIT' );

        return array(
            'fixed'   => $fixed_count,
            'message' => $fixed_count . ' duplicate ticket(s) corrected.',
        );
    }

    /**
     * AJAX: Check for duplicates (admin only).
     */
    public function ajax_check_duplicates() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'No permission.' ) );
        }
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_draw_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }

        $raffle_id  = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        $duplicates = self::find_duplicates( $raffle_id );

        $total = 0;
        $details = array();
        foreach ( $duplicates as $dup ) {
            $extra = (int) $dup->cnt - 1;
            $total += $extra;
            $details[] = array(
                'ticket_number' => (int) $dup->ticket_number,
                'copies'        => (int) $dup->cnt,
            );
        }

        wp_send_json_success( array(
            'count'   => $total,
            'details' => $details,
        ) );
    }

    /**
     * AJAX: Fix duplicates (admin only).
     */
    public function ajax_fix_duplicates() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'No permission.' ) );
        }
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_draw_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }

        $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        $result    = self::fix_duplicates( $raffle_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Toggle auto-fix option (admin only).
     */
    public function ajax_toggle_auto_fix() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'No permission.' ) );
        }
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_draw_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }

        $enabled = isset( $_POST['enabled'] ) && $_POST['enabled'] === '1' ? '1' : '0';
        update_option( 'raffle_auto_fix_duplicates', $enabled );

        wp_send_json_success();
    }
}
