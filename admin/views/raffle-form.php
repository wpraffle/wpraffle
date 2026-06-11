<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo $raffle ? 'Edit Raffle' : 'Create New Raffle'; ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=raffle-list' ) ); ?>" class="page-title-action">Back to List</a>
    <hr class="wp-header-end">

    <form method="post" action="" style="margin-top: 20px;">
        <?php wp_nonce_field( 'raffle_save', 'raffle_nonce' ); ?>
        <input type="hidden" name="raffle_form_submit" value="1">
        <?php if ( $raffle ) : ?>
            <input type="hidden" name="raffle_id" value="<?php echo esc_attr( $raffle->id ); ?>">
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
                                        <th scope="row"><label for="prize_value">Prize Value ($) *</label></th>
                                        <td>
                                            <input name="prize_value" type="number" id="prize_value" class="regular-text" step="0.01" min="0" required
                                                   value="<?php echo $raffle ? esc_attr( $raffle->prize_value ) : ''; ?>"
                                                   placeholder="e.g. 1000">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="ticket_price">Price per Ticket ($) *</label></th>
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
                                            <input name="packages" type="text" id="packages" class="regular-text" required
                                                   placeholder="5,10,15,25"
                                                   value="<?php echo $raffle ? esc_attr( implode( ',', json_decode( $raffle->packages, true ) ?: array() ) ) : '5,10,15,25'; ?>">
                                            <p class="description">Comma-separated quantities. E.g: <strong>5, 10, 15, 25</strong>.</p>
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
                                        <th scope="row"><label for="cash_alternative_amount">Cash Alternative Amount ($)</label></th>
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

                    <!-- Meta Box: Templates & Clone -->
                    <?php if ( $raffle ) : ?>
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
