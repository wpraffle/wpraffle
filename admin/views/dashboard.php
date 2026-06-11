<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap">
    <h1 class="wp-heading-inline">Analytics Dashboard</h1>
    <button id="rs-refresh-dashboard" class="page-title-action">Refresh</button>
    <hr class="wp-header-end">

    <!-- KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top:20px;" id="rs-kpi-cards">
        <div class="card" style="margin: 0; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
            <h2 style="margin: 0 0 5px; font-size: 13px; color: #64748b; font-weight:600; text-transform:uppercase;">Total Revenue</h2>
            <p style="margin: 0; font-size: 20px; font-weight: 700; color: #1d2327;" id="kpi-revenue">—</p>
        </div>
        <div class="card" style="margin: 0; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
            <h2 style="margin: 0 0 5px; font-size: 13px; color: #64748b; font-weight:600; text-transform:uppercase;">Net Profit</h2>
            <p style="margin: 0; font-size: 20px; font-weight: 700; color: #10b981;" id="kpi-net-profit">—</p>
        </div>
        <div class="card" style="margin: 0; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
            <h2 style="margin: 0 0 5px; font-size: 13px; color: #64748b; font-weight:600; text-transform:uppercase;">Tickets Sold</h2>
            <p style="margin: 0; font-size: 20px; font-weight: 700; color: #1d2327;" id="kpi-tickets">—</p>
        </div>
        <div class="card" style="margin: 0; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
            <h2 style="margin: 0 0 5px; font-size: 13px; color: #64748b; font-weight:600; text-transform:uppercase;">Unique Buyers</h2>
            <p style="margin: 0; font-size: 20px; font-weight: 700; color: #1d2327;" id="kpi-buyers">—</p>
        </div>
        <div class="card" style="margin: 0; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
            <h2 style="margin: 0 0 5px; font-size: 13px; color: #64748b; font-weight:600; text-transform:uppercase;">Sell Rate</h2>
            <p style="margin: 0; font-size: 20px; font-weight: 700; color: #1d2327;" id="kpi-sell-rate">—</p>
        </div>
    </div>

    <!-- Secondary KPIs -->
    <div style="display:flex; flex-wrap:wrap; gap:10px; margin: 15px 0;">
        <span id="kpi-active-raffles" style="background:#f0f6fc; color:#0969da; border:1px solid #d0e7ff; padding: 4px 10px; border-radius:4px; font-weight:500; font-size:12px;">Active Raffles: <span>—</span></span>
        <span id="kpi-total-raffles" style="background:#f6f8fa; color:#24292f; border:1px solid #d0d7de; padding: 4px 10px; border-radius:4px; font-weight:500; font-size:12px;">Total Raffles: <span>—</span></span>
        <span id="kpi-avg-price" style="background:#f6f8fa; color:#24292f; border:1px solid #d0d7de; padding: 4px 10px; border-radius:4px; font-weight:500; font-size:12px;">Average Price: $<span>—</span></span>
        <span id="kpi-month-trend" style="background:#dafbe1; color:#1a7f37; border:1px solid #acf2bd; padding: 4px 10px; border-radius:4px; font-weight:500; font-size:12px;">This Month: $<span>—</span></span>
    </div>

    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content">
                
                <!-- Row 1: Charts -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap:20px;">
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'chart', 'wpr-icon--sm' ); ?> Revenue by Raffle</h2>
                        </div>
                        <div class="inside" style="padding:10px;">
                            <div class="rs-chart-container">
                                <canvas id="chart-revenue-raffle"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'ticket', 'wpr-icon--sm' ); ?> Tickets Sold by Raffle</h2>
                        </div>
                        <div class="inside" style="padding:10px;">
                            <div class="rs-chart-container">
                                <canvas id="chart-tickets-raffle"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Charts -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap:20px; margin-top:20px;">
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'chart', 'wpr-icon--sm' ); ?> Net Profit by Raffle</h2>
                        </div>
                        <div class="inside" style="padding:10px;">
                            <div class="rs-chart-container">
                                <canvas id="chart-net-profit"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle" style="display:flex; justify-content:space-between; align-items:center;">
                                <span><?php wpr_icon( 'chart', 'wpr-icon--sm' ); ?> Sales Trend</span>
                                <div style="display:flex; gap:5px;" class="rs-chart-toolbar">
                                    <button type="button" class="button button-small button-primary rs-chip" data-period="daily">Daily</button>
                                    <button type="button" class="button button-small rs-chip" data-period="monthly">Monthly</button>
                                    <button type="button" class="button button-small rs-chip" data-period="annual">Annual</button>
                                </div>
                            </h2>
                        </div>
                        <div class="inside" style="padding:10px;">
                            <div class="rs-chart-container">
                                <canvas id="chart-sales-trend"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 3: Tables -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap:20px; margin-top:20px;">
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'trophy', 'wpr-icon--sm' ); ?> Top 10 Buyers</h2>
                        </div>
                        <div class="inside" style="padding:0; margin:0;">
                            <table class="wp-list-table widefat striped posts" style="border:none;" id="table-top-buyers">
                                <thead>
                                    <tr>
                                        <th style="padding-left:15px; width:50px;">#</th>
                                        <th>Name</th>
                                        <th>Purchases</th>
                                        <th>Tickets</th>
                                        <th style="padding-right:15px;">Spent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="5" style="text-align:center; padding:15px; color:#64748b;"><span class="spinner is-active" style="float:none;"></span> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php wpr_icon( 'ticket', 'wpr-icon--sm' ); ?> Latest Transactions</h2>
                        </div>
                        <div class="inside" style="padding:0; margin:0;">
                            <table class="wp-list-table widefat striped posts" style="border:none;" id="table-recent-txns">
                                <thead>
                                    <tr>
                                        <th style="padding-left:15px;">Raffle</th>
                                        <th>Buyer</th>
                                        <th>Tickets</th>
                                        <th>Total</th>
                                        <th style="padding-right:15px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="5" style="text-align:center; padding:15px; color:#64748b;"><span class="spinner is-active" style="float:none;"></span> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
