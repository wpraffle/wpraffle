<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Activator {

    public static function activate() {
        self::create_tables();
        self::create_shadow_product();
        self::create_default_pages();
        update_option( 'raffle_system_version', RAFFLE_SYSTEM_VERSION );

        // Ensure WooCommerce rewrite endpoint is registered and rewrite rules are flushed
        add_rewrite_endpoint( 'my-raffles', EP_ROOT | EP_PAGES );
        flush_rewrite_rules();

        if ( ! wp_next_scheduled( 'raffle_system_auto_draw_cron' ) ) {
            wp_schedule_event( time(), 'hourly', 'raffle_system_auto_draw_cron' );
        }
    }

    /**
     * Auto-create required pages with shortcodes on first activation.
     */
    private static function create_default_pages() {
        $pages = get_option( 'wpraffle_pages', array() );

        $configs = array(
            'raffles'     => array( 'title' => 'Raffles',      'content' => '[raffle_list]' ),
            'ended'       => array( 'title' => 'Past Raffles', 'content' => '[raffle_ended_list]' ),
            'entry_list'  => array( 'title' => 'Entry Lists',  'content' => '[raffle_entry_list]' ),
            'live_draw'   => array( 'title' => 'Live Draw',    'content' => '[raffle_live_draw]' ),
            'my_raffles'  => array( 'title' => 'My Raffles',   'content' => '' ),
        );

        $created = array();
        foreach ( $configs as $key => $cfg ) {
            // Skip if page already exists
            if ( ! empty( $pages[ $key ] ) && get_post( $pages[ $key ] ) ) {
                continue;
            }

            $page_id = wp_insert_post( array(
                'post_title'   => $cfg['title'],
                'post_content' => $cfg['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => get_current_user_id() ?: 1,
            ) );

            if ( ! is_wp_error( $page_id ) ) {
                $pages[ $key ] = $page_id;
                $created[]     = $cfg['title'];
            }
        }

        if ( ! empty( $created ) ) {
            update_option( 'wpraffle_pages', $pages );
            set_transient( 'wpraffle_pages_created', $created, 60 );
        }
    }

    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_raffles      = $wpdb->prefix . 'raffles';
        $table_purchases    = $wpdb->prefix . 'raffle_purchases';
        $table_tickets      = $wpdb->prefix . 'raffle_tickets';
        $table_instant      = $wpdb->prefix . 'raffle_instant_wins';
        $table_prizes       = $wpdb->prefix . 'raffle_prizes';
        $table_referrals    = $wpdb->prefix . 'raffle_referrals';
        $table_reservations = $wpdb->prefix . 'raffle_reservations';
        $table_audit        = $wpdb->prefix . 'raffle_audit_log';
        $table_templates    = $wpdb->prefix . 'raffle_templates';
        $table_free_entries = $wpdb->prefix . 'raffle_free_entries';
        $table_charities    = $wpdb->prefix . 'raffle_charities';
        $table_charity_alloc = $wpdb->prefix . 'raffle_charity_allocations';
        $table_credits      = $wpdb->prefix . 'raffle_credits';
        $table_payouts      = $wpdb->prefix . 'raffle_payouts';
        $table_rg           = $wpdb->prefix . 'raffle_rg_settings';

        $sql = "CREATE TABLE {$table_raffles} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            prize_value decimal(10,2) NOT NULL DEFAULT 0,
            prize_image varchar(500) DEFAULT '',
            total_tickets int(11) NOT NULL DEFAULT 0,
            sold_tickets int(11) NOT NULL DEFAULT 0,
            ticket_price decimal(10,2) NOT NULL DEFAULT 0,
            packages text,
            start_date datetime DEFAULT NULL,
            draw_date datetime DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            winner_ticket_id bigint(20) UNSIGNED DEFAULT NULL,
            wc_product_id bigint(20) UNSIGNED DEFAULT NULL,
            enable_cash_alternative tinyint(1) NOT NULL DEFAULT 0,
            cash_alternative_amount decimal(10,2) NOT NULL DEFAULT 0,
            ticket_selection varchar(20) NOT NULL DEFAULT 'random',
            draw_type varchar(20) NOT NULL DEFAULT 'manual',
            live_draw_url varchar(500) DEFAULT '',
            jackpot_type varchar(20) NOT NULL DEFAULT 'fixed',
            jackpot_percent int(11) NOT NULL DEFAULT 50,
            discount_rules text,
            enable_question tinyint(1) NOT NULL DEFAULT 0,
            question_text text,
            question_answers text,
            correct_answer_index int(11) NOT NULL DEFAULT 0,
            postal_instructions text,
            max_tickets_per_user int(11) NOT NULL DEFAULT 100,
            reminder_sent tinyint(1) NOT NULL DEFAULT 0,
            multi_winner tinyint(1) NOT NULL DEFAULT 0,
            number_of_winners int(11) NOT NULL DEFAULT 1,
            allow_free_entry tinyint(1) NOT NULL DEFAULT 0,
            free_entry_question text,
            free_entry_answers text,
            free_entry_correct_index int(11) NOT NULL DEFAULT 0,
            geo_restricted tinyint(1) NOT NULL DEFAULT 0,
            geo_allowed_countries text,
            allow_referrals tinyint(1) NOT NULL DEFAULT 0,
            referral_bonus_entries int(11) NOT NULL DEFAULT 1,
            template_id bigint(20) UNSIGNED DEFAULT NULL,
            draw_video_url varchar(500) DEFAULT '',
            verified_result text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};

        CREATE TABLE {$table_purchases} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            raffle_id bigint(20) UNSIGNED NOT NULL,
            buyer_name varchar(255) NOT NULL,
            buyer_email varchar(255) NOT NULL,
            quantity int(11) NOT NULL,
            total_amount decimal(10,2) NOT NULL DEFAULT 0,
            payment_status varchar(20) NOT NULL DEFAULT 'pending',
            wc_order_id bigint(20) UNSIGNED DEFAULT NULL,
            purchase_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            referral_code varchar(50) DEFAULT NULL,
            entry_type varchar(20) NOT NULL DEFAULT 'paid',
            PRIMARY KEY (id),
            KEY raffle_id (raffle_id),
            KEY wc_order_id (wc_order_id),
            KEY referral_code (referral_code)
        ) {$charset_collate};

        CREATE TABLE {$table_tickets} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            raffle_id bigint(20) UNSIGNED NOT NULL,
            purchase_id bigint(20) UNSIGNED NOT NULL,
            ticket_number int(11) NOT NULL,
            buyer_email varchar(255) NOT NULL,
            is_reserved tinyint(1) NOT NULL DEFAULT 0,
            reserved_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_ticket (raffle_id, ticket_number),
            KEY raffle_id (raffle_id),
            KEY purchase_id (purchase_id)
        ) {$charset_collate};

        CREATE TABLE {$table_instant} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            raffle_id bigint(20) UNSIGNED NOT NULL,
            ticket_number int(11) NOT NULL,
            prize_name varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'available',
            winner_email varchar(255) DEFAULT NULL,
            purchase_id bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY raffle_id (raffle_id),
            UNIQUE KEY unique_instant_ticket (raffle_id, ticket_number)
        ) {$charset_collate};

        CREATE TABLE {$table_prizes} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            raffle_id bigint(20) UNSIGNED NOT NULL,
            position int(11) NOT NULL DEFAULT 0,
            prize_name varchar(255) NOT NULL,
            prize_value decimal(10,2) NOT NULL DEFAULT 0,
            prize_image varchar(500) DEFAULT '',
            winner_ticket_id bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY raffle_id (raffle_id)
        ) {$charset_collate};

        CREATE TABLE {$table_referrals} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            raffle_id bigint(20) UNSIGNED NOT NULL,
            user_email varchar(255) NOT NULL,
            referral_code varchar(50) NOT NULL,
            referred_email varchar(255) DEFAULT NULL,
            bonus_entries int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_raffle (raffle_id, user_email),
            UNIQUE KEY unique_referral_code (referral_code),
            KEY raffle_id (raffle_id)
        ) {$charset_collate};

        CREATE TABLE {$table_reservations} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            raffle_id bigint(20) UNSIGNED NOT NULL,
            ticket_numbers text NOT NULL,
            user_email varchar(255) NOT NULL,
            session_id varchar(100) NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY raffle_id (raffle_id),
            KEY session_id (session_id),
            KEY expires_at (expires_at)
        ) {$charset_collate};

        CREATE TABLE {$table_audit} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            raffle_id bigint(20) UNSIGNED NOT NULL,
            action_type varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            details longtext,
            fairness_proof varchar(255) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY raffle_id (raffle_id),
            KEY action_type (action_type),
            KEY created_at (created_at)
        ) {$charset_collate};

        CREATE TABLE {$table_templates} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            config longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};

        CREATE TABLE {$table_free_entries} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            raffle_id bigint(20) UNSIGNED NOT NULL,
            buyer_name varchar(255) NOT NULL,
            buyer_email varchar(255) NOT NULL,
            answer_index int(11) NOT NULL DEFAULT 0,
            ticket_number int(11) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY raffle_id (raffle_id),
            KEY buyer_email (buyer_email)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function create_shadow_product() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        $existing_id = get_option( 'raffle_system_wc_product_id' );
        if ( $existing_id && get_post( $existing_id ) ) {
            return;
        }

        $post_id = wp_insert_post( array(
            'post_title'   => 'Raffle Entry',
            'post_status'  => 'publish',
            'post_type'    => 'product',
            'post_author'  => get_current_user_id() ?: 1,
            'post_content' => 'System product used for raffle purchases. Do not delete.',
        ) );

        if ( ! is_wp_error( $post_id ) ) {
            wp_set_object_terms( $post_id, 'simple', 'product_type' );
            // SEC-15 FIX: Use product_visibility taxonomy instead of deprecated _visibility meta
            wp_set_object_terms( $post_id, array( 'exclude-from-catalog', 'exclude-from-search' ), 'product_visibility' );
            update_post_meta( $post_id, '_stock_status', 'instock' );
            update_post_meta( $post_id, '_regular_price', '0' );
            update_post_meta( $post_id, '_price', '0' );
            update_post_meta( $post_id, '_virtual', 'yes' );
            update_post_meta( $post_id, '_sold_individually', 'yes' );

            update_option( 'raffle_system_wc_product_id', $post_id );
        }
    }
}
