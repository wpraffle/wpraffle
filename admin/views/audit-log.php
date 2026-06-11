<?php if ( ! defined( 'ABSPATH' ) ) exit;

// Handle CSV export
if ( isset( $_GET['export_csv'] ) && $_GET['export_csv'] === '1' ) {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorised.' );
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'raffle_audit_export' ) ) {
        wp_die( 'Security check failed.' );
    }
    Raffle_Audit::export_csv( array(
        'raffle_id'   => isset( $_GET['raffle_id'] ) ? absint( $_GET['raffle_id'] ) : 0,
        'action_type' => isset( $_GET['action_type'] ) ? sanitize_text_field( wp_unslash( $_GET['action_type'] ) ) : '',
        'date_from'   => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
        'date_to'     => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
    ) );
}

// Filters
$filter_raffle   = isset( $_GET['raffle_id'] ) ? absint( $_GET['raffle_id'] ) : 0;
$filter_action   = isset( $_GET['action_type'] ) ? sanitize_text_field( wp_unslash( $_GET['action_type'] ) ) : '';
$filter_from     = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
$filter_to       = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
$current_page    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$per_page        = 50;

$args = array(
    'raffle_id'   => $filter_raffle,
    'action_type' => $filter_action,
    'date_from'   => $filter_from,
    'date_to'     => $filter_to,
    'limit'       => $per_page,
    'offset'      => ( $current_page - 1 ) * $per_page,
);

$logs       = Raffle_Audit::get_logs( $args );
$total      = Raffle_Audit::get_total_count( $args );
$total_pages = ceil( $total / $per_page );
$action_types = Raffle_Audit::get_action_types();

// Raffles for filter dropdown
global $wpdb;
$raffles = $wpdb->get_results( "SELECT id, title FROM {$wpdb->prefix}raffles ORDER BY created_at DESC LIMIT 200" );

// Build export URL
$export_url = add_query_arg( array_merge( $_GET, array(
    'export_csv' => '1',
    '_wpnonce'   => wp_create_nonce( 'raffle_audit_export' ),
) ), admin_url( 'admin.php' ) );

// Action type badge colors
$action_colors = array(
    'purchase'        => '#dbeafe',
    'draw_completed'  => '#dcfce7',
    'draw_multiple'   => '#dcfce7',
    'instant_win'     => '#fef3c7',
    'admin_create'    => '#ede9fe',
    'admin_update'    => '#ede9fe',
    'admin_delete'    => '#fee2e2',
    'instant_win_awarded' => '#fef3c7',
);
$action_text_colors = array(
    'purchase'        => '#1e40af',
    'draw_completed'  => '#166534',
    'draw_multiple'   => '#166534',
    'instant_win'     => '#92400e',
    'admin_create'    => '#5b21b6',
    'admin_update'    => '#5b21b6',
    'admin_delete'    => '#991b1b',
    'instant_win_awarded' => '#92400e',
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php wpr_icon( 'shield', 'wpr-icon--sm' ); ?> Audit Log</h1>

    <!-- Filters -->
    <form method="get" style="margin: 20px 0;">
        <input type="hidden" name="page" value="raffle-audit">
        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; background: #fff; padding: 15px; border: 1px solid #c3c4c7; border-radius: 4px;">
            <select name="raffle_id" style="min-width:200px;">
                <option value="">All Raffles</option>
                <?php foreach ( $raffles as $r ) : ?>
                    <option value="<?php echo esc_attr( $r->id ); ?>" <?php selected( $filter_raffle, $r->id ); ?>>
                        #<?php echo esc_html( $r->id ); ?> — <?php echo esc_html( $r->title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="action_type" style="min-width:160px;">
                <option value="">All Actions</option>
                <?php foreach ( $action_types as $at ) : ?>
                    <option value="<?php echo esc_attr( $at ); ?>" <?php selected( $filter_action, $at ); ?>>
                        <?php echo esc_html( ucfirst( str_replace( '_', ' ', $at ) ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date_from" value="<?php echo esc_attr( $filter_from ); ?>" placeholder="From date" style="min-width:140px;">
            <input type="date" name="date_to" value="<?php echo esc_attr( $filter_to ); ?>" placeholder="To date" style="min-width:140px;">

            <button type="submit" class="button button-primary">Filter</button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=raffle-audit' ) ); ?>" class="button">Clear</a>

            <a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary" style="margin-left: auto;" target="_blank">
                <span class="dashicons dashicons-download" style="vertical-align:middle;margin-top:2px;"></span> Export CSV
            </a>
        </div>
    </form>

    <!-- Summary Stats -->
    <div style="display:flex;gap:15px;margin-bottom:20px;">
        <div style="background:#fff;padding:15px 20px;border:1px solid #e5e7eb;border-radius:8px;min-width:120px;text-align:center;">
            <div style="font-size:24px;font-weight:800;color:#1f2937;"><?php echo number_format( $total ); ?></div>
            <div style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;">Total Events</div>
        </div>
        <div style="background:#fff;padding:15px 20px;border:1px solid #e5e7eb;border-radius:8px;min-width:120px;text-align:center;">
            <div style="font-size:24px;font-weight:800;color:#166534;"><?php echo number_format( Raffle_Audit::get_total_count( array( 'action_type' => 'purchase' ) ) ); ?></div>
            <div style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;">Purchases</div>
        </div>
        <div style="background:#fff;padding:15px 20px;border:1px solid #e5e7eb;border-radius:8px;min-width:120px;text-align:center;">
            <div style="font-size:24px;font-weight:800;color:#5b21b6;"><?php echo number_format( Raffle_Audit::get_total_count( array( 'action_type' => 'draw_completed' ) ) ); ?></div>
            <div style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;">Draws</div>
        </div>
    </div>

    <!-- Log Table -->
    <table class="wp-list-table widefat fixed striped posts">
        <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th style="width:160px;">Date/Time</th>
                <th>Raffle</th>
                <th style="width:140px;">Action</th>
                <th style="width:130px;">User</th>
                <th>Details</th>
                <th style="width:200px;">Fairness Proof</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $logs ) ) : ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:30px;color:#6b7280;">
                        <span class="dashicons dashicons-shield" style="font-size:30px;width:30px;height:30px;display:block;margin:0 auto 10px;"></span>
                        No audit log entries found. Entries will appear here automatically when purchases, draws, or admin actions occur.
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ( $logs as $log ) :
                    $bg = $action_colors[ $log->action_type ] ?? '#f3f4f6';
                    $tc = $action_text_colors[ $log->action_type ] ?? '#374151';
                ?>
                    <tr>
                        <td><strong>#<?php echo esc_html( $log->id ); ?></strong></td>
                        <td style="font-size:12px;color:#6b7280;">
                            <?php echo esc_html( date_i18n( 'd M Y H:i:s', strtotime( $log->created_at ) ) ); ?>
                        </td>
                        <td>
                            <?php if ( $log->raffle_title ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=raffle-list&action=view&id=' . $log->raffle_id ) ); ?>">
                                    <?php echo esc_html( $log->raffle_title ); ?>
                                </a>
                            <?php elseif ( $log->raffle_id ) : ?>
                                #<?php echo esc_html( $log->raffle_id ); ?>
                            <?php else : ?>
                                <span style="color:#9ca3af;">System</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $tc ); ?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;">
                                <?php echo esc_html( str_replace( '_', ' ', $log->action_type ) ); ?>
                            </span>
                        </td>
                        <td style="font-size:13px;">
                            <?php
                            if ( $log->user_display ) {
                                echo esc_html( $log->user_display );
                            } elseif ( $log->user_id ) {
                                echo 'User #' . esc_html( $log->user_id );
                            } else {
                                echo '<span style="color:#9ca3af;">System/Guest</span>';
                            }
                            ?>
                        </td>
                        <td style="font-size:13px;max-width:350px;">
                            <?php
                            // Try to parse JSON details
                            $decoded = json_decode( $log->details, true );
                            if ( is_array( $decoded ) ) {
                                echo '<code style="font-size:11px;background:#f3f4f6;padding:2px 6px;border-radius:3px;">' . esc_html( substr( $log->details, 0, 200 ) ) . '</code>';
                            } else {
                                echo esc_html( substr( $log->details, 0, 250 ) );
                            }
                            ?>
                        </td>
                        <td style="font-size:11px;font-family:monospace;">
                            <?php if ( $log->fairness_proof ) : ?>
                                <span title="<?php echo esc_attr( $log->fairness_proof ); ?>" style="cursor:help;">
                                    <?php echo esc_html( substr( $log->fairness_proof, 0, 20 ) ); ?>...
                                </span>
                            <?php else : ?>
                                <span style="color:#d1d5db;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
    <div class="tablenav bottom" style="margin-top:10px;">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo number_format( $total ); ?> items</span>
            <span class="pagination-links">
                <?php
                $base_url = add_query_arg( array(
                    'page'        => 'raffle-audit',
                    'raffle_id'   => $filter_raffle,
                    'action_type' => $filter_action,
                    'date_from'   => $filter_from,
                    'date_to'     => $filter_to,
                ), admin_url( 'admin.php' ) );

                if ( $current_page > 1 ) {
                    echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ) . '">&lsaquo; Prev</a> ';
                }
                echo '<span class="paging-input" style="padding:0 10px;">Page ' . $current_page . ' of ' . $total_pages . '</span>';
                if ( $current_page < $total_pages ) {
                    echo ' <a class="button" href="' . esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ) . '">Next &rsaquo;</a>';
                }
                ?>
            </span>
        </div>
    </div>
    <?php endif; ?>

    <div style="margin-top:20px;padding:15px;background:#f0f6fc;border:1px solid #c3daf0;border-radius:4px;font-size:13px;color:#1e40af;">
        <strong>Authority Disclosure:</strong> Use the <strong>Export CSV</strong> button above to download a full audit trail for regulatory compliance.
        Each draw event includes a SHA-256 fairness proof that can be independently verified.
        Logs are retained according to your retention settings in <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpraffle-settings&tab=advanced' ) ); ?>">Settings → Advanced</a>.
    </div>
</div>