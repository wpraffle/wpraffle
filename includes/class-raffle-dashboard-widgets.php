<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Dashboard_Widgets {

    public function __construct() {
        add_action( 'wp_dashboard_setup', array( $this, 'register_widgets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_raffle_dashboard_widget_data', array( $this, 'ajax_get_data' ) );
    }

    /**
     * Register dashboard widgets — only for users who can manage_options.
     */
    public function register_widgets() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'raffle_overview_widget',
            'Raffle Overview',
            array( $this, 'render_overview_widget' )
        );

        wp_add_dashboard_widget(
            'raffle_active_widget',
            'Active Raffles',
            array( $this, 'render_active_widget' )
        );

        wp_add_dashboard_widget(
            'raffle_recent_widget',
            'Recent Transactions',
            array( $this, 'render_recent_widget' )
        );

        // Side widget
        wp_add_dashboard_widget(
            'raffle_month_widget',
            'Raffle Sales This Month',
            array( $this, 'render_month_widget' )
        );
    }

    /**
     * Enqueue admin styles/scripts on the dashboard page.
     */
    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'index.php' ) {
            return;
        }
        wp_enqueue_style( 'wpraffle-icons', RAFFLE_SYSTEM_URL . 'assets/css/icons.css', array(), RAFFLE_SYSTEM_VERSION );
        wp_register_style( 'raffle-dashboard-widgets', false, array( 'wpraffle-icons' ) );
        wp_enqueue_style( 'raffle-dashboard-widgets' );
        wp_add_inline_style( 'raffle-dashboard-widgets', $this->get_widget_css() );

        wp_register_script( 'raffle-dashboard-widgets', false, array( 'jquery' ), RAFFLE_SYSTEM_VERSION, true );
        wp_enqueue_script( 'raffle-dashboard-widgets' );
        wp_add_inline_script( 'raffle-dashboard-widgets', 'var raffleDW = { curSym: "' . esc_js( wpr_currency_symbol() ) . '" };' );
        wp_add_inline_script( 'raffle-dashboard-widgets', $this->get_widget_js() );
    }

    /* ===================================================================
       Render Callbacks
       =================================================================== */

    public function render_overview_widget() {
        echo '<div class="rs-dw-overview" id="rs-dw-overview">';
        echo '<p><span class="spinner is-active" style="float:none;"></span> Loading stats…</p>';
        echo '</div>';
    }

    public function render_active_widget() {
        echo '<div class="rs-dw-active" id="rs-dw-active">';
        echo '<p><span class="spinner is-active" style="float:none;"></span> Loading active raffles…</p>';
        echo '</div>';
    }

    public function render_recent_widget() {
        echo '<div class="rs-dw-recent" id="rs-dw-recent">';
        echo '<p><span class="spinner is-active" style="float:none;"></span> Loading transactions…</p>';
        echo '</div>';
    }

    public function render_month_widget() {
        echo '<div class="rs-dw-month" id="rs-dw-month">';
        echo '<p><span class="spinner is-active" style="float:none;"></span> Loading…</p>';
        echo '</div>';
    }

    /* ===================================================================
       AJAX Handler — serves all widget data in one request
       =================================================================== */

    public function ajax_get_data() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        check_ajax_referer( 'raffle_dashboard_widget', 'nonce' );

        global $wpdb;
        $pfx = $wpdb->prefix;

        // Overview KPIs
        $total_revenue = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(total_amount), 0) FROM {$pfx}raffle_purchases WHERE payment_status = 'completed'"
        );
        $total_tickets_sold = (int) $wpdb->get_var(
            "SELECT COALESCE(SUM(sold_tickets), 0) FROM {$pfx}raffles"
        );
        $total_tickets_available = (int) $wpdb->get_var(
            "SELECT COALESCE(SUM(total_tickets), 0) FROM {$pfx}raffles"
        );
        $total_prize_value = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(prize_value), 0) FROM {$pfx}raffles"
        );
        $total_buyers = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT buyer_email) FROM {$pfx}raffle_purchases WHERE payment_status = 'completed'"
        );
        $sell_rate = $total_tickets_available > 0 ? round( ( $total_tickets_sold / $total_tickets_available ) * 100, 1 ) : 0;

        // Month comparison
        $revenue_this_month = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(total_amount), 0) FROM {$pfx}raffle_purchases
             WHERE payment_status = 'completed' AND MONTH(purchase_date) = MONTH(CURDATE()) AND YEAR(purchase_date) = YEAR(CURDATE())"
        );
        $tickets_this_month = (int) $wpdb->get_var(
            "SELECT COALESCE(SUM(quantity), 0) FROM {$pfx}raffle_purchases
             WHERE payment_status = 'completed' AND MONTH(purchase_date) = MONTH(CURDATE()) AND YEAR(purchase_date) = YEAR(CURDATE())"
        );
        $revenue_last_month = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(total_amount), 0) FROM {$pfx}raffle_purchases
             WHERE payment_status = 'completed' AND MONTH(purchase_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(purchase_date) = YEAR(CURDATE() - INTERVAL 1 MONTH)"
        );

        // Active raffles
        $active_raffles = $wpdb->get_results(
            "SELECT id, title, sold_tickets, total_tickets, ticket_price, draw_date
             FROM {$pfx}raffles WHERE status = 'active' ORDER BY draw_date ASC LIMIT 10"
        );

        // Recent transactions
        $recent = $wpdb->get_results(
            "SELECT p.buyer_name, p.quantity, p.total_amount, p.payment_status, p.purchase_date, r.title AS raffle_title
             FROM {$pfx}raffle_purchases p
             JOIN {$pfx}raffles r ON r.id = p.raffle_id
             ORDER BY p.purchase_date DESC LIMIT 10"
        );

        wp_send_json_success( array(
            'overview' => array(
                'revenue'       => $total_revenue,
                'net_profit'    => $total_revenue - $total_prize_value,
                'tickets'       => $total_tickets_sold,
                'buyers'        => $total_buyers,
                'sell_rate'     => $sell_rate,
            ),
            'month' => array(
                'this_month'     => $revenue_this_month,
                'last_month'     => $revenue_last_month,
                'tickets_month'  => $tickets_this_month,
                'change_pct'     => $revenue_last_month > 0 ? round( ( ( $revenue_this_month - $revenue_last_month ) / $revenue_last_month ) * 100, 1 ) : null,
            ),
            'active_raffles'   => $active_raffles,
            'recent'           => $recent,
        ) );
    }

    /* ===================================================================
       Inline CSS
       =================================================================== */

    private function get_widget_css() {
        return '
            .rs-dw-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 8px; }
            .rs-dw-kpi { background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; text-align: center; }
            .rs-dw-kpi-label { font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin: 0 0 4px; }
            .rs-dw-kpi-value { font-size: 20px; font-weight: 800; color: #1e293b; margin: 0; }
            .rs-dw-kpi-value.green { color: #10b981; }
            .rs-dw-raffle-row { display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
            .rs-dw-raffle-row:last-child { border-bottom: none; }
            .rs-dw-raffle-title { font-weight: 600; font-size: 13px; color: #1e293b; }
            .rs-dw-raffle-meta { font-size: 11px; color: #64748b; }
            .rs-dw-bar-wrap { height: 6px; background: #e2e8f0; border-radius: 3px; width: 120px; margin-top: 4px; }
            .rs-dw-bar-fill { height: 100%; border-radius: 3px; background: #6c5ce7; }
            .rs-dw-table { width: 100%; border-collapse: collapse; font-size: 12px; }
            .rs-dw-table th { text-align: left; padding: 6px 8px; color: #64748b; font-weight: 600; border-bottom: 1px solid #e2e8f0; }
            .rs-dw-table td { padding: 6px 8px; border-bottom: 1px solid #f8fafc; color: #334155; }
            .rs-dw-status { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
            .rs-dw-status-completed { background: #d1fae5; color: #065f46; }
            .rs-dw-status-pending { background: #fef3c7; color: #92400e; }
            .rs-dw-status-failed { background: #fee2e2; color: #991b1b; }
            .rs-dw-month-big { font-size: 28px; font-weight: 900; color: #1e293b; margin: 0; }
            .rs-dw-month-label { font-size: 12px; color: #64748b; margin: 0; }
            .rs-dw-month-change { font-size: 13px; font-weight: 700; margin-top: 4px; }
            .rs-dw-month-change.up { color: #10b981; }
            .rs-dw-month-change.down { color: #ef4444; }
            .rs-dw-footer { margin-top: 10px; text-align: right; }
            .rs-dw-footer a { font-size: 12px; }
        ';
    }

    /* ===================================================================
       Inline JS — loads data via AJAX after page loads
       =================================================================== */

    private function get_widget_js() {
        return "
        jQuery(document).ready(function($) {
            var nonce = '" . wp_create_nonce( 'raffle_dashboard_widget' ) . "';

            $.post(ajaxurl, { action: 'raffle_dashboard_widget_data', nonce: nonce }, function(res) {
                if (!res.success) return;
                var d = res.data;

                // Overview widget
                var o = d.overview;
                $('#rs-dw-overview').html(
                    '<div class=\"rs-dw-kpis\">' +
                        '<div class=\"rs-dw-kpi\"><p class=\"rs-dw-kpi-label\">Revenue</p><p class=\"rs-dw-kpi-value\">' + raffleDW.curSym + Number(o.revenue).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) + '</p></div>' +
                        '<div class=\"rs-dw-kpi\"><p class=\"rs-dw-kpi-label\">Net Profit</p><p class=\"rs-dw-kpi-value green\">' + raffleDW.curSym + Number(o.net_profit).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) + '</p></div>' +
                        '<div class=\"rs-dw-kpi\"><p class=\"rs-dw-kpi-label\">Tickets Sold</p><p class=\"rs-dw-kpi-value\">' + Number(o.tickets).toLocaleString() + '</p></div>' +
                        '<div class=\"rs-dw-kpi\"><p class=\"rs-dw-kpi-label\">Buyers</p><p class=\"rs-dw-kpi-value\">' + Number(o.buyers).toLocaleString() + '</p></div>' +
                        '<div class=\"rs-dw-kpi\"><p class=\"rs-dw-kpi-label\">Sell Rate</p><p class=\"rs-dw-kpi-value\">' + o.sell_rate + '%</p></div>' +
                    '</div>' +
                    '<div class=\"rs-dw-footer\"><a href=\"admin.php?page=raffle-system\">View Full Dashboard →</a></div>'
                );

                // Month widget
                var m = d.month;
                var changeHtml = '';
                if (m.change_pct !== null) {
                    var cls = m.change_pct >= 0 ? 'up' : 'down';
                    var arrow = m.change_pct >= 0 ? '↑' : '↓';
                    changeHtml = '<p class=\"rs-dw-month-change ' + cls + '\">' + arrow + ' ' + Math.abs(m.change_pct) + '% vs last month</p>';
                }
                $('#rs-dw-month').html(
                    '<p class=\"rs-dw-month-label\">This Month\\'s Revenue</p>' +
                    '<p class=\"rs-dw-month-big\">' + raffleDW.curSym + Number(m.this_month).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) + '</p>' +
                    changeHtml +
                    '<p style=\"font-size:12px;color:#64748b;margin-top:6px;\">' + Number(m.tickets_month).toLocaleString() + ' tickets sold</p>'
                );

                // Active raffles
                var ar = d.active_raffles;
                var arHtml = '';
                if (!ar || ar.length === 0) {
                    arHtml = '<p style=\"color:#64748b;font-size:13px;text-align:center;padding:15px 0;\">No active raffles.</p>';
                } else {
                    for (var i = 0; i < ar.length; i++) {
                        var r = ar[i];
                        var pct = r.total_tickets > 0 ? Math.round((r.sold_tickets / r.total_tickets) * 100) : 0;
                        arHtml += '<div class=\"rs-dw-raffle-row\">' +
                            '<div>' +
                                '<div class=\"rs-dw-raffle-title\">' + $('<span>').text(r.title).html() + '</div>' +
                                '<div class=\"rs-dw-raffle-meta\">' + Number(r.sold_tickets).toLocaleString() + ' / ' + Number(r.total_tickets).toLocaleString() + ' tickets · ' + raffleDW.curSym + Number(r.ticket_price).toFixed(2) + '/entry' +
                                (r.draw_date ? ' · Draw: ' + $('<span>').text(r.draw_date).html() : '') +
                                '</div>' +
                            '</div>' +
                            '<div>' +
                                '<div style=\"font-weight:800;font-size:14px;text-align:right;\">' + pct + '%</div>' +
                                '<div class=\"rs-dw-bar-wrap\"><div class=\"rs-dw-bar-fill\" style=\"width:' + pct + '%\"></div></div>' +
                            '</div>' +
                        '</div>';
                    }
                }
                arHtml += '<div class=\"rs-dw-footer\"><a href=\"admin.php?page=raffle-list\">Manage Raffles →</a></div>';
                $('#rs-dw-active').html(arHtml);

                // Recent transactions
                var txns = d.recent;
                var txnHtml = '';
                if (!txns || txns.length === 0) {
                    txnHtml = '<p style=\"color:#64748b;font-size:13px;text-align:center;padding:15px 0;\">No transactions yet.</p>';
                } else {
                    txnHtml = '<table class=\"rs-dw-table\"><thead><tr><th>Raffle</th><th>Buyer</th><th>Tickets</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
                    for (var j = 0; j < txns.length; j++) {
                        var t = txns[j];
                        var statusCls = t.payment_status === 'completed' ? 'completed' : (t.payment_status === 'pending' ? 'pending' : 'failed');
                        txnHtml += '<tr>' +
                            '<td>' + $('<span>').text(t.raffle_title).html() + '</td>' +
                            '<td>' + $('<span>').text(t.buyer_name).html() + '</td>' +
                            '<td>' + Number(t.quantity).toLocaleString() + '</td>' +
                            '<td>' + raffleDW.curSym + Number(t.total_amount).toFixed(2) + '</td>' +
                            '<td><span class=\"rs-dw-status rs-dw-status-' + statusCls + '\">' + $('<span>').text(t.payment_status).html() + '</span></td>' +
                        '</tr>';
                    }
                    txnHtml += '</tbody></table>';
                }
                txnHtml += '<div class=\"rs-dw-footer\"><a href=\"admin.php?page=raffle-system\">View All →</a></div>';
                $('#rs-dw-recent').html(txnHtml);
            });
        });
        ";
    }
}
