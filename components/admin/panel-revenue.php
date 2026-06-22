<!-- REVENUE -->
  <div class="panel" id="panel-revenue">
    <div class="page-header"><h2 class="page-title">Revenue Analytics</h2><div class="page-sub">Platform commission &amp; payment totals</div></div>

    <!-- Date range filter — changing this and clicking Apply refreshes all numbers below -->
    <div class="scard" style="margin-bottom:16px">
      <div style="padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <span style="font-size:12px;color:var(--text-muted);font-weight:600">DATE RANGE</span>
        <input type="date" id="revDateFrom" class="form-input" style="width:150px;padding:6px 10px;font-size:13px">
        <span style="font-size:12px;color:var(--text-muted)">to</span>
        <input type="date" id="revDateTo" class="form-input" style="width:150px;padding:6px 10px;font-size:13px">
        <button class="btn-primary" style="width:auto;padding:7px 18px;font-size:12px" onclick="loadRevenue()">Apply</button>
        <button class="scard-action" onclick="resetRevenueDates()">Reset to this month</button>
        <span id="revPeriodLabel" style="font-size:11px;color:var(--text-muted)"></span>
      </div>
    </div>

    <!-- P&L Summary — shows at the top so leadership sees the big picture first -->
    <div class="scard" id="plSummaryCard" style="margin-bottom:16px">
      <div class="scard-header">
        <h3>P&amp;L Summary</h3>
        <span id="plPeriodBadge" style="font-size:11px;color:var(--text-muted)"></span>
      </div>
      <div class="metrics-grid three" style="padding:0 16px 8px">
        <div class="metric-card">
          <div class="metric-label">GROSS REVENUE</div>
          <div class="metric-value" id="plGrossRevenue">--</div>
          <div class="metric-delta neutral" id="plRevenueDelta">vs prior period</div>
        </div>
        <div class="metric-card">
          <div class="metric-label">TOTAL PAYOUTS MADE</div>
          <div class="metric-value" id="plTotalPayouts">--</div>
          <div class="metric-delta neutral" id="plPayoutRate">-- payout rate</div>
        </div>
        <div class="metric-card">
          <div class="metric-label">TOTAL REFUNDS</div>
          <div class="metric-value" id="plTotalRefunds">--</div>
          <div class="metric-delta neutral">Refunded to customers</div>
        </div>
        <div class="metric-card">
          <div class="metric-label">BONUSES PAID</div>
          <div class="metric-value" id="plTotalBonuses">--</div>
          <div class="metric-delta neutral">Campaign &amp; manual</div>
        </div>
        <div class="metric-card" style="border:2px solid var(--gold)">
          <div class="metric-label">NET PROFIT</div>
          <div class="metric-value" id="plNetProfit">--</div>
          <div class="metric-delta neutral">Revenue − Payouts − Refunds − Bonuses</div>
        </div>
        <div class="metric-card">
          <div class="metric-label">PLATFORM CASH POSITION</div>
          <div class="metric-value" id="plCashPosition">--</div>
          <div class="metric-delta neutral">Commission − Payouts − Refunds</div>
        </div>
      </div>
      <div style="padding:0 16px 14px;font-size:12px;color:var(--text-muted)">
        Outstanding pending payouts (all time):&nbsp;<strong id="plOutstandingPayouts">--</strong>
      </div>
    </div>

    <!-- Existing monthly metric cards -->
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">PERIOD REVENUE</div><div class="metric-value" id="revMonthly">--</div><div class="metric-delta neutral" id="revMonthlyDelta">Loading…</div></div>
      <div class="metric-card"><div class="metric-label">NET COMMISSION</div><div class="metric-value" id="revCommission">--</div><div class="metric-delta neutral">Platform share of fares</div></div>
      <div class="metric-card"><div class="metric-label">DRIVER PAYOUTS</div><div class="metric-value" id="revPayouts">--</div><div class="metric-delta neutral">Paid out in period</div></div>
      <div class="metric-card"><div class="metric-label">AVG DAILY</div><div class="metric-value" id="revAvgDaily">--</div><div class="metric-delta neutral">Commission per day</div></div>
    </div>
    <div class="scard">
      <div class="scard-header">
        <h3>Revenue by day (last 7 in period)</h3>
        <button class="scard-action" onclick="exportRevenueCsv()">Download CSV</button>
      </div>
      <div class="rev-bars" id="revWeekChart" style="height:120px;display:flex;align-items:flex-end;gap:6px;padding:0 16px 0"></div>
      <div style="display:flex;justify-content:space-between;padding:4px 16px 12px;font-size:11px;color:var(--text-secondary)" id="revWeekLabels"></div>
      <div style="padding:4px 16px 12px;font-size:12px;color:var(--text-secondary)" id="revWeekSummary"></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Revenue by delivery category</h3></div>
      <div id="revCategoryChart" style="padding:16px">
      </div>
    </div>
    <div class="metrics-grid three">
      <div class="metric-card"><div class="metric-label">COMPLETED TRIPS (PERIOD)</div><div class="metric-value" id="revSameDay">--</div><div class="metric-delta neutral">In selected range</div></div>
      <div class="metric-card"><div class="metric-label">GATEWAY SUCCESS</div><div class="metric-value" id="revGateway">--</div><div class="metric-delta neutral">Captured / total payments</div></div>
      <div class="metric-card"><div class="metric-label">PAYOUT RATIO</div><div class="metric-value" id="revPayoutRatio">--</div><div class="metric-delta neutral">Payouts vs commission</div></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Tax &amp; Compliance Exports</h3></div>
      <div style="padding:16px 18px">
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:14px">Download tax summaries for drivers and platform income reports for accounting. Exports use the date range selected above.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 16px" onclick="exportTaxSummary()">Tax Summary (CSV)</button>
          <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 16px;background:var(--navy-light)" onclick="exportDriverWht()">Driver WHT Reports</button>
          <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 16px;background:var(--navy-light)" onclick="exportVatSchedule()">VAT Schedule</button>
        </div>
      </div>
    </div>
  </div>

<script>
(function(){
  // Set default dates to the current calendar month when the panel first loads.
  function initRevenueDates(){
    var fromEl = document.getElementById('revDateFrom');
    var toEl   = document.getElementById('revDateTo');
    if (!fromEl || !toEl) return;
    if (fromEl.value && toEl.value) return; // already set
    var now   = new Date();
    var y     = now.getFullYear();
    var m     = String(now.getMonth() + 1).padStart(2, '0');
    var today = y + '-' + m + '-' + String(now.getDate()).padStart(2, '0');
    fromEl.value = y + '-' + m + '-01';
    toEl.value   = today;
  }

  window.resetRevenueDates = function(){
    var fromEl = document.getElementById('revDateFrom');
    var toEl   = document.getElementById('revDateTo');
    if (!fromEl || !toEl) return;
    var now   = new Date();
    var y     = now.getFullYear();
    var m     = String(now.getMonth() + 1).padStart(2, '0');
    var today = y + '-' + m + '-' + String(now.getDate()).padStart(2, '0');
    fromEl.value = y + '-' + m + '-01';
    toEl.value   = today;
    if (typeof loadRevenue === 'function') loadRevenue();
  };

  // Make sure dates are initialised when panel-revenue is shown.
  document.addEventListener('DOMContentLoaded', function(){
    initRevenueDates();
  });
  // Also init immediately in case DOMContentLoaded already fired.
  initRevenueDates();
})();
</script>
