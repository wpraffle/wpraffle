<?php
/**
 * Account tab: Responsible Gambling.
 *
 * Player-controlled spend limits, self-exclusion, and reality-check prefs.
 * All limits are enforced server-side in the purchase flow; this UI only
 * captures preferences. Limit increases have a 24h cool-off.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id    = get_current_user_id();
$settings   = Raffle_Responsible_Gambling::get_settings( $user_id );
$spend_used = Raffle_Responsible_Gambling::get_spend_in_window( $user_id, $settings->spend_limit_period );
$rg_nonce   = wp_create_nonce( 'raffle_rg_nonce' );
$now        = current_time( 'mysql' );

$is_excluded = $settings->self_excluded_until && $settings->self_excluded_until > $now;
$is_locked   = ! empty( $settings->operator_locked ) || ( $settings->locked_until && $settings->locked_until > $now );
$cool_off    = $settings->cool_off_change_until && $settings->cool_off_change_until > $now;
?>

<h3 style="margin:0 0 6px;font-size:18px;color:var(--wpr-text-primary);">Responsible Gambling</h3>
<p style="color:var(--wpr-text-muted);font-size:13px;margin:0 0 20px;">Tools to help you stay in control of your competition entries. These limits are enforced automatically.</p>

<?php if ( $is_excluded ) : ?>
    <div style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b;border-radius:10px;padding:16px;margin-bottom:20px;">
        <strong><svg class="wpr-icon wpr-icon--sm"><use href="#wpr-x-circle"></use></svg> You are currently self-excluded</strong> until <?php echo esc_html( date_i18n( 'jS F Y g:i a', strtotime( $settings->self_excluded_until ) ) ); ?>.
        You cannot enter competitions during this period, and the exclusion cannot be lifted early.
    </div>
<?php elseif ( $is_locked ) : ?>
    <div style="background:var(--wpr-accent-bg);border:1px solid var(--wpr-accent-border);color:var(--wpr-accent-text);border-radius:10px;padding:16px;margin-bottom:20px;">
        <strong><svg class="wpr-icon wpr-icon--sm"><use href="#wpr-lock"></use></svg> Your account is locked</strong>. Reason: <?php echo esc_html( $settings->operator_lock_reason ? $settings->operator_lock_reason : 'operator lock' ); ?>.
        Please contact support.
    </div>
<?php endif; ?>

<!-- Spend Limit -->
<div style="background:var(--wpr-bg-surface);border:1px solid var(--wpr-border-color);border-radius:12px;padding:20px;margin-bottom:16px;">
    <h4 style="margin:0 0 6px;font-size:15px;color:var(--wpr-text-primary);"><svg class="wpr-icon wpr-icon--sm"><use href="#wpr-chart"></use></svg> Spending Limit</h4>
    <p style="font-size:12px;color:var(--wpr-text-muted);margin:0 0 14px;">Caps how much you can spend on competition entries per period.</p>

    <?php if ( (float) $settings->spend_limit_amount > 0 ) : ?>
        <div style="background:var(--wpr-success-bg);border:1px solid var(--wpr-success-bg);border-radius:8px;padding:12px;margin-bottom:14px;">
            <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--wpr-success-text);">
                <span>Used this <?php echo esc_html( $settings->spend_limit_period ); ?>:</span>
                <strong><?php echo esc_html( wpr_price( $spend_used ) ); ?> / <?php echo esc_html( wpr_price( $settings->spend_limit_amount ) ); ?></strong>
            </div>
            <?php $pct = min( 100, round( ( $spend_used / max( 1, $settings->spend_limit_amount ) ) * 100 ) ); ?>
            <div style="background:var(--wpr-bg-muted);border-radius:10px;height:8px;margin-top:8px;overflow:hidden;">
                <div style="background:<?php echo $pct >= 90 ? '#dc2626' : 'var(--wpr-success)'; ?>;width:<?php echo esc_attr( $pct ); ?>%;height:100%;"></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ( $cool_off ) : ?>
        <div style="background:var(--wpr-accent-bg);border:1px solid var(--wpr-accent-border);border-radius:8px;padding:10px;font-size:12px;color:var(--wpr-accent-text);margin-bottom:12px;">
            An increase to your limit is pending and will take effect after <?php echo esc_html( date_i18n( 'jS F Y g:i a', strtotime( $settings->cool_off_change_until ) ) ); ?> (24-hour cool-off).
        </div>
    <?php endif; ?>

    <form id="wpraffle-rg-limit-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
        <div>
            <label style="display:block;font-size:11px;color:var(--wpr-text-muted);margin-bottom:3px;">Period</label>
            <select name="period" style="padding:6px 8px;border:1px solid var(--wpr-border-color);border-radius:6px;background:var(--wpr-bg-surface);color:var(--wpr-text-primary);">
                <option value="day" <?php selected( $settings->spend_limit_period, 'day' ); ?>>Daily</option>
                <option value="week" <?php selected( $settings->spend_limit_period, 'week' ); ?>>Weekly</option>
                <option value="month" <?php selected( $settings->spend_limit_period, 'month' ); ?>>Monthly</option>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:11px;color:var(--wpr-text-muted);margin-bottom:3px;">Limit (<?php echo esc_html( wpr_currency_symbol() ); ?>)</label>
            <input type="number" name="amount" min="0" step="0.01" value="<?php echo esc_attr( $settings->spend_limit_amount ); ?>" style="padding:6px 8px;border:1px solid var(--wpr-border-color);border-radius:6px;width:120px;background:var(--wpr-bg-surface);color:var(--wpr-text-primary);">
        </div>
        <button type="submit" class="woocommerce-Button button" style="background:var(--wpr-accent);color:var(--wpr-text-inverse);">Save Limit</button>
    </form>
    <p style="font-size:11px;color:var(--wpr-text-muted);margin-top:8px;">Decreases apply immediately. Increases have a 24-hour cool-off period.</p>
</div>

<!-- Self-Exclusion -->
<div style="background:var(--wpr-bg-surface);border:1px solid var(--wpr-border-color);border-radius:12px;padding:20px;margin-bottom:16px;">
    <h4 style="margin:0 0 6px;font-size:15px;color:var(--wpr-text-primary);"><svg class="wpr-icon wpr-icon--sm"><use href="#wpr-x-circle"></use></svg> Self-Exclusion</h4>
    <p style="font-size:12px;color:var(--wpr-text-muted);margin:0 0 14px;">Block yourself from all competitions for a chosen period. <strong>Cannot be reversed early.</strong></p>
    <form id="wpraffle-rg-exclude-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;" <?php echo $is_excluded ? 'onsubmit="return false;"' : ''; ?>>
        <div>
            <label style="display:block;font-size:11px;color:var(--wpr-text-muted);margin-bottom:3px;">Duration</label>
            <select name="days" style="padding:6px 8px;border:1px solid var(--wpr-border-color);border-radius:6px;background:var(--wpr-bg-surface);color:var(--wpr-text-primary);" <?php echo $is_excluded ? 'disabled' : ''; ?>>
                <option value="7">7 days</option>
                <option value="30">30 days</option>
                <option value="90">3 months</option>
                <option value="180">6 months</option>
                <option value="365">1 year</option>
            </select>
        </div>
        <button type="submit" class="woocommerce-Button button" style="background:#dc2626;color:#fff;" <?php echo $is_excluded ? 'disabled' : ''; ?>>Exclude Me</button>
    </form>
</div>

<script>
jQuery(function($){
    $('#wpraffle-rg-limit-form').on('submit', function(e){
        e.preventDefault();
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('Saving...');
        $.post(rafflePublic.ajax_url, {
            action: 'raffle_rg_save_limit',
            nonce: '<?php echo esc_js( $rg_nonce ); ?>',
            period: $(this).find('[name="period"]').val(),
            amount: $(this).find('[name="amount"]').val()
        }, function(res){
            btn.prop('disabled', false).text('Save Limit');
            alert(res.success ? res.data.message : (res.data.message || 'Error'));
            if (res.success) location.reload();
        }).fail(function(){ btn.prop('disabled', false).text('Save Limit'); alert('Connection error.'); });
    });

    $('#wpraffle-rg-exclude-form').on('submit', function(e){
        e.preventDefault();
        if (!confirm('Are you sure? Self-exclusion CANNOT be reversed early.')) return;
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('Processing...');
        $.post(rafflePublic.ajax_url, {
            action: 'raffle_rg_self_exclude',
            nonce: '<?php echo esc_js( $rg_nonce ); ?>',
            days: $(this).find('[name="days"]').val()
        }, function(res){
            btn.prop('disabled', false).text('Exclude Me');
            alert(res.success ? res.data.message : (res.data.message || 'Error'));
            if (res.success) location.reload();
        }).fail(function(){ btn.prop('disabled', false).text('Exclude Me'); alert('Connection error.'); });
    });
});
</script>