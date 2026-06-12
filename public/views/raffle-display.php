<?php if ( ! defined( 'ABSPATH' ) ) exit;

// Determine settings
$ticket_selection = isset( $raffle->ticket_selection ) ? $raffle->ticket_selection : 'random';
$live_draw_url = isset( $raffle->live_draw_url ) ? $raffle->live_draw_url : '';

$max_tickets = isset( $raffle->max_tickets_per_user ) ? (int) $raffle->max_tickets_per_user : 100;
$enable_question = isset( $raffle->enable_question ) ? (bool) $raffle->enable_question : false;
$question_text = isset( $raffle->question_text ) ? $raffle->question_text : '';
$question_answers = array();
if ( isset( $raffle->question_answers ) ) {
    $question_answers = json_decode( $raffle->question_answers, true ) ?: array();
}
$question_answers = array_filter( array_map( 'trim', $question_answers ) );
// NOTE: correct_answer_index is NOT exposed client-side for security.
// The answer is validated server-side only.

$postal_instructions = isset( $raffle->postal_instructions ) && ! empty( $raffle->postal_instructions ) 
    ? $raffle->postal_instructions 
    : "To enter this competition for free by post, please send your Name, Address, Email, Phone Number, and correct answer to the skill question on a postcard to: Paragon Competitions Ltd, 123 Main Street, London, EC1A 1BB. Postal entries must be received before the draw date to be eligible.";
?>

<div class="raffle-container" data-raffle-id="<?php echo esc_attr( $raffle->id ); ?>">

    <?php 
    $r_computed_state = Raffle_Public::get_raffle_state( $raffle );
    if ( $r_computed_state === 'ended' ) : ?>
        <div class="raffle-finished-banner" style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 15px 20px; border-radius: 8px; font-weight: 700; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; font-size: 16px;">
            <?php echo wpr_get_icon( 'flag', 'wpr-icon--lg' ); ?>
            <span>This raffle has ended</span>
        </div>
    <?php elseif ( $r_computed_state === 'draft' ) : ?>
        <div class="raffle-finished-banner" style="background: #fef3c7; border: 1px solid #fde68a; color: #92400e; padding: 15px 20px; border-radius: 8px; font-weight: 700; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; font-size: 16px;">
            <?php echo wpr_get_icon( 'edit', 'wpr-icon--lg' ); ?>
            <span>PREVIEW: This raffle is in DRAFT / SCHEDULING mode</span>
        </div>
    <?php endif; ?>

    <!-- Top Metadata Stats Header -->
    <div class="raffle-stats-header">
        <div class="raffle-stat-box">
            <?php echo wpr_get_icon( 'ticket', 'wpr-icon--md' ); ?>
            <span class="raffle-stat-text">Max <?php echo number_format( $max_tickets ); ?> tickets per user</span>
        </div>
        <div class="raffle-stat-box">
            <?php echo wpr_get_icon( 'ticket', 'wpr-icon--md' ); ?>
            <span class="raffle-stat-text"><?php echo number_format( $raffle->total_tickets ); ?> tickets available</span>
        </div>
        <?php if ( $raffle->draw_date ) : ?>
            <div class="raffle-stat-box">
                <?php echo wpr_get_icon( 'calendar', 'wpr-icon--md' ); ?>
                <span class="raffle-stat-text">Live Draw on <?php echo date_i18n( 'jS F @ g:iA', strtotime( $raffle->draw_date ) ); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main Grid Wrapper -->
    <div class="raffle-main-grid<?php echo empty( $raffle->prize_image ) ? ' raffle-no-image' : ''; ?>">
        <?php if ( $raffle->prize_image ) : ?>
        <!-- Left Column: Image -->
        <div class="raffle-left-col">
                <div class="raffle-image-card">
                    <img src="<?php echo esc_url( $raffle->prize_image ); ?>" alt="<?php echo esc_attr( $raffle->title ); ?>">
                    <?php if ( ! empty( $raffle->enable_cash_alternative ) ) : ?>
                        <div class="raffle-image-overlay-badge">+ Cash Alternative Available</div>
                    <?php endif; ?>
                </div>
        </div>
        <?php endif; ?>

        <!-- Right Column: Buying Box -->
        <div class="raffle-right-col">
            <!-- Draw Date Banner -->
            <?php if ( $raffle->draw_date ) : ?>
                <div class="raffle-draw-banner-green">
                    DRAW <?php echo strtoupper( date_i18n( 'D jS M @ g:i A', strtotime( $raffle->draw_date ) ) ); ?>
                </div>
            <?php endif; ?>

            <!-- Cash Alternative Banner -->
            <?php if ( ! empty( $raffle->enable_cash_alternative ) ) : ?>
                <div class="raffle-cash-banner-dark">
                    CASH ALTERNATIVE: <?php echo esc_html( wpr_price( $raffle->cash_alternative_amount ) ); ?>
                </div>
            <?php endif; ?>

            <!-- Title -->
            <h1 class="raffle-title-main"><?php echo esc_html( $raffle->title ); ?></h1>

            <!-- Entry Price -->
            <div class="raffle-price-row">
                <span class="raffle-price-value"><?php echo esc_html( wpr_price( $raffle->ticket_price ) ); ?></span>
                <span class="raffle-price-label">PER ENTRY</span>
            </div>

            <!-- Progress Bar -->
            <div class="raffle-progress-box-custom">
                <div class="raffle-progress-meta-row">
                    <span class="raffle-progress-label-percent">Sold: <?php echo esc_html( $progress ); ?>%</span>
                    <span class="raffle-progress-label-numbers"><?php echo esc_html( $raffle->sold_tickets ); ?> of <?php echo esc_html( $raffle->total_tickets ); ?></span>
                </div>
                <div class="raffle-progress-bar-wrap">
                    <div class="raffle-progress-bar-inner" style="width: <?php echo esc_attr( $progress ); ?>%;"></div>
                </div>
            </div>

            <!-- Tabs Selection -->
            <div class="raffle-entry-tabs">
                <button type="button" class="raffle-tab-btn active" data-tab="online">ONLINE ENTRY</button>
                <button type="button" class="raffle-tab-btn" data-tab="postal">FREE POSTAL ENTRY</button>
            </div>

            <!-- Tab Content Area -->
            <div class="raffle-tab-contents">
                
                <!-- Online Entry -->
                <div class="raffle-tab-content active" id="tab-online">
                    
                    <!-- Skill Question -->
                    <?php if ( $enable_question && ! empty( $question_answers ) ) : ?>
                        <div class="raffle-question-wrapper">
                            <h3 class="raffle-question-title"><?php echo esc_html( $question_text ); ?></h3>
                            <div class="raffle-question-options">
                                <?php foreach ( $question_answers as $idx => $opt ) : ?>
                                    <label class="raffle-question-option-card">
                                        <input type="radio" name="raffle_skill_answer" value="<?php echo $idx; ?>">
                                        <span class="raffle-question-option-dot"></span>
                                        <span class="raffle-question-option-text"><?php echo esc_html( $opt ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="raffle-question-error" style="display: none;"></div>
                        </div>
                    <?php endif; ?>

                    <?php if ( $r_computed_state === 'ended' ) : ?>
                        <!-- Ended State Results Card -->
                        <div class="raffle-ended-results-card" style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 25px; margin-top: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); display: flex; flex-direction: column; gap: 20px;">
                            
                            <h4 style="margin: 0; font-size: 18px; font-weight: 700; color: #1f2937; border-bottom: 1px solid #f3f4f6; padding-bottom: 10px;"><?php echo wpr_get_icon( 'trophy', 'wpr-icon--sm' ); ?> COMPETITION RESULTS</h4>
                            
                            <!-- Stats -->
                            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                                <div style="flex: 1; min-width: 120px; background: #fafafa; border: 1px solid #f3f4f6; padding: 12px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase;">Total Entries</div>
                                    <div style="font-size: 18px; font-weight: 800; color: #1f2937; margin-top: 4px;"><?php echo esc_html( $raffle->sold_tickets ); ?> / <?php echo esc_html( $raffle->total_tickets ); ?></div>
                                </div>
                                <div style="flex: 1; min-width: 120px; background: #fafafa; border: 1px solid #f3f4f6; padding: 12px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase;">Ticket Price</div>
                                    <div style="font-size: 18px; font-weight: 800; color: #1f2937; margin-top: 4px;"><?php echo esc_html( wpr_price( $raffle->ticket_price ) ); ?></div>
                                </div>
                            </div>

                            <!-- Main Winner -->
                            <div style="background: #e0e7ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 15px; text-align: center; border-left: 5px solid #4f46e5;">
                                <div style="font-size: 12px; color: #4338ca; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;">Main Draw Winner</div>
                                <?php 
                                global $wpdb;
                                $winner_info = null;
                                if ( $raffle->winner_ticket_id ) {
                                    $winner_info = $wpdb->get_row( $wpdb->prepare(
                                        "SELECT t.ticket_number, p.buyer_name, p.buyer_email
                                         FROM {$wpdb->prefix}raffle_tickets t
                                         JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id
                                         WHERE t.id = %d",
                                        $raffle->winner_ticket_id
                                    ) );
                                }
                                if ( $winner_info ) : 
                                    $winner_initials = class_exists('Raffle_Instant_Wins') ? Raffle_Instant_Wins::get_initials( $winner_info->buyer_name ) : '';
                                    $total_digits = strlen( (string) $raffle->total_tickets );
                                    $formatted_num = str_pad( $winner_info->ticket_number, $total_digits, '0', STR_PAD_LEFT );
                                ?>
                                    <div style="font-size: 22px; font-weight: 800; color: #312e81;"><?php echo esc_html( $winner_initials ); ?></div>
                                    <div style="font-size: 13px; color: #4338ca; margin-top: 4px;">
                                        Winning Ticket: <strong style="font-family: monospace; background: #fff; padding: 2px 6px; border-radius: 4px; border: 1px solid #c7d2fe;"><?php echo esc_html( $formatted_num ); ?></strong>
                                    </div>
                                <?php else : ?>
                                    <div style="font-size: 14px; color: #4b5563; font-weight: 600;">Draw pending / no winner selected yet</div>
                                <?php endif; ?>
                            </div>

                            <!-- Instant Wins Grid -->
                            <div>
                                <div style="font-size: 14px; font-weight: 700; color: #1f2937; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                                    <?php echo wpr_get_icon( 'gift', 'wpr-icon--sm' ); ?> Claimed Instant Wins
                                </div>
                                <?php
                                $instant_wins = $wpdb->get_results( $wpdb->prepare(
                                    "SELECT iw.*, p.buyer_name as winner_name 
                                     FROM {$wpdb->prefix}raffle_instant_wins iw
                                     LEFT JOIN {$wpdb->prefix}raffle_purchases p ON iw.purchase_id = p.id
                                     WHERE iw.raffle_id = %d AND iw.status = 'won'
                                     ORDER BY iw.created_at DESC",
                                    $raffle->id
                                ) );
                                if ( ! empty( $instant_wins ) ) : ?>
                                    <div style="max-height: 200px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px;">
                                        <?php foreach ( $instant_wins as $iw ) : 
                                            $iw_initials = class_exists('Raffle_Instant_Wins') ? Raffle_Instant_Wins::get_initials( $iw->winner_name ) : '';
                                            $total_digits = strlen( (string) $raffle->total_tickets );
                                            $formatted_iw_num = str_pad( $iw->ticket_number, $total_digits, '0', STR_PAD_LEFT );
                                        ?>
                                            <div style="display: flex; justify-content: space-between; align-items: center; background: #fafafa; border: 1px solid #f3f4f6; padding: 10px; border-radius: 8px; font-size: 13px;">
                                                <div>
                                                    <strong style="color: #374151;"><?php echo esc_html( $iw->prize_name ); ?></strong>
                                                    <div style="font-size: 11px; color: #9ca3af; margin-top: 2px;">Ticket: #<?php echo esc_html( $formatted_iw_num ); ?></div>
                                                </div>
                                                <span style="background: #d1fae5; color: #065f46; font-weight: 700; padding: 2px 8px; border-radius: 12px; font-size: 11px; text-transform: uppercase;">
                                                    <?php echo esc_html( $iw_initials ); ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    <div style="font-size: 13px; color: #9ca3af; font-style: italic; text-align: center; padding: 15px 0; border: 1px dashed #e5e7eb; border-radius: 8px;">
                                        No instant wins were claimed in this competition.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Draw Video -->
                            <?php if ( ! empty( $raffle->draw_video_url ) ) : ?>
                                <div style="border-radius: 8px; overflow: hidden;">
                                    <iframe width="100%" height="315" src="<?php echo esc_url( $raffle->draw_video_url ); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                </div>
                            <?php endif; ?>

                            <!-- Verified Result -->
                            <?php if ( ! empty( $raffle->verified_result ) ) : ?>
                                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #1e40af; display: flex; align-items: flex-start; gap: 8px;">
                                    <span style="flex-shrink: 0;"><?php echo wpr_get_icon( 'check-circle', 'wpr-icon--sm', 'Verified' ); ?></span>
                                    <span><?php echo esc_html( $raffle->verified_result ); ?></span>
                                </div>
                            <?php endif; ?>

                        </div>
                    <?php elseif ( $r_computed_state === 'draft' ) : ?>
                        <!-- Draft State Coming Soon Notice -->
                        <div style="background: #fafafa; border: 2px dashed #d1d5db; border-radius: 12px; padding: 30px; text-align: center; margin-top: 15px;">
                            <span style="font-size: 36px; display: block; margin-bottom: 10px;">⏳</span>
                            <h4 style="margin: 0 0 6px 0; font-size: 18px; font-weight: 700; color: #374151;">Coming Soon</h4>
                            <p style="margin: 0; color: #6b7280; font-size: 14px;">This competition is not active yet. Check back soon for your chance to enter!</p>
                            <?php if ( ! empty( $raffle->start_date ) ) : ?>
                                <div style="margin-top: 15px; font-size: 14px; color: #4b5563; font-weight: 600;">
                                    Scheduled start: <?php echo esc_html( date_i18n( 'jS F Y @ g:i a', strtotime( $raffle->start_date ) ) ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <!-- Quick Select -->
                        <?php
                        // Use admin-configured packages, filtered by availability and max per-user limit
                        $display_packages = array_filter( $packages, function( $qty ) use ( $max_tickets, $remaining ) {
                            return $qty >= 1 && $qty <= $max_tickets && $qty <= $remaining;
                        } );
                        // Fallback defaults if no packages configured or all filtered out
                        if ( empty( $display_packages ) ) {
                            $display_packages = array_filter( array( 5, 10, 15, 25 ), function( $qty ) use ( $max_tickets, $remaining ) {
                                return $qty >= 1 && $qty <= $max_tickets && $qty <= $remaining;
                            } );
                        }
                        ?>
                        <?php if ( ! empty( $display_packages ) ) : ?>
                        <div class="raffle-quick-select-qty">
                            <span class="raffle-qty-heading">QUICK SELECT QUANTITY</span>
                            <div class="raffle-qty-pills-row">
                                <?php foreach ( $display_packages as $pkg_qty ) : ?>
                                    <button type="button" class="raffle-qty-pill" data-qty="<?php echo esc_attr( $pkg_qty ); ?>"><?php echo esc_html( $pkg_qty ); ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Slider selector -->
                        <div class="raffle-slider-qty-selector">
                            <div class="raffle-slider-controls">
                                <button type="button" class="raffle-slider-btn minus">-</button>
                                <div class="raffle-slider-track-wrap">
                                    <input type="range" id="raffle-qty-range-slider" min="1" max="<?php echo esc_attr( min( $max_tickets, $remaining ) ); ?>" value="1">
                                    <div class="raffle-slider-tooltip" id="raffle-qty-slider-tooltip">1 TICKET</div>
                                </div>
                                <button type="button" class="raffle-slider-btn plus">+</button>
                            </div>
                            <div class="raffle-manual-qty-input-wrap">
                                <input type="number" id="raffle-manual-qty-num" min="1" max="<?php echo esc_attr( min( $max_tickets, $remaining ) ); ?>" value="1">
                            </div>
                        </div>

                        <!-- Enter Button -->
                        <div class="raffle-enter-action-wrapper">
                            <?php if ( $remaining > 0 ) : ?>
                                <button type="button" class="raffle-enter-comp-btn" id="raffle-enter-comp-submit-btn">
                                    ENTER COMPETITION
                                </button>
                            <?php else : ?>
                                <div class="raffle-sold-out-badge">SOLD OUT</div>
                            <?php endif; ?>
                        </div>

                        <!-- Countdown -->
                        <?php if ( $raffle->draw_date ) : ?>
                            <div class="raffle-countdown-timer-inline" id="raffle-countdown-inline"
                                 data-draw-date="<?php echo esc_attr( gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $raffle->draw_date ) ) ); ?>">
                                <div class="raffle-cd-box">
                                    <span class="raffle-cd-num" id="cd-inline-days">00</span>
                                    <span class="raffle-cd-lbl">DAYS</span>
                                </div>
                                <div class="raffle-cd-box">
                                    <span class="raffle-cd-num" id="cd-inline-hours">00</span>
                                    <span class="raffle-cd-lbl">HRS</span>
                                </div>
                                <div class="raffle-cd-box">
                                    <span class="raffle-cd-num" id="cd-inline-minutes">00</span>
                                    <span class="raffle-cd-lbl">MINS</span>
                                </div>
                                <div class="raffle-cd-box">
                                    <span class="raffle-cd-num" id="cd-inline-seconds">00</span>
                                    <span class="raffle-cd-lbl">SECS</span>
                                </div>
                            </div>
                            
                            <div class="raffle-countdown-expired-inline" id="raffle-countdown-expired-inline" style="display:none; margin-top:20px;">
                                <?php echo wpr_get_icon( 'zap', 'wpr-icon--md wpr-icon--primary', 'Draw' ); ?> It's draw time!
                                <?php if ( $live_draw_url ) : ?>
                                    <div style="margin-top: 15px;">
                                        <iframe width="100%" height="315" src="<?php echo esc_url( $live_draw_url ); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Free Postal Entry -->
                <div class="raffle-tab-content" id="tab-postal" style="display: none;">
                    <div class="raffle-postal-info-card">
                        <p><?php echo wp_kses_post( nl2br( $postal_instructions ) ); ?></p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php if ( $r_computed_state === 'live' ) : ?>

        <?php if ( $ticket_selection === 'manual' ) : ?>
            <div id="raffle-manual-selection" style="display:none; margin: 20px 0; background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                <h3 style="margin-top:0;">Pick Your <span id="manual-qty-target">0</span> Numbers</h3>
                <p>Click on the available numbers below to select them. <span style="float:right;"><span id="manual-qty-selected">0</span> selected</span></p>
                <div id="raffle-number-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(60px, 1fr)); gap:10px; max-height:400px; overflow-y:auto; padding:10px 0;">
                    <!-- Populated by JS -->
                </div>
                <div style="margin-top:15px; text-align:right;">
                    <button class="rs-btn rs-btn-secondary" id="cancel-manual-selection">Cancel</button>
                    <button class="rs-btn rs-btn-primary" id="confirm-manual-selection" disabled>Confirm Numbers</button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Trust -->
        <div class="raffle-trust" style="margin-top: 25px;">
            <div class="raffle-trust-item"><?php echo wpr_get_icon( 'shield', 'wpr-icon--sm' ); ?> Secure purchase</div>
            <div class="raffle-trust-item"><?php echo wpr_get_icon( 'mail', 'wpr-icon--sm' ); ?> Instant confirmation</div>
            <div class="raffle-trust-item"><?php echo wpr_get_icon( 'refresh', 'wpr-icon--sm' ); ?> Random numbers</div>
        </div>

    <?php endif; ?>

    <!-- Instant Wins Section (ALWAYS DISPLAYED) -->
    <?php 
    $instant_wins = Raffle_Instant_Wins::get_instant_wins( $raffle->id );
    if ( ! empty( $instant_wins ) ) :
        // Group by prize_name for quantity display
        $iw_grouped = array();
        foreach ( $instant_wins as $iw ) {
            $key = $iw->prize_name;
            if ( ! isset( $iw_grouped[ $key ] ) ) {
                $iw_grouped[ $key ] = array( 'total' => 0, 'won' => 0, 'available' => 0, 'last_won' => null );
            }
            $iw_grouped[ $key ]['total']++;
            if ( $iw->status === 'won' ) {
                $iw_grouped[ $key ]['won']++;
                $iw_grouped[ $key ]['last_won'] = $iw;
            } else {
                $iw_grouped[ $key ]['available']++;
            }
        }
    ?>
        <div class="raffle-instant-wins-section" style="margin-top: 30px; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #f3f4f6; text-align: left;">
            <h3 style="margin-top: 0; margin-bottom: 8px; font-size: 20px; font-weight: 700; color: #1f2937; display: flex; align-items: center; gap: 8px;">
                <?php echo wpr_get_icon( 'gift', 'wpr-icon--sm' ); ?> Instant Win Prizes
            </h3>
            <p style="margin-top: 0; margin-bottom: 20px; color: #6b7280; font-size: 14px;">Purchase tickets for this raffle and win any of these prizes instantly if your ticket number matches!</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">
                <?php foreach ( $iw_grouped as $iw_prize_name => $iw_group ) :
                    $iw_all_won = $iw_group['available'] === 0;
                    $iw_remaining = $iw_group['total'] > 1 ? $iw_group['available'] . ' of ' . $iw_group['total'] . ' remaining' : '';
                ?>
                    <div style="background: <?php echo $iw_all_won ? '#f9fafb' : '#fff8f1'; ?>; border: 1px solid <?php echo $iw_all_won ? '#e5e7eb' : '#ffedd5'; ?>; padding: 15px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; gap: 10px; position: relative; overflow: hidden; opacity: <?php echo $iw_all_won ? 0.75 : 1; ?>;">
                        <?php if ( ! $iw_all_won ) : ?>
                            <div style="position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: #f97316;"></div>
                        <?php endif; ?>
                        <div>
                            <div style="font-weight: 700; color: #374151; font-size: 15px; margin-bottom: 4px;"><?php echo esc_html( $iw_prize_name ); ?></div>
                            <?php if ( $iw_remaining ) : ?>
                                <div style="font-size: 13px; color: <?php echo $iw_all_won ? '#9ca3af' : '#ea580c'; ?>; font-weight: 600;">
                                    <?php echo esc_html( $iw_remaining ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ( $iw_all_won ) : ?>
                                <?php
                                $initials = '';
                                if ( $iw_group['last_won'] && ! empty( $iw_group['last_won']->winner_name ) ) {
                                    $initials = Raffle_Instant_Wins::get_initials( $iw_group['last_won']->winner_name );
                                }
                                ?>
                                <span style="background: #e5e7eb; color: #374151; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                    All claimed <?php echo $initials ? '(' . esc_html( $initials ) . ')' : ''; ?>
                                </span>
                            <?php else : ?>
                                <span style="background: #ffedd5; color: #ea580c; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px;<?php echo $iw_group['total'] > 1 ? '' : ' animation: rs-pulse 2s infinite;'; ?>">
                                    <span style="width: 6px; height: 6px; background: #ea580c; border-radius: 50%;"></span> <?php echo $iw_group['total'] > 1 ? esc_html( $iw_group['available'] ) . ' left' : 'Available'; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <style>
        @keyframes rs-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        </style>
    <?php endif; ?>

    <!-- Accordion: Description / Rules / FAQ -->
    <?php
    $legal = wp_parse_args( get_option( 'wpraffle_legal_settings', array() ), array(
        'rules_template' => '',
        'faq_template'   => '',
    ) );
    $rules_text = ! empty( $legal['rules_template'] ) ? wpraffle_replace_placeholders( $legal['rules_template'], $raffle ) : '';
    $faq_items  = array();
    if ( ! empty( $legal['faq_items'] ) ) {
        $faq_items = is_string( $legal['faq_items'] ) ? json_decode( $legal['faq_items'], true ) : $legal['faq_items'];
        // Apply placeholders to each item
        foreach ( $faq_items as &$fi ) {
            $fi['q'] = wpraffle_replace_placeholders( $fi['q'], $raffle );
            $fi['a'] = wpraffle_replace_placeholders( $fi['a'], $raffle );
        }
        unset( $fi );
    } elseif ( ! empty( $legal['faq_template'] ) ) {
        $faq_raw   = wpraffle_replace_placeholders( $legal['faq_template'], $raffle );
        $faq_items = wpraffle_parse_faq( $faq_raw );
    }
    $has_desc   = ! empty( $raffle->description );
    $has_rules  = ! empty( $rules_text );
    $has_faq    = ! empty( $faq_items );
    ?>
    <?php if ( $has_desc || $has_rules || $has_faq ) : ?>
    <div class="rs-accordion" style="margin-top: 30px;">

        <?php if ( $has_desc ) : ?>
        <div class="rs-accordion-item">
            <button type="button" class="rs-accordion-header" aria-expanded="false">
                <span>Prize Description</span>
                <svg class="rs-accordion-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </button>
            <div class="rs-accordion-body">
                <div style="color: #4b5563; line-height: 1.7; font-size: 15px;"><?php echo wp_kses_post( nl2br( $raffle->description ) ); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $has_rules ) : ?>
        <div class="rs-accordion-item">
            <button type="button" class="rs-accordion-header" aria-expanded="false">
                <span>Raffle Rules</span>
                <svg class="rs-accordion-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </button>
            <div class="rs-accordion-body">
                <div style="color: #4b5563; line-height: 1.7; font-size: 15px;"><?php echo wp_kses_post( nl2br( $rules_text ) ); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $has_faq ) : ?>
        <div class="rs-accordion-item">
            <button type="button" class="rs-accordion-header" aria-expanded="false">
                <span>Frequently Asked Questions</span>
                <svg class="rs-accordion-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </button>
            <div class="rs-accordion-body">
                <div class="rs-faq-list">
                    <?php foreach ( $faq_items as $faq ) : ?>
                    <div class="rs-faq-item">
                        <div class="rs-faq-q"><?php echo esc_html( $faq['q'] ); ?></div>
                        <div class="rs-faq-a"><?php echo nl2br( esc_html( $faq['a'] ) ); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <style>
    .rs-accordion { border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; }
    .rs-accordion-item { border-bottom: 1px solid #f3f4f6; }
    .rs-accordion-item:last-child { border-bottom: none; }
    .rs-accordion-header { display: flex; justify-content: space-between; align-items: center; width: 100%; padding: 18px 22px; background: none; border: none; cursor: pointer; font-size: 16px; font-weight: 700; color: #1f2937; text-align: left; transition: background 0.2s; }
    .rs-accordion-header:hover { background: #f9fafb; }
    .rs-accordion-chevron { width: 20px; height: 20px; transition: transform 0.3s; flex-shrink: 0; color: #9ca3af; }
    .rs-accordion-open .rs-accordion-chevron { transform: rotate(180deg); }
    .rs-accordion-body { padding: 0 22px 20px; display: none; }
    .rs-accordion-open + .rs-accordion-body { display: block; }
    .rs-faq-list { display: flex; flex-direction: column; gap: 14px; }
    .rs-faq-item { background: #f9fafb; border-radius: 8px; padding: 14px 16px; }
    .rs-faq-q { font-weight: 700; color: #1f2937; font-size: 14px; margin-bottom: 4px; }
    .rs-faq-a { color: #6b7280; font-size: 14px; line-height: 1.6; }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.rs-accordion-header').forEach(function(header) {
            header.addEventListener('click', function() {
                var body = header.nextElementSibling;
                var isOpen = header.classList.contains('rs-accordion-open');
                if (isOpen) {
                    header.classList.remove('rs-accordion-open');
                    header.setAttribute('aria-expanded', 'false');
                    body.style.display = 'none';
                } else {
                    header.classList.add('rs-accordion-open');
                    header.setAttribute('aria-expanded', 'true');
                    body.style.display = 'block';
                }
            });
        });
    });
    </script>
    <?php endif; ?>

    <?php if ( $r_computed_state === 'live' ) : ?>

        <!-- Purchase Modal -->
        <div class="raffle-modal" id="raffle-modal" style="display:none;">
            <div class="raffle-modal-content">
                <span class="raffle-modal-close">&times;</span>
                <div class="raffle-modal-header">
                    <h3>Complete Purchase</h3>
                    <p class="raffle-modal-summary"></p>
                </div>
                <form id="raffle-purchase-form">
                    <input type="hidden" name="raffle_id" value="<?php echo esc_attr( $raffle->id ); ?>">
                    <input type="hidden" name="quantity" id="raffle-quantity" value="">
                    <input type="hidden" name="selected_numbers" id="raffle-selected-numbers" value="">
                    <!-- Hidden field to pass answer selection index -->
                    <input type="hidden" name="answer_index" id="raffle-answer-index" value="-1">
                    
                    <div class="raffle-form-group">
                        <label for="buyer_name">Full name</label>
                        <input type="text" id="buyer_name" name="buyer_name" required
                               placeholder="E.g.: John Smith">
                    </div>
                    <div class="raffle-form-group">
                        <label for="buyer_email">Email address</label>
                        <input type="email" id="buyer_email" name="buyer_email" required
                               placeholder="john@example.com">
                    </div>
                    <button type="submit" class="raffle-submit-btn">
                        <?php if ( Raffle_WooCommerce::is_available() ) : ?>
                            <span class="raffle-submit-btn-icon"><?php echo wpr_get_icon( 'ticket', 'wpr-icon--xs' ); ?></span> Proceed to Payment
                        <?php else : ?>
                            <span class="raffle-submit-btn-icon"><?php echo wpr_get_icon( 'ticket', 'wpr-icon--xs' ); ?></span> Confirm Purchase
                        <?php endif; ?>
                    </button>
                </form>
                <div class="raffle-loading" style="display:none;">
                    <div class="raffle-spinner"></div>
                    <span>Processing your purchase...</span>
                </div>
                <div class="raffle-modal-secure"><?php echo wpr_get_icon( 'shield', 'wpr-icon--xs' ); ?> Your data is protected</div>
            </div>
        </div>

        <!-- Confirmation -->
        <div class="raffle-confirmation" id="raffle-confirmation" style="display:none;">
            <div class="raffle-confirmation-content">
                <span class="raffle-modal-close">&times;</span>
                <div class="raffle-confirmation-icon"><?php echo wpr_get_icon( 'star', 'wpr-icon--2xl wpr-icon--primary', 'Success' ); ?></div>
                <h3>Purchase Successful!</h3>
                <p>Your ticket numbers:</p>
                <div class="raffle-ticket-numbers" id="raffle-ticket-numbers"></div>
                <p class="raffle-confirmation-email">
                    <?php echo wpr_get_icon( 'mail', 'wpr-icon--xs' ); ?> A confirmation email with your numbers has been sent.
                </p>
            </div>
        </div>

    <?php endif; ?>

</div>
