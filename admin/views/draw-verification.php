<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap rs-wrap rs-feature-page">
    <header class="rs-page-header"><div><h1 class="rs-page-title"><?php esc_html_e( 'Draw Verification', 'wpraffle' ); ?></h1><p class="rs-page-subtitle"><?php esc_html_e( 'Publish a commitment before entries open, then share a reproducible draw record.', 'wpraffle' ); ?></p></div></header>
    <div class="rs-feature-columns">
        <section class="rs-card"><h2 class="rs-card-title"><?php esc_html_e( 'Enable verification', 'wpraffle' ); ?></h2><p><?php esc_html_e( 'Choose a new raffle with no entries or winner. An existing commitment cannot be replaced.', 'wpraffle' ); ?></p>
            <form class="rs-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="wpraffle_commit_draw"><?php wp_nonce_field( 'wpraffle_commit_draw' ); ?>
                <div class="rs-form-group"><label for="rs-verification-id"><?php esc_html_e( 'Raffle ID', 'wpraffle' ); ?></label><input id="rs-verification-id" name="raffle_id" type="number" min="1" required aria-describedby="rs-verification-help"><p id="rs-verification-help" class="description"><?php esc_html_e( 'Find the raffle ID in All Raffles.', 'wpraffle' ); ?></p></div>
                <?php submit_button( __( 'Enable and publish commitment', 'wpraffle' ) ); ?>
            </form>
        </section>
        <section class="rs-card"><h2 class="rs-card-title"><?php esc_html_e( 'How it works', 'wpraffle' ); ?></h2><ol class="rs-feature-steps"><li><?php esc_html_e( 'Enable verification before accepting entries.', 'wpraffle' ); ?></li><li><?php esc_html_e( 'Display the commitment on your website and save a copy before sales begin.', 'wpraffle' ); ?></li><li><?php esc_html_e( 'Run the draw to reveal the seed and eligible ticket pool.', 'wpraffle' ); ?></li></ol><p><?php esc_html_e( 'Display the record with this shortcode, replacing 123 with your raffle ID:', 'wpraffle' ); ?></p><code class="rs-feature-code">[raffle_draw_receipt raffle_id="123"]</code><p class="description"><?php esc_html_e( 'Keep the private seed secure until the draw. Verification reproduces the selected numbers; it does not establish pool completeness or regulatory compliance.', 'wpraffle' ); ?></p></section>
    </div>
</div>
