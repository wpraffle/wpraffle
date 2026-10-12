<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
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

