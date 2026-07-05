<?php
/**
 * Plugin Name: WPRaffle
 * Plugin URI:  https://github.com/wpraffle/wpraffle
 * Description: A fully-featured WooCommerce raffle & competition system. Run live competitions, manage tickets, instant wins, skill questions, postal entries, and lifecycle states — all for free.
 * Version:     1.2.0
 * Author:      WPRaffle
 * Author URI:  https://github.com/wpraffle
 * Text Domain: wpraffle
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RAFFLE_SYSTEM_VERSION', '1.2.0' );
define( 'RAFFLE_SYSTEM_PATH', plugin_dir_path( __FILE__ ) );
define( 'RAFFLE_SYSTEM_URL', plugin_dir_url( __FILE__ ) );

// ─────────────────────────────────────────────────────────────────────
// Dependency Checks
// ─────────────────────────────────────────────────────────────────────

/**
 * Check WooCommerce dependency on activation — bail if not active.
 */
register_activation_hook( __FILE__, function() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            '<h1>WPRaffle requires WooCommerce</h1>' .
            '<p>WPRaffle is a WooCommerce raffle &amp; competition system and cannot run without it.</p>' .
            '<p>Please install and activate <a href="https://wordpress.org/plugins/woocommerce/">WooCommerce</a> first, then re-activate WPRaffle.</p>',
            'Plugin Activation Error',
            array( 'response' => 200, 'back_link' => true )
        );
    }
} );

/**
 * If WooCommerce is deactivated while WPRaffle is active, deactivate WPRaffle too.
 */
add_action( 'admin_init', function() {
    if ( ! class_exists( 'WooCommerce' ) && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>WPRaffle deactivated.</strong> WPRaffle requires WooCommerce to be installed and active. Please <a href="' . esc_url( admin_url( 'plugin-install.php?tab=search&s=woocommerce' ) ) . '">install WooCommerce</a> and re-activate WPRaffle.</p></div>';
        } );
    }
} );

/**
 * Admin notices: WooCommerce required + WooWallet recommended.
 */
add_action( 'admin_notices', function() {
    // WooCommerce missing
    if ( ! class_exists( 'WooCommerce' ) ) {
        echo '<div class="notice notice-error"><p><strong>WPRaffle requires WooCommerce.</strong> Please install and activate WooCommerce to use WPRaffle.</p></div>';
        return;
    }

    // WooWallet recommended (not required)
    if ( ! function_exists( 'woo_wallet' ) ) {
        echo '<div class="notice notice-info is-dismissible"><p><strong>Recommendation:</strong> Install <a href="' . esc_url( admin_url( 'plugin-install.php?tab=search&s=woo+wallet' ) ) . '">WooWallet</a> to enable the Account Cash wallet for raffle winnings (automatic payouts to winners). Without WooWallet, winnings are recorded but must be paid manually.</p></div>';
    }
} );

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
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-privacy.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-rate-limiter.php';

// Feature expansion: schema setup, charity, credits, wallet, RG, account.
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-setup.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-charity.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-credits.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-wallet-adapter.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-responsible-gambling.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-account.php';
require_once RAFFLE_SYSTEM_PATH . 'includes/class-raffle-styling.php';

// Activation
register_activation_hook( __FILE__, array( 'Raffle_Activator', 'activate' ) );

// Deactivation — clear all scheduled cron events so they don't fire as orphans.
register_deactivation_hook( __FILE__, function() {
    foreach ( array(
        'raffle_cleanup_reservations',
        'raffle_charity_allocations_refresh',
        'raffle_system_auto_draw_cron',
        'raffle_draw_reminder_cron',
        'wpraffle_check_updates',
    ) as $event ) {
        $ts = wp_next_scheduled( $event );
        while ( $ts ) {
            wp_unschedule_event( $ts, $event );
            $ts = wp_next_scheduled( $event );
        }
    }
} );

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
// BUG-2 FIX: Schedule cron inside plugins_loaded (not at file include time)
add_action( 'plugins_loaded', function() {
    if ( ! wp_next_scheduled( 'raffle_cleanup_reservations' ) ) {
        wp_schedule_event( time(), 'hourly', 'raffle_cleanup_reservations' );
    }
    // Charity totals refresh — was previously hooked but never scheduled, so
    // totals only updated when an admin clicked "Recalculate". Now hourly.
    if ( ! wp_next_scheduled( 'raffle_charity_allocations_refresh' ) ) {
        wp_schedule_event( time(), 'hourly', 'raffle_charity_allocations_refresh' );
    }
}, 5 );

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

    // Feature expansion classes
    new Raffle_Charity();
    if ( class_exists( 'WooCommerce' ) ) {
        new Raffle_Account();
    }

    // Initialize Privacy & Rate Limiter
    Raffle_Privacy::init();
    Raffle_Rate_Limiter::register_ajax();

    // Load plugin text domain
    load_plugin_textdomain( 'wpraffle', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// Run schema migrations for the feature expansion on admin load (idempotent).
add_action( 'admin_init', array( 'Raffle_Setup', 'run_migrations' ) );

// Flush rewrite rules — check against a dedicated flag so reinstalls always trigger.
add_action( 'admin_init', function () {
    $flush_flag = get_option( 'wpraffle_endpoints_flushed' );
    if ( $flush_flag !== RAFFLE_SYSTEM_VERSION ) {
        flush_rewrite_rules();
        update_option( 'wpraffle_endpoints_flushed', RAFFLE_SYSTEM_VERSION );
    }
} );

// Output the selected styling preset as inline CSS.
add_action( 'wp_enqueue_scripts', array( 'Raffle_Styling', 'output_inline_css' ), 100 );

// Register the charity CPT + meta boxes.
add_action( 'init', array( 'Raffle_Setup', 'register_content_types' ), 5 );
add_action( 'add_meta_boxes', array( 'Raffle_Setup', 'add_charity_meta_box' ) );
add_action( 'save_post_raffle_charity', array( 'Raffle_Setup', 'save_charity_meta' ) );

// Set parent_file so the Raffles menu stays highlighted on charity screens.
add_filter( 'parent_file', function( $parent_file ) {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && 'raffle_charity' === $screen->post_type ) {
        return 'raffle-system';
    }
    return $parent_file;
}, 99 );

// Enforce Responsible-Gambling controls at every purchase gate. Gates pass
// ($allowed, $user_id, $amount, $buyer_email); the email is honoured for guest
// buyers so exclusion/limits bind to guests too. Apply via:
//   $rg = apply_filters( 'raffle_pre_purchase_check', true, $user_id, $amount, $buyer_email );
add_filter( 'raffle_pre_purchase_check', function ( $allowed, $user_id, $amount, $buyer_email = '' ) {
    if ( class_exists( 'Raffle_Responsible_Gambling' ) ) {
        $rg = Raffle_Responsible_Gambling::check_purchase_allowed( $user_id, $amount, $buyer_email );
        if ( is_wp_error( $rg ) ) {
            return $rg;
        }
    }
    return $allowed;
}, 10, 4 );

// Snapshot charity allocation inside the draw transaction.
add_action( 'raffle_draw_completed', function ( $raffle_id, $winner_ticket ) {
    // Charity allocation snapshot (idempotent).
    if ( class_exists( 'Raffle_Charity' ) ) {
        Raffle_Charity::snapshot_allocation( $raffle_id );
    }
}, 10, 2 );

// Wallet payout: resolve the winning ticket's owner and credit winnings to
// their wallet (idempotent — safe even if the draw action re-fires). Runs at
// priority 15 so the charity snapshot (10) completes first. Winnings use the
// raffle's prize_value if set, else 0 (operator pays manually).
add_action( 'raffle_draw_completed', function ( $raffle_id, $winner_ticket ) {
    if ( ! class_exists( 'Raffle_Wallet_Adapter' ) || ! property_exists( $winner_ticket, 'id' ) ) {
        return;
    }
    global $wpdb;
    $winner_purchase = $wpdb->get_row( $wpdb->prepare(
        "SELECT buyer_email, buyer_name FROM {$wpdb->prefix}raffle_purchases WHERE id = %d",
        isset( $winner_ticket->purchase_id ) ? (int) $winner_ticket->purchase_id : 0
    ) );
    if ( ! $winner_purchase ) {
        return;
    }
    $user = get_user_by( 'email', $winner_purchase->buyer_email );
    if ( ! $user ) {
        return; // Guest winner — operator handles payout manually.
    }
    $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT prize_value FROM {$wpdb->prefix}raffles WHERE id = %d", $raffle_id ) );
    $amount = $raffle ? (float) $raffle->prize_value : 0.0;
    if ( $amount <= 0 ) {
        return; // No cash winnings configured.
    }
    Raffle_Wallet_Adapter::credit_winnings(
        (int) $raffle_id,
        (int) $winner_ticket->id,
        (int) $user->ID,
        $winner_purchase->buyer_email,
        $amount,
        'winnings'
    );
}, 15, 2 );

// Consolation coupons: when a raffle with enable_consolation_coupon set
// completes its draw, every non-winning entrant gets an emailed WooCommerce
// coupon. Idempotent via a raffle-level meta flag so re-firing the draw
// action can't double-issue.
add_action( 'raffle_draw_completed', function ( $raffle_id, $winner_ticket ) {
    if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'Raffle_Email' ) ) {
        return;
    }
    global $wpdb;
    $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d", $raffle_id ) );
    if ( ! $raffle || empty( $raffle->enable_consolation_coupon ) ) {
        return;
    }
    // Idempotency: bail if we've already issued consolation coupons.
    $sent_flag = 'consolation_sent_' . $raffle_id;
    if ( get_transient( $sent_flag ) ) {
        return;
    }
    set_transient( $sent_flag, 1, YEAR_IN_SECONDS );

    // Parse the consolation config.
    $config = wp_parse_args( json_decode( $raffle->consolation_config, true ) ?: array(), array(
        'type'        => 'percent',
        'amount'      => 10,
        'expiry_days' => 30,
    ) );

    // Winner email(s) — exclude them from consolation.
    $winner_emails = array();
    if ( isset( $winner_ticket->buyer_email ) ) {
        $winner_emails[] = strtolower( $winner_ticket->buyer_email );
    }

    // All unique non-winning completed purchasers.
    $buyers = $wpdb->get_results( $wpdb->prepare(
        "SELECT DISTINCT buyer_email, buyer_name FROM {$wpdb->prefix}raffle_purchases
         WHERE raffle_id = %d AND payment_status = 'completed' AND buyer_email != ''",
        $raffle_id
    ) );

    foreach ( $buyers as $buyer ) {
        if ( in_array( strtolower( $buyer->buyer_email ), $winner_emails, true ) ) {
            continue;
        }
        // Create a single-use, email-restricted coupon.
        $code = 'CONSOL-' . strtoupper( substr( md5( $buyer->buyer_email . '|' . $raffle_id . '|' . wp_rand() ), 0, 8 ) );
        try {
            $coupon = new WC_Coupon();
            $coupon->set_code( $code );
            $coupon->set_discount_type( $config['type'] === 'fixed' ? 'fixed_cart' : 'percent' );
            $coupon->set_amount( (float) $config['amount'] );
            $coupon->set_individual_use( true );
            $coupon->set_email_restrictions( array( $buyer->buyer_email ) );
            $coupon->set_date_expires( time() + ( (int) $config['expiry_days'] * DAY_IN_SECONDS ) );
            $coupon->set_usage_limit( 1 );
            $coupon->save();
        } catch ( Exception $e ) {
            continue;
        }

        $discount_label = $config['type'] === 'fixed'
            ? wpr_price( $config['amount'] )
            : (float) $config['amount'] . '%';
        Raffle_Email::send_consolation_coupon( $buyer->buyer_email, $buyer->buyer_name, $raffle, $code, $discount_label );

        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( $raffle_id, 'consolation_coupon', array(
                'email'  => $buyer->buyer_email,
                'code'   => $code,
                'amount' => $discount_label,
            ), 'system' );
        }
    }
}, 20, 2 );

// Virality: capture ?ref=REF-XXXX from the query string into a 30-day cookie
// so a referral is attributed even if the buyer browses other pages first.
// The cookie is consumed inside Raffle_WooCommerce::on_payment_complete().
add_action( 'template_redirect', function () {
    if ( ! isset( $_GET['ref'] ) ) {
        return;
    }
    $code = preg_replace( '/[^A-Za-z0-9\-]/', '', sanitize_text_field( wp_unslash( $_GET['ref'] ) ) );
    if ( ! $code ) {
        return;
    }
    // Persist for 30 days. Use a cookie (not just WC session) so it survives
    // across guest sessions / devices within the attribution window.
    setcookie( 'wpraffle_ref', $code, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN );
    $_COOKIE['wpraffle_ref'] = $code;
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
        "SELECT id, title FROM {$wpdb->prefix}raffles WHERE status = 'active' AND draw_type = 'auto' AND draw_date <= %s",
        $now
    ) );

    if ( empty( $raffles ) ) {
        return;
    }

    foreach ( $raffles as $raffle ) {
        // Generate pre-draw fairness proof (SHA-256 HMAC of raffle_id + timestamp + WP salt)
        $pre_seed  = wp_generate_password( 32, false );
        $pre_proof = hash_hmac( 'sha256', $raffle->id . '|' . microtime( true ) . '|' . $pre_seed, wp_salt( 'auth' ) );

        // Log the pre-draw commitment
        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( $raffle->id, 'auto_draw_start', array(
                'title'     => $raffle->title,
                'pre_seed'  => $pre_seed,
                'pre_proof' => $pre_proof,
            ), 'cron' );
        }

        // Execute the draw
        $result = Raffle_Draw::do_draw( $raffle->id );

        // Generate post-draw fairness proof
        $post_proof = hash_hmac( 'sha256', $raffle->id . '|' . $pre_seed . '|' . wp_salt( 'auth' ), 'wpraffle_draw_verification' );

        // Store fairness proof on the raffle for public verification
        $wpdb->update(
            $wpdb->prefix . 'raffles',
            array( 'verified_result' => $post_proof ),
            array( 'id' => $raffle->id ),
            array( '%s' ),
            array( '%d' )
        );

        // Log the completion
        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( $raffle->id, 'auto_draw_complete', array(
                'title'     => $raffle->title,
                'post_proof' => $post_proof,
                'result'    => is_wp_error( $result ) ? $result->get_error_message() : 'success',
            ), $pre_proof );
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

/**
 * Normalise a raffle's packages JSON into a uniform array of bundle objects.
 *
 * Supports two legacy shapes and one new shape:
 *   - Bare ints:   [5, 10, 15]                      → standard price each.
 *   - Old objects: [{"qty":5}]                      → standard price each.
 *   - New bundles: [{"qty":5,"price":25,"label":..,"badge":..}] → fixed bundle price.
 *
 * Each returned item always has keys: qty (int), price (float, 0 = standard),
 * label (string, ''), badge (string, '').
 *
 * @param string $packages_json  Raw JSON from $raffle->packages.
 * @param float  $ticket_price   Standard single-ticket price (for savings calc).
 * @return array Normalised bundle objects, filtered to qty >= 1.
 */
function wpraffle_normalise_packages( $packages_json, $ticket_price = 0.0 ) {
    $raw = json_decode( (string) $packages_json, true );
    if ( ! is_array( $raw ) ) {
        $raw = array();
    }
    $out = array();
    foreach ( $raw as $pkg ) {
        if ( is_int( $pkg ) || is_float( $pkg ) ) {
            $qty = (int) $pkg;
            if ( $qty < 1 ) {
                continue;
            }
            $out[] = array(
                'qty'   => $qty,
                'price' => 0.0,
                'label' => '',
                'badge' => '',
            );
            continue;
        }
        if ( ! is_array( $pkg ) || empty( $pkg['qty'] ) ) {
            continue;
        }
        $qty   = absint( $pkg['qty'] );
        $price = isset( $pkg['price'] ) ? (float) $pkg['price'] : 0.0;
        if ( $qty < 1 ) {
            continue;
        }
        $out[] = array(
            'qty'   => $qty,
            'price' => $price,
            'label' => isset( $pkg['label'] ) ? sanitize_text_field( $pkg['label'] ) : '',
            'badge' => isset( $pkg['badge'] ) ? sanitize_text_field( $pkg['badge'] ) : '',
        );
    }
    return $out;
}

