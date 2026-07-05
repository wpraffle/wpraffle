<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap">
    <h1 class="wp-heading-inline">Raffles</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=raffle-new' ) ); ?>" class="page-title-action">Create Raffle</a>
    <hr class="wp-header-end">

    <?php if ( isset( $_GET['message'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                $msg = sanitize_text_field( wp_unslash( $_GET['message'] ) );
                echo $msg === 'saved' ? 'Raffle saved successfully.' : 'Raffle deleted.';
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php
    global $wpdb;
    $raffles = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}raffles ORDER BY created_at DESC" );
    ?>

    <div class="list-table-wrapper" style="margin-top: 20px;">
        <table class="wp-list-table widefat fixed striped table-view-list posts">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-id" style="width:60px;">ID</th>
                    <th scope="col" class="manage-column column-title column-primary">Title</th>
                    <th scope="col" class="manage-column column-prize">Prize Value</th>
                    <th scope="col" class="manage-column column-tickets">Tickets Sold / Total</th>
                    <th scope="col" class="manage-column column-instant_wins">Instant Wins</th>
                    <th scope="col" class="manage-column column-price">Ticket Price</th>
                    <th scope="col" class="manage-column column-draw_date">Draw Date</th>
                    <th scope="col" class="manage-column column-progress" style="width:150px;">Progress</th>
                    <th scope="col" class="manage-column column-status" style="width:100px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $raffles ) ) : ?>
                    <tr>
                        <td colspan="9" style="text-align:center; padding: 30px 10px; color:#64748b;">
                            No raffles created yet. <a href="<?php echo esc_url( admin_url( 'admin.php?page=raffle-new' ) ); ?>">Create your first raffle!</a>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $raffles as $r ) : ?>
                        <?php
                        $percent = $r->total_tickets > 0 ? round( ( $r->sold_tickets / $r->total_tickets ) * 100 ) : 0;

                        // Fetch instant wins count
                        $total_iw = $wpdb->get_var( $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}raffle_instant_wins WHERE raffle_id = %d",
                            $r->id
                        ) );
                        $won_iw = $wpdb->get_var( $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}raffle_instant_wins WHERE raffle_id = %d AND status = 'won'",
                            $r->id
                        ) );
                        ?>
                        <tr>
                            <td>#<?php echo esc_html( $r->id ); ?></td>
                            <td class="title column-title column-primary page-title">
                                <strong>
                                    <a class="row-title" href="<?php echo esc_url( admin_url( "admin.php?page=raffle-list&action=edit&id={$r->id}" ) ); ?>">
                                        <?php echo esc_html( $r->title ); ?>
                                    </a>
                                </strong>
                                <div class="row-actions">
                                    <span class="view">
                                        <a href="<?php echo esc_url( admin_url( "admin.php?page=raffle-list&action=view&id={$r->id}" ) ); ?>">View details</a> |
                                    </span>
                                    <span class="edit">
                                        <a href="<?php echo esc_url( admin_url( "admin.php?page=raffle-list&action=edit&id={$r->id}" ) ); ?>">Edit</a> |
                                    </span>
                                    <span class="trash">
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( "admin.php?page=raffle-list&action=delete&id={$r->id}" ), 'delete_raffle' ) ); ?>"
                                           onclick="return confirm('Are you sure you want to delete this raffle and all its data?');">
                                            Delete
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td><?php echo esc_html( wpr_price( $r->prize_value ) ); ?></td>
                            <td><?php echo esc_html( $r->sold_tickets ); ?> / <?php echo esc_html( $r->total_tickets ); ?></td>
                            <td>
                                <?php if ( $total_iw > 0 ) : ?>
                                    <span style="background:#fff7ed; color:#ea580c; border:1px solid #ffedd5; padding:3px 8px; border-radius:4px; font-weight:600; display:inline-flex; align-items:center; gap:4px; font-size:11px;">
                                        <?php wpr_icon( 'gift', 'wpr-icon--xs' ); ?> <?php echo esc_html( $won_iw ); ?> / <?php echo esc_html( $total_iw ); ?>
                                    </span>
                                <?php else : ?>
                                    <span style="opacity: 0.5;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( wpr_price( $r->ticket_price ) ); ?></td>
                            <td><?php echo $r->draw_date ? esc_html( date_i18n( 'd/m/Y H:i', strtotime( $r->draw_date ) ) ) : '—'; ?></td>
                            <td>
                                <div style="background:#e5e7eb; border-radius:10px; height:12px; width:100%; overflow:hidden; display:inline-block; vertical-align:middle; margin-right:5px;">
                                    <div style="background:<?php echo $percent >= 100 ? '#10b981' : '#3b82f6'; ?>; height:100%; width:<?php echo esc_attr( $percent ); ?>%;"></div>
                                </div>
                                <span style="font-size:12px; font-weight:600; color:#4b5563;"><?php echo esc_html( $percent ); ?>%</span>
                            </td>
                            <td>
                                <?php
                                $r_state = Raffle_Public::get_raffle_state( $r );
                                if ( $r_state === 'live' ) {
                                    $bg_color = '#dcfce7; color:#166534;';
                                    $lbl = 'Live';
                                } elseif ( $r_state === 'draft' ) {
                                    $bg_color = '#fef3c7; color:#92400e;';
                                    $lbl = 'Draft';
                                } else {
                                    $bg_color = '#fee2e2; color:#991b1b;';
                                    $lbl = 'Ended';
                                }
                                ?>
                                <span style="background:<?php echo $bg_color; ?>; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px; display:inline-block; text-transform: uppercase;">
                                    <?php echo esc_html( $lbl ); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-top: 20px; max-width: 100%;">
        <h2 class="title">How to display raffles on your site</h2>
        <p class="description" style="margin-bottom:16px;">Use the shortcodes below on any page or post to display raffle content.</p>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:220px;">Shortcode</th>
                    <th>Description</th>
                    <th style="width:300px;">Attributes</th>
                </tr>
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
                        <code>show_image</code> — <code>yes</code> / <code>no</code> <em>(default: yes)</em><br>
                        <code>show_winner</code> — <code>yes</code> / <code>no</code> <em>(default: yes)</em><br>
                        <code>show_video_btn</code> — <code>yes</code> / <code>no</code> <em>(default: yes)</em><br>
                        <code>show_verified_btn</code> — <code>yes</code> / <code>no</code> <em>(default: yes)</em><br>
                        <code>show_date</code> — <code>yes</code> / <code>no</code> <em>(default: yes)</em><br>
                        <code>show_entries</code> — <code>yes</code> / <code>no</code> <em>(default: yes)</em>
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
                    <td><code>[raffle_entry_list]</code></td>
                    <td>Display a grid of all closed/ended competitions with a download button for each, allowing customers to download the full entry list as a PDF file.</td>
                    <td>
                        <code>layout</code> — <code>grid</code> / <code>list</code> <em>(default: grid)</em><br>
                        <code>columns</code> — Grid columns <em>(default: 2, only used when layout=grid)</em><br>
                        <code>button_text</code> — Button label <em>(default: "Download Entry List")</em><br>
                        <code>button_bg</code> — Button background colour <em>(default: var(--wpr-accent))</em><br>
                        <code>button_color</code> — Button text colour <em>(default: var(--wpr-text-inverse))</em><br>
                        <code>button_radius</code> — Button border radius <em>(default: 8)</em><br>
                        <code>show_image</code> — <code>yes</code> / <code>no</code> <em>(default: yes)</em>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Charity shortcode documentation (feature expansion) -->
<div class="card" style="margin-top: 20px; max-width: 100%;">
    <h2 class="title">Charity &amp; Wallet Shortcodes</h2>
    <p class="description" style="margin-bottom:16px;">Additional shortcodes introduced in the feature expansion.</p>
    <table class="widefat striped">
        <thead>
            <tr>
                <th style="width:220px;">Shortcode</th>
                <th>Description</th>
                <th style="width:300px;">Attributes</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>[raffle_charities]</code></td>
                <td>Display a directory of all active charities with logos, descriptions, registration numbers, and total raised through competitions. Each card links to the charity website.</td>
                <td><code>columns</code> — Grid columns <em>(default: 3)</em></td>
            </tr>
        </tbody>
    </table>
</div>
