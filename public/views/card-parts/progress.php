<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
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

