<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Reservations {

    /**
     * Reserve tickets for a user session (default 15 min hold).
     *
     * @internal Callers MUST verify capability/nonce/ownership before invoking
     *           this method — it performs no auth of its own so it can be used
     *           from both authenticated purchase flows and (future) logged-in
     *           number-picker UIs. Never expose via an unauthenticated endpoint.
     *
     * @param int                  $raffle_id
     * @param int[]                $ticket_numbers
     * @param string               $user_email
     * @param string               $session_id
     * @param int                  $minutes      Validated to [1, 60].
     * @return int Inserted row id.
     */
    public static function reserve_tickets( $raffle_id, $ticket_numbers, $user_email, $session_id, $minutes = 15 ) {
        global $wpdb;

        // Clamp the hold window to a sane range so a future caller can't pass
        // an unbounded/garbage value via $_POST.
        $minutes = max( 1, min( 60, (int) $minutes ) );
        $expires = gmdate( 'Y-m-d H:i:s', strtotime( "+{$minutes} minutes", time() ) );

        $wpdb->insert(
            $wpdb->prefix . 'raffle_reservations',
            array(
                'raffle_id'      => absint( $raffle_id ),
                'ticket_numbers' => wp_json_encode( array_map( 'intval', $ticket_numbers ) ),
                'user_email'     => sanitize_email( $user_email ),
                'session_id'     => sanitize_text_field( $session_id ),
                'expires_at'     => $expires,
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        Raffle_Audit::log( $raffle_id, 'tickets_reserved', array(
            'tickets'  => $ticket_numbers,
            'email'    => $user_email,
            'expires'  => $expires,
        ), $user_email );

        return $wpdb->insert_id;
    }

    /**
     * Get active reservations for a raffle.
     */
    public static function get_reserved_numbers( $raffle_id ) {
        global $wpdb;
        $now = current_time( 'mysql' );

        // Clean expired reservations
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}raffle_reservations WHERE raffle_id = %d AND expires_at < %s",
            $raffle_id, $now
        ) );

        // Get active reservation numbers
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT ticket_numbers FROM {$wpdb->prefix}raffle_reservations WHERE raffle_id = %d AND expires_at >= %s",
            $raffle_id, $now
        ) );

        $reserved = array();
        foreach ( $rows as $row ) {
            $nums = json_decode( $row->ticket_numbers, true );
            if ( is_array( $nums ) ) {
                $reserved = array_merge( $reserved, $nums );
            }
        }
        return array_map( 'intval', array_unique( $reserved ) );
    }

    /**
     * Release a reservation (after purchase completes or user cancels).
     */
    public static function release_reservation( $raffle_id, $session_id ) {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'raffle_reservations',
            array(
                'raffle_id'  => absint( $raffle_id ),
                'session_id' => sanitize_text_field( $session_id ),
            ),
            array( '%d', '%s' )
        );
    }

    /**
     * Cleanup expired reservations (called periodically).
     */
    public static function cleanup_expired() {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}raffle_reservations WHERE expires_at < %s",
            current_time( 'mysql' )
        ) );
    }
}
