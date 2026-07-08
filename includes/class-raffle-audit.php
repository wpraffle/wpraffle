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
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
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
            'user_id'     => 0,
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
        if ( ! empty( $args['user_id'] ) ) {
            $where[] = 'a.user_id = %d';
            $params[] = absint( $args['user_id'] );
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'a.created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'a.created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }

        $limit  = absint( $args['limit'] );
        $offset = absint( $args['offset'] );

        // SEC-9 FIX: Always use $wpdb->prepare() including LIMIT/OFFSET.
        $params[] = $limit;
        $params[] = $offset;

        // The WHERE clause is built from hardcoded SQL fragments only; all
        // dynamic values are passed as $params placeholders. implode() is
        // inlined so no intermediate variable is flagged.
        $query = "SELECT a.*, r.title as raffle_title, u.display_name as user_display
             FROM {$wpdb->prefix}raffle_audit_log a
             LEFT JOIN {$wpdb->prefix}raffles r ON a.raffle_id = r.id
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE " . implode( ' AND ', $where ) . "
             ORDER BY a.created_at DESC
             LIMIT %d OFFSET %d";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- $query is passed through $wpdb->prepare() with $params placeholders; the WHERE clause is built from hardcoded fragments only.
        return $wpdb->get_results( $wpdb->prepare( $query, $params ) );
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
            'user_id'     => 0,
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
        if ( ! empty( $args['user_id'] ) ) {
            $where[] = 'user_id = %d';
            $params[] = absint( $args['user_id'] );
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }

        if ( ! empty( $params ) ) {
            $query = "SELECT COUNT(*) FROM {$wpdb->prefix}raffle_audit_log WHERE " . implode( ' AND ', $where );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- $query is passed through $wpdb->prepare() with $params placeholders; the WHERE clause is built from hardcoded fragments only.
            return (int) $wpdb->get_var( $wpdb->prepare( $query, $params ) );
        }

        // No filters: $where is just array( '1=1' ), so the query is static.
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}raffle_audit_log WHERE 1=1" );
    }

    /**
     * Get distinct action types for filter dropdown.
     */
    public static function get_action_types() {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_audit_log';
        return $wpdb->get_col( "SELECT DISTINCT action_type FROM {$wpdb->prefix}raffle_audit_log ORDER BY action_type" );
    }

    /**
     * Get distinct actors (users) who appear in the log, for the actor filter.
     * Returns rows of {user_id, display_name}.
     */
    public static function get_actors() {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_audit_log';
        return $wpdb->get_results(
            "SELECT DISTINCT a.user_id, u.display_name
             FROM {$wpdb->prefix}raffle_audit_log a
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE a.user_id > 0
             ORDER BY u.display_name"
        );
    }

    /**
     * Render a log entry's details field as structured HTML.
     *
     * JSON details are decoded and rendered as a labelled definition list with
     * known keys mapped to human labels; unknown keys fall back to a generic
     * key/value row. Non-JSON details are returned esc_html'd as-is.
     *
     * @param string $details Raw details string (plain text or JSON).
     * @param bool   $full    When true, render the complete content (used in the
     *                        expandable detail row). When false, render a compact
     *                        summary for the main table cell.
     * @return string HTML (already escaped).
     */
    public static function render_details( $details, $full = false ) {
        if ( $details === null || $details === '' ) {
            return '<span style="color:#9ca3af;">—</span>';
        }

        $decoded = json_decode( $details, true );

        // Non-JSON — render as plain text (truncated in summary mode).
        if ( ! is_array( $decoded ) ) {
            return $full
                ? '<span>' . esc_html( $details ) . '</span>'
                : '<span>' . esc_html( wp_trim_words( $details, 18 ) ) . '</span>';
        }

        // Map known keys to human labels.
        $labels = array(
            'ticket'       => __( 'Winning Ticket', 'wpraffle' ),
            'proof'        => __( 'Fairness Proof', 'wpraffle' ),
            'email'        => __( 'Email', 'wpraffle' ),
            'code'         => __( 'Coupon Code', 'wpraffle' ),
            'amount'       => __( 'Amount', 'wpraffle' ),
            'type'         => __( 'Type', 'wpraffle' ),
            'title'        => __( 'Raffle', 'wpraffle' ),
            'pre_seed'     => __( 'Pre-draw Seed', 'wpraffle' ),
            'pre_proof'    => __( 'Pre-draw Proof', 'wpraffle' ),
            'expiry_days'  => __( 'Expiry (days)', 'wpraffle' ),
            'status'       => __( 'Status', 'wpraffle' ),
        );

        $rows = array();
        foreach ( $decoded as $key => $value ) {
            $label = isset( $labels[ $key ] ) ? $labels[ $key ] : ucfirst( str_replace( '_', ' ', $key ) );

            if ( is_array( $value ) ) {
                // Render nested arrays as a comma-separated, escaped list.
                $value = esc_html( implode( ', ', array_map( 'strval', $value ) ) );
            } else {
                $value = esc_html( (string) $value );
            }

            // Truncate long hash-like values in summary mode.
            if ( ! $full && preg_match( '/^[0-9a-f]{40,}$/i', (string) $decoded[ $key ] ) ) {
                $value = '<code>' . esc_html( substr( (string) $decoded[ $key ], 0, 20 ) ) . '…</code>';
            }

            $rows[] = '<dt>' . esc_html( $label ) . '</dt><dd>' . $value . '</dd>';

            if ( ! $full && count( $rows ) >= 3 ) {
                $more = count( $decoded ) - 3;
                if ( $more > 0 ) {
                    $rows[] = '<dt></dt><dd style="color:#6b7280;font-style:italic;">+' . $more . ' more</dd>';
                }
                break;
            }
        }

        return '<dl class="rs-audit-detail">' . implode( '', $rows ) . '</dl>';
    }

    /**
     * Export logs as CSV for authority requests.
     */
    public static function export_csv( $args = array() ) {
        // SEC-A11 FIX: Verify admin access before exporting audit data
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized.', 'Forbidden', array( 'response' => 403 ) );
        }

        $logs = self::get_logs( array_merge( $args, array( 'limit' => 10000 ) ) );

        $filename = 'raffle-audit-log-' . gmdate( 'Y-m-d-His' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- audit CSV export streams to php://output.
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

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closing the php://output stream for CSV export.
        fclose( $output );
        exit;
    }
}
