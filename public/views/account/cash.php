<?php
/**
 * Account tab: Account Cash (real-money wallet via TerraWallet).
 *
 * Read-only view that wraps TerraWallet's balance + transactions. All
 * deposit/withdrawal actions are handled by TerraWallet's own pages and
 * KYC flow — WPRaffle never exposes a debit/withdraw action here.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Raffle_Wallet_Adapter' ) || ! Raffle_Wallet_Adapter::is_available() ) {
    echo '<div class="woocommerce-info">Account Cash requires the WooWallet plugin (free or Pro), which is not currently active.</div>';
    return;
}

$user_id      = get_current_user_id();
$balance      = Raffle_Wallet_Adapter::get_balance( $user_id );
$transactions = Raffle_Wallet_Adapter::get_transactions( $user_id, 20 );
$hold_hours   = Raffle_Wallet_Adapter::get_hold_hours();

// Wallet pages (deposit/withdraw) are owned by TerraWallet.
$wallet_url = Raffle_Wallet_Adapter::get_wallet_url();
?>

<h3 style="margin:0 0 18px;font-size:18px;color:var(--wpr-text-primary);">Account Cash</h3>

<div style="background:linear-gradient(135deg,var(--wpr-success, #059669),color-mix(in srgb, var(--wpr-success, #059669) 70%, var(--wpr-bg-surface)));color:#fff;border-radius:16px;padding:28px;text-align:center;margin-bottom:20px;">
    <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;opacity:0.9;">Withdrawable Balance</div>
    <div style="font-size:36px;font-weight:800;margin-top:6px;"><?php echo esc_html( wpr_price( $balance ) ); ?></div>
    <?php if ( $hold_hours > 0 ) : ?>
        <div style="font-size:11px;opacity:0.85;margin-top:6px;">Raffle winnings are subject to a <?php echo esc_html( $hold_hours ); ?>-hour hold before withdrawal.</div>
    <?php endif; ?>
</div>

<div style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;">
    <a href="<?php echo esc_url( $wallet_url ); ?>" class="woocommerce-Button button" style="background:var(--wpr-success, #059669);color:var(--wpr-text-inverse);">Manage Wallet</a>
</div>

<h4 style="font-size:14px;font-weight:700;color:var(--wpr-text-primary);margin:0 0 12px;">Recent Wallet Activity</h4>

<?php if ( empty( $transactions ) ) : ?>
    <div style="background:var(--wpr-bg-muted);border:1px dashed var(--wpr-border-color);border-radius:8px;padding:20px;text-align:center;color:var(--wpr-text-muted);font-size:14px;">
        No wallet transactions yet.
    </div>
<?php else : ?>
    <table class="woocommerce-table shop_table" style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="border-bottom:2px solid var(--wpr-border-color);text-align:left;">
                <th style="padding:8px;">Date</th>
                <th style="padding:8px;">Details</th>
                <th style="padding:8px;text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $transactions as $tx ) :
                $amount = isset( $tx->credit ) ? (float) $tx->credit : 0;
                if ( ! $amount ) { $amount = -1 * ( isset( $tx->debit ) ? (float) $tx->debit : 0 ); }
            ?>
                <tr style="border-bottom:1px solid var(--wpr-border-color);">
                    <td style="padding:8px;color:var(--wpr-text-muted);"><?php echo esc_html( isset( $tx->date ) ? date_i18n( 'd M Y H:i', strtotime( $tx->date ) ) : '—' ); ?></td>
                    <td style="padding:8px;color:var(--wpr-text-primary);"><?php echo esc_html( isset( $tx->details ) ? $tx->details : ( isset( $tx->entry ) ? $tx->entry : '—' ) ); ?></td>
                    <td style="padding:8px;text-align:right;font-weight:700;color:<?php echo $amount >= 0 ? 'var(--wpr-success, #059669)' : '#dc2626'; ?>;">
                        <?php echo esc_html( ( $amount >= 0 ? '+' : '' ) . wpr_price( $amount ) ); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>