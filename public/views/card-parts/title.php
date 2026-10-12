<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
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

