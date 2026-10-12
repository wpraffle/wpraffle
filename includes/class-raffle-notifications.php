<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Account preferences apply only to optional lifecycle emails. */
final class Raffle_Notifications {
    const META = 'wpraffle_notification_preferences';
    public static function privacy_export( $email, $page = 1 ) {
        $user = get_user_by( 'email', $email ); $data = array();
        if ( 1 === (int) $page && $user ) {
            $data[] = array( 'group_id' => 'wpraffle-notifications', 'group_label' => 'WPRaffle notification preferences', 'item_id' => 'wpraffle-notifications-' . $user->ID, 'data' => array( array( 'name' => 'Optional email preferences', 'value' => wp_json_encode( self::get( $user->ID ) ) ) ) );
        }
        return array( 'data' => $data, 'done' => true );
    }
    public static function privacy_erase( $email, $page = 1 ) {
        $user = get_user_by( 'email', $email );
        $removed = $user ? delete_user_meta( $user->ID, self::META ) : false;
        return array( 'items_removed' => (bool) $removed, 'items_retained' => false, 'messages' => array(), 'done' => true );
    }
    public static function defaults() { return array( 'draw_reminder' => true, 'no_luck' => true, 'raffle_started' => true, 'raffle_extended' => true ); }
    public static function get( $user_id ) {
        $stored = get_user_meta( $user_id, self::META, true );
        return array_merge( self::defaults(), is_array( $stored ) ? array_intersect_key( $stored, self::defaults() ) : array() );
    }
    public static function allowed( $email, $type ) {
        if ( ! array_key_exists( $type, self::defaults() ) ) { return true; }
        $user = get_user_by( 'email', $email );
        return ! $user || ! empty( self::get( $user->ID )[ $type ] );
    }
    public static function save() {
        if ( ! is_user_logged_in() ) { wp_die( esc_html__( 'Please sign in.', 'wpraffle' ), '', array( 'response' => 401 ) ); }
        check_admin_referer( 'wpraffle_notifications' );
        $posted = isset( $_POST['preferences'] ) && is_array( $_POST['preferences'] ) ? wp_unslash( $_POST['preferences'] ) : array();
        $clean = array(); foreach ( self::defaults() as $type => $enabled ) { $clean[ $type ] = isset( $posted[ $type ] ) && '1' === $posted[ $type ]; }
        update_user_meta( get_current_user_id(), self::META, $clean );
        wp_safe_redirect( add_query_arg( array( 'sub' => 'notifications', 'saved' => '1' ), wc_get_account_endpoint_url( 'my-raffles' ) ) ); exit;
    }
    public static function render() {
        if ( ! is_user_logged_in() ) { return; }
        $p = self::get( get_current_user_id() );
        $labels = array( 'draw_reminder' => __( 'Draw reminders', 'wpraffle' ), 'no_luck' => __( 'Draw results when I have not won', 'wpraffle' ), 'raffle_started' => __( 'Competition launch announcements', 'wpraffle' ), 'raffle_extended' => __( 'Competition extension announcements', 'wpraffle' ) );
        echo '<section><h3>' . esc_html__( 'Notification preferences', 'wpraffle' ) . '</h3><p>' . esc_html__( 'Purchase confirmations, prize notifications, security and essential account messages remain enabled.', 'wpraffle' ) . '</p>';
        if ( isset( $_GET['saved'] ) ) { echo '<p role="status">' . esc_html__( 'Preferences saved.', 'wpraffle' ) . '</p>'; }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="wpraffle_notifications">';
        wp_nonce_field( 'wpraffle_notifications' );
        foreach ( $labels as $key => $label ) { echo '<p><label><input type="checkbox" name="preferences[' . esc_attr( $key ) . ']" value="1" ' . checked( ! empty( $p[ $key ] ), true, false ) . '> ' . esc_html( $label ) . '</label></p>'; }
        echo '<button type="submit" class="button">' . esc_html__( 'Save preferences', 'wpraffle' ) . '</button></form></section>';
    }
}

add_filter( 'wp_privacy_personal_data_exporters', static function( $items ) { $items['wpraffle-notifications'] = array( 'exporter_friendly_name' => 'WPRaffle notification preferences', 'callback' => array( 'Raffle_Notifications', 'privacy_export' ) ); return $items; } );
add_filter( 'wp_privacy_personal_data_erasers', static function( $items ) { $items['wpraffle-notifications'] = array( 'eraser_friendly_name' => 'WPRaffle notification preferences', 'callback' => array( 'Raffle_Notifications', 'privacy_erase' ) ); return $items; } );
