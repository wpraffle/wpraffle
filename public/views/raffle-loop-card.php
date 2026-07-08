<?php
/**
 * Raffle competition card for WooCommerce shop loop.
 * Replaces default product card when product is linked to a raffle.
 *
 * Variables expected: $raffle (object), $product (WC_Product)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$progress    = ( $raffle->total_tickets > 0 ) ? round( ( $raffle->sold_tickets / $raffle->total_tickets ) * 100 ) : 0;
$remaining   = $raffle->total_tickets - $raffle->sold_tickets;

// Determine link — works in WooCommerce loop ($product is WC_Product) and shortcode context ($product may be stdClass)
if ( $product instanceof WC_Product ) {
    $link = get_permalink( $product->get_id() );
} elseif ( ! empty( $raffle->wc_product_id ) ) {
    $link = get_permalink( $raffle->wc_product_id );
} else {
    $link = '#';
}


// Cash alternative — use wpr_price() so currency position/decimals match the
// rest of the plugin (was a raw symbol + number_format, which ignored settings).
$has_cash_alt  = ! empty( $raffle->enable_cash_alternative );
$cash_alt_amt  = $has_cash_alt ? wpr_price( $raffle->cash_alternative_amount ) : '';

// Instant wins count — prefer a precomputed value (batched by the caller to
// avoid a per-card N+1 query); fall back to a single query if not provided.
if ( ! isset( $iw_count ) ) {
    global $wpdb;
    $iw_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}raffle_instant_wins WHERE raffle_id = %d",
        $raffle->id
    ) );
} else {
    $iw_count = (int) $iw_count;
}

// Progress bar color (urgency-based coloring: green -> amber -> red)
$bar_color = 'var(--wpr-success)';
if ( $progress >= 85 ) {
    $bar_color = 'var(--wpr-danger)';
} elseif ( $progress >= 50 ) {
    $bar_color = 'var(--wpr-warning)';
}

// Draw date
$has_draw_date = ! empty( $raffle->draw_date );
$draw_iso      = $has_draw_date ? gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $raffle->draw_date ) ) : '';

// Card state — drives status badges and click/keyboard behaviour.
$card_state       = Raffle_Public::get_raffle_state( $raffle );
$is_sold_out      = ( $card_state === 'live' && $remaining <= 0 );
$is_ended         = ( $card_state === 'ended' );
$is_ending_soon   = ( $card_state === 'live' && $has_draw_date && ( strtotime( $raffle->draw_date ) - time() ) <= DAY_IN_SECONDS && ( strtotime( $raffle->draw_date ) - time() ) > 0 );
$is_closed        = $is_sold_out || $is_ended;

// Status badge to overlay on the image.
$status_badge_label = '';
$status_badge_mod   = '';
if ( $is_ended ) {
    $status_badge_label = __( 'ENDED', 'wpraffle' );
    $status_badge_mod   = 'rc-card__status--ended';
} elseif ( $is_sold_out ) {
    $status_badge_label = __( 'SOLD OUT', 'wpraffle' );
    $status_badge_mod   = 'rc-card__status--soldout';
} elseif ( $is_ending_soon ) {
    $status_badge_label = __( 'ENDING SOON', 'wpraffle' );
    $status_badge_mod   = 'rc-card__status--soon';
}

// CTA label adapts to state.
$cta_label = $is_closed ? __( 'VIEW RESULTS', 'wpraffle' ) : __( 'VIEW COMPETITION', 'wpraffle' );
?>

<div class="rc-card<?php echo $is_ended ? ' rc-card--expired' : ''; echo $is_sold_out ? ' rc-card--sold-out' : ''; echo $is_ending_soon ? ' rc-card--ending-soon' : ''; ?>"
     data-raffle-link="<?php echo esc_url( $link ); ?>"
     tabindex="0" role="link"
     aria-label="<?php echo esc_attr( sprintf(
         /* translators: %s: raffle title. */
         __( 'View competition: %s', 'wpraffle' ),
         $raffle->title
     ) ); ?>">

    <!-- Title (uppercase via CSS so screen readers don't spell it out) -->
    <div class="rc-card__title">
        <?php echo esc_html( $raffle->title ); ?>
        <?php if ( $has_cash_alt ) : ?>
            <span class="rc-card__title-alt">(OR <?php echo esc_html( $cash_alt_amt ); ?>)</span>
        <?php endif; ?>
        <?php if ( $iw_count > 0 ) : ?>
            <div class="rc-card__title-iw">WITH <?php echo esc_html( $iw_count ); ?> INSTANT WIN<?php echo $iw_count > 1 ? 'S' : ''; ?></div>
        <?php endif; ?>
    </div>

    <!-- Image -->
    <div class="rc-card__image">
        <?php if ( $raffle->prize_image ) : ?>
            <img src="<?php echo esc_url( $raffle->prize_image ); ?>" alt="<?php echo esc_attr( $raffle->title ); ?>">
        <?php else : ?>
            <div class="rc-card__image-placeholder"><?php wpr_icon( 'gift', 'wpr-icon--lg' ); ?></div>
        <?php endif; ?>

        <?php if ( $status_badge_label ) : ?>
            <div class="rc-card__status <?php echo esc_attr( $status_badge_mod ); ?>"><?php echo esc_html( $status_badge_label ); ?></div>
        <?php elseif ( $iw_count > 0 ) : ?>
            <div class="rc-card__iw-badge">
                <span>+<?php echo esc_html( $iw_count ); ?></span> INSTANT WINS
            </div>
        <?php endif; ?>

    </div>

    <!-- Draw date bar -->
    <?php if ( $has_draw_date ) : ?>
        <div class="rc-card__draw-bar">
            DRAW <?php echo esc_html( strtoupper( date_i18n( 'D jS M', strtotime( $raffle->draw_date ) ) ) ); ?> @ <?php echo esc_html( date_i18n( 'g:iA', strtotime( $raffle->draw_date ) ) ); ?>
        </div>
    <?php endif; ?>

    <!-- Countdown -->
    <?php
    if ( $has_draw_date && $card_state === 'live' ) :
    ?>
        <div class="rc-card__countdown" data-draw-date="<?php echo esc_attr( $draw_iso ); ?>">
            <div class="rc-card__cd-unit">
                <span class="rc-card__cd-num rc-cd-days">0</span>
                <span class="rc-card__cd-label">DAYS</span>
            </div>
            <div class="rc-card__cd-unit">
                <span class="rc-card__cd-num rc-cd-hours">0</span>
                <span class="rc-card__cd-label">HRS</span>
            </div>
            <div class="rc-card__cd-unit">
                <span class="rc-card__cd-num rc-cd-mins">0</span>
                <span class="rc-card__cd-label">MINS</span>
            </div>
            <div class="rc-card__cd-unit">
                <span class="rc-card__cd-num rc-cd-secs">0</span>
                <span class="rc-card__cd-label">SECS</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Cash alternative bar -->
    <?php if ( $has_cash_alt ) : ?>
        <div class="rc-card__cash-bar">
            CASH ALTERNATIVE: <?php echo esc_html( $cash_alt_amt ); ?>
        </div>
    <?php endif; ?>

    <!-- Price per entry -->
    <div class="rc-card__price">
        <span class="rc-card__price-amount"><?php echo esc_html( wpr_price( $raffle->ticket_price ) ); ?></span>
        <span class="rc-card__price-label">PER ENTRY</span>
    </div>

    <!-- Progress bar -->
    <div class="rc-card__progress">
        <div class="rc-card__progress-stats">
            <span>Sold: <?php echo esc_html( $progress ); ?>%</span>
            <span><?php echo esc_html( $raffle->sold_tickets ); ?> / <?php echo esc_html( $raffle->total_tickets ); ?></span>
        </div>
        <div class="rc-card__progress-bar">
            <div class="rc-card__progress-fill" style="width: <?php echo esc_attr( $progress ); ?>%; background: <?php echo esc_attr( $bar_color ); ?>;"></div>
        </div>
    </div>

    <!-- Charity badge -->
    <?php if ( class_exists( 'Raffle_Charity' ) ) :
        $ci = Raffle_Charity::get_raffle_charity( $raffle->id );
        if ( $ci ) : ?>
            <div class="rc-card__charity">
                <?php wpr_icon( 'gift', 'wpr-icon--xs' ); ?>
                <span><?php echo esc_html( $ci['percent'] ); ?>% to <?php echo esc_html( $ci['charity']->name ); ?></span>
            </div>
        <?php endif;
    endif; ?>

    <!-- CTA -->
    <a href="<?php echo esc_url( $link ); ?>" class="rc-card__cta<?php echo $is_closed ? ' rc-card__cta--closed' : ''; ?>"><?php echo esc_html( $cta_label ); ?></a>
</div>
