<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Analytics {

    public function __construct() {
        add_action( 'wp_ajax_raffle_analytics_data', array( $this, 'get_analytics_data' ) );
    }

    public function get_analytics_data() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        check_ajax_referer( 'raffle_analytics_nonce', 'nonce' );

        global $wpdb;
        $pfx = $wpdb->prefix;

        $type = sanitize_text_field( wp_unslash( $_GET['type'] ?? '' ) );

        switch ( $type ) {
            case 'overview':
                wp_send_json_success( $this->get_overview( $pfx ) );
                break;
            case 'revenue_by_raffle':
                wp_send_json_success( $this->get_revenue_by_raffle( $pfx ) );
                break;
            case 'tickets_by_raffle':
                wp_send_json_success( $this->get_tickets_by_raffle( $pfx ) );
                break;
            case 'net_profit':
                wp_send_json_success( $this->get_net_profit( $pfx ) );
                break;
            case 'sales_trend':
                $period = sanitize_text_field( wp_unslash( $_GET['period'] ?? 'daily' ) );
                wp_send_json_success( $this->get_sales_trend( $pfx, $period ) );
                break;
            case 'top_buyers':
                wp_send_json_success( $this->get_top_buyers( $pfx ) );
                break;
            case 'recent_transactions':
                wp_send_json_success( $this->get_recent_transactions( $pfx ) );
                break;
            default:
                wp_send_json_error( 'Invalid type' );
        }
    }

    private function get_overview( $pfx ) {
        global $wpdb;

        $total_revenue = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(total_amount), 0) FROM {$pfx}raffle_purchases WHERE payment_status = 'completed'"
        );

        $total_tickets_sold = (int) $wpdb->get_var(
            "SELECT COALESCE(SUM(sold_tickets), 0) FROM {$pfx}raffles"
        );

        $total_tickets_available = (int) $wpdb->get_var(
            "SELECT COALESCE(SUM(total_tickets), 0) FROM {$pfx}raffles"
        );

        $active_raffles = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$pfx}raffles WHERE status = 'active'"
        );

        $total_raffles = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$pfx}raffles"
        );

        $total_prize_value = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(prize_value), 0) FROM {$pfx}raffles"
        );

        $total_buyers = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT buyer_email) FROM {$pfx}raffle_purchases WHERE payment_status = 'completed'"
        );

        $avg_ticket_price = (float) $wpdb->get_var(
            "SELECT COALESCE(AVG(ticket_price), 0) FROM {$pfx}raffles"
        );

        // Revenue this month vs last month
        $revenue_this_month = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(total_amount), 0) FROM {$pfx}raffle_purchases
             WHERE payment_status = 'completed' AND MONTH(purchase_date) = MONTH(CURDATE()) AND YEAR(purchase_date) = YEAR(CURDATE())"
        );

        $revenue_last_month = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(total_amount), 0) FROM {$pfx}raffle_purchases
             WHERE payment_status = 'completed' AND MONTH(purchase_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(purchase_date) = YEAR(CURDATE() - INTERVAL 1 MONTH)"
        );

        return array(
            'total_revenue'          => $total_revenue,
            'total_tickets_sold'     => $total_tickets_sold,
            'total_tickets_available' => $total_tickets_available,
            'active_raffles'         => $active_raffles,
            'total_raffles'          => $total_raffles,
            'total_prize_value'      => $total_prize_value,
            'net_profit'             => $total_revenue - $total_prize_value,
            'total_buyers'           => $total_buyers,
            'avg_ticket_price'       => $avg_ticket_price,
            'revenue_this_month'     => $revenue_this_month,
            'revenue_last_month'     => $revenue_last_month,
            'sell_rate'              => $total_tickets_available > 0 ? round( ( $total_tickets_sold / $total_tickets_available ) * 100, 1 ) : 0,
        );
    }

    private function get_revenue_by_raffle( $pfx ) {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT r.id, r.title,
                    COALESCE(SUM(p.total_amount), 0) AS revenue,
                    r.prize_value,
                    r.sold_tickets,
                    r.total_tickets
             FROM {$pfx}raffles r
             LEFT JOIN {$pfx}raffle_purchases p ON p.raffle_id = r.id AND p.payment_status = 'completed'
             GROUP BY r.id
             ORDER BY revenue DESC"
        );
    }

    private function get_tickets_by_raffle( $pfx ) {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT r.id, r.title, r.sold_tickets, r.total_tickets,
                    ROUND((r.sold_tickets / r.total_tickets) * 100, 1) AS sell_rate
             FROM {$pfx}raffles r
             WHERE r.total_tickets > 0
             ORDER BY sell_rate DESC"
        );
    }

    private function get_net_profit( $pfx ) {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT r.id, r.title, r.prize_value,
                    COALESCE(SUM(p.total_amount), 0) AS revenue,
                    (COALESCE(SUM(p.total_amount), 0) - r.prize_value) AS net_profit
             FROM {$pfx}raffles r
             LEFT JOIN {$pfx}raffle_purchases p ON p.raffle_id = r.id AND p.payment_status = 'completed'
             GROUP BY r.id
             ORDER BY net_profit DESC"
        );
    }

    private function get_sales_trend( $pfx, $period ) {
        global $wpdb;

        switch ( $period ) {
            case 'monthly':
                $sql = "SELECT DATE_FORMAT(purchase_date, '%Y-%m') AS label,
                               COUNT(*) AS transactions,
                               COALESCE(SUM(total_amount), 0) AS revenue,
                               COALESCE(SUM(quantity), 0) AS tickets
                        FROM {$pfx}raffle_purchases
                        WHERE payment_status = 'completed'
                        GROUP BY label
                        ORDER BY label ASC
                        LIMIT 24";
                break;

            case 'annual':
                $sql = "SELECT YEAR(purchase_date) AS label,
                               COUNT(*) AS transactions,
                               COALESCE(SUM(total_amount), 0) AS revenue,
                               COALESCE(SUM(quantity), 0) AS tickets
                        FROM {$pfx}raffle_purchases
                        WHERE payment_status = 'completed'
                        GROUP BY label
                        ORDER BY label ASC";
                break;

            default: // daily
                $sql = "SELECT DATE(purchase_date) AS label,
                               COUNT(*) AS transactions,
                               COALESCE(SUM(total_amount), 0) AS revenue,
                               COALESCE(SUM(quantity), 0) AS tickets
                        FROM {$pfx}raffle_purchases
                        WHERE payment_status = 'completed' AND purchase_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY label
                        ORDER BY label ASC";
                break;
        }

        return $wpdb->get_results( $sql );
    }

    private function get_top_buyers( $pfx ) {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT buyer_name, buyer_email,
                    COUNT(*) AS purchases,
                    COALESCE(SUM(quantity), 0) AS total_tickets,
                    COALESCE(SUM(total_amount), 0) AS total_spent
             FROM {$pfx}raffle_purchases
             WHERE payment_status = 'completed'
             GROUP BY buyer_email
             ORDER BY total_spent DESC
             LIMIT 10"
        );
    }

    private function get_recent_transactions( $pfx ) {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT p.id, p.buyer_name, p.buyer_email, p.quantity, p.total_amount,
                    p.payment_status, p.purchase_date, r.title AS raffle_title
             FROM {$pfx}raffle_purchases p
             JOIN {$pfx}raffles r ON r.id = p.raffle_id
             ORDER BY p.purchase_date DESC
             LIMIT 15"
        );
    }
}
