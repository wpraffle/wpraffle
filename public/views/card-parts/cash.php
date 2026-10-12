<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
    <!-- Cash alternative bar -->
    <?php if ( $has_cash_alt ) : ?>
        <div class="rc-card__cash-bar">
            CASH ALTERNATIVE: <?php echo esc_html( $cash_alt_amt ); ?>
        </div>
    <?php endif; ?>

