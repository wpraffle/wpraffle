<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Run DB work with deterministic advisory-lock ordering and no terminating responses. */
final class Raffle_Transactions {
    public static function run( $locks, $callback, $after_commit = null ) {
        global $wpdb;
        $acquired = array(); $started = false;
        $locks = array_unique( $locks ); sort( $locks, SORT_STRING );
        try {
            foreach ( $locks as $lock ) {
                if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) {
                    return new WP_Error( 'transaction_busy', __( 'Another request is being processed. Please try again shortly.', 'wpraffle' ) );
                }
                $acquired[] = $lock;
            }
            if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException( 'Could not start the transaction.' ); }
            $started = true; $result = call_user_func( $callback );
            if ( is_wp_error( $result ) ) { $wpdb->query( 'ROLLBACK' ); $started = false;
            return $result; }
            if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'Could not commit the transaction.' ); }
            $started = false;
            if ( $after_commit ) { call_user_func( $after_commit, $result ); }
            return $result;
        } catch ( Throwable $e ) {
            if ( $started ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'transaction_failed', __( 'The request could not be completed. Please try again or contact support.', 'wpraffle' ) );
        } finally {
            foreach ( array_reverse( $acquired ) as $lock ) { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
        }
    }
}
