<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
$layout = $atts['layout'] ?? 'grid';
$is_list = ( $layout === 'list' );
?>

<div class="raffle-entry-list-wrapper" style="padding: 20px 0;">
    <div class="raffle-entry-list-<?php echo $is_list ? 'list' : 'grid'; ?>" style="<?php echo $is_list ? 'display: flex; flex-direction: column; gap: 16px;' : 'display: grid; grid-template-columns: repeat(' . esc_attr( $cols ) . ', 1fr); gap: 24px;'; ?>">
        <?php foreach ( $raffles as $r ) :
            $total_digits    = strlen( (string) $r->total_tickets );
            $download_url    = add_query_arg( array(
                'action'    => 'raffle_download_entry_list',
                'raffle_id' => $r->id,
                'nonce'     => wp_create_nonce( 'raffle_entry_list_nonce' ),
            ), admin_url( 'admin-ajax.php' ) );

            // Winner info
            $winner_info = null;
            if ( $r->winner_ticket_id ) {
                $winner_info = $wpdb->get_row( $wpdb->prepare(
                    "SELECT t.ticket_number, p.buyer_name
                     FROM {$wpdb->prefix}raffle_tickets t
                     JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id
                     WHERE t.id = %d",
                    $r->winner_ticket_id
                ) );
            }
            $formatted_winner_num = $winner_info ? str_pad( $winner_info->ticket_number, $total_digits, '0', STR_PAD_LEFT ) : '';
        ?>

        <?php if ( $is_list ) : ?>
        <!-- LIST LAYOUT -->
        <div class="raffle-entry-list-card" style="background: var(--wpr-bg-surface); border: 1px solid var(--wpr-border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 16px; padding: 16px 20px; flex-wrap: wrap;">

            <?php if ( $show_image ) : ?>
                <?php if ( ! empty( $r->prize_image ) ) : ?>
                    <img src="<?php echo esc_url( $r->prize_image ); ?>" style="width: 64px; height: 64px; object-fit: cover; border-radius: 8px; flex-shrink: 0;">
                <?php else : ?>
                    <div class="raffle-image-placeholder-list" style="width: 64px; height: 64px; background: linear-gradient(135deg, var(--wpr-accent-bg) 0%, var(--wpr-border-color) 100%); display: flex; align-items: center; justify-content: center; color: var(--wpr-accent); border-radius: 8px; flex-shrink: 0;">
                        <?php echo wpr_get_icon( 'gift', 'wpr-icon--sm', 'Competition Prize' ); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div style="flex: 1; min-width: 180px;">
                <h3 style="margin: 0 0 4px 0; font-size: 15px; font-weight: 700; color: var(--wpr-text-primary); line-height: 1.3;"><?php echo esc_html( $r->title ); ?></h3>
                <div style="font-size: 12px; color: var(--wpr-text-muted);">
                    <?php echo esc_html( $r->sold_tickets ); ?> / <?php echo esc_html( $r->total_tickets ); ?> entries
                    <?php if ( $r->draw_date ) : ?>
                        &bull; <?php echo esc_html( date_i18n( 'jS M Y', strtotime( $r->draw_date ) ) ); ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( $winner_info ) : ?>
            <div style="display: flex; align-items: center; gap: 6px; background: var(--wpr-success-bg); border: 1px solid var(--wpr-success-bg); border-radius: 8px; padding: 6px 12px; flex-shrink: 0;">
                <?php echo wpr_get_icon( 'trophy', 'wpr-icon--sm', 'Winner' ); ?>
                <span style="font-size: 13px; font-weight: 700; color: var(--wpr-success-text);"><?php echo esc_html( $winner_info->buyer_name ); ?></span>
                <span style="font-size: 11px; color: var(--wpr-text-muted);">#<span style="font-family: monospace; background: var(--wpr-success-bg); color: var(--wpr-success-text); padding: 1px 5px; border-radius: 3px; font-weight: bold;"><?php echo esc_html( $formatted_winner_num ); ?></span></span>
            </div>
            <?php endif; ?>

            <a href="<?php echo esc_url( $download_url ); ?>" class="raffle-entry-list-download-btn" style="display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 16px; background: <?php echo esc_attr( $btn_bg ); ?>; color: <?php echo esc_attr( $btn_color ); ?>; border: none; border-radius: <?php echo esc_attr( $btn_radius ); ?>px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; text-align: center; flex-shrink: 0; transition: opacity 0.2s; white-space: nowrap;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                    <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                </svg>
                <?php echo esc_html( $btn_text ); ?>
            </a>

        </div>

        <?php else : ?>
        <!-- GRID LAYOUT -->
        <div class="raffle-entry-list-card" style="background: var(--wpr-bg-surface); border: 1px solid var(--wpr-border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); display: flex; flex-direction: column;">

            <?php if ( $show_image ) : ?>
                <?php if ( ! empty( $r->prize_image ) ) : ?>
                    <div style="position: relative; overflow: hidden;">
                        <img src="<?php echo esc_url( $r->prize_image ); ?>" style="width: 100%; height: 180px; object-fit: cover;">
                        <?php if ( $r->draw_date ) : ?>
                        <div style="position: absolute; top: 12px; right: 12px; background: rgba(0,0,0,0.65); color: var(--wpr-text-inverse); padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600;">
                            <?php echo esc_html( date_i18n( 'jS M Y', strtotime( $r->draw_date ) ) ); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="raffle-image-placeholder" style="width: 100%; height: 180px; background: linear-gradient(135deg, var(--wpr-accent-bg) 0%, var(--wpr-border-color) 100%); display: flex; align-items: center; justify-content: center; color: var(--wpr-accent); position: relative;">
                        <?php echo wpr_get_icon( 'gift', 'wpr-icon--lg', 'Competition Prize' ); ?>
                        <?php if ( $r->draw_date ) : ?>
                        <div style="position: absolute; top: 12px; right: 12px; background: rgba(0,0,0,0.65); color: var(--wpr-text-inverse); padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600;">
                            <?php echo esc_html( date_i18n( 'jS M Y', strtotime( $r->draw_date ) ) ); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div style="padding: 20px; display: flex; flex-direction: column; gap: 12px; flex: 1;">

                <!-- Title & Entry Count -->
                <div>
                    <h3 style="margin: 0 0 4px 0; font-size: 17px; font-weight: 700; color: var(--wpr-text-primary); line-height: 1.3;"><?php echo esc_html( $r->title ); ?></h3>
                    <div style="font-size: 13px; color: var(--wpr-text-muted);">
                        <?php echo esc_html( $r->sold_tickets ); ?> / <?php echo esc_html( $r->total_tickets ); ?> entries
                    </div>
                </div>

                <?php if ( $winner_info ) : ?>
                <!-- Winner -->
                <div style="background: var(--wpr-success-bg); border: 1px solid var(--wpr-success-bg); border-radius: 10px; padding: 10px 14px; display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;">
                    <?php echo wpr_get_icon( 'trophy', 'wpr-icon--sm', 'Winner' ); ?>
                    <span style="font-size: 14px; font-weight: 700; color: var(--wpr-success-text);"><?php echo esc_html( $winner_info->buyer_name ); ?></span>
                    <span style="font-size: 12px; color: var(--wpr-text-muted);">Ticket: <span style="font-family: monospace; background: var(--wpr-success-bg); color: var(--wpr-success-text); padding: 2px 6px; border-radius: 4px; font-weight: bold;"><?php echo esc_html( $formatted_winner_num ); ?></span></span>
                </div>
                <?php endif; ?>

                <!-- Download Button -->
                <a href="<?php echo esc_url( $download_url ); ?>" class="raffle-entry-list-download-btn" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 16px; background: <?php echo esc_attr( $btn_bg ); ?>; color: <?php echo esc_attr( $btn_color ); ?>; border: none; border-radius: <?php echo esc_attr( $btn_radius ); ?>px; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; text-align: center; margin-top: auto; transition: opacity 0.2s;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                        <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                    </svg>
                    <?php echo esc_html( $btn_text ); ?>
                </a>

            </div>
        </div>
        <?php endif; ?>

        <?php endforeach; ?>
    </div>
</div>
