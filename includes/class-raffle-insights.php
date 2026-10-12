<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Raffle_Insights {
    public static function init() {
        add_action( 'admin_menu', static function() { add_submenu_page( 'raffle-system', __( 'Competition insights', 'wpraffle' ), __( 'Insights', 'wpraffle' ), 'manage_options', 'wpraffle-insights', array( __CLASS__, 'render' ) ); } );
    }
    public static function report() {
        global $wpdb;
        $summary = $wpdb->get_row( "SELECT COUNT(*) AS purchases, COALESCE(SUM(total_amount),0) AS gross, COALESCE(SUM(quantity),0) AS tickets, COUNT(DISTINCT buyer_email) AS customers FROM {$wpdb->prefix}raffle_purchases WHERE payment_status = 'completed'" );
        $repeat = (int) $wpdb->get_var( "SELECT COUNT(*) FROM (SELECT buyer_email FROM {$wpdb->prefix}raffle_purchases WHERE payment_status = 'completed' GROUP BY buyer_email HAVING COUNT(*) > 1) repeat_buyers" );
        $rows = $wpdb->get_results( "SELECT r.id, r.title, COUNT(p.id) AS purchases, COALESCE(SUM(p.total_amount),0) AS gross, COALESCE(SUM(p.quantity),0) AS tickets, COUNT(DISTINCT p.buyer_email) AS customers FROM {$wpdb->prefix}raffles r LEFT JOIN {$wpdb->prefix}raffle_purchases p ON p.raffle_id = r.id AND p.payment_status = 'completed' GROUP BY r.id,r.title ORDER BY gross DESC,r.id DESC LIMIT 100" );
        return array( 'summary' => $summary, 'repeat_buyers' => $repeat, 'competitions' => $rows );
    }
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $r = self::report(); $s = $r['summary'];
        echo '<div class="wrap"><h1>' . esc_html__( 'Competition insights', 'wpraffle' ) . '</h1><p>' . esc_html__( 'Current completed-purchase records only. Gross values are not profit, conversion rates or historical net revenue. Deleted/refunded records and old relists may no longer be represented.', 'wpraffle' ) . '</p><dl>';
        foreach ( array( 'purchases' => __( 'Completed purchases', 'wpraffle' ), 'tickets' => __( 'Confirmed tickets', 'wpraffle' ), 'customers' => __( 'Unique buyer email addresses', 'wpraffle' ), 'gross' => __( 'Gross recorded sales', 'wpraffle' ) ) as $key => $label ) { echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $s->$key ) . '</dd>'; }
        echo '<dt>' . esc_html__( 'Repeat buyer email addresses', 'wpraffle' ) . '</dt><dd>' . esc_html( $r['repeat_buyers'] ) . '</dd><dt>' . esc_html__( 'Average recorded purchase value', 'wpraffle' ) . '</dt><dd>' . esc_html( number_format_i18n( $s->purchases ? $s->gross / $s->purchases : 0, 2 ) ) . '</dd></dl><h2>' . esc_html__( 'Top 100 competitions by gross recorded sales', 'wpraffle' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Competition', 'wpraffle' ) . '</th><th>' . esc_html__( 'Purchases', 'wpraffle' ) . '</th><th>' . esc_html__( 'Tickets', 'wpraffle' ) . '</th><th>' . esc_html__( 'Customers', 'wpraffle' ) . '</th><th>' . esc_html__( 'Gross', 'wpraffle' ) . '</th></tr></thead><tbody>';
        foreach ( $r['competitions'] as $row ) { echo '<tr><td>' . esc_html( $row->title ) . '</td><td>' . esc_html( $row->purchases ) . '</td><td>' . esc_html( $row->tickets ) . '</td><td>' . esc_html( $row->customers ) . '</td><td>' . esc_html( $row->gross ) . '</td></tr>'; }
        echo '</tbody></table></div>';
    }
}
