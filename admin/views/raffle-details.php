<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$total_tickets = $raffle->total_tickets;
$revenue      = $wpdb->get_var( $wpdb->prepare(
    "SELECT SUM(total_amount) FROM {$wpdb->prefix}raffle_purchases WHERE raffle_id = %d AND payment_status = 'completed'",
    $raffle->id
) );
$remaining = $raffle->total_tickets - $raffle->sold_tickets;
$progress  = ( $raffle->total_tickets > 0 ) ? round( ( $raffle->sold_tickets / $raffle->total_tickets ) * 100 ) : 0;

$instant_wins = Raffle_Instant_Wins::get_instant_wins( $raffle->id );
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo esc_html( $raffle->title ); ?>
        <span style="background:<?php echo $raffle->status === 'active' ? '#dcfce7; color:#166534;' : '#f3f4f6; color:#374151;'; ?>; padding:4px 10px; border-radius:4px; font-weight:600; font-size:13px; margin-left:10px; display:inline-block; vertical-align:middle;">
            <?php echo $raffle->status === 'active' ? 'Active' : 'Finished'; ?>
        </span>
    </h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=raffle-list' ) ); ?>" class="page-title-action">Back to List</a>
    <a href="<?php echo esc_url( admin_url( "admin.php?page=raffle-list&action=edit&id={$raffle->id}" ) ); ?>" class="page-title-action text-primary">Edit Raffle</a>
    <hr class="wp-header-end">

    <!-- Stats summary grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top:20px;">
        <div class="card" style="margin: 0; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
            <h2 style="margin: 0 0 5px; font-size: 14px; color: #64748b;">Tickets Sold</h2>
            <p style="margin: 0; font-size: 20px; font-weight: 700; color: #1d2327;"><?php echo esc_html( $raffle->sold_tickets ); ?> / <?php echo esc_html( $raffle->total_tickets ); ?></p>
        </div>
        <div class="card" style="margin: 0; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
            <h2 style="margin: 0 0 5px; font-size: 14px; color: #64748b;">Tickets Remaining</h2>
            <p style="margin: 0; font-size: 20px; font-weight: 700; color: #1d2327;"><?php echo esc_html( $remaining ); ?></p>
        </div>
        <div class="card" style="margin: 0; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
            <h2 style="margin: 0 0 5px; font-size: 14px; color: #64748b;">Revenue Generated</h2>
            <p style="margin: 0; font-size: 20px; font-weight: 700; color: #10b981;"><?php echo esc_html( wpr_price( $revenue ?: 0 ) ); ?></p>
        </div>
        <div class="card" style="margin: 0; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
            <h2 style="margin: 0 0 5px; font-size: 14px; color: #64748b;">Draw Date</h2>
            <p style="margin: 0; font-size: 16px; font-weight: 700; color: #1d2327; padding-top:4px;">
                <?php echo $raffle->draw_date ? esc_html( date_i18n( 'd/m/Y H:i', strtotime( $raffle->draw_date ) ) ) : '—'; ?>
            </p>
        </div>
    </div>

    <!-- Progress bar -->
    <div class="card" style="margin-top: 20px; max-width: 100%;">
        <h2 style="margin-top:0;">Sales Progress</h2>
        <div style="background:#e5e7eb; border-radius:10px; height:20px; width:100%; overflow:hidden; position:relative;">
            <div style="background:#3b82f6; height:100%; width:<?php echo esc_attr( $progress ); ?>%; transition: width 0.5s ease; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:bold; font-size:12px;">
                <?php echo esc_html( $progress ); ?>%
            </div>
        </div>
        <p class="description" style="margin-top:8px;">Shortcode to display this raffle: <code>[raffle id="<?php echo esc_attr( $raffle->id ); ?>"]</code></p>
    </div>

    <div id="poststuff" style="margin-top: 20px;">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content">
                
                <!-- Winner Banner / Manual Draw -->
                <?php if ( $winner ) : ?>
                    <div class="notice notice-success inline" style="margin: 0 0 20px; padding: 15px; border-left-width: 6px;">
                        <h3 style="margin:0 0 10px; font-size: 18px; color: #15803d;"><?php wpr_icon( 'trophy', 'wpr-icon--sm' ); ?> Winner Selected!</h3>
                        <div style="display:flex; gap:30px; flex-wrap:wrap;">
                            <div>
                                <small style="display:block; color:#4b5563; font-weight:500;">Winning Ticket</small>
                                <strong style="font-size:24px; font-family:monospace; color:#1d2327;">#<?php echo esc_html( Raffle_Tickets::format_ticket_number( $winner->ticket_number, $raffle->total_tickets ) ); ?></strong>
                            </div>
                            <div>
                                <small style="display:block; color:#4b5563; font-weight:500;">Winner Name</small>
                                <strong style="font-size:18px; color:#1d2327;"><?php echo esc_html( $winner->buyer_name ); ?></strong>
                            </div>
                            <div>
                                <small style="display:block; color:#4b5563; font-weight:500;">Winner Email</small>
                                <strong style="font-size:18px; color:#1d2327;"><?php echo esc_html( $winner->buyer_email ); ?></strong>
                            </div>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'star', 'wpr-icon--sm' ); ?> Select Winner</h2>
                        </div>
                        <div class="inside">
                            <?php if ( $raffle->sold_tickets > 0 ) : ?>
                                <p>Sales are active! You can now draw a random winner from the tickets purchased.</p>
                                <button type="button" id="draw-winner-btn" class="button button-primary button-large" data-raffle-id="<?php echo esc_attr( $raffle->id ); ?>">
                                    Select Winner
                                </button>
                                <div id="draw-result" style="display:none; margin-top:15px;"></div>
                            <?php else : ?>
                                <p class="description"><?php wpr_icon( 'info', 'wpr-icon--sm' ); ?> No tickets have been sold yet. The draw will be available once there are sales.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Duplicate Control -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php wpr_icon( 'shield', 'wpr-icon--sm' ); ?> Duplicate Ticket Control</h2>
                    </div>
                    <div class="inside">
                        <p class="description">If two customers purchase at the same time, there may be overlap. Use these tools to detect and fix any duplicates.</p>
                        
                        <div style="margin: 15px 0;">
                            <label>
                                <input type="checkbox" id="raffle-auto-fix-toggle" value="1" <?php checked( get_option( 'raffle_auto_fix_duplicates', '1' ), '1' ); ?>>
                                <strong>Auto-fix after each purchase</strong>
                            </label>
                            <p class="description" style="margin-left: 25px;">The system will check and fix duplicates after each new order is completed.</p>
                        </div>

                        <div style="display:flex; gap:10px;">
                            <button type="button" id="check-duplicates-btn" class="button" data-raffle-id="<?php echo esc_attr( $raffle->id ); ?>">Check Duplicates</button>
                            <button type="button" id="fix-duplicates-btn" class="button button-primary" style="display:none;" data-raffle-id="<?php echo esc_attr( $raffle->id ); ?>">Fix Duplicates</button>
                        </div>
                        <div id="duplicates-result" style="margin-top:15px;"></div>
                    </div>
                </div>

                <!-- Instant Wins List -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php wpr_icon( 'gift', 'wpr-icon--sm' ); ?> Instant Wins</h2>
                    </div>
                    <div class="inside">
                        <p class="description" style="margin-bottom: 15px;">Instant Wins allow users to win specific prizes immediately when they purchase the matching ticket number.</p>
                        
                        <div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; background: #f6f7f7; padding: 15px; border:1px solid #c3c4c7; border-radius: 4px;">
                            <input type="text" id="iw-prize-name" placeholder="Prize Name (e.g. $50 Gift Card)" style="flex:1; height:35px;">
                            <input type="number" id="iw-ticket-number" placeholder="Ticket # (blank for random)" style="width: 200px; height:35px;">
                            <button type="button" id="add-instant-win-btn" class="button button-primary" data-raffle-id="<?php echo esc_attr( $raffle->id ); ?>" style="height:35px; line-height:33px;">
                                Add Instant Win
                            </button>
                        </div>

                        <table class="wp-list-table widefat striped posts">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Ticket Number</th>
                                    <th>Prize Name</th>
                                    <th>Status</th>
                                    <th>Winner Email</th>
                                    <th style="width:85px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( empty( $instant_wins ) ) : ?>
                                    <tr><td colspan="6" style="text-align:center; padding: 15px; color:#64748b;">No instant wins configured.</td></tr>
                                <?php else : ?>
                                    <?php foreach ( $instant_wins as $iw ) : ?>
                                        <tr>
                                            <td>#<?php echo esc_html( $iw->id ); ?></td>
                                            <td><strong><?php echo esc_html( Raffle_Tickets::format_ticket_number( $iw->ticket_number, $raffle->total_tickets ) ); ?></strong></td>
                                            <td><?php echo esc_html( $iw->prize_name ); ?></td>
                                            <td>
                                                <span style="background:<?php echo $iw->status === 'won' ? '#dcfce7; color:#166534;' : '#fef3c7; color:#92400e;'; ?>; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px;">
                                                    <?php echo esc_html( ucfirst( $iw->status ) ); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html( $iw->winner_email ?: '—' ); ?></td>
                                            <td>
                                                <?php if ( $iw->status === 'available' ) : ?>
                                                    <button type="button" class="button button-link-delete delete-instant-win-btn" data-id="<?php echo esc_attr( $iw->id ); ?>">
                                                        Delete
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Purchases / Buyers List -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php wpr_icon( 'user', 'wpr-icon--sm' ); ?> Buyers & Ticket Holders (<?php echo count( $purchases ); ?>)</h2>
                        <div class="handle-actions" style="display:flex; gap:8px; align-items:center; padding-right:10px;">
                            <label for="rs-buyers-search" class="screen-reader-text"><?php esc_html_e( 'Search buyers', 'wpraffle' ); ?></label>
                            <input type="search" id="rs-buyers-search" placeholder="<?php esc_attr_e( 'Search name or email…', 'wpraffle' ); ?>" style="font-size:12px; min-width:200px;">
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( "admin.php?page=raffle-list&action=export_buyers&id={$raffle->id}" ), 'export_buyers_' . $raffle->id ) ); ?>" class="button button-primary" style="font-size:12px;">
                                <?php wpr_icon( 'refresh', 'wpr-icon--xs' ); ?> <?php esc_html_e( 'Export CSV', 'wpraffle' ); ?>
                            </a>
                        </div>
                    </div>
                    <div class="inside" style="padding:0; margin:0;">
                        <table class="wp-list-table widefat striped posts" id="rs-buyers-table" style="border:none; box-shadow:none;">
                            <thead>
                                <tr>
                                    <th scope="col" style="padding-left:15px; width:60px;">ID</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Email</th>
                                    <th scope="col" style="width:70px;">Tickets</th>
                                    <th scope="col">Total Paid</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Purchase Date</th>
                                    <th scope="col" style="padding-right:15px;">Ticket Numbers</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( empty( $purchases ) ) : ?>
                                    <tr>
                                        <td colspan="8" style="text-align:center; padding: 30px 10px; color:#64748b;">
                                            No purchases recorded for this raffle yet.
                                        </td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ( $purchases as $p ) :
                                        $tickets = $wpdb->get_col( $wpdb->prepare(
                                            "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE purchase_id = %d ORDER BY ticket_number",
                                            $p->id
                                        ) );
                                        $formatted = array_map( function ( $n ) use ( $raffle ) {
                                            return Raffle_Tickets::format_ticket_number( $n, $raffle->total_tickets );
                                        }, $tickets );
                                    ?>
                                        <tr>
                                            <td style="padding-left:15px;">#<?php echo esc_html( $p->id ); ?></td>
                                            <td><strong><?php echo esc_html( $p->buyer_name ); ?></strong></td>
                                            <td><?php echo esc_html( $p->buyer_email ); ?></td>
                                            <td><?php echo esc_html( $p->quantity ); ?></td>
                                            <td><strong style="color: #10b981;"><?php echo esc_html( wpr_price( $p->total_amount ) ); ?></strong></td>
                                            <td>
                                                <span style="background:<?php echo $p->payment_status === 'completed' ? '#dcfce7; color:#166534;' : '#fee2e2; color:#991b1b;'; ?>; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px; display:inline-block;">
                                                    <?php echo esc_html( ucfirst( $p->payment_status ) ); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $p->purchase_date ) ) ); ?></td>
                                            <td style="padding-right:15px;">
                                                <div style="display:flex; flex-wrap:wrap; gap:4px; max-height: 80px; overflow-y: auto;">
                                                    <?php foreach ( $formatted as $num ) : ?>
                                                        <span style="background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; padding:2px 6px; border-radius:4px; font-size:11px; font-family:monospace; font-weight:bold;">
                                                            <?php echo esc_html( $num ); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
// Client-side buyer search — filters table rows by name/email as you type.
// Cheaper than a round-trip for the typical buyers-list size, and gives
// instant feedback.
(function(){
    var input = document.getElementById('rs-buyers-search');
    var table = document.getElementById('rs-buyers-table');
    if (!input || !table) return;
    input.addEventListener('input', function(){
        var q = this.value.trim().toLowerCase();
        var rows = table.querySelectorAll('tbody tr');
        rows.forEach(function(row){
            if (row.cells.length < 3) return; // skip the empty-state row
            var name = (row.cells[1] ? row.cells[1].textContent : '').toLowerCase();
            var email = (row.cells[2] ? row.cells[2].textContent : '').toLowerCase();
            row.style.display = (name.indexOf(q) !== -1 || email.indexOf(q) !== -1) ? '' : 'none';
        });
    });
})();
</script>
