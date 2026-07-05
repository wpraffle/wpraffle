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

    var curSym = rafflePublic.currency_symbol || "$";
    var pricePer = parseFloat($(".raffle-price-value").text().replace(curSym, "")) || 0;
    var totalPrice = curSym + (qty * pricePer).toFixed(2);
    $(".raffle-modal-summary").text(qty + " tickets — " + totalPrice);

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

    $("#raffle-quantity").val(qty);
    $(".raffle-modal-summary").text(qty + " tickets — " + price);

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
    );
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
    modal.show();
  }

  $(document).on("click", ".raffle-modal-close", function () {
    modal.hide();
    confirmation.hide();
  });

  modal.on("click", function (e) {
    if (e.target === this) modal.hide();
  });

  confirmation.on("click", function (e) {
    if (e.target === this) confirmation.hide();
  });

  $(document).on("keydown", function (e) {
    if (e.key === "Escape") {
      modal.hide();
      confirmation.hide();
    }
  });

  function showModalError(msg) {
    var form = $("#raffle-purchase-form");
    form.show();
    form.find(".raffle-submit-btn").prop("disabled", false);
    modal.find(".raffle-loading").hide();
    modal.find(".raffle-error-msg").remove();
    var errorDiv = $('<div class="raffle-error-msg" style="background:#fee;color:#c00;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:14px;"></div>');
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

    function updateCountdown() {
      var now = Date.now();
      var diff = drawDate - now;

      if (diff <= 0) {
        countdownEl.style.display = "none";
        if (expiredEl) expiredEl.style.display = "block";
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
    setInterval(updateCountdown, 1000);
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
          // Drop any selected numbers that became sold/reserved.
          selectedNumbers = selectedNumbers.filter(function (n) {
            return !gridSold[n] && !gridReserved[n];
          });
          renderNumberGrid();
        }
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
            amountEl.stop(true, true).css({ transform: "scale(1.12)" }).animate({ transform: "scale(1)" }, 400);
          }
        });
      });
    }
    setInterval(pollCharityTotals, 60000); // 60s charity totals refresh
  }
})(jQuery);
