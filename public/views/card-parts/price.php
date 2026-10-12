<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
    <!-- Price per entry -->
    <div class="rc-card__price">
        <span class="rc-card__price-amount"><?php echo esc_html( wpr_price( $raffle->ticket_price ) ); ?></span>
        <span class="rc-card__price-label"><?php esc_html_e( 'per entry', 'wpraffle' ); ?></span>
    </div>

