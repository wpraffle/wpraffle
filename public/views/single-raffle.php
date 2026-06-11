<?php
/**
 * Custom single product template for raffle products.
 * Wraps custom raffle layout with the theme's header/footer.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
    // Block Theme / FSE
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php wp_head(); ?>
    </head>
    <body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    <div class="wp-site-blocks">
        <?php 
        if ( function_exists( 'block_template_part' ) ) {
            block_template_part( 'header' );
        }
        ?>
        <main class="wp-block-group" style="padding: 40px 0; background: #f8f9fd; min-height: 60vh;">
            <?php
            while ( have_posts() ) :
                the_post();
                $product_id = get_the_ID();
                $raffle_id  = (int) get_post_meta( $product_id, '_raffle_id', true );
                if ( $raffle_id ) {
                    global $wpdb;
                    $raffle = $wpdb->get_row( $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
                        $raffle_id
                    ) );
                    if ( $raffle ) {
                        $packages  = json_decode( $raffle->packages, true ) ?: array();
                        $progress  = ( $raffle->total_tickets > 0 ) ? round( ( $raffle->sold_tickets / $raffle->total_tickets ) * 100 ) : 0;
                        $remaining = $raffle->total_tickets - $raffle->sold_tickets;
                        
                        wp_enqueue_script( 'raffle-public' );
                        wp_enqueue_style( 'raffle-public' );
                        
                        include RAFFLE_SYSTEM_PATH . 'public/views/raffle-display.php';
                    } else {
                        the_content();
                    }
                } else {
                    the_content();
                }
            endwhile;
            ?>
        </main>
        <?php 
        if ( function_exists( 'block_template_part' ) ) {
            block_template_part( 'footer' );
        }
        ?>
    </div>
    <?php wp_footer(); ?>
    </body>
    </html>
    <?php
} else {
    // Classic Theme
    get_header();
    while ( have_posts() ) :
        the_post();
        $product_id = get_the_ID();
        $raffle_id  = (int) get_post_meta( $product_id, '_raffle_id', true );
        if ( $raffle_id ) {
            global $wpdb;
            $raffle = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
                $raffle_id
            ) );
            if ( $raffle ) {
                $packages  = json_decode( $raffle->packages, true ) ?: array();
                $progress  = ( $raffle->total_tickets > 0 ) ? round( ( $raffle->sold_tickets / $raffle->total_tickets ) * 100 ) : 0;
                $remaining = $raffle->total_tickets - $raffle->sold_tickets;
                
                wp_enqueue_script( 'raffle-public' );
                wp_enqueue_style( 'raffle-public' );
                
                echo '<div class="raffle-single-page-wrapper" style="padding: 40px 0; background: #f8f9fd; min-height: 60vh;">';
                include RAFFLE_SYSTEM_PATH . 'public/views/raffle-display.php';
                echo '</div>';
            } else {
                the_content();
            }
        } else {
            the_content();
        }
    endwhile;
    get_footer();
}
