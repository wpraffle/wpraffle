<?php
/**
 * Shared purchase modal partial.
 *
 * Single source of truth for the purchase modal markup. Included by both the
 * canonical raffle-display.php template and the Elementor "Raffle Purchase
 * Modal" widget, so the JS hooks in public.js (which bind #raffle-modal,
 * #raffle-purchase-form, .raffle-modal-close) always find the expected
 * elements regardless of how the page was composed.
 *
 * Expected $args:
 *   $raffle  Raffle row object.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! isset( $raffle ) || ! $raffle ) {
    return;
}
?>
<div class="raffle-modal" id="raffle-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="raffle-modal-title">
    <div class="raffle-modal-content">
        <button type="button" class="raffle-modal-close" aria-label="Close">&times;</button>
        <div class="raffle-modal-header">
            <h3 id="raffle-modal-title">Complete Purchase</h3>
        </div>

        <div class="raffle-order-summary" aria-live="polite">
            <div class="raffle-order-summary__row">
                <span class="raffle-order-summary__label" data-summary="qty-label">1 ticket</span>
                <span class="raffle-order-summary__unit" data-summary="unit-price"></span>
            </div>
            <div class="raffle-order-summary__row raffle-order-summary__row--total">
                <span class="raffle-order-summary__label">Total</span>
                <span class="raffle-order-summary__total" data-summary="total"></span>
            </div>
            <div class="raffle-order-summary__savings" data-summary="savings" style="display:none;"></div>
        </div>
        <form id="raffle-purchase-form">
            <input type="hidden" name="raffle_id" value="<?php echo esc_attr( $raffle->id ); ?>">
            <input type="hidden" name="quantity" id="raffle-quantity" value="">
            <input type="hidden" name="selected_numbers" id="raffle-selected-numbers" value="">
            <input type="hidden" name="answer_index" id="raffle-answer-index" value="-1">

            <div class="raffle-form-group">
                <label for="buyer_name">Full name</label>
                <input type="text" id="buyer_name" name="buyer_name" required placeholder="E.g.: John Smith">
            </div>
            <div class="raffle-form-group">
                <label for="buyer_email">Email address</label>
                <input type="email" id="buyer_email" name="buyer_email" required placeholder="john@example.com">
            </div>
            <button type="submit" class="raffle-submit-btn">
                <?php if ( class_exists( 'Raffle_WooCommerce' ) && Raffle_WooCommerce::is_available() ) : ?>
                    <span class="raffle-submit-btn-icon"><?php wpr_icon( 'ticket', 'wpr-icon--xs' ); ?></span> Proceed to Payment
                <?php else : ?>
                    <span class="raffle-submit-btn-icon"><?php wpr_icon( 'ticket', 'wpr-icon--xs' ); ?></span> Confirm Purchase
                <?php endif; ?>
            </button>
        </form>
        <div class="raffle-loading" style="display:none;">
            <div class="raffle-spinner"></div>
            <span>Processing your purchase...</span>
        </div>
        <div class="raffle-modal-secure"><?php wpr_icon( 'shield', 'wpr-icon--xs' ); ?> Your data is protected</div>
    </div>
</div>

<!-- Confirmation -->
<div class="raffle-confirmation" id="raffle-confirmation" style="display:none;">
    <div class="raffle-confirmation-content">
        <button type="button" class="raffle-modal-close" aria-label="Close">&times;</button>
        <div class="raffle-confirmation-icon"><?php wpr_icon( 'star', 'wpr-icon--2xl wpr-icon--primary', 'Success' ); ?></div>
        <h3>Purchase Successful!</h3>
        <p>Your ticket numbers:</p>
        <div class="raffle-ticket-numbers" id="raffle-ticket-numbers"></div>
        <p class="raffle-confirmation-email">
            <?php wpr_icon( 'mail', 'wpr-icon--xs' ); ?> A confirmation email with your numbers has been sent.
        </p>
    </div>
</div>
