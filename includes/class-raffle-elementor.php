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
     *
     * Uses a glob-based autoloader: any `class-widget-*.php` file in the
     * widgets directory is auto-discovered. The class name is derived from
     * the filename by converting `class-widget-enter-btn.php` →
     * `Raffle_Widget_Enter_Btn` (each hyphen-separated segment capitalised,
     * joined with underscores). Adding a widget = dropping in a file.
     */
    public function register_widgets( $widgets_manager ) {
        $dir   = RAFFLE_SYSTEM_PATH . 'includes/elementor-widgets/';
        $files = glob( $dir . 'class-widget-*.php' );

        if ( empty( $files ) ) {
            return;
        }

        foreach ( $files as $file ) {
            require_once $file;

            // class-widget-enter-btn.php → Raffle_Widget_Enter_Btn
            $basename = basename( $file, '.php' );                  // class-widget-enter-btn
            $basename = str_replace( 'class-widget-', '', $basename ); // enter-btn
            $segments = array_map( 'ucfirst', explode( '-', $basename ) );
            $class    = 'Raffle_Widget_' . implode( '_', $segments );

            if ( class_exists( $class ) ) {
                $widgets_manager->register( new $class() );
            }
        }
    }

    /**
     * Register the shared "Raffle" control used by every single-raffle widget.
     *
     * Lets the operator pick a specific raffle in the editor (so the widget
     * renders in the canvas even when there is no current product page). When
     * unset, the widget falls back to the current product's linked raffle.
     * Must be called from inside a widget's register_controls() while a
     * section is open.
     */
    public static function register_raffle_id_control( $widget ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'raffles';
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;

        $options = array( '' => '— Use current page —' );
        if ( $exists ) {
            $raffles = $wpdb->get_results( "SELECT id, title FROM {$table} ORDER BY created_at DESC LIMIT 200" );
            if ( is_array( $raffles ) ) {
                foreach ( $raffles as $r ) {
                    $options[ $r->id ] = '#' . $r->id . ' — ' . $r->title;
                }
            }
        }

        $widget->add_control( 'raffle_id', array(
            'label'       => __( 'Raffle', 'wpraffle' ),
            'type'        => \Elementor\Controls_Manager::SELECT,
            'default'     => '',
            'options'     => $options,
            'description' => __( 'Pick a specific raffle to display, or leave on "current page" to use the raffle linked to this product.', 'wpraffle' ),
        ) );
    }

    /**
     * Resolve the raffle for a widget instance.
     *
     * Uses the widget's `raffle_id` control when set; otherwise falls back to
     * the current product's linked raffle. Data access goes through the cached
     * wpraffle_get_raffle() helper so repeated widget renders on a page share
     * a single DB lookup.
     *
     * @param \Elementor\Widget_Base $widget
     * @return object|false Raffle row object or false.
     */
    public static function get_raffle_for_widget( $widget ) {
        $settings  = $widget->get_settings_for_display();
        $raffle_id = isset( $settings['raffle_id'] ) ? absint( $settings['raffle_id'] ) : 0;

        if ( $raffle_id ) {
            if ( function_exists( 'wpraffle_get_raffle' ) ) {
                return wpraffle_get_raffle( $raffle_id ) ?: false;
            }
            global $wpdb;
            return $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
                $raffle_id
            ) );
        }

        // Fall back to the current product's linked raffle.
        return self::get_current_raffle();
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
