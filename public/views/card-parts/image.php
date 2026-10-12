<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
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

