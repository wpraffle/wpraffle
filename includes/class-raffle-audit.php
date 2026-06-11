<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Audit {

    /**
     * Log an auditable event.
     *
     * @param int    $raffle_id   Raffle ID (0 for system-wide events).
     * @param string $action_type E.g. 'purchase', 'draw', 'instant_win', 'refund', 'admin_edit'.
     * @param string $details     Human-readable description of what happened.
     * @param string $proof       Optional fairness/verification proof (e.g. hash, seed).
     */
    public static function log( $raffle_id, $action_type, $details, $proof = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_audit_log';

        // Check the table exists before inserting
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return false;
        }

        // Support array details (serialize to JSON)
        if ( is_array( $details ) ) {
            $details = wp_json_encode( $details );
        }

        // If proof is passed as a context string (e.g. 'admin'/'cron'), store in details
        $proof_value = '';
        if ( is_string( $proof ) && in_array( $proof, array( 'admin', 'cron', 'system', 'ajax' ), true ) ) {
            $details .= ' [via ' . $proof . ']';
        } elseif ( ! empty( $proof ) ) {
            $proof_value = sanitize_text_field( $proof );
        }

        $inserted = $wpdb->insert( $table, array(
            'raffle_id'      => absint( $raffle_id ),
            'action_type'    => sanitize_text_field( $action_type ),
            'user_id'        => get_current_user_id() ?: null,
            'details'        => sanitize_textarea_field( $details ),
            'fairness_proof' => $proof_value ?: null,
            'created_at'     => current_time( 'mysql' ),
        ), array( '%d', '%s', '%d', '%s', '%s', '%s' ) );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Generate a cryptographic draw proof for fairness verification.
     *
     * @param int   $raffle_id
     * @param array $context  Additional context (e.g. ticket_count).
     * @return string SHA-256 hash proof.
     */
    public static function generate_draw_proof( $raffle_id, $context = array() ) {
        $seed = wp_generate_password( 32, false );
        $data = $raffle_id . '|' . $seed . '|' . wp_json_encode( $context ) . '|' . microtime( true );
        return hash( 'sha256', $data . wp_salt( 'auth' ) );
    }

    /**
     * Get audit logs with optional filters.
     *
     * @param array $args {
     *   @type int    $raffle_id    Filter by raffle.
     *   @type string $action_type  Filter by action type.
     *   @type string $date_from    Start date (Y-m-d).
     *   @type string $date_to      End date (Y-m-d).
     *   @type int    $limit        Max rows.
     *   @type int    $offset       Offset for pagination.
     * }
     * @return array Array of log objects.
     */
    public static function get_logs( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_audit_log';

        $defaults = array(
            'raffle_id'   => 0,
            'action_type' => '',
            'date_from'   => '',
            'date_to'     => '',
            'limit'       => 100,
            'offset'      => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $params = array();

        if ( ! empty( $args['raffle_id'] ) ) {
            $where[] = 'a.raffle_id = %d';
            $params[] = absint( $args['raffle_id'] );
        }
        if ( ! empty( $args['action_type'] ) ) {
            $where[] = 'a.action_type = %s';
            $params[] = sanitize_text_field( $args['action_type'] );
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'a.created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'a.created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }

        $where_clause = implode( ' AND ', $where );
        $limit  = absint( $args['limit'] );
        $offset = absint( $args['offset'] );

        $sql = "SELECT a.*, r.title as raffle_title, u.display_name as user_display
                FROM {$table} a
                LEFT JOIN {$wpdb->prefix}raffles r ON a.raffle_id = r.id
                LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                WHERE {$where_clause}
                ORDER BY a.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( $sql, $params );
        }

        return $wpdb->get_results( $sql );
    }

    /**
     * Get total log count for pagination.
     */
    public static function get_total_count( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_audit_log';

        $defaults = array(
            'raffle_id'   => 0,
            'action_type' => '',
            'date_from'   => '',
            'date_to'     => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $params = array();

        if ( ! empty( $args['raffle_id'] ) ) {
            $where[] = 'raffle_id = %d';
            $params[] = absint( $args['raffle_id'] );
        }
        if ( ! empty( $args['action_type'] ) ) {
            $where[] = 'action_type = %s';
            $params[] = sanitize_text_field( $args['action_type'] );
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }

        $where_clause = implode( ' AND ', $where );
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";

        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( $sql, $params );
        }

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Get distinct action types for filter dropdown.
     */
    public static function get_action_types() {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_audit_log';
        return $wpdb->get_col( "SELECT DISTINCT action_type FROM {$table} ORDER BY action_type" );
    }

    /**
     * Generate a fairness proof hash for a draw.
     *
     * @param int    $raffle_id
     * @param string $seed  Random seed used.
     * @param int    $winning_ticket
     * @return string SHA-256 hash.
     */
    public static function generate_fairness_proof( $raffle_id, $seed, $winning_ticket ) {
        return hash( 'sha256', $raffle_id . '|' . $seed . '|' . $winning_ticket . '|' . wp_salt( 'auth' ) );
    }

    /**
     * Export logs as CSV for authority requests.
     */
    public static function export_csv( $args = array() ) {
        $logs = self::get_logs( array_merge( $args, array( 'limit' => 10000 ) ) );

        $filename = 'raffle-audit-log-' . date( 'Y-m-d-His' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );

        $output = fopen( 'php://output', 'w' );

        // CSV headers
        fputcsv( $output, array( 'ID', 'Date/Time', 'Raffle ID', 'Raffle Title', 'Action', 'User', 'Details', 'Fairness Proof' ) );

        foreach ( $logs as $log ) {
            fputcsv( $output, array(
                $log->id,
                $log->created_at,
                $log->raffle_id,
                $log->raffle_title ?? 'N/A',
                $log->action_type,
                $log->user_display ?? ( $log->user_id ? 'User #' . $log->user_id : 'System/Guest' ),
                $log->details,
                $log->fairness_proof ?? '',
            ) );
        }

        fclose( $output );
        exit;
    }
}