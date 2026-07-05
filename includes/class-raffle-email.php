<?php
/**
 * WPRaffle — Email Automation Engine
 * Handles all transactional and automated emails.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Email {

    // ─────────────────────────────────────────────────────────────────────
    // Settings helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Get email configuration from WP options (set via admin Email Settings page).
     */
    public static function get_settings() {
        return wp_parse_args( get_option( 'wpraffle_email_settings', array() ), array(
            'from_name'    => get_bloginfo( 'name' ),
            'from_email'   => get_option( 'admin_email' ),
            'accent_color' => '#6c5ce7',
            'logo_url'     => '',
            'footer_text'  => 'You are receiving this email because you entered a competition on ' . get_bloginfo( 'name' ) . '.',
            'site_url'     => home_url(),
        ) );
    }

    /**
     * Standard email headers.
     */
    private static function get_headers( $settings ) {
        return array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Base HTML template
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Wrap content in the shared branded email shell.
     */
    private static function wrap( $content, $preheader = '' ) {
        $s    = self::get_settings();
        $name = esc_html( $s['from_name'] );
        $url  = esc_url( $s['site_url'] );
        $col  = esc_attr( $s['accent_color'] );
        $foot = wp_kses_post( $s['footer_text'] );
        $year = date( 'Y' );

        $logo_html = $s['logo_url']
            ? '<img src="' . esc_url( $s['logo_url'] ) . '" alt="' . $name . '" style="max-height:50px;max-width:180px;display:block;margin:0 auto;">'
            : '<span style="font-size:22px;font-weight:900;color:#fff;letter-spacing:-0.5px;">' . $name . '</span>';

        $pre = $preheader
            ? '<div style="display:none;max-height:0;overflow:hidden;font-size:1px;color:#f4f5f7;">' . esc_html( $preheader ) . '&nbsp;&zwnj;&nbsp;</div>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>{$name}</title>
<!--[if mso]><style>table{border-collapse:collapse}td,th{font-family:Arial,sans-serif}</style><![endif]-->
</head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;">
{$pre}

<!-- Wrapper -->
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f0f2f5;">
<tr><td align="center" style="padding:40px 20px;">

  <!-- Email Card -->
  <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

    <!-- Header -->
    <tr>
      <td style="background:linear-gradient(135deg,{$col},#a29bfe);padding:36px 40px;text-align:center;">
        <a href="{$url}" style="text-decoration:none;">{$logo_html}</a>
      </td>
    </tr>

    <!-- Body -->
    <tr>
      <td style="padding:40px 40px 32px;">
        {$content}
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="background:#f8f9fa;border-top:1px solid #e9ecef;padding:24px 40px;text-align:center;">
        <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;">{$foot}</p>
        <p style="margin:0;font-size:12px;color:#c4c9d4;">&copy; {$year} {$name} &mdash; <a href="{$url}" style="color:#9ca3af;text-decoration:none;">{$url}</a></p>
      </td>
    </tr>

  </table>
</td></tr>
</table>
</body>
</html>
HTML;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Shared component builders
    // ─────────────────────────────────────────────────────────────────────

    private static function ticket_block( $ticket_numbers, $col ) {
        $chunks  = array_chunk( $ticket_numbers, 5 );
        $rows    = '';
        foreach ( $chunks as $row ) {
            $cells = '';
            foreach ( $row as $num ) {
                $cells .= '<td style="padding:4px 6px;"><span style="display:inline-block;background:#f0f2ff;color:' . $col . ';font-family:monospace;font-size:14px;font-weight:700;padding:6px 12px;border-radius:8px;border:1px solid #e0e7ff;letter-spacing:0.5px;">' . esc_html( $num ) . '</span></td>';
            }
            $rows .= '<tr>' . $cells . '</tr>';
        }
        return '<table cellpadding="0" cellspacing="4" border="0" style="margin:0 auto;">' . $rows . '</table>';
    }

    private static function stat_cell( $value, $label ) {
        return '<td style="padding:12px 16px;text-align:center;">
            <div style="font-size:22px;font-weight:800;color:#1f2937;">' . esc_html( $value ) . '</div>
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9ca3af;margin-top:4px;">' . esc_html( $label ) . '</div>
        </td>';
    }

    private static function cta_button( $url, $label, $col ) {
        return '<table cellpadding="0" cellspacing="0" border="0" style="margin:24px auto 0;">
            <tr><td style="background:' . esc_attr( $col ) . ';border-radius:50px;">
                <a href="' . esc_url( $url ) . '" style="display:inline-block;padding:14px 36px;color:#fff;font-size:15px;font-weight:700;text-decoration:none;letter-spacing:0.3px;">' . esc_html( $label ) . '</a>
            </td></tr>
        </table>';
    }

    private static function section_heading( $text ) {
        return '<h2 style="margin:0 0 20px;font-size:24px;font-weight:800;color:#1f2937;line-height:1.2;">' . esc_html( $text ) . '</h2>';
    }

    private static function muted_p( $text ) {
        return '<p style="margin:0 0 16px;font-size:15px;color:#6b7280;line-height:1.7;">' . wp_kses_post( $text ) . '</p>';
    }

    private static function box( $inner, $bg = '#f8f9fa', $border = '#e9ecef' ) {
        return '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $bg . ';border:1px solid ' . $border . ';border-radius:12px;margin:20px 0;">
            <tr><td style="padding:24px;">' . $inner . '</td></tr>
        </table>';
    }

    // ─────────────────────────────────────────────────────────────────────
    // 1. Purchase Confirmation
    // ─────────────────────────────────────────────────────────────────────

    public static function send_purchase_confirmation( $purchase_id, $raffle, $tickets, $instant_wins = array() ) {
        global $wpdb;
        $purchase = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_purchases WHERE id = %d",
            $purchase_id
        ) );
        if ( ! $purchase ) {
            return;
        }

        $s   = self::get_settings();
        $col = $s['accent_color'];

        $formatted = array_map( function ( $num ) use ( $raffle ) {
            return Raffle_Tickets::format_ticket_number( $num, $raffle->total_tickets );
        }, $tickets );

        $draw_date = $raffle->draw_date
            ? date_i18n( 'l jS F Y \a\t g:i a', strtotime( $raffle->draw_date ) )
            : 'To be confirmed';

        $qty   = (int) $purchase->quantity;
        $total = wpr_currency_symbol() . number_format( (float) $purchase->total_amount, 2 );
        $name  = esc_html( $purchase->buyer_name );

        // Build ticket table
        $ticket_html = self::ticket_block( $formatted, $col );

        // Stats row
        $stats = '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8f9fa;border-radius:12px;margin:20px 0;">
            <tr style="border-bottom:1px solid #e9ecef;">'
            . self::stat_cell( $qty, 'Tickets' )
            . self::stat_cell( $total, 'Total Paid' )
            . self::stat_cell( $draw_date, 'Draw Date' )
            . '</tr></table>';

        // Instant wins callout
        $iw_html = '';
        if ( ! empty( $instant_wins ) ) {
            $iw_items = '';
            foreach ( $instant_wins as $win ) {
                $win_num   = str_pad( $win->ticket_number, strlen( (string) $raffle->total_tickets ), '0', STR_PAD_LEFT );
                $iw_items .= '<tr><td style="padding:8px 0;border-bottom:1px solid #fde68a;">
                    <strong style="color:#92400e;">Ticket #' . esc_html( $win_num ) . '</strong> &mdash; ' . esc_html( $win->prize_name ) . '
                </td></tr>';
            }
            $iw_html = self::box(
                '<h3 style="margin:0 0 16px;color:#92400e;font-size:18px;">🎉 You Won Instant Prizes!</h3>
                <table width="100%" cellpadding="0" cellspacing="0">' . $iw_items . '</table>',
                '#fffbeb', '#fde68a'
            );
        }

        $body = self::section_heading( 'Your tickets are confirmed! 🎟️' )
            . self::muted_p( 'Hi <strong>' . $name . '</strong>, your entry for <strong>' . esc_html( $raffle->title ) . '</strong> has been processed. Here are your ticket numbers:' )
            . self::box( '<div style="text-align:center;">' . $ticket_html . '</div>', '#f0f2ff', '#e0e7ff' )
            . $stats
            . $iw_html
            . self::muted_p( 'Good luck! The draw will be conducted fairly and the winner notified by email.' )
            . self::cta_button( home_url( '/my-account/my-raffles/' ), 'View My Raffles', $col );

        $subject = 'Entry confirmed: ' . $raffle->title . ' — ' . $qty . ' ticket' . ( $qty > 1 ? 's' : '' );

        wp_mail(
            $purchase->buyer_email,
            $subject,
            self::wrap( $body, 'Your ' . $qty . ' ticket' . ( $qty > 1 ? 's' : '' ) . ' for ' . $raffle->title . ' are confirmed!' ),
            self::get_headers( $s )
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. Winner Notification
    // ─────────────────────────────────────────────────────────────────────

    public static function send_winner_notification( $buyer_email, $buyer_name, $raffle, $winning_ticket ) {
        $s   = self::get_settings();
        $col = $s['accent_color'];

        $formatted = Raffle_Tickets::format_ticket_number( $winning_ticket, $raffle->total_tickets );

        $prize_display = ! empty( $raffle->prize_value ) ? wpr_price( $raffle->prize_value, 0 ) : 'the prize';

        $body = self::section_heading( '🏆 Congratulations — You Won!' )
            . self::muted_p( 'Hi <strong>' . esc_html( $buyer_name ) . '</strong>, your ticket was drawn as the winner of <strong>' . esc_html( $raffle->title ) . '</strong>!' )
            . self::box(
                '<div style="text-align:center;">
                    <div style="font-size:14px;color:#6b7280;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">Winning Ticket</div>
                    <div style="display:inline-block;background:#f0f2ff;border:2px solid ' . esc_attr( $col ) . ';border-radius:12px;padding:16px 32px;">
                        <span style="font-family:monospace;font-size:28px;font-weight:900;color:' . esc_attr( $col ) . ';">' . esc_html( $formatted ) . '</span>
                    </div>
                    <div style="margin-top:16px;font-size:18px;font-weight:700;color:#1f2937;">Prize value: ' . esc_html( $prize_display ) . '</div>
                </div>',
                '#f0f2ff', '#e0e7ff'
            )
            . self::muted_p( 'Our team will be in touch shortly to arrange prize delivery. Please keep this email as proof of your win.' )
            . self::cta_button( home_url(), 'Visit ' . esc_html( $s['from_name'] ), $col );

        wp_mail(
            $buyer_email,
            '🏆 You Won! ' . $raffle->title,
            self::wrap( $body, 'Congratulations! Your ticket won ' . $raffle->title . '.' ),
            self::get_headers( $s )
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. Instant Win Alert
    // ─────────────────────────────────────────────────────────────────────

    public static function send_instant_win_alert( $buyer_email, $buyer_name, $raffle, $instant_win ) {
        $s   = self::get_settings();
        $col = $s['accent_color'];

        $formatted = str_pad( $instant_win->ticket_number, strlen( (string) $raffle->total_tickets ), '0', STR_PAD_LEFT );

        $body = self::section_heading( '⚡ Instant Win — ' . esc_html( $instant_win->prize_name ) )
            . self::muted_p( 'Hi <strong>' . esc_html( $buyer_name ) . '</strong>, one of your tickets just won an instant prize in <strong>' . esc_html( $raffle->title ) . '</strong>!' )
            . self::box(
                '<table width="100%" cellpadding="0" cellspacing="0"><tr>
                    <td style="padding:8px 0;">
                        <div style="font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Winning Ticket</div>
                        <div style="font-family:monospace;font-size:20px;font-weight:800;color:' . esc_attr( $col ) . ';">#' . esc_html( $formatted ) . '</div>
                    </td>
                    <td style="padding:8px 0;text-align:right;">
                        <div style="font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Prize Won</div>
                        <div style="font-size:18px;font-weight:800;color:#1f2937;">' . esc_html( $instant_win->prize_name ) . '</div>
                    </td>
                </tr></table>',
                '#f0fff4', '#bbf7d0'
            )
            . self::muted_p( 'Our team will be in contact to arrange your prize. You are still entered in the main draw — good luck! 🍀' )
            . self::cta_button( home_url( '/my-account/my-raffles/' ), 'View My Raffles', $col );

        wp_mail(
            $buyer_email,
            '⚡ Instant Win! ' . $instant_win->prize_name . ' — ' . $raffle->title,
            self::wrap( $body, 'You won an instant prize in ' . $raffle->title . '!' ),
            self::get_headers( $s )
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. Draw Reminder (24h before)
    // ─────────────────────────────────────────────────────────────────────

    public static function send_draw_reminder( $buyer_email, $buyer_name, $raffle ) {
        $s   = self::get_settings();
        $col = $s['accent_color'];

        $draw_formatted = date_i18n( 'l jS F Y \a\t g:i a', strtotime( $raffle->draw_date ) );
        $remaining      = max( 0, (int) $raffle->total_tickets - (int) $raffle->sold_tickets );

        $body = self::section_heading( '⏰ The Draw is Tomorrow!' )
            . self::muted_p( 'Hi <strong>' . esc_html( $buyer_name ) . '</strong>, a quick reminder that the draw for <strong>' . esc_html( $raffle->title ) . '</strong> is taking place in approximately 24 hours.' )
            . self::box(
                '<table width="100%" cellpadding="0" cellspacing="0"><tr>'
                . self::stat_cell( $draw_formatted, 'Draw Time' )
                . self::stat_cell( $remaining, 'Tickets Remaining' )
                . '</tr></table>',
                '#fffbeb', '#fde68a'
            )
            . self::muted_p( 'Make sure you\'re ready — the winner will be drawn live and notified immediately by email.' )
            . ( $remaining > 0
                ? self::muted_p( 'There are still tickets available — <a href="' . esc_url( get_permalink( $raffle->wc_product_id ) ) . '" style="color:' . esc_attr( $col ) . ';font-weight:700;">enter more tickets</a> to increase your chances!' )
                : '' )
            . self::cta_button( home_url( '/my-account/my-raffles/' ), 'View My Entry', $col );

        wp_mail(
            $buyer_email,
            '⏰ Draw Tomorrow: ' . $raffle->title,
            self::wrap( $body, 'The draw for ' . $raffle->title . ' is in 24 hours — good luck!' ),
            self::get_headers( $s )
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // 5. Admin Sold-Out Notification
    // ─────────────────────────────────────────────────────────────────────

    public static function send_admin_sold_out( $raffle ) {
        $s        = self::get_settings();
        $col      = $s['accent_color'];
        $admin    = get_option( 'admin_email' );
        $edit_url = admin_url( 'admin.php?page=raffle-list&action=edit&id=' . $raffle->id );
        $revenue  = wpr_currency_symbol() . number_format( (float) $raffle->sold_tickets * (float) $raffle->ticket_price, 2 );

        $body = self::section_heading( '🎉 Raffle Sold Out: ' . esc_html( $raffle->title ) )
            . self::muted_p( 'Great news! All tickets for <strong>' . esc_html( $raffle->title ) . '</strong> have been sold. Here\'s a summary:' )
            . self::box(
                '<table width="100%" cellpadding="0" cellspacing="0"><tr>'
                . self::stat_cell( $raffle->total_tickets, 'Total Tickets' )
                . self::stat_cell( $revenue, 'Gross Revenue' )
                . self::stat_cell( wpr_price( $raffle->ticket_price ), 'Per Ticket' )
                . '</tr></table>'
            )
            . self::muted_p( 'You can now trigger the draw from the admin panel.' )
            . self::cta_button( $edit_url, 'Go to Raffle Admin', $col );

        wp_mail(
            $admin,
            '🎉 Sold Out: ' . $raffle->title,
            self::wrap( $body, $raffle->title . ' has sold out. Time to draw the winner!' ),
            self::get_headers( $s )
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // 6. Test Email (called from admin settings)
    // ─────────────────────────────────────────────────────────────────────

    public static function send_test_email( $to ) {
        $s   = self::get_settings();
        $col = $s['accent_color'];

        $body = self::section_heading( '✅ Your email is configured correctly!' )
            . self::muted_p( 'This is a test email from <strong>' . esc_html( $s['from_name'] ) . '</strong>. If you can read this, your WPRaffle email settings are working perfectly.' )
            . self::box(
                '<table width="100%" cellpadding="0" cellspacing="0"><tr>'
                . self::stat_cell( esc_html( $s['from_name'] ), 'From Name' )
                . self::stat_cell( esc_html( $s['from_email'] ), 'From Email' )
                . self::stat_cell( esc_html( $s['accent_color'] ), 'Accent Colour' )
                . '</tr></table>'
            )
            . self::cta_button( admin_url( 'admin.php?page=wpraffle-settings&tab=email' ), 'Back to Email Settings', $col );

        return wp_mail(
            $to,
            'WPRaffle — Test Email',
            self::wrap( $body, 'Your WPRaffle email configuration is working!' ),
            self::get_headers( $s )
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // 7. Data-deletion confirmation (GDPR two-step)
    // ─────────────────────────────────────────────────────────────────────

    public static function send_deletion_confirm( $email, $name, $confirm_url ) {
        $s   = self::get_settings();
        $col = $s['accent_color'];

        $body = self::section_heading( 'Confirm your data deletion request' )
            . self::muted_p( sprintf(
                'Hi <strong>%s</strong>, you requested deletion of your raffle data on <strong>%s</strong>.',
                esc_html( $name ),
                esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) )
            ) )
            . self::muted_p( 'Click the button below within <strong>24 hours</strong> to confirm. Purchase records are retained for regulatory compliance, but your personal information (name, email) will be anonymised.' )
            . self::cta_button( $confirm_url, 'Confirm Data Deletion', $col )
            . self::muted_p( 'If you did not request this, you can safely ignore this email — no data will be changed.' );

        return wp_mail(
            $email,
            'Confirm your WPRaffle data deletion',
            self::wrap( $body, 'Confirm your data deletion request' ),
            self::get_headers( $s )
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // 8. Consolation coupon (sent to non-winning entrants after a draw)
    // ─────────────────────────────────────────────────────────────────────

    public static function send_consolation_coupon( $email, $name, $raffle, $coupon_code, $discount ) {
        $s   = self::get_settings();
        $col = $s['accent_color'];

        $shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop' );

        $body = self::section_heading( 'Better luck next time — here\'s a little something' )
            . self::muted_p( sprintf(
                'Hi <strong>%s</strong>, the draw for <strong>%s</strong> has taken place and unfortunately your ticket wasn\'t selected this time.',
                esc_html( $name ),
                esc_html( $raffle->title )
            ) )
            . self::box(
                '<p style="margin:0;font-size:15px;color:#475569;">' . esc_html__( 'As a thank-you for entering, here is a coupon for your next competition:', 'wpraffle' ) . '</p>'
                . '<p style="margin:8px 0 0;font-size:24px;font-weight:800;color:' . esc_attr( $col ) . ';">' . esc_html( $discount ) . ' off</p>'
                . '<p style="margin:4px 0 0;font-size:13px;color:#64748b;">' . esc_html__( 'Use code at checkout:', 'wpraffle' ) . '</p>'
                . '<p style="margin:2px 0 0;font-size:20px;font-weight:700;letter-spacing:2px;color:#0f172a;">' . esc_html( $coupon_code ) . '</p>'
            )
            . self::cta_button( $shop_url, 'Browse Competitions', $col )
            . self::muted_p( esc_html__( 'Limited to one use, tied to your email address. Expiry applies.', 'wpraffle' ) );

        return wp_mail(
            $email,
            sprintf( __( 'Your %s consolation coupon', 'wpraffle' ), $discount ),
            self::wrap( $body, 'A thank-you from ' . $raffle->title ),
            self::get_headers( $s )
        );
    }
}
