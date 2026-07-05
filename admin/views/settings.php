<?php
/**
 * WPRaffle — Unified Settings Page (Tabbed)
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get current tab
$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
$tabs = array(
    'general'  => 'General',
    'pages'    => 'Pages',
    'email'    => 'Email',
    'legal'    => 'Legal',
    'sync'     => 'Sync',
    'sync'     => 'Sync',
    'advanced' => 'Advanced',
    'styling'  => 'Styling',
    'updates'  => 'Updates',
);

// Load settings
$general = wp_parse_args( get_option( 'wpraffle_general_settings', array() ), array(
    'company_name'    => get_bloginfo( 'name' ),
    'company_address' => '',
    'currency_code'   => 'GBP',
    'logo_url'        => '',
    'max_tickets_default' => 100,
) );

$email = wp_parse_args( get_option( 'wpraffle_email_settings', array() ), array(
    'from_name'    => get_bloginfo( 'name' ),
    'from_email'   => get_option( 'admin_email' ),
    'accent_color' => '#6c5ce7',
    'logo_url'     => '',
    'footer_text'  => 'You are receiving this email because you entered a competition on ' . get_bloginfo( 'name' ) . '.',
) );

$advanced = wp_parse_args( get_option( 'wpraffle_advanced_settings', array() ), array(
    'auto_fix_duplicates' => 1,
    'rate_limit_per_minute' => 5,
    'audit_log_days' => 90,
    'enable_audit' => 1,
) );

// Trusted-proxy allowlist (S3) — only honoured in wpraffle_get_client_ip()
// when REMOTE_ADDR matches one of these IPs/CIDRs. Leave blank on shared/MP
// hosting unless you operate your own reverse proxy.
$trusted_proxies = get_option( 'wpraffle_trusted_proxies', '' );

$updates = wp_parse_args( get_option( 'wpraffle_update_settings', array() ), array(
    'github_repo'   => 'wpraffle/wpraffle',
    'auto_update'   => 1,
    'update_channel' => 'stable',
) );

$legal = wp_parse_args( get_option( 'wpraffle_legal_settings', array() ), array(
    'rules_template' => "This competition is open to UK residents aged 18 or over.\n\nYou may enter this competition up to {{max_tickets}} times.\n\nYou will be randomly allocated your ticket number(s) when ordering and you will receive an email confirmation.\n\nThe total amount of tickets for this competition is ({{total_tickets}}).\n\nIf all tickets do not sell out, the draw will happen on {{draw_date}} regardless.\n\nYou may enter the competition online or for free by post by sending your entry to {{company_name}} on a postcard. You must have an account on {{company_name}} for your entry to be processed. All details on your entry MUST correspond to the details on your account to receive the order confirmation and ticket number. Postal entries received without a registered account cannot be processed.\n\nThe live draw will take place on the {{company_name}} Facebook page using an independently verified drawing service (Random Picker) to select the winning ticket number from all Entrants.\n\nThis competition is in no way sponsored, endorsed, administered by or associated with Facebook, Apple or Google. By entering the competitions, Entrants agree that neither Facebook, Apple, nor Google have any liability and are not responsible for the administration or promotion of this competition.",
    'faq_template' => "How many times can I enter this competition?\nYou can enter this competition up to {{max_tickets}} times.\n\nHow do I get my number?\nOnce your order has been placed your ticket number(s) will be randomly allocated and will show on your order confirmation. They will also be emailed to you, and will be available in the my account area.\n\nHow is the winner chosen?\nThe draw is done live on Facebook using an independently verified drawing service (RandomPicker) to determine the winning ticket number. You'll be contacted directly if you have won.\n\nCan the draw date change?\nIf all the entries are sold sooner the draw may be brought forward. Keep updated on the confirmed draw date via our Facebook page and website.\n\nHow do Instant Payouts work?\nWhen you withdraw money from your {{company_name}} Cash Wallet to your bank account, we use an open banking service provided by an FCA (Financial Conduct Authority) Regulated Company (TrueLayer) to instantly send the money to your bank account. No sensitive details are shared with us, and it is completely secure. This means after winning a cash prize on an Instant Win Competition, you can pay yourself out instantly and don't have to wait for a manual payment to be made to you.",
) );

// Page status
$pages = get_option( 'wpraffle_pages', array() );
$saved = isset( $_GET['saved'] ) && $_GET['saved'] === '1';

// Styling settings
$styling = wp_parse_args( get_option( 'wpraffle_styling_settings', array() ), array(
    'preset'                 => 'diamonds',
    'custom_accent'          => '',
    'custom_accent_dark'     => '',
    'custom_text'            => '',
    'custom_bg'              => '',
    'disable_custom_styling' => '0',
) );

// Test email status
$test_sent   = isset( $_GET['test'] ) && $_GET['test'] === 'sent';
$test_failed = isset( $_GET['test'] ) && $_GET['test'] === 'failed';

// Check for updates
$latest_version = get_transient( 'wpraffle_latest_version' );
$update_available = $latest_version && version_compare( $latest_version, RAFFLE_SYSTEM_VERSION, '>' );
?>
<div class="wrap rs-wrap">

    <div class="rs-page-header">
        <div>
            <h1 class="rs-page-title">
                <svg class="wpr-icon wpr-icon--xl" style="color:#6c5ce7;margin-right:10px;"><use href="#wpr-settings"></use></svg>
                Settings
            </h1>
            <p class="rs-page-subtitle">Configure WPRaffle — general, pages, email, and advanced options.</p>
        </div>
    </div>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible"><p><strong>Settings saved.</strong></p></div>
    <?php endif; ?>
    <?php if ( $test_sent ) : ?>
        <div class="notice notice-success is-dismissible"><p><strong>Test email sent!</strong> Check your inbox at <code><?php echo esc_html( get_option( 'admin_email' ) ); ?></code>.</p></div>
    <?php endif; ?>
    <?php if ( $test_failed ) : ?>
        <div class="notice notice-error is-dismissible"><p><strong>Test email failed to send.</strong> Check your WordPress mail configuration (e.g. install WP Mail SMTP).</p></div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom:24px;">
        <?php foreach ( $tabs as $key => $label ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpraffle-settings&tab=' . $key ) ); ?>"
               class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- ════════════════════ GENERAL TAB ════════════════════ -->
    <?php if ( $tab === 'general' ) : ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'wpraffle_save_settings', 'wpraffle_settings_nonce' ); ?>
        <input type="hidden" name="action" value="wpraffle_save_general_settings">

        <div class="rs-card" style="margin-bottom:20px;">
            <h2 class="rs-card-title">Company Information</h2>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row"><label for="company_name">Company Name</label></th>
                    <td>
                        <input type="text" id="company_name" name="company_name" value="<?php echo esc_attr( $general['company_name'] ); ?>" class="regular-text">
                        <p class="description">Used in email templates, legal text, and postal entry instructions.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="company_address">Company Address</label></th>
                    <td>
                        <textarea id="company_address" name="company_address" rows="3" class="large-text"><?php echo esc_textarea( $general['company_address'] ); ?></textarea>
                        <p class="description">Required for UK raffle regulations. Displayed in postal entry instructions and email footers.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="logo_url">Site Logo URL</label></th>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <input type="url" id="general_logo_url" name="logo_url" value="<?php echo esc_attr( $general['logo_url'] ); ?>" class="regular-text" placeholder="https://example.com/logo.png">
                            <button type="button" class="button wpraffle-media-btn" data-target="general_logo_url">Choose Image</button>
                        </div>
                        <p class="description">Used for Open Graph tags and email templates. Recommended: 360×100px PNG.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="rs-card" style="margin-bottom:20px;">
            <h2 class="rs-card-title">Defaults</h2>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row"><label for="currency_code">Currency Code</label></th>
                    <td>
                        <select id="currency_code" name="currency_code">
                            <?php
                            $currencies = array( 'GBP', 'USD', 'EUR', 'COP' );
                            foreach ( $currencies as $c ) : ?>
                                <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $general['currency_code'], $c ); ?>><?php echo esc_html( $c ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Winners Page — Tab Visibility</th>
                    <td>
                        <fieldset>
                            <label style="display:block;margin-bottom:8px;"><input type="checkbox" name="winners_show_live_draw" value="1" <?php checked( $general['winners_show_live_draw'] ?? 1, 1 ); ?>> <strong>Live Draw</strong> — manually drawn competitions</label>
                            <label style="display:block;margin-bottom:8px;"><input type="checkbox" name="winners_show_auto_draw" value="1" <?php checked( $general['winners_show_auto_draw'] ?? 1, 1 ); ?>> <strong>Auto-Draw</strong> — automatically drawn competitions</label>
                            <label style="display:block;margin-bottom:8px;"><input type="checkbox" name="winners_show_instant_wins" value="1" <?php checked( $general['winners_show_instant_wins'] ?? 1, 1 ); ?>> <strong>Instant Wins</strong> — all instant win prizes grouped by date</label>
                        </fieldset>
                        <p class="description">Control which tabs appear on the winners page. At least one tab must be enabled. Uses the <code>[raffle_entry_list]</code> shortcode.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="max_tickets_default">Default Max Tickets Per User</label></th>
                    <td>
                        <input type="number" id="max_tickets_default" name="max_tickets_default" value="<?php echo esc_attr( $general['max_tickets_default'] ); ?>" min="1" max="9999" class="small-text">
                        <p class="description">Default limit applied to new raffles. Can be overridden per raffle.</p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( 'Save General Settings', 'primary' ); ?>
    </form>

    <!-- ════════════════════ PAGES TAB ════════════════════ -->
    <?php elseif ( $tab === 'pages' ) : ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'wpraffle_save_pages', 'wpraffle_pages_nonce' ); ?>
        <input type="hidden" name="action" value="wpraffle_save_pages">

        <div class="rs-card" style="margin-bottom:20px;">
            <h2 class="rs-card-title">Page Assignments</h2>
            <p class="description" style="margin-bottom:16px;">Select which WordPress pages are used for each raffle feature. You can pick an existing page or create a new one.</p>

            <table class="widefat striped" style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:18%;">Feature</th>
                        <th style="width:22%;">Shortcode / Type</th>
                        <th>Assigned Page</th>
                        <th style="width:20%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $page_configs = array(
                        'raffles'     => array( 'title' => 'Raffles',         'shortcode' => '[raffle_list]',      'type' => 'page' ),
                        'ended'       => array( 'title' => 'Past Raffles',    'shortcode' => '[raffle_ended_list]', 'type' => 'page' ),
                        'entry_list'  => array( 'title' => 'Entry Lists',     'shortcode' => '[raffle_entry_list]', 'type' => 'page' ),
                        'live_draw'   => array( 'title' => 'Live Draw',       'shortcode' => '[raffle_live_draw]',  'type' => 'page' ),
                        'charities'   => array( 'title' => 'Charities',       'shortcode' => '[raffle_charities]',  'type' => 'page' ),
                    );
                    foreach ( $page_configs as $key => $cfg ) :
                        $page_id = isset( $pages[ $key ] ) ? (int) $pages[ $key ] : 0;
                        $page_exists = $page_id && get_post( $page_id );
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $cfg['title'] ); ?></strong></td>
                        <td><code><?php echo esc_html( $cfg['shortcode'] ); ?></code></td>
                        <td>
                            <?php
                            wp_dropdown_pages( array(
                                'name'             => 'wpraffle_page_' . $key,
                                'id'               => 'wpraffle_page_' . $key,
                                'selected'         => $page_id ?: 0,
                                'show_option_none' => '— Select a page —',
                                'option_none_value' => '0',
                                'post_status'      => 'publish,private,draft',
                            ) );
                            ?>
                        </td>
                        <td>
                            <?php if ( $page_exists ) : ?>
                                <a href="<?php echo esc_url( get_edit_post_link( $page_id ) ); ?>" class="button button-small">Edit</a>
                                <a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" class="button button-small" target="_blank">View</a>
                            <?php else : ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                                    <?php wp_nonce_field( 'wpraffle_create_page', 'wpraffle_page_nonce' ); ?>
                                    <input type="hidden" name="action" value="wpraffle_create_page">
                                    <input type="hidden" name="page_key" value="<?php echo esc_attr( $key ); ?>">
                                    <button type="submit" class="button button-small">Create Page</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background:#f0fdf4;">
                        <td><strong>My Raffles</strong></td>
                        <td><code>WooCommerce Endpoint</code></td>
                        <td colspan="2">
                            <span style="color:#16a34a;font-weight:600;">✓ Automatically added to My Account</span>
                            <p class="description" style="margin-top:4px;">Appears as a tab under <strong>My Account</strong> at <code>/my-account/my-raffles/</code>. No page assignment needed — it's registered via <code>add_rewrite_endpoint()</code>.</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button( 'Save Page Assignments', 'primary' ); ?>
        </div>
    </form>

    <div class="rs-card" style="margin-bottom:20px;">
        <h2 class="rs-card-title">Shortcode Reference</h2>
        <p class="description" style="margin-bottom:16px;">Use these shortcodes in any page or post to display raffle content.</p>
        <table class="widefat striped" style="width:100%;">
            <thead>
                <tr><th style="width:20%;">Shortcode</th><th>Description</th><th style="width:35%;">Attributes</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[raffle id="X"]</code></td>
                    <td>Display a single raffle with full details, ticket selection, countdown, and purchase button. Replace <strong>X</strong> with the raffle ID.</td>
                    <td><code>id</code> — The raffle ID <em>(required)</em></td>
                </tr>
                <tr>
                    <td><code>[raffle_list]</code></td>
                    <td>Display a responsive grid of raffles. Shows live raffles by default; use the <code>status</code> attribute to filter.</td>
                    <td><code>status</code> — <code>active</code> <em>(default)</em>, <code>finished</code>, <code>draft</code>, or <code>all</code></td>
                </tr>
                <tr>
                    <td><code>[raffle_ended_list]</code></td>
                    <td>Display a dedicated page showing all ended/finished competitions, total entries, winners, instant wins, and draw verification links.</td>
                    <td>
                        <code>columns</code> — Grid columns <em>(default: 3)</em><br>
                        <code>show_image</code> — <code>yes</code> / <code>no</code><br>
                        <code>show_winner</code> — <code>yes</code> / <code>no</code><br>
                        <code>show_instant</code> — <code>yes</code> / <code>no</code><br>
                        <code>show_video_btn</code> — <code>yes</code> / <code>no</code><br>
                        <code>show_verified_btn</code> — <code>yes</code> / <code>no</code><br>
                        <code>show_date</code> — <code>yes</code> / <code>no</code><br>
                        <code>show_entries</code> — <code>yes</code> / <code>no</code>
                    </td>
                </tr>
                <tr>
                    <td><code>[raffle_lookup]</code></td>
                    <td>Display a ticket lookup form where users can enter their email to find their purchased tickets. Logged-in users are redirected to their account dashboard instead.</td>
                    <td><em>None</em></td>
                </tr>
                <tr>
                    <td><code>[raffle_live_draw raffle_id="X"]</code></td>
                    <td>Display an animated live draw page for a raffle. Shows a slot-machine style draw with a "DRAW WINNER" button. Admin only — the actual draw requires admin permissions.</td>
                    <td><code>raffle_id</code> — The raffle ID <em>(required)</em></td>
                </tr>
                <tr>
                    <td><code>[raffle_charities]</code></td>
                    <td>Display a directory of all active charities with logos, descriptions, registration numbers, and total raised through competitions.</td>
                    <td><code>columns</code> — Grid columns <em>(default: 3)</em></td>
                </tr>
                <tr>
                    <td><code>[raffle_entry_list]</code></td>
                    <td>Display all closed/ended competitions with a download button for each, allowing customers to download the full entry list as a PDF file.</td>
                    <td>
                        <code>layout</code> — <code>grid</code> / <code>list</code> <em>(default: grid)</em><br>
                        <code>columns</code> — Grid columns <em>(default: 2, only used when layout=grid)</em><br>
                        <code>button_text</code> — Button label <em>(default: "Download Entry List")</em><br>
                        <code>button_bg</code> — Button background colour <em>(default: #1e40af)</em><br>
                        <code>button_color</code> — Button text colour <em>(default: #ffffff)</em><br>
                        <code>button_radius</code> — Button border radius <em>(default: 8)</em><br>
                        <code>show_image</code> — <code>yes</code> / <code>no</code>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="rs-card">
        <h2 class="rs-card-title">Elementor Widgets</h2>
        <p class="description" style="margin-bottom:16px;">If Elementor is active, these custom widgets are available for building raffle pages:</p>
        <table class="widefat striped" style="width:100%;">
            <thead>
                <tr><th style="width:25%;">Widget</th><th>Description</th></tr>
            </thead>
            <tbody>
                <tr><td>Raffle Title</td><td>Raffle title with styling options</td></tr>
                <tr><td>Raffle Image</td><td>Prize image with lightbox</td></tr>
                <tr><td>Raffle Price</td><td>Ticket price or prize value</td></tr>
                <tr><td>Raffle Progress</td><td>Sales progress bar with stats</td></tr>
                <tr><td>Raffle Countdown</td><td>Live countdown timer to draw date</td></tr>
                <tr><td>Raffle Quantity Selector</td><td>Package selection grid</td></tr>
                <tr><td>Raffle Enter Button</td><td>CTA button to enter the raffle</td></tr>
                <tr><td>Raffle Description</td><td>Raffle description text</td></tr>
                <tr><td>Raffle Stats Header</td><td>Key stats (sold, remaining, price)</td></tr>
                <tr><td>Raffle Tabs</td><td>Tabbed content (description, instant wins, question)</td></tr>
                <tr><td>Raffle Instant Wins</td><td>Instant win prizes grid</td></tr>
                <tr><td>Raffle Question</td><td>Skill question form</td></tr>
                <tr><td>Raffle Trust Badge</td><td>Trust/verification badge</td></tr>
                <tr><td>Raffle Full Page</td><td>Complete raffle layout (all-in-one)</td></tr>
                <tr><td>Raffle Modal</td><td>Purchase/entry modal</td></tr>
                <tr><td>Entry List Downloads</td><td>Closed competition entry list download grid with customisable buttons</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Shortcode Customisation -->
    <div class="rs-card" style="margin-bottom:20px;">
        <h2 class="rs-card-title">Shortcode Customisation</h2>
        <p class="description" style="margin-bottom:16px;">Override default shortcode attributes from here instead of editing pages. Enable the toggle, then adjust the fields. Inline shortcode attributes will still override these settings.</p>

        <?php
        $sc_settings = wp_parse_args( get_option( 'wpraffle_shortcode_settings', array() ), array(
            'raffle_ended_list' => array(),
            'raffle_entry_list' => array(),
            'raffle_list'       => array(),
        ) );

        // Define configurable shortcodes and their fields
        $shortcodes_config = array(
            'raffle_ended_list' => array(
                'label'   => 'Winners / Ended Raffles',
                'code'    => '[raffle_ended_list]',
                'fields'  => array(
                    'columns'          => array( 'label' => 'Grid Columns',       'type' => 'number', 'default' => 3,    'min' => 1, 'max' => 6 ),
                    'show_image'       => array( 'label' => 'Prize Image',        'type' => 'yesno',  'default' => 'yes' ),
                    'show_winner'      => array( 'label' => 'Winner Box',         'type' => 'yesno',  'default' => 'yes' ),
                    'show_video_btn'   => array( 'label' => 'Watch Draw Button',  'type' => 'yesno',  'default' => 'yes' ),
                    'show_verified_btn'=> array( 'label' => 'Verified Draw Button','type' => 'yesno',  'default' => 'yes' ),
                    'show_date'        => array( 'label' => 'Draw Date Badge',    'type' => 'yesno',  'default' => 'yes' ),
                    'show_entries'     => array( 'label' => 'Entry Count',        'type' => 'yesno',  'default' => 'yes' ),
                ),
            ),
            'raffle_entry_list' => array(
                'label'   => 'Entry List Downloads',
                'code'    => '[raffle_entry_list]',
                'fields'  => array(
                    'layout'        => array( 'label' => 'Layout',              'type' => 'select', 'default' => 'grid', 'options' => array( 'grid' => 'Grid', 'list' => 'List' ) ),
                    'columns'       => array( 'label' => 'Grid Columns',        'type' => 'number', 'default' => 2,     'min' => 1, 'max' => 4 ),
                    'button_text'   => array( 'label' => 'Button Text',         'type' => 'text',   'default' => 'Download Entry List' ),
                    'button_bg'     => array( 'label' => 'Button Background',   'type' => 'color',  'default' => '#1e40af' ),
                    'button_color'  => array( 'label' => 'Button Text Colour',  'type' => 'color',  'default' => '#ffffff' ),
                    'button_radius' => array( 'label' => 'Button Border Radius','type' => 'number', 'default' => 8,     'min' => 0, 'max' => 50 ),
                    'show_image'    => array( 'label' => 'Prize Image',         'type' => 'yesno',  'default' => 'yes' ),
                ),
            ),
            'raffle_list' => array(
                'label'   => 'Raffle List / Shop',
                'code'    => '[raffle_list]',
                'fields'  => array(
                    'status' => array( 'label' => 'Default Status Filter', 'type' => 'select', 'default' => 'active', 'options' => array( 'active' => 'Active (Live)', 'finished' => 'Finished', 'draft' => 'Draft', 'all' => 'All' ) ),
                ),
            ),
        );

        // Render in a form
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'wpraffle_save_shortcode_settings', 'wpraffle_sc_nonce' ); ?>
            <input type="hidden" name="action" value="wpraffle_save_shortcode_settings">

            <?php foreach ( $shortcodes_config as $sc_key => $sc_cfg ) :
                $sc_vals = isset( $sc_settings[ $sc_key ] ) ? $sc_settings[ $sc_key ] : array();
                $enabled = ! empty( $sc_vals['enabled'] );
            ?>
            <div class="rs-card" style="margin-bottom:16px;border:1px solid <?php echo $enabled ? '#6c5ce7' : '#e5e7eb'; ?>;border-radius:12px;overflow:hidden;">
                <!-- Header / Toggle -->
                <div class="wpraffle-sc-toggle" data-target="sc-panel-<?php echo esc_attr( $sc_key ); ?>" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;cursor:pointer;background:<?php echo $enabled ? '#f5f3ff' : '#f9fafb'; ?>;border-bottom:1px solid <?php echo $enabled ? '#e0e7ff' : '#e5e7eb'; ?>;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="font-weight:700;font-size:15px;color:#1f2937;"><?php echo esc_html( $sc_cfg['label'] ); ?></span>
                        <code style="font-size:12px;background:#f3f4f6;padding:2px 8px;border-radius:4px;color:#6b7280;"><?php echo esc_html( $sc_cfg['code'] ); ?></code>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="font-size:12px;color:<?php echo $enabled ? '#6c5ce7' : '#9ca3af'; ?>;font-weight:600;"><?php echo $enabled ? 'Customised' : 'Default'; ?></span>
                        <label style="position:relative;display:inline-block;width:44px;height:24px;margin:0;">
                            <input type="checkbox" name="sc_<?php echo esc_attr( $sc_key ); ?>_enabled" value="1" <?php checked( $enabled ); ?> style="opacity:0;width:0;height:0;" class="wpraffle-sc-switch">
                            <span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:<?php echo $enabled ? '#6c5ce7' : '#d1d5db'; ?>;border-radius:24px;transition:.3s;"></span>
                            <span style="position:absolute;height:18px;width:18px;left:<?php echo $enabled ? '22px' : '3px'; ?>;bottom:3px;background:#fff;border-radius:50%;transition:.3s;box-shadow:0 1px 3px rgba(0,0,0,0.2);"></span>
                        </label>
                    </div>
                </div>

                <!-- Settings Panel -->
                <div class="wpraffle-sc-panel" id="sc-panel-<?php echo esc_attr( $sc_key ); ?>" style="<?php echo $enabled ? '' : 'display:none;'; ?>padding:20px;">
                    <table class="form-table" style="margin:0;">
                        <?php foreach ( $sc_cfg['fields'] as $field_key => $field ) :
                            $val = isset( $sc_vals[ $field_key ] ) ? $sc_vals[ $field_key ] : $field['default'];
                        ?>
                        <tr>
                            <th scope="row" style="width:200px;padding:10px 0;">
                                <label><?php echo esc_html( $field['label'] ); ?></label>
                            </th>
                            <td style="padding:10px 0;">
                                <?php if ( $field['type'] === 'yesno' ) : ?>
                                    <select name="sc_<?php echo esc_attr( $sc_key ); ?>_<?php echo esc_attr( $field_key ); ?>">
                                        <option value="yes" <?php selected( $val, 'yes' ); ?>>Yes</option>
                                        <option value="no" <?php selected( $val, 'no' ); ?>>No</option>
                                    </select>
                                <?php elseif ( $field['type'] === 'number' ) : ?>
                                    <input type="number" name="sc_<?php echo esc_attr( $sc_key ); ?>_<?php echo esc_attr( $field_key ); ?>" value="<?php echo esc_attr( $val ); ?>" min="<?php echo esc_attr( $field['min'] ?? 0 ); ?>" max="<?php echo esc_attr( $field['max'] ?? 100 ); ?>" class="small-text">
                                    <span style="color:#9ca3af;font-size:12px;margin-left:6px;">default: <?php echo esc_html( $field['default'] ); ?></span>
                                <?php elseif ( $field['type'] === 'color' ) : ?>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <input type="color" name="sc_<?php echo esc_attr( $sc_key ); ?>_<?php echo esc_attr( $field_key ); ?>" value="<?php echo esc_attr( $val ); ?>" style="width:40px;height:32px;border:1px solid #ddd;border-radius:6px;cursor:pointer;padding:2px;">
                                        <input type="text" value="<?php echo esc_attr( $val ); ?>" class="small-text" style="font-family:monospace;" readonly>
                                    </div>
                                <?php elseif ( $field['type'] === 'select' ) : ?>
                                    <select name="sc_<?php echo esc_attr( $sc_key ); ?>_<?php echo esc_attr( $field_key ); ?>">
                                        <?php foreach ( $field['options'] as $opt_val => $opt_label ) : ?>
                                            <option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $val, $opt_val ); ?>><?php echo esc_html( $opt_label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ( $field['type'] === 'text' ) : ?>
                                    <input type="text" name="sc_<?php echo esc_attr( $sc_key ); ?>_<?php echo esc_attr( $field_key ); ?>" value="<?php echo esc_attr( $val ); ?>" class="regular-text">
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>

            <?php submit_button( 'Save Shortcode Settings', 'primary' ); ?>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Shortcode toggle panels
        document.querySelectorAll('.wpraffle-sc-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                // Don't toggle if clicking the switch itself
                if (e.target.closest('.wpraffle-sc-switch')) return;
                var targetId = toggle.getAttribute('data-target');
                var panel = document.getElementById(targetId);
                if (panel) {
                    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
                }
            });
        });

        // Toggle switch UI + panel visibility
        document.querySelectorAll('.wpraffle-sc-switch').forEach(function(sw) {
            sw.addEventListener('change', function() {
                var wrapper = sw.closest('.rs-card');
                var panel = wrapper.querySelector('.wpraffle-sc-panel');
                var header = wrapper.querySelector('.wpraffle-sc-toggle');
                var indicator = header.querySelector('span[style*="font-weight:600"]');
                var track = sw.nextElementSibling;
                var knob = track.nextElementSibling;

                if (sw.checked) {
                    panel.style.display = 'block';
                    header.style.background = '#f5f3ff';
                    header.style.borderBottomColor = '#e0e7ff';
                    wrapper.style.borderColor = '#6c5ce7';
                    track.style.background = '#6c5ce7';
                    knob.style.left = '22px';
                    if (indicator) { indicator.textContent = 'Customised'; indicator.style.color = '#6c5ce7'; }
                } else {
                    panel.style.display = 'none';
                    header.style.background = '#f9fafb';
                    header.style.borderBottomColor = '#e5e7eb';
                    wrapper.style.borderColor = '#e5e7eb';
                    track.style.background = '#d1d5db';
                    knob.style.left = '3px';
                    if (indicator) { indicator.textContent = 'Default'; indicator.style.color = '#9ca3af'; }
                }
            });
        });
    });
    </script>

    <!-- ════════════════════ EMAIL TAB ════════════════════ -->
    <?php elseif ( $tab === 'email' ) : ?>
    <div style="display:grid;grid-template-columns:1fr 380px;gap:28px;align-items:start;">

        <div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wpraffle-email-settings-form">
                <?php wp_nonce_field( 'wpraffle_save_email_settings', 'wpraffle_email_nonce' ); ?>
                <input type="hidden" name="action" value="wpraffle_save_email_settings">

                <div class="rs-card" style="margin-bottom:20px;">
                    <h2 class="rs-card-title">Sender Details</h2>
                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th scope="row"><label for="from_name">From Name</label></th>
                            <td>
                                <input type="text" id="from_name" name="from_name" value="<?php echo esc_attr( $email['from_name'] ); ?>" class="regular-text">
                                <p class="description">The name that appears in the "From" field of all emails.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="from_email">From Email</label></th>
                            <td>
                                <input type="email" id="from_email" name="from_email" value="<?php echo esc_attr( $email['from_email'] ); ?>" class="regular-text">
                                <p class="description">The reply-to address. Use an address at your own domain for best deliverability.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="rs-card" style="margin-bottom:20px;">
                    <h2 class="rs-card-title">Email Branding</h2>
                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th scope="row"><label for="accent_color">Accent Colour</label></th>
                            <td>
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <input type="color" id="accent_color" name="accent_color" value="<?php echo esc_attr( $email['accent_color'] ); ?>" style="width:50px;height:36px;border:1px solid #ddd;border-radius:6px;padding:2px;cursor:pointer;">
                                    <input type="text" id="accent_color_hex" value="<?php echo esc_attr( $email['accent_color'] ); ?>" class="small-text" style="font-family:monospace;" readonly>
                                </div>
                                <p class="description">Used for the email header gradient, buttons, and ticket number highlights.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email_logo_url">Logo URL</label></th>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <input type="url" id="email_logo_url" name="logo_url" value="<?php echo esc_attr( $email['logo_url'] ); ?>" class="regular-text" placeholder="https://example.com/logo.png">
                                    <button type="button" class="button wpraffle-media-btn" data-target="email_logo_url">Choose Image</button>
                                </div>
                                <p class="description">Your logo displayed in the email header. Recommended: 360x100px PNG with transparent background.</p>
                                <?php if ( $email['logo_url'] ) : ?>
                                    <div style="margin-top:10px;padding:10px;background:#f9f9f9;border:1px solid #e5e7eb;border-radius:8px;display:inline-block;">
                                        <img src="<?php echo esc_url( $email['logo_url'] ); ?>" style="max-height:50px;max-width:200px;display:block;">
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="footer_text">Footer Text</label></th>
                            <td>
                                <textarea id="footer_text" name="footer_text" rows="3" class="large-text"><?php echo esc_textarea( $email['footer_text'] ); ?></textarea>
                                <p class="description">Shown in the footer of every email.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button( 'Save Email Settings', 'primary' ); ?>
            </form>
        </div>

        <!-- Right sidebar -->
        <div style="position:sticky;top:32px;">
            <div class="rs-card" style="margin-bottom:20px;">
                <h2 class="rs-card-title">Automated Email Types</h2>
                <ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:10px;">
                    <?php
                    $email_types = array(
                        array( 'icon' => 'ticket', 'label' => 'Purchase Confirmation',  'desc' => 'Sent immediately after a successful order.' ),
                        array( 'icon' => 'trophy', 'label' => 'Winner Notification',    'desc' => 'Sent to the winner when the draw is conducted.' ),
                        array( 'icon' => 'zap',    'label' => 'Instant Win Alert',      'desc' => 'Sent when a ticket wins an instant prize.' ),
                        array( 'icon' => 'clock',  'label' => 'Draw Reminder',          'desc' => 'Sent to all entrants 24h before the draw date.' ),
                        array( 'icon' => 'alert',  'label' => 'Sold Out Admin Alert',   'desc' => 'Sent to admin when all tickets are sold.' ),
                    );
                    foreach ( $email_types as $et ) : ?>
                        <li style="display:flex;align-items:flex-start;gap:12px;padding:12px;background:#f9fafb;border-radius:8px;border:1px solid #f3f4f6;">
                            <svg class="wpr-icon wpr-icon--md" style="color:#6c5ce7;flex-shrink:0;margin-top:2px;"><use href="#wpr-<?php echo esc_attr( $et['icon'] ); ?>"></use></svg>
                            <div>
                                <div style="font-weight:700;font-size:13px;color:#1f2937;"><?php echo esc_html( $et['label'] ); ?></div>
                                <div style="font-size:12px;color:#6b7280;margin-top:2px;"><?php echo esc_html( $et['desc'] ); ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="rs-card">
                <h2 class="rs-card-title">Send Test Email</h2>
                <p style="font-size:13px;color:#6b7280;margin:0 0 16px;">Send a test email to verify your settings and preview the template.</p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'wpraffle_test_email', 'wpraffle_test_nonce' ); ?>
                    <input type="hidden" name="action" value="wpraffle_send_test_email">
                    <input type="email" name="test_email_to" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="regular-text" style="width:100%;margin-bottom:12px;">
                    <?php submit_button( 'Send Test Email', 'secondary', 'submit', false, array( 'style' => 'width:100%' ) ); ?>
                </form>
            </div>
        </div>
    </div>

    <!-- ════════════════════ LEGAL TAB ════════════════════ -->
    <?php elseif ( $tab === 'legal' ) : ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'wpraffle_save_settings', 'wpraffle_settings_nonce' ); ?>
        <input type="hidden" name="action" value="wpraffle_save_legal_settings">

        <div class="rs-card" style="margin-bottom:20px;">
            <h2 class="rs-card-title">Competition Rules Template</h2>
            <p class="description" style="margin-bottom:12px;">This text is displayed in the "Raffle Rules" accordion on every competition. Use placeholders to auto-fill raffle-specific details.</p>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row"><label for="rules_template">Rules Text</label></th>
                    <td>
                        <textarea id="rules_template" name="rules_template" rows="12" class="large-text" style="font-size:13px;line-height:1.6;"><?php echo esc_textarea( $legal['rules_template'] ); ?></textarea>
                    </td>
                </tr>
            </table>
        </div>

        <div class="rs-card" style="margin-bottom:20px;">
            <h2 class="rs-card-title">FAQ Items</h2>
            <p class="description" style="margin-bottom:12px;">Each item is a question & answer pair displayed in the "Frequently Asked Questions" accordion. Add, edit, or remove items individually.</p>
            <div id="wpraffle-faq-items">
                <?php
                // Parse existing FAQ items (from new array or legacy text)
                $faq_items = array();
                if ( ! empty( $legal['faq_items'] ) ) {
                    $faq_items = is_string( $legal['faq_items'] ) ? json_decode( $legal['faq_items'], true ) : $legal['faq_items'];
                }
                if ( empty( $faq_items ) && ! empty( $legal['faq_template'] ) ) {
                    $faq_items = wpraffle_parse_faq( $legal['faq_template'] );
                }
                if ( ! empty( $faq_items ) ) :
                    foreach ( $faq_items as $i => $item ) :
                ?>
                <div class="wpraffle-faq-row" style="display:flex;gap:12px;align-items:flex-start;margin-bottom:12px;padding:14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;">
                    <div style="flex:1;min-width:0;">
                        <label style="font-weight:600;font-size:12px;color:#374151;display:block;margin-bottom:4px;">Question</label>
                        <input type="text" name="faq_questions[]" value="<?php echo esc_attr( $item['q'] ?? '' ); ?>" class="regular-text" style="width:100%;" placeholder="e.g. How many times can I enter?">
                    </div>
                    <div style="flex:2;min-width:0;">
                        <label style="font-weight:600;font-size:12px;color:#374151;display:block;margin-bottom:4px;">Answer</label>
                        <textarea name="faq_answers[]" rows="3" class="large-text" style="width:100%;font-size:13px;line-height:1.5;" placeholder="Enter the answer..."><?php echo esc_textarea( $item['a'] ?? '' ); ?></textarea>
                    </div>
                    <button type="button" class="button wpraffle-faq-remove" style="margin-top:20px;flex-shrink:0;color:#dc2626;" title="Remove this FAQ item">✕</button>
                </div>
                <?php endforeach; else : ?>
                <div class="wpraffle-faq-row" style="display:flex;gap:12px;align-items:flex-start;margin-bottom:12px;padding:14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;">
                    <div style="flex:1;min-width:0;">
                        <label style="font-weight:600;font-size:12px;color:#374151;display:block;margin-bottom:4px;">Question</label>
                        <input type="text" name="faq_questions[]" value="" class="regular-text" style="width:100%;" placeholder="e.g. How many times can I enter?">
                    </div>
                    <div style="flex:2;min-width:0;">
                        <label style="font-weight:600;font-size:12px;color:#374151;display:block;margin-bottom:4px;">Answer</label>
                        <textarea name="faq_answers[]" rows="3" class="large-text" style="width:100%;font-size:13px;line-height:1.5;" placeholder="Enter the answer..."></textarea>
                    </div>
                    <button type="button" class="button wpraffle-faq-remove" style="margin-top:20px;flex-shrink:0;color:#dc2626;" title="Remove this FAQ item">✕</button>
                </div>
                <?php endif; ?>
            </div>
            <button type="button" class="button" id="wpraffle-faq-add" style="margin-top:8px;">+ Add FAQ Item</button>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var container = document.getElementById('wpraffle-faq-items');
            var addBtn = document.getElementById('wpraffle-faq-add');
            if (!container || !addBtn) return;

            addBtn.addEventListener('click', function() {
                var row = document.createElement('div');
                row.className = 'wpraffle-faq-row';
                row.style.cssText = 'display:flex;gap:12px;align-items:flex-start;margin-bottom:12px;padding:14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;';
                row.innerHTML = '<div style="flex:1;min-width:0;"><label style="font-weight:600;font-size:12px;color:#374151;display:block;margin-bottom:4px;">Question</label><input type="text" name="faq_questions[]" value="" class="regular-text" style="width:100%;" placeholder="e.g. How many times can I enter?"></div><div style="flex:2;min-width:0;"><label style="font-weight:600;font-size:12px;color:#374151;display:block;margin-bottom:4px;">Answer</label><textarea name="faq_answers[]" rows="3" class="large-text" style="width:100%;font-size:13px;line-height:1.5;" placeholder="Enter the answer..."></textarea></div><button type="button" class="button wpraffle-faq-remove" style="margin-top:20px;flex-shrink:0;color:#dc2626;" title="Remove this FAQ item">✕</button>';
                container.appendChild(row);
            });

            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('wpraffle-faq-remove')) {
                    var rows = container.querySelectorAll('.wpraffle-faq-row');
                    if (rows.length > 1) {
                        e.target.closest('.wpraffle-faq-row').remove();
                    } else {
                        alert('You must have at least one FAQ item.');
                    }
                }
            });
        });
        </script>

        <div class="rs-card" style="margin-bottom:20px;">
            <h2 class="rs-card-title">Available Placeholders</h2>
            <p class="description" style="margin-bottom:12px;">These are automatically replaced with raffle-specific values on the front-end:</p>
            <table class="widefat striped" style="max-width:600px;">
                <thead><tr><th>Placeholder</th><th>Replaced With</th></tr></thead>
                <tbody>
                    <tr><td><code>{{max_tickets}}</code></td><td>Max entries per user for this raffle</td></tr>
                    <tr><td><code>{{total_tickets}}</code></td><td>Total number of tickets available</td></tr>
                    <tr><td><code>{{draw_date}}</code></td><td>Formatted draw date (e.g. "June 8, 2026")</td></tr>
                    <tr><td><code>{{company_name}}</code></td><td>Your company name from General settings</td></tr>
                    <tr><td><code>{{ticket_price}}</code></td><td>Price per entry</td></tr>
                    <tr><td><code>{{prize_description}}</code></td><td>Raffle title / prize name</td></tr>
                </tbody>
            </table>
        </div>

        <?php submit_button( 'Save Legal Settings', 'primary' ); ?>
    </form>

    <!-- ════════════════════ ADVANCED TAB ════════════════════ -->
    <!-- SNC TAB -->
    <?php elseif ( $tab === 'sync' ) : ?>

    <?php
    global $wpdb;
    $raffles_table = $wpdb->prefix . 'raffles';
    $sync_results  = array();
    $sync_done     = false;

    // Handle sync all
    if ( isset( $_GET['sync_all'] ) && check_admin_referer( 'wpraffle_sync_all', 'sync_nonce' ) ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do that.', 'wpraffle' ) );
        }
        $all_raffles = $wpdb->get_results( "SELECT * FROM {$raffles_table}" );
        foreach ( $all_raffles as $r ) {
            if ( ! $r->wc_product_id ) continue;
            $product = get_post( $r->wc_product_id );
            if ( ! $product ) continue;

            $wc_status = $r->status === 'draft' ? 'draft' : 'publish';
            wp_update_post( array(
                'ID'          => $r->wc_product_id,
                'post_title'  => $r->title,
                'post_status' => $wc_status,
            ) );
            update_post_meta( $r->wc_product_id, '_regular_price', $r->ticket_price );
            update_post_meta( $r->wc_product_id, '_price', $r->ticket_price );
            update_post_meta( $r->wc_product_id, '_raffle_id', $r->id );
            update_post_meta( $r->wc_product_id, '_raffle_status', $r->status );
        }
        $sync_done = true;
    }

    // Handle individual sync
    if ( isset( $_GET['sync_raffle'] ) && check_admin_referer( 'wpraffle_sync_single', 'sync_nonce' ) ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do that.', 'wpraffle' ) );
        }
        $sync_id = absint( $_GET['sync_raffle'] );
        $r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$raffles_table} WHERE id = %d", $sync_id ) );
        if ( $r && $r->wc_product_id ) {
            $product = get_post( $r->wc_product_id );
            if ( $product ) {
                $wc_status = $r->status === 'draft' ? 'draft' : 'publish';
                wp_update_post( array(
                    'ID'          => $r->wc_product_id,
                    'post_title'  => $r->title,
                    'post_status' => $wc_status,
                ) );
                update_post_meta( $r->wc_product_id, '_regular_price', $r->ticket_price );
                update_post_meta( $r->wc_product_id, '_price', $r->ticket_price );
                update_post_meta( $r->wc_product_id, '_raffle_id', $r->id );
                update_post_meta( $r->wc_product_id, '_raffle_status', $r->status );
                $sync_done = true;
            }
        }
    }

    // Handle create missing products
    if ( isset( $_GET['sync_create'] ) && check_admin_referer( 'wpraffle_sync_create', 'sync_nonce' ) ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do that.', 'wpraffle' ) );
        }
        $create_id = absint( $_GET['sync_create'] );
        $r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$raffles_table} WHERE id = %d", $create_id ) );
        if ( $r && class_exists( 'WooCommerce' ) ) {
            $wc_status = $r->status === 'draft' ? 'draft' : 'publish';
            $pid = wp_insert_post( array(
                'post_title'   => $r->title,
                'post_content' => $r->description,
                'post_status'  => $wc_status,
                'post_type'    => 'product',
            ) );
            if ( ! is_wp_error( $pid ) ) {
                wp_set_object_terms( $pid, 'simple', 'product_type' );
                update_post_meta( $pid, '_price', $r->ticket_price );
                update_post_meta( $pid, '_regular_price', $r->ticket_price );
                update_post_meta( $pid, '_virtual', 'yes' );
                update_post_meta( $pid, '_raffle_id', $r->id );
                update_post_meta( $pid, '_raffle_status', $r->status );
                $wpdb->update( $raffles_table, array( 'wc_product_id' => $pid ), array( 'id' => $r->id ) );
                $sync_done = true;
            }
        }
    }

    // Build sync data
    $all_raffles = $wpdb->get_results( "SELECT * FROM {$raffles_table} ORDER BY created_at DESC" );
    foreach ( $all_raffles as $r ) {
        $row = array(
            'raffle'       => $r,
            'product'      => null,
            'issues'       => array(),
            'status_match' => true,
        );

        if ( ! $r->wc_product_id ) {
            $row['issues'][] = 'No WooCommerce product linked';
        } else {
            $product = get_post( $r->wc_product_id );
            if ( ! $product ) {
                $row['issues'][] = 'WooCommerce product (ID: ' . $r->wc_product_id . ') has been deleted';
            } else {
                $row['product'] = $product;
                $wc_status = $product->post_status;
                $expected_status = $r->status === 'draft' ? 'draft' : 'publish';

                if ( $wc_status !== $expected_status ) {
                    $row['status_match'] = false;
                    $row['issues'][] = 'Status mismatch: Raffle is "' . $r->status . '" but product is "' . $wc_status . '"';
                }

                $wc_price = get_post_meta( $r->wc_product_id, '_price', true );
                if ( $wc_price != $r->ticket_price ) {
                    $row['issues'][] = 'Price mismatch: Raffle is ' . $r->ticket_price . ' but product is ' . $wc_price;
                }

                $wc_raffle_id = get_post_meta( $r->wc_product_id, '_raffle_id', true );
                if ( $wc_raffle_id != $r->id ) {
                    $row['issues'][] = 'Product _raffle_id meta mismatch';
                }
            }
        }

        $sync_results[] = $row;
    }

    $issues_count = 0;
    foreach ( $sync_results as $sr ) {
        if ( ! empty( $sr['issues'] ) ) $issues_count++;
    }
    ?>

    <?php if ( $sync_done ) : ?>
        <div class="notice notice-success is-dismissible"><p><strong>Sync completed.</strong> Raffle and WooCommerce product data has been synchronised.</p></div>
    <?php endif; ?>

    <div class="rs-card" style="margin-bottom:20px;">
        <h2 class="rs-card-title">Raffle & WooCommerce Product Sync</h2>
        <p class="description" style="margin-bottom:16px;">This tool ensures every raffle has a matching WooCommerce product with the correct status, price, and metadata. If products get out of sync (e.g. manually editing a product, deleting via WP admin), use this to fix them.</p>

        <?php if ( $issues_count > 0 ) : ?>
            <div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px;">
                <svg class="wpr-icon wpr-icon--lg" style="color:#d97706;"><use href="#wpr-alert"></use></svg>
                <div>
                    <strong style="color:#92400e;"><?php echo esc_html( $issues_count ); ?> raffle(s) have sync issues.</strong>
                    <div style="font-size:13px;color:#78350f;">Click "Sync All" below to fix all issues at once, or sync individually.</div>
                </div>
            </div>
        <?php else : ?>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px;">
                <svg class="wpr-icon wpr-icon--lg" style="color:#16a34a;"><use href="#wpr-check-circle"></use></svg>
                <div>
                    <strong style="color:#166534;">All raffles are in sync.</strong>
                    <div style="font-size:13px;color:#15803d;">No issues found between raffles and WooCommerce products.</div>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-bottom:16px;">
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpraffle-settings&tab=sync&sync_all=1' ), 'wpraffle_sync_all', 'sync_nonce' ) ); ?>" class="button button-primary" onclick="return confirm('This will update all WooCommerce products to match their raffle data. Continue?');">
                <?php echo wpr_get_icon( 'refresh', 'wpr-icon--sm', 'Sync' ); ?> Sync All
            </a>
        </div>
    </div>

    <div class="rs-card" style="margin-bottom:20px;">
        <h2 class="rs-card-title">Sync Status Table</h2>
        <table class="widefat striped" style="max-width:900px;">
            <thead>
                <tr>
                    <th>Raffle</th>
                    <th>Raffle Status</th>
                    <th>WC Product</th>
                    <th>Product Status</th>
                    <th>Price</th>
                    <th>Issues</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $sync_results as $sr ) :
                    $r = $sr['raffle'];
                    $has_issues = ! empty( $sr['issues'] );
                ?>
                <tr style="<?php echo $has_issues ? 'background:#fef2f2;' : ''; ?>">
                    <td>
                        <strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=raffle-list&action=edit&id=' . $r->id ) ); ?>"><?php echo esc_html( $r->title ); ?></a></strong>
                        <div style="font-size:11px;color:#6b7280;">ID: <?php echo esc_html( $r->id ); ?></div>
                    </td>
                    <td>
                        <?php
                        $status_colors = array( 'draft' => '#6b7280', 'active' => '#16a34a', 'finished' => '#dc2626' );
                        $sc = isset( $status_colors[ $r->status ] ) ? $status_colors[ $r->status ] : '#6b7280';
                        ?>
                        <span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:700;color:#fff;background:<?php echo esc_attr( $sc ); ?>;"><?php echo esc_html( ucfirst( $r->status ) ); ?></span>
                    </td>
                    <td>
                        <?php if ( $sr['product'] ) : ?>
                            <a href="<?php echo esc_url( get_edit_post_link( $r->wc_product_id ) ); ?>" target="_blank">#<?php echo esc_html( $r->wc_product_id ); ?></a>
                        <?php elseif ( $r->wc_product_id ) : ?>
                            <span style="color:#dc2626;font-weight:600;">#<?php echo esc_html( $r->wc_product_id ); ?> (deleted)</span>
                        <?php else : ?>
                            <span style="color:#9ca3af;">None</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $sr['product'] ) :
                            $psc = $sr['product']->post_status === 'publish' ? '#16a34a' : '#6b7280';
                        ?>
                            <span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:700;color:#fff;background:<?php echo esc_attr( $psc ); ?>;"><?php echo esc_html( ucfirst( $sr['product']->post_status ) ); ?></span>
                        <?php else : ?>
                            <span style="color:#9ca3af;">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        if ( $sr['product'] ) {
                            $wc_price = get_post_meta( $r->wc_product_id, '_price', true );
                            $price_match = $wc_price == $r->ticket_price;
                            echo '<span style="' . ( $price_match ? 'color:#16a34a' : 'color:#dc2626;font-weight:700' ) . ';">' . esc_html( wpr_price( $wc_price ) ) . '</span>';
                            echo '<div style="font-size:11px;color:#9ca3af;">Expected: ' . esc_html( wpr_price( $r->ticket_price ) ) . '</div>';
                        } else {
                            echo '<span style="color:#9ca3af;">&mdash;</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ( $has_issues ) : ?>
                            <ul style="margin:0;padding:0 0 0 16px;color:#dc2626;font-size:12px;">
                                <?php foreach ( $sr['issues'] as $issue ) : ?>
                                    <li><?php echo esc_html( $issue ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <span style="color:#16a34a;font-weight:600;"><?php echo wpr_get_icon( 'check-circle', 'wpr-icon--sm', 'OK' ); ?> OK</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $has_issues ) : ?>
                            <?php if ( ! $r->wc_product_id || ! $sr['product'] ) : ?>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpraffle-settings&tab=sync&sync_create=' . $r->id ), 'wpraffle_sync_create', 'sync_nonce' ) ); ?>" class="button button-small" onclick="return confirm('Create a WooCommerce product for this raffle?');">Create Product</a>
                            <?php else : ?>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpraffle-settings&tab=sync&sync_raffle=' . $r->id ), 'wpraffle_sync_single', 'sync_nonce' ) ); ?>" class="button button-small">Fix</a>
                            <?php endif; ?>
                        <?php else : ?>
                            <span style="color:#9ca3af;font-size:12px;">&mdash;</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>


    <?php elseif ( $tab === 'advanced' ) : ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'wpraffle_save_settings', 'wpraffle_settings_nonce' ); ?>
        <input type="hidden" name="action" value="wpraffle_save_advanced_settings">

        <div class="rs-card" style="margin-bottom:20px;">
            <h2 class="rs-card-title">Duplicate Handling</h2>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row">Auto-Fix Duplicates</th>
                    <td>
                        <label><input type="checkbox" name="auto_fix_duplicates" value="1" <?php checked( $advanced['auto_fix_duplicates'], 1 ); ?>> Automatically fix duplicate tickets after each purchase</label>
                        <p class="description">Recommended: enabled. Runs the duplicate correction algorithm after every purchase.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="rs-card" style="margin-bottom:20px;">
            <h2 class="rs-card-title">Security</h2>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row"><label for="rate_limit_per_minute">Rate Limit (per minute)</label></th>
                    <td>
                        <input type="number" id="rate_limit_per_minute" name="rate_limit_per_minute" value="<?php echo esc_attr( $advanced['rate_limit_per_minute'] ); ?>" min="1" max="60" class="small-text">
                        <p class="description">Max AJAX requests per IP per minute. Prevents brute-force ticket purchasing.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="trusted_proxies">Trusted Proxy IPs</label></th>
                    <td>
                        <textarea id="trusted_proxies" name="trusted_proxies" rows="2" class="large-text" placeholder="e.g. 173.245.48.1, 103.21.244.1 (CloudFlare)"><?php echo esc_textarea( $trusted_proxies ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Comma-separated list of reverse-proxy IPs (CloudFlare, your nginx/load balancer). WPRaffle will only honour X-Forwarded-For / CF-Connecting-IP headers when the request comes from one of these IPs — preventing IP spoofing on the rate limiter and geo-restriction. Leave blank if your site does not sit behind a proxy you control.', 'wpraffle' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="rs-card" style="margin-bottom:20px;">
            <h2 class="rs-card-title">Audit Log</h2>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row">Enable Audit Logging</th>
                    <td>
                        <label><input type="checkbox" name="enable_audit" value="1" <?php checked( $advanced['enable_audit'], 1 ); ?>> Log all raffle actions (draws, purchases, admin changes)</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="audit_log_days">Retention Period (days)</label></th>
                    <td>
                        <input type="number" id="audit_log_days" name="audit_log_days" value="<?php echo esc_attr( $advanced['audit_log_days'] ); ?>" min="7" max="365" class="small-text">
                        <p class="description">Logs older than this will be automatically purged.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="rs-card" style="margin-bottom:20px;">
            <h2 class="rs-card-title">Scheduled Tasks</h2>
            <table class="widefat" style="max-width:600px;">
                <thead><tr><th>Task</th><th>Interval</th><th>Next Run</th></tr></thead>
                <tbody>
                    <?php
                    $crons = array(
                        'raffle_system_auto_draw_cron' => 'Auto Draw (expired raffles)',
                        'raffle_draw_reminder_cron' => 'Draw Reminder Emails',
                        'raffle_cleanup_reservations' => 'Cleanup Expired Reservations',
                    );
                    foreach ( $crons as $hook => $label ) :
                        $timestamp = wp_next_scheduled( $hook );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $label ); ?></td>
                        <td>Hourly</td>
                        <td><?php echo $timestamp ? esc_html( date( 'Y-m-d H:i:s', $timestamp ) ) : '<em>Not scheduled</em>'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php submit_button( 'Save Advanced Settings', 'primary' ); ?>
    </form>

    <?php
    // Handle recalculate charity totals
    $charity_recalc_done = false;
    $charity_snapshots   = 0;
    if ( isset( $_GET['charity_recalc'] ) && check_admin_referer( 'wpraffle_recalc_charities', 'recalc_nonce' ) ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do that.', 'wpraffle' ) );
        }
        if ( class_exists( 'Raffle_Charity' ) ) {
            Raffle_Charity::refresh_all_totals();
            $charity_recalc_done = true;
        }
    }
    ?>

    <?php if ( $charity_recalc_done ) : ?>
        <div class="notice notice-success is-dismissible"><p><strong>Charity totals recalculated successfully.</strong> Reload the page to see updated values.</p></div>
    <?php endif; ?>

    <div class="rs-card" style="margin-bottom:20px;">
        <h2 class="rs-card-title">Charity Totals</h2>
        <p class="description" style="margin-bottom:16px;">Charity totals are calculated as a percentage of <strong>gross ticket revenue</strong> (total ticket sales). Prizes are assumed to be donated by the operator. Use the button below to force a recalculation across all charities.</p>

        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpraffle-settings&tab=advanced&charity_recalc=1' ), 'wpraffle_recalc_charities', 'recalc_nonce' ) ); ?>" class="button button-primary" style="margin-bottom:16px;" onclick="return confirm('Recalculate all charity totals now?');">
            Recalculate All Charity Totals
        </a>

        <?php
        // Diagnostic table: show all charity-linked raffles and their computed values
        global $wpdb;
        $charity_raffles = $wpdb->get_results(
            "SELECT r.id, r.title, r.status, r.charity_id, r.charity_mode, r.charity_percent, r.sold_tickets, r.ticket_price, r.prize_value, r.total_tickets
             FROM {$wpdb->prefix}raffles r
             WHERE r.charity_id IS NOT NULL AND r.charity_mode != 'none'
             ORDER BY r.id DESC"
        );

        if ( ! empty( $charity_raffles ) ) :
        ?>
        <h3 style="margin:16px 0 8px;font-size:14px;font-weight:700;">Charity-Linked Raffles</h3>
        <table class="widefat striped" style="max-width:100%;">
            <thead>
                <tr>
                    <th>Raffle</th>
                    <th>Status</th>
                    <th>Charity ID</th>
                    <th>Mode / %</th>
                    <th>Sold</th>
                    <th>Gross Revenue</th>
                    <th>Charity Amount</th>
                    <th>Allocation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $charity_raffles as $cr ) :
                    $gross = (float) $cr->sold_tickets * (float) $cr->ticket_price;
                    $pct   = (int) $cr->charity_percent;
                    $charity_amount = round( $gross * ( $pct / 100 ), 2 );

                    // Check for committed allocation
                    $allocation = $wpdb->get_row( $wpdb->prepare(
                        "SELECT allocated_amount, status FROM {$wpdb->prefix}raffle_charity_allocations WHERE raffle_id = %d",
                        $cr->id
                    ) );

                    // Resolve charity name
                    $charity_name = '';
                    $db_charity = $wpdb->get_var( $wpdb->prepare(
                        "SELECT name FROM {$wpdb->prefix}raffle_charities WHERE id = %d",
                        absint( $cr->charity_id )
                    ) );
                    if ( $db_charity ) {
                        $charity_name = $db_charity;
                    } else {
                        $post = get_post( absint( $cr->charity_id ) );
                        if ( $post ) $charity_name = $post->post_title;
                    }

                    $gross_is_zero = $gross <= 0;
                ?>
                <tr style="<?php echo $gross_is_zero ? 'background:#fef3c7;' : ''; ?>">
                    <td>
                        <strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=raffle-list&action=edit&id=' . $cr->id ) ); ?>"><?php echo esc_html( $cr->title ); ?></a></strong>
                        <div style="font-size:11px;color:#6b7280;">ID: <?php echo esc_html( $cr->id ); ?></div>
                    </td>
                    <td>
                        <?php
                        $sc_map = array( 'draft' => '#6b7280', 'active' => '#16a34a', 'finished' => '#dc2626' );
                        $sc_c = isset( $sc_map[ $cr->status ] ) ? $sc_map[ $cr->status ] : '#6b7280';
                        ?>
                        <span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:700;color:#fff;background:<?php echo esc_attr( $sc_c ); ?>;"><?php echo esc_html( ucfirst( $cr->status ) ); ?></span>
                    </td>
                    <td>
                        <span title="<?php echo esc_attr( $charity_name ); ?>"><?php echo esc_html( $cr->charity_id ); ?></span>
                        <?php if ( $charity_name ) : ?>
                            <div style="font-size:11px;color:#6b7280;"><?php echo esc_html( $charity_name ); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( ucfirst( $cr->charity_mode ) ); ?> / <?php echo esc_html( $pct ); ?>%</td>
                    <td><?php echo esc_html( $cr->sold_tickets ); ?>/<?php echo esc_html( $cr->total_tickets ); ?></td>
                    <td><?php echo esc_html( wpr_price( $gross ) ); ?></td>
                    <td style="font-weight:700;"><?php echo esc_html( wpr_price( $charity_amount ) ); ?></td>
                    <td>
                        <?php if ( $allocation ) : ?>
                            <span style="color:#16a34a;font-weight:600;"><?php echo esc_html( wpr_price( $allocation->allocated_amount ) ); ?></span>
                            <div style="font-size:11px;color:#6b7280;"><?php echo esc_html( ucfirst( $allocation->status ) ); ?></div>
                        <?php else : ?>
                            <span style="color:#9ca3af;font-size:12px;">Not yet snapshotted</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ( $gross_is_zero ) : ?>
                <tr style="background:#fef3c7;">
                    <td colspan="8" style="padding:6px 12px;font-size:12px;color:#92400e;">
                        ⚠️ No tickets have been sold yet, so the charity amount is £0.
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        // Show charity totals summary
        if ( class_exists( 'Raffle_Charity' ) ) :
            $all_charities = Raffle_Charity::get_active_charities();
            if ( ! empty( $all_charities ) ) :
        ?>
        <h3 style="margin:24px 0 8px;font-size:14px;font-weight:700;">Charity Totals Summary</h3>
        <table class="widefat striped" style="max-width:500px;">
            <thead><tr><th>Charity</th><th>Live Raised</th></tr></thead>
            <tbody>
                <?php foreach ( $all_charities as $ac ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $ac->name ); ?></strong> <span style="color:#9ca3af;font-size:11px;">(ID: <?php echo esc_html( $ac->id ); ?>)</span></td>
                    <td style="font-weight:700;color:#065f46;"><?php echo esc_html( wpr_price( $ac->live_raised ?? 0 ) ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; endif; ?>

        <?php else : ?>
            <p style="color:#6b7280;padding:12px;">No raffles are currently linked to a charity.</p>
        <?php endif; ?>
    </div>

    <!-- ════════════════════ STYLING TAB ════════════════════ -->
    <?php elseif ( $tab === 'styling' ) : ?>
    <?php
    $presets = class_exists( 'Raffle_Styling' ) ? Raffle_Styling::get_presets() : array();
    $var_docs = class_exists( 'Raffle_Styling' ) ? Raffle_Styling::get_variable_docs() : array();
    ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'wpraffle_save_settings', 'wpraffle_settings_nonce' ); ?>
        <input type="hidden" name="action" value="wpraffle_save_styling_settings">

        <!-- Theme Integration -->
        <div class="rs-card" style="margin-bottom:20px;">
            <h2 class="rs-card-title">Theme Integration</h2>
            <p class="description" style="margin-bottom:16px;">If you want WPRaffle to completely inherit your theme's native colors, backgrounds, borders, and styles without applying any plugin-level overrides, check this option.</p>
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:600;font-size:14px;color:#1f2937;">
                <input type="checkbox" name="disable_custom_styling" value="1" <?php checked( $styling['disable_custom_styling'], '1' ); ?> style="width:18px;height:18px;margin:0;">
                Disable Plugin Styling (Use Active Theme Styles & Colors Only)
            </label>
        </div>

        <!-- Preset Theme Selector -->
        <div class="rs-card" style="margin-bottom:20px;">
            <h2 class="rs-card-title">Theme Presets</h2>
            <p class="description" style="margin-bottom:16px;">Choose a colour theme for WPRaffle. The layout stays the same — only colours change. Semantic colours (red, green, amber) keep their meaning across all themes.</p>

            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
                <?php foreach ( $presets as $key => $preset ) : ?>
                    <label style="cursor:pointer;display:block;border:2px solid <?php echo $styling['preset'] === $key ? 'var(--wpr-accent,#6c5ce7)' : '#e5e7eb'; ?>;border-radius:12px;padding:16px;text-align:center;transition:border-color 0.2s;background:#fff;">
                        <input type="radio" name="preset" value="<?php echo esc_attr( $key ); ?>" <?php checked( $styling['preset'], $key ); ?> style="display:none;" onchange="this.closest('label').parentElement.querySelectorAll('label').forEach(l=>l.style.borderColor='#e5e7eb');this.closest('label').style.borderColor='<?php echo esc_attr( $preset['vars']['--wpr-accent'] ); ?>';">

                        <!-- Colour swatch -->
                        <div style="display:flex;gap:4px;justify-content:center;margin-bottom:10px;">
                            <div style="width:32px;height:32px;border-radius:50%;background:<?php echo esc_attr( $preset['vars']['--wpr-accent'] ); ?>;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,0.15);"></div>
                            <div style="width:32px;height:32px;border-radius:50%;background:<?php echo esc_attr( $preset['vars']['--wpr-accent-dark'] ); ?>;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,0.15);"></div>
                            <div style="width:32px;height:32px;border-radius:50%;background:<?php echo esc_attr( $preset['vars']['--wpr-accent-bg'] ); ?>;border:2px solid #e5e7eb;box-shadow:0 1px 3px rgba(0,0,0,0.1);"></div>
                        </div>

                        <div style="font-weight:700;font-size:14px;color:#1f2937;"><?php echo esc_html( $preset['name'] ); ?></div>
                        <div style="font-size:11px;color:#6b7280;margin-top:4px;"><?php echo esc_html( $preset['description'] ); ?></div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Custom Overrides -->
        <div class="rs-card" style="margin-bottom:20px;">
            <h2 class="rs-card-title">Custom Colour Overrides (Optional)</h2>
            <p class="description" style="margin-bottom:16px;">Override specific colours beyond the preset. Enable a field and pick a colour to override. Disable to use the preset value.</p>
            <table class="form-table" style="margin:0;">
                <?php
                $custom_fields = array(
                    'custom_accent'      => 'Accent Colour',
                    'custom_accent_dark' => 'Accent Dark (Hover)',
                    'custom_text'        => 'Text Colour',
                    'custom_bg'          => 'Background Colour',
                );
                foreach ( $custom_fields as $field_key => $field_label ) :
                    $field_val = ! empty( $styling[ $field_key ] ) ? $styling[ $field_key ] : '';
                    $is_active = ! empty( $field_val );
                ?>
                <tr>
                    <th scope="row"><label><?php echo esc_html( $field_label ); ?></label></th>
                    <td>
                        <div class="wpr-color-override" style="display:flex;align-items:center;gap:10px;">
                            <!-- Hidden input carries the actual submitted value (can be empty) -->
                            <input type="hidden" name="<?php echo esc_attr( $field_key ); ?>" class="wpr-co-value" value="<?php echo esc_attr( $field_val ); ?>">

                            <!-- Enable toggle -->
                            <label style="position:relative;display:inline-block;width:36px;height:20px;margin:0;flex-shrink:0;">
                                <input type="checkbox" class="wpr-co-toggle" <?php checked( $is_active ); ?> style="opacity:0;width:0;height:0;">
                                <span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:<?php echo $is_active ? '#6c5ce7' : '#d1d5db'; ?>;border-radius:20px;transition:.3s;"></span>
                                <span style="position:absolute;height:14px;width:14px;left:<?php echo $is_active ? '18px' : '3px'; ?>;bottom:3px;background:#fff;border-radius:50%;transition:.3s;box-shadow:0 1px 2px rgba(0,0,0,0.2);"></span>
                            </label>

                            <!-- Colour picker (UI only, no name attribute) -->
                            <input type="color" class="wpr-co-picker" value="<?php echo esc_attr( $is_active ? $field_val : '#6c5ce7' ); ?>" style="width:40px;height:32px;border:1px solid #ddd;border-radius:6px;cursor:pointer;padding:2px;<?php echo $is_active ? '' : 'opacity:0.4;pointer-events:none;'; ?>">

                            <!-- Hex display -->
                            <code class="wpr-co-hex" style="font-family:monospace;font-size:13px;color:<?php echo $is_active ? '#1f2937' : '#9ca3af'; ?>;"><?php echo $is_active ? esc_html( $field_val ) : '—'; ?></code>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.wpr-color-override').forEach(function(row) {
                    var toggle  = row.querySelector('.wpr-co-toggle');
                    var picker  = row.querySelector('.wpr-co-picker');
                    var hidden  = row.querySelector('.wpr-co-value');
                    var hex     = row.querySelector('.wpr-co-hex');
                    var track   = toggle.nextElementSibling;
                    var knob    = track.nextElementSibling;

                    function updateUI(active) {
                        track.style.background = active ? '#6c5ce7' : '#d1d5db';
                        knob.style.left = active ? '18px' : '3px';
                        picker.style.opacity = active ? '1' : '0.4';
                        picker.style.pointerEvents = active ? 'auto' : 'none';
                        if (active) {
                            hidden.value = picker.value;
                            hex.textContent = picker.value;
                            hex.style.color = '#1f2937';
                        } else {
                            hidden.value = '';
                            hex.textContent = '—';
                            hex.style.color = '#9ca3af';
                        }
                    }

                    toggle.addEventListener('change', function() { updateUI(toggle.checked); });

                    picker.addEventListener('input', function() {
                        if (toggle.checked) {
                            hidden.value = picker.value;
                            hex.textContent = picker.value;
                        }
                    });
                });
            });
            </script>
        </div>

        <?php submit_button( 'Save Styling Settings', 'primary' ); ?>
    </form>

        <!-- Developer Documentation -->
    <div class="rs-card">
        <h2 class="rs-card-title">Developer Reference — CSS Custom Properties</h2>
        <p class="description" style="margin-bottom:16px;">Theme developers can override any of these CSS variables in their theme's <code>style.css</code> or via Elementor custom CSS. These take precedence over the preset above.</p>

        <details open style="margin-bottom:16px;">
            <summary style="cursor:pointer;font-weight:700;font-size:13px;padding:8px 0;">View all <?php echo array_sum(array_map('count', $var_docs)); ?> CSS variables</summary>
            <div style="padding:12px 0;">
                <?php foreach ( $var_docs as $category => $vars ) : ?>
                    <h4 style="font-size:12px;text-transform:uppercase;color:#6b7280;margin:16px 0 8px;letter-spacing:0.5px;"><?php echo esc_html( $category ); ?></h4>
                    <table class="widefat striped" style="max-width:700px;">
                        <tbody>
                            <?php foreach ( $vars as $var => $desc ) : ?>
                                <tr>
                                    <td style="width:220px;"><code style="font-family:monospace;font-size:12px;color:#6c5ce7;"><?php echo esc_html( $var ); ?></code></td>
                                    <td style="font-size:12px;color:#4b5563;"><?php echo esc_html( $desc ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            </div>
        </details>

        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin-top:12px;">
            <h4 style="margin:0 0 8px;font-size:13px;">Example: Override in your theme</h4>
            <pre style="background:#1a1a1a;color:#e2e8f0;padding:12px;border-radius:6px;font-size:12px;overflow-x:auto;margin:0;"><code>/* In your theme's style.css */
:root {
    --wpr-accent: #e11d48;
    --wpr-accent-dark: #be123c;
    --wpr-text-primary: #0f172a;
    --wpr-bg-surface: #fefce8;
}</code></pre>
        </div>

        <p style="margin-top:12px;font-size:12px;color:#6b7280;">
            Full documentation: see <code>STYLING-GUIDE.md</code> in the plugin folder, or visit the
            <a href="https://github.com/wpraffle/wpraffle/wiki" target="_blank" rel="noopener">WPRaffle Wiki</a>.
        </p>
    </div>

    <!-- ════════════════════ UPDATES TAB ════════════════════ -->
    <?php elseif ( $tab === 'updates' ) : ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'wpraffle_save_settings', 'wpraffle_settings_nonce' ); ?>
        <input type="hidden" name="action" value="wpraffle_save_update_settings">

        <div class="rs-card" style="margin-bottom:20px;">
            <h2 class="rs-card-title">Update Status</h2>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row">Current Version</th>
                    <td><code style="font-size:14px;padding:4px 10px;background:#f3f4f6;border-radius:4px;"><?php echo esc_html( RAFFLE_SYSTEM_VERSION ); ?></code></td>
                </tr>
                <tr>
                    <th scope="row">Latest Available</th>
                    <td>
                        <?php if ( $latest_version ) : ?>
                            <code style="font-size:14px;padding:4px 10px;background:<?php echo $update_available ? '#fef2f2' : '#f0fdf4'; ?>;border-radius:4px;"><?php echo esc_html( $latest_version ); ?></code>
                            <?php if ( $update_available ) : ?>
                                <span style="color:#e74c3c;font-weight:700;margin-left:12px;">Update available!</span>
                            <?php else : ?>
                                <span style="color:#00b894;font-weight:700;margin-left:12px;">Up to date</span>
                            <?php endif; ?>
                        <?php else : ?>
                            <em>Not checked yet</em>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <div style="margin-top:12px;">
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpraffle-settings&tab=updates&check_updates=1' ), 'wpraffle_check_updates' ) ); ?>" class="button">Check for Updates</a>
            </div>
        </div>

        <div class="rs-card" style="margin-bottom:20px;">
            <h2 class="rs-card-title">GitHub Repository</h2>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row"><label for="github_repo">Repository</label></th>
                    <td>
                        <input type="text" id="github_repo" name="github_repo" value="<?php echo esc_attr( $updates['github_repo'] ); ?>" class="regular-text" placeholder="owner/repo">
                        <p class="description">GitHub repository in <code>owner/repo</code> format. Releases are checked for updates.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Auto-Update</th>
                    <td>
                        <label><input type="checkbox" name="auto_update" value="1" <?php checked( $updates['auto_update'], 1 ); ?>> Automatically install updates when available</label>
                        <p class="description">When disabled, you will be notified of available updates but must install manually.</p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( 'Save Update Settings', 'primary' ); ?>
    </form>

    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Color picker sync
    var picker = document.getElementById('accent_color');
    var hex = document.getElementById('accent_color_hex');
    if ( picker && hex ) {
        picker.addEventListener('input', function() { hex.value = picker.value; });
    }

    // Media upload buttons
    document.querySelectorAll('.wpraffle-media-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var target = document.getElementById(btn.dataset.target);
            if ( ! target ) return;
            var frame = wp.media({
                title: 'Choose Image',
                button: { text: 'Use this image' },
                multiple: false
            });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                target.value = attachment.url;
            });
            frame.open();
        });
    });
});
</script>
