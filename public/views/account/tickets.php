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
    "SELECT p.*, r.title, r.status, r.draw_date, r.winner_ticket_id, r.prize_image, r.total_tickets, r.start_date
     FROM {$wpdb->prefix}raffle_purchases p
     JOIN {$wpdb->prefix}raffles r ON p.raffle_id = r.id
     WHERE p.buyer_email = %s AND p.payment_status = 'completed'
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
    <?php echo wpr_get_icon( 'ticket', 'wpr-icon--md' ); ?> My Tickets
</h3>

<!-- Live -->
<div style="margin-bottom:1.5em;">
    <h4 style="text-transform:uppercase;font-size:0.85em;letter-spacing:0.5px;margin:0 0 0.75em;">
        <?php echo wpr_get_icon( 'ticket', 'wpr-icon--xs' ); ?> Live Competitions (<?php echo count( $live ); ?>)
    </h4>
    <?php if ( empty( $live ) ) : ?>
        <p class="woocommerce-info">No active entries. <a href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>">Browse competitions</a></p>
    <?php else : ?>
        <?php foreach ( $live as $p ) :
            $tickets = $wpdb->get_col( $wpdb->prepare( "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE purchase_id = %d ORDER BY ticket_number ASC", $p->id ) );
            $total_digits = strlen( (string) ( $p->total_tickets ?? 3 ) );
        ?>
            <details style="margin-bottom:0.4em;">
                <summary style="cursor:pointer;padding:0.6em 0.8em;border:1px solid var(--wpr-border-color);border-radius:6px;font-weight:600;font-size:0.9em;display:flex;align-items:center;justify-content:space-between;list-style:none;">
                    <span><?php echo esc_html( $p->title ); ?> <span class="wpr-status-live" style="font-size:0.7em;padding:1px 6px;border-radius:3px;background:var(--wpr-success-bg);color:var(--wpr-success-text);font-weight:700;text-transform:uppercase;">Live</span></span>
                    <span style="opacity:0.7;font-size:0.8em;color:var(--wpr-text-muted);"><?php echo count( $tickets ); ?> tickets &bull; <?php echo esc_html( date_i18n( 'j M', strtotime( $p->purchase_date ) ) ); ?></span>
                </summary>
                <div style="padding:0.6em 0.8em;display:flex;flex-wrap:wrap;gap:4px;">
                    <?php foreach ( $tickets as $t ) : ?>
                        <code style="background:var(--wpr-bg-muted);color:var(--wpr-text-primary);padding:2px 6px;border-radius:3px;font-size:0.8em;"><?php echo esc_html( str_pad( $t, $total_digits, '0', STR_PAD_LEFT ) ); ?></code>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Drawn -->
<div>
    <h4 style="text-transform:uppercase;font-size:0.85em;letter-spacing:0.5px;margin:0 0 0.75em;">
        <?php echo wpr_get_icon( 'trophy', 'wpr-icon--xs' ); ?> Drawn / Past (<?php echo count( $drawn ); ?>)
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