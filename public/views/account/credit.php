<?php
/**
 * Account tab: Account Credit (site-credit ledger).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id      = get_current_user_id();
$balance      = Raffle_Credits::get_balance( $user_id );
$history      = Raffle_Credits::get_history( $user_id, 25 );
?>

<h3 style="margin:0 0 18px;font-size:18px;color:var(--wpr-text-primary);">Account Credit</h3>

<div style="background:linear-gradient(135deg,var(--wpr-accent),var(--wpr-accent-dark));color:var(--wpr-text-inverse);border-radius:16px;padding:28px;text-align:center;margin-bottom:24px;">
    <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;opacity:0.9;">Available Credit</div>
    <div style="font-size:36px;font-weight:800;margin-top:6px;"><?php echo esc_html( wpr_price( $balance ) ); ?></div>
    <div style="font-size:12px;opacity:0.8;margin-top:6px;">Use credit toward raffle entries where accepted.</div>
</div>

<h4 style="font-size:14px;font-weight:700;color:var(--wpr-text-primary);margin:0 0 12px;">Transaction History</h4>

<?php if ( empty( $history ) ) : ?>
    <div style="background:var(--wpr-bg-muted);border:1px dashed var(--wpr-border-color);border-radius:8px;padding:20px;text-align:center;color:var(--wpr-text-muted);font-size:14px;">
        No credit transactions yet.
    </div>
<?php else : ?>
    <table class="woocommerce-table shop_table" style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="border-bottom:2px solid var(--wpr-border-color);text-align:left;">
                <th style="padding:8px;">Date</th>
                <th style="padding:8px;">Type</th>
                <th style="padding:8px;">Details</th>
                <th style="padding:8px;text-align:right;">Amount</th>
                <th style="padding:8px;text-align:right;">Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $history as $h ) : ?>
                <tr style="border-bottom:1px solid var(--wpr-border-color);">
                    <td style="padding:8px;color:var(--wpr-text-muted);"><?php echo esc_html( date_i18n( 'd M Y H:i', strtotime( $h->created_at ) ) ); ?></td>
                    <td style="padding:8px;">
                        <span style="background:var(--wpr-bg-muted);padding:2px 8px;border-radius:4px;font-size:11px;text-transform:uppercase;font-weight:600;color:var(--wpr-text-muted);"><?php echo esc_html( $h->type ); ?></span>
                    </td>
                    <td style="padding:8px;color:var(--wpr-text-primary);"><?php echo esc_html( $h->reason ? $h->reason : '—' ); ?></td>
                    <td style="padding:8px;text-align:right;font-weight:700;color:<?php echo $h->amount >= 0 ? 'var(--wpr-success, #059669)' : '#dc2626'; ?>;">
                        <?php echo esc_html( ( $h->amount >= 0 ? '+' : '' ) . wpr_price( $h->amount ) ); ?>
                    </td>
                    <td style="padding:8px;text-align:right;color:var(--wpr-text-muted);font-family:monospace;"><?php echo esc_html( wpr_price( $h->balance_after ) ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>