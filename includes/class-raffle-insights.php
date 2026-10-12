<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Raffle_Insights {
    public static function init() {
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
        $report = self::report();
        $money = static function( $amount ) {
            return function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $amount ) ) : esc_html( number_format_i18n( $amount, 2 ) );
        };
        require RAFFLE_SYSTEM_PATH . 'admin/views/insights.php';
    }
}
