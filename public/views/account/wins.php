<?php
/**
 * Account tab: Wins — compact accordion view with inherited theme styles.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$current_user = wp_get_current_user();
$email        = $current_user->user_email;

global $wpdb;

$main_wins = $wpdb->get_results( $wpdb->prepare(
    "SELECT r.id, r.title, r.prize_image, r.total_tickets, r.winner_ticket_id, r.enable_cash_alternative, r.cash_alternative_amount, r.draw_date, r.wc_product_id,
            t.ticket_number, t.id as ticket_id
     FROM {$wpdb->prefix}raffles r
     JOIN {$wpdb->prefix}raffle_tickets t ON t.id = r.winner_ticket_id
     WHERE t.buyer_email = %s
     ORDER BY r.draw_date DESC",
    $email
) );

$instant_wins = $wpdb->get_results( $wpdb->prepare(
    "SELECT iw.*, r.title as raffle_title, r.total_tickets
     FROM {$wpdb->prefix}raffle_instant_wins iw
     JOIN {$wpdb->prefix}raffles r ON r.id = iw.raffle_id
     WHERE iw.winner_email = %s AND iw.status = 'won'
     ORDER BY iw.created_at DESC",
    $email
) );
?>

<h3 style="margin:0 0 1em;">
    <?php wpr_icon( 'trophy', 'wpr-icon--md' ); ?> My Wins
</h3>

<?php if ( empty( $main_wins ) && empty( $instant_wins ) ) : ?>
    <p class="woocommerce-info">No wins yet — keep entering!</p>
<?php else : ?>

    <?php if ( ! empty( $main_wins ) ) : ?>
        <details open style="margin-bottom:1em;">
            <summary style="cursor:pointer;padding:0.6em 0.8em;border:1px solid var(--wpr-border-color);border-radius:6px;font-weight:700;font-size:0.95em;display:flex;align-items:center;justify-content:space-between;list-style:none;">
                <span><?php wpr_icon( 'trophy', 'wpr-icon--xs' ); ?> Main Prize Wins (<?php echo count( $main_wins ); ?>)</span>
            </summary>
            <div style="padding:0.6em 0.8em;display:flex;flex-direction:column;gap:0.5em;">
                <?php foreach ( $main_wins as $w ) :
                    $td = strlen( (string) ( $w->total_tickets ?? 3 ) );
                ?>
                    <div style="display:flex;align-items:center;gap:0.6em;border:1px solid var(--wpr-border-color);border-radius:6px;padding:0.5em 0.7em;">
                        <?php if ( $w->prize_image ) : ?>
                            <img src="<?php echo esc_url( $w->prize_image ); ?>" style="width:40px;height:40px;border-radius:5px;object-fit:cover;flex-shrink:0;" alt="">
                        <?php endif; ?>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;font-size:0.9em;color:var(--wpr-text-primary);"><?php echo esc_html( $w->title ); ?></div>
                            <div style="font-size:0.75em;color:var(--wpr-text-muted);">
                                Winning Ticket: <code style="font-weight:700;background:var(--wpr-bg-muted);color:var(--wpr-text-primary);"><?php echo esc_html( str_pad( $w->ticket_number, $td, '0', STR_PAD_LEFT ) ); ?></code>
                            </div>
                            <?php if ( $w->draw_date ) : ?>
                                <div style="font-size:0.75em;color:var(--wpr-text-muted);">Drawn: <strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $w->draw_date ) ) ); ?></strong></div>
                            <?php endif; ?>
                            <?php if ( $w->enable_cash_alternative ) : ?>
                                <div style="font-size:0.75em;color:var(--wpr-text-muted);">Cash alternative: <strong><?php echo esc_html( wpr_price( $w->cash_alternative_amount ) ); ?></strong></div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:4px; flex-shrink:0;">
                            <span style="background:var(--wpr-success-bg);color:var(--wpr-success-text);padding:2px 8px;border-radius:10px;font-size:0.65em;font-weight:700;text-transform:uppercase;">Won</span>
                            <?php if ( ! empty( $w->wc_product_id ) && get_post_status( $w->wc_product_id ) ) : ?>
                                <a href="<?php echo esc_url( get_permalink( (int) $w->wc_product_id ) ); ?>" style="font-size:0.7em;font-weight:600;color:var(--wpr-accent);text-decoration:none;">View competition &rsaquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

    <?php if ( ! empty( $instant_wins ) ) : ?>
        <details style="margin-bottom:1em;">
            <summary style="cursor:pointer;padding:0.6em 0.8em;border:1px solid var(--wpr-border-color);border-radius:6px;font-weight:700;font-size:0.95em;display:flex;align-items:center;justify-content:space-between;list-style:none;">
                <span><?php wpr_icon( 'zap', 'wpr-icon--xs' ); ?> Instant Wins (<?php echo count( $instant_wins ); ?>)</span>
            </summary>
            <div style="padding:0.6em 0.8em;display:flex;flex-direction:column;gap:0.4em;">
                <?php foreach ( $instant_wins as $iw ) :
                    $td = strlen( (string) ( $iw->total_tickets ?? 3 ) );
                ?>
                    <div style="display:flex;align-items:center;gap:0.6em;border:1px solid var(--wpr-border-color);border-radius:5px;padding:0.4em 0.7em;">
                        <span style="flex-shrink:0;color:var(--wpr-accent);"><?php wpr_icon( 'gift', 'wpr-icon--sm' ); ?></span>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;font-size:0.9em;color:var(--wpr-text-primary);"><?php echo esc_html( $iw->prize_name ); ?></div>
                            <div style="font-size:0.75em;color:var(--wpr-text-muted);">
                                <?php echo esc_html( $iw->raffle_title ); ?> &bull; #<code style="font-weight:700;background:var(--wpr-bg-muted);color:var(--wpr-text-primary);"><?php echo esc_html( str_pad( $iw->ticket_number, $td, '0', STR_PAD_LEFT ) ); ?></code>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

<?php endif; ?>
