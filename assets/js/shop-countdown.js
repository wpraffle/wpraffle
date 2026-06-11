/**
 * Raffle System — Shop Loop Countdown Timers
 * Initialises live countdowns for all rc-card__countdown elements on the WooCommerce shop page.
 */
(function ($) {
    'use strict';

    function updateCountdowns() {
        var now = new Date().getTime();

        $('.rc-card__countdown').each(function () {
            var $el = $(this);
            var drawDate = $el.data('draw-date');
            if (!drawDate) return;

            var target = new Date(drawDate).getTime();
            var diff = target - now;

            if (diff <= 0) {
                $el.find('.rc-cd-days').text('0');
                $el.find('.rc-cd-hours').text('0');
                $el.find('.rc-cd-mins').text('0');
                $el.find('.rc-cd-secs').text('0');
                return;
            }

            var days  = Math.floor(diff / (1000 * 60 * 60 * 24));
            var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var mins  = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            var secs  = Math.floor((diff % (1000 * 60)) / 1000);

            $el.find('.rc-cd-days').text(days);
            $el.find('.rc-cd-hours').text(hours);
            $el.find('.rc-cd-mins').text(mins);
            $el.find('.rc-cd-secs').text(secs);
        });
    }

    $(document).ready(function () {
        // Run immediately and then every second
        updateCountdowns();
        setInterval(updateCountdowns, 1000);

        // Make entire card clickable
        $(document).on('click', '.rc-card', function (e) {
            // Don't intercept clicks on the CTA link itself
            if ($(e.target).closest('.rc-card__cta').length) return;
            var link = $(this).data('raffle-link');
            if (link) {
                window.location.href = link;
            }
        });
    });
})(jQuery);
