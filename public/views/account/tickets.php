<?php
/**
 * Account tab: Recent Tickets — compact accordion view with Live/Drawn split.
 * Uses inherited theme styles — no hardcoded colours.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$current_user = wp_get_current_user();
$email        = $current_user->user_email;

global $wpdb;

$purchases = $wpdb->get_results( $wpdb->prepare(
    "SELECT p.*, r.title, r.status, r.draw_date, r.winner_ticket_id, r.prize_image, r.total_tickets, r.sold_tickets, r.start_date
     FROM {$wpdb->prefix}raffle_purchases p
     JOIN {$wpdb->prefix}raffles r ON p.raffle_id = r.id
     WHERE p.buyer_email = %s AND p.payment_status = 'completed'
     ORDER BY p.purchase_date DESC",
    $email
) );

// Pending / processing purchases are surfaced separately so a buyer whose
// payment is still on-hold/pending can see that their entry was received,
// rather than assuming it was lost — a common support-ticket driver.
$pending = $wpdb->get_results( $wpdb->prepare(
    "SELECT p.*, r.title, r.draw_date
     FROM {$wpdb->prefix}raffle_purchases p
     JOIN {$wpdb->prefix}raffles r ON p.raffle_id = r.id
     WHERE p.buyer_email = %s AND p.payment_status IN ('pending','processing','on-hold')
     ORDER BY p.purchase_date DESC",
    $email
) );

$live   = array();
$drawn  = array();
foreach ( $purchases as $p ) {
    $state = Raffle_Public::get_raffle_state( $p );
    if ( $state === 'live' ) {
        $live[] = $p;
    } else {
        $drawn[] = $p;
    }
}
?>

<h3 style="margin:0 0 1em;">
    <?php wpr_icon( 'ticket', 'wpr-icon--md' ); ?> My Tickets
</h3>

<!-- Pending / Processing -->
<?php if ( ! empty( $pending ) ) : ?>
<div style="margin-bottom:1.5em; padding:0.8em 1em; background:color-mix(in srgb, var(--wpr-urgency-warn, #f59e0b) 8%, transparent); border:1px solid color-mix(in srgb, var(--wpr-urgency-warn, #f59e0b) 30%, transparent); border-radius:8px;">
    <h4 style="text-transform:uppercase;font-size:0.85em;letter-spacing:0.5px;margin:0 0 0.6em;display:flex;align-items:center;gap:6px;color:var(--wpr-urgency-warn, #b45309);">
        <?php wpr_icon( 'clock-filled', 'wpr-icon--xs' ); ?> Processing (<?php echo count( $pending ); ?>)
    </h4>
    <p style="font-size:0.78em;color:var(--wpr-text-muted);margin:0 0 0.6em;">These entries are confirmed but awaiting payment clearance. Your tickets will appear here once payment completes.</p>
    <?php foreach ( $pending as $p ) : ?>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:0.4em 0.6em;background:var(--wpr-bg-surface);border:1px solid var(--wpr-border-color);border-radius:6px;margin-bottom:0.3em;font-size:0.85em;">
            <span style="font-weight:600;color:var(--wpr-text-primary);"><?php echo esc_html( $p->title ); ?></span>
            <span style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:0.7em;padding:1px 6px;border-radius:3px;background:color-mix(in srgb, var(--wpr-urgency-warn, #f59e0b) 15%, transparent);color:var(--wpr-urgency-warn, #b45309);font-weight:700;text-transform:uppercase;"><?php echo esc_html( ucfirst( $p->payment_status ) ); ?></span>
                <span style="font-size:0.75em;color:var(--wpr-text-muted);"><?php echo esc_html( date_i18n( 'j M Y', strtotime( $p->purchase_date ) ) ); ?></span>
            </span>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Live -->
<div style="margin-bottom:1.5em;">
    <h4 style="text-transform:uppercase;font-size:0.85em;letter-spacing:0.5px;margin:0 0 0.75em;">
        <?php wpr_icon( 'ticket', 'wpr-icon--xs' ); ?> Live Competitions (<?php echo count( $live ); ?>)
    </h4>
    <?php if ( empty( $live ) ) : ?>
        <p class="woocommerce-info">No active entries. <a href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>">Browse competitions</a></p>
    <?php else : ?>
        <?php foreach ( $live as $p ) :
            $tickets = $wpdb->get_col( $wpdb->prepare( "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE purchase_id = %d ORDER BY ticket_number ASC", $p->id ) );
            $total_digits = strlen( (string) ( $p->total_tickets ?? 3 ) );

            // Odds of winning: user's tickets / total tickets in the raffle.
            // Capped at the configured total to avoid divide-by-zero / silly values.
            $my_count = count( $tickets );
            $total_t  = max( (int) $p->total_tickets, 1 );
            $odds_one_in = $my_count > 0 ? max( 1, (int) round( $total_t / $my_count ) ) : 0;
            $odds_pct = $my_count > 0 ? min( 100, ( $my_count / $total_t ) * 100 ) : 0;
        ?>
            <details style="margin-bottom:0.4em;">
                <summary style="cursor:pointer;padding:0.6em 0.8em;border:1px solid var(--wpr-border-color);border-radius:6px;font-weight:600;font-size:0.9em;display:flex;align-items:center;justify-content:space-between;list-style:none;">
                    <span><?php echo esc_html( $p->title ); ?> <span class="wpr-status-live" style="font-size:0.7em;padding:1px 6px;border-radius:3px;background:var(--wpr-success-bg);color:var(--wpr-success-text);font-weight:700;text-transform:uppercase;">Live</span></span>
                    <span style="opacity:0.7;font-size:0.8em;color:var(--wpr-text-muted);"><?php echo count( $tickets ); ?> tickets &bull; <?php echo esc_html( date_i18n( 'j M', strtotime( $p->purchase_date ) ) ); ?></span>
                </summary>
                <div style="padding:0.6em 0.8em;">
                    <?php if ( $odds_one_in > 0 ) : ?>
                        <div style="font-size:0.75em;color:var(--wpr-text-muted);margin-bottom:0.5em;display:flex;align-items:center;gap:6px;">
                            <?php wpr_icon( 'star', 'wpr-icon--xs' ); ?>
                            Your odds: <strong style="color:var(--wpr-accent);">1 in <?php echo esc_html( number_format_i18n( $odds_one_in ) ); ?></strong>
                            <span style="opacity:0.7;">(<?php echo esc_html( round( $odds_pct, 2 ) ); ?>%)</span>
                        </div>
                    <?php endif; ?>
                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                        <?php foreach ( $tickets as $t ) : ?>
                            <code style="background:var(--wpr-bg-muted);color:var(--wpr-text-primary);padding:2px 6px;border-radius:3px;font-size:0.8em;"><?php echo esc_html( str_pad( $t, $total_digits, '0', STR_PAD_LEFT ) ); ?></code>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Drawn -->
<div>
    <h4 style="text-transform:uppercase;font-size:0.85em;letter-spacing:0.5px;margin:0 0 0.75em;">
        <?php wpr_icon( 'trophy', 'wpr-icon--xs' ); ?> Drawn / Past (<?php echo count( $drawn ); ?>)
    </h4>
    <?php if ( empty( $drawn ) ) : ?>
        <p class="woocommerce-info">No past entries yet.</p>
    <?php else : ?>
        <?php foreach ( $drawn as $p ) :
            $tickets = $wpdb->get_col( $wpdb->prepare( "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE purchase_id = %d ORDER BY ticket_number ASC", $p->id ) );
            $total_digits = strlen( (string) ( $p->total_tickets ?? 3 ) );
        ?>
            <details style="margin-bottom:0.4em;">
                <summary style="cursor:pointer;padding:0.6em 0.8em;border:1px solid var(--wpr-border-color);border-radius:6px;font-weight:600;font-size:0.9em;display:flex;align-items:center;justify-content:space-between;list-style:none;">
                    <span><?php echo esc_html( $p->title ); ?> <span style="font-size:0.7em;padding:1px 6px;border-radius:3px;background:var(--wpr-bg-muted);color:var(--wpr-text-muted);font-weight:700;text-transform:uppercase;">Drawn</span></span>
                    <span style="opacity:0.7;font-size:0.8em;color:var(--wpr-text-muted);"><?php echo count( $tickets ); ?> tickets &bull; <?php echo esc_html( date_i18n( 'j M', strtotime( $p->purchase_date ) ) ); ?></span>
                </summary>
                <div style="padding:0.6em 0.8em;display:flex;flex-wrap:wrap;gap:4px;">
                    <?php foreach ( $tickets as $t ) : ?>
                        <code style="background:var(--wpr-bg-muted);color:var(--wpr-text-muted);padding:2px 6px;border-radius:3px;font-size:0.8em;"><?php echo esc_html( str_pad( $t, $total_digits, '0', STR_PAD_LEFT ) ); ?></code>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
// Remove default details marker
document.querySelectorAll('.woocommerce-MyAccount-content details summary').forEach(function(s) {
    s.style.listStyle = 'none';
    s.style.webkitListStyle = 'none';
});
</script>