<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$total_tickets = $raffle->total_tickets;
$revenue      = $wpdb->get_var( $wpdb->prepare(
    "SELECT SUM(total_amount) FROM {$wpdb->prefix}raffle_purchases WHERE raffle_id = %d AND payment_status = 'completed'",
    $raffle->id
) );
$remaining = $raffle->total_tickets - $raffle->sold_tickets;
$progress  = ( $raffle->total_tickets > 0 ) ? round( ( $raffle->sold_tickets / $raffle->total_tickets ) * 100 ) : 0;

$instant_wins = Raffle_Instant_Wins::get_instant_wins( $raffle->id );
?>

<div class="wrap">
    <?php
    // 1.3.0 — status badge reflects finished/failed/extended/active/draft.
    $status_map = array(
        'active'   => array( 'label' => 'Active',   'bg' => '#dcfce7', 'fg' => '#166534' ),
        'finished' => array( 'label' => 'Finished', 'bg' => '#f3f4f6', 'fg' => '#374151' ),
        'failed'   => array( 'label' => 'Failed',   'bg' => '#fee2e2', 'fg' => '#991b1b' ),
        'extended' => array( 'label' => 'Extended', 'bg' => '#dbeafe', 'fg' => '#1e40af' ),
        'draft'    => array( 'label' => 'Draft',    'bg' => '#f3f4f6', 'fg' => '#6b7280' ),
    );
    $sb = $status_map[ $raffle->status ] ?? $status_map['finished'];
    if ( ! empty( $raffle->fail_reason ) ) {
        $sb['label'] .= ' (' . ( $raffle->fail_reason === 'min_tickets' ? 'min tickets' : 'min entrants' ) . ')';
    }
    ?>
    <h1 class="wp-heading-inline">
        <?php echo esc_html( $raffle->title ); ?>
        <span style="background:<?php echo esc_attr( $sb['bg'] ); ?>; color:<?php echo esc_attr( $sb['fg'] ); ?>; padding:4px 10px; border-radius:4px; font-weight:600; font-size:13px; margin-left:10px; display:inline-block; vertical-align:middle;">
            <?php echo esc_html( $sb['label'] ); ?>
        </span>
    </h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=raffle-list' ) ); ?>" class="page-title-action">Back to List</a>
    <a href="<?php echo esc_url( admin_url( "admin.php?page=raffle-list&action=edit&id={$raffle->id}" ) ); ?>" class="page-title-action text-primary">Edit Raffle</a>

    <?php
    // 1.3.0 — Lifecycle action notices + Extend / Relist buttons.
    if ( isset( $_GET['lifecycle_error'] ) ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( wp_unslash( $_GET['lifecycle_error'] ) ) . '</p></div>';
    }
    if ( isset( $_GET['lifecycle_ok'] ) ) {
        $ok_msg = $_GET['lifecycle_ok'] === 'extended' ? __( 'Raffle extended and reopened for entries.', 'wpraffle' ) : __( 'Raffle relisted with a fresh entry slate.', 'wpraffle' );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $ok_msg ) . '</p></div>';
    }
    // 1.3.0 — Wallet payout re-sync result notice.
    if ( isset( $_GET['wallet_sync_done'] ) ) {
        $sync_msg = isset( $_GET['wallet_sync_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['wallet_sync_msg'] ) ) : __( 'Wallet payouts re-synced.', 'wpraffle' );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $sync_msg ) . '</p></div>';
    }
    // 1.3.0 — Manual wallet/credit payout re-sync button (always shown; idempotent).
    $pending_payouts = class_exists( 'Raffle_Wallet_Adapter' ) ? Raffle_Wallet_Adapter::count_pending_payouts( $raffle->id ) : 0;
    $sync_url = wp_nonce_url( admin_url( "admin.php?page=raffle-list&action=sync_wallet&id={$raffle->id}" ), 'raffle_sync_wallet_' . $raffle->id );
    ?>
    <div style="margin: 12px 0; display:flex; gap:10px; align-items:center; flex-wrap:wrap; background:#fffbeb; border:1px solid #fde68a; padding:10px 14px; border-radius:4px;">
        <strong style="font-size:13px; color:#92400e;">Wallet / Credit payouts:</strong>
        <?php if ( $pending_payouts > 0 ) : ?>
            <span style="font-size:12px; color:#b45309; font-weight:600;"><?php
            /* translators: %d: number of pending payouts. */
            echo esc_html( sprintf( _n( '%d pending payout awaiting sync.', '%d pending payouts awaiting sync.', $pending_payouts, 'wpraffle' ), $pending_payouts ) );
            ?></span>
            <a href="<?php echo esc_url( $sync_url ); ?>" class="button button-primary" style="font-size:12px;">
                <?php esc_html_e( 'Sync Wallet Payouts', 'wpraffle' ); ?>
            </a>
        <?php else : ?>
            <span style="font-size:12px; color:#6b7280;">All payouts credited. </span>
            <a href="<?php echo esc_url( $sync_url ); ?>" class="button button-secondary" style="font-size:12px;">
                <?php esc_html_e( 'Re-check payouts', 'wpraffle' ); ?>
            </a>
        <?php endif; ?>
        <span style="font-size:11px; color:#9ca3af;">Re-processes any instant-win credit prizes that didn't reach the wallet. Safe to run repeatedly.</span>
    </div>
    <?php
    // Show extend/relist for finished or failed raffles (relist only for finished/failed; extend for failed).
    if ( in_array( $raffle->status, array( 'finished', 'failed' ), true ) ) :
        $default_new_date = gmdate( 'Y-m-d\TH:i', time() + ( 7 * DAY_IN_SECONDS ) );
        ?>
        <div style="margin: 12px 0; display:flex; gap:10px; align-items:center; flex-wrap:wrap; background:#fff; border:1px solid #c3c4c7; padding:12px; border-radius:4px;">
            <label for="lifecycle_new_date" style="font-weight:600; font-size:13px;">New draw date:</label>
            <input type="datetime-local" id="lifecycle_new_date" value="<?php echo esc_attr( $default_new_date ); ?>" class="small-text">
            <a href="#" class="button button-secondary" id="raffle-extend-btn"
               data-url-base="<?php echo esc_attr( wp_nonce_url( admin_url( "admin.php?page=raffle-list&action=extend&id={$raffle->id}" ), 'raffle_extend_' . $raffle->id ) ); ?>">
                <?php esc_html_e( 'Extend Deadline', 'wpraffle' ); ?>
            </a>
            <a href="#" class="button button-primary" id="raffle-relist-btn"
               data-url-base="<?php echo esc_attr( wp_nonce_url( admin_url( "admin.php?page=raffle-list&action=relist&id={$raffle->id}" ), 'raffle_relist_' . $raffle->id ) ); ?>">
                <?php esc_html_e( 'Relist Raffle', 'wpraffle' ); ?>
            </a>
            <span style="font-size:12px; color:#6b7280;">
                <?php esc_html_e( 'Extend pushes the deadline out (keeps entries). Relist wipes entries and re-runs in place (preserves the permalink).', 'wpraffle' ); ?>
            </span>
        </div>
        <script>
        (function(){
            function wire(id){
                var btn = document.getElementById(id);
                if (!btn) return;
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    var d = document.getElementById('lifecycle_new_date');
                    var date = d ? d.value : '';
                    var base = btn.dataset.urlBase;
                    var sep = base.indexOf('?') === -1 ? '?' : '&';
                    if (!window.confirm(btn.textContent.trim() + '?')) return;
                    window.location.href = base + ( date ? sep + 'new_draw_date=' + encodeURIComponent(date) : '' );
                });
            }
            wire('raffle-extend-btn');
            wire('raffle-relist-btn');
        })();
        </script>
    <?php endif; ?>
    <hr class="wp-header-end">

    <!-- Stats summary grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top:20px;">
        <div class="card" style="margin: 0; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
            <h2 style="margin: 0 0 5px; font-size: 14px; color: #64748b;">Tickets Sold</h2>
            <p style="margin: 0; font-size: 20px; font-weight: 700; color: #1d2327;"><?php echo esc_html( $raffle->sold_tickets ); ?> / <?php echo esc_html( $raffle->total_tickets ); ?></p>
        </div>
        <div class="card" style="margin: 0; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
            <h2 style="margin: 0 0 5px; font-size: 14px; color: #64748b;">Tickets Remaining</h2>
            <p style="margin: 0; font-size: 20px; font-weight: 700; color: #1d2327;"><?php echo esc_html( $remaining ); ?></p>
        </div>
        <div class="card" style="margin: 0; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
            <h2 style="margin: 0 0 5px; font-size: 14px; color: #64748b;">Revenue Generated</h2>
            <p style="margin: 0; font-size: 20px; font-weight: 700; color: #10b981;"><?php echo esc_html( wpr_price( $revenue ?: 0 ) ); ?></p>
        </div>
        <div class="card" style="margin: 0; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
            <h2 style="margin: 0 0 5px; font-size: 14px; color: #64748b;">Draw Date</h2>
            <p style="margin: 0; font-size: 16px; font-weight: 700; color: #1d2327; padding-top:4px;">
                <?php echo $raffle->draw_date ? esc_html( date_i18n( 'd/m/Y H:i', strtotime( $raffle->draw_date ) ) ) : '—'; ?>
            </p>
        </div>
    </div>

    <!-- Progress bar -->
    <div class="card" style="margin-top: 20px; max-width: 100%;">
        <h2 style="margin-top:0;">Sales Progress</h2>
        <div style="background:#e5e7eb; border-radius:10px; height:20px; width:100%; overflow:hidden; position:relative;">
            <div style="background:#3b82f6; height:100%; width:<?php echo esc_attr( $progress ); ?>%; transition: width 0.5s ease; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:bold; font-size:12px;">
                <?php echo esc_html( $progress ); ?>%
            </div>
        </div>
        <p class="description" style="margin-top:8px;">Shortcode to display this raffle: <code>[raffle id="<?php echo esc_attr( $raffle->id ); ?>"]</code></p>
    </div>

    <div id="poststuff" style="margin-top: 20px;">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content">
                
                <!-- Winner Banner / Manual Draw -->
                <?php if ( $winner ) : ?>
                    <?php
                    // 1.3.0 — Featured winner data (flag + photo + testimonial).
                    $featured = class_exists( 'Raffle_Featured_Winners' ) ? Raffle_Featured_Winners::get( $raffle->id ) : null;
                    $fw_photo_id = $featured ? (int) $featured->winner_photo_id : 0;
                    $fw_is_featured = $featured ? (int) $featured->is_featured : 0;
                    $fw_testimonial = $featured ? $featured->testimonial : '';
                    wp_enqueue_media();
                    ?>
                    <div class="notice notice-success inline" style="margin: 0 0 20px; padding: 15px; border-left-width: 6px;">
                        <h3 style="margin:0 0 10px; font-size: 18px; color: #15803d;"><?php wpr_icon( 'trophy', 'wpr-icon--sm' ); ?> Winner Selected!</h3>
                        <div style="display:flex; gap:30px; flex-wrap:wrap;">
                            <div>
                                <small style="display:block; color:#4b5563; font-weight:500;">Winning Ticket</small>
                                <strong style="font-size:24px; font-family:monospace; color:#1d2327;">#<?php echo esc_html( Raffle_Tickets::format_ticket_number( $winner->ticket_number, $raffle->total_tickets ) ); ?></strong>
                            </div>
                            <div>
                                <small style="display:block; color:#4b5563; font-weight:500;">Winner Name</small>
                                <strong style="font-size:18px; color:#1d2327;"><?php echo esc_html( $winner->buyer_name ); ?></strong>
                            </div>
                            <div>
                                <small style="display:block; color:#4b5563; font-weight:500;">Winner Email</small>
                                <strong style="font-size:18px; color:#1d2327;"><?php echo esc_html( $winner->buyer_email ); ?></strong>
                            </div>
                        </div>

                        <!-- Featured Winner Panel (1.3.0) -->
                        <div style="margin-top:16px; padding-top:16px; border-top:1px solid #c3c4c7;">
                            <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:12px;">
                                <label style="display:inline-flex; align-items:center; gap:8px; font-weight:700; font-size:14px; cursor:pointer;">
                                    <input type="checkbox" id="fw-featured-toggle" <?php checked( $fw_is_featured, 1 ); ?>>
                                    ★ Feature this winner
                                </label>
                                <span style="font-size:12px; color:#6b7280;">Featured winners appear in the winners carousel.</span>
                                <span id="fw-saved-msg" style="font-size:12px; color:#15803d; font-weight:600; display:none;">✓ Saved</span>
                            </div>
                            <div id="fw-fields" style="<?php echo $fw_is_featured ? '' : 'display:none;'; ?> display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start;">
                                <!-- Photo upload -->
                                <div style="flex:0 0 auto;">
                                    <small style="display:block; color:#4b5563; font-weight:500; margin-bottom:6px;">Winner photo</small>
                                    <div id="fw-photo-preview" style="width:120px; height:120px; border:2px dashed #c3c4c7; border-radius:8px; display:flex; align-items:center; justify-content:center; overflow:hidden; background:#f9fafb; margin-bottom:6px;">
                                        <?php if ( $fw_photo_id ) : ?>
                                            <?php echo wp_get_attachment_image( $fw_photo_id, 'thumbnail', false, array( 'style' => 'width:100%;height:100%;object-fit:cover;' ) ); ?>
                                        <?php else : ?>
                                            <span style="font-size:11px; color:#9ca3af;">No photo</span>
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" id="fw-photo-id" value="<?php echo esc_attr( $fw_photo_id ); ?>">
                                    <button type="button" class="button button-small" id="fw-upload-btn"><?php esc_html_e( 'Upload Photo', 'wpraffle' ); ?></button>
                                    <button type="button" class="button button-small" id="fw-remove-photo-btn" style="<?php echo $fw_photo_id ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'wpraffle' ); ?></button>
                                </div>
                                <!-- Testimonial -->
                                <div style="flex:1; min-width:200px;">
                                    <small style="display:block; color:#4b5563; font-weight:500; margin-bottom:6px;">Winner testimonial / quote (optional)</small>
                                    <textarea id="fw-testimonial" rows="4" style="width:100%;" placeholder="e.g. &quot;I couldn't believe it when I won! Amazing experience.&quot;"><?php echo esc_textarea( $fw_testimonial ); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <script>
                        jQuery(function($){
                            var nonce = '<?php echo esc_js( wp_create_nonce( 'raffle_featured_nonce' ) ); ?>';
                            var raffleId = <?php echo (int) $raffle->id; ?>;

                            function saveFeatured(){
                                $.post(ajaxurl, {
                                    action: 'raffle_save_featured_winner',
                                    nonce: nonce,
                                    raffle_id: raffleId,
                                    winner_name: '<?php echo esc_js( $winner->buyer_name ); ?>',
                                    winner_email: '<?php echo esc_js( $winner->buyer_email ); ?>',
                                    winner_photo_id: $('#fw-photo-id').val() || 0,
                                    is_featured: $('#fw-featured-toggle').is(':checked') ? 1 : 0,
                                    testimonial: $('#fw-testimonial').val() || ''
                                }).done(function(res){
                                    if (res && res.success) {
                                        var msg = $('#fw-saved-msg');
                                        msg.show().delay(1500).fadeOut();
                                    } else {
                                        alert((res && res.data && res.data.message) ? res.data.message : 'Error saving featured winner.');
                                    }
                                }).fail(function(xhr){
                                    console.log('Featured winner save failed:', xhr.status, xhr.responseText);
                                    alert('Featured winner save failed (HTTP ' + xhr.status + '). Check the browser console for details.');
                                });
                            }

                            $('#fw-featured-toggle').on('change', function(){
                                if ($(this).is(':checked')) {
                                    $('#fw-fields').slideDown();
                                } else {
                                    $('#fw-fields').slideUp();
                                }
                                saveFeatured();
                            });

                            var frame;
                            $('#fw-upload-btn').on('click', function(e){
                                e.preventDefault();
                                if (frame) { frame.open(); return; }
                                frame = wp.media({ title: 'Choose Winner Photo', button: { text: 'Use this photo' }, multiple: false });
                                frame.on('select', function(){
                                    var att = frame.state().get('selection').first().toJSON();
                                    $('#fw-photo-id').val(att.id);
                                    $('#fw-photo-preview').html('<img src="' + att.url + '" style="width:100%;height:100%;object-fit:cover;">');
                                    $('#fw-remove-photo-btn').show();
                                    saveFeatured();
                                });
                                frame.open();
                            });

                            $('#fw-remove-photo-btn').on('click', function(){
                                $('#fw-photo-id').val(0);
                                $('#fw-photo-preview').html('<span style="font-size:11px; color:#9ca3af;">No photo</span>');
                                $('#fw-remove-photo-btn').hide();
                                saveFeatured();
                            });

                            // Save testimonial on blur (debounced).
                            var to;
                            $('#fw-testimonial').on('input', function(){
                                clearTimeout(to);
                                to = setTimeout(saveFeatured, 800);
                            });
                        });
                        </script>
                    </div>
                <?php else : ?>
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'star', 'wpr-icon--sm' ); ?> Select Winner</h2>
                        </div>
                        <div class="inside">
                            <?php if ( $raffle->sold_tickets > 0 ) : ?>
                                <p>Sales are active! You can now draw a random winner from the tickets purchased.</p>
                                <button type="button" id="draw-winner-btn" class="button button-primary button-large" data-raffle-id="<?php echo esc_attr( $raffle->id ); ?>">
                                    Select Winner
                                </button>
                                <div id="draw-result" style="display:none; margin-top:15px;"></div>
                            <?php else : ?>
                                <p class="description"><?php wpr_icon( 'info', 'wpr-icon--sm' ); ?> No tickets have been sold yet. The draw will be available once there are sales.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Duplicate Control -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php wpr_icon( 'shield', 'wpr-icon--sm' ); ?> Duplicate Ticket Control</h2>
                    </div>
                    <div class="inside">
                        <p class="description">If two customers purchase at the same time, there may be overlap. Use these tools to detect and fix any duplicates.</p>
                        
                        <div style="margin: 15px 0;">
                            <label>
                                <input type="checkbox" id="raffle-auto-fix-toggle" value="1" <?php checked( get_option( 'raffle_auto_fix_duplicates', '1' ), '1' ); ?>>
                                <strong>Auto-fix after each purchase</strong>
                            </label>
                            <p class="description" style="margin-left: 25px;">The system will check and fix duplicates after each new order is completed.</p>
                        </div>

                        <div style="display:flex; gap:10px;">
                            <button type="button" id="check-duplicates-btn" class="button" data-raffle-id="<?php echo esc_attr( $raffle->id ); ?>">Check Duplicates</button>
                            <button type="button" id="fix-duplicates-btn" class="button button-primary" style="display:none;" data-raffle-id="<?php echo esc_attr( $raffle->id ); ?>">Fix Duplicates</button>
                        </div>
                        <div id="duplicates-result" style="margin-top:15px;"></div>
                    </div>
                </div>

                <!-- Instant Wins List -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php wpr_icon( 'gift', 'wpr-icon--sm' ); ?> Instant Wins</h2>
                    </div>
                    <div class="inside">
                        <p class="description" style="margin-bottom: 15px;">Instant Wins allow users to win specific prizes immediately when they purchase the matching ticket number.</p>
                        
                        <div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; background: #f6f7f7; padding: 15px; border:1px solid #c3c4c7; border-radius: 4px;">
                            <input type="text" id="iw-prize-name" placeholder="Prize Name (e.g. $50 Gift Card)" style="flex:1; height:35px;">
                            <input type="number" id="iw-ticket-number" placeholder="Ticket # (blank for random)" style="width: 200px; height:35px;">
                            <button type="button" id="add-instant-win-btn" class="button button-primary" data-raffle-id="<?php echo esc_attr( $raffle->id ); ?>" style="height:35px; line-height:33px;">
                                Add Instant Win
                            </button>
                        </div>

                        <table class="wp-list-table widefat striped posts">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Ticket Number</th>
                                    <th>Prize Name</th>
                                    <th>Status</th>
                                    <th>Winner Email</th>
                                    <th style="width:85px;">Actions</th>
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

                <!-- Purchases / Buyers List -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php wpr_icon( 'user', 'wpr-icon--sm' ); ?> Buyers & Ticket Holders (<?php echo count( $purchases ); ?>)</h2>
                        <div class="handle-actions" style="display:flex; gap:8px; align-items:center; padding-right:10px;">
                            <label for="rs-buyers-search" class="screen-reader-text"><?php esc_html_e( 'Search buyers', 'wpraffle' ); ?></label>
                            <input type="search" id="rs-buyers-search" placeholder="<?php esc_attr_e( 'Search name or email…', 'wpraffle' ); ?>" style="font-size:12px; min-width:200px;">
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( "admin.php?page=raffle-list&action=export_buyers&id={$raffle->id}" ), 'export_buyers_' . $raffle->id ) ); ?>" class="button button-primary" style="font-size:12px;">
                                <?php wpr_icon( 'refresh', 'wpr-icon--xs' ); ?> <?php esc_html_e( 'Export Buyers', 'wpraffle' ); ?>
                            </a>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( "admin-post.php?action=wpraffle_export_tickets&id={$raffle->id}" ), 'export_tickets_' . $raffle->id ) ); ?>" class="button" style="font-size:12px;" target="_blank">
                                <?php esc_html_e( 'Export Tickets', 'wpraffle' ); ?>
                            </a>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( "admin-post.php?action=wpraffle_export_instant_wins&id={$raffle->id}" ), 'export_iw_' . $raffle->id ) ); ?>" class="button" style="font-size:12px;" target="_blank">
                                <?php esc_html_e( 'Export Instant Wins', 'wpraffle' ); ?>
                            </a>
                        </div>
                    </div>
                    <div class="inside" style="padding:0; margin:0;">
                        <table class="wp-list-table widefat striped posts" id="rs-buyers-table" style="border:none; box-shadow:none;">
                            <thead>
                                <tr>
                                    <th scope="col" style="padding-left:15px; width:60px;">ID</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Email</th>
                                    <th scope="col" style="width:70px;">Tickets</th>
                                    <th scope="col">Total Paid</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Purchase Date</th>
                                    <th scope="col" style="padding-right:15px;">Ticket Numbers</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( empty( $purchases ) ) : ?>
                                    <tr>
                                        <td colspan="8" style="text-align:center; padding: 30px 10px; color:#64748b;">
                                            No purchases recorded for this raffle yet.
                                        </td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ( $purchases as $p ) :
                                        $tickets = $wpdb->get_col( $wpdb->prepare(
                                            "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE purchase_id = %d ORDER BY ticket_number",
                                            $p->id
                                        ) );
                                        $formatted = array_map( function ( $n ) use ( $raffle ) {
                                            return Raffle_Tickets::format_ticket_number( $n, $raffle->total_tickets );
                                        }, $tickets );
                                    ?>
                                        <tr>
                                            <td style="padding-left:15px;">#<?php echo esc_html( $p->id ); ?></td>
                                            <td><strong><?php echo esc_html( $p->buyer_name ); ?></strong></td>
                                            <td><?php echo esc_html( $p->buyer_email ); ?></td>
                                            <td><?php echo esc_html( $p->quantity ); ?></td>
                                            <td><strong style="color: #10b981;"><?php echo esc_html( wpr_price( $p->total_amount ) ); ?></strong></td>
                                            <td>
                                                <span style="background:<?php echo $p->payment_status === 'completed' ? '#dcfce7; color:#166534;' : '#fee2e2; color:#991b1b;'; ?>; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px; display:inline-block;">
                                                    <?php echo esc_html( ucfirst( $p->payment_status ) ); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $p->purchase_date ) ) ); ?></td>
                                            <td style="padding-right:15px;">
                                                <div style="display:flex; flex-wrap:wrap; gap:4px; max-height: 80px; overflow-y: auto;">
                                                    <?php foreach ( $formatted as $num ) : ?>
                                                        <span style="background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; padding:2px 6px; border-radius:4px; font-size:11px; font-family:monospace; font-weight:bold;">
                                                            <?php echo esc_html( $num ); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
// Client-side buyer search — filters table rows by name/email as you type.
// Cheaper than a round-trip for the typical buyers-list size, and gives
// instant feedback.
(function(){
    var input = document.getElementById('rs-buyers-search');
    var table = document.getElementById('rs-buyers-table');
    if (!input || !table) return;
    input.addEventListener('input', function(){
        var q = this.value.trim().toLowerCase();
        var rows = table.querySelectorAll('tbody tr');
        rows.forEach(function(row){
            if (row.cells.length < 3) return; // skip the empty-state row
            var name = (row.cells[1] ? row.cells[1].textContent : '').toLowerCase();
            var email = (row.cells[2] ? row.cells[2].textContent : '').toLowerCase();
            row.style.display = (name.indexOf(q) !== -1 || email.indexOf(q) !== -1) ? '' : 'none';
        });
    });
})();
</script>
