<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Elementor {

    public function __construct() {
        // Only load if Elementor is active
        if ( ! did_action( 'elementor/loaded' ) ) {
            return;
        }

        add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
        add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
        add_action( 'elementor/frontend/after_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
    }

    /**
     * Register custom "Raffle" category in Elementor.
     */
    public function register_category( $elements_manager ) {
        $elements_manager->add_category(
            'raffle-system',
            array(
                'title' => '🎟️ Raffle System',
                'icon'  => 'fas fa-ticket-alt',
            )
        );
    }

    /**
     * Register all raffle widgets.
     */
    public function register_widgets( $widgets_manager ) {
        $widget_files = array(
            'class-widget-image.php',
            'class-widget-title.php',
            'class-widget-price.php',
            'class-widget-progress.php',
            'class-widget-countdown.php',
            'class-widget-quantity.php',
            'class-widget-enter-btn.php',
            'class-widget-question.php',
            'class-widget-instant-wins.php',
            'class-widget-description.php',
            'class-widget-stats-header.php',
            'class-widget-tabs.php',
            'class-widget-trust.php',
            'class-widget-modal.php',
            'class-widget-full-page.php',
            'class-widget-all-competitions.php',
            'class-widget-ended-raffles.php',
            'class-widget-entry-list.php',
        );

        foreach ( $widget_files as $file ) {
            $path = RAFFLE_SYSTEM_PATH . 'includes/elementor-widgets/' . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }

        // Instantiate each widget class
        $widget_classes = array(
            'Raffle_Widget_Image',
            'Raffle_Widget_Title',
            'Raffle_Widget_Price',
            'Raffle_Widget_Progress',
            'Raffle_Widget_Countdown',
            'Raffle_Widget_Quantity',
            'Raffle_Widget_Enter_Btn',
            'Raffle_Widget_Question',
            'Raffle_Widget_Instant_Wins',
            'Raffle_Widget_Description',
            'Raffle_Widget_Stats_Header',
            'Raffle_Widget_Tabs',
            'Raffle_Widget_Trust',
            'Raffle_Widget_Modal',
            'Raffle_Widget_Full_Page',
            'Raffle_Widget_All_Competitions',
            'Raffle_Widget_Ended_Raffles',
            'Raffle_Widget_Entry_List',
        );

        foreach ( $widget_classes as $class ) {
            if ( class_exists( $class ) ) {
                $widgets_manager->register( new $class() );
            }
        }
    }

    /**
     * Enqueue raffle public styles/scripts when Elementor renders frontend.
     */
    public function enqueue_frontend() {
        wp_enqueue_style( 'raffle-public' );
        wp_enqueue_script( 'raffle-public' );
    }

    /**
     * Helper: Get raffle data for the current product/post.
     * Returns raffle object or false.
     */
    public static function get_current_raffle() {
        $product_id = get_the_ID();
        if ( ! $product_id ) {
            return false;
        }

        $raffle_id = (int) get_post_meta( $product_id, '_raffle_id', true );
        if ( ! $raffle_id ) {
            return false;
        }

        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            $raffle_id
        ) );

        return $raffle;
    }

    /**
     * Helper: Get common computed values for a raffle.
     */
    public static function get_raffle_context( $raffle ) {
        if ( ! $raffle ) {
            return array();
        }

        $packages  = json_decode( $raffle->packages, true ) ?: array();
        $progress  = ( $raffle->total_tickets > 0 ) ? round( ( $raffle->sold_tickets / $raffle->total_tickets ) * 100 ) : 0;
        $remaining = $raffle->total_tickets - $raffle->sold_tickets;
        $max_tickets = isset( $raffle->max_tickets_per_user ) ? (int) $raffle->max_tickets_per_user : 100;

        return array(
            'raffle'     => $raffle,
            'packages'   => $packages,
            'progress'   => $progress,
            'remaining'  => $remaining,
            'max_tickets' => $max_tickets,
        );
    }
}