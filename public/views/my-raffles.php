<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$current_user = wp_get_current_user();
if ( 0 == $current_user->ID ) {
    echo '<p class="woocommerce-info">You must be logged in to view your raffles.</p>';
    return;
}

global $wpdb;
$email = $current_user->user_email;

// Find all unique purchases by email. We also JOIN the winning ticket row so we
// can resolve winner_ticket_id (a ticket ROW id stored on the raffle) into the
// actual ticket NUMBER the user sees — without this, the "YOU WON!" check below
// compares a row id against ticket numbers and never matches.
$purchases = $wpdb->get_results( $wpdb->prepare(
    "SELECT p.*, r.title, r.status, r.draw_date, r.winner_ticket_id, r.prize_image,
            r.total_tickets, r.wc_product_id, wt.ticket_number AS winner_ticket_number
     FROM {$wpdb->prefix}raffle_purchases p
     JOIN {$wpdb->prefix}raffles r ON p.raffle_id = r.id
     LEFT JOIN {$wpdb->prefix}raffle_tickets wt ON wt.id = r.winner_ticket_id
     WHERE p.buyer_email = %s AND p.payment_status = 'completed'
     ORDER BY p.purchase_date DESC",
    $email
) );

if ( empty( $purchases ) ) {
    echo '<div class="woocommerce-info">You have not purchased any raffle tickets yet. <a class="woocommerce-Button button" href="' . esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ) . '">Go to Shop</a></div>';
    return;
}

// Group by raffle so if they bought multiple packages, we show them together
$raffles_grouped = array();
foreach ( $purchases as $p ) {
    if ( ! isset( $raffles_grouped[ $p->raffle_id ] ) ) {
        $raffles_grouped[ $p->raffle_id ] = array(
            'id' => $p->raffle_id,
            'title' => $p->title,
            'status' => $p->status,
            'draw_date' => $p->draw_date,
            'winner_ticket_id' => $p->winner_ticket_id,
            'winner_ticket_number' => $p->winner_ticket_number, // resolved ticket NUMBER (may be null)
            'total_tickets' => $p->total_tickets,
            'prize_image' => $p->prize_image,
            'wc_product_id' => $p->wc_product_id,
            'tickets' => array(),
            'instant_wins' => array()
        );
    }

    // Get tickets
    $tickets = $wpdb->get_col( $wpdb->prepare(
        "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE purchase_id = %d",
        $p->id
    ) );
    $raffles_grouped[ $p->raffle_id ]['tickets'] = array_merge( $raffles_grouped[ $p->raffle_id ]['tickets'], $tickets );

    // Get instant wins for this purchase
    $instant_wins = $wpdb->get_results( $wpdb->prepare(
        "SELECT ticket_number, prize_name FROM {$wpdb->prefix}raffle_instant_wins WHERE purchase_id = %d AND status = 'won'",
        $p->id
    ) );
    if ( $instant_wins ) {
        $raffles_grouped[ $p->raffle_id ]['instant_wins'] = array_merge( $raffles_grouped[ $p->raffle_id ]['instant_wins'], $instant_wins );
    }
}

$accordion_id = 0;
?>

<h3 class="woocommerce-MyAccount-title" style="margin-bottom: 25px;">My Raffle Entries</h3>

<style>
/* Icon sizing — ensures SVGs render correctly within WooCommerce */
.wpr-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    vertical-align: middle;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    width: 18px;
    height: 18px;
}
.wpr-icon--xs  { width: 12px; height: 12px; }
.wpr-icon--sm  { width: 14px; height: 14px; }
.wpr-icon--md  { width: 18px; height: 18px; }
.wpr-icon--lg  { width: 22px; height: 22px; }
.wpr-icon--xl  { width: 28px; height: 28px; }

.myr-toggle { margin: 0; }
.myr-toggle-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    background: var(--wpr-bg-muted);
    border: 1px solid var(--wpr-border-color);
    border-radius: 8px;
    padding: 10px 14px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: var(--wpr-text-primary);
    transition: background 0.2s, border-color 0.2s;
}
.myr-toggle-btn:hover { background: var(--wpr-bg-hover); }
.myr-toggle-btn .myr-chevron {
    transition: transform 0.25s ease;
    flex-shrink: 0;
    margin-left: 8px;
}
.myr-toggle-btn.myr-open .myr-chevron { transform: rotate(180deg); }
.myr-toggle-btn.myr-open { border-radius: 8px 8px 0 0; border-color: var(--wpr-border-color); }

.myr-panel {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    border: 1px solid transparent;
    border-top: none;
    border-radius: 0 0 8px 8px;
    background: var(--wpr-bg-surface);
}
.myr-panel.myr-open {
    max-height: 500px;
    border-color: var(--wpr-border-color);
}
.myr-panel-inner {
    padding: 12px 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
</style>

<div class="my-raffles-wrapper">
    <?php foreach ( $raffles_grouped as $raffle_id => $data ) :
        // Sort ticket numbers as integers — get_col() returns strings, so a
        // naive sort() would order "100" before "20".
        $tickets_int = array_map( 'intval', $data['tickets'] );
        sort( $tickets_int );
        $data['tickets'] = $tickets_int;

        $is_active = ( $data['status'] === 'active' );
        // Compare the winning ticket NUMBER (resolved via the tickets-table JOIN
        // above) against the user's ticket numbers. Previously this compared the
        // ticket ROW id against ticket numbers, which never matched.
        $winner_number = $data['winner_ticket_number'] !== null ? (int) $data['winner_ticket_number'] : null;
        $did_i_win_main = ( ! $is_active && $winner_number !== null && in_array( $winner_number, $data['tickets'], true ) );
        $accordion_id++;
        $uid = 'myr-acc-' . $accordion_id;

        // Build a link back to the competition page so finished entries can be reviewed.
        $results_url = '';
        if ( ! empty( $data['wc_product_id'] ) && get_post_status( $data['wc_product_id'] ) ) {
            $results_url = get_permalink( (int) $data['wc_product_id'] );
        }
    ?>
        <div style="background:var(--wpr-bg-surface); border:1px solid var(--wpr-border-color); border-radius:12px; padding:20px; margin-bottom:16px; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.06);">

            <!-- Header row -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div style="display:flex; align-items:center; gap:14px; flex:1; min-width:0;">
                    <?php if ( $data['prize_image'] ) : ?>
                        <img src="<?php echo esc_url( $data['prize_image'] ); ?>" style="width:52px; height:52px; object-fit:cover; border-radius:8px; border:1px solid var(--wpr-border-color); flex-shrink:0;">
                    <?php else : ?>
                        <div style="width:52px; height:52px; background:var(--wpr-bg-muted); display:flex; align-items:center; justify-content:center; border-radius:8px; border:1px solid var(--wpr-border-color); flex-shrink:0;"><?php echo wpr_get_icon( 'ticket', 'wpr-icon--lg' ); ?></div>
                    <?php endif; ?>
                    <div style="min-width:0;">
                        <h4 style="margin:0 0 4px 0; font-size:15px; color:var(--wpr-text-primary); font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html( $data['title'] ); ?></h4>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <span style="display:inline-block; padding:2px 7px; border-radius:4px; font-size:10px; font-weight:bold; text-transform:uppercase; background:<?php echo $is_active ? 'var(--wpr-success-bg); color:var(--wpr-success-text);' : 'var(--wpr-bg-muted); color:var(--wpr-text-muted);'; ?>">
                                <?php echo $is_active ? 'Active' : 'Finished'; ?>
                            </span>
                            <?php if ( $data['draw_date'] ) : ?>
                                <span style="font-size:11px; color:var(--wpr-text-muted);">
                                    Draw: <strong><?php echo date_i18n( 'd/m/Y H:i', strtotime( $data['draw_date'] ) ); ?></strong>
                                </span>
                            <?php endif; ?>
                            <span style="font-size:11px; color:var(--wpr-text-muted);">
                                <?php echo count( $data['tickets'] ); ?> ticket<?php echo count( $data['tickets'] ) !== 1 ? 's' : ''; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                    <?php if ( ! $is_active ) : ?>
                        <?php if ( $did_i_win_main ) : ?>
                            <div style="background:var(--wpr-success-bg); border:1px solid var(--wpr-success-bg); border-radius:6px; padding:5px 10px; text-align:center; font-weight:700; color:var(--wpr-success-text); font-size:12px; display:flex; align-items:center; gap:4px;">
                                <?php echo wpr_get_icon( 'trophy', 'wpr-icon--xs' ); ?> YOU WON!
                            </div>
                        <?php else : ?>
                            <div style="background:var(--wpr-bg-muted); border:1px solid var(--wpr-border-color); border-radius:6px; padding:5px 10px; text-align:center; font-weight:600; color:var(--wpr-text-muted); font-size:12px;">
                                Completed
                            </div>
                        <?php endif; ?>
                        <?php if ( $results_url ) : ?>
                            <a href="<?php echo esc_url( $results_url ); ?>" style="display:inline-flex; align-items:center; gap:4px; padding:5px 10px; border-radius:6px; font-size:12px; font-weight:600; color:var(--wpr-accent); border:1px solid color-mix(in srgb, var(--wpr-accent) 30%, transparent); text-decoration:none;">
                                <?php echo wpr_get_icon( 'eye', 'wpr-icon--xs' ); ?> View Results
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Instant wins accordion -->
            <?php if ( ! empty( $data['instant_wins'] ) ) : ?>
                <div class="myr-toggle" style="margin-top:14px;">
                    <button type="button" class="myr-toggle-btn" onclick="myrToggle(this)" aria-expanded="false" style="background:color-mix(in srgb, var(--wpr-accent) 8%, transparent); border-color:color-mix(in srgb, var(--wpr-accent) 25%, transparent);">
                        <span style="display:flex; align-items:center; gap:6px; color:var(--wpr-accent);">
                            <?php echo wpr_get_icon( 'gift', 'wpr-icon--xs' ); ?>
                            Instant Win<?php echo count( $data['instant_wins'] ) > 1 ? 's' : ''; ?> (<?php echo count( $data['instant_wins'] ); ?>)
                        </span>
                        <?php echo wpr_get_icon( 'chevron-down', 'wpr-icon--sm myr-chevron' ); ?>
                    </button>
                    <div class="myr-panel" style="background:color-mix(in srgb, var(--wpr-accent) 5%, transparent);">
                        <div class="myr-panel-inner" style="flex-direction:column; gap:8px;<?php echo count( $data['instant_wins'] ) > 10 ? ' max-height:300px; overflow-y:auto;' : ''; ?>">
                            <?php foreach ( $data['instant_wins'] as $win ) : ?>
                                <div style="display:flex; align-items:center; gap:8px; background:var(--wpr-bg-surface); border:1px solid color-mix(in srgb, var(--wpr-accent) 20%, transparent); border-radius:6px; padding:8px 12px;">
                                    <span style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; background:color-mix(in srgb, var(--wpr-accent) 15%, transparent); border-radius:6px; flex-shrink:0;">
                                        <?php echo wpr_get_icon( 'gift', 'wpr-icon--xs' ); ?>
                                    </span>
                                    <div style="min-width:0; flex:1;">
                                        <div style="font-weight:700; color:var(--wpr-accent); font-size:13px;"><?php echo esc_html( $win->prize_name ); ?></div>
                                        <div style="font-size:11px; color:var(--wpr-text-muted);">Ticket #<?php echo esc_html( Raffle_Tickets::format_ticket_number( $win->ticket_number, $data['total_tickets'] ) ); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Ticket numbers accordion -->
            <div class="myr-toggle" style="margin-top:14px;">
                <button type="button" class="myr-toggle-btn" onclick="myrToggle(this)" aria-expanded="false">
                    <span style="display:flex; align-items:center; gap:6px;">
                        <?php echo wpr_get_icon( 'ticket', 'wpr-icon--xs' ); ?>
                        View Ticket Numbers (<?php echo count( $data['tickets'] ); ?>)
                    </span>
                    <svg class="myr-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="myr-panel" id="<?php echo esc_attr( $uid ); ?>">
                    <div class="myr-panel-inner">
                        <?php foreach ( $data['tickets'] as $ticket ) :
                            // Compare as ints against the resolved winning ticket NUMBER.
                            $is_winner = ( $winner_number !== null && (int) $ticket === $winner_number );
                            $is_instant_win = false;
                            foreach( $data['instant_wins'] as $iw ) {
                                if ( $iw->ticket_number == $ticket ) {
                                    $is_instant_win = true; 
                                    break;
                                }
                            }
                            
                            $bg = 'var(--wpr-bg-muted)'; $color = 'var(--wpr-text-primary)'; $border = '1px solid var(--wpr-border-color)';
                            if ( $is_winner ) {
                                $bg = 'var(--wpr-success-bg)'; $color = 'var(--wpr-success-text)'; $border = '2px solid var(--wpr-success)';
                            } elseif ( $is_instant_win ) {
                                $bg = 'var(--wpr-accent-bg)'; $color = 'var(--wpr-accent-text)'; $border = '1px solid var(--wpr-accent-border)';
                            }
                        ?>
                            <span style="background:<?php echo $bg; ?>; color:<?php echo $color; ?>; border:<?php echo $border; ?>; padding:4px 10px; border-radius:6px; font-weight:bold; font-family:monospace; font-size:13px; display:inline-block;" title="<?php echo $is_winner ? 'Winning Ticket' : ($is_instant_win ? 'Instant Win Ticket' : ''); ?>">
                                <?php echo esc_html( Raffle_Tickets::format_ticket_number( $ticket, $data['total_tickets'] ) ); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    <?php endforeach; ?>
</div>

<!-- Privacy Controls: Export & Delete Data (GDPR) -->
<div style="margin-top: 32px; padding: 24px; background: var(--wpr-bg-muted); border: 1px solid var(--wpr-border-color); border-radius: 12px;">
    <h4 style="margin: 0 0 8px 0; font-size: 15px; font-weight: 700; color: var(--wpr-text-primary);">Your Data & Privacy</h4>
    <p style="margin: 0 0 16px 0; font-size: 13px; color: var(--wpr-text-muted); line-height: 1.5;">You have the right to export or delete your raffle data in accordance with GDPR regulations.</p>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button type="button" id="raffle-export-my-data-btn" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: var(--wpr-accent); color: var(--wpr-text-inverse); border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: opacity 0.2s;">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export My Data
        </button>
        <button type="button" id="raffle-delete-my-data-btn" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: var(--wpr-bg-surface); color: #dc2626; border: 1px solid #fca5a5; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: opacity 0.2s;">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            Request Data Deletion
        </button>
    </div>
    <div id="raffle-privacy-status" style="margin-top: 12px; font-size: 13px; display: none;"></div>
</div>

<script>
// Privacy controls
(function() {
    var privacyNonce = '<?php echo esc_js( wp_create_nonce( "raffle_my_data_nonce" ) ); ?>';

    document.getElementById('raffle-export-my-data-btn').addEventListener('click', function() {
        var btn = this;
        btn.disabled = true; btn.style.opacity = '0.6';
        var statusEl = document.getElementById('raffle-privacy-status');
        statusEl.style.display = 'block'; statusEl.style.color = '#1d4ed8'; statusEl.textContent = 'Exporting your data...';

        jQuery.post(rafflePublic.ajax_url, {
            action: 'raffle_export_my_data',
            nonce: privacyNonce
        }, function(res) {
            btn.disabled = false; btn.style.opacity = '1';
            if (res.success) {
                var blob = new Blob([JSON.stringify(res.data, null, 2)], {type: 'application/json'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url; a.download = 'raffle-data-export.json'; a.click();
                URL.revokeObjectURL(url);
                statusEl.style.color = '#166534'; statusEl.textContent = 'Data exported successfully!';
            } else {
                statusEl.style.color = '#dc2626'; statusEl.textContent = res.data.message || 'Export failed.';
            }
        }).fail(function() {
            btn.disabled = false; btn.style.opacity = '1';
            statusEl.style.color = '#dc2626'; statusEl.textContent = 'Export failed. Please try again.';
        });
    });

    document.getElementById('raffle-delete-my-data-btn').addEventListener('click', function() {
        if (!confirm('Are you sure you want to delete your raffle data? Your personal information will be anonymized. This action cannot be undone.')) return;
        var btn = this;
        btn.disabled = true; btn.style.opacity = '0.6';
        var statusEl = document.getElementById('raffle-privacy-status');
        statusEl.style.display = 'block'; statusEl.style.color = '#dc2626'; statusEl.textContent = 'Processing deletion request...';

        jQuery.post(rafflePublic.ajax_url, {
            action: 'raffle_request_deletion',
            nonce: privacyNonce
        }, function(res) {
            btn.disabled = false; btn.style.opacity = '1';
            if (res.success) {
                statusEl.style.color = '#166534'; statusEl.textContent = res.data.message;
            } else {
                statusEl.style.color = '#dc2626'; statusEl.textContent = res.data.message || 'Deletion failed.';
            }
        }).fail(function() {
            btn.disabled = false; btn.style.opacity = '1';
            statusEl.style.color = '#dc2626'; statusEl.textContent = 'Deletion failed. Please try again.';
        });
    });
})();

function myrToggle(btn) {
    var panel = btn.nextElementSibling;
    var isOpen = btn.classList.contains('myr-open');
    
    if (isOpen) {
        btn.classList.remove('myr-open');
        panel.classList.remove('myr-open');
        btn.setAttribute('aria-expanded', 'false');
    } else {
        btn.classList.add('myr-open');
        panel.classList.add('myr-open');
        btn.setAttribute('aria-expanded', 'true');
    }
}
</script>