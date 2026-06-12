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


// Cash alternative
$has_cash_alt  = ! empty( $raffle->enable_cash_alternative );
$cash_alt_amt  = $has_cash_alt ? wpr_currency_symbol() . number_format( $raffle->cash_alternative_amount, 0 ) : '';

// Instant wins count
global $wpdb;
$iw_count = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}raffle_instant_wins WHERE raffle_id = %d",
    $raffle->id
) );

// Progress bar color
$bar_color = '#dc2626'; // red
if ( $progress >= 75 ) {
    $bar_color = '#dc2626';
} elseif ( $progress >= 40 ) {
    $bar_color = '#f59e0b';
}

// Draw date
$has_draw_date = ! empty( $raffle->draw_date );
$draw_iso      = $has_draw_date ? gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $raffle->draw_date ) ) : '';
?>

<div class="rc-card" data-raffle-link="<?php echo esc_url( $link ); ?>">

    <!-- Title -->
    <div class="rc-card__title">
        <?php echo esc_html( strtoupper( $raffle->title ) ); ?>
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
            <div class="rc-card__image-placeholder"><?php echo wpr_get_icon( 'gift', 'wpr-icon--lg' ); ?></div>
        <?php endif; ?>

        <?php if ( $iw_count > 0 ) : ?>
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
    $r_computed_state = Raffle_Public::get_raffle_state( $raffle );
    if ( $has_draw_date && $r_computed_state === 'live' ) : 
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

    <!-- CTA -->
    <a href="<?php echo esc_url( $link ); ?>" class="rc-card__cta">VIEW COMPETITION</a>
</div>
