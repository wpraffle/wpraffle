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
$draw_iso      = $has_draw_date ? ( new DateTimeImmutable( $raffle->draw_date, wp_timezone() ) )->format( DATE_ATOM ) : '';

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
     data-raffle-id="<?php echo esc_attr( $raffle->id ); ?>"
     tabindex="0" role="link"
     aria-label="<?php echo esc_attr( sprintf(
         /* translators: %s: raffle title. */
         __( 'View competition: %s', 'wpraffle' ),
         $raffle->title
     ) ); ?>">

    <?php
    $allowed_fields = array( 'title','image','draw','countdown','cash','price','progress','charity','cta' );
    $card_fields = apply_filters( 'wpraffle_card_fields', $allowed_fields, $raffle );
    $card_fields = is_array( $card_fields ) ? array_values( array_unique( array_filter( $card_fields, 'is_string' ) ) ) : $allowed_fields;
    foreach ( $card_fields as $card_field ) {
        if ( in_array( $card_field, $allowed_fields, true ) ) { include RAFFLE_SYSTEM_PATH . 'public/views/card-parts/' . $card_field . '.php'; }
    }
    ?>
</div>
