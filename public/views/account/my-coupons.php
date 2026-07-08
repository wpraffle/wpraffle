<?php
/**
 * Account tab: My Coupons — coupons won via instant wins or issued as
 * consolation, plus any WooCommerce coupon restricted to this user.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$current_user = wp_get_current_user();
$email        = $current_user->user_email;

global $wpdb;

// ── Source 1: instant-win coupon prizes (prize_config holds the code). ──
$coupon_wins = $wpdb->get_results( $wpdb->prepare(
    "SELECT iw.id, iw.prize_name, iw.prize_config, iw.status, iw.created_at, iw.won_at,
            r.title AS raffle_title
     FROM {$wpdb->prefix}raffle_instant_wins iw
     JOIN {$wpdb->prefix}raffles r ON r.id = iw.raffle_id
     WHERE iw.winner_email = %s AND iw.status = 'won' AND iw.prize_type = 'coupon'
     ORDER BY iw.won_at DESC",
    $email
) );

// ── Source 2: consolation coupons (issued to non-winners, email-restricted). ──
// These are WooCommerce shop_coupon posts whose email restriction matches the
// user and whose code starts with the CONSOL- prefix. Resolved via the
// _customer_email meta Woo uses, falling back to scanning post_excerpt.
$consol_coupons = array();
if ( class_exists( 'WooCommerce' ) ) {
    $consol_coupons = get_posts( array(
        'post_type'      => 'shop_coupon',
        'post_status'    => 'any',
        'posts_per_page' => 100,
        'meta_query'     => array(
            array(
                'key'     => 'customer_email',
                'value'   => serialize( array( strtolower( $email ) ) ),
                'compare' => 'LIKE',
            ),
        ),
    ) );
}

// Filter consolation coupons to the CONSOL- prefix and exclude any already
// surfaced as instant-win coupon prizes.
$coupon_codes_used = array();
foreach ( $coupon_wins as $cw ) {
    $cfg = json_decode( $cw->prize_config ?: '', true );
    if ( ! empty( $cfg['coupon_code'] ) ) {
        $coupon_codes_used[] = $cfg['coupon_code'];
    }
}
$consol_filtered = array();
foreach ( $consol_coupons as $cpost ) {
    if ( strpos( $cpost->post_title, 'CONSOL-' ) !== 0 ) {
        continue;
    }
    if ( in_array( $cpost->post_title, $coupon_codes_used, true ) ) {
        continue;
    }
    $consol_filtered[] = $cpost;
}

$total_coupons = count( $coupon_wins ) + count( $consol_filtered );
?>

<h3 style="margin:0 0 1em;">
    <?php wpr_icon( 'gift', 'wpr-icon--md' ); ?> My Coupons
</h3>

<?php if ( $total_coupons === 0 ) : ?>
    <p class="woocommerce-info">You have no coupons yet. Win an instant prize or enter a competition with a consolation prize to earn coupons!</p>
<?php else : ?>

    <p style="color:var(--wpr-text-muted);font-size:0.85em;margin-bottom:1em;">
        These are your coupons from instant wins and consolation prizes. Copy a code and paste it at checkout to redeem.
    </p>

    <?php if ( ! empty( $coupon_wins ) ) : ?>
        <details open style="margin-bottom:1em;">
            <summary style="cursor:pointer;padding:0.6em 0.8em;border:1px solid var(--wpr-border-color);border-radius:6px;font-weight:700;font-size:0.95em;display:flex;align-items:center;justify-content:space-between;list-style:none;">
                <span><?php wpr_icon( 'zap', 'wpr-icon--xs' ); ?> Instant Win Coupons (<?php echo count( $coupon_wins ); ?>)</span>
            </summary>
            <div style="padding:0.6em 0.8em;display:flex;flex-direction:column;gap:0.5em;">
                <?php foreach ( $coupon_wins as $cw ) :
                    $cfg         = json_decode( $cw->prize_config ?: '', true );
                    $code        = isset( $cfg['coupon_code'] ) ? $cfg['coupon_code'] : '';
                    $coupon_id   = isset( $cfg['coupon_id'] ) ? (int) $cfg['coupon_id'] : 0;
                    $used        = false;
                    $expiry      = '';
                    if ( $coupon_id && function_exists( 'wc_get_coupon' ) ) {
                        $coupon = new WC_Coupon( $coupon_id );
                        if ( $coupon->get_id() ) {
                            $used    = $coupon->get_usage_count() > 0;
                            $expires = $coupon->get_date_expires();
                            if ( $expires ) {
                                $expiry = wp_date( get_option( 'date_format' ), $expires->getTimestamp() );
                            }
                        }
                    }
                ?>
                    <div style="display:flex;align-items:center;gap:0.6em;border:1px solid var(--wpr-border-color);border-radius:6px;padding:0.6em 0.8em;">
                        <span style="flex-shrink:0;color:var(--wpr-accent);"><?php wpr_icon( 'ticket', 'wpr-icon--sm' ); ?></span>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;font-size:0.9em;color:var(--wpr-text-primary);"><?php echo esc_html( $cw->prize_name ); ?></div>
                            <div style="font-size:0.75em;color:var(--wpr-text-muted);"><?php echo esc_html( $cw->raffle_title ); ?></div>
                            <?php if ( $code ) : ?>
                                <div style="margin-top:4px;">
                                    <code class="wpr-copy-code" style="font-weight:700;background:var(--wpr-bg-muted);color:var(--wpr-text-primary);padding:3px 8px;border-radius:4px;letter-spacing:0.5px;font-size:0.85em;cursor:pointer;" title="Click to copy"><?php echo esc_html( $code ); ?></code>
                                </div>
                            <?php endif; ?>
                            <?php if ( $expiry ) : ?>
                                <div style="font-size:0.7em;color:var(--wpr-text-muted);margin-top:2px;">Expires: <strong><?php echo esc_html( $expiry ); ?></strong></div>
                            <?php endif; ?>
                        </div>
                        <div style="flex-shrink:0;">
                            <?php if ( $used ) : ?>
                                <span style="background:var(--wpr-bg-muted);color:var(--wpr-text-muted);padding:2px 8px;border-radius:10px;font-size:0.65em;font-weight:700;text-transform:uppercase;">Used</span>
                            <?php else : ?>
                                <span style="background:var(--wpr-success-bg);color:var(--wpr-success-text);padding:2px 8px;border-radius:10px;font-size:0.65em;font-weight:700;text-transform:uppercase;">Ready</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

    <?php if ( ! empty( $consol_filtered ) ) : ?>
        <details style="margin-bottom:1em;">
            <summary style="cursor:pointer;padding:0.6em 0.8em;border:1px solid var(--wpr-border-color);border-radius:6px;font-weight:700;font-size:0.95em;display:flex;align-items:center;justify-content:space-between;list-style:none;">
                <span><?php wpr_icon( 'gift', 'wpr-icon--xs' ); ?> Consolation Coupons (<?php echo count( $consol_filtered ); ?>)</span>
            </summary>
            <div style="padding:0.6em 0.8em;display:flex;flex-direction:column;gap:0.5em;">
                <?php foreach ( $consol_filtered as $cpost ) :
                    $coupon = function_exists( 'wc_get_coupon' ) ? new WC_Coupon( $cpost->ID ) : null;
                    $used   = $coupon ? $coupon->get_usage_count() > 0 : false;
                ?>
                    <div style="display:flex;align-items:center;gap:0.6em;border:1px solid var(--wpr-border-color);border-radius:6px;padding:0.6em 0.8em;">
                        <span style="flex-shrink:0;color:var(--wpr-text-muted);"><?php wpr_icon( 'ticket', 'wpr-icon--sm' ); ?></span>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;font-size:0.9em;color:var(--wpr-text-primary);">
                                <code class="wpr-copy-code" style="background:var(--wpr-bg-muted);color:var(--wpr-text-primary);padding:3px 8px;border-radius:4px;letter-spacing:0.5px;font-size:0.85em;cursor:pointer;" title="Click to copy"><?php echo esc_html( $cpost->post_title ); ?></code>
                            </div>
                            <?php if ( $coupon ) :
                                $amount = $coupon->get_amount();
                                $type   = $coupon->get_discount_type();
                                $desc   = ( $type === 'percent' ) ? $amount . '% off' : wc_price( $amount );
                            ?>
                                <div style="font-size:0.75em;color:var(--wpr-text-muted);margin-top:2px;"><?php echo wp_kses_post( $desc ); ?> <?php echo esc_html( $type === 'percent' ? 'discount' : 'off' ); ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="flex-shrink:0;">
                            <?php if ( $used ) : ?>
                                <span style="background:var(--wpr-bg-muted);color:var(--wpr-text-muted);padding:2px 8px;border-radius:10px;font-size:0.65em;font-weight:700;text-transform:uppercase;">Used</span>
                            <?php else : ?>
                                <span style="background:var(--wpr-success-bg);color:var(--wpr-success-text);padding:2px 8px;border-radius:10px;font-size:0.65em;font-weight:700;text-transform:uppercase;">Ready</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

    <script>
    // Click-to-copy for coupon codes.
    (function(){
        document.querySelectorAll('.wpr-copy-code').forEach(function(el){
            el.addEventListener('click', function(){
                var code = el.textContent.trim();
                var done = function(){ el.textContent = code + ' ✓'; setTimeout(function(){ el.textContent = code; }, 1500); };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(code).then(done, function(){ fallback(); });
                } else { fallback(); }
                function fallback(){
                    var ta = document.createElement('textarea'); ta.value = code;
                    document.body.appendChild(ta); ta.select();
                    try { document.execCommand('copy'); done(); } catch(e){}
                    document.body.removeChild(ta);
                }
            });
        });
    })();
    </script>

<?php endif; ?>
