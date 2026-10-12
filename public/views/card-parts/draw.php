<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php if ( $has_draw_date ) : ?>
<p class="rc-card__draw-bar"><span><?php esc_html_e( 'Draw:', 'wpraffle' ); ?></span> <time datetime="<?php echo esc_attr( $draw_iso ); ?>"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $raffle->draw_date ) ); ?></time></p>
<?php endif; ?>
