<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo $raffle ? ( $is_template ? 'Create Raffle from Template' : 'Edit Raffle' ) : 'Create New Raffle'; ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=raffle-list' ) ); ?>" class="page-title-action">Back to List</a>
    <hr class="wp-header-end">

    <?php if ( $is_template && $template ) : ?>
    <div class="notice notice-info">
        <p>
            <?php wpr_icon( 'edit', 'wpr-icon--sm' ); ?>
            <strong><?php esc_html_e( 'Creating from template:', 'wpraffle' ); ?></strong>
            <?php echo esc_html( $template->name ); ?>
            <br>
            <?php esc_html_e( 'Configuration has been pre-filled. Add a title, prize details and dates, then publish. Instant wins from the template will be added when you save.', 'wpraffle' ); ?>
        </p>
    </div>
    <?php endif; ?>

    <form method="post" action="" style="margin-top: 20px;">
        <?php wp_nonce_field( 'raffle_save', 'raffle_nonce' ); ?>
        <input type="hidden" name="raffle_form_submit" value="1">
        <?php if ( $raffle && ! $is_template ) : ?>
            <input type="hidden" name="raffle_id" value="<?php echo esc_attr( $raffle->id ); ?>">
        <?php endif; ?>
        <?php if ( $is_template && $template ) : ?>
            <input type="hidden" name="template_id" value="<?php echo esc_attr( (int) $template->id ); ?>">
        <?php endif; ?>

        <div id="poststuff">
            <div id="post-body" class="metabox-holder">
                <div id="post-body-content">
                    
                    <!-- Meta Box 1: Raffle Details -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'ticket', 'wpr-icon--sm' ); ?> General Details</h2>
                        </div>
                        <div class="inside">
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="title">Title *</label></th>
                                        <td>
                                            <input name="title" type="text" id="title" class="regular-text" required
                                                   value="<?php echo $raffle ? esc_attr( $raffle->title ) : ''; ?>"
                                                   placeholder="e.g. iPhone 17 Pro Max">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="description">Description</label></th>
                                        <td>
                                            <textarea name="description" id="description" rows="5" class="large-text"
                                                      placeholder="Detailed description of the prize..."><?php echo $raffle ? esc_textarea( $raffle->description ) : ''; ?></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="prize_value">Prize Value (<?php echo esc_html( wpr_currency_symbol() ); ?>) *</label></th>
                                        <td>
                                            <input name="prize_value" type="number" id="prize_value" class="regular-text" step="0.01" min="0" required
                                                   value="<?php echo $raffle ? esc_attr( $raffle->prize_value ) : ''; ?>"
                                                   placeholder="e.g. 1000">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="ticket_price">Price per Ticket (<?php echo esc_html( wpr_currency_symbol() ); ?>) *</label></th>
                                        <td>
                                            <input name="ticket_price" type="number" id="ticket_price" class="regular-text" step="0.01" min="0" required
                                                   value="<?php echo $raffle ? esc_attr( $raffle->ticket_price ) : ''; ?>"
                                                   placeholder="e.g. 5">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="total_tickets">Total Tickets *</label></th>
                                        <td>
                                            <input name="total_tickets" type="number" id="total_tickets" class="regular-text" min="1" required
                                                   value="<?php echo $raffle ? esc_attr( $raffle->total_tickets ) : ''; ?>"
                                                   placeholder="e.g. 1000"
                                                   <?php echo ( $raffle && $raffle->sold_tickets > 0 ) ? 'readonly' : ''; ?>>
                                            <?php if ( $raffle && $raffle->sold_tickets > 0 ) : ?>
                                                <p class="description" style="color: #b32d2e;"><?php wpr_icon( 'lock', 'wpr-icon--sm' ); ?> Locked: Tickets have already been sold.</p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="packages">Ticket Packages *</label></th>
                                        <td>
                                            <?php
                                            // Resolve the current packages value into a normalised array of
                                            // bundle objects so the builder UI below can render existing rows.
                                            // Supports bare ints [5,10], old [{"qty":5}], and full bundles.
                                            $raw_packages = $raffle && $raffle->packages ? $raffle->packages : '[5,10,15,25]';
                                            $bundles      = wpraffle_normalise_packages( $raw_packages );
                                            if ( empty( $bundles ) ) {
                                                $bundles = array(
                                                    array( 'qty' => 5, 'price' => 0.0, 'label' => '', 'badge' => '' ),
                                                    array( 'qty' => 10, 'price' => 0.0, 'label' => '', 'badge' => '' ),
                                                    array( 'qty' => 15, 'price' => 0.0, 'label' => '', 'badge' => '' ),
                                                    array( 'qty' => 25, 'price' => 0.0, 'label' => '', 'badge' => '' ),
                                                );
                                            }
                                            $cur_symbol = function_exists( 'wpr_currency_symbol' ) ? wpr_currency_symbol() : '$';
                                            ?>
                                            <!-- Hidden field is the real source of truth submitted to the server.
                                                 The builder UI syncs into it on every change. -->
                                            <input type="hidden" name="packages" id="packages" value="<?php echo esc_attr( $raw_packages ); ?>">

                                            <div id="rs-bundle-builder" style="max-width:680px;">
                                                <div id="rs-bundle-rows" style="display:flex;flex-direction:column;gap:8px;margin-bottom:8px;">
                                                    <?php foreach ( $bundles as $b ) : ?>
                                                        <div class="rs-bundle-row" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:8px;">
                                                            <input type="number" class="rs-b-qty" min="1" step="1" placeholder="Qty" value="<?php echo esc_attr( $b['qty'] ); ?>" style="width:70px;" aria-label="Quantity">
                                                            <span style="color:#50575e;font-size:12px;">tickets for</span>
                                                            <span class="rs-b-price-wrap" style="display:flex;align-items:center;gap:2px;">
                                                                <span style="color:#50575e;"><?php echo esc_html( $cur_symbol ); ?></span>
                                                                <input type="number" class="rs-b-price" min="0" step="0.01" placeholder="Standard" value="<?php echo esc_attr( $b['price'] > 0 ? $b['price'] : '' ); ?>" style="width:90px;" aria-label="Bundle price (leave blank for standard price each)">
                                                            </span>
                                                            <input type="text" class="rs-b-label" placeholder="Label (e.g. 5 for £25)" value="<?php echo esc_attr( $b['label'] ); ?>" style="width:180px;" aria-label="Label">
                                                            <input type="text" class="rs-b-badge" placeholder="Badge (e.g. Popular)" value="<?php echo esc_attr( $b['badge'] ); ?>" style="width:130px;" aria-label="Badge">
                                                            <button type="button" class="button rs-b-remove" aria-label="Remove bundle">&times;</button>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <button type="button" class="button button-secondary" id="rs-add-bundle"><?php echo wpr_get_icon( 'plus', 'wpr-icon--xs' ); ?> Add Bundle</button>
                                                <p class="description" style="margin-top:8px;">
                                                    <?php esc_html_e( 'Each row is an entry option. Set a Quantity, and optionally a fixed Bundle Price (leave blank to charge the standard ticket price × qty). Label and Badge show on the entry button.', 'wpraffle' ); ?>
                                                </p>
                                            </div>

                                            <label style="display:inline-flex;align-items:center;gap:6px;margin-top:10px;">
                                                <input type="checkbox" name="enable_bundles" value="1" <?php checked( $raffle && ! empty( $raffle->enable_bundles ), true ); ?>>
                                                <?php esc_html_e( 'Enable Bundle display (shows price + savings % on the entry buttons)', 'wpraffle' ); ?>
                                            </label>
                                            <label style="display:inline-flex;align-items:center;gap:6px;margin-top:10px;margin-left:16px;">
                                                <input type="checkbox" name="enable_number_grid" value="1" <?php checked( $raffle && ! empty( $raffle->enable_number_grid ), true ); ?>>
                                                <?php esc_html_e( 'Show visual Number Picker Grid (lets buyers choose specific ticket numbers)', 'wpraffle' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label>Prize Image</label></th>
                                        <td>
                                            <div class="rs-image-upload" style="display:flex; align-items:center; gap:15px;">
                                                <input type="hidden" id="prize_image" name="prize_image"
                                                       value="<?php echo $raffle ? esc_attr( $raffle->prize_image ) : ''; ?>">
                                                <div id="prize-image-preview" style="width:100px; height:100px; border:1px solid #c3c4c7; background:#f0f0f1; display:flex; align-items:center; justify-content:center; border-radius:4px; overflow:hidden;">
                                                    <?php if ( $raffle && $raffle->prize_image ) : ?>
                                                        <img src="<?php echo esc_url( $raffle->prize_image ); ?>" style="max-width:100%; max-height:100%; object-fit:cover;">
                                                    <?php else : ?>
                                                        <?php wpr_icon( 'image', 'wpr-icon--lg' ); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="upload-actions">
                                                    <button type="button" class="button" id="upload-prize-image">Select Image</button>
                                                    <button type="button" class="button button-link-delete" id="remove-prize-image" style="<?php echo ( $raffle && $raffle->prize_image ) ? '' : 'display:none;'; ?>">Remove</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Meta Box 2: Advanced Settings -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'settings', 'wpr-icon--sm' ); ?> Advanced Settings</h2>
                        </div>
                        <div class="inside">
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="ticket_selection">Ticket Selection Mode</label></th>
                                        <td>
                                            <select name="ticket_selection" id="ticket_selection">
                                                <option value="random" <?php selected( $raffle ? $raffle->ticket_selection : 'random', 'random' ); ?>>Random (System Picks)</option>
                                                <option value="manual" <?php selected( $raffle ? $raffle->ticket_selection : '', 'manual' ); ?>>Manual (User Picks)</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="jackpot_type">Prize Type</label></th>
                                        <td>
                                            <select name="jackpot_type" id="jackpot_type">
                                                <option value="fixed" <?php selected( $raffle ? $raffle->jackpot_type : 'fixed', 'fixed' ); ?>>Fixed Prize</option>
                                                <option value="progressive" <?php selected( $raffle ? $raffle->jackpot_type : '', 'progressive' ); ?>>Progressive Pot (e.g. 50/50)</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="jackpot_percent">Progressive Pot %</label></th>
                                        <td>
                                            <input name="jackpot_percent" type="number" id="jackpot_percent" min="1" max="100" class="small-text"
                                                   value="<?php echo $raffle ? esc_attr( $raffle->jackpot_percent ) : '50'; ?>">
                                            <p class="description">Percentage of pot going to winner if progressive type is chosen.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="enable_cash_alternative">Cash Alternative Option</label></th>
                                        <td>
                                            <select name="enable_cash_alternative" id="enable_cash_alternative">
                                                <option value="0" <?php selected( $raffle ? $raffle->enable_cash_alternative : 0, 0 ); ?>>Disabled</option>
                                                <option value="1" <?php selected( $raffle ? $raffle->enable_cash_alternative : 0, 1 ); ?>>Enabled</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="cash_alternative_amount">Cash Alternative Amount (<?php echo esc_html( wpr_currency_symbol() ); ?>)</label></th>
                                        <td>
                                            <input name="cash_alternative_amount" type="number" id="cash_alternative_amount" class="regular-text" step="0.01" min="0"
                                                   value="<?php echo $raffle ? esc_attr( $raffle->cash_alternative_amount ) : '0'; ?>"
                                                   placeholder="e.g. 500">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="draw_type">Draw Automation</label></th>
                                        <td>
                                            <select name="draw_type" id="draw_type">
                                                <option value="manual" <?php selected( $raffle ? $raffle->draw_type : 'manual', 'manual' ); ?>>Manual (Admin clicks draw)</option>
                                                <option value="auto" <?php selected( $raffle ? $raffle->draw_type : '', 'auto' ); ?>>Auto (Runs on Expiry)</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="live_draw_url">Live Draw Embed URL</label></th>
                                        <td>
                                            <input name="live_draw_url" type="url" id="live_draw_url" class="regular-text" placeholder="https://youtube.com/embed/..."
                                                   value="<?php echo $raffle ? esc_attr( $raffle->live_draw_url ) : ''; ?>">
                                            <p class="description">YouTube or Twitch embed link shown on draw page.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="start_date">Start Date & Time</label></th>
                                        <td>
                                            <input name="start_date" type="datetime-local" id="start_date"
                                                   value="<?php echo ( $raffle && $raffle->start_date ) ? esc_attr( date( 'Y-m-d\TH:i', strtotime( $raffle->start_date ) ) ) : ''; ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="draw_date">Draw Date & Time</label></th>
                                        <td>
                                            <input name="draw_date" type="datetime-local" id="draw_date"
                                                   value="<?php echo ( $raffle && $raffle->draw_date ) ? esc_attr( date( 'Y-m-d\TH:i', strtotime( $raffle->draw_date ) ) ) : ''; ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="status">Raffle Status</label></th>
                                        <td>
                                            <select name="status" id="status">
                                                <option value="draft"    <?php selected( $raffle ? $raffle->status : 'draft', 'draft' ); ?>>Draft</option>
                                                <option value="active"   <?php selected( $raffle ? $raffle->status : 'active', 'active' ); ?>>Active / Live</option>
                                                <option value="finished" <?php selected( $raffle ? $raffle->status : '', 'finished' ); ?>>Finished / Ended</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="max_tickets_per_user">Max Tickets Per User</label></th>
                                        <td>
                                            <input name="max_tickets_per_user" type="number" id="max_tickets_per_user" class="small-text" min="1"
                                                   value="<?php echo $raffle ? esc_attr( $raffle->max_tickets_per_user ) : '100'; ?>">
                                        </td>
                                    </tr>
                                    <?php
                                    // WooCommerce Product Categories
                                    $product_categories = class_exists( 'WooCommerce' ) ? get_terms( array(
                                        'taxonomy'   => 'product_cat',
                                        'hide_empty' => false,
                                    ) ) : array();
                                    $selected_cats = array();
                                    if ( $raffle && $raffle->wc_product_id ) {
                                        $selected_cats = wp_get_post_terms( $raffle->wc_product_id, 'product_cat', array( 'fields' => 'ids' ) );
                                        if ( is_wp_error( $selected_cats ) ) $selected_cats = array();
                                    }
                                    $product_tags = class_exists( 'WooCommerce' ) ? get_terms( array(
                                        'taxonomy'   => 'product_tag',
                                        'hide_empty' => false,
                                    ) ) : array();
                                    $selected_tags = array();
                                    if ( $raffle && $raffle->wc_product_id ) {
                                        $selected_tags = wp_get_post_terms( $raffle->wc_product_id, 'product_tag', array( 'fields' => 'ids' ) );
                                        if ( is_wp_error( $selected_tags ) ) $selected_tags = array();
                                    }
                                    ?>
                                    <?php if ( ! empty( $product_categories ) && ! is_wp_error( $product_categories ) ) : ?>
                                    <tr>
                                        <th scope="row"><label for="product_categories">Product Category</label></th>
                                        <td>
                                            <select name="product_categories[]" id="product_categories" multiple style="min-width:300px; height:auto; max-height:150px;">
                                                <?php foreach ( $product_categories as $cat ) : ?>
                                                    <option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php echo in_array( $cat->term_id, $selected_cats ) ? 'selected' : ''; ?>>
                                                        <?php echo esc_html( $cat->name ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description">WooCommerce product categories. Hold Ctrl/Cmd to select multiple.</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $product_tags ) && ! is_wp_error( $product_tags ) ) : ?>
                                    <tr>
                                        <th scope="row"><label for="product_tags">Product Tags</label></th>
                                        <td>
                                            <select name="product_tags[]" id="product_tags" multiple style="min-width:300px; height:auto; max-height:150px;">
                                                <?php foreach ( $product_tags as $tag ) : ?>
                                                    <option value="<?php echo esc_attr( $tag->term_id ); ?>" <?php echo in_array( $tag->term_id, $selected_tags ) ? 'selected' : ''; ?>>
                                                        <?php echo esc_html( $tag->name ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description">WooCommerce product tags. Hold Ctrl/Cmd to select multiple.</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Meta Box: Engagement & Marketing -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'zap', 'wpr-icon--sm' ); ?> <?php esc_html_e( 'Engagement & Marketing', 'wpraffle' ); ?></h2>
                            <button type="button" class="handlediv"><?php wpr_icon( 'chevron-down', 'wpr-icon--sm' ); ?></button>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tbody>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Consolation Coupons', 'wpraffle' ); ?></th>
                                        <td>
                                            <label style="display:inline-flex;align-items:center;gap:6px;">
                                                <input type="checkbox" name="enable_consolation_coupon" value="1" <?php checked( $raffle && ! empty( $raffle->enable_consolation_coupon ), true ); ?>>
                                                <?php esc_html_e( 'Email a WooCommerce coupon to every non-winning entrant after the draw.', 'wpraffle' ); ?>
                                            </label>
                                            <?php
                                            $consolation_config = $raffle && $raffle->consolation_config ? json_decode( $raffle->consolation_config, true ) : array();
                                            $consolation_config = wp_parse_args( $consolation_config, array( 'type' => 'percent', 'amount' => 10, 'expiry_days' => 30 ) );
                                            ?>
                                            <div style="display:flex;gap:12px;margin-top:8px;flex-wrap:wrap;">
                                                <label><?php esc_html_e( 'Type:', 'wpraffle' ); ?>
                                                    <select name="consolation_type">
                                                        <option value="percent" <?php selected( $consolation_config['type'], 'percent' ); ?>><?php esc_html_e( 'Percentage', 'wpraffle' ); ?></option>
                                                        <option value="fixed" <?php selected( $consolation_config['type'], 'fixed' ); ?>><?php esc_html_e( 'Fixed amount', 'wpraffle' ); ?></option>
                                                    </select>
                                                </label>
                                                <label><?php esc_html_e( 'Amount:', 'wpraffle' ); ?>
                                                    <input type="number" name="consolation_amount" class="small-text" min="0" step="0.01" value="<?php echo esc_attr( $consolation_config['amount'] ); ?>">
                                                </label>
                                                <label><?php esc_html_e( 'Expiry (days):', 'wpraffle' ); ?>
                                                    <input type="number" name="consolation_expiry_days" class="small-text" min="1" value="<?php echo esc_attr( $consolation_config['expiry_days'] ); ?>">
                                                </label>
                                            </div>
                                            <p class="description"><?php esc_html_e( 'Coupons are single-use, locked to each entrant\'s email, and issued once per draw.', 'wpraffle' ); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Live Scarcity', 'wpraffle' ); ?></th>
                                        <td>
                                            <label style="display:inline-flex;align-items:center;gap:6px;">
                                                <input type="checkbox" name="enable_scarcity" value="1" <?php checked( $raffle && ! empty( $raffle->enable_scarcity ), true ); ?>>
                                                <?php esc_html_e( 'Animate the progress bar and show live "only N tickets left" updates.', 'wpraffle' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Viewing Now', 'wpraffle' ); ?></th>
                                        <td>
                                            <label style="display:inline-flex;align-items:center;gap:6px;">
                                                <input type="checkbox" name="enable_viewers_now" value="1" <?php checked( $raffle && ! empty( $raffle->enable_viewers_now ), true ); ?>>
                                                <?php esc_html_e( 'Show an "X people viewing this raffle" social-proof badge.', 'wpraffle' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Share & Refer', 'wpraffle' ); ?></th>
                                        <td>
                                            <label style="display:inline-flex;align-items:center;gap:6px;">
                                                <input type="checkbox" name="enable_share" value="1" <?php checked( $raffle && ! empty( $raffle->enable_share ), true ); ?>>
                                                <?php esc_html_e( 'Show social share buttons (WhatsApp, Facebook, X, copy-link) on the entry page.', 'wpraffle' ); ?>
                                            </label>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Meta Box 3: compliance & skills test -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'shield', 'wpr-icon--sm' ); ?> UK Regulations & Postal Entries</h2>
                        </div>
                        <div class="inside">
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="enable_question">Enable Skill Question</label></th>
                                        <td>
                                            <select name="enable_question" id="enable_question">
                                                <option value="0" <?php selected( $raffle ? $raffle->enable_question : 0, 0 ); ?>>Disabled</option>
                                                <option value="1" <?php selected( $raffle ? $raffle->enable_question : 0, 1 ); ?>>Enabled</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr class="question-only-field" style="<?php echo ( $raffle && $raffle->enable_question ) ? '' : 'display:none;'; ?>">
                                        <th scope="row"><label for="question_text">Question Text</label></th>
                                        <td>
                                            <input name="question_text" type="text" id="question_text" class="large-text"
                                                   value="<?php echo $raffle ? esc_attr( $raffle->question_text ) : ''; ?>"
                                                   placeholder="e.g. What is the capital of the United Kingdom?">
                                        </td>
                                    </tr>
                                    <?php
                                    $answers = array( '', '', '' );
                                    if ( $raffle && ! empty( $raffle->question_answers ) ) {
                                        $answers = json_decode( $raffle->question_answers, true ) ?: array( '', '', '' );
                                    }
                                    ?>
                                    <tr class="question-only-field" style="<?php echo ( $raffle && $raffle->enable_question ) ? '' : 'display:none;'; ?>">
                                        <th scope="row"><label>Answer Options</label></th>
                                        <td>
                                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                                <input type="text" name="question_answer_0" class="regular-text" value="<?php echo esc_attr( $answers[0] ?? '' ); ?>" placeholder="Option 1">
                                                <input type="text" name="question_answer_1" class="regular-text" value="<?php echo esc_attr( $answers[1] ?? '' ); ?>" placeholder="Option 2">
                                                <input type="text" name="question_answer_2" class="regular-text" value="<?php echo esc_attr( $answers[2] ?? '' ); ?>" placeholder="Option 3">
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="question-only-field" style="<?php echo ( $raffle && $raffle->enable_question ) ? '' : 'display:none;'; ?>">
                                        <th scope="row"><label for="correct_answer_index">Correct Answer Option</label></th>
                                        <td>
                                            <select name="correct_answer_index" id="correct_answer_index">
                                                <option value="0" <?php selected( $raffle ? $raffle->correct_answer_index : 0, 0 ); ?>>Option 1</option>
                                                <option value="1" <?php selected( $raffle ? $raffle->correct_answer_index : 0, 1 ); ?>>Option 2</option>
                                                <option value="2" <?php selected( $raffle ? $raffle->correct_answer_index : 0, 2 ); ?>>Option 3</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="postal_instructions">Postal Entry Instructions</label></th>
                                        <td>
                                            <textarea name="postal_instructions" id="postal_instructions" rows="4" class="large-text"
                                                      placeholder="Instructions on how users can enter for free by mail..."><?php 
                                                if ( $raffle && ! empty( $raffle->postal_instructions ) ) {
                                                    echo esc_textarea( $raffle->postal_instructions );
                                                } else {
                                                    echo esc_textarea( "To enter this competition for free by post, please send your Name, Address, Email, Phone Number, and correct answer to the skill question on a postcard to: Paragon Competitions Ltd, 123 Main Street, London, EC1A 1BB. Postal entries must be received before the draw date to be eligible." );
                                                }
                                            ?></textarea>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Meta Box: Multi-Winner & Prizes -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'trophy', 'wpr-icon--sm' ); ?> Multi-Winner & Prizes</h2>
                        </div>
                        <div class="inside">
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="multi_winner">Enable Multiple Winners</label></th>
                                        <td>
                                            <select name="multi_winner" id="multi_winner">
                                                <option value="0" <?php selected( $raffle ? $raffle->multi_winner : 0, 0 ); ?>>Single Winner</option>
                                                <option value="1" <?php selected( $raffle ? $raffle->multi_winner : 0, 1 ); ?>>Multiple Winners</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr id="num-winners-row" style="<?php echo ( $raffle && $raffle->multi_winner ) ? '' : 'display:none;'; ?>">
                                        <th scope="row"><label for="number_of_winners">Number of Winners</label></th>
                                        <td>
                                            <input name="number_of_winners" type="number" id="number_of_winners" class="small-text" min="2" max="20"
                                                   value="<?php echo $raffle ? esc_attr( $raffle->number_of_winners ) : '2'; ?>">
                                            <p class="description">Each winner gets a different prize position (1st, 2nd, 3rd...)</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <?php if ( $raffle ) :
                                $prizes = class_exists( 'Raffle_Prizes' ) ? Raffle_Prizes::get_prizes( $raffle->id ) : array();
                            ?>
                            <div id="prizes-config" style="<?php echo ( $raffle && $raffle->multi_winner ) ? '' : 'display:none;'; ?>">
                                <h4 style="margin-bottom:10px;">Prize Positions</h4>
                                <div id="prizes-list">
                                    <?php foreach ( $prizes as $i => $prize ) : ?>
                                    <div class="prize-row" style="display:flex;gap:10px;align-items:center;margin-bottom:8px;">
                                        <span class="prize-position" style="font-weight:700;width:30px;"><?php echo $i + 1; ?>.</span>
                                        <input type="text" name="prize_name[]" class="regular-text" value="<?php echo esc_attr( $prize->prize_name ); ?>" placeholder="Prize name (e.g. $500 Gift Card)">
                                        <input type="number" name="prize_value[]" class="small-text" step="0.01" min="0" value="<?php echo esc_attr( $prize->prize_value ); ?>" placeholder="Value">
                                        <button type="button" class="button remove-prize-btn" title="Remove">×</button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" id="add-prize-btn" class="button button-secondary">+ Add Prize</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Meta Box: Free Entry (No Purchase Necessary) -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'ticket', 'wpr-icon--sm' ); ?> Free Entry (No Purchase Necessary)</h2>
                        </div>
                        <div class="inside">
                            <p class="description" style="margin-bottom:15px;">
                                When enabled, users can enter the raffle for free without purchasing a ticket. 
                                If a skill question is configured in the <strong>UK Regulations</strong> section above, free entrants must also answer it correctly.
                                Each user gets 1 free entry per raffle.
                            </p>
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="allow_free_entry">Allow Free Entries</label></th>
                                        <td>
                                            <select name="allow_free_entry" id="allow_free_entry">
                                                <option value="0" <?php selected( $raffle ? $raffle->allow_free_entry : 0, 0 ); ?>>Disabled</option>
                                                <option value="1" <?php selected( $raffle ? $raffle->allow_free_entry : 0, 1 ); ?>>Enabled</option>
                                            </select>
                                            <p class="description">Required in many jurisdictions (UK, EU). Allows users to enter without purchasing.</p>
                                        </td>
                                    </tr>
                                    <tr class="free-entry-field" style="<?php echo ( $raffle && $raffle->allow_free_entry ) ? '' : 'display:none;'; ?>">
                                        <th scope="row"><label for="free_entry_max">Max Free Entries Per User</label></th>
                                        <td>
                                            <input name="free_entry_max" type="number" id="free_entry_max" class="small-text" min="1" max="10"
                                                   value="<?php echo $raffle ? esc_attr( $raffle->free_entry_max ?? 1 ) : '1'; ?>">
                                            <p class="description">Number of free entries allowed per user per raffle.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <?php if ( $raffle && $raffle->allow_free_entry ) :
                                global $wpdb;
                                $free_count = (int) $wpdb->get_var( $wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->prefix}raffle_free_entries WHERE raffle_id = %d",
                                    $raffle->id
                                ) );
                            ?>
                            <div class="free-entry-field" style="margin-top:10px;padding:12px;background:#f0f6fc;border:1px solid #c3daf0;border-radius:4px;">
                                <strong>Free Entries Used:</strong> <?php echo $free_count; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Meta Box: Geo-restriction -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'share', 'wpr-icon--sm' ); ?> Geo-Restriction</h2>
                        </div>
                        <div class="inside">
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="geo_restricted">Restrict by Country</label></th>
                                        <td>
                                            <select name="geo_restricted" id="geo_restricted">
                                                <option value="0" <?php selected( $raffle ? $raffle->geo_restricted : 0, 0 ); ?>>No Restriction (Global)</option>
                                                <option value="1" <?php selected( $raffle ? $raffle->geo_restricted : 0, 1 ); ?>>Restrict to Selected Countries</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr id="geo-countries-row" style="<?php echo ( $raffle && $raffle->geo_restricted ) ? '' : 'display:none;'; ?>">
                                        <th scope="row"><label>Allowed Countries</label></th>
                                        <td>
                                            <?php
                                            $all_countries = class_exists( 'Raffle_Geo' ) ? Raffle_Geo::get_countries_list() : array();
                                            $selected_countries = $raffle ? json_decode( $raffle->geo_allowed_countries ?? '[]', true ) : array();
                                            if ( ! is_array( $selected_countries ) ) $selected_countries = array();
                                            ?>
                                            <select name="geo_allowed_countries[]" id="geo_allowed_countries" multiple style="height:200px;min-width:300px;">
                                                <?php foreach ( $all_countries as $code => $name ) : ?>
                                                    <option value="<?php echo esc_attr( $code ); ?>" <?php echo in_array( $code, $selected_countries ) ? 'selected' : ''; ?>>
                                                        <?php echo esc_html( $name ); ?> (<?php echo esc_html( $code ); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description">Hold Ctrl/Cmd to select multiple countries.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Meta Box: Referrals -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'share', 'wpr-icon--sm' ); ?> Referral System</h2>
                        </div>
                        <div class="inside">
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="allow_referrals">Enable Referrals</label></th>
                                        <td>
                                            <select name="allow_referrals" id="allow_referrals">
                                                <option value="0" <?php selected( $raffle ? $raffle->allow_referrals : 0, 0 ); ?>>Disabled</option>
                                                <option value="1" <?php selected( $raffle ? $raffle->allow_referrals : 0, 1 ); ?>>Enabled</option>
                                            </select>
                                            <p class="description">Users get a unique referral link. When someone uses it, the referrer gets bonus entries.</p>
                                        </td>
                                    </tr>
                                    <tr id="referral-bonus-row" style="<?php echo ( $raffle && $raffle->allow_referrals ) ? '' : 'display:none;'; ?>">
                                        <th scope="row"><label for="referral_bonus_entries">Bonus Entries Per Referral</label></th>
                                        <td>
                                            <input name="referral_bonus_entries" type="number" id="referral_bonus_entries" class="small-text" min="1" max="50"
                                                   value="<?php echo $raffle ? esc_attr( $raffle->referral_bonus_entries ?? 1 ) : '1'; ?>">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>


                    <!-- Meta Box: Charity -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><svg class="wpr-icon wpr-icon--sm"><use href="#wpr-gift"></use></svg> Charity / Fundraising</h2>
                        </div>
                        <div class="inside">
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="charity_mode">Charity Mode</label></th>
                                        <td>
                                            <select name="charity_mode" id="charity_mode">
                                                <option value="none" <?php echo ( $raffle && $raffle->charity_mode === 'none' ) ? 'selected' : ''; ?>>None (no charity)</option>
                                                <option value="partial" <?php echo ( $raffle && $raffle->charity_mode === 'partial' ) ? 'selected' : ''; ?>>Partial (% of net proceeds)</option>
                                                <option value="full" <?php echo ( $raffle && $raffle->charity_mode === 'full' ) ? 'selected' : ''; ?>>Full (100% of net proceeds)</option>
                                            </select>
                                            <p class="description">Pledge a portion of net proceeds (after prize cost) to a charity.</p>
                                        </td>
                                    </tr>
                                    <tr id="charity-select-row" style="<?php echo ( $raffle && $raffle->charity_mode !== 'none' ) ? '' : 'display:none;'; ?>">
                                        <th scope="row"><label for="charity_id">Select Charity</label></th>
                                        <td>
                                            <select name="charity_id" id="charity_id">
                                                <option value="">— Select a charity —</option>
                                                <?php if ( class_exists( 'Raffle_Charity' ) ) : ?>
                                                    <?php foreach ( Raffle_Charity::get_active_charities() as $c ) : ?>
                                                        <option value="<?php echo esc_attr( $c->id ); ?>" <?php echo ( $raffle && (int)$raffle->charity_id === (int)$c->id ) ? 'selected' : ''; ?>><?php echo esc_html( $c->name ); ?></option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                            <p class="description">Manage charities in <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=raffle_charity' ) ); ?>">Charities</a>.</p>
                                        </td>
                                    </tr>
                                    <tr id="charity-percent-row" style="<?php echo ( $raffle && $raffle->charity_mode === 'partial' ) ? '' : 'display:none;'; ?>">
                                        <th scope="row"><label for="charity_percent">Donation Percentage</label></th>
                                        <td>
                                            <input name="charity_percent" type="number" id="charity_percent" class="small-text" min="1" max="100"
                                                   value="<?php echo $raffle ? esc_attr( $raffle->charity_percent ?? 100 ) : 100; ?>">%
                                            <p class="description">Percentage of gross ticket sales donated to the charity.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Meta Box: Templates & Clone -->
                    <?php if ( $raffle && ! $is_template ) : ?>
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'edit', 'wpr-icon--sm' ); ?> Templates & Clone</h2>
                        </div>
                        <div class="inside">
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row">Save as Template</th>
                                        <td>
                                            <input type="text" id="template-name" class="regular-text" placeholder="Template name (e.g. Standard iPhone Raffle)">
                                            <button type="button" id="save-template-btn" class="button button-secondary" data-raffle-id="<?php echo esc_attr( $raffle->id ); ?>">Save as Template</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Clone Raffle</th>
                                        <td>
                                            <button type="button" id="clone-raffle-btn" class="button button-secondary" data-raffle-id="<?php echo esc_attr( $raffle->id ); ?>">Clone This Raffle</button>
                                            <p class="description">Creates a copy with zero sold tickets, ready to configure.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Meta Box 4: Instant Wins (Edit mode only) -->
                    <?php if ( $raffle ) : 
                        $instant_wins = Raffle_Instant_Wins::get_instant_wins( $raffle->id );
                    ?>
                        <div class="postbox">
                            <div class="postbox-header">
                                <h2 class="hndle"><?php wpr_icon( 'gift', 'wpr-icon--sm' ); ?> Configure Instant Wins</h2>
                            </div>
                            <div class="inside">
                                <p class="description" style="margin-bottom: 15px;">Instant Wins allow users to win specific prizes immediately when they purchase the matching ticket number.</p>
                                
                                <div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; background: #f6f7f7; padding: 15px; border:1px solid #c3c4c7; border-radius: 4px;">
                                    <input type="text" id="iw-prize-name" placeholder="Prize Name (e.g. $50 Gift Card)" style="flex:1; height: 35px;">
                                    <input type="number" id="iw-ticket-number" placeholder="Ticket # (blank=random)" style="width: 180px; height: 35px;">
                                    <input type="number" id="iw-quantity" placeholder="Qty" value="1" min="1" max="100" style="width: 70px; height: 35px;">
                                    <button type="button" id="add-instant-win-btn" class="button button-primary" data-raffle-id="<?php echo esc_attr( $raffle->id ); ?>" style="height: 35px; line-height: 33px;">
                                        Add Instant Win
                                    </button>
                                </div>

                                <table class="wp-list-table widefat fixed striped posts">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Ticket Number</th>
                                            <th>Prize Name</th>
                                            <th>Status</th>
                                            <th>Winner Email</th>
                                            <th style="width:80px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ( empty( $instant_wins ) ) : ?>
                                            <tr><td colspan="6" style="text-align:center; padding: 15px; color:#64748b;">No instant wins configured.</td></tr>
                                        <?php else : ?>
                                            <?php foreach ( $instant_wins as $iw ) : ?>
                                                <tr>
                                                    <td>#<?php echo esc_html( $iw->id ); ?></td>
                                                    <td><strong><?php echo esc_html( Raffle_Tickets::format_ticket_number( $iw->ticket_number, $raffle->total_tickets ) ); ?></strong></td>
                                                    <td><?php echo esc_html( $iw->prize_name ); ?></td>
                                                    <td>
                                                        <span style="background:<?php echo $iw->status === 'won' ? '#dcfce7; color:#166534;' : '#fef3c7; color:#92400e;'; ?>; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px;">
                                                            <?php echo esc_html( ucfirst( $iw->status ) ); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo esc_html( $iw->winner_email ?: '—' ); ?></td>
                                                    <td>
                                                        <?php if ( $iw->status === 'available' ) : ?>
                                                            <button type="button" class="button button-link-delete delete-instant-win-btn" data-id="<?php echo esc_attr( $iw->id ); ?>">
                                                                Delete
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Draw Verification (post-draw) -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'shield', 'wpr-icon--sm' ); ?> Draw Verification</h2>
                        </div>
                        <div class="inside">
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><label for="draw_video_url">Draw Video URL</label></th>
                                    <td>
                                        <input name="draw_video_url" type="url" id="draw_video_url" class="large-text"
                                               value="<?php echo $raffle ? esc_attr( $raffle->draw_video_url ?? '' ) : ''; ?>"
                                               placeholder="https://www.youtube.com/embed/...">
                                        <p class="description">YouTube or Vimeo embed URL shown on the winners page after the draw.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="verified_result">Verified Result</label></th>
                                    <td>
                                        <textarea name="verified_result" id="verified_result" rows="3" class="large-text"
                                                  placeholder="e.g. Verified by independent auditor on [date]. Draw reference: #ABC123"><?php echo $raffle ? esc_textarea( $raffle->verified_result ?? '' ) : ''; ?></textarea>
                                        <p class="description">Optional text displayed under the winner to confirm the draw was verified.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Form submission actions -->
                    <div style="margin-top: 20px; display:flex; gap:10px;">
                        <button type="submit" class="button button-primary button-large" style="height: 40px; font-size: 14px; line-height: 38px;">
                            <?php echo $raffle ? 'Update Raffle' : 'Create Raffle'; ?>
                        </button>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=raffle-list' ) ); ?>" class="button button-secondary button-large" style="height: 40px; font-size: 14px; line-height: 38px;">
                            Cancel
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </form>
</div>
<script>
jQuery(function($){
    // Charity mode toggle
    $('#charity_mode').on('change', function(){
        var mode = $(this).val();
        if ( mode === 'none' ) {
            $('#charity-select-row, #charity-percent-row').hide();
        } else if ( mode === 'full' ) {
            $('#charity-select-row').show();
            $('#charity-percent-row').hide();
        } else {
            $('#charity-select-row').show();
            $('#charity-percent-row').show();
        }
    });
});
</script>
