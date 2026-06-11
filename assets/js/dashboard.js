(function ($) {
  "use strict";

  var charts = {};
  var chartColors = {
    blue: "rgba(0, 156, 255, ##)",
    green: "rgba(0, 184, 148, ##)",
    orange: "rgba(230, 168, 0, ##)",
    red: "rgba(231, 76, 60, ##)",
    purple: "rgba(108, 92, 231, ##)",
    teal: "rgba(0, 206, 201, ##)",
    pink: "rgba(232, 67, 147, ##)",
    yellow: "rgba(243, 156, 18, ##)",
  };

  var colorKeys = Object.keys(chartColors);

  function rgba(name, alpha) {
    return chartColors[name].replace("##", alpha);
  }

  function colorAt(i, alpha) {
    return rgba(colorKeys[i % colorKeys.length], alpha);
  }

  function formatMoney(n) {
    return (
      raffleDashboard.curSym +
      Number(n).toLocaleString("es-CO", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      })
    );
  }

  function truncate(str, len) {
    return str.length > len ? str.substring(0, len) + "…" : str;
  }

  // Chart.js global defaults for dark cards -> white bg cards
  Chart.defaults.color = "#6c757d";
  Chart.defaults.font.family = "'Heebo', sans-serif";
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.pointStyleWidth = 10;
  Chart.defaults.scale.grid = { color: "rgba(0,0,0,.06)" };

  function ajax(type, extra) {
    var params = $.extend(
      {
        action: "raffle_analytics_data",
        nonce: raffleDashboard.nonce,
        type: type,
      },
      extra || {},
    );
    return $.getJSON(raffleDashboard.ajax_url, params);
  }

  /* ── KPIs ─────────────────────────────── */
  function loadOverview() {
    ajax("overview").done(function (res) {
      if (!res.success) return;
      var d = res.data;
      $("#kpi-revenue").text(formatMoney(d.total_revenue));
      $("#kpi-net-profit").text(formatMoney(d.net_profit));
      $("#kpi-tickets").text(
        d.total_tickets_sold.toLocaleString("es-CO") +
          " / " +
          d.total_tickets_available.toLocaleString("es-CO"),
      );
      $("#kpi-buyers").text(d.total_buyers.toLocaleString("es-CO"));
      $("#kpi-sell-rate").text(d.sell_rate + "%");

      // net profit color
      $("#kpi-net-profit").css(
        "color",
        d.net_profit >= 0 ? "#00b894" : "#e74c3c",
      );

      // secondary
      $("#kpi-active-raffles span").text(d.active_raffles);
      $("#kpi-total-raffles span").text(d.total_raffles);
      $("#kpi-avg-price span").text(
        Number(d.avg_ticket_price).toLocaleString("es-CO", {
          minimumFractionDigits: 0,
        }),
      );
      $("#kpi-month-trend span").text(
        Number(d.revenue_this_month).toLocaleString("es-CO", {
          minimumFractionDigits: 0,
        }),
      );

      // month trend arrow
      var isUp = d.revenue_this_month >= d.revenue_last_month;
      var color = isUp ? "#00b894" : "#e74c3c";
      var arrow = isUp ? "↑" : "↓";
      $("#kpi-month-trend .rs-trend-arrow")
        .text(arrow)
        .css("color", color);
    });
  }

  /* ── Revenue by Raffle ────────────────── */
  function loadRevenueByRaffle() {
    ajax("revenue_by_raffle").done(function (res) {
      if (!res.success) return;
      var data = res.data;
      var labels = data.map(function (r) {
        return truncate(r.title, 18);
      });
      var values = data.map(function (r) {
        return parseFloat(r.revenue);
      });
      var bg = data.map(function (_, i) {
        return colorAt(i, 0.75);
      });
      var border = data.map(function (_, i) {
        return colorAt(i, 1);
      });

      if (charts.revenue) charts.revenue.destroy();
      charts.revenue = new Chart($("#chart-revenue-raffle")[0], {
        type: "bar",
        data: {
          labels: labels,
          datasets: [
            {
              label: "Revenue ($)",
              data: values,
              backgroundColor: bg,
              borderColor: border,
              borderWidth: 2,
              borderRadius: 6,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  return formatMoney(ctx.raw);
                },
              },
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: function (v) {
                  return formatMoney(v);
                },
              },
            },
          },
        },
      });
    });
  }

  /* ── Tickets by Raffle ────────────────── */
  function loadTicketsByRaffle() {
    ajax("tickets_by_raffle").done(function (res) {
      if (!res.success) return;
      var data = res.data;
      var labels = data.map(function (r) {
        return truncate(r.title, 18);
      });
      var sold = data.map(function (r) {
        return parseInt(r.sold_tickets, 10);
      });
      var total = data.map(function (r) {
        return parseInt(r.total_tickets, 10) - parseInt(r.sold_tickets, 10);
      });

      if (charts.tickets) charts.tickets.destroy();
      charts.tickets = new Chart($("#chart-tickets-raffle")[0], {
        type: "bar",
        data: {
          labels: labels,
          datasets: [
            {
              label: "Sold",
              data: sold,
              backgroundColor: rgba("blue", 0.8),
              borderRadius: 6,
            },
            {
              label: "Available",
              data: total,
              backgroundColor: rgba("blue", 0.15),
              borderRadius: 6,
            },
          ],
        },
        options: {
          indexAxis: "y",
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: "bottom" } },
          scales: {
            x: { stacked: true, beginAtZero: true },
            y: { stacked: true },
          },
        },
      });
    });
  }

  /* ── Net Profit ───────────────────────── */
  function loadNetProfit() {
    ajax("net_profit").done(function (res) {
      if (!res.success) return;
      var data = res.data;
      var labels = data.map(function (r) {
        return truncate(r.title, 18);
      });
      var profits = data.map(function (r) {
        return parseFloat(r.net_profit);
      });
      var bg = profits.map(function (v) {
        return v >= 0 ? rgba("green", 0.75) : rgba("red", 0.75);
      });
      var border = profits.map(function (v) {
        return v >= 0 ? rgba("green", 1) : rgba("red", 1);
      });

      if (charts.profit) charts.profit.destroy();
      charts.profit = new Chart($("#chart-net-profit")[0], {
        type: "bar",
        data: {
          labels: labels,
          datasets: [
            {
              label: "Net Profit ($)",
              data: profits,
              backgroundColor: bg,
              borderColor: border,
              borderWidth: 2,
              borderRadius: 6,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  return formatMoney(ctx.raw);
                },
              },
            },
          },
          scales: {
            y: {
              ticks: {
                callback: function (v) {
                  return formatMoney(v);
                },
              },
            },
          },
        },
      });
    });
  }

  /* ── Sales Trend ──────────────────────── */
  function loadSalesTrend(period) {
    period = period || "daily";
    ajax("sales_trend", { period: period }).done(function (res) {
      if (!res.success) return;
      var data = res.data;
      var labels = data.map(function (r) {
        return r.label;
      });
      var revenue = data.map(function (r) {
        return parseFloat(r.revenue);
      });
      var tickets = data.map(function (r) {
        return parseInt(r.tickets, 10);
      });

      if (charts.trend) charts.trend.destroy();
      charts.trend = new Chart($("#chart-sales-trend")[0], {
        type: "line",
        data: {
          labels: labels,
          datasets: [
            {
              label: "Revenue ($)",
              data: revenue,
              borderColor: rgba("blue", 1),
              backgroundColor: rgba("blue", 0.08),
              fill: true,
              tension: 0.35,
              pointBackgroundColor: rgba("blue", 1),
              pointRadius: 4,
              yAxisID: "y",
            },
            {
              label: "Tickets",
              data: tickets,
              borderColor: rgba("green", 1),
              backgroundColor: "transparent",
              borderDash: [5, 5],
              tension: 0.35,
              pointBackgroundColor: rgba("green", 1),
              pointRadius: 4,
              yAxisID: "y1",
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: "index", intersect: false },
          plugins: { legend: { position: "bottom" } },
          scales: {
            y: {
              type: "linear",
              position: "left",
              beginAtZero: true,
              ticks: {
                callback: function (v) {
                  return formatMoney(v);
                },
              },
            },
            y1: {
              type: "linear",
              position: "right",
              beginAtZero: true,
              grid: { drawOnChartArea: false },
            },
          },
        },
      });
    });
  }

  /* ── Top Buyers ───────────────────────── */
  function loadTopBuyers() {
    ajax("top_buyers").done(function (res) {
      if (!res.success) return;
      var data = res.data;
      var $tbody = $("#table-top-buyers tbody");
      if (!data.length) {
        $tbody.html(
          '<tr><td colspan="5" class="rs-table-empty">No data</td></tr>',
        );
        return;
      }
      var rows = "";
      $.each(data, function (i, b) {
        rows +=
          "<tr>" +
          "<td><strong>" +
          (i + 1) +
          "</strong></td>" +
          "<td>" +
          escHtml(b.buyer_name) +
          '<br><small style="color:#6c757d;">' +
          escHtml(b.buyer_email) +
          "</small></td>" +
          "<td>" +
          b.purchases +
          "</td>" +
          "<td>" +
          parseInt(b.total_tickets, 10).toLocaleString("es-CO") +
          "</td>" +
          "<td><strong>" +
          formatMoney(b.total_spent) +
          "</strong></td>" +
          "</tr>";
      });
      $tbody.html(rows);
    });
  }

  /* ── Recent Transactions ──────────────── */
  function loadRecentTransactions() {
    ajax("recent_transactions").done(function (res) {
      if (!res.success) return;
      var data = res.data;
      var $tbody = $("#table-recent-txns tbody");
      if (!data.length) {
        $tbody.html(
          '<tr><td colspan="5" class="rs-table-empty">No data</td></tr>',
        );
        return;
      }
      var rows = "";
      $.each(data, function (_, t) {
        var statusClass =
          t.payment_status === "completed" ? "completed" : "pending";
        var statusLabel =
          t.payment_status === "completed" ? "Completed" : "Pending";
        rows +=
          "<tr>" +
          "<td>" +
          escHtml(truncate(t.raffle_title, 22)) +
          "</td>" +
          "<td>" +
          escHtml(t.buyer_name) +
          "</td>" +
          "<td>" +
          t.quantity +
          "</td>" +
          "<td><strong>" +
          formatMoney(t.total_amount) +
          "</strong></td>" +
          '<td><span class="rs-badge rs-badge-' +
          statusClass +
          '">' +
          statusLabel +
          "</span></td>" +
          "</tr>";
      });
      $tbody.html(rows);
    });
  }

  function escHtml(str) {
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  /* ── Init ─────────────────────────────── */
  function loadAll() {
    loadOverview();
    loadRevenueByRaffle();
    loadTicketsByRaffle();
    loadNetProfit();
    loadSalesTrend("daily");
    loadTopBuyers();
    loadRecentTransactions();
  }

  $(function () {
    loadAll();

    // Refresh button
    $("#rs-refresh-dashboard").on("click", function () {
      var $btn = $(this);
      $btn.addClass("rs-spinning");
      loadAll();
      setTimeout(function () {
        $btn.removeClass("rs-spinning");
      }, 1200);
    });

    // Period selector
    $(document).on("click", ".rs-chip", function () {
      $(".rs-chip").removeClass("rs-chip-active");
      $(this).addClass("rs-chip-active");
      loadSalesTrend($(this).data("period"));
    });
  });
})(jQuery);
