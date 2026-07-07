(function ($) {
  "use strict";

  var modal = $("#raffle-modal");
  var confirmation = $("#raffle-confirmation");
  var selectedNumbers = [];
  var selectedBundlePrice = 0;

  // --- Entry Tabs Switcher ---
  $(".raffle-tab-btn").on("click", function () {
    $(".raffle-tab-btn").removeClass("active");
    $(this).addClass("active");
    var tab = $(this).data("tab");
    $(".raffle-tab-content").hide();
    $("#tab-" + tab).show();
  });

  // --- Range Slider & Quantity Inputs ---
  var slider = $("#raffle-qty-range-slider");
  var numInput = $("#raffle-manual-qty-num");
  var tooltip = $("#raffle-qty-slider-tooltip");

  function updateQty(qty) {
    qty = parseInt(qty) || 1;
    var min = parseInt(slider.attr("min")) || 1;
    var max = parseInt(slider.attr("max")) || 100;

    if (qty < min) qty = min;
    if (qty > max) qty = max;

    slider.val(qty);
    numInput.val(qty);

    // Update floating tooltip label
    tooltip.text(qty + (qty === 1 ? " TICKET" : " TICKETS"));

    // Position the tooltip nicely over the range slider thumb
    var percent = ((qty - min) / (max - min)) * 100;
    tooltip.css("left", "calc(" + percent + "% + (" + (8 - percent * 0.15) + "px))");

    // Toggle active state on pills + recompute bundle price.
    $(".raffle-qty-pill").removeClass("active");
    selectedBundlePrice = 0;
    $(".raffle-qty-pill").each(function() {
      if (parseInt($(this).data("qty")) === qty) {
        $(this).addClass("active");
        selectedBundlePrice = parseFloat($(this).data("bundle-price")) || 0;
      }
    });

    // Keep the modal order summary in sync as the slider/pills move.
    refreshModalSummary(qty);

    // Keep the odds-of-winning display in sync with the selected quantity.
    updateOdds(qty);
  }

  /**
   * Recalculate the "Your odds: 1 in N" display for the given ticket quantity.
   * Uses the raffle total (cached in #raffle-odds-box data-total). When the
   * buyer selects N tickets the denominator shrinks proportionally.
   */
  function updateOdds(qty) {
    var oddsBox = $("#raffle-odds-box");
    if (oddsBox.length === 0) {
      return;
    }
    qty = parseInt(qty, 10) || 1;
    if (qty < 1) qty = 1;
    var total = parseInt(oddsBox.data("total"), 10) || 0;
    if (total < 1) {
      oddsBox.hide();
      return;
    }
    var oneIn = Math.max(1, Math.round(total / qty));
    oddsBox.find(".raffle-odds-qty").text(qty);
    oddsBox.find(".raffle-odds-value").text("1 in " + oneIn.toLocaleString());
  }

  /**
   * Recompute the live order summary (qty × price = total, plus any bundle
   * savings). Called from updateQty() on every slider input and when the
   * modal opens. Reads the standard price from .raffle-price-value and the
   * active bundle price from selectedBundlePrice (0 = no bundle = per-ticket).
   */
  function refreshModalSummary(qty) {
    var summary = $(".raffle-order-summary");
    if (summary.length === 0) {
      return; // Summary block not present on this page.
    }

    qty = parseInt(qty, 10);
    if (isNaN(qty) || qty < 1) {
      qty = parseInt(slider.val(), 10) || 1;
    }

    var curSym = (window.rafflePublic && rafflePublic.currency_symbol) || "$";
    var pricePer = parseFloat($(".raffle-price-value").text().replace(curSym, "")) || 0;
    var standardTotal = qty * pricePer;

    // A bundle is active when selectedBundlePrice > 0; its price is the fixed
    // total for the whole qty. Otherwise total is qty × standard price.
    var isBundle = selectedBundlePrice > 0;
    var total = isBundle ? selectedBundlePrice : standardTotal;
    var savings = isBundle ? (standardTotal - selectedBundlePrice) : 0;

    var unitLabel = isBundle
      ? curSym + total.toFixed(2) + " for " + qty
      : curSym + pricePer.toFixed(2) + " each";

    summary.find('[data-summary="qty-label"]').text(qty + (qty === 1 ? " ticket" : " tickets"));
    summary.find('[data-summary="unit-price"]').text(unitLabel);
    summary.find('[data-summary="total"]').text(curSym + total.toFixed(2));

    var savingsEl = summary.find('[data-summary="savings"]');
    if (savings > 0) {
      savingsEl.text("You save " + curSym + savings.toFixed(2)).show();
    } else {
      savingsEl.hide();
    }
  }

  if (slider.length > 0) {
    slider.on("input", function () {
      updateQty($(this).val());
    });

    numInput.on("input", function () {
      updateQty($(this).val());
    });

    $(".raffle-slider-btn.minus").on("click", function () {
      updateQty(parseInt(slider.val()) - 1);
    });

    $(".raffle-slider-btn.plus").on("click", function () {
      updateQty(parseInt(slider.val()) + 1);
    });

    $(".raffle-qty-pill").on("click", function () {
      updateQty($(this).data("qty"));
      selectedBundlePrice = parseFloat($(this).data("bundle-price")) || 0;
    });

    // Init quantity layout
    updateQty(1);
  }

  // --- ENTER COMPETITION Submit Handler ---
  $("#raffle-enter-comp-submit-btn").on("click", function (e) {
    e.preventDefault();

    var questionWrap = $(".raffle-question-wrapper");
    var selectedAnswer = $('input[name="raffle_skill_answer"]:checked').val();

    if (questionWrap.length > 0) {
      if (selectedAnswer === undefined) {
        $(".raffle-question-error")
          .text("Please select an answer to enter the competition.")
          .show();
        questionWrap.addClass("shake");
        setTimeout(function () {
          questionWrap.removeClass("shake");
        }, 500);
        return;
      }
      // NOTE: Correct answer is validated server-side only to prevent answer exposure.
      $(".raffle-question-error").hide();
    }

    // Set selected answer index
    $("#raffle-answer-index").val(selectedAnswer !== undefined ? selectedAnswer : -1);

    var qty = parseInt(slider.val()) || 1;
    var raffleId = $(".raffle-container").data("raffle-id");

    $("#raffle-quantity").val(qty);

    refreshModalSummary(qty);

    // If manual selection is enabled
    if ($("#raffle-manual-selection").length > 0) {
      $("#manual-qty-target").text(qty);
      $("#manual-qty-selected").text(0);
      $("#raffle-selected-numbers").val("");
      selectedNumbers = [];
      loadManualSelection(raffleId, qty);
      $("#raffle-manual-selection").show();
      $(".raffle-main-grid").hide();
      return;
    }

    openModal();
  });

  // Keep compatibility for any legacy card buy buttons if they remain
  $(".raffle-buy-btn").on("click", function (e) {
    e.preventDefault();
    var qty = $(this).data("quantity");
    var price = $(this).closest(".raffle-package-card").find(".raffle-package-price").text();
    var raffleId = $(".raffle-container").data("raffle-id");

    // Sync qty + bundle price so the live summary reflects this package.
    $("#raffle-quantity").val(qty);
    selectedBundlePrice = parseFloat(String(price).replace(/[^0-9.]/g, "")) || 0;
    if (typeof updateQty === "function" && slider.length > 0) {
      updateQty(qty);
    }
    refreshModalSummary(qty);

    if ($("#raffle-manual-selection").length > 0) {
      $("#manual-qty-target").text(qty);
      $("#manual-qty-selected").text(0);
      $("#raffle-selected-numbers").val("");
      selectedNumbers = [];
      loadManualSelection(raffleId, qty);
      $("#raffle-manual-selection").show();
      $(".raffle-main-grid").hide();
      return;
    }

    openModal();
  });

  function loadManualSelection(raffleId, targetQty) {
    var grid = $("#raffle-number-grid");
    var btn = $("#confirm-manual-selection");
    btn.prop("disabled", true);
    grid.html("<p>Loading available numbers...</p>");

    $.post(
      rafflePublic.ajax_url,
      {
        action: "raffle_get_sold_numbers",
        nonce: rafflePublic.nonce,
        raffle_id: raffleId,
      },
      function (response) {
        if (!response.success) {
          grid.html("<p>Error loading numbers.</p>");
          return;
        }
        var sold = response.data.sold || [];
        var total =
          parseInt(
            $(".raffle-progress-label-numbers").text().split("of")[1] ||
              $(".raffle-progress-detail:eq(1) .raffle-progress-detail-number")
                .text()
                .trim()
          ) || 100;
        var html = "";

        var maxRender = Math.min(total, 5000);
        for (var i = 1; i <= maxRender; i++) {
          var isSold = sold.indexOf(i) !== -1;
          var style = isSold
            ? "background:var(--wpr-bg-muted); color:var(--wpr-text-light); cursor:not-allowed;"
            : "background:var(--wpr-bg-subtle); color:var(--wpr-text-primary); cursor:pointer;";
          html +=
            '<div class="rs-grid-number" data-num="' +
            i +
            '" data-sold="' +
            (isSold ? "1" : "0") +
            '" style="' +
            style +
            ' text-align:center; padding:10px; border-radius:4px; font-weight:bold; user-select:none;">' +
            i +
            "</div>";
        }
        grid.html(html);

        grid.find('.rs-grid-number[data-sold="0"]').on("click", function () {
          var num = $(this).data("num");
          var idx = selectedNumbers.indexOf(num);
          if (idx !== -1) {
            selectedNumbers.splice(idx, 1);
            $(this).css({ background: "var(--wpr-bg-subtle)", color: "var(--wpr-text-primary)" });
          } else {
            if (selectedNumbers.length < targetQty) {
              selectedNumbers.push(num);
              $(this).css({ background: "var(--wpr-accent)", color: "var(--wpr-text-inverse)" });
            } else {
              alert("You have already selected " + targetQty + " numbers.");
            }
          }
          $("#manual-qty-selected").text(selectedNumbers.length);
          btn.prop("disabled", selectedNumbers.length !== parseInt(targetQty));
        });
      }
    ).fail(function () {
      // Network error / 5xx / nonce expiry — previously the grid hung on the
      // "Loading..." placeholder forever with no escape. Show a retry button.
      var raffleIdRetry = raffleId;
      var targetQtyRetry = targetQty;
      grid.html(
        '<div style="text-align:center; padding:24px;">' +
        '<p style="color:var(--wpr-danger, #dc2626); margin:0 0 12px; font-weight:600;">We couldn\'t load the available numbers.</p>' +
        '<button type="button" id="rs-retry-load-numbers" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:var(--wpr-accent);color:var(--wpr-text-inverse);border:none;border-radius:6px;font-weight:700;cursor:pointer;">' +
        'Try again</button></div>'
      );
      $("#rs-retry-load-numbers").on("click", function () {
        loadManualSelection(raffleIdRetry, targetQtyRetry);
      });
    });
  }

  $("#cancel-manual-selection").on("click", function () {
    $("#raffle-manual-selection").hide();
    $(".raffle-main-grid").show();
  });

  $("#confirm-manual-selection").on("click", function () {
    $("#raffle-selected-numbers").val(selectedNumbers.join(","));
    openModal();
  });

  function handleWooCommerceAddToCart() {
    var qty = $("#raffle-quantity").val() || slider.val() || 1;
    var raffleId = $(".raffle-container").data("raffle-id");
    var answerIdx = $('input[name="raffle_skill_answer"]:checked').val();
    if (answerIdx === undefined) {
      answerIdx = -1;
    }
    var selectedNums = selectedNumbers.join(",");

    var btn = $("#raffle-enter-comp-submit-btn");
    var origText = btn.html();
    btn.prop("disabled", true).html('<span class="raffle-loading-text">Adding to Cart...</span>');

    $.post(
      rafflePublic.ajax_url,
      {
        action: "raffle_add_to_cart",
        nonce: rafflePublic.nonce,
        raffle_id: raffleId,
        quantity: qty,
        selected_numbers: selectedNums,
        answer_index: answerIdx,
        bundle_price: selectedBundlePrice
      },
      function (response) {
        if (response.success) {
          window.location.href = response.data.cart_url || rafflePublic.cart_url;
        } else {
          btn.prop("disabled", false).html(origText);
          alert(response.data.message || "Error adding to cart.");
        }
      }
    ).fail(function () {
      btn.prop("disabled", false).html(origText);
      alert("Connection error. Please try again.");
    });
  }

  // Track the element that opened the modal so focus can be restored on close
  // — a baseline accessibility requirement for dialog patterns.
  var modalTrigger = null;

  function openModal() {
    if (typeof rafflePublic.wc_enabled !== "undefined" && rafflePublic.wc_enabled === "1") {
      handleWooCommerceAddToCart();
      return;
    }
    $("#raffle-purchase-form")[0].reset();

    var qty = $("#manual-qty-target").text() || $("#raffle-quantity").val();
    $("#raffle-quantity").val(qty);
    if (selectedNumbers.length > 0) {
      $("#raffle-selected-numbers").val(selectedNumbers.join(","));
    }

    $("#raffle-purchase-form").show();
    modal.find(".raffle-loading").hide();
    modal.find(".raffle-error-msg").remove();
    refreshModalSummary(qty);
    modal.show();

    // Focus management: remember the trigger, then move focus into the dialog
    // so keyboard/screen-reader users land inside it rather than behind it.
    modalTrigger = document.activeElement;
    focusFirstInDialog(modal);
  }

  /** Move focus to the first focusable element inside a dialog. */
  function focusFirstInDialog($dialog) {
    var focusable = getFocusable($dialog);
    if (focusable.length) {
      focusable.first().trigger("focus");
    } else {
      $dialog.attr("tabindex", "-1").trigger("focus");
    }
  }

  /** Query the focusable elements within a container. */
  function getFocusable($container) {
    return $container.find(
      'a[href], area[href], input:not([disabled]), select:not([disabled]), ' +
      'textarea:not([disabled]), button:not([disabled]), iframe, object, ' +
      'embed, [tabindex]:not([tabindex="-1"]), [contenteditable="true"]'
    ).filter(":visible");
  }

  function closeModal() {
    modal.hide();
    confirmation.hide();
    // Restore focus to the element that opened the dialog.
    if (modalTrigger && typeof modalTrigger.focus === "function") {
      modalTrigger.focus();
      modalTrigger = null;
    }
  }

  $(document).on("click", ".raffle-modal-close", function () {
    closeModal();
  });

  modal.on("click", function (e) {
    if (e.target === this) closeModal();
  });

  confirmation.on("click", function (e) {
    if (e.target === this) {
      confirmation.hide();
      if (modalTrigger && typeof modalTrigger.focus === "function") {
        modalTrigger.focus();
        modalTrigger = null;
      }
    }
  });

  $(document).on("keydown", function (e) {
    if (e.key === "Escape") {
      if (modal.is(":visible") || confirmation.is(":visible")) {
        closeModal();
      }
    }
    // Focus trap: when Tab (or Shift+Tab) would escape an open dialog, wrap
    // focus to the other end so the dialog is self-contained for keyboard users.
    if (e.key === "Tab" && modal.is(":visible")) {
      var focusable = getFocusable(modal);
      if (!focusable.length) return;
      var first = focusable.first()[0];
      var last = focusable.last()[0];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });

  function showModalError(msg) {
    var form = $("#raffle-purchase-form");
    form.show();
    form.find(".raffle-submit-btn").prop("disabled", false);
    modal.find(".raffle-loading").hide();
    modal.find(".raffle-error-msg").remove();
    // role="alert" so screen readers announce the error immediately.
    var errorDiv = $('<div class="raffle-error-msg" role="alert" style="background:#fee;color:#c00;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:14px;"></div>');
    errorDiv.text(msg);
    form.before(errorDiv);
  }

  function showConfirmation(tickets) {
    modal.hide();
    var ticketsHtml = tickets.join(" &nbsp;·&nbsp; ");
    $("#raffle-ticket-numbers").html(ticketsHtml);
    confirmation.show();
  }

  $("#raffle-purchase-form").on("submit", function (e) {
    e.preventDefault();

    var form = $(this);
    var submitBtn = form.find(".raffle-submit-btn");

    submitBtn.prop("disabled", true);
    form.hide();
    modal.find(".raffle-error-msg").remove();
    modal.find(".raffle-loading").show();

    if (
      typeof rafflePublic.wc_enabled !== "undefined" &&
      rafflePublic.wc_enabled === "1"
    ) {
      handleWooCommercePurchase(form);
    } else {
      handleDirectPurchase(form);
    }
  });

  function handleDirectPurchase(form) {
    $.post(
      rafflePublic.ajax_url,
      {
        action: "raffle_purchase",
        nonce: rafflePublic.nonce,
        raffle_id: form.find('[name="raffle_id"]').val(),
        quantity: form.find('[name="quantity"]').val(),
        answer_index: form.find('[name="answer_index"]').val(),
        buyer_name: form.find('[name="buyer_name"]').val(),
        buyer_email: form.find('[name="buyer_email"]').val(),
      },
      function (response) {
        if (response.success) {
          showConfirmation(response.data.tickets);
          form[0].reset();
          form.find(".raffle-submit-btn").prop("disabled", false);
        } else {
          showModalError(response.data.message);
        }
      }
    ).fail(function () {
      showModalError("Connection error. Please try again.");
    });
  }

  function handleWooCommercePurchase(form) {
    $.post(
      rafflePublic.ajax_url,
      {
        action: "raffle_create_order",
        nonce: rafflePublic.nonce,
        raffle_id: form.find('[name="raffle_id"]').val(),
        quantity: form.find('[name="quantity"]').val(),
        selected_numbers: form.find('[name="selected_numbers"]').val(),
        answer_index: form.find('[name="answer_index"]').val(),
        buyer_name: form.find('[name="buyer_name"]').val(),
        buyer_email: form.find('[name="buyer_email"]').val(),
        bundle_price: selectedBundlePrice
      },
      function (response) {
        if (response.success && response.data.pay_url) {
          window.location.href = response.data.pay_url;
        } else {
          showModalError(response.data.message || "Error creating order.");
        }
      }
    ).fail(function () {
      showModalError("Connection error. Please try again.");
    });
  }

  confirmation.on("click", ".raffle-modal-close", function () {
    location.reload();
  });

  // --- Free Entry Form ---
  $("#raffle-free-entry-form").on("submit", function (e) {
    e.preventDefault();
    var form = $(this);
    var btn = form.find('button[type="submit"]');
    var answerIdx = form.find('input[name="free_entry_answer"]:checked').val();
    var raffleId = form.data("raffle-id");

    if (answerIdx === undefined) {
      form.find(".free-entry-error").text("Please select an answer.").show();
      return;
    }
    form.find(".free-entry-error").hide();
    btn.prop("disabled", true).text("Submitting...");

    $.post(rafflePublic.ajax_url, {
      action: "raffle_free_entry",
      nonce: rafflePublic.nonce,
      raffle_id: raffleId,
      answer_index: answerIdx,
    }, function (response) {
      if (response.success) {
        var ticketSpan = $('<strong>').text(response.data.ticket_number);
        form.html($('<div class="free-entry-success" style="background:var(--wpr-success-bg);color:var(--wpr-success-text);padding:20px;border-radius:8px;text-align:center;">')
          .append('<h3>🎉 You\'re In!</h3>')
          .append($('<p>').text('Your free entry has been submitted. Ticket number: ').append(ticketSpan))
        );
      } else {
        alert(response.data.message || "Error submitting free entry.");
        btn.prop("disabled", false).text("Submit Free Entry");
      }
    }).fail(function () {
      alert("Connection error. Please try again.");
      btn.prop("disabled", false).text("Submit Free Entry");
    });
  });

  // --- Copy Referral Link ---
  $("#copy-referral-link-btn").on("click", function () {
    var input = $("#raffle-referral-link")[0];
    input.select();
    input.setSelectionRange(0, 99999);
    if (navigator.clipboard) {
      navigator.clipboard.writeText(input.value).then(function () {
        showToast("Referral link copied!");
      });
    } else {
      document.execCommand("copy");
      showToast("Referral link copied!");
    }
  });

  function showToast(msg) {
    var toast = $('<div class="raffle-toast" style="position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:var(--wpr-success);color:var(--wpr-text-inverse);padding:12px 24px;border-radius:8px;z-index:99999;font-weight:600;"></div>');
    toast.text(msg);
    $("body").append(toast);
    setTimeout(function () { toast.fadeOut(function () { toast.remove(); }); }, 2500);
  }

  // --- Countdown timer ---
  var countdownEl =
    document.getElementById("raffle-countdown-inline") ||
    document.getElementById("raffle-countdown");

  if (countdownEl) {
    var drawDate = new Date(countdownEl.getAttribute("data-draw-date")).getTime();
    var expiredEl =
      document.getElementById("raffle-countdown-expired-inline") ||
      document.getElementById("raffle-countdown-expired");

    function freezeEntryUI() {
      // Disable every entry affordance the instant the raffle closes, so a
      // buyer can't click "Enter Competition" only to be rejected by AJAX.
      var enterBtn = document.getElementById("raffle-enter-comp-submit-btn");
      if (enterBtn) {
        enterBtn.disabled = true;
        enterBtn.classList.add("raffle-btn--closed");
        enterBtn.textContent = "COMPETITION CLOSED";
      }
      // Hide the online entry panel + slider + bundles.
      var entryPanel = document.getElementById("tab-online");
      if (entryPanel) {
        entryPanel.style.display = "none";
      }
      // Broadcast for any other listeners (e.g. number grid, scarcity pollers).
      $(document).trigger("raffle:closed");
    }

    var countdownInterval;
    function updateCountdown() {
      var now = Date.now();
      var diff = drawDate - now;

      if (diff <= 0) {
        countdownEl.style.display = "none";
        if (expiredEl) expiredEl.style.display = "block";
        // Freeze the page once and stop ticking — the interval is pointless on
        // an expired raffle and just burns CPU in backgrounded tabs.
        freezeEntryUI();
        if (countdownInterval) {
          clearInterval(countdownInterval);
          countdownInterval = null;
        }
        return;
      }

      var days = Math.floor(diff / 86400000);
      var hours = Math.floor((diff % 86400000) / 3600000);
      var minutes = Math.floor((diff % 3600000) / 60000);
      var seconds = Math.floor((diff % 60000) / 1000);

      var dEl = document.getElementById("cd-inline-days") || document.getElementById("cd-days");
      var hEl = document.getElementById("cd-inline-hours") || document.getElementById("cd-hours");
      var mEl =
        document.getElementById("cd-inline-minutes") || document.getElementById("cd-minutes");
      var sEl =
        document.getElementById("cd-inline-seconds") || document.getElementById("cd-seconds");

      if (dEl) dEl.textContent = days < 10 ? "0" + days : days;
      if (hEl) hEl.textContent = hours < 10 ? "0" + hours : hours;
      if (mEl) mEl.textContent = minutes < 10 ? "0" + minutes : minutes;
      if (sEl) sEl.textContent = seconds < 10 ? "0" + seconds : seconds;
    }

    updateCountdown();
    countdownInterval = setInterval(updateCountdown, 1000);
  }

  // ─────────────────────────────────────────────────────────────────────
  // Number Picker Grid (Phase 2.2)
  // Renders clickable cells for each ticket number; sold/reserved greyed out.
  // Selection feeds into the existing selectedNumbers[] + hidden form field.
  // ─────────────────────────────────────────────────────────────────────
  var numberGrid = $("#raffle-number-grid");
  if (numberGrid.length > 0) {
    var gridSection = numberGrid.closest(".raffle-number-grid-section");
    var gridRaffleId = parseInt(gridSection.data("raffle-id")) || 0;
    var gridTotal = parseInt(gridSection.data("total")) || 0;
    var gridSold = {};
    var gridReserved = {};

    function renderNumberGrid() {
      if (!gridTotal) return;
      var html = "";
      for (var n = 1; n <= gridTotal; n++) {
        var sold = !!gridSold[n];
        var reserved = !sold && !!gridReserved[n];
        var selected = selectedNumbers.indexOf(n) !== -1;
        var cls = "raffle-ng-cell";
        if (sold) cls += " raffle-ng-cell-sold";
        else if (reserved) cls += " raffle-ng-cell-reserved";
        else if (selected) cls += " raffle-ng-cell-selected";
        html += '<button type="button" class="' + cls + '" data-num="' + n + '"' +
          (sold || reserved ? ' disabled' : '') + '>' + n + "</button>";
      }
      numberGrid.html(html);
    }

    function toggleGridNumber(num) {
      var qty = parseInt(slider.val()) || 1;
      var idx = selectedNumbers.indexOf(num);
      if (idx !== -1) {
        selectedNumbers.splice(idx, 1);
      } else {
        if (selectedNumbers.length >= qty) {
          // Cap at the current quantity selection.
          selectedNumbers.shift();
        }
        selectedNumbers.push(num);
      }
      $('input[name="selected_numbers"]').val(selectedNumbers.join(","));
      renderNumberGrid();
    }

    function luckyDipGrid() {
      var qty = parseInt(slider.val()) || 1;
      var pool = [];
      for (var n = 1; n <= gridTotal; n++) {
        if (!gridSold[n] && !gridReserved[n]) pool.push(n);
      }
      // Fisher-Yates with random_int-equivalent (crypto when available).
      for (var i = pool.length - 1; i > 0; i--) {
        var j = (window.crypto && window.crypto.getRandomValues)
          ? window.crypto.getRandomValues(new Uint32Array(1))[0] % (i + 1)
          : Math.floor(Math.random() * (i + 1));
        var tmp = pool[i]; pool[i] = pool[j]; pool[j] = tmp;
      }
      selectedNumbers = pool.slice(0, qty);
      $('input[name="selected_numbers"]').val(selectedNumbers.join(","));
      renderNumberGrid();
    }

    numberGrid.on("click", ".raffle-ng-cell", function () {
      toggleGridNumber(parseInt($(this).data("num")) || 0);
    });
    gridSection.on("click", ".raffle-number-grid-luckydip", function (e) {
      e.preventDefault();
      luckyDipGrid();
    });

    // Initial fetch + periodic refresh (so sold numbers update live).
    function fetchGridStatus() {
      $.post(rafflePublic.ajax_url, {
        action: "raffle_get_sold_numbers",
        nonce: rafflePublic.nonce,
        raffle_id: gridRaffleId
      }, function (response) {
        if (response && response.success && response.data) {
          gridSold = {};
          (response.data.sold || []).forEach(function (n) { gridSold[n] = true; });
          gridReserved = {};
          (response.data.reserved || []).forEach(function (n) { gridReserved[n] = true; });
          // Drop any selected numbers that became sold/reserved. Track how many
          // were removed so we can warn the buyer — previously this happened
          // silently and they only discovered the loss at the confirm step.
          var before = selectedNumbers.length;
          var lostNumbers = selectedNumbers.filter(function (n) {
            return gridSold[n] || gridReserved[n];
          });
          if (lostNumbers.length > 0) {
            selectedNumbers = selectedNumbers.filter(function (n) {
              return !gridSold[n] && !gridReserved[n];
            });
            // Non-blocking notice via the existing toast helper.
            var msg = lostNumbers.length === 1
              ? "1 of your selected numbers was just taken — please pick another."
              : lostNumbers.length + " of your selected numbers were just taken — please pick others.";
            if (typeof showToast === "function") {
              showToast(msg);
            }
            $('input[name="selected_numbers"]').val(selectedNumbers.join(","));
          }
            renderNumberGrid();
        }
      }).fail(function () {
        // Silent on failure — the next 30s poll will retry. We deliberately do
        // not throw an error toast here because the grid is still usable with
        // its last-known state; only a persistent failure should escalate.
      });
    }
    fetchGridStatus();
    setInterval(fetchGridStatus, 30000);
  }

    // ─────────────────────────────────────────────────────────────────────
    // Share copy-to-clipboard (Phase 2.4)
    // ─────────────────────────────────────────────────────────────────────
    $(".raffle-share-copy").on("click", function () {
      var url = $(this).data("share-url") || window.location.href;
      var confirm = $(".raffle-share-copy-confirm");
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function () {
          confirm.fadeIn(200).delay(1500).fadeOut(400);
        });
      } else {
        // Legacy fallback.
        var $tmp = $('<input>').val(url).appendTo("body").select();
        try { document.execCommand("copy"); confirm.fadeIn(200).delay(1500).fadeOut(400); } catch (e) {}
        $tmp.remove();
      }
    });

  // ─────────────────────────────────────────────────────────────────────
  // Scarcity / Urgency (Phase 2.5) — viewers-now heartbeat + live stock
  // ─────────────────────────────────────────────────────────────────────
  var viewersBadge = $("#raffle-viewers-now");
  if (viewersBadge.length > 0) {
    var viewersRaffleId = parseInt(viewersBadge.data("raffle-id")) || 0;
    function fetchViewers() {
      $.post(rafflePublic.ajax_url, {
        action: "raffle_viewers",
        raffle_id: viewersRaffleId
      }, function (response) {
        if (response && response.success && response.data && response.data.viewers) {
          var n = parseInt(response.data.viewers) || 0;
          if (n >= 2) {
            viewersBadge.find(".raffle-viewers-count").text(
              n + (n === 1 ? " person" : " people")
            );
            viewersBadge.fadeIn(300);
          } else {
            viewersBadge.fadeOut(300);
          }
        }
      });
    }
    fetchViewers();
    setInterval(fetchViewers, 30000); // 30s heartbeat
  }

  var scarcityBox = $(".raffle-scarcity-enabled");
  if (scarcityBox.length > 0) {
    var scarcityRaffleId = parseInt(scarcityBox.data("raffle-id")) || 0;
    var scarcityTotal = parseInt(scarcityBox.data("total")) || 0;
    function pollStock() {
      // Don't poll when tab hidden (saves server load).
      if (document.hidden) return;
      $.post(rafflePublic.ajax_url, {
        action: "raffle_get_sold_numbers",
        nonce: rafflePublic.nonce,
        raffle_id: scarcityRaffleId
      }, function (response) {
        if (!response || !response.success || !response.data) return;
        var sold = (response.data.sold || []).length;
        var pct = scarcityTotal > 0 ? Math.round((sold / scarcityTotal) * 100) : 0;
        var remaining = Math.max(0, scarcityTotal - sold);
        scarcityBox.find(".raffle-progress-pct").text(pct);
        scarcityBox.find(".raffle-progress-sold").text(sold);
        scarcityBox.find(".raffle-progress-bar-inner").css("width", pct + "%");
      });
    }
    setInterval(pollStock, 15000); // 15s stock refresh
  }

  // ─────────────────────────────────────────────────────────────────────
  // Charity grid — live totals (polls every 60s so a new purchase reflects
  // on the grid without a page reload).
  // ─────────────────────────────────────────────────────────────────────
  var charityGrid = $(".raffle-charities-grid[data-live='1']");
  if (charityGrid.length > 0 && typeof raffleCharities !== "undefined") {
    function pollCharityTotals() {
      if (document.hidden) return;
      var ids = [];
      charityGrid.find(".raffle-charity-card").each(function () {
        ids.push($(this).data("charity-id"));
      });
      if (!ids.length) return;
      $.post(raffleCharities.ajax_url, {
        action: "raffle_charity_totals",
        nonce: raffleCharities.nonce,
        charity_ids: ids
      }, function (response) {
        if (!response || !response.success || !response.data || !response.data.totals) return;
        charityGrid.find(".raffle-charity-card").each(function () {
          var id = $(this).data("charity-id");
          var total = parseFloat(response.data.totals[id]);
          if (isNaN(total)) return;
          var amountEl = $(this).find(".raffle-charity-total-amount");
          var prev = parseFloat(amountEl.data("raw")) || 0;
          amountEl.attr("data-raw", total);
          // Only animate when the value actually changed.
          if (total !== prev) {
            amountEl.text(raffleCharities.symbol + total.toFixed(2));
            // jQuery's .animate() cannot animate `transform` without a plugin,
            // which previously left the number stuck at scale(1.12). Use a CSS
            // class + transition instead so the pulse actually resolves.
            amountEl.removeClass("raffle-pulse-bump");
            void amountEl[0].offsetWidth; // force reflow so the class re-triggers
            amountEl.addClass("raffle-pulse-bump");
          }
        });
      });
    }
    setInterval(pollCharityTotals, 60000); // 60s charity totals refresh
  }
})(jQuery);
