/**
 * Raffle System — Shop Loop Countdown Timers
 * Initialises live countdowns for all rc-card__countdown elements on the WooCommerce shop page.
 * Zero-pads digits to match the product-page countdown and marks expired cards.
 */
(function ($) {
    'use strict';

    // Zero-pad to 2 digits so the shop card shows 09:05:03, not 9:5:3.
    function pad(n) {
        n = parseInt(n, 10) || 0;
        return n < 10 ? '0' + n : '' + n;
    }

    function updateCountdowns() {
        // Skip work entirely when the tab is hidden — saves battery on mobile.
        if (document.hidden) {
            return;
        }
        var now = new Date().getTime();

        $('.rc-card__countdown').each(function () {
            var $el = $(this);
            var drawDate = $el.data('draw-date');
            if (!drawDate) return;

            var target = new Date(drawDate).getTime();
            var diff = target - now;

            if (diff <= 0) {
                $el.find('.rc-cd-days').text('00');
                $el.find('.rc-cd-hours').text('00');
                $el.find('.rc-cd-mins').text('00');
                $el.find('.rc-cd-secs').text('00');
                // Mark the card as expired so CSS can grey it out and the
                // click/keyboard handlers below treat it as non-clickable.
                $el.closest('.rc-card').addClass('rc-card--expired');
                return;
            }

            // Add an "ending soon" flag (<= 24h remaining) for urgency styling.
            var $card = $el.closest('.rc-card');
            if (diff <= 24 * 60 * 60 * 1000) {
                $card.addClass('rc-card--ending-soon');
            } else {
                $card.removeClass('rc-card--ending-soon');
            }

            var days  = Math.floor(diff / (1000 * 60 * 60 * 24));
            var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var mins  = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            var secs  = Math.floor((diff % (1000 * 60)) / 1000);

            $el.find('.rc-cd-days').text(pad(days));
            $el.find('.rc-cd-hours').text(pad(hours));
            $el.find('.rc-cd-mins').text(pad(mins));
            $el.find('.rc-cd-secs').text(pad(secs));
        });
    }

    function navigateToCard($card) {
        if ($card.hasClass('rc-card--expired') || $card.hasClass('rc-card--sold-out')) {
            return; // Don't navigate into closed/sold-out competitions.
        }
        var link = $card.data('raffle-link');
        if (link) {
            window.location.href = link;
        }
    }

    $(document).ready(function () {
        // Run immediately and then every second
        updateCountdowns();
        setInterval(updateCountdowns, 1000);

        // Make entire card clickable (mouse)
        $(document).on('click', '.rc-card', function (e) {
            // Don't intercept clicks on the CTA link itself
            if ($(e.target).closest('.rc-card__cta').length) return;
            navigateToCard($(this));
        });

        // Keyboard support — Enter/Space activates the card for non-mouse users.
        $(document).on('keydown', '.rc-card', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            // Only when the card itself (not a nested link/button) has focus.
            if ($(e.target).is('a, button')) return;
            e.preventDefault();
            navigateToCard($(this));
        });
    });
})(jQuery);
