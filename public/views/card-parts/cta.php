<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
    <!-- CTA -->
    <a href="<?php echo esc_url( $link ); ?>" class="rc-card__cta<?php echo $is_closed ? ' rc-card__cta--closed' : ''; ?>"><?php echo esc_html( $cta_label ); ?></a>
