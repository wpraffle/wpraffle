<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
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

