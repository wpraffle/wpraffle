<?php
/**
 * Plugin Name: WPRaffle
 * Plugin URI:  https://github.com/wpraffle/wpraffle
 * Description: A fully-featured WooCommerce raffle & competition system. Run live competitions, manage tickets, instant wins, skill questions, postal entries, and lifecycle states — all for free.
 * Version:     1.0.0
 * Author:      WPRaffle
 * Author URI:  https://github.com/wpraffle
 * Text Domain: wpraffle
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RAFFLE_SYSTEM_VERSION', '1.0.0' );
define( 'RAFFLE_SYSTEM_PATH', plugin_dir_path( __FILE__ ) );
define( 'RAFFLE_SYSTEM_URL', plugin_dir_url( __FILE__ ) );

// Includes
require_once RAFFLE_SYSTEM_PATH . 'includes/functions-icons.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-activator.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-tickets.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-purchase.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-draw.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-email.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-duplicates.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-woocommerce.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-instant-wins.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-audit.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-prizes.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-referrals.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-free-entry.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-templates.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-reservations.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-geo.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-live-draw.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-pdf.php';
require_once RAFFLE_SYSTEM_PATH . 'admin/class-raffle-admin.php';
require_once RAFFLE_SYSTEM_PATH . 'admin/class-raffle-analytics.php';
require_once RAFFLE_SYSTEM_PATH . 'public/class-raffle-public.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-dashboard-widgets.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-elementor.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-updater.php';

// Activation
register_activation_hook( __FILE__, array( 'Raffle_Activator', 'activate' ) );

// Inject SVG sprite once per page (frontend + admin)
add_action( 'wp_body_open', 'wpr_output_icon_sprite', 1 );
add_action( 'wp_footer',    'wpr_output_icon_sprite', 1 );
add_action( 'admin_footer',  'wpr_output_icon_sprite', 1 );

// Feature 4: Raffle Categories & Tags (Taxonomies)
add_action( 'init', function () {
    register_taxonomy( 'raffle_category', 'product', array(
        'labels'            => array(
            'name'          => 'Raffle Categories',
            'singular_name' => 'Raffle Category',
            'add_new_item'  => 'Add Raffle Category',
            'edit_item'     => 'Edit Raffle Category',
        ),
        'public'       => true,
        'hierarchical' => true,
        'rewrite'      => array( 'slug' => 'raffle-category' ),
        'show_in_rest' => true,
        'show_ui'      => true,
        'show_in_menu' => false, // Hidden from WooCommerce menu — shown via Raffles submenu
        'meta_box_cb'  => 'post_categories_meta_box',
    ) );

    register_taxonomy( 'raffle_tag', 'product', array(
        'labels'            => array(
            'name'          => 'Raffle Tags',
            'singular_name' => 'Raffle Tag',
        ),
        'public'       => true,
        'hierarchical' => false,
        'rewrite'      => array( 'slug' => 'raffle-tag' ),
        'show_in_rest' => true,
        'show_ui'      => true,
        'show_in_menu' => false, // Hidden from WooCommerce menu — shown via Raffles submenu
        'meta_box_cb'  => 'post_tags_meta_box',
    ) );
}, 0 );

// Move Raffle taxonomies under the Raffles admin menu
add_filter( 'parent_file', function( $parent_file ) {
    global $current_screen;
    if ( $current_screen && in_array( $current_screen->taxonomy, array( 'raffle_category', 'raffle_tag' ), true ) ) {
        $parent_file = 'raffle-system';
    }
    return $parent_file;
} );

// Feature 9: Cleanup expired reservations periodically
add_action( 'raffle_cleanup_reservations', array( 'Raffle_Reservations', 'cleanup_expired' ) );
if ( ! wp_next_scheduled( 'raffle_cleanup_reservations' ) ) {
    wp_schedule_event( time(), 'hourly', 'raffle_cleanup_reservations' );
}

// Init
add_action( 'plugins_loaded', function () {
    new Raffle_Admin();
    new Raffle_Analytics();
    new Raffle_Public();
    new Raffle_Purchase();
    new Raffle_Draw();
    new Raffle_Duplicates();
    new Raffle_WooCommerce();
    new Raffle_Instant_Wins();
    new Raffle_Dashboard_Widgets();
    new Raffle_Elementor();
    new Raffle_Prizes();
    new Raffle_Referrals();
    new Raffle_Free_Entry();
    new Raffle_Templates();
    new Raffle_Live_Draw();
    new Raffle_Updater();
} );

// Activation notice: show auto-created pages
add_action( 'admin_notices', function () {
    $created = get_transient( 'wpraffle_pages_created' );
    if ( ! $created || ! is_array( $created ) ) {
        return;
    }
    delete_transient( 'wpraffle_pages_created' );
    $pages = get_option( 'wpraffle_pages', array() );
    ?>
    <div class="notice notice-success is-dismissible">
        <p><strong>WPRaffle activated!</strong> The following pages were auto-created:</p>
        <ul style="margin:4px 0 4px 18px;list-style:disc;">
            <?php foreach ( $created as $i => $name ) :
                $key = array_search( $name, array( 'Raffles', 'Past Raffles', 'Live Draw', 'My Raffles' ) );
                // Find the key by title
                $configs = array( 'raffles' => 'Raffles', 'ended' => 'Past Raffles', 'entry_list' => 'Entry Lists', 'live_draw' => 'Live Draw', 'my_raffles' => 'My Raffles' );
                $found_key = array_search( $name, $configs );
                $url = $found_key && ! empty( $pages[ $found_key ] ) ? get_permalink( $pages[ $found_key ] ) : '';
            ?>
                <li>
                    <strong><?php echo esc_html( $name ); ?></strong>
                    <?php if ( $url ) : ?>
                        — <a href="<?php echo esc_url( $url ); ?>" target="_blank">View</a> |
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpraffle-settings&tab=pages' ) ); ?>">Manage</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p>Manage pages at <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpraffle-settings&tab=pages' ) ); ?>">Raffles → Settings → Pages</a></p>
    </div>
    <?php
} );

// WP-Cron Auto Draw
add_action( 'raffle_system_auto_draw_cron', 'raffle_system_handle_auto_draws' );

function raffle_system_handle_auto_draws() {
    global $wpdb;
    $now = current_time( 'mysql' );

    $raffles = $wpdb->get_results( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}raffles WHERE status = 'active' AND draw_type = 'auto' AND draw_date <= %s",
        $now
    ) );

    if ( ! empty( $raffles ) ) {
        foreach ( $raffles as $raffle ) {
            Raffle_Draw::handle_draw( $raffle->id );
        }
    }
}

// WP-Cron Draw Reminders (runs hourly, sends 24h reminder emails)
add_action( 'raffle_draw_reminder_cron', 'raffle_system_send_draw_reminders' );

function raffle_system_send_draw_reminders() {
    global $wpdb;
    $now      = current_time( 'mysql' );
    $in_24hrs = date( 'Y-m-d H:i:s', strtotime( '+24 hours', strtotime( $now ) ) );
    $in_25hrs = date( 'Y-m-d H:i:s', strtotime( '+25 hours', strtotime( $now ) ) );

    // Raffles drawing in the next 24-25 hour window (to avoid duplicate sends)
    $raffles = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}raffles 
         WHERE status = 'active' 
           AND draw_date >= %s 
           AND draw_date <= %s
           AND reminder_sent = 0",
        $now, $in_25hrs
    ) );

    foreach ( $raffles as $raffle ) {
        // Get all unique buyers for this raffle
        $buyers = $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT buyer_name, buyer_email FROM {$wpdb->prefix}raffle_purchases WHERE raffle_id = %d",
            $raffle->id
        ) );
        foreach ( $buyers as $buyer ) {
            Raffle_Email::send_draw_reminder( $buyer->buyer_email, $buyer->buyer_name, $raffle );
        }
        // Mark reminder as sent
        $wpdb->update( "{$wpdb->prefix}raffles", array( 'reminder_sent' => 1 ), array( 'id' => $raffle->id ) );
    }
}

/**
 * Replace {{placeholders}} in legal text with raffle-specific values.
 */
function wpraffle_replace_placeholders( $text, $raffle ) {
    $general = wp_parse_args( get_option( 'wpraffle_general_settings', array() ), array(
        'company_name' => get_bloginfo( 'name' ),
    ) );

    $draw_date_formatted = $raffle->draw_date
        ? date_i18n( get_option( 'date_format' ), strtotime( $raffle->draw_date ) )
        : 'TBD';

    $replacements = array(
        '{{max_tickets}}'       => number_format( (int) $raffle->max_tickets_per_user ),
        '{{total_tickets}}'     => number_format( (int) $raffle->total_tickets ),
        '{{draw_date}}'         => $draw_date_formatted,
        '{{company_name}}'      => $general['company_name'],
        '{{ticket_price}}'      => wpr_price( $raffle->ticket_price ),
        '{{prize_description}}' => $raffle->title,
    );

    return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
}

/**
 * Parse FAQ template text into an array of Q&A pairs.
 * Blocks are separated by blank lines. First line = question, rest = answer.
 */
function wpraffle_parse_faq( $faq_text ) {
    $blocks = preg_split( '/\n{2,}/', trim( $faq_text ) );
    $faqs   = array();
    foreach ( $blocks as $block ) {
        $lines = array_filter( array_map( 'trim', explode( "\n", $block ) ) );
        if ( empty( $lines ) ) continue;
        $question = array_shift( $lines );
        $answer   = implode( "\n", $lines );
        $faqs[]   = array( 'q' => $question, 'a' => $answer );
    }
    return $faqs;
}

