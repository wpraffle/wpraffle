<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Recurring draft creation preserves existing competitions and entry history. */
final class Raffle_Recurring {
    const OPTION = 'wpraffle_recurring_schedules';
    public static function init() {
        add_action( 'admin_post_wpraffle_recurring', array( __CLASS__, 'save' ) );
        add_action( 'wpraffle_recurring_check', array( __CLASS__, 'run' ) );
        if ( ! wp_next_scheduled( 'wpraffle_recurring_check' ) ) { wp_schedule_event( time() + 300, 'hourly', 'wpraffle_recurring_check' ); }
    }
    public static function save() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'wpraffle_recurring' );
        $action = isset( $_POST['operation'] ) && is_scalar( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : 'add';
        $result = Raffle_Transactions::run( array( 'wpraffle_recurring_schedules' ), static function() use ( $action ) {
            wp_cache_delete( self::OPTION, 'options' );
            $items = get_option( self::OPTION, array() ); $items = is_array( $items ) ? $items : array();
            if ( 'delete' === $action ) {
                $id = isset( $_POST['schedule_id'] ) && is_scalar( $_POST['schedule_id'] ) ? sanitize_key( wp_unslash( $_POST['schedule_id'] ) ) : '';
                unset( $items[ $id ] );
            } else {
                $source = isset( $_POST['source_id'] ) && is_scalar( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
                $interval = isset( $_POST['interval_days'] ) && is_scalar( $_POST['interval_days'] ) ? absint( $_POST['interval_days'] ) : 0;
                $count = isset( $_POST['count'] ) && is_scalar( $_POST['count'] ) ? absint( $_POST['count'] ) : 0;
                if ( ! $source || $interval < 1 || $interval > 365 || $count < 1 || $count > 52 || count( $items ) >= 50 || ! wpraffle_get_raffle( $source ) ) { return new WP_Error( 'invalid_schedule', 'Use an existing source competition, 1–365 days, and 1–52 copies. Maximum 50 schedules.' ); }
                $items[ wp_generate_uuid4() ] = array( 'source_id' => $source, 'interval_days' => $interval, 'remaining' => $count, 'next' => time() + $interval * DAY_IN_SECONDS, 'created_ids' => array() );
            }
            update_option( self::OPTION, $items, false ); return true;
        } );
        if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
        wp_safe_redirect( admin_url( 'admin.php?page=wpraffle-recurring&saved=1' ) ); exit;
    }
    private static function copy_prize_rules( $source_id, $new_id ) {
        global $wpdb;
        $groups = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}raffle_instant_win_groups WHERE raffle_id = %d", $source_id ) );
        $map = array();
        foreach ( $groups as $group ) {
            $data = (array) $group; $old = $data['id']; unset( $data['id'] );
            $data['raffle_id'] = $new_id; $data['created_at'] = current_time( 'mysql' );
            if ( false === $wpdb->insert( $wpdb->prefix . 'raffle_instant_win_groups', $data ) ) { return new WP_Error( 'copy_failed', 'Could not copy instant-win groups.' ); }
            $map[ $old ] = $wpdb->insert_id;
        }
        $wins = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}raffle_instant_wins WHERE raffle_id = %d", $source_id ) );
        foreach ( $wins as $win ) {
            $data = (array) $win;
            unset( $data['id'], $data['winner_email'], $data['purchase_id'], $data['won_at'] );
            $data['raffle_id'] = $new_id; $data['status'] = 'available'; $data['created_at'] = current_time( 'mysql' );
            $data['prize_group_id'] = isset( $map[ $win->prize_group_id ] ) ? $map[ $win->prize_group_id ] : null;
            if ( false === $wpdb->insert( $wpdb->prefix . 'raffle_instant_wins', $data ) ) { return new WP_Error( 'copy_failed', 'Could not copy instant-win rules.' ); }
        }
        $prizes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}raffle_prizes WHERE raffle_id = %d", $source_id ) );
        foreach ( $prizes as $prize ) {
            $data = (array) $prize; unset( $data['id'], $data['winner_ticket_id'] ); $data['raffle_id'] = $new_id;
            if ( false === $wpdb->insert( $wpdb->prefix . 'raffle_prizes', $data ) ) { return new WP_Error( 'copy_failed', 'Could not copy prize tiers.' ); }
        }
        return true;
    }

    public static function run() {
        return Raffle_Transactions::run( array( 'wpraffle_recurring_schedules' ), static function() {
            wp_cache_delete( self::OPTION, 'options' );
            $items = get_option( self::OPTION, array() ); $items = is_array( $items ) ? $items : array();
            $processed = 0;
            foreach ( $items as $key => &$item ) {
                if ( empty( $item['remaining'] ) || $item['next'] > time() || $processed >= 10 ) { continue; }
                $id = Raffle_Templates::clone_raffle( (int) $item['source_id'] );
                if ( is_wp_error( $id ) || ! $id ) { return is_wp_error( $id ) ? $id : new WP_Error( 'clone_failed', 'Could not create the scheduled draft.' ); }
                $rules = self::copy_prize_rules( (int) $item['source_id'], $id );
                if ( is_wp_error( $rules ) ) { return $rules; }
                global $wpdb;
                // Give each draft a fresh review window; never launch it automatically.
                $start = wp_date( 'Y-m-d H:i:s', time(), wp_timezone() );
                $draw = wp_date( 'Y-m-d H:i:s', time() + (int) $item['interval_days'] * DAY_IN_SECONDS, wp_timezone() );
                if ( false === $wpdb->update( $wpdb->prefix . 'raffles', array( 'start_date' => $start, 'draw_date' => $draw ), array( 'id' => $id ), array( '%s','%s' ), array( '%d' ) ) ) { return new WP_Error( 'schedule_write_failed', 'Could not set scheduled draft dates.' ); }
                $item['remaining']--; $item['next'] = time() + (int) $item['interval_days'] * DAY_IN_SECONDS;
                $item['created_ids'][] = $id; $item['created_ids'] = array_slice( $item['created_ids'], -52 ); $processed++;
            }
            unset( $item );
            if ( $processed && ! update_option( self::OPTION, $items, false ) ) { return new WP_Error( 'schedule_write_failed', 'Could not advance the schedule.' ); }
            return $processed;
        } );
    }
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $schedules = (array) get_option( self::OPTION, array() );
        require RAFFLE_SYSTEM_PATH . 'admin/views/recurring-raffles.php';
    }
}
