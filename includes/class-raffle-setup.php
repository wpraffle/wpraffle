<?php
/**
 * WPRaffle — Schema Setup & Migrations for v2 features
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Setup {

    public static function run_migrations() {
        self::migration_v6_tables();
        self::migration_v7_raffle_charity_cols();
        self::migration_v8_rg_email_and_payouts();
        self::migration_v9_raffle_feature_flags();
        self::migration_v10_charity_tables_backstop();
        self::migration_v10_charity_backfill();
    }

    private static function migration_v6_tables() {
        if ( get_option( 'raffle_system_db_migrated_v6' ) ) {
            return;
        }

        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = '';
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_charities (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description longtext,
            logo_url varchar(500) DEFAULT '',
            website varchar(500) DEFAULT '',
            registration_number varchar(100) DEFAULT '',
            donation_address longtext,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            total_raised decimal(12,2) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) {$charset}; ";

        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_charity_allocations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            raffle_id bigint(20) UNSIGNED NOT NULL,
            charity_id bigint(20) UNSIGNED NOT NULL,
            gross_revenue decimal(12,2) NOT NULL DEFAULT 0,
            prize_value decimal(10,2) NOT NULL DEFAULT 0,
            net_proceeds decimal(12,2) NOT NULL DEFAULT 0,
            allocation_percent int(11) NOT NULL DEFAULT 100,
            allocated_amount decimal(12,2) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            disbursed_at datetime DEFAULT NULL,
            disbursement_reference varchar(255) DEFAULT '',
            fairness_proof varchar(255) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY raffle_id (raffle_id),
            KEY charity_id (charity_id)
        ) {$charset}; ";

        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_credits (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            raffle_id bigint(20) UNSIGNED DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            balance_after decimal(10,2) NOT NULL,
            type varchar(20) NOT NULL,
            reason varchar(255) DEFAULT '',
            reference varchar(100) DEFAULT '',
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY raffle_id (raffle_id)
        ) {$charset}; ";

        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_payouts (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            raffle_id bigint(20) UNSIGNED NOT NULL,
            ticket_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            user_email varchar(255) NOT NULL,
            payout_type varchar(20) NOT NULL,
            amount decimal(10,2) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            idempotency_key varchar(100) NOT NULL,
            provider varchar(50) DEFAULT '',
            provider_txn_id varchar(100) DEFAULT '',
            fairness_proof varchar(255) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            credited_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idempotency_key (idempotency_key),
            KEY raffle_id (raffle_id),
            KEY ticket_id (ticket_id),
            KEY user_email (user_email)
        ) {$charset}; ";

        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_rg_settings (
            user_id bigint(20) NOT NULL,
            buyer_email varchar(100) DEFAULT '',
            spend_limit_period varchar(10) NOT NULL DEFAULT 'month',
            spend_limit_amount decimal(10,2) NOT NULL DEFAULT 0,
            spend_window_start datetime DEFAULT NULL,
            spend_window_total decimal(10,2) NOT NULL DEFAULT 0,
            self_excluded_until datetime DEFAULT NULL,
            reality_check_minutes int(11) NOT NULL DEFAULT 0,
            operator_locked tinyint(1) NOT NULL DEFAULT 0,
            operator_lock_reason varchar(255) DEFAULT '',
            locked_until datetime DEFAULT NULL,
            cool_off_change_until datetime DEFAULT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id)
        ) {$charset}; ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Only flag v6 complete if the charity tables actually exist. dbDelta
        // can silently no-op on formatting quirks; the v10 backstop is the
        // safety net, but we don't want a false-positive v6 flag here either.
        $charities_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'raffle_charities' ) ) === $wpdb->prefix . 'raffle_charities';
        if ( $charities_exists ) {
            update_option( 'raffle_system_db_migrated_v6', 1 );
        }
    }

    private static function migration_v7_raffle_charity_cols() {
        if ( get_option( 'raffle_system_db_migrated_v7' ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'raffles';

        $cols = array(
            'charity_id'      => "ALTER TABLE {$table} ADD COLUMN charity_id bigint(20) UNSIGNED DEFAULT NULL",
            'charity_mode'    => "ALTER TABLE {$table} ADD COLUMN charity_mode varchar(10) NOT NULL DEFAULT 'none'",
            'charity_percent' => "ALTER TABLE {$table} ADD COLUMN charity_percent int(11) NOT NULL DEFAULT 100",
        );

        foreach ( $cols as $col => $sql ) {
            $exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $col ) );
            if ( empty( $exists ) ) {
                $wpdb->query( $sql );
            }
        }

        update_option( 'raffle_system_db_migrated_v7', 1 );
    }

    /**
     * v8 — RG email extension (guest self-exclusion + spend tracking) and
     * payouts idempotency hardening. The idempotency_key UNIQUE constraint
     * already exists from v6; this migration adds a named KEY on status to
     * speed up the pending-payout retry lookup and ensures the RG settings
     * table has the email column + indices needed for guest RG enforcement.
     */
    private static function migration_v8_rg_email_and_payouts() {
        if ( get_option( 'raffle_system_db_migrated_v8' ) ) {
            return;
        }

        global $wpdb;

        // RG settings: add buyer_email column (for logged-in users we backfill
        // it lazily; for guests it carries the exclusion/limit anchor).
        $rg_table = $wpdb->prefix . 'raffle_rg_settings';
        $exists   = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$rg_table} LIKE %s", 'buyer_email' ) );
        if ( empty( $exists ) ) {
            $wpdb->query( "ALTER TABLE {$rg_table} ADD COLUMN buyer_email varchar(100) DEFAULT '' AFTER user_id" );
            $wpdb->query( "ALTER TABLE {$rg_table} ADD KEY buyer_email (buyer_email)" );
        }

        // Widen user_id from UNSIGNED to SIGNED so we can store negative
        // synthetic IDs for guest (email-keyed) RG rows. Real WP user IDs are
        // always positive, so negative values are reserved for guests.
        $col = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$rg_table} LIKE %s", 'user_id' ) );
        if ( $col && stripos( $col->Type, 'unsigned' ) !== false ) {
            $wpdb->query( "ALTER TABLE {$rg_table} MODIFY user_id bigint(20) NOT NULL" );
        }

        // Pending spend limit — when a user raises their limit, the new value
        // is held here until the 24h cool-off passes, while spend_limit_amount
        // keeps the old (effective) value. Closes the dead-column bug where
        // cool_off_change_until was written but never read.
        $exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$rg_table} LIKE %s", 'pending_spend_limit_amount' ) );
        if ( empty( $exists ) ) {
            $wpdb->query( "ALTER TABLE {$rg_table} ADD COLUMN pending_spend_limit_amount decimal(10,2) NOT NULL DEFAULT 0" );
        }

        // Pending-payout retry lookup index.
        $payouts = $wpdb->prefix . 'raffle_payouts';
        $idx     = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM {$payouts} WHERE Key_name = %s", 'status' ) );
        if ( empty( $idx ) ) {
            $wpdb->query( "ALTER TABLE {$payouts} ADD KEY status (status)" );
        }

        update_option( 'raffle_system_db_migrated_v8', 1 );
    }

    /**
     * v9 — Raffle engagement feature flags. All default OFF so existing
     * raffles are unaffected until an operator opts in.
     */
    private static function migration_v9_raffle_feature_flags() {
        if ( get_option( 'raffle_system_db_migrated_v9' ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'raffles';

        $cols = array(
            'enable_bundles'           => "ALTER TABLE {$table} ADD COLUMN enable_bundles tinyint(1) NOT NULL DEFAULT 0",
            'bundle_config'            => "ALTER TABLE {$table} ADD COLUMN bundle_config longtext",
            'enable_number_grid'       => "ALTER TABLE {$table} ADD COLUMN enable_number_grid tinyint(1) NOT NULL DEFAULT 0",
            'enable_scarcity'          => "ALTER TABLE {$table} ADD COLUMN enable_scarcity tinyint(1) NOT NULL DEFAULT 0",
            'enable_viewers_now'       => "ALTER TABLE {$table} ADD COLUMN enable_viewers_now tinyint(1) NOT NULL DEFAULT 0",
            'enable_share'             => "ALTER TABLE {$table} ADD COLUMN enable_share tinyint(1) NOT NULL DEFAULT 0",
            'enable_consolation_coupon' => "ALTER TABLE {$table} ADD COLUMN enable_consolation_coupon tinyint(1) NOT NULL DEFAULT 0",
            'consolation_config'       => "ALTER TABLE {$table} ADD COLUMN consolation_config longtext",
        );

        foreach ( $cols as $col => $sql ) {
            $exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $col ) );
            if ( empty( $exists ) ) {
                $wpdb->query( $sql );
            }
        }

        update_option( 'raffle_system_db_migrated_v9', 1 );
    }

    /**
     * v10 — Charity tables backstop.
     *
     * The charity feature depends on {$prefix}raffle_charities and
     * {$prefix}raffle_charity_allocations. On some installs the v6 migration
     * flagged itself complete without these tables actually being created
     * (dbDelta silently no-ops on formatting quirks), which made every charity
     * query return 0. This backstop runs on every admin_init, is independent
     * of the v6 flag, and idempotently creates the tables if missing. dbDelta
     * is inherently safe to re-run.
     */
    private static function migration_v10_charity_tables_backstop() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $missing = false;
        foreach ( array( 'raffle_charities', 'raffle_charity_allocations' ) as $tbl ) {
            $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $tbl ) );
            if ( $found !== $wpdb->prefix . $tbl ) {
                $missing = true;
                break;
            }
        }

        if ( ! $missing ) {
            return; // Tables exist — nothing to do.
        }

        // dbDelta is fussy about formatting: lowercase types, two spaces
        // between column name and type, KEY (not INDEX).
        $sql = "CREATE TABLE {$wpdb->prefix}raffle_charities (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL,
  slug varchar(255) NOT NULL,
  description longtext,
  logo_url varchar(500) DEFAULT '',
  website varchar(500) DEFAULT '',
  registration_number varchar(100) DEFAULT '',
  donation_address longtext,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  total_raised decimal(12,2) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug)
) $charset;

CREATE TABLE {$wpdb->prefix}raffle_charity_allocations (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  raffle_id bigint(20) unsigned NOT NULL,
  charity_id bigint(20) unsigned NOT NULL,
  gross_revenue decimal(12,2) NOT NULL DEFAULT 0,
  prize_value decimal(10,2) NOT NULL DEFAULT 0,
  net_proceeds decimal(12,2) NOT NULL DEFAULT 0,
  allocation_percent int(11) NOT NULL DEFAULT 100,
  allocated_amount decimal(12,2) NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'pending',
  disbursed_at datetime DEFAULT NULL,
  disbursement_reference varchar(255) DEFAULT '',
  fairness_proof varchar(255) DEFAULT '',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY raffle_id (raffle_id),
  KEY charity_id (charity_id)
) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * v10 — One-shot backfill of charity totals.
     *
     * After the v10 backstop creates the tables, sync every charity CPT post
     * into the raffle_charities table (so the DB-table path is the canonical
     * one going forward) and recompute totals from existing sold tickets.
     * Runs once, gated by a flag.
     */
    private static function migration_v10_charity_backfill() {
        if ( get_option( 'wpraffle_charity_backfill_v10' ) ) {
            return;
        }

        // Sync every charity CPT post into the DB table.
        $posts = get_posts( array(
            'post_type'      => 'raffle_charity',
            'post_status'    => 'any',
            'posts_per_page' => -1,
        ) );

        foreach ( $posts as $post ) {
            // sync_charity_to_db is private; replicate its core insert/update
            // logic here so the backfill is self-contained.
            self::backfill_one_charity( $post );
        }

        // Recompute totals from existing sold tickets.
        if ( class_exists( 'Raffle_Charity' ) ) {
            Raffle_Charity::refresh_all_totals();
        }

        update_option( 'wpraffle_charity_backfill_v10', 1 );
    }

    /**
     * Mirror one charity CPT post into the raffle_charities DB table.
     * Used by the v10 backfill. Idempotent on slug.
     */
    private static function backfill_one_charity( $post ) {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_charities';

        $name        = $post->post_title;
        $slug        = $post->post_name ?: sanitize_title( $name );
        $description = get_post_meta( $post->ID, '_charity_description', true );
        $logo_url    = get_post_meta( $post->ID, '_charity_logo_url', true );
        $website     = get_post_meta( $post->ID, '_charity_website', true );
        $reg_number  = get_post_meta( $post->ID, '_charity_registration_number', true );
        $donation    = get_post_meta( $post->ID, '_charity_donation_address', true );
        $is_active   = (int) get_post_meta( $post->ID, '_charity_is_active', true );

        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $slug ) );

        if ( $existing ) {
            $wpdb->update(
                $table,
                array(
                    'name'                => $name,
                    'description'         => $description,
                    'logo_url'            => $logo_url,
                    'website'             => $website,
                    'registration_number' => $reg_number,
                    'donation_address'    => $donation,
                    'is_active'           => $is_active ?: 1,
                ),
                array( 'id' => $existing->id ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' ),
                array( '%d' )
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'name'                => $name,
                    'slug'                => $slug,
                    'description'         => $description,
                    'logo_url'            => $logo_url,
                    'website'             => $website,
                    'registration_number' => $reg_number,
                    'donation_address'    => $donation,
                    'is_active'           => $is_active ?: 1,
                    'created_at'          => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
            );
        }
    }

    /**
     * Register the raffle_charity custom post type.
     */
    public static function register_content_types() {
        register_post_type( 'raffle_charity', array(
            'labels' => array(
                'name'          => 'Charities',
                'singular_name' => 'Charity',
                'add_new_item'  => 'Add New Charity',
                'edit_item'     => 'Edit Charity',
                'new_item'      => 'New Charity',
                'view_item'     => 'View Charity',
                'search_items'  => 'Search Charities',
                'not_found'     => 'No charities found',
                'not_found_in_trash' => 'No charities found in Trash',
            ),
            'public'       => true,
            'show_ui'      => true,
            'has_archive'  => true,
            'show_in_menu' => false,
            'menu_icon'    => 'dashicons-heart',
            'supports'     => array( 'title', 'thumbnail' ),
            'rewrite'      => array( 'slug' => 'charity' ),
        ) );
    }

    /* ===================================================================
       Charity CPT Meta Box (preset fields)
       =================================================================== */

    /**
     * Register the charity details meta box on the CPT edit screen.
     */
    public static function add_charity_meta_box() {
        add_meta_box(
            'raffle_charity_details',
            'Charity Details',
            array( __CLASS__, 'render_charity_meta_box' ),
            'raffle_charity',
            'normal',
            'high'
        );
    }

    /**
     * Render the charity details meta box with preset fields.
     */
    public static function render_charity_meta_box( $post ) {
        wp_nonce_field( 'raffle_charity_save', 'raffle_charity_nonce' );

        $description      = get_post_meta( $post->ID, '_charity_description', true );
        $logo_url         = get_post_meta( $post->ID, '_charity_logo_url', true );
        $website          = get_post_meta( $post->ID, '_charity_website', true );
        $reg_number       = get_post_meta( $post->ID, '_charity_registration_number', true );
        $donation_address = get_post_meta( $post->ID, '_charity_donation_address', true );
        $is_active        = get_post_meta( $post->ID, '_charity_is_active', true );
        $is_active        = ( $is_active === '' ) ? '1' : $is_active;

        wp_enqueue_media();
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="charity_description">Description</label></th>
                <td>
                    <textarea name="charity_description" id="charity_description" rows="3" class="large-text" placeholder="Brief description of the charity and its mission..."><?php echo esc_textarea( $description ); ?></textarea>
                    <p class="description">Shown on the public charity directory page.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="charity_logo_url">Logo URL</label></th>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <input type="url" name="charity_logo_url" id="charity_logo_url" value="<?php echo esc_attr( $logo_url ); ?>" class="regular-text" placeholder="https://example.com/logo.png">
                        <button type="button" class="button wpraffle-charity-media-btn">Choose Image</button>
                    </div>
                    <p class="description">Recommended: 200x200px PNG with transparent background.</p>
                    <?php if ( $logo_url ) : ?>
                        <div style="margin-top:8px;"><img src="<?php echo esc_url( $logo_url ); ?>" style="max-width:80px;max-height:80px;border-radius:8px;border:1px solid #ddd;"></div>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="charity_website">Website</label></th>
                <td>
                    <input type="url" name="charity_website" id="charity_website" value="<?php echo esc_attr( $website ); ?>" class="regular-text" placeholder="https://charity.org.uk">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="charity_registration_number">Registration Number</label></th>
                <td>
                    <input type="text" name="charity_registration_number" id="charity_registration_number" value="<?php echo esc_attr( $reg_number ); ?>" class="regular-text" placeholder="e.g. 1234567">
                    <p class="description">UK Charity Commission registration number (shown publicly for transparency).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="charity_donation_address">Donation / Payment Address</label></th>
                <td>
                    <textarea name="charity_donation_address" id="charity_donation_address" rows="2" class="large-text" placeholder="Bank details or payment reference for disbursement..."><?php echo esc_textarea( $donation_address ); ?></textarea>
                    <p class="description">Used internally by the operator for manual disbursement. Never shown publicly.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Status</th>
                <td>
                    <label><input type="checkbox" name="charity_is_active" value="1" <?php checked( $is_active, '1' ); ?>> <strong>Active</strong> (show on public charity directory)</label>
                </td>
            </tr>
        </table>

        <script>
        jQuery(function($){
            $('.wpraffle-charity-media-btn').on('click', function(e){
                e.preventDefault();
                var target = $('#charity_logo_url');
                var frame = wp.media({ title: 'Choose Logo', button: { text: 'Use this image' }, multiple: false });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    target.val(attachment.url);
                });
                frame.open();
            });
        });
        </script>
        <?php
    }

    /**
     * Save the charity meta box data + sync to the raffle_charities DB table.
     */
    public static function save_charity_meta( $post_id ) {
        if ( ! isset( $_POST['raffle_charity_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['raffle_charity_nonce'] ) ), 'raffle_charity_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( get_post_type( $post_id ) !== 'raffle_charity' ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save post meta (preset fields)
        $meta_fields = array(
            '_charity_description'      => array( 'charity_description', 'sanitize_textarea_field' ),
            '_charity_logo_url'         => array( 'charity_logo_url', 'esc_url_raw' ),
            '_charity_website'          => array( 'charity_website', 'esc_url_raw' ),
            '_charity_registration_number' => array( 'charity_registration_number', 'sanitize_text_field' ),
            '_charity_donation_address' => array( 'charity_donation_address', 'sanitize_textarea_field' ),
        );

        foreach ( $meta_fields as $meta_key => $field ) {
            $post_field = $field[0];
            $sanitize   = $field[1];
            $value      = isset( $_POST[ $post_field ] ) ? call_user_func( $sanitize, wp_unslash( $_POST[ $post_field ] ) ) : '';
            update_post_meta( $post_id, $meta_key, $value );
        }

        $is_active = isset( $_POST['charity_is_active'] ) ? 1 : 0;
        update_post_meta( $post_id, '_charity_is_active', $is_active );

        // Sync to the raffle_charities DB table (what the shortcode reads from)
        self::sync_charity_to_db( $post_id );
    }

    /**
     * Sync a charity CPT post to the raffle_charities database table.
     */
    private static function sync_charity_to_db( $post_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_charities';

        $post = get_post( $post_id );
        if ( ! $post ) {
            return;
        }

        $name        = $post->post_title;
        $slug        = $post->post_name ?: sanitize_title( $name );
        $description = get_post_meta( $post_id, '_charity_description', true );
        $logo_url    = get_post_meta( $post_id, '_charity_logo_url', true );
        $website     = get_post_meta( $post_id, '_charity_website', true );
        $reg_number  = get_post_meta( $post_id, '_charity_registration_number', true );
        $donation    = get_post_meta( $post_id, '_charity_donation_address', true );
        $is_active   = (int) get_post_meta( $post_id, '_charity_is_active', true );

        // Check if a row already exists for this slug.
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, total_raised FROM {$table} WHERE slug = %s",
            $slug
        ) );

        if ( $existing ) {
            // Update existing row (preserve total_raised)
            $wpdb->update( $table, array(
                'name'                => $name,
                'description'         => $description,
                'logo_url'            => $logo_url,
                'website'             => $website,
                'registration_number' => $reg_number,
                'donation_address'    => $donation,
                'is_active'           => $is_active,
            ), array( 'id' => $existing->id ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' ), array( '%d' ) );
        } else {
            $wpdb->insert( $table, array(
                'name'                => $name,
                'slug'                => $slug,
                'description'         => $description,
                'logo_url'            => $logo_url,
                'website'             => $website,
                'registration_number' => $reg_number,
                'donation_address'    => $donation,
                'is_active'           => $is_active ?: 1,
                'created_at'          => current_time( 'mysql' ),
            ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ) );
        }
    }
}