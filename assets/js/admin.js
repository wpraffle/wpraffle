(function ($) {
  "use strict";

  // --- Media uploader for prize image ---
  $("#upload-prize-image").on("click", function (e) {
    e.preventDefault();
    var frame = wp.media({
      title: "Select prize image",
      multiple: false,
      library: { type: "image" },
    });

    frame.on("select", function () {
      var attachment = frame.state().get("selection").first().toJSON();
      $("#prize_image").val(attachment.url);
      $("#prize-image-preview").html('<img src="' + attachment.url + '">');
      $("#remove-prize-image").show();
    });

    frame.open();
  });

  $("#remove-prize-image").on("click", function (e) {
    e.preventDefault();
    $("#prize_image").val("");
    $("#prize-image-preview").html(
      '<svg class="wpr-icon wpr-icon--lg" aria-hidden="true" role="img" xmlns="http://www.w3.org/2000/svg"><use href="#wpr-image"></use></svg>',
    );
    $(this).hide();
  });

  // --- Draw winner ---
  $("#draw-winner-btn").on("click", function () {
    if (
      !confirm(
        "Are you sure you want to perform the draw? This action cannot be undone.",
      )
    ) {
      return;
    }

    var btn = $(this);
    var raffleId = btn.data("raffle-id");
    btn.prop("disabled", true).text("Drawing...");

    $.post(
      raffleAdmin.ajax_url,
      {
        action: "raffle_draw",
        raffle_id: raffleId,
        nonce: raffleAdmin.draw_nonce,
      },
      function (response) {
        if (response.success) {
          btn.hide();
          $("#draw-result")
            .show()
            .html(
              "<h3>Winner selected!</h3>" +
                "<p><strong>Name:</strong> " +
                $("<span>").text(response.data.buyer_name).html() +
                "</p>" +
                "<p><strong>Ticket:</strong> #" +
                $("<span>").text(response.data.ticket_number).html() +
                "</p>" +
                "<p><strong>Email:</strong> " +
                $("<span>").text(response.data.buyer_email).html() +
                "</p>",
            );
        } else {
          alert(response.data.message);
          btn.prop("disabled", false).text("Select Winner");
        }
      },
    ).fail(function () {
      alert("Connection error. Please try again.");
      btn.prop("disabled", false).text("Select Winner");
    });
  });

  // --- Auto-fix duplicates toggle ---
  $("#raffle-auto-fix-toggle").on("change", function () {
    var enabled = $(this).is(":checked") ? "1" : "0";
    $.post(raffleAdmin.ajax_url, {
      action: "raffle_toggle_auto_fix",
      nonce: raffleAdmin.draw_nonce,
      enabled: enabled,
    });
  });

  // --- Check duplicates ---
  $("#check-duplicates-btn").on("click", function () {
    var btn = $(this);
    var raffleId = btn.data("raffle-id");
    btn.prop("disabled", true).text("Checking...");

    $.post(
      raffleAdmin.ajax_url,
      {
        action: "raffle_check_duplicates",
        raffle_id: raffleId,
        nonce: raffleAdmin.draw_nonce,
      },
      function (response) {
        btn.prop("disabled", false).text("Check Duplicates");
        if (response.success) {
          if (response.data.count === 0) {
            $("#duplicates-result").html(
              '<div class="raffle-duplicates-ok">No duplicate tickets found.</div>',
            );
            $("#fix-duplicates-btn").hide();
          } else {
            var details = response.data.details
              .map(function (d) {
                return (
                  "Ticket #" + d.ticket_number + " (" + d.copies + " copies)"
                );
              })
              .join(", ");
            $("#duplicates-result").html(
              '<div class="raffle-duplicates-warn">Found <strong>' +
                response.data.count +
                "</strong> duplicate tickets: " +
                details +
                "</div>",
            );
            $("#fix-duplicates-btn").show();
          }
        } else {
          alert(response.data.message);
        }
      },
    ).fail(function () {
      btn.prop("disabled", false).text("Check Duplicates");
      alert("Connection error.");
    });
  });

  // --- Fix duplicates ---
  $("#fix-duplicates-btn").on("click", function () {
    var btn = $(this);
    var raffleId = btn.data("raffle-id");
    btn.prop("disabled", true).text("Fixing...");

    $.post(
      raffleAdmin.ajax_url,
      {
        action: "raffle_fix_duplicates",
        raffle_id: raffleId,
        nonce: raffleAdmin.draw_nonce,
      },
      function (response) {
        btn.prop("disabled", false).text("Fix Duplicates");
        if (response.success) {
          btn.hide();
            $("#duplicates-result").html(
              '<div class="raffle-duplicates-fixed">' +
                $("<span>").text(response.data.message).html() +
                "</div>",
            );
        } else {
          alert(response.data.message);
        }
      },
    ).fail(function () {
      btn.prop("disabled", false).text("Fix Duplicates");
      alert("Connection error.");
    });
  });

  // --- Instant Wins ---
  $("#add-instant-win-btn").on("click", function () {
    var btn = $(this);
    var raffleId = btn.data("raffle-id");
    var prizeName = $("#iw-prize-name").val();
    var ticketNum = $("#iw-ticket-number").val();
    var quantity = $("#iw-quantity").val() || 1;

    if (!prizeName) {
      alert("Please enter a prize name.");
      return;
    }

    btn.prop("disabled", true).text("Adding...");

    $.post(
      raffleAdmin.ajax_url,
      {
        action: "raffle_add_instant_win",
        raffle_id: raffleId,
        prize_name: prizeName,
        ticket_number: ticketNum,
        quantity: quantity,
        nonce: raffleAdmin.draw_nonce,
      },
      function (response) {
        if (response.success) {
          location.reload();
        } else {
          alert(response.data.message);
          btn.prop("disabled", false).text('+ Add Instant Win');
        }
      }
    ).fail(function () {
      alert("Connection error.");
      btn.prop("disabled", false).text('+ Add Instant Win');
    });
  });

  $(".delete-instant-win-btn").on("click", function () {
    if (!confirm("Are you sure you want to delete this instant win?")) return;
    var btn = $(this);
    var id = btn.data("id");

    btn.prop("disabled", true).css("opacity", "0.5");

    $.post(
      raffleAdmin.ajax_url,
      {
        action: "raffle_delete_instant_win",
        id: id,
        nonce: raffleAdmin.draw_nonce,
      },
      function (response) {
        if (response.success) {
          location.reload();
        } else {
          alert(response.data.message);
          btn.prop("disabled", false).css("opacity", "1");
        }
      }
    ).fail(function () {
      alert("Connection error.");
      btn.prop("disabled", false).css("opacity", "1");
    });
  });

  // --- Skill question fields toggle ---
  $("#enable_question").on("change", function () {
    if ($(this).val() === "1") {
      $(".question-only-field").show();
    } else {
      $(".question-only-field").hide();
    }
  });

  // --- Multi-winner toggle ---
  $("#multi_winner").on("change", function () {
    if ($(this).val() === "1") {
      $("#num-winners-row").show();
      $("#prizes-config").show();
    } else {
      $("#num-winners-row").hide();
      $("#prizes-config").hide();
    }
  });

  // --- Add prize row ---
  var prizeCounter = $("#prizes-list .prize-row").length;
  $("#add-prize-btn").on("click", function () {
    prizeCounter++;
    var html = '<div class="prize-row" style="display:flex;gap:10px;align-items:center;margin-bottom:8px;">' +
      '<span class="prize-position" style="font-weight:700;width:30px;">' + prizeCounter + '.</span>' +
      '<input type="text" name="prize_name[]" class="regular-text" placeholder="Prize name (e.g. $500 Gift Card)">' +
      '<input type="number" name="prize_value[]" class="small-text" step="0.01" min="0" placeholder="Value">' +
      '<button type="button" class="button remove-prize-btn" title="Remove">×</button>' +
      '</div>';
    $("#prizes-list").append(html);
  });

  // --- Remove prize row ---
  $(document).on("click", ".remove-prize-btn", function () {
    $(this).closest(".prize-row").remove();
    // Re-number positions
    $("#prizes-list .prize-row").each(function (i) {
      $(this).find(".prize-position").text((i + 1) + ".");
    });
    prizeCounter = $("#prizes-list .prize-row").length;
  });

  // --- Free entry toggle ---
  $("#allow_free_entry").on("change", function () {
    if ($(this).val() === "1") {
      $(".free-entry-field").show();
    } else {
      $(".free-entry-field").hide();
    }
  });

  // --- Geo-restriction toggle ---
  $("#geo_restricted").on("change", function () {
    if ($(this).val() === "1") {
      $("#geo-countries-row").show();
    } else {
      $("#geo-countries-row").hide();
    }
  });

  // --- Referrals toggle ---
  $("#allow_referrals").on("change", function () {
    if ($(this).val() === "1") {
      $("#referral-bonus-row").show();
    } else {
      $("#referral-bonus-row").hide();
    }
  });

  // --- Save as template ---
  $("#save-template-btn").on("click", function () {
    var btn = $(this);
    var raffleId = btn.data("raffle-id");
    var templateName = $("#template-name").val();
    if (!templateName) {
      alert("Please enter a template name.");
      return;
    }
    btn.prop("disabled", true).text("Saving...");
    $.post(raffleAdmin.ajax_url, {
      action: "raffle_save_template",
      raffle_id: raffleId,
      template_name: templateName,
      nonce: raffleAdmin.template_nonce,
    }, function (response) {
      if (response.success) {
        btn.text("✓ Saved!").addClass("button-primary");
        setTimeout(function () {
          btn.prop("disabled", false).text("Save as Template").removeClass("button-primary");
        }, 2000);
      } else {
        alert(response.data || "Error saving template.");
        btn.prop("disabled", false).text("Save as Template");
      }
    }).fail(function () {
      alert("Connection error.");
      btn.prop("disabled", false).text("Save as Template");
    });
  });

  // --- Clone raffle ---
  $("#clone-raffle-btn").on("click", function () {
    if (!confirm("Clone this raffle? A new draft raffle will be created.")) return;
    var btn = $(this);
    var raffleId = btn.data("raffle-id");
    btn.prop("disabled", true).text("Cloning...");
    $.post(raffleAdmin.ajax_url, {
      action: "raffle_clone_raffle",
      raffle_id: raffleId,
      nonce: raffleAdmin.clone_nonce,
    }, function (response) {
      if (response.success) {
        // The edit action is served by the raffle-list page slug, not the
        // top-level raffle-system slug (which renders the dashboard and
        // ignores `action=edit`). Redirect to the correct page so the new
        // clone opens for editing instead of landing on the dashboard.
        window.location.href = "?page=raffle-list&action=edit&id=" + response.data.new_id;
      } else {
        alert(response.data || "Error cloning raffle.");
        btn.prop("disabled", false).text("Clone This Raffle");
      }
    }).fail(function () {
      alert("Connection error.");
      btn.prop("disabled", false).text("Clone This Raffle");
    });
  });

  // ─────────────────────────────────────────────────────────────────────
  // Ticket Bundle Builder — syncs the friendly repeatable-row UI into the
  // hidden #packages field (the real source of truth submitted to the server).
  // Produces bare-int JSON [5,10,15] when no bundle prices are set, or full
  // bundle objects [{"qty":5,"price":25,...}] when any price/label/badge is set.
  // The server-side save handler already sanitises both shapes, so no PHP
  // change is required.
  // ─────────────────────────────────────────────────────────────────────
  function bundleRowHasPrice($row) {
    var price = parseFloat($row.find(".rs-b-price").val());
    var label = $.trim($row.find(".rs-b-label").val());
    var badge = $.trim($row.find(".rs-b-badge").val());
    return (!isNaN(price) && price > 0) || label !== "" || badge !== "";
  }

  function syncPackagesFromBuilder() {
    var $rows = $("#rs-bundle-rows .rs-bundle-row");
    if ($rows.length === 0) {
      $("#packages").val("");
      return;
    }
    var anyBundles = false;
    var asBundles = [];
    var asInts = [];
    $rows.each(function () {
      var qty = parseInt($(this).find(".rs-b-qty").val(), 10);
      if (isNaN(qty) || qty < 1) return; // skip invalid rows
      var priceRaw = $(this).find(".rs-b-price").val();
      var price = priceRaw !== "" ? parseFloat(priceRaw) : 0;
      var label = $.trim($(this).find(".rs-b-label").val());
      var badge = $.trim($(this).find(".rs-b-badge").val());
      if ((!isNaN(price) && price > 0) || label !== "" || badge !== "") {
        anyBundles = true;
      }
      var b = { qty: qty, price: (!isNaN(price) ? price : 0), label: label, badge: badge };
      asBundles.push(b);
      asInts.push(qty);
    });

    // If any row uses a bundle feature, emit full bundle objects; otherwise
    // emit the lean bare-int shape for back-compat with simple raffles.
    var out = anyBundles ? asBundles : asInts;
    $("#packages").val(JSON.stringify(out));
    // Live validation feedback — toggle a red outline on any qty that's invalid.
    $rows.each(function () {
      var q = parseInt($(this).find(".rs-b-qty").val(), 10);
      $(this).find(".rs-b-qty").css("border-color", (isNaN(q) || q < 1) ? "#d63638" : "#c3c4c7");
    });
  }

  if ($("#rs-bundle-builder").length > 0) {
    $("#rs-add-bundle").on("click", function () {
      var symbol = $("#rs-bundle-rows").data("symbol") || "$";
      var row = $(
        '<div class="rs-bundle-row" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:8px;">' +
        '<input type="number" class="rs-b-qty" min="1" step="1" placeholder="Qty" value="" style="width:70px;" aria-label="Quantity">' +
        '<span style="color:#50575e;font-size:12px;">tickets for</span>' +
        '<span class="rs-b-price-wrap" style="display:flex;align-items:center;gap:2px;"><span style="color:#50575e;">' + symbol + '</span>' +
        '<input type="number" class="rs-b-price" min="0" step="0.01" placeholder="Standard" value="" style="width:90px;" aria-label="Bundle price"></span>' +
        '<input type="text" class="rs-b-label" placeholder="Label (e.g. 5 for £25)" value="" style="width:180px;" aria-label="Label">' +
        '<input type="text" class="rs-b-badge" placeholder="Badge (e.g. Popular)" value="" style="width:130px;" aria-label="Badge">' +
        '<button type="button" class="button rs-b-remove" aria-label="Remove bundle">&times;</button>' +
        '</div>'
      );
      $("#rs-bundle-rows").append(row);
      syncPackagesFromBuilder();
    });

    // Remove row (delegated so it works for dynamically added rows).
    $("#rs-bundle-rows").on("click", ".rs-b-remove", function () {
      $(this).closest(".rs-bundle-row").remove();
      syncPackagesFromBuilder();
    });

    // Sync on any input change (delegated).
    $("#rs-bundle-rows").on("input change", ".rs-b-qty, .rs-b-price, .rs-b-label, .rs-b-badge", function () {
      syncPackagesFromBuilder();
    });

    // Final sync before form submit so the latest state is captured even if
    // the user tabbed away without triggering a change event.
    $("#rs-bundle-builder").closest("form").on("submit", function () {
      syncPackagesFromBuilder();
    });

    // Initial sync to normalise whatever was loaded from the DB.
    syncPackagesFromBuilder();
  }
})(jQuery);
