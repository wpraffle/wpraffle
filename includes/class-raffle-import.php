<?php
/**
 * WPRaffle — CSV Import/Export for tickets and instant-win config
 *
 * Phase 5 (1.3.0). Export mirrors the existing export_buyers_csv() pattern
 * (stream to php://output with a UTF-8 BOM); import parses an uploaded CSV
 * and bulk-inserts instant-win rules / prize groups with validation.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Import {

    public function __construct() {
        add_action( 'admin_post_wpraffle_export_tickets', array( $this, 'export_tickets' ) );
        add_action( 'admin_post_wpraffle_export_instant_wins', array( $this, 'export_instant_wins' ) );
        add_action( 'admin_post_wpraffle_import_instant_wins', array( $this, 'handle_import' ) );
    }

    /* ===================================================================
       Export — tickets
       =================================================================== */

    public function export_tickets() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wpraffle' ) );
        }
        $raffle_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        check_admin_referer( 'export_tickets_' . $raffle_id );

        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d", $raffle_id ) );
        if ( ! $raffle ) {
            wp_die( esc_html__( 'Raffle not found.', 'wpraffle' ) );
        }

        $tickets = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.ticket_number, t.buyer_email, p.buyer_name, p.purchase_date, p.payment_status
             FROM {$wpdb->prefix}raffle_tickets t
             JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id
             WHERE t.raffle_id = %d
             ORDER BY t.ticket_number",
            $raffle_id
        ) );

        $slug = sanitize_title( $raffle->title );
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="tickets-' . $slug . '-' . $raffle_id . '.csv"' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming CSV to php://output (WP_Filesystem does not support stdout streaming).
        $out = fopen( 'php://output', 'w' );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputs -- UTF-8 BOM for Excel.
        fputs( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, array( 'Ticket Number', 'Buyer Name', 'Buyer Email', 'Purchase Date', 'Payment Status' ) );
        foreach ( $tickets as $t ) {
            fputcsv( $out, array(
                Raffle_Tickets::format_ticket_number( $t->ticket_number, $raffle->total_tickets, $raffle ),
                $t->buyer_name,
                $t->buyer_email,
                $t->purchase_date,
                $t->payment_status,
            ) );
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closing the php://output stream for CSV export.
        fclose( $out );
        exit;
    }

    /* ===================================================================
       Export — instant wins
       =================================================================== */

    public function export_instant_wins() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wpraffle' ) );
        }
        $raffle_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        check_admin_referer( 'export_iw_' . $raffle_id );

        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT ticket_number, prize_name, prize_type, prize_config, status FROM {$wpdb->prefix}raffle_instant_wins WHERE raffle_id = %d ORDER BY ticket_number",
            $raffle_id
        ) );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="instant-wins-' . $raffle_id . '.csv"' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming CSV to php://output.
        $out = fopen( 'php://output', 'w' );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputs -- UTF-8 BOM for Excel.
        fputs( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, array( 'Ticket Number', 'Prize Name', 'Prize Type', 'Prize Config (JSON)', 'Status' ) );
        foreach ( $rows as $r ) {
            fputcsv( $out, array( $r->ticket_number, $r->prize_name, $r->prize_type, $r->prize_config, $r->status ) );
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closing the php://output stream for CSV export.
        fclose( $out );
        exit;
    }

    /* ===================================================================
       Import — instant wins (CSV upload)
       =================================================================== */

    /**
     * Expected CSV columns: Ticket Number, Prize Name, Prize Type, Prize Config (JSON)
     * Ticket Number may be left blank to auto-assign.
     */
    public function handle_import() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wpraffle' ) );
        }
        check_admin_referer( 'wpraffle_import_iw', 'wpraffle_import_iw_nonce' );

        $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        if ( ! $raffle_id ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'raffle-list', 'import_error' => 'no_raffle' ), admin_url( 'admin.php' ) ) );
            exit;
        }
        if ( empty( $_FILES['import_file']['tmp_name'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'raffle-list', 'import_error' => 'no_file' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- reading an uploaded temp file; WP_Filesystem is awkward for line-by-line CSV parsing.
        $handle = fopen( $_FILES['import_file']['tmp_name'], 'r' );
        if ( ! $handle ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'raffle-list', 'import_error' => 'read_failed' ), admin_url( 'admin.php' ) ) );
            exit;
        }

        global $wpdb;
        $table_instant = $wpdb->prefix . 'raffle_instant_wins';
        $table_raffles = $wpdb->prefix . 'raffles';

        $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT total_tickets FROM {$table_raffles} WHERE id = %d", $raffle_id ) );
        $total = $raffle ? (int) $raffle->total_tickets : 0;

        $header = fgetcsv( $handle );
        $imported = 0;
        $skipped = 0;
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $ticket_num = isset( $row[0] ) ? absint( $row[0] ) : 0;
            $prize_name = isset( $row[1] ) ? sanitize_text_field( $row[1] ) : '';
            $prize_type = isset( $row[2] ) ? sanitize_text_field( $row[2] ) : 'physical';
            $prize_cfg  = isset( $row[3] ) ? wp_unslash( $row[3] ) : '';

            if ( empty( $prize_name ) ) {
                $skipped++;
                continue;
            }
            // Validate prize_config JSON if present.
            if ( ! empty( $prize_cfg ) ) {
                $decoded = json_decode( $prize_cfg, true );
                if ( null === $decoded ) {
                    $prize_cfg = ''; // Discard invalid JSON.
                } else {
                    $prize_cfg = wp_json_encode( $decoded );
                }
            }

            // Auto-assign ticket number if blank or out of range.
            if ( $ticket_num < 1 || ( $total > 0 && $ticket_num > $total ) ) {
                $ticket_num = $this->find_free_ticket_number( $raffle_id, $total );
                if ( ! $ticket_num ) {
                    $skipped++;
                    continue;
                }
            } else {
                // Skip duplicates.
                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_instant} WHERE raffle_id = %d AND ticket_number = %d",
                    $raffle_id, $ticket_num
                ) );
                if ( $exists ) {
                    $skipped++;
                    continue;
                }
            }

            $insert = array(
                'raffle_id'     => $raffle_id,
                'ticket_number' => $ticket_num,
                'prize_name'    => $prize_name,
                'prize_type'    => $prize_type,
                'status'        => 'available',
            );
            $fmt = array( '%d', '%d', '%s', '%s', '%s' );
            if ( ! empty( $prize_cfg ) ) {
                $insert['prize_config'] = $prize_cfg;
                $fmt[] = '%s';
            }
            $result = $wpdb->insert( $table_instant, $insert, $fmt );
            if ( false !== $result ) {
                $imported++;
            } else {
                $skipped++;
            }
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closing the uploaded CSV handle after import.
        fclose( $handle );

        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( $raffle_id, 'instant_wins_import', array(
                'imported' => $imported,
                'skipped'  => $skipped,
            ), 'admin' );
        }

        wp_safe_redirect( add_query_arg( array(
            'page' => 'raffle-list',
            'import_done' => '1',
            'imported' => $imported,
            'skipped' => $skipped,
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Find a random unused ticket number for auto-assignment during import.
     */
    private function find_free_ticket_number( $raffle_id, $total ) {
        global $wpdb;
        $taken = $wpdb->get_col( $wpdb->prepare(
            "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE raffle_id = %d
             UNION SELECT ticket_number FROM {$wpdb->prefix}raffle_instant_wins WHERE raffle_id = %d",
            $raffle_id, $raffle_id
        ) );
        $taken_set = array_flip( array_map( 'intval', $taken ) );
        $available = array();
        for ( $i = 1; $i <= $total; $i++ ) {
            if ( ! isset( $taken_set[ $i ] ) ) {
                $available[] = $i;
            }
        }
        if ( empty( $available ) ) {
            return 0;
        }
        return $available[ random_int( 0, count( $available ) - 1 ) ];
    }
}
