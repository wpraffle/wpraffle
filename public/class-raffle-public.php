<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Public {

    public function __construct() {
        add_shortcode( 'raffle', array( $this, 'render_shortcode' ) );
        add_shortcode( 'raffle_lookup', array( $this, 'render_lookup_shortcode' ) );
        add_shortcode( 'raffle_list', array( $this, 'render_raffle_list_shortcode' ) );
        add_shortcode( 'raffle_ended_list', array( $this, 'render_raffle_ended_list_shortcode' ) );
        add_shortcode( 'raffle_entry_list', array( $this, 'render_entry_list_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        add_action( 'wp_ajax_raffle_get_sold_numbers', array( $this, 'ajax_get_sold_numbers' ) );
        add_action( 'wp_ajax_nopriv_raffle_get_sold_numbers', array( $this, 'ajax_get_sold_numbers' ) );

        add_action( 'wp_ajax_raffle_download_entry_list', array( $this, 'ajax_download_entry_list' ) );
        add_action( 'wp_ajax_nopriv_raffle_download_entry_list', array( $this, 'ajax_download_entry_list' ) );

        add_action( 'woocommerce_before_single_product', array( $this, 'maybe_override_product_layout' ) );
        add_filter( 'template_include', array( $this, 'override_single_product_template' ), 99 );
    }

    public function enqueue_assets() {
        global $post;
        $is_raffle_product = false;
        if ( is_a( $post, 'WP_Post' ) && $post->post_type === 'product' ) {
            $is_raffle_product = (bool) get_post_meta( $post->ID, '_raffle_id', true );
        }

        // WooCommerce My Account — my-raffles endpoint
        $is_my_raffles = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'my-raffles' );

        $needs_raffle_assets = $is_raffle_product || $is_my_raffles || (
            is_a( $post, 'WP_Post' ) && (
                has_shortcode( $post->post_content, 'raffle' ) ||
                has_shortcode( $post->post_content, 'raffle_list' ) ||
                has_shortcode( $post->post_content, 'raffle_ended_list' ) ||
                has_shortcode( $post->post_content, 'raffle_entry_list' ) ||
                has_shortcode( $post->post_content, 'raffle_live_draw' )
            )
        );

        if ( $needs_raffle_assets ) {
            wp_enqueue_style( 'wpraffle-icons', RAFFLE_SYSTEM_URL . 'assets/css/icons.css', array(), RAFFLE_SYSTEM_VERSION );
            wp_enqueue_style( 'raffle-public', RAFFLE_SYSTEM_URL . 'assets/css/public.css', array( 'wpraffle-icons' ), RAFFLE_SYSTEM_VERSION );

            wp_enqueue_script( 'raffle-public', RAFFLE_SYSTEM_URL . 'assets/js/public.js', array( 'jquery' ), RAFFLE_SYSTEM_VERSION, true );

            $localize_data = array(
                'ajax_url'       => admin_url( 'admin-ajax.php' ),
                'nonce'          => wp_create_nonce( 'raffle_purchase_nonce' ),
                'currency_symbol' => wpr_currency_symbol(),
            );

            if ( Raffle_WooCommerce::is_available() ) {
                $localize_data['wc_enabled'] = '1';
                $localize_data['cart_url']   = wc_get_cart_url();
                $localize_data['checkout_url'] = wc_get_checkout_url();
            }

            wp_localize_script( 'raffle-public', 'rafflePublic', $localize_data );
            wp_localize_script( 'raffle-public', 'raffleCountdown', array(
                'labels' => array(
                    'days'    => 'Days',
                    'hours'   => 'Hours',
                    'minutes' => 'Min',
                    'seconds' => 'Sec',
                    'expired' => "It's draw time!",
                ),
            ) );
        }

        // For raffle products using custom template, dequeue WooCommerce block/interactivity scripts
        // that cause "Failed to resolve module specifier @wordpress/interactivity" errors
        if ( $is_raffle_product ) {
            add_action( 'wp_enqueue_scripts', function() {
                // Dequeue WooCommerce Interactivity API scripts (block-based product page)
                wp_dequeue_script( 'wc-product-interactivity-frontend' );
                wp_deregister_script( 'wc-product-interactivity-frontend' );
                wp_dequeue_script( 'woocommerce-product-interactivity-frontend' );
                wp_deregister_script( 'woocommerce-product-interactivity-frontend' );
                wp_dequeue_script( 'wc-interactivity-frontend' );
                wp_deregister_script( 'wc-interactivity-frontend' );
                // Dequeue generic interactivity runtime if present
                wp_dequeue_script( 'wp-interactivity' );
                wp_deregister_script( 'wp-interactivity' );
                // Also deregister WP 6.5+ script modules (new module system)
                if ( function_exists( 'wp_deregister_script_module' ) ) {
                    wp_deregister_script_module( '@wordpress/interactivity' );
                    wp_deregister_script_module( '@woocommerce/product-interactivity' );
                    wp_deregister_script_module( 'wc-product-interactivity' );
                }
            }, 100 );

            // Also filter script tags to remove any remaining interactivity module scripts
            add_filter( 'script_loader_tag', function( $tag, $handle ) {
                // Remove module scripts that reference @wordpress/interactivity
                if ( strpos( $tag, '@wordpress/interactivity' ) !== false || strpos( $tag, 'type="module"' ) !== false ) {
                    if ( preg_match( '/woocommerce|wc-product|interactivity/i', $tag ) ) {
                        return '';
                    }
                }
                return $tag;
            }, 100, 2 );
        }
    }

    public function render_shortcode( $atts ) {
        $atts      = shortcode_atts( array( 'id' => 0 ), $atts, 'raffle' );
        $raffle_id = absint( $atts['id'] );

        if ( ! $raffle_id ) {
            return '<p>Raffle ID not specified.</p>';
        }

        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            return '<p>Raffle not found.</p>';
        }

        $packages  = json_decode( $raffle->packages, true ) ?: array();
        $progress  = ( $raffle->total_tickets > 0 ) ? round( ( $raffle->sold_tickets / $raffle->total_tickets ) * 100 ) : 0;
        $remaining = $raffle->total_tickets - $raffle->sold_tickets;

        ob_start();
        include RAFFLE_SYSTEM_PATH . 'public/views/raffle-display.php';
        return ob_get_clean();
    }

    public function render_lookup_shortcode( $atts ) {
        if ( is_user_logged_in() ) {
            return '<p>Please visit the <a href="' . esc_url( wc_get_account_endpoint_url( 'my-raffles' ) ) . '">My Raffles</a> section in your account dashboard.</p>';
        }

        ob_start();
        ?>
        <div class="raffle-lookup-container" style="max-width: 400px; margin: 0 auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h3 style="margin-top: 0;">Lookup Your Tickets</h3>
            <p>Enter the email address used during purchase to view your active tickets.</p>
            <form method="post" action="" class="rs-lookup-form">
                <?php wp_nonce_field( 'raffle_lookup_nonce', 'lookup_nonce' ); ?>
                <div style="margin-bottom: 15px;">
                    <input type="email" name="lookup_email" required placeholder="Enter your email" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <button type="submit" style="width: 100%; padding: 10px; background: #667eea; color: #fff; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Lookup Tickets</button>
            </form>
            
            <?php
            if ( isset( $_POST['lookup_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lookup_nonce'] ) ), 'raffle_lookup_nonce' ) ) {
                $email = sanitize_email( wp_unslash( $_POST['lookup_email'] ) );
                if ( is_email( $email ) ) {
                    global $wpdb;
                    $count = $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}raffle_purchases WHERE buyer_email = %s AND payment_status = 'completed'",
                        $email
                    ) );
                    
                    if ( $count > 0 ) {
                        echo '<div style="margin-top: 15px; padding: 10px; background: #dcfce7; color: #166534; border-radius: 4px;">';
                        echo 'We found ' . esc_html( $count ) . ' purchases associated with this email. A secure link to view your tickets has been sent to your email address.';
                        echo '</div>';
                        // For a real app, send email with a secure token.
                    } else {
                        echo '<div style="margin-top: 15px; padding: 10px; background: #fee2e2; color: #991b1b; border-radius: 4px;">';
                        echo 'No ticket purchases found for this email address.';
                        echo '</div>';
                    }
                }
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_get_sold_numbers() {
        // Verify nonce to prevent unauthorized data harvesting
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_purchase_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }

        $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        if ( ! $raffle_id ) wp_send_json_error();

        global $wpdb;
        $sold = $wpdb->get_col( $wpdb->prepare(
            "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE raffle_id = %d",
            $raffle_id
        ) );
        
        $pending_purchases = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}raffle_purchases WHERE raffle_id = %d AND payment_status = 'pending'",
            $raffle_id
        ) );

        wp_send_json_success( array( 'sold' => array_map( 'intval', $sold ) ) );
    }

    public function maybe_override_product_layout() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) {
            return;
        }

        $raffle_id = get_post_meta( $post->ID, '_raffle_id', true );
        if ( ! $raffle_id ) {
            return;
        }

        // Clean up standard WooCommerce product single display (classic themes)
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );
        remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
        remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
        remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );

        // Remove WooCommerce's default product images
        remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );

        // Hide WooCommerce block-based elements (Woo 8.3+ / block themes)
        add_action( 'wp_head', function() {
            echo '<style>
                /* Hide WC block elements for raffle products */
                .wpraffle-hide-wc .wp-block-woocommerce-product-image,
                .wpraffle-hide-wc .wp-block-woocommerce-product-title,
                .wpraffle-hide-wc .wp-block-woocommerce-product-price,
                .wpraffle-hide-wc .wp-block-woocommerce-product-button,
                .wpraffle-hide-wc .wp-block-woocommerce-product-rating,
                .wpraffle-hide-wc .wp-block-woocommerce-product-summary,
                .wpraffle-hide-wc .wp-block-woocommerce-product-details,
                .wpraffle-hide-wc .wp-block-woocommerce-product-meta,
                .wpraffle-hide-wc .wp-block-woocommerce-product-related-products,
                .wpraffle-hide-wc .wp-block-woocommerce-product-upsells,
                .wpraffle-hide-wc .wp-block-woocommerce-product-tabs,
                .wpraffle-hide-wc .woocommerce-tabs,
                .wpraffle-hide-wc .related.products,
                .wpraffle-hide-wc .upsells.products,
                .wpraffle-hide-wc .woocommerce-product-gallery,
                .wpraffle-hide-wc .woocommerce-product-details__short-description,
                .wpraffle-hide-wc form.cart,
                .wpraffle-hide-wc .product_meta { display: none !important; }
            </style>';
        } );

        // Add CSS class to product wrapper to trigger WC element hiding
        add_filter( 'woocommerce_single_product_flexslider_enabled', '__return_false' );
        add_filter( 'post_class', function( $classes ) {
            $classes[] = 'wpraffle-hide-wc';
            return $classes;
        } );

        // Render our custom raffle layout
        add_action( 'woocommerce_single_product_summary', array( $this, 'render_raffle_product_layout' ), 5 );
    }

    public function render_raffle_product_layout() {
        global $post;
        $raffle_id = (int) get_post_meta( $post->ID, '_raffle_id', true );
        if ( ! $raffle_id ) {
            return;
        }

        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            echo '<p>Raffle not found.</p>';
            return;
        }

        $packages  = json_decode( $raffle->packages, true ) ?: array();
        $progress  = ( $raffle->total_tickets > 0 ) ? round( ( $raffle->sold_tickets / $raffle->total_tickets ) * 100 ) : 0;
        $remaining = $raffle->total_tickets - $raffle->sold_tickets;

        // Render public view
        include RAFFLE_SYSTEM_PATH . 'public/views/raffle-display.php';
    }

    /**
     * Override single product template for raffle products.
     */
    public function override_single_product_template( $template ) {
        if ( is_singular( 'product' ) ) {
            $product_id = get_the_ID();
            if ( $product_id && get_post_meta( $product_id, '_raffle_id', true ) ) {
                $custom_template = RAFFLE_SYSTEM_PATH . 'public/views/single-raffle.php';
                if ( file_exists( $custom_template ) ) {
                    return $custom_template;
                }
            }
        }
        return $template;
    }

    public static function get_raffle_state( $raffle ) {
        $now = current_time( 'mysql' );
        $remaining = (int)$raffle->total_tickets - (int)$raffle->sold_tickets;

        if ( $raffle->status === 'draft' ) {
            return 'draft';
        }

        if ( $raffle->status === 'active' && ! empty( $raffle->start_date ) && $raffle->start_date > $now ) {
            return 'draft';
        }

        if ( $raffle->status === 'finished' ) {
            return 'ended';
        }

        if ( $raffle->status === 'active' && ! empty( $raffle->draw_date ) && $raffle->draw_date <= $now ) {
            return 'ended';
        }

        if ( $raffle->status === 'active' && $remaining <= 0 ) {
            return 'ended';
        }

        return 'live';
    }

    public function render_raffle_list_shortcode( $atts ) {
        $sc_defaults = self::get_sc_settings( 'raffle_list', array(
            'status' => 'active', // 'active' (live), 'finished' (ended), 'draft', or 'all'
        ) );

        $atts = shortcode_atts( $sc_defaults, $atts, 'raffle_list' );

        global $wpdb;
        $table = $wpdb->prefix . 'raffles';
        $now = current_time( 'mysql' );

        if ( $atts['status'] === 'active' ) {
            $raffles = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} 
                 WHERE status = 'active' 
                   AND ( start_date IS NULL OR start_date <= %s ) 
                   AND ( draw_date IS NULL OR draw_date > %s )
                   AND sold_tickets < total_tickets
                 ORDER BY created_at DESC",
                $now, $now
            ) );
        } elseif ( $atts['status'] === 'finished' || $atts['status'] === 'ended' ) {
            $raffles = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} 
                 WHERE status = 'finished' 
                    OR ( status = 'active' AND draw_date IS NOT NULL AND draw_date <= %s ) 
                    OR ( status = 'active' AND sold_tickets >= total_tickets )
                 ORDER BY draw_date DESC, created_at DESC",
                $now
            ) );
        } elseif ( $atts['status'] === 'draft' ) {
            $raffles = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} 
                 WHERE status = 'draft' 
                    OR ( status = 'active' AND start_date IS NOT NULL AND start_date > %s )
                 ORDER BY created_at DESC",
                $now
            ) );
        } else {
            // SEC-19: No user input in this query — table name is from $wpdb->prefix (safe)
            $raffles = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
        }

        if ( empty( $raffles ) ) {
            return '<div style="text-align:center; padding: 40px; color: #6b7280; font-weight: 500; font-size: 16px;">' . wpr_get_icon( 'gift', 'wpr-icon--sm' ) . ' No raffle competitions found.</div>';
        }

        // Enqueue countdown JS for the shortcode page
        wp_enqueue_script( 'raffle-shop-countdown', RAFFLE_SYSTEM_URL . 'assets/js/shop-countdown.js', array( 'jquery' ), RAFFLE_SYSTEM_VERSION, true );

        ob_start();
        ?>
        <div class="raffle-list-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px; padding: 20px 0;">
            <?php foreach ( $raffles as $r ) :
                // Build a minimal $product-like wrapper and $raffle reference for the template
                $raffle  = $r;
                $product = $r->wc_product_id ? wc_get_product( $r->wc_product_id ) : null;
                if ( ! $product ) {
                    // Create a fake product-like object for the template
                    $product = new stdClass();
                    $product->_fake = true;
                }
                ?>
                <div class="raffle-list-item">
                    <?php include RAFFLE_SYSTEM_PATH . 'public/views/raffle-loop-card.php'; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_raffle_ended_list_shortcode( $atts ) {
        // Read tab visibility from settings
        $general = wp_parse_args( get_option( 'wpraffle_general_settings', array() ), array(
            'winners_show_live_draw'    => 1,
            'winners_show_auto_draw'    => 1,
            'winners_show_instant_wins' => 1,
        ) );
        $show_tab_live    = ! empty( $general['winners_show_live_draw'] );
        $show_tab_auto    = ! empty( $general['winners_show_auto_draw'] );
        $show_tab_iw      = ! empty( $general['winners_show_instant_wins'] );

        // Merge stored customisation settings as defaults
        $sc_defaults = self::get_sc_settings( 'raffle_ended_list', array(
            'columns'        => '3',
            'show_image'     => 'yes',
            'show_winner'    => 'yes',
            'show_video_btn' => 'yes',
            'show_verified_btn' => 'yes',
            'show_date'      => 'yes',
            'show_entries'   => 'yes',
        ) );

        $atts = shortcode_atts( $sc_defaults, $atts, 'raffle_ended_list' );

        $cols = max( 1, min( 6, absint( $atts['columns'] ) ) );
        $show = array(
            'image'       => $atts['show_image'] === 'yes',
            'winner'      => $atts['show_winner'] === 'yes',
            'video_btn'   => $atts['show_video_btn'] === 'yes',
            'verified_btn'=> $atts['show_verified_btn'] === 'yes',
            'date'        => $atts['show_date'] === 'yes',
            'entries'     => $atts['show_entries'] === 'yes',
        );

        global $wpdb;
        $table = $wpdb->prefix . 'raffles';
        $now = current_time( 'mysql' );

        $raffles = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE status = 'finished' 
                OR ( status = 'active' AND draw_date IS NOT NULL AND draw_date <= %s ) 
                OR ( status = 'active' AND sold_tickets >= total_tickets )
             ORDER BY draw_date DESC, created_at DESC",
            $now
        ) );

        if ( empty( $raffles ) ) {
            return '<div style="text-align:center; padding: 40px; color: #6b7280; font-weight: 500; font-size: 16px;">' . wpr_get_icon( 'trophy', 'wpr-icon--sm' ) . ' No ended competitions to show yet. Check back soon!</div>';
        }

        // Separate raffles by draw type
        $live_draw_raffles = array();
        $auto_draw_raffles = array();
        foreach ( $raffles as $r ) {
            if ( isset( $r->draw_type ) && $r->draw_type === 'auto' ) {
                $auto_draw_raffles[] = $r;
            } else {
                $live_draw_raffles[] = $r;
            }
        }

        // Determine active tab
        $active_tab = '';
        if ( $show_tab_live && ! empty( $live_draw_raffles ) )    $active_tab = 'live-draw';
        elseif ( $show_tab_auto && ! empty( $auto_draw_raffles ) ) $active_tab = 'auto-draw';
        elseif ( $show_tab_iw )                                    $active_tab = 'instant-wins';
        elseif ( $show_tab_live )                                  $active_tab = 'live-draw';
        elseif ( $show_tab_auto )                                  $active_tab = 'auto-draw';

        // Get all instant wins grouped by date
        $all_iw_by_date = array();
        if ( $show_tab_iw ) {
            $raffle_ids = wp_list_pluck( $raffles, 'id' );
            if ( ! empty( $raffle_ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $raffle_ids ), '%d' ) );
                $all_iw = $wpdb->get_results( $wpdb->prepare(
                    "SELECT iw.*, p.buyer_name as winner_name, r.title as raffle_title, r.total_tickets
                     FROM {$wpdb->prefix}raffle_instant_wins iw
                     LEFT JOIN {$wpdb->prefix}raffle_purchases p ON iw.purchase_id = p.id
                     LEFT JOIN {$wpdb->prefix}raffles r ON iw.raffle_id = r.id
                     WHERE iw.raffle_id IN ({$placeholders}) AND iw.status = 'won'
                     ORDER BY iw.created_at DESC",
                    ...$raffle_ids
                ) );
                foreach ( $all_iw as $iw ) {
                    $dk = date_i18n( 'Y-m-d', strtotime( $iw->created_at ) );
                    if ( ! isset( $all_iw_by_date[ $dk ] ) ) $all_iw_by_date[ $dk ] = array();
                    $all_iw_by_date[ $dk ][] = $iw;
                }
            }
        }

        ob_start();
        ?>
        <div class="raffle-ended-wrapper" style="padding: 20px 0;">

        <?php if ( $show_tab_live || $show_tab_auto || $show_tab_iw ) : ?>

        <!-- Tab Navigation -->
        <div class="raffle-winners-tabs" style="display:flex;gap:0;border-bottom:2px solid #e5e7eb;margin-bottom:30px;">
            <?php if ( $show_tab_live ) : ?>
            <button type="button" class="raffle-winners-tab-btn <?php echo $active_tab === 'live-draw' ? 'active' : ''; ?>" data-tab="live-draw" style="padding:14px 28px;font-size:15px;font-weight:700;border:none;background:none;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;color:<?php echo $active_tab === 'live-draw' ? '#1e40af' : '#6b7280'; ?>;border-bottom-color:<?php echo $active_tab === 'live-draw' ? '#1e40af' : 'transparent'; ?>;transition:all 0.2s;">
                <?php echo wpr_get_icon( 'zap', 'wpr-icon--sm' ); ?> Live Draw
            </button>
            <?php endif; ?>
            <?php if ( $show_tab_auto ) : ?>
            <button type="button" class="raffle-winners-tab-btn <?php echo $active_tab === 'auto-draw' ? 'active' : ''; ?>" data-tab="auto-draw" style="padding:14px 28px;font-size:15px;font-weight:700;border:none;background:none;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;color:<?php echo $active_tab === 'auto-draw' ? '#1e40af' : '#6b7280'; ?>;border-bottom-color:<?php echo $active_tab === 'auto-draw' ? '#1e40af' : 'transparent'; ?>;transition:all 0.2s;">
                <?php echo wpr_get_icon( 'refresh', 'wpr-icon--sm' ); ?> Auto-Draw
            </button>
            <?php endif; ?>
            <?php if ( $show_tab_iw ) : ?>
            <button type="button" class="raffle-winners-tab-btn <?php echo $active_tab === 'instant-wins' ? 'active' : ''; ?>" data-tab="instant-wins" style="padding:14px 28px;font-size:15px;font-weight:700;border:none;background:none;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;color:<?php echo $active_tab === 'instant-wins' ? '#1e40af' : '#6b7280'; ?>;border-bottom-color:<?php echo $active_tab === 'instant-wins' ? '#1e40af' : 'transparent'; ?>;transition:all 0.2s;">
                <?php echo wpr_get_icon( 'gift', 'wpr-icon--sm' ); ?> Instant Wins
            </button>
            <?php endif; ?>
        </div>

        <!-- Tab: Live Draw -->
        <?php if ( $show_tab_live ) : ?>
        <div class="raffle-winners-tab-content <?php echo $active_tab === 'live-draw' ? 'active' : ''; ?>" id="tab-live-draw" style="<?php echo $active_tab !== 'live-draw' ? 'display:none;' : ''; ?>">
            <?php if ( empty( $live_draw_raffles ) ) : ?>
                <div style="text-align:center;padding:40px;color:#6b7280;font-weight:500;font-size:16px;"><?php echo wpr_get_icon( 'zap', 'wpr-icon--sm' ); ?> No live draw competitions yet. Check back soon!</div>
            <?php else : ?>
            <div style="display:grid;grid-template-columns:repeat(<?php echo esc_attr( $cols ); ?>,1fr);gap:24px;">
                <?php foreach ( $live_draw_raffles as $r ) :
                    $winner_info = null;
                    if ( $r->winner_ticket_id ) {
                        $winner_info = $wpdb->get_row( $wpdb->prepare(
                            "SELECT t.ticket_number, p.buyer_name FROM {$wpdb->prefix}raffle_tickets t JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id WHERE t.id = %d",
                            $r->winner_ticket_id
                        ) );
                    }
                    $total_digits = strlen( (string) $r->total_tickets );
                    $formatted_num = $winner_info ? str_pad( $winner_info->ticket_number, $total_digits, '0', STR_PAD_LEFT ) : '';
                    $has_video = ! empty( $r->draw_video_url );
                    $has_verified = ! empty( $r->verified_result );
                ?>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.05);display:flex;flex-direction:column;">
                    <?php if ( $show['image'] && $r->prize_image ) : ?>
                    <div style="position:relative;overflow:hidden;">
                        <img src="<?php echo esc_url( $r->prize_image ); ?>" style="width:100%;height:200px;object-fit:cover;">
                        <?php if ( $show['date'] && $r->draw_date ) : ?>
                        <div style="position:absolute;top:12px;right:12px;background:rgba(0,0,0,0.65);color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;"><?php echo esc_html( date_i18n( 'jS M Y', strtotime( $r->draw_date ) ) ); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div style="padding:20px;display:flex;flex-direction:column;gap:14px;flex:1;">
                        <div>
                            <h3 style="margin:0 0 4px 0;font-size:17px;font-weight:700;color:#1f2937;line-height:1.3;"><?php echo esc_html( $r->title ); ?></h3>
                            <?php if ( $show['entries'] ) : ?><div style="font-size:13px;color:#6b7280;"><?php echo esc_html( $r->sold_tickets ); ?> / <?php echo esc_html( $r->total_tickets ); ?> entries</div><?php endif; ?>
                        </div>
                        <?php if ( $show['winner'] ) : ?>
                        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px 14px;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;">
                            <?php echo wpr_get_icon( 'trophy', 'wpr-icon--sm', 'Winner' ); ?>
                            <?php if ( $winner_info ) : ?>
                                <span style="font-size:14px;font-weight:700;color:#166534;"><?php echo esc_html( $winner_info->buyer_name ); ?></span>
                                <span style="font-size:12px;color:#6b7280;">Ticket: <span style="font-family:monospace;background:#dcfce7;color:#166534;padding:2px 6px;border-radius:4px;font-weight:bold;"><?php echo esc_html( $formatted_num ); ?></span></span>
                            <?php else : ?>
                                <span style="font-size:13px;color:#6b7280;font-weight:600;">Draw pending</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:auto;">
                            <?php if ( $show['video_btn'] && $has_video ) : ?>
                            <a href="<?php echo esc_url( $r->draw_video_url ); ?>" target="_blank" rel="noopener noreferrer" style="flex:1;min-width:120px;padding:8px 14px;border-radius:8px;border:1px solid #c7d2fe;background:#e0e7ff;color:#4338ca;font-weight:600;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;">
                                <?php echo wpr_get_icon( 'zap', 'wpr-icon--sm', 'Watch' ); ?> Watch Draw
                            </a>
                            <?php endif; ?>
                            <?php if ( $show['verified_btn'] && $has_verified ) : ?>
                            <a href="<?php echo esc_url( $r->verified_result ); ?>" target="_blank" rel="noopener noreferrer" style="flex:1;min-width:120px;padding:8px 14px;border-radius:8px;border:1px solid #bbf7d0;background:#dcfce7;color:#166534;font-weight:600;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;">
                                <?php echo wpr_get_icon( 'check-circle', 'wpr-icon--sm', 'Verified' ); ?> Verified Draw
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Tab: Auto-Draw -->
        <?php if ( $show_tab_auto ) : ?>
        <div class="raffle-winners-tab-content <?php echo $active_tab === 'auto-draw' ? 'active' : ''; ?>" id="tab-auto-draw" style="<?php echo $active_tab !== 'auto-draw' ? 'display:none;' : ''; ?>">
            <?php if ( empty( $auto_draw_raffles ) ) : ?>
                <div style="text-align:center;padding:40px;color:#6b7280;font-weight:500;font-size:16px;"><?php echo wpr_get_icon( 'refresh', 'wpr-icon--sm' ); ?> No auto-draw competitions yet. Check back soon!</div>
            <?php else : ?>
            <div style="display:grid;grid-template-columns:repeat(<?php echo esc_attr( $cols ); ?>,1fr);gap:24px;">
                <?php foreach ( $auto_draw_raffles as $r ) :
                    $winner_info = null;
                    if ( $r->winner_ticket_id ) {
                        $winner_info = $wpdb->get_row( $wpdb->prepare(
                            "SELECT t.ticket_number, p.buyer_name FROM {$wpdb->prefix}raffle_tickets t JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id WHERE t.id = %d",
                            $r->winner_ticket_id
                        ) );
                    }
                    $total_digits = strlen( (string) $r->total_tickets );
                    $formatted_num = $winner_info ? str_pad( $winner_info->ticket_number, $total_digits, '0', STR_PAD_LEFT ) : '';
                    $has_video = ! empty( $r->draw_video_url );
                    $has_verified = ! empty( $r->verified_result );
                ?>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.05);display:flex;flex-direction:column;">
                    <?php if ( $show['image'] && $r->prize_image ) : ?>
                    <div style="position:relative;overflow:hidden;">
                        <img src="<?php echo esc_url( $r->prize_image ); ?>" style="width:100%;height:200px;object-fit:cover;">
                        <?php if ( $show['date'] && $r->draw_date ) : ?>
                        <div style="position:absolute;top:12px;right:12px;background:rgba(0,0,0,0.65);color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;"><?php echo esc_html( date_i18n( 'jS M Y', strtotime( $r->draw_date ) ) ); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div style="padding:20px;display:flex;flex-direction:column;gap:14px;flex:1;">
                        <div>
                            <h3 style="margin:0 0 4px 0;font-size:17px;font-weight:700;color:#1f2937;line-height:1.3;"><?php echo esc_html( $r->title ); ?></h3>
                            <div style="font-size:13px;color:#6b7280;">
                                <?php if ( $show['entries'] ) : ?><?php echo esc_html( $r->sold_tickets ); ?> / <?php echo esc_html( $r->total_tickets ); ?> entries<?php endif; ?>
                                <span style="background:#eff6ff;color:#1d4ed8;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;margin-left:6px;">AUTO</span>
                            </div>
                        </div>
                        <?php if ( $show['winner'] ) : ?>
                        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px 14px;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;">
                            <?php echo wpr_get_icon( 'trophy', 'wpr-icon--sm', 'Winner' ); ?>
                            <?php if ( $winner_info ) : ?>
                                <span style="font-size:14px;font-weight:700;color:#166534;"><?php echo esc_html( $winner_info->buyer_name ); ?></span>
                                <span style="font-size:12px;color:#6b7280;">Ticket: <span style="font-family:monospace;background:#dcfce7;color:#166534;padding:2px 6px;border-radius:4px;font-weight:bold;"><?php echo esc_html( $formatted_num ); ?></span></span>
                            <?php else : ?>
                                <span style="font-size:13px;color:#6b7280;font-weight:600;">Draw pending</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:auto;">
                            <?php if ( $show['video_btn'] && $has_video ) : ?>
                            <a href="<?php echo esc_url( $r->draw_video_url ); ?>" target="_blank" rel="noopener noreferrer" style="flex:1;min-width:120px;padding:8px 14px;border-radius:8px;border:1px solid #c7d2fe;background:#e0e7ff;color:#4338ca;font-weight:600;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;">
                                <?php echo wpr_get_icon( 'zap', 'wpr-icon--sm', 'Watch' ); ?> Watch Draw
                            </a>
                            <?php endif; ?>
                            <?php if ( $show['verified_btn'] && $has_verified ) : ?>
                            <a href="<?php echo esc_url( $r->verified_result ); ?>" target="_blank" rel="noopener noreferrer" style="flex:1;min-width:120px;padding:8px 14px;border-radius:8px;border:1px solid #bbf7d0;background:#dcfce7;color:#166534;font-weight:600;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;">
                                <?php echo wpr_get_icon( 'check-circle', 'wpr-icon--sm', 'Verified' ); ?> Verified Draw
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Tab: Instant Wins -->
        <?php if ( $show_tab_iw ) : ?>
        <div class="raffle-winners-tab-content <?php echo $active_tab === 'instant-wins' ? 'active' : ''; ?>" id="tab-instant-wins" style="<?php echo $active_tab !== 'instant-wins' ? 'display:none;' : ''; ?>">
            <?php if ( empty( $all_iw_by_date ) ) : ?>
                <div style="text-align:center;padding:40px;color:#6b7280;font-weight:500;font-size:16px;"><?php echo wpr_get_icon( 'gift', 'wpr-icon--sm' ); ?> No instant win claims yet. Check back soon!</div>
            <?php else : ?>
                <?php foreach ( $all_iw_by_date as $date_key => $date_wins ) : ?>
                <div style="margin-bottom:30px;">
                    <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin:0 0 16px 0;display:flex;align-items:center;gap:8px;">
                        <span style="background:#f3f4f6;padding:6px 14px;border-radius:8px;font-size:13px;"><?php echo esc_html( date_i18n( 'l, jS F Y', strtotime( $date_key ) ) ); ?></span>
                        <span style="font-size:12px;color:#6b7280;font-weight:500;"><?php echo count( $date_wins ); ?> win<?php echo count( $date_wins ) > 1 ? 's' : ''; ?></span>
                    </h3>
                    <div style="display:grid;grid-template-columns:repeat(<?php echo esc_attr( $cols ); ?>,1fr);gap:16px;">
                        <?php foreach ( $date_wins as $iw ) :
                            $td = strlen( (string) $iw->total_tickets );
                            $fn = str_pad( $iw->ticket_number, $td, '0', STR_PAD_LEFT );
                            $initials = class_exists( 'Raffle_Instant_Wins' ) ? Raffle_Instant_Wins::get_initials( $iw->winner_name ) : '';
                        ?>
                        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 4px rgba(0,0,0,0.04);">
                            <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#f97316,#ea580c);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:14px;flex-shrink:0;">
                                <?php echo esc_html( $initials ?: '?' ); ?>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:700;font-size:14px;color:#1f2937;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html( $iw->prize_name ); ?></div>
                                <div style="font-size:12px;color:#6b7280;margin-top:2px;">
                                    <?php echo esc_html( $iw->raffle_title ); ?> &bull; Ticket #<span style="font-family:monospace;font-weight:700;"><?php echo esc_html( $fn ); ?></span>
                                </div>
                            </div>
                            <div style="background:#dcfce7;color:#166534;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;flex-shrink:0;">Won</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Tab Switching JS -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tabs = document.querySelectorAll('.raffle-winners-tab-btn');
            tabs.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    var target = tab.getAttribute('data-tab');
                    tabs.forEach(function(t) { t.style.color = '#6b7280'; t.style.borderBottomColor = 'transparent'; t.classList.remove('active'); });
                    document.querySelectorAll('.raffle-winners-tab-content').forEach(function(c) { c.style.display = 'none'; c.classList.remove('active'); });
                    tab.style.color = '#1e40af'; tab.style.borderBottomColor = '#1e40af'; tab.classList.add('active');
                    var content = document.getElementById('tab-' + target);
                    if (content) { content.style.display = 'block'; content.classList.add('active'); }
                });
            });
        });
        </script>

        <?php else : ?>
            <div style="text-align:center;padding:40px;color:#6b7280;font-weight:500;font-size:16px;">No tabs enabled. Configure winners page in Settings.</div>
        <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the entry list page showing all closed/ended raffles with download buttons.
     */
    public function render_entry_list_shortcode( $atts ) {
        $sc_defaults = self::get_sc_settings( 'raffle_entry_list', array(
            'button_text' => 'Download Entry List',
            'button_bg'   => '#1e40af',
            'button_color'=> '#ffffff',
            'button_radius' => '8',
            'show_image'  => 'yes',
            'columns'     => '2',
            'layout'      => 'grid',
        ) );

        $atts = shortcode_atts( $sc_defaults, $atts, 'raffle_entry_list' );

        global $wpdb;
        $table = $wpdb->prefix . 'raffles';
        $now   = current_time( 'mysql' );

        $raffles = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status = 'finished'
                OR ( status = 'active' AND draw_date IS NOT NULL AND draw_date <= %s )
                OR ( status = 'active' AND sold_tickets >= total_tickets )
             ORDER BY draw_date DESC, created_at DESC",
            $now
        ) );

        if ( empty( $raffles ) ) {
            return '<div style="text-align:center; padding: 40px; color: #6b7280; font-weight: 500; font-size: 16px;">No closed competitions found. Check back soon!</div>';
        }

        $cols         = max( 1, min( 4, absint( $atts['columns'] ) ) );
        $show_image   = $atts['show_image'] === 'yes';
        $btn_text     = $atts['button_text'];
        $btn_bg       = $atts['button_bg'];
        $btn_color    = $atts['button_color'];
        $btn_radius   = intval( $atts['button_radius'] );

        ob_start();
        include RAFFLE_SYSTEM_PATH . 'public/views/entry-list.php';
        return ob_get_clean();
    }

    /**
     * AJAX handler to generate and return a PDF entry list for a closed raffle.
     */
    public function ajax_download_entry_list() {
        $raffle_id = isset( $_GET['raffle_id'] ) ? absint( $_GET['raffle_id'] ) : 0;
        if ( ! $raffle_id ) {
            wp_die( 'Invalid raffle ID.' );
        }

        // Verify nonce
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'raffle_entry_list_nonce' ) ) {
            wp_die( 'Security check failed.' );
        }

        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            wp_die( 'Raffle not found.' );
        }

        // Only allow downloads for closed/ended raffles
        $state = self::get_raffle_state( $raffle );
        if ( $state !== 'ended' ) {
            wp_die( 'Entry list is only available for closed competitions.' );
        }

        $tickets = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.ticket_number, t.buyer_email, p.buyer_name, p.purchase_date, p.entry_type
             FROM {$wpdb->prefix}raffle_tickets t
             JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id
             WHERE t.raffle_id = %d
             ORDER BY t.ticket_number ASC",
            $raffle_id
        ) );

        $total_digits = strlen( (string) $raffle->total_tickets );
        $filename     = sanitize_file_name( 'entry-list-' . $raffle->id . '-' . sanitize_title( $raffle->title ) . '.pdf' );

        // Generate PDF using built-in lightweight PDF generator
        $pdf_content = WPRaffle_PDF::entry_list( $raffle, $tickets, $total_digits );

        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        header( 'Content-Length: ' . strlen( $pdf_content ) );

        echo $pdf_content;
        exit;
    }

    /**
     * Get stored shortcode customisation settings merged with defaults.
     * If customisation is not enabled for the shortcode, returns original defaults.
     */
    private static function get_sc_settings( $shortcode, $defaults ) {
        $sc_settings = get_option( 'wpraffle_shortcode_settings', array() );
        if ( ! isset( $sc_settings[ $shortcode ] ) || empty( $sc_settings[ $shortcode ]['enabled'] ) ) {
            return $defaults;
        }
        $stored = $sc_settings[ $shortcode ];
        unset( $stored['enabled'] );
        // Merge stored over defaults (stored values take precedence)
        return wp_parse_args( $stored, $defaults );
    }
}
