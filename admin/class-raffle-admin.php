<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Admin {

    public function __construct() {
        $this->run_migrations();
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
        add_action( 'admin_post_wpraffle_save_email_settings', array( $this, 'save_email_settings' ) );
        add_action( 'admin_post_wpraffle_save_email_toggles', array( $this, 'save_email_toggles' ) );
        add_action( 'admin_post_wpraffle_save_admin_recipients', array( $this, 'save_admin_recipients' ) );
        add_action( 'admin_post_wpraffle_send_test_email', array( $this, 'handle_test_email' ) );
        add_action( 'admin_post_wpraffle_save_general_settings', array( $this, 'save_general_settings' ) );
        add_action( 'admin_post_wpraffle_save_legal_settings', array( $this, 'save_legal_settings' ) );
        add_action( 'admin_post_wpraffle_save_advanced_settings', array( $this, 'save_advanced_settings' ) );
        add_action( 'admin_post_wpraffle_save_update_settings', array( $this, 'save_update_settings' ) );
        add_action( 'admin_post_wpraffle_save_styling_settings', array( $this, 'save_styling_settings' ) );
        add_action( 'admin_post_wpraffle_create_page', array( $this, 'handle_create_page' ) );
        add_action( 'admin_post_wpraffle_save_pages', array( $this, 'save_pages' ) );
        add_action( 'admin_post_wpraffle_save_shortcode_settings', array( $this, 'save_shortcode_settings' ) );
        // 1.3.0 — Manual wallet/credit payout re-sync.
        add_action( 'admin_post_wpraffle_sync_wallet_payouts', array( $this, 'handle_sync_wallet_payouts' ) );

        // BUG-2 FIX: Schedule draw reminder cron inside admin_init hook (not constructor)
        add_action( 'admin_init', array( $this, 'schedule_reminder_cron' ) );
        add_action( 'admin_init', array( $this, 'handle_template_delete' ) );
    }

    /**
     * BUG-2 FIX: Schedule cron inside a proper hook.
     */
    public function schedule_reminder_cron() {
        if ( ! wp_next_scheduled( 'raffle_draw_reminder_cron' ) ) {
            wp_schedule_event( time(), 'hourly', 'raffle_draw_reminder_cron' );
        }
    }

    public function run_migrations() {
        global $wpdb;
        $table = $wpdb->prefix . 'raffles';

        // v2 migration
        if ( ! get_option( 'raffle_system_db_migrated_v2' ) ) {
            $col_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'enable_question'" );
            if ( empty( $col_exists ) ) {
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN enable_question tinyint(1) NOT NULL DEFAULT 0" );
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN question_text text" );
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN question_answers text" );
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN correct_answer_index int(11) NOT NULL DEFAULT 0" );
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN postal_instructions text" );
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN max_tickets_per_user int(11) NOT NULL DEFAULT 100" );
            }
            update_option( 'raffle_system_db_migrated_v2', 1 );
        }

        // v3 migration: add reminder_sent column
        // SEC-16 FIX: Add version flag to avoid SHOW COLUMNS on every page load
        if ( ! get_option( 'raffle_system_db_migrated_v3' ) ) {
            $col_v3 = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'reminder_sent'" );
            if ( empty( $col_v3 ) ) {
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN reminder_sent tinyint(1) NOT NULL DEFAULT 0" );
            }
            update_option( 'raffle_system_db_migrated_v3', 1 );
        }

        // v4 migration: new feature columns + tables
        if ( ! get_option( 'raffle_system_db_migrated_v4' ) ) {
            $v4_cols = array(
                'multi_winner'            => "ALTER TABLE {$table} ADD COLUMN multi_winner tinyint(1) NOT NULL DEFAULT 0",
                'number_of_winners'       => "ALTER TABLE {$table} ADD COLUMN number_of_winners int(11) NOT NULL DEFAULT 2",
                'allow_free_entry'        => "ALTER TABLE {$table} ADD COLUMN allow_free_entry tinyint(1) NOT NULL DEFAULT 0",
                'free_entry_question'     => "ALTER TABLE {$table} ADD COLUMN free_entry_question text",
                'free_entry_answers'      => "ALTER TABLE {$table} ADD COLUMN free_entry_answers text",
                'free_entry_correct_index' => "ALTER TABLE {$table} ADD COLUMN free_entry_correct_index int(11) NOT NULL DEFAULT 0",
                'geo_restricted'          => "ALTER TABLE {$table} ADD COLUMN geo_restricted tinyint(1) NOT NULL DEFAULT 0",
                'geo_allowed_countries'   => "ALTER TABLE {$table} ADD COLUMN geo_allowed_countries text",
                'allow_referrals'         => "ALTER TABLE {$table} ADD COLUMN allow_referrals tinyint(1) NOT NULL DEFAULT 0",
                'referral_bonus_entries'  => "ALTER TABLE {$table} ADD COLUMN referral_bonus_entries int(11) NOT NULL DEFAULT 1",
                'template_id'             => "ALTER TABLE {$table} ADD COLUMN template_id int(11) DEFAULT NULL",
            );
            foreach ( $v4_cols as $col => $sql ) {
                $exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $col ) );
                if ( empty( $exists ) ) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL ALTER statement built from hardcoded literals, cannot use placeholders.
                    $wpdb->query( $sql );
                }
            }

            // v4: new tables
            $charset = $wpdb->get_charset_collate();

            $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_prizes (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                raffle_id bigint(20) NOT NULL,
                position int(11) NOT NULL DEFAULT 0,
                prize_name varchar(255) NOT NULL,
                prize_value decimal(10,2) DEFAULT 0,
                winner_ticket_id bigint(20) DEFAULT NULL,
                PRIMARY KEY (id),
                KEY raffle_id (raffle_id)
            ) {$charset}" );

            // BUG-1 FIX: Use user_email instead of user_id to match activator schema and code usage
            $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_referrals (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                raffle_id bigint(20) NOT NULL,
                user_email varchar(255) NOT NULL,
                referral_code varchar(50) NOT NULL,
                referred_email varchar(255) DEFAULT NULL,
                bonus_entries int(11) NOT NULL DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_user_raffle (raffle_id, user_email),
                UNIQUE KEY unique_referral_code (referral_code),
                KEY raffle_id (raffle_id)
            ) {$charset}" );

            $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_reservations (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                raffle_id bigint(20) NOT NULL,
                ticket_numbers text NOT NULL,
                user_email varchar(255) NOT NULL,
                session_id varchar(128) NOT NULL,
                expires_at datetime NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY raffle_id (raffle_id),
                KEY session_id (session_id),
                KEY expires_at (expires_at)
            ) {$charset}" );

            $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_audit_log (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                raffle_id bigint(20) NOT NULL,
                action_type varchar(50) NOT NULL,
                user_id bigint(20) DEFAULT NULL,
                details longtext,
                fairness_proof varchar(128) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY raffle_id (raffle_id)
            ) {$charset}" );

            $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_templates (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                config longtext NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) {$charset}" );

            // BUG-6 FIX: Match schema to actual insert statements (buyer_name, buyer_email, status columns)
            $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_free_entries (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                raffle_id bigint(20) NOT NULL,
                buyer_name varchar(255) NOT NULL,
                buyer_email varchar(255) NOT NULL,
                answer_index int(11) NOT NULL DEFAULT 0,
                ticket_number int(11) NOT NULL DEFAULT 0,
                status varchar(20) NOT NULL DEFAULT 'pending',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY raffle_id (raffle_id),
                KEY buyer_email (buyer_email)
            ) {$charset}" );

            // Add new columns to purchases and tickets tables
            $purchases_table = $wpdb->prefix . 'raffle_purchases';
            $tickets_table = $wpdb->prefix . 'raffle_tickets';

            $purch_cols = array(
                'referral_code' => "ALTER TABLE {$purchases_table} ADD COLUMN referral_code varchar(32) DEFAULT NULL",
                'entry_type'    => "ALTER TABLE {$purchases_table} ADD COLUMN entry_type varchar(20) DEFAULT 'purchase'",
            );
            foreach ( $purch_cols as $col => $sql ) {
                $exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$purchases_table} LIKE %s", $col ) );
                if ( empty( $exists ) ) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL ALTER statement built from hardcoded literals.
                    $wpdb->query( $sql );
                }
            }

            $ticket_cols = array(
                'is_reserved' => "ALTER TABLE {$tickets_table} ADD COLUMN is_reserved tinyint(1) NOT NULL DEFAULT 0",
                'reserved_at' => "ALTER TABLE {$tickets_table} ADD COLUMN reserved_at datetime DEFAULT NULL",
            );
            foreach ( $ticket_cols as $col => $sql ) {
                $exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$tickets_table} LIKE %s", $col ) );
                if ( empty( $exists ) ) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL ALTER statement built from hardcoded literals.
                    $wpdb->query( $sql );
                }
            }

            $reservations_table = $wpdb->prefix . 'raffle_reservations';
            $res_cols = array(
                'user_email' => "ALTER TABLE {$reservations_table} ADD COLUMN user_email varchar(255) NOT NULL",
                'created_at' => "ALTER TABLE {$reservations_table} ADD COLUMN created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
            );
            foreach ( $res_cols as $col => $sql ) {
                $exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$reservations_table} LIKE %s", $col ) );
                if ( empty( $exists ) ) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL ALTER statement built from hardcoded literals.
                    $wpdb->query( $sql );
                }
            }

            update_option( 'raffle_system_db_migrated_v4', 1 );
        }

        // v5 migration: draw_video_url + verified_result
        if ( ! get_option( 'raffle_system_db_migrated_v5' ) ) {
            $v5_cols = array(
                'draw_video_url'  => "ALTER TABLE {$table} ADD COLUMN draw_video_url varchar(500) DEFAULT ''",
                'verified_result' => "ALTER TABLE {$table} ADD COLUMN verified_result text",
            );
            foreach ( $v5_cols as $col => $sql ) {
                $exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $col ) );
                if ( empty( $exists ) ) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL ALTER statement built from hardcoded literals.
                    $wpdb->query( $sql );
                }
            }
            update_option( 'raffle_system_db_migrated_v5', 1 );
        }
    }

    public function add_menu() {
        // Top-level — clicking "Raffles" goes to Dashboard
        add_menu_page(
            'Raffles',
            'Raffles',
            'manage_options',
            'raffle-system',
            array( $this, 'render_dashboard_page' ),
            'dashicons-tickets-alt',
            30
        );

        // 1. Dashboard (same slug as parent — replaces top-level callback)
        add_submenu_page(
            'raffle-system',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'raffle-system',
            array( $this, 'render_dashboard_page' )
        );

        // 2. All Raffles
        add_submenu_page(
            'raffle-system',
            'All Raffles',
            'All Raffles',
            'manage_options',
            'raffle-list',
            array( $this, 'render_list_page' )
        );

        // 3. New Raffle
        add_submenu_page(
            'raffle-system',
            'New Raffle',
            'New Raffle',
            'manage_options',
            'raffle-new',
            array( $this, 'render_form_page' )
        );

        // 4. Templates
        add_submenu_page(
            'raffle-system',
            'Templates',
            'Templates',
            'manage_options',
            'raffle-templates',
            array( $this, 'render_templates_page' )
        );

        // 5. Charities (links to the raffle_charity CPT list)
        add_submenu_page(
            'raffle-system',
            'Charities',
            'Charities',
            'manage_options',
            'edit.php?post_type=raffle_charity'
        );

        // 5. Audit Log
        add_submenu_page(
            'raffle-system',
            'Audit Log',
            'Audit Log',
            'manage_options',
            'raffle-audit',
            array( $this, 'render_audit_page' )
        );

        // 6. Settings
        add_submenu_page(
            'raffle-system',
            'Settings',
            'Settings',
            'manage_options',
            'wpraffle-settings',
            array( $this, 'render_settings_page' )
        );



    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'raffle' ) === false ) {
            return;
        }

        wp_enqueue_media();

        // Google Fonts — Inter
        wp_enqueue_style( 'raffle-google-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
            array(), null
        );

        // WPRaffle Icon System
        wp_enqueue_style( 'wpraffle-icons', RAFFLE_SYSTEM_URL . 'assets/css/icons.css', array(), RAFFLE_SYSTEM_VERSION );

        wp_enqueue_style( 'raffle-admin', RAFFLE_SYSTEM_URL . 'assets/css/admin.css', array( 'raffle-google-fonts', 'wpraffle-icons' ), RAFFLE_SYSTEM_VERSION );
        wp_enqueue_script( 'raffle-admin', RAFFLE_SYSTEM_URL . 'assets/js/admin.js', array( 'jquery' ), RAFFLE_SYSTEM_VERSION, true );
        wp_localize_script( 'raffle-admin', 'raffleAdmin', array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'draw_nonce' => wp_create_nonce( 'raffle_draw_nonce' ),
            'clone_nonce' => wp_create_nonce( 'raffle_clone_nonce' ),
            'template_nonce' => wp_create_nonce( 'raffle_template_nonce' ),
        ) );

        // Dashboard page — Chart.js + dashboard.js (hook is toplevel_page_raffle-system)
        if ( strpos( $hook, 'toplevel_page_raffle-system' ) !== false ) {
            wp_enqueue_script( 'chartjs', RAFFLE_SYSTEM_URL . 'assets/vendor/chart.umd.min.js', array(), '4.4.7', true );
            wp_enqueue_script( 'raffle-dashboard', RAFFLE_SYSTEM_URL . 'assets/js/dashboard.js', array( 'jquery', 'chartjs' ), RAFFLE_SYSTEM_VERSION, true );
            wp_localize_script( 'raffle-dashboard', 'raffleDashboard', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'raffle_analytics_nonce' ),
                'curSym'   => wpr_currency_symbol(),
            ) );
        }
    }

    public function handle_form_submission() {
        if ( ! isset( $_POST['raffle_form_submit'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( ! isset( $_POST['raffle_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['raffle_nonce'] ) ), 'raffle_save' ) ) {
            wp_die( 'Security error.' );
        }

        // Ensure the schema is current BEFORE any write on this request. The
        // versioned migrations also run on admin_init, but callback ordering
        // between this handler and Raffle_Setup::run_migrations() is not
        // guaranteed, so a form save could otherwise fire before the v12-v14
        // columns exist (silent save failure). Running them here guarantees
        // the columns exist before the UPDATE/INSERT.
        if ( class_exists( 'Raffle_Setup' ) ) {
            Raffle_Setup::run_migrations();
        }

        global $wpdb;
        $table = $wpdb->prefix . 'raffles';

        $data = array(
            'title'                   => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
            'description'             => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'prize_value'             => floatval( wp_unslash( $_POST['prize_value'] ?? 0 ) ),
            'prize_image'             => esc_url_raw( wp_unslash( $_POST['prize_image'] ?? '' ) ),
            'total_tickets'           => absint( wp_unslash( $_POST['total_tickets'] ?? 0 ) ),
            'ticket_price'            => floatval( wp_unslash( $_POST['ticket_price'] ?? 0 ) ),
            'start_date'              => sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) ),
            'draw_date'               => sanitize_text_field( wp_unslash( $_POST['draw_date'] ?? '' ) ),
            'status'                  => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
            'ticket_selection'        => sanitize_text_field( wp_unslash( $_POST['ticket_selection'] ?? 'random' ) ),
            'draw_type'               => sanitize_text_field( wp_unslash( $_POST['draw_type'] ?? 'manual' ) ),
            'live_draw_url'           => esc_url_raw( wp_unslash( $_POST['live_draw_url'] ?? '' ) ),
            'jackpot_type'            => sanitize_text_field( wp_unslash( $_POST['jackpot_type'] ?? 'fixed' ) ),
            'jackpot_percent'         => absint( wp_unslash( $_POST['jackpot_percent'] ?? 50 ) ),
            'enable_cash_alternative' => (int) ( wp_unslash( $_POST['enable_cash_alternative'] ?? 0 ) ),
            'cash_alternative_amount' => floatval( wp_unslash( $_POST['cash_alternative_amount'] ?? 0 ) ),
            'enable_question'         => (int) ( wp_unslash( $_POST['enable_question'] ?? 0 ) ),
            'question_text'           => sanitize_text_field( wp_unslash( $_POST['question_text'] ?? '' ) ),
            'correct_answer_index'    => (int) ( wp_unslash( $_POST['correct_answer_index'] ?? 0 ) ),
            'postal_instructions'     => sanitize_textarea_field( wp_unslash( $_POST['postal_instructions'] ?? '' ) ),
            'max_tickets_per_user'    => (int) ( wp_unslash( $_POST['max_tickets_per_user'] ?? 100 ) ),
            'multi_winner'            => (int) ( wp_unslash( $_POST['multi_winner'] ?? 0 ) ),
            'number_of_winners'       => (int) ( wp_unslash( $_POST['number_of_winners'] ?? 2 ) ),
            'allow_free_entry'        => (int) ( wp_unslash( $_POST['allow_free_entry'] ?? 0 ) ),
            'geo_restricted'          => (int) ( wp_unslash( $_POST['geo_restricted'] ?? 0 ) ),
            'geo_allowed_countries'   => wp_json_encode( array_map( 'sanitize_text_field', (array) ( wp_unslash( $_POST['geo_allowed_countries'] ?? array() ) ) ) ),
            'allow_referrals'         => (int) ( wp_unslash( $_POST['allow_referrals'] ?? 0 ) ),
            'referral_bonus_entries'  => (int) ( wp_unslash( $_POST['referral_bonus_entries'] ?? 1 ) ),
            'draw_video_url'          => esc_url_raw( wp_unslash( $_POST['draw_video_url'] ?? '' ) ),
            'verified_result'         => sanitize_textarea_field( wp_unslash( $_POST['verified_result'] ?? '' ) ),
            // Feature expansion: charity fields
            'charity_id'              => isset( $_POST['charity_id'] ) && $_POST['charity_id'] ? absint( wp_unslash( $_POST['charity_id'] ) ) : null,
            'charity_mode'            => sanitize_text_field( wp_unslash( $_POST['charity_mode'] ?? 'none' ) ),
            'charity_percent'         => absint( wp_unslash( $_POST['charity_percent'] ?? 100 ) ),
            // 1.3.0 — Lifecycle fields.
            'min_tickets'             => absint( wp_unslash( $_POST['min_tickets'] ?? 0 ) ),
            'min_unique_users'        => absint( wp_unslash( $_POST['min_unique_users'] ?? 0 ) ),
            'auto_refund_on_fail'     => ! empty( $_POST['auto_refund_on_fail'] ) ? 1 : 0,
            // 1.3.0 — Q&A limits.
            'qa_time_limit'           => absint( wp_unslash( $_POST['qa_time_limit'] ?? 0 ) ),
            'qa_max_attempts'         => absint( wp_unslash( $_POST['qa_max_attempts'] ?? 0 ) ),
            // 1.3.0 — Ticket numbering.
            'ticket_numbering'        => in_array( sanitize_text_field( wp_unslash( $_POST['ticket_numbering'] ?? 'random' ) ), array( 'random', 'sequential', 'shuffled' ), true ) ? sanitize_text_field( wp_unslash( $_POST['ticket_numbering'] ?? 'random' ) ) : 'random',
            'ticket_prefix'           => sanitize_text_field( wp_unslash( $_POST['ticket_prefix'] ?? '' ) ),
            'ticket_suffix'           => sanitize_text_field( wp_unslash( $_POST['ticket_suffix'] ?? '' ) ),
            'ticket_start_number'     => max( 1, absint( wp_unslash( $_POST['ticket_start_number'] ?? 1 ) ) ),
        );

        // Convert empty start_date to NULL for DB compatibility
        if ( empty( $data['start_date'] ) ) {
            $data['start_date'] = null;
        } else {
            // Convert datetime-local format (T) to MySQL format
            $data['start_date'] = str_replace( 'T', ' ', $data['start_date'] );
            if ( strlen( $data['start_date'] ) === 16 ) { // YYYY-MM-DD HH:MM
                $data['start_date'] .= ':00';
            }
        }

        // Convert empty draw_date to NULL for DB compatibility
        if ( empty( $data['draw_date'] ) ) {
            $data['draw_date'] = null;
        } else {
            // Convert datetime-local format (T) to MySQL format
            $data['draw_date'] = str_replace( 'T', ' ', $data['draw_date'] );
            if ( strlen( $data['draw_date'] ) === 16 ) { // YYYY-MM-DD HH:MM
                $data['draw_date'] .= ':00';
            }
        }

        // Packages — accept either comma-separated ints (legacy) or a JSON
        // array of bundle objects ([{"qty":5,"price":25,...}]). When bundles
        // are enabled we store the JSON shape so the public UI can render
        // price/savings; otherwise we normalise to bare ints for back-compat.
        $packages_raw    = trim( wp_unslash( $_POST['packages'] ?? '' ) );
        $enable_bundles  = ! empty( $_POST['enable_bundles'] );
        $data['enable_bundles'] = $enable_bundles ? 1 : 0;

        // Number picker grid toggle (off by default for back-compat).
        $data['enable_number_grid'] = ! empty( $_POST['enable_number_grid'] ) ? 1 : 0;

        // Engagement & Marketing feature flags (all off by default).
        $data['enable_consolation_coupon'] = ! empty( $_POST['enable_consolation_coupon'] ) ? 1 : 0;
        $data['enable_scarcity']           = ! empty( $_POST['enable_scarcity'] ) ? 1 : 0;
        $data['enable_viewers_now']        = ! empty( $_POST['enable_viewers_now'] ) ? 1 : 0;
        $data['enable_share']              = ! empty( $_POST['enable_share'] ) ? 1 : 0;

        // Consolation coupon config.
        if ( $data['enable_consolation_coupon'] ) {
            $data['consolation_config'] = wp_json_encode( array(
                'type'        => ( $_POST['consolation_type'] ?? 'percent' ) === 'fixed' ? 'fixed' : 'percent',
                'amount'      => (float) ( $_POST['consolation_amount'] ?? 10 ),
                'expiry_days' => max( 1, (int) ( $_POST['consolation_expiry_days'] ?? 30 ) ),
            ) );
        } else {
            $data['consolation_config'] = '';
        }

        // 1.3.0 — Auto-relist config (JSON). Only stored when auto-relist is on.
        if ( ! empty( $_POST['enable_auto_relist'] ) ) {
            $data['relist_config'] = wp_json_encode( array(
                'auto_relist'       => 1,
                'relist_days'       => max( 1, (int) ( $_POST['relist_days'] ?? 7 ) ),
                'relist_pause_days' => max( 0, (int) ( $_POST['relist_pause_days'] ?? 0 ) ),
                'relist_count'      => max( 0, (int) ( $_POST['relist_count'] ?? 0 ) ),
            ) );
        } else {
            $data['relist_config'] = '';
        }

        if ( $enable_bundles && $packages_raw !== '' && $packages_raw[0] === '[' ) {
            // JSON bundle syntax — sanitise each object's scalar fields.
            $decoded = json_decode( $packages_raw, true );
            $clean   = array();
            if ( is_array( $decoded ) ) {
                foreach ( $decoded as $b ) {
                    if ( ! is_array( $b ) || empty( $b['qty'] ) ) {
                        continue;
                    }
                    $clean[] = array(
                        'qty'   => absint( $b['qty'] ),
                        'price' => isset( $b['price'] ) ? (float) $b['price'] : 0.0,
                        'label' => isset( $b['label'] ) ? sanitize_text_field( $b['label'] ) : '',
                        'badge' => isset( $b['badge'] ) ? sanitize_text_field( $b['badge'] ) : '',
                    );
                }
            }
            $data['packages'] = wp_json_encode( $clean );
        } else {
            // Legacy comma-separated ints.
            $packages_ints    = array_values( array_filter( array_map( 'absint', explode( ',', $packages_raw ) ) ) );
            $data['packages'] = wp_json_encode( $packages_ints );
        }

        // ── Server-side validation (before any side effects). ─────────────
        $raffle_id_for_validation = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        $existing                 = null;
        if ( $raffle_id_for_validation ) {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
                $raffle_id_for_validation
            ) );
        }
        $validation_errors = Raffle_Admin_Validation::validate_raffle(
            $data,
            (bool) $raffle_id_for_validation,
            $existing
        );

        if ( ! empty( $validation_errors ) ) {
            // Re-render the form with every field repopulated from $_POST and
            // per-field error messages, so the operator can fix and resubmit.
            $GLOBALS['wpraffle_form_errors'] = $validation_errors;
            $GLOBALS['wpraffle_posted_raffle'] = (object) array_merge(
                (array) ( $existing ?? new stdClass() ),
                $data,
                array(
                    // Preserve raw POST values the view reads directly where the
                    // $data shape differs (e.g. multi-field answers).
                    'question_answer_0' => sanitize_text_field( wp_unslash( $_POST['question_answer_0'] ?? '' ) ),
                    'question_answer_1' => sanitize_text_field( wp_unslash( $_POST['question_answer_1'] ?? '' ) ),
                    'question_answer_2' => sanitize_text_field( wp_unslash( $_POST['question_answer_2'] ?? '' ) ),
                )
            );
            // Pre-populate fields the view reads as $raffle->X but that are
            // posted separately from the structured $data array.
            $posted = $GLOBALS['wpraffle_posted_raffle'];
            foreach ( array( 'consolation_amount', 'consolation_expiry_days', 'consolation_type' ) as $f ) {
                $posted->{$f} = sanitize_text_field( wp_unslash( $_POST[ $f ] ?? '' ) );
            }
            // 1.3.0 — repopulate the new fields the form reads as $raffle->X.
            foreach ( array( 'enable_auto_relist', 'relist_days', 'relist_pause_days', 'relist_count' ) as $f ) {
                $posted->{$f} = sanitize_text_field( wp_unslash( $_POST[ $f ] ?? '' ) );
            }
            $this->render_form_page();
            return;
        }

        // Answers
        $answers = array(
            sanitize_text_field( wp_unslash( $_POST['question_answer_0'] ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['question_answer_1'] ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['question_answer_2'] ?? '' ) ),
        );
        $data['question_answers'] = wp_json_encode( $answers );

        $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;

        // Sync WooCommerce Product
        $wc_product_id = 0;
        if ( class_exists( 'WooCommerce' ) ) {
            $wc_status = $data['status'] === 'draft' ? 'draft' : 'publish';
            $product_data = array(
                'post_title'   => $data['title'],
                'post_content' => $data['description'],
                'post_status'  => $wc_status,
                'post_type'    => 'product',
                'post_author'  => get_current_user_id() ?: 1,
            );

            $existing_product_id = 0;
            if ( $raffle_id ) {
                $existing_product_id = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT wc_product_id FROM {$table} WHERE id = %d",
                    $raffle_id
                ) );
            }

            if ( $existing_product_id && get_post( $existing_product_id ) ) {
                $product_data['ID'] = $existing_product_id;
                wp_update_post( $product_data );
                $wc_product_id = $existing_product_id;
            } else {
                $wc_product_id = wp_insert_post( $product_data );
                if ( ! is_wp_error( $wc_product_id ) ) {
                    wp_set_object_terms( $wc_product_id, 'simple', 'product_type' );
                }
            }

            if ( ! is_wp_error( $wc_product_id ) && $wc_product_id ) {
                update_post_meta( $wc_product_id, '_visibility', 'visible' );
                update_post_meta( $wc_product_id, '_stock_status', 'instock' );
                update_post_meta( $wc_product_id, '_regular_price', $data['ticket_price'] );
                update_post_meta( $wc_product_id, '_price', $data['ticket_price'] );
                update_post_meta( $wc_product_id, '_virtual', 'yes' );
                update_post_meta( $wc_product_id, '_sold_individually', 'no' );
                update_post_meta( $wc_product_id, '_raffle_id', $raffle_id ? $raffle_id : 'temp' );
                update_post_meta( $wc_product_id, '_raffle_start_date', $data['start_date'] );
                update_post_meta( $wc_product_id, '_raffle_draw_date', $data['draw_date'] );
                update_post_meta( $wc_product_id, '_raffle_status', $data['status'] );
                
                // Clear catalog visibility exclusion
                wp_remove_object_terms( $wc_product_id, array( 'exclude-from-catalog', 'exclude-from-search' ), 'product_visibility' );

                // Apply WooCommerce product categories
                if ( isset( $_POST['product_categories'] ) && is_array( $_POST['product_categories'] ) ) {
                    $cat_ids = array_map( 'absint', $_POST['product_categories'] );
                    wp_set_object_terms( $wc_product_id, $cat_ids, 'product_cat' );
                } else {
                    wp_set_object_terms( $wc_product_id, array(), 'product_cat' );
                }

                // Apply WooCommerce product tags
                if ( isset( $_POST['product_tags'] ) && is_array( $_POST['product_tags'] ) ) {
                    $tag_ids = array_map( 'absint', $_POST['product_tags'] );
                    wp_set_object_terms( $wc_product_id, $tag_ids, 'product_tag' );
                } else {
                    wp_set_object_terms( $wc_product_id, array(), 'product_tag' );
                }
            }
        }

        $data['wc_product_id'] = $wc_product_id;

        // Build formats dynamically.
        // Defense in depth: strip any data keys whose columns don't exist in
        // the live table yet (e.g. a migration that failed to add a column).
        // This keeps the save working even if one optional column is missing —
        // the field is simply not persisted rather than failing the whole save.
        $actual_columns = $wpdb->get_col( "DESCRIBE {$table}" ); // phpcs:ignore WordPress.DB
        if ( is_array( $actual_columns ) ) {
            $data = array_intersect_key( $data, array_flip( $actual_columns ) );
        }

        $formats = array();
        foreach ( $data as $key => $val ) {
            if ( is_null( $val ) ) {
                $formats[] = '%s';
            } elseif ( is_int( $val ) || is_bool( $val ) ) {
                $formats[] = '%d';
            } elseif ( is_float( $val ) ) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        if ( $raffle_id ) {
            $result = $wpdb->update( $table, $data, array( 'id' => $raffle_id ), $formats, array( '%d' ) );
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $formats[]          = '%s';
            $result = $wpdb->insert( $table, $data, $formats );
            $new_raffle_id = $wpdb->insert_id;
            if ( $wc_product_id && $new_raffle_id ) {
                update_post_meta( $wc_product_id, '_raffle_id', $new_raffle_id );
            }
        }

        if ( false === $result ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional production error logging on DB write failure.
            error_log( 'WPRaffle: Error saving raffle: ' . $wpdb->last_error );
            wp_die(
                esc_html__( 'Error saving raffle.', 'wpraffle' ) .
                ( $wpdb->last_error ? '<br><code style="color:#b91c1c;">' . esc_html( $wpdb->last_error ) . '</code>' : '' )
            );
        }

        // BUG-6 FIX: Save prizes if multi-winner is enabled
        $target_id = $raffle_id ? $raffle_id : ( $new_raffle_id ?? 0 );
        if ( $target_id && class_exists( 'Raffle_Prizes' ) ) {
            $prize_names = $_POST['prize_name'] ?? array();
            $prize_values = $_POST['prize_value'] ?? array();
            $prizes = array();
            if ( is_array( $prize_names ) ) {
                foreach ( $prize_names as $i => $name ) {
                    if ( ! empty( $name ) ) {
                        $prizes[] = array(
                            'prize_name'  => sanitize_text_field( $name ),
                            'prize_value' => floatval( $prize_values[$i] ?? 0 ),
                            'prize_image' => '', 
                        );
                    }
                }
            }
            Raffle_Prizes::save_prizes( $target_id, $prizes );
        }

        // Audit log
        if ( class_exists( 'Raffle_Audit' ) ) {
            $action = $raffle_id ? 'admin_update' : 'admin_create';
            $rid = $raffle_id ? $raffle_id : ( $new_raffle_id ?? 0 );
            Raffle_Audit::log( $rid, $action, "Raffle '{$data['title']}' " . ( $raffle_id ? 'updated' : 'created' ) . " by admin. Status: {$data['status']}.", '' );
        }

        // Restore instant wins from a template (only on new-raffle creation).
        $template_post_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
        if ( ! $raffle_id && $template_post_id && ! empty( $new_raffle_id ) && class_exists( 'Raffle_Templates' ) ) {
            $tpl = Raffle_Templates::get_template( $template_post_id );
            if ( $tpl ) {
                $tpl_config = json_decode( $tpl->config, true );
                if ( is_array( $tpl_config ) && ! empty( $tpl_config['instant_wins'] ) ) {
                    $iw_table = $wpdb->prefix . 'raffle_instant_wins';
                    foreach ( $tpl_config['instant_wins'] as $iw ) {
                        if ( empty( $iw['prize_name'] ) ) {
                            continue;
                        }
                        $wpdb->insert(
                            $iw_table,
                            array(
                                'raffle_id'     => $new_raffle_id,
                                'ticket_number' => isset( $iw['ticket_number'] ) ? absint( $iw['ticket_number'] ) : 0,
                                'prize_name'    => sanitize_text_field( $iw['prize_name'] ),
                                'status'        => 'available',
                            ),
                            array( '%d', '%d', '%s', '%s' )
                        );
                    }
                }
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=raffle-list&message=saved' ) );
        exit;
    }

    public function render_list_page() {
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'view' && isset( $_GET['id'] ) ) {
            $this->render_details_page();
            return;
        }
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
            $this->render_form_page();
            return;
        }
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
            $this->delete_raffle();
            return;
        }
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'export_buyers' && isset( $_GET['id'] ) ) {
            $this->export_buyers_csv( absint( $_GET['id'] ) );
            return;
        }
        // 1.3.0 — Lifecycle actions (extend / relist), driven from raffle-details.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'extend' && isset( $_GET['id'] ) ) {
            $this->handle_extend();
            return;
        }
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'relist' && isset( $_GET['id'] ) ) {
            $this->handle_relist();
            return;
        }
        // 1.3.0 — Manual re-sync of missed wallet payouts for this raffle.
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'sync_wallet' && isset( $_GET['id'] ) ) {
            $this->handle_sync_wallet_payouts( absint( $_GET['id'] ) );
            return;
        }

        include RAFFLE_SYSTEM_PATH . 'admin/views/raffle-list.php';
    }

    /**
     * Stream a CSV of all buyers + their ticket numbers for a raffle.
     * Capability-checked + nonced via the row-action URL.
     */
    private function export_buyers_csv( $raffle_id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wpraffle' ) );
        }
        check_admin_referer( 'export_buyers_' . $raffle_id );

        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d", $raffle_id ) );
        if ( ! $raffle ) {
            wp_die( esc_html__( 'Raffle not found.', 'wpraffle' ) );
        }

        $purchases = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_purchases WHERE raffle_id = %d ORDER BY purchase_date DESC",
            $raffle_id
        ) );

        $slug = sanitize_title( $raffle->title );
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="buyers-' . $slug . '-' . $raffle_id . '.csv"' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV export streams to php://output.
        $out = fopen( 'php://output', 'w' );
        // UTF-8 BOM so Excel opens the CSV with correct encoding.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputs -- streaming to php://output, WP_Filesystem does not support output streams.
        fputs( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, array( 'Purchase ID', 'Name', 'Email', 'Quantity', 'Total Paid', 'Payment Status', 'Purchase Date', 'Ticket Numbers' ) );

        foreach ( $purchases as $p ) {
            $tickets = $wpdb->get_col( $wpdb->prepare(
                "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE purchase_id = %d ORDER BY ticket_number",
                $p->id
            ) );
            $formatted = array_map( function ( $n ) use ( $raffle ) {
                return Raffle_Tickets::format_ticket_number( $n, $raffle->total_tickets );
            }, $tickets );

            fputcsv( $out, array(
                $p->id,
                $p->buyer_name,
                $p->buyer_email,
                $p->quantity,
                $p->total_amount,
                $p->payment_status,
                $p->purchase_date,
                implode( ', ', $formatted ),
            ) );
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closing the php://output stream for CSV export.
        fclose( $out );
        exit;
    }

    /**
     * 1.3.0 — Extend a raffle's draw date. Nonced GET action from raffle-details.
     * Reads the new draw date from the query string; delegates to
     * Raffle_Lifecycle::extend_raffle().
     */
    public function handle_extend() {
        $raffle_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wpraffle' ) );
        }
        check_admin_referer( 'raffle_extend_' . $raffle_id );

        $new_date = isset( $_GET['new_draw_date'] ) ? sanitize_text_field( wp_unslash( $_GET['new_draw_date'] ) ) : '';
        if ( ! class_exists( 'Raffle_Lifecycle' ) ) {
            wp_die( esc_html__( 'Lifecycle feature unavailable.', 'wpraffle' ) );
        }
        $result = Raffle_Lifecycle::extend_raffle( $raffle_id, $new_date );

        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( array(
                'page'   => 'raffle-list',
                'action' => 'view',
                'id'     => $raffle_id,
                'lifecycle_error' => rawurlencode( $result->get_error_message() ),
            ), admin_url( 'admin.php' ) ) );
        } else {
            wp_safe_redirect( add_query_arg( array(
                'page'          => 'raffle-list',
                'action'        => 'view',
                'id'            => $raffle_id,
                'lifecycle_ok'  => 'extended',
            ), admin_url( 'admin.php' ) ) );
        }
        exit;
    }

    /**
     * 1.3.0 — Relist a finished/failed raffle. Nonced GET action from
     * raffle-details. Delegates to Raffle_Lifecycle::relist_raffle().
     */
    public function handle_relist() {
        $raffle_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wpraffle' ) );
        }
        check_admin_referer( 'raffle_relist_' . $raffle_id );

        $new_date = isset( $_GET['new_draw_date'] ) ? sanitize_text_field( wp_unslash( $_GET['new_draw_date'] ) ) : '';
        if ( ! class_exists( 'Raffle_Lifecycle' ) ) {
            wp_die( esc_html__( 'Lifecycle feature unavailable.', 'wpraffle' ) );
        }
        $result = Raffle_Lifecycle::relist_raffle( $raffle_id, $new_date );

        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( array(
                'page'   => 'raffle-list',
                'action' => 'view',
                'id'     => $raffle_id,
                'lifecycle_error' => rawurlencode( $result->get_error_message() ),
            ), admin_url( 'admin.php' ) ) );
        } else {
            wp_safe_redirect( add_query_arg( array(
                'page'          => 'raffle-list',
                'action'        => 'view',
                'id'            => $raffle_id,
                'lifecycle_ok'  => 'relisted',
            ), admin_url( 'admin.php' ) ) );
        }
        exit;
    }

    /**
     * 1.3.0 — Manual re-sync of missed wallet/credit payouts. Fires from the
     * per-raffle "Sync Wallet Payouts" button (GET, $raffle_id scoped) and the
     * global Settings → Advanced button (admin-post, $raffle_id = 0 = all).
     *
     * Re-attempts every 'pending' instant-win payout via the idempotent
     * Raffle_Wallet_Adapter::credit_instant_win(), so already-credited payouts
     * are skipped and only genuinely-missed ones are re-processed.
     *
     * @param int $raffle_id 0 = sync all raffles (from Settings); >0 = one raffle.
     */
    public function handle_sync_wallet_payouts( $raffle_id = 0 ) {
        // Capability + nonce. The per-raffle GET link uses a scoped nonce;
        // the admin-post Settings form uses a separate nonce.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wpraffle' ) );
        }
        if ( ! $raffle_id ) {
            // Called via admin-post from Settings → Advanced.
            check_admin_referer( 'wpraffle_sync_wallet', 'wpraffle_sync_wallet_nonce' );
            $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        } else {
            // Per-raffle GET link.
            check_admin_referer( 'raffle_sync_wallet_' . $raffle_id );
        }

        if ( ! class_exists( 'Raffle_Wallet_Adapter' ) ) {
            wp_die( esc_html__( 'Wallet adapter unavailable.', 'wpraffle' ) );
        }

        $result = Raffle_Wallet_Adapter::sync_pending_payouts( $raffle_id );

        // Build a human-readable summary for the admin notice.
        $summary = sprintf(
            /* translators: 1: credited count, 2: processed count. */
            _n( '%1$d payout credited (%2$d processed).', '%1$d payouts credited (%2$d processed).', $result['credited'], 'wpraffle' ),
            $result['credited'],
            $result['processed']
        );
        if ( $result['still_pending'] > 0 ) {
            $summary .= ' ' . sprintf(
                /* translators: count still pending. */
                _n( '%d still pending.', '%d still pending.', $result['still_pending'], 'wpraffle' ),
                $result['still_pending']
            );
        }

        // Redirect back to where we came from.
        if ( $raffle_id ) {
            wp_safe_redirect( add_query_arg( array(
                'page'             => 'raffle-list',
                'action'           => 'view',
                'id'               => $raffle_id,
                'wallet_sync_done' => '1',
                'wallet_sync_msg'  => rawurlencode( $summary ),
            ), admin_url( 'admin.php' ) ) );
        } else {
            wp_safe_redirect( add_query_arg( array(
                'page'             => 'wpraffle-settings',
                'tab'              => 'sync',
                'wallet_sync_done' => '1',
                'wallet_sync_msg'  => rawurlencode( $summary ),
            ), admin_url( 'admin.php' ) ) );
        }
        exit;
    }

    public function render_dashboard_page() {
        include RAFFLE_SYSTEM_PATH . 'admin/views/dashboard.php';
    }

    /**
     * Handle server-side template deletion via a nonced link on the Templates
     * page (simpler + more WP-native than an AJAX round-trip).
     */
    public function handle_template_delete() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'raffle-templates' ) {
            return;
        }
        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'delete' ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do that.', 'wpraffle' ) );
        }
        check_admin_referer( 'raffle_delete_template' );

        $template_id = absint( $_GET['id'] ?? 0 );
        if ( ! $template_id ) {
            return;
        }
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'raffle_templates', array( 'id' => $template_id ), array( '%d' ) );

        wp_safe_redirect( admin_url( 'admin.php?page=raffle-templates&message=deleted' ) );
        exit;
    }

    /**
     * Render the Templates library page.
     */
    public function render_templates_page() {
        $templates = class_exists( 'Raffle_Templates' ) ? Raffle_Templates::get_templates() : array();
        include RAFFLE_SYSTEM_PATH . 'admin/views/templates.php';
    }

    public function render_form_page() {
        $raffle      = null;
        $is_template = false;
        $template    = null;
        // Inline-validation support: when the form fails server-side
        // validation we re-render with a $_POST-derived raffle object plus
        // an errors map so the view can repopulate every field and show
        // per-field messages. These globals are set by handle_form_submission().
        $errors = isset( $GLOBALS['wpraffle_form_errors'] ) ? $GLOBALS['wpraffle_form_errors'] : array();

        if ( ! empty( $GLOBALS['wpraffle_posted_raffle'] ) ) {
            // Failed-validation re-render: use the posted object as-is.
            $raffle = $GLOBALS['wpraffle_posted_raffle'];
        } elseif ( isset( $_GET['id'] ) ) {
            global $wpdb;
            $raffle = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
                absint( $_GET['id'] )
            ) );
        } elseif ( isset( $_GET['template_id'] ) ) {
            // Pre-fill the form from a saved template. The template's config
            // becomes a raffle-like object so the existing value-population
            // pattern in the form view populates every field automatically.
            $template_id = absint( $_GET['template_id'] );
            $nonce_ok = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'raffle_apply_template' );

            if ( $nonce_ok && $template_id && class_exists( 'Raffle_Templates' ) ) {
                $template = Raffle_Templates::get_template( $template_id );
                if ( $template ) {
                    $config = json_decode( $template->config, true );
                    if ( is_array( $config ) ) {
                        // Merge with the full column set so every property the
                        // form view reads exists (avoids "Undefined property"
                        // warnings on template-derived objects).
                        $config = wp_parse_args( $config, array(
                            'id'                       => 0,
                            'title'                    => '',
                            'description'              => '',
                            'prize_value'              => '',
                            'prize_image'              => '',
                            'total_tickets'            => '',
                            'ticket_price'             => '',
                            'start_date'               => '',
                            'draw_date'                => '',
                            'status'                   => 'draft',
                            'ticket_selection'         => 'random',
                            'draw_type'                => 'manual',
                            'live_draw_url'            => '',
                            'jackpot_type'             => 'fixed',
                            'jackpot_percent'          => 50,
                            'enable_cash_alternative'  => 0,
                            'cash_alternative_amount'  => 0,
                            'enable_question'          => 0,
                            'question_text'            => '',
                            'question_answers'         => '',
                            'correct_answer_index'     => 0,
                            'postal_instructions'      => '',
                            'max_tickets_per_user'     => 100,
                            'multi_winner'             => 0,
                            'number_of_winners'        => 2,
                            'allow_free_entry'         => 0,
                            'free_entry_max'           => 1,
                            'geo_restricted'           => 0,
                            'geo_allowed_countries'    => '[]',
                            'allow_referrals'          => 0,
                            'referral_bonus_entries'   => 1,
                            'draw_video_url'           => '',
                            'verified_result'          => '',
                            'packages'                 => '[5,10,15,25]',
                            'enable_bundles'           => 0,
                            'enable_number_grid'       => 0,
                            'enable_consolation_coupon' => 0,
                            'consolation_config'       => '',
                            'enable_scarcity'          => 0,
                            'enable_viewers_now'       => 0,
                            'enable_share'             => 0,
                            'wc_product_id'            => 0,
                            'sold_tickets'             => 0,
                            'charity_id'               => null,
                            'charity_mode'             => 'none',
                            'charity_percent'          => 100,
                        ) );
                        // Clear raffle-specific fields so the operator must
                        // fill them in for the new raffle.
                        $config['id']          = 0;
                        $config['title']       = '';
                        $config['description'] = '';
                        $config['prize_value'] = '';
                        $config['prize_image'] = '';
                        $config['start_date']  = '';
                        $config['draw_date']   = '';
                        $config['status']      = 'draft';
                        $config['wc_product_id'] = 0;
                        $config['sold_tickets']  = 0;
                        $raffle                = (object) $config;
                        $is_template           = true;
                    }
                }
            }

            if ( ! $is_template ) {
                // Invalid template/nonce — fall through to a blank form.
                $raffle = null;
            }
        }

        include RAFFLE_SYSTEM_PATH . 'admin/views/raffle-form.php';
    }

    private function render_details_page() {
        global $wpdb;
        $raffle_id = absint( $_GET['id'] );
        $raffle    = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            echo '<div class="wrap"><h1>Raffle not found</h1></div>';
            return;
        }

        $purchases = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_purchases WHERE raffle_id = %d ORDER BY purchase_date DESC",
            $raffle_id
        ) );

        $winner = null;
        if ( $raffle->winner_ticket_id ) {
            $winner = $wpdb->get_row( $wpdb->prepare(
                "SELECT t.*, p.buyer_name
                 FROM {$wpdb->prefix}raffle_tickets t
                 JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id
                 WHERE t.id = %d",
                $raffle->winner_ticket_id
            ) );
        }

        include RAFFLE_SYSTEM_PATH . 'admin/views/raffle-details.php';
    }

    private function delete_raffle() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_raffle' ) ) {
            wp_die( 'Security error.' );
        }

        global $wpdb;
        $raffle_id = absint( $_GET['id'] );

        // Delete associated WooCommerce product if exists
        if ( class_exists( 'WooCommerce' ) ) {
            $wc_product_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT wc_product_id FROM {$wpdb->prefix}raffles WHERE id = %d",
                $raffle_id
            ) );
            if ( $wc_product_id ) {
                wp_delete_post( $wc_product_id, true );
            }
        }

        // ATOMIC TRANSACTION: delete everything or nothing
        $wpdb->query( 'START TRANSACTION' );
        $wpdb->delete( $wpdb->prefix . 'raffle_tickets', array( 'raffle_id' => $raffle_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'raffle_purchases', array( 'raffle_id' => $raffle_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'raffles', array( 'id' => $raffle_id ), array( '%d' ) );
        $wpdb->query( 'COMMIT' );

        wp_safe_redirect( admin_url( 'admin.php?page=raffle-list&message=deleted' ) );
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Unified Settings Page
    // ─────────────────────────────────────────────────────────────────────

    public function render_audit_page() {
        include RAFFLE_SYSTEM_PATH . 'admin/views/audit-log.php';
    }

    public function render_settings_page() {
        include RAFFLE_SYSTEM_PATH . 'admin/views/settings.php';
    }

    public function save_general_settings() {
        $this->verify_settings_nonce();
        $settings = array(
            'company_name'              => sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) ),
            'company_address'           => sanitize_textarea_field( wp_unslash( $_POST['company_address'] ?? '' ) ),
            'currency_code'             => sanitize_text_field( wp_unslash( $_POST['currency_code'] ?? 'GBP' ) ),
            'logo_url'                  => esc_url_raw( wp_unslash( $_POST['logo_url'] ?? '' ) ),
            'max_tickets_default'       => absint( $_POST['max_tickets_default'] ?? 100 ),
            'publish_winner_full_name'  => isset( $_POST['publish_winner_full_name'] ) ? 1 : 0,
            'winners_show_live_draw'    => isset( $_POST['winners_show_live_draw'] ) ? 1 : 0,
            'winners_show_auto_draw'    => isset( $_POST['winners_show_auto_draw'] ) ? 1 : 0,
            'winners_show_instant_wins' => isset( $_POST['winners_show_instant_wins'] ) ? 1 : 0,
        );
        update_option( 'wpraffle_general_settings', $settings );
        $this->redirect_settings( 'general' );
    }

    public function save_email_settings() {
        $this->verify_email_nonce();

        // Server-side validation before persisting.
        $errors = Raffle_Admin_Validation::validate_settings( 'email', $_POST );
        if ( ! empty( $errors ) ) {
            $this->redirect_settings_error( 'email', (string) key( $errors ) );
        }

        $settings = array(
            'from_name'    => sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) ),
            'from_email'   => sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) ),
            'accent_color' => sanitize_hex_color( wp_unslash( $_POST['accent_color'] ?? '#6c5ce7' ) ),
            'logo_url'     => esc_url_raw( wp_unslash( $_POST['logo_url'] ?? '' ) ),
            'footer_text'  => sanitize_textarea_field( wp_unslash( $_POST['footer_text'] ?? '' ) ),
        );
        // 1.3.0 — preserve the toggle map + admin recipients + PDF-attach flag
        // when re-saving sender details (these live in the same option).
        $existing = get_option( 'wpraffle_email_settings', array() );
        if ( is_array( $existing ) ) {
            if ( isset( $existing['enabled'] ) ) {
                $settings['enabled'] = $existing['enabled'];
            }
            if ( isset( $existing['admin_notify_emails'] ) ) {
                $settings['admin_notify_emails'] = $existing['admin_notify_emails'];
            }
        }
        $settings['attach_ticket_pdf'] = isset( $_POST['attach_ticket_pdf'] ) ? 1 : 0;
        update_option( 'wpraffle_email_settings', $settings );
        $this->redirect_settings( 'email' );
    }

    /**
     * 1.3.0 — Save per-email enable/disable toggles. Merges into the existing
     * wpraffle_email_settings option so sender/branding settings are preserved.
     * Each email id maps to ['off' => 1] when unchecked, or is absent when
     * enabled (absent = enabled, matching is_email_enabled()'s default).
     */
    public function save_email_toggles() {
        check_admin_referer( 'wpraffle_save_email_toggles', 'wpraffle_email_toggles_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wpraffle' ) );
        }

        $existing = get_option( 'wpraffle_email_settings', array() );
        if ( ! is_array( $existing ) ) {
            $existing = array();
        }

        $posted = isset( $_POST['email_toggles'] ) ? (array) wp_unslash( $_POST['email_toggles'] ) : array();
        $enabled = array();
        // Build the full map: any id NOT in the posted set is OFF.
        $all_ids = array(
            'purchase', 'winner', 'instant_win', 'no_luck', 'draw_reminder',
            'raffle_started', 'raffle_extended', 'failed_participant',
            'admin_sale', 'admin_draw', 'admin_winner', 'admin_failed',
            'admin_started', 'admin_relisted',
        );
        foreach ( $all_ids as $id ) {
            if ( isset( $posted[ $id ] ) ) {
                // Checked = enabled → omit (absent means enabled).
            } else {
                $enabled[ $id ] = array( 'off' => 1 );
            }
        }

        $existing['enabled'] = $enabled;
        update_option( 'wpraffle_email_settings', $existing );
        $this->redirect_settings( 'email' );
    }

    /**
     * 1.3.0 — Save additional admin notification recipients (comma-separated).
     */
    public function save_admin_recipients() {
        check_admin_referer( 'wpraffle_save_admin_recipients', 'wpraffle_admin_recip_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'wpraffle' ) );
        }

        $existing = get_option( 'wpraffle_email_settings', array() );
        if ( ! is_array( $existing ) ) {
            $existing = array();
        }

        $raw    = isset( $_POST['admin_notify_emails'] ) ? sanitize_text_field( wp_unslash( $_POST['admin_notify_emails'] ) ) : '';
        $emails = array();
        foreach ( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) as $e ) {
            if ( is_email( $e ) ) {
                $emails[] = $e;
            }
        }
        $existing['admin_notify_emails'] = implode( ', ', $emails );
        update_option( 'wpraffle_email_settings', $existing );
        $this->redirect_settings( 'email' );
    }

    public function save_legal_settings() {
        $this->verify_settings_nonce();
        $settings = array(
            'rules_template' => wp_kses_post( wp_unslash( $_POST['rules_template'] ?? '' ) ),
            'faq_template'   => wp_kses_post( wp_unslash( $_POST['faq_template'] ?? '' ) ),
            'faq_items'      => (function() {
                // SEC-18 FIX: Explicitly cast to arrays to prevent errors if not array
                $questions = (array) ( $_POST['faq_questions'] ?? array() );
                $answers   = (array) ( $_POST['faq_answers'] ?? array() );
                $items = array();
                for ( $i = 0; $i < max( count( $questions ), count( $answers ) ); $i++ ) {
                    $q = sanitize_text_field( wp_unslash( $questions[ $i ] ?? '' ) );
                    $a = wp_kses_post( wp_unslash( $answers[ $i ] ?? '' ) );
                    if ( $q || $a ) {
                        $items[] = array( 'q' => $q, 'a' => $a );
                    }
                }
                return wp_json_encode( $items );
            })(),
        );
        update_option( 'wpraffle_legal_settings', $settings );
        $this->redirect_settings( 'legal' );
    }

    public function save_advanced_settings() {
        $this->verify_settings_nonce();

        // Server-side validation before persisting.
        $errors = Raffle_Admin_Validation::validate_settings( 'advanced', $_POST );
        if ( ! empty( $errors ) ) {
            $this->redirect_settings_error( 'advanced', (string) key( $errors ) );
        }

        $settings = array(
            'auto_fix_duplicates'   => absint( $_POST['auto_fix_duplicates'] ?? 0 ),
            'rate_limit_per_minute' => absint( $_POST['rate_limit_per_minute'] ?? 5 ),
            'audit_log_days'        => absint( $_POST['audit_log_days'] ?? 90 ),
            'enable_audit'          => absint( $_POST['enable_audit'] ?? 1 ),
        );
        update_option( 'wpraffle_advanced_settings', $settings );

        // Trusted-proxy allowlist (S3) — store comma-separated, validated to
        // IP/CIDR-ish tokens only.
        $raw_proxies = isset( $_POST['trusted_proxies'] ) ? sanitize_text_field( wp_unslash( $_POST['trusted_proxies'] ) ) : '';
        $tokens = array_filter( array_map( 'trim', explode( ',', $raw_proxies ) ) );
        $clean  = array();
        foreach ( $tokens as $t ) {
            if ( preg_match( '/^[0-9a-fA-F.:\/]+$/', $t ) ) {
                $clean[] = $t;
            }
        }
        update_option( 'wpraffle_trusted_proxies', implode( ', ', $clean ) );

        $this->redirect_settings( 'advanced' );
    }

    public function save_update_settings() {
        $this->verify_settings_nonce();
        $settings = array(
            'github_repo'     => sanitize_text_field( wp_unslash( $_POST['github_repo'] ?? 'wpraffle/wpraffle' ) ),
            'auto_update'     => absint( $_POST['auto_update'] ?? 0 ),
            'update_channel'  => sanitize_text_field( wp_unslash( $_POST['update_channel'] ?? 'stable' ) ),
        );
        update_option( 'wpraffle_update_settings', $settings );

        // Anonymous activation notice opt-out (checkbox checked = opted out).
        update_option( 'wpraffle_tracking_opted_out', isset( $_POST['wpraffle_tracking_opted_out'] ) ? 1 : 0 );

        // Clear cache so next check uses new repo
        delete_transient( 'wpraffle_release_info' );
        delete_transient( 'wpraffle_latest_version' );
        $this->redirect_settings( 'updates' );
    }

    public function handle_create_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorised.' );
        }
        if ( ! isset( $_POST['wpraffle_page_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpraffle_page_nonce'] ) ), 'wpraffle_create_page' ) ) {
            wp_die( 'Security check failed.' );
        }

        $key = sanitize_text_field( wp_unslash( $_POST['page_key'] ?? '' ) );
        $pages = get_option( 'wpraffle_pages', array() );

        $configs = array(
            'raffles'     => array( 'title' => 'Raffles',      'content' => '[raffle_list]' ),
            'ended'       => array( 'title' => 'Past Raffles', 'content' => '[raffle_ended_list]' ),
            'entry_list'  => array( 'title' => 'Entry Lists',  'content' => '[raffle_entry_list]' ),
            'live_draw'   => array( 'title' => 'Live Draw',    'content' => '[raffle_live_draw]' ),
            'my_raffles'  => array( 'title' => 'My Raffles',   'content' => '' ),
            'charities'   => array( 'title' => 'Charities',    'content' => '[raffle_charities]' ),
        );

        if ( ! isset( $configs[ $key ] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=wpraffle-settings&tab=pages' ) );
            exit;
        }

        $cfg = $configs[ $key ];
        $page_id = wp_insert_post( array(
            'post_title'   => $cfg['title'],
            'post_content' => $cfg['content'],
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id(),
        ) );

        if ( ! is_wp_error( $page_id ) ) {
            $pages[ $key ] = $page_id;
            update_option( 'wpraffle_pages', $pages );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=wpraffle-settings&tab=pages&saved=1' ) );
        exit;
    }

    public function save_pages() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorised.' );
        }
        if ( ! isset( $_POST['wpraffle_pages_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpraffle_pages_nonce'] ) ), 'wpraffle_save_pages' ) ) {
            wp_die( 'Security check failed.' );
        }

        $keys = array( 'raffles', 'ended', 'entry_list', 'live_draw', 'my_raffles', 'charities' );
        $pages = get_option( 'wpraffle_pages', array() );

        foreach ( $keys as $key ) {
            $field = 'wpraffle_page_' . $key;
            $pages[ $key ] = isset( $_POST[ $field ] ) ? absint( $_POST[ $field ] ) : 0;
        }

        update_option( 'wpraffle_pages', $pages );
        $this->redirect_settings( 'pages' );
    }

    public function save_shortcode_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorised.' );
        }
        if ( ! isset( $_POST['wpraffle_sc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpraffle_sc_nonce'] ) ), 'wpraffle_save_shortcode_settings' ) ) {
            wp_die( 'Security check failed.' );
        }

        $shortcodes = array( 'raffle_ended_list', 'raffle_entry_list', 'raffle_list' );
        $fields_map = array(
            'raffle_ended_list' => array( 'columns', 'show_image', 'show_winner', 'show_video_btn', 'show_verified_btn', 'show_date', 'show_entries' ),
            'raffle_entry_list' => array( 'layout', 'columns', 'button_text', 'button_bg', 'button_color', 'button_radius', 'show_image' ),
            'raffle_list'       => array( 'status' ),
        );
        $sanitize_map = array(
            'columns' => 'absint', 'show_image' => 'sanitize_text_field', 'show_winner' => 'sanitize_text_field',
            'show_video_btn' => 'sanitize_text_field', 'show_verified_btn' => 'sanitize_text_field',
            'show_date' => 'sanitize_text_field', 'show_entries' => 'sanitize_text_field',
            'layout' => 'sanitize_text_field', 'button_text' => 'sanitize_text_field',
            'button_bg' => 'sanitize_hex_color', 'button_color' => 'sanitize_hex_color',
            'button_radius' => 'absint', 'status' => 'sanitize_text_field',
        );

        $settings = array();
        foreach ( $shortcodes as $sc ) {
            $sc_data = array();
            $sc_data['enabled'] = isset( $_POST[ 'sc_' . $sc . '_enabled' ] ) ? 1 : 0;

            if ( isset( $fields_map[ $sc ] ) ) {
                foreach ( $fields_map[ $sc ] as $field ) {
                    $post_key = 'sc_' . $sc . '_' . $field;
                    if ( isset( $_POST[ $post_key ] ) ) {
                        $sanitizer = isset( $sanitize_map[ $field ] ) ? $sanitize_map[ $field ] : 'sanitize_text_field';
                        $sc_data[ $field ] = call_user_func( $sanitizer, wp_unslash( $_POST[ $post_key ] ) );
                    }
                }
            }
            $settings[ $sc ] = $sc_data;
        }

        update_option( 'wpraffle_shortcode_settings', $settings );
        $this->redirect_settings( 'pages' );
    }

    private function verify_settings_nonce( $field = 'wpraffle_settings_nonce', $action = 'wpraffle_save_settings' ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorised.' );
        }
        if ( ! isset( $_POST[ $field ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ), $action ) ) {
            wp_die( 'Security check failed.' );
        }
    }

    private function verify_email_nonce() {
        $this->verify_settings_nonce( 'wpraffle_email_nonce', 'wpraffle_save_email_settings' );
    }

    private function redirect_settings( $tab ) {
        wp_safe_redirect( admin_url( 'admin.php?page=wpraffle-settings&tab=' . $tab . '&saved=1' ) );
        exit;
    }

    /**
     * Redirect back to a settings tab with an error message. The error code
     * is mapped to a human message by settings.php for display.
     *
     * @param string $tab  Settings tab slug.
     * @param string $code Stable error code (keyed in settings.php's map).
     */
    private function redirect_settings_error( $tab, $code ) {
        wp_safe_redirect( admin_url( 'admin.php?page=wpraffle-settings&tab=' . $tab . '&error=1&ecode=' . rawurlencode( $code ) ) );
        exit;
    }

    public function save_styling_settings() {
        $this->verify_settings_nonce();

        $color_keys = array( 'custom_accent', 'custom_accent_dark', 'custom_text', 'custom_bg' );
        $settings = array(
            'preset'                 => sanitize_text_field( wp_unslash( $_POST['preset'] ?? 'default' ) ),
            'disable_custom_styling' => isset( $_POST['disable_custom_styling'] ) ? '1' : '0',
        );
        foreach ( $color_keys as $key ) {
            $raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
            // Only store a valid hex colour; empty/invalid = empty string (use preset).
            $settings[ $key ] = $raw ? ( sanitize_hex_color( $raw ) ?: '' ) : '';
        }

        update_option( 'wpraffle_styling_settings', $settings );
        $this->redirect_settings( 'styling' );
    }

    public function handle_test_email() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorised.' );
        }
        if ( ! isset( $_POST['wpraffle_test_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpraffle_test_nonce'] ) ), 'wpraffle_test_email' ) ) {
            wp_die( 'Security check failed.' );
        }

        $to     = sanitize_email( wp_unslash( $_POST['test_email_to'] ?? get_option( 'admin_email' ) ) );
        $result = Raffle_Email::send_test_email( $to );

        $status = $result ? 'sent' : 'failed';
        wp_safe_redirect( admin_url( 'admin.php?page=wpraffle-settings&tab=email&test=' . $status ) );
        exit;
    }
}
