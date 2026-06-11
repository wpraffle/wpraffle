<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$current_user = wp_get_current_user();
if ( 0 == $current_user->ID ) {
    echo '<p class="woocommerce-info">You must be logged in to view your raffles.</p>';
    return;
}

global $wpdb;
$email = $current_user->user_email;

// Find all unique purchases by email
$purchases = $wpdb->get_results( $wpdb->prepare(
    "SELECT p.*, r.title, r.status, r.draw_date, r.winner_ticket_id, r.prize_image, r.total_tickets 
     FROM {$wpdb->prefix}raffle_purchases p
     JOIN {$wpdb->prefix}raffles r ON p.raffle_id = r.id
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
            'total_tickets' => $p->total_tickets,
            'prize_image' => $p->prize_image,
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
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 10px 14px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    transition: background 0.2s, border-color 0.2s;
}
.myr-toggle-btn:hover { background: #f3f4f6; }
.myr-toggle-btn .myr-chevron {
    transition: transform 0.25s ease;
    flex-shrink: 0;
    margin-left: 8px;
}
.myr-toggle-btn.myr-open .myr-chevron { transform: rotate(180deg); }
.myr-toggle-btn.myr-open { border-radius: 8px 8px 0 0; border-color: #d1d5db; }

.myr-panel {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    border: 1px solid transparent;
    border-top: none;
    border-radius: 0 0 8px 8px;
    background: #fff;
}
.myr-panel.myr-open {
    max-height: 500px;
    border-color: #d1d5db;
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
        sort( $data['tickets'] );
        $is_active = ( $data['status'] === 'active' );
        $did_i_win_main = in_array( $data['winner_ticket_id'], $data['tickets'] );
        $accordion_id++;
        $uid = 'myr-acc-' . $accordion_id;
    ?>
        <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; padding:20px; margin-bottom:16px; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.06);">

            <!-- Header row -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div style="display:flex; align-items:center; gap:14px; flex:1; min-width:0;">
                    <?php if ( $data['prize_image'] ) : ?>
                        <img src="<?php echo esc_url( $data['prize_image'] ); ?>" style="width:52px; height:52px; object-fit:cover; border-radius:8px; border:1px solid #e5e7eb; flex-shrink:0;">
                    <?php else : ?>
                        <div style="width:52px; height:52px; background:#f3f4f6; display:flex; align-items:center; justify-content:center; border-radius:8px; border:1px solid #e5e7eb; flex-shrink:0;"><?php echo wpr_get_icon( 'ticket', 'wpr-icon--lg' ); ?></div>
                    <?php endif; ?>
                    <div style="min-width:0;">
                        <h4 style="margin:0 0 4px 0; font-size:15px; color:#111827; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html( $data['title'] ); ?></h4>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <span style="display:inline-block; padding:2px 7px; border-radius:4px; font-size:10px; font-weight:bold; text-transform:uppercase; background:<?php echo $is_active ? '#dcfce7; color:#166534;' : '#f3f4f6; color:#4b5563;'; ?>">
                                <?php echo $is_active ? 'Active' : 'Finished'; ?>
                            </span>
                            <?php if ( $data['draw_date'] ) : ?>
                                <span style="font-size:11px; color:#6b7280;">
                                    Draw: <strong><?php echo date_i18n( 'd/m/Y H:i', strtotime( $data['draw_date'] ) ); ?></strong>
                                </span>
                            <?php endif; ?>
                            <span style="font-size:11px; color:#6b7280;">
                                <?php echo count( $data['tickets'] ); ?> ticket<?php echo count( $data['tickets'] ) !== 1 ? 's' : ''; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                    <?php if ( ! $is_active ) : ?>
                        <?php if ( $did_i_win_main ) : ?>
                            <div style="background:#fef08a; border:1px solid #facc15; border-radius:6px; padding:5px 10px; text-align:center; font-weight:700; color:#854d0e; font-size:12px; display:flex; align-items:center; gap:4px;">
                                <?php echo wpr_get_icon( 'trophy', 'wpr-icon--xs' ); ?> YOU WON!
                            </div>
                        <?php else : ?>
                            <div style="background:#f3f4f6; border:1px solid #d1d5db; border-radius:6px; padding:5px 10px; text-align:center; font-weight:600; color:#4b5563; font-size:12px;">
                                Completed
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Instant wins accordion -->
            <?php if ( ! empty( $data['instant_wins'] ) ) : ?>
                <div class="myr-toggle" style="margin-top:14px;">
                    <button type="button" class="myr-toggle-btn" onclick="myrToggle(this)" aria-expanded="false" style="background:#fffbeb; border-color:#fde68a;">
                        <span style="display:flex; align-items:center; gap:6px; color:#92400e;">
                            <?php echo wpr_get_icon( 'gift', 'wpr-icon--xs' ); ?>
                            Instant Win<?php echo count( $data['instant_wins'] ) > 1 ? 's' : ''; ?> (<?php echo count( $data['instant_wins'] ); ?>)
                        </span>
                        <svg class="myr-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="myr-panel" style="background:#fffbeb;">
                        <div class="myr-panel-inner" style="flex-direction:column; gap:8px;<?php echo count( $data['instant_wins'] ) > 10 ? ' max-height:300px; overflow-y:auto;' : ''; ?>">
                            <?php foreach ( $data['instant_wins'] as $win ) : ?>
                                <div style="display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #fde68a; border-radius:6px; padding:8px 12px;">
                                    <span style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; background:#fef3c7; border-radius:6px; flex-shrink:0;">
                                        <?php echo wpr_get_icon( 'gift', 'wpr-icon--xs' ); ?>
                                    </span>
                                    <div style="min-width:0; flex:1;">
                                        <div style="font-weight:700; color:#92400e; font-size:13px;"><?php echo esc_html( $win->prize_name ); ?></div>
                                        <div style="font-size:11px; color:#b45309;">Ticket #<?php echo esc_html( Raffle_Tickets::format_ticket_number( $win->ticket_number, $data['total_tickets'] ) ); ?></div>
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
                            $is_winner = ( $ticket == $data['winner_ticket_id'] );
                            $is_instant_win = false;
                            foreach( $data['instant_wins'] as $iw ) {
                                if ( $iw->ticket_number == $ticket ) {
                                    $is_instant_win = true; 
                                    break;
                                }
                            }
                            
                            $bg = '#f3f4f6'; $color = '#1f2937'; $border = '1px solid #d1d5db';
                            if ( $is_winner ) {
                                $bg = '#fef08a'; $color = '#854d0e'; $border = '2px solid #eab308';
                            } elseif ( $is_instant_win ) {
                                $bg = '#fde68a'; $color = '#92400e'; $border = '1px solid #f59e0b';
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

<script>
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
</task_progress>