<!-- REVENUE -->
  <div class="panel" id="panel-revenue">
    <div class="page-header"><h2 class="page-title">Revenue Analytics</h2><div class="page-sub">Platform commission &amp; payment totals</div></div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">MONTHLY REVENUE</div><div class="metric-value" id="revMonthly">--</div><div class="metric-delta neutral" id="revMonthlyDelta">Loading…</div></div>
      <div class="metric-card"><div class="metric-label">NET COMMISSION</div><div class="metric-value" id="revCommission">--</div><div class="metric-delta neutral">Platform share of fares</div></div>
      <div class="metric-card"><div class="metric-label">DRIVER PAYOUTS</div><div class="metric-value" id="revPayouts">--</div><div class="metric-delta neutral">Paid out this month</div></div>
      <div class="metric-card"><div class="metric-label">AVG DAILY</div><div class="metric-value" id="revAvgDaily">--</div><div class="metric-delta neutral">Commission per day</div></div>
    </div>
    <div class="scard">
      <div class="scard-header">
        <h3>Revenue by day (this week)</h3>
        <button class="scard-action" onclick="exportRevenueCsv()">Download CSV</button>
      </div>
      <div class="rev-bars" id="revWeekChart" style="height:120px;display:flex;align-items:flex-end;gap:6px;padding:0 16px 0"></div>
      <div style="display:flex;justify-content:space-between;padding:4px 16px 12px;font-size:11px;color:var(--text-secondary)" id="revWeekLabels"></div>
      <div style="padding:4px 16px 12px;font-size:12px;color:var(--text-secondary)" id="revWeekSummary"></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Revenue by delivery category</h3></div>
      <div id="revCategoryChart" style="padding:16px">
        <div class="loading-state">Loading category breakdown…</div>
      </div>
    </div>
    <div class="metrics-grid three">
      <div class="metric-card"><div class="metric-label">COMPLETED TRIPS (MTD)</div><div class="metric-value" id="revSameDay">--</div><div class="metric-delta neutral">Month to date</div></div>
      <div class="metric-card"><div class="metric-label">GATEWAY SUCCESS</div><div class="metric-value" id="revGateway">--</div><div class="metric-delta neutral">Captured / total payments</div></div>
      <div class="metric-card"><div class="metric-label">PAYOUT RATIO</div><div class="metric-value" id="revPayoutRatio">--</div><div class="metric-delta neutral">Payouts vs commission</div></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Tax &amp; Compliance Exports</h3></div>
      <div style="padding:16px 18px">
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:14px">Download tax summaries for drivers and platform income reports for accounting.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 16px" onclick="exportTaxSummary()">Tax Summary (CSV)</button>
          <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 16px;background:var(--navy-light)" onclick="exportDriverWht()">Driver WHT Reports</button>
          <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 16px;background:var(--navy-light)" onclick="exportVatSchedule()">VAT Schedule</button>
        </div>
      </div>
    </div>
  </div>
