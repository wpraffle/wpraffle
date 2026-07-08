<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php wpr_icon( 'edit', 'wpr-icon--sm' ); ?>
        <?php esc_html_e( 'Raffle Templates', 'wpraffle' ); ?>
    </h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=raffle-new' ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Create New Raffle (blank)', 'wpraffle' ); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ( isset( $_GET['message'] ) && $_GET['message'] === 'deleted' ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Template deleted.', 'wpraffle' ); ?></p></div>
    <?php endif; ?>

    <p class="description" style="margin: 16px 0;">
        <?php esc_html_e( 'Templates capture a raffle\'s configuration — ticket settings, packages, instant wins, skill question, geo-restriction, referral config and more — so you can spin up a new raffle with the same setup in seconds. Save one from any raffle\'s edit screen using "Save as Template".', 'wpraffle' ); ?>
    </p>

    <?php if ( empty( $templates ) ) : ?>
        <div class="rs-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:40px 20px;text-align:center;">
            <div style="color:#6b7280;margin-bottom:16px;">
                <?php wpr_icon( 'edit', 'wpr-icon--3xl' ); ?>
            </div>
            <h3 style="margin-top:0;"><?php esc_html_e( 'No templates yet', 'wpraffle' ); ?></h3>
            <p style="color:#6b7280;max-width:480px;margin:0 auto 16px;">
                <?php esc_html_e( 'Open any existing raffle\'s edit screen and click "Save as Template" to capture its configuration here.', 'wpraffle' ); ?>
            </p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=raffle-list' ) ); ?>" class="button button-primary">
                <?php esc_html_e( 'Go to Raffles', 'wpraffle' ); ?>
            </a>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped posts">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-name" style="width:30%;">
                        <?php esc_html_e( 'Template Name', 'wpraffle' ); ?>
                    </th>
                    <th scope="col" class="manage-column" style="width:15%;">
                        <?php esc_html_e( 'Price', 'wpraffle' ); ?>
                    </th>
                    <th scope="col" class="manage-column" style="width:15%;">
                        <?php esc_html_e( 'Tickets', 'wpraffle' ); ?>
                    </th>
                    <th scope="col" class="manage-column" style="width:15%;">
                        <?php esc_html_e( 'Created', 'wpraffle' ); ?>
                    </th>
                    <th scope="col" class="manage-column" style="width:25%;text-align:right;">
                        <?php esc_html_e( 'Actions', 'wpraffle' ); ?>
                    </th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php foreach ( $templates as $tpl ) :
                    $cfg = json_decode( $tpl->config, true );
                    if ( ! is_array( $cfg ) ) {
                        $cfg = array();
                    }
                    $price   = isset( $cfg['ticket_price'] ) ? wpr_price( $cfg['ticket_price'] ) : '—';
                    $tickets = isset( $cfg['total_tickets'] ) ? number_format( (int) $cfg['total_tickets'] ) : '—';
                    $iw_count = isset( $cfg['instant_wins'] ) && is_array( $cfg['instant_wins'] ) ? count( $cfg['instant_wins'] ) : 0;

                    $use_url = wp_nonce_url(
                        admin_url( 'admin.php?page=raffle-new&template_id=' . $tpl->id ),
                        'raffle_apply_template'
                    );
                    $delete_url = wp_nonce_url(
                        admin_url( 'admin.php?page=raffle-templates&action=delete&id=' . $tpl->id ),
                        'raffle_delete_template'
                    );
                ?>
                    <tr>
                        <td class="column-name">
                            <strong><?php echo esc_html( $tpl->name ); ?></strong>
                            <?php if ( $iw_count > 0 ) : ?>
                                <div style="font-size:11px;color:#6b7280;margin-top:2px;">
                                    <?php
                                    /* translators: %d: number of instant wins. */
                                    echo esc_html( sprintf( _n( '%d instant win', '%d instant wins', $iw_count, 'wpraffle' ), $iw_count ) );
                                    ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $price ); ?></td>
                        <td><?php echo esc_html( $tickets ); ?></td>
                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $tpl->created_at ) ) ); ?></td>
                        <td style="text-align:right;">
                            <a href="<?php echo esc_url( $use_url ); ?>" class="button button-primary" style="display:inline-flex;align-items:center;gap:4px;">
                                <?php wpr_icon( 'plus', 'wpr-icon--xs' ); ?>
                                <?php esc_html_e( 'Use Template', 'wpraffle' ); ?>
                            </a>
                            <a href="<?php echo esc_url( $delete_url ); ?>" class="button button-link-delete" style="display:inline-flex;align-items:center;gap:4px;margin-left:6px;" onclick="return confirm('<?php echo esc_js( __( 'Delete this template? This cannot be undone.', 'wpraffle' ) ); ?>');">
                                <?php wpr_icon( 'x-circle', 'wpr-icon--xs' ); ?>
                                <?php esc_html_e( 'Delete', 'wpraffle' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
