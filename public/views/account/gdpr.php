<?php
/**
 * Account tab: Data & Privacy (expanded GDPR).
 *
 * Surfaces the existing Raffle_Privacy export/erasure actions plus the new
 * data tables (credits, payouts, RG settings). Deletion requires double
 * confirmation.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id     = get_current_user_id();
$gdpr_nonce  = wp_create_nonce( 'raffle_my_data_nonce' );
?>
<h3 style="margin:0 0 6px;font-size:18px;color:var(--wpr-text-primary);">Data & Privacy</h3>
<p style="color:var(--wpr-text-muted);font-size:13px;margin:0 0 20px;">You have the right to export or delete your raffle data in accordance with GDPR regulations.</p>

<!-- What we hold -->
<div style="background:var(--wpr-bg-muted);border:1px solid var(--wpr-border-color);border-radius:12px;padding:18px;margin-bottom:20px;">
    <h4 style="margin:0 0 10px;font-size:14px;color:var(--wpr-text-primary);">What data we hold about you</h4>
    <ul style="margin:0;padding-left:18px;font-size:13px;color:var(--wpr-text-muted);line-height:1.7;">
        <li><strong>Competition entries</strong> — tickets, purchases, entry dates</li>
        <li><strong>Wins</strong> — main prize wins, instant wins, payouts</li>
        <li><strong>Account credit</strong> — ledger of bonuses and adjustments</li>
        <li><strong>Wallet activity</strong> — winnings credited to your cash wallet</li>
        <li><strong>Responsible gambling</strong> — spend limits, self-exclusion</li>
        <li><strong>Referrals</strong> — referral codes and bonus awards</li>
    </ul>
</div>

<!-- Actions -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
    <button type="button" id="raffle-export-my-data-btn" class="woocommerce-Button button" style="background:var(--wpr-accent);color:var(--wpr-text-inverse);">
        <svg class="wpr-icon wpr-icon--sm"><use href="#wpr-arrow-right"></use></svg> Export My Data
    </button>
    <button type="button" id="raffle-delete-my-data-btn" class="woocommerce-Button button" style="background:var(--wpr-bg-surface);color:#dc2626;border:1px solid #fca5a5;">
        <svg class="wpr-icon wpr-icon--sm"><use href="#wpr-x-circle"></use></svg> Request Data Deletion
    </button>
</div>

<div id="raffle-privacy-status" style="margin-top:12px;font-size:13px;display:none;"></div>

<script>
(function(){
    var privacyNonce = '<?php echo esc_js( $gdpr_nonce ); ?>';

    document.getElementById('raffle-export-my-data-btn').addEventListener('click', function(){
        var btn = this;
        btn.disabled = true; btn.style.opacity = '0.6';
        var statusEl = document.getElementById('raffle-privacy-status');
        statusEl.style.display = 'block'; statusEl.style.color = '#1d4ed8'; statusEl.textContent = 'Exporting your data...';
        jQuery.post(rafflePublic.ajax_url, {
            action: 'raffle_export_my_data',
            nonce: privacyNonce
        }, function(res){
            btn.disabled = false; btn.style.opacity = '1';
            if (res.success) {
                var blob = new Blob([JSON.stringify(res.data, null, 2)], {type:'application/json'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url; a.download = 'raffle-data-export.json'; a.click();
                URL.revokeObjectURL(url);
                statusEl.style.color = '#166534'; statusEl.textContent = 'Data exported successfully!';
            } else {
                statusEl.style.color = '#dc2626'; statusEl.textContent = res.data.message || 'Export failed.';
            }
        }).fail(function(){
            btn.disabled = false; btn.style.opacity = '1';
            statusEl.style.color = '#dc2626'; statusEl.textContent = 'Export failed. Please try again.';
        });
    });

    document.getElementById('raffle-delete-my-data-btn').addEventListener('click', function(){
        // Double confirmation (closes security review finding L5).
        if (!confirm('Step 1 of 2: This will permanently anonymise your personal data. Continue?')) return;
        if (!confirm('Step 2 of 2: This action CANNOT be undone. Are you absolutely sure?')) return;
        var btn = this;
        btn.disabled = true; btn.style.opacity = '0.6';
        var statusEl = document.getElementById('raffle-privacy-status');
        statusEl.style.display = 'block'; statusEl.style.color = '#dc2626'; statusEl.textContent = 'Processing deletion request...';
        jQuery.post(rafflePublic.ajax_url, {
            action: 'raffle_request_deletion',
            nonce: privacyNonce
        }, function(res){
            btn.disabled = false; btn.style.opacity = '1';
            if (res.success) {
                statusEl.style.color = '#166534'; statusEl.textContent = res.data.message;
            } else {
                statusEl.style.color = '#dc2626'; statusEl.textContent = res.data.message || 'Deletion failed.';
            }
        }).fail(function(){
            btn.disabled = false; btn.style.opacity = '1';
            statusEl.style.color = '#dc2626'; statusEl.textContent = 'Deletion failed. Please try again.';
        });
    });
})();
</script>