<!-- PAYOUTS -->
  <div class="panel" id="panel-payouts">
    <div class="page-header"><h2 class="page-title">Driver Payouts</h2><div class="page-sub">Manage and release driver earnings</div></div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">TOTAL PENDING</div><div class="metric-value" id="payoutPendingAmount">--</div><div class="metric-delta down" id="payoutPendingCount">Loading…</div></div>
      <div class="metric-card"><div class="metric-label">PROCESSED TODAY</div><div class="metric-value" id="payoutProcessedAmount">--</div><div class="metric-delta up" id="payoutProcessedCount">Loading…</div></div>
      <div class="metric-card"><div class="metric-label">FAILED PAYOUTS</div><div class="metric-value" style="color:var(--danger)" id="payoutFailedCount">--</div><div class="metric-delta down">Review needed</div></div>
      <div class="metric-card"><div class="metric-label">AVG PAYOUT</div><div class="metric-value" id="payoutAvgAmount">--</div><div class="metric-delta neutral">Per payout/wk</div></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Driver Payouts</h3><button class="btn-primary disabled-action" id="releaseAllPayoutsBtn" style="font-size:11px;padding:6px 12px;width:auto;" aria-disabled="true" onclick="showUnavailableFeature('Bulk payout release', 'Payout release is disabled until a real transfer provider or manual transfer reference flow is connected.')">Release visible</button></div>
      <div class="panel-search" style="padding:14px 16px;margin-bottom:0"><input id="payoutSearch" placeholder="Search driver, bank, reference…" oninput="queuePayoutSearch(this.value)"><select class="filter-select" id="payoutStatus" onchange="setPayoutStatus(this.value)"><option value="pending">Pending</option><option value="processing">Processing</option><option value="failed">Failed</option><option value="paid">Paid</option><option value="all">All</option></select></div><div id="payoutList"></div><div class="pagination" id="payoutPagination"></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Manual Transfers</h3><button class="scard-action" onclick="loadManualPaymentsPayouts()">Refresh</button></div>
      <div style="padding:8px 16px 4px;font-size:12px;color:var(--text-secondary)">Bank-transfer proofs awaiting admin review. Approve to capture payment and confirm the booking.</div>
      <div class="panel-search" style="padding:10px 16px;margin-bottom:0">
        <select class="filter-select" id="manualPaymentsStatus" onchange="loadManualPaymentsPayouts()" style="max-width:200px">
          <option value="proof_submitted">Pending review</option>
          <option value="captured">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="all">All</option>
        </select>
      </div>
      <div id="manualPaymentsListPayouts"></div>
      <div class="pagination" id="manualPaymentsPagination"></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Tax portal</h3></div>
      <div style="padding:16px 18px">
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:14px">Generate tax summaries for drivers and platform income reports for accounting.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 16px" onclick="exportTaxSummary()">Tax Summary (CSV)</button>
          <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 16px;background:var(--navy-light)" onclick="exportDriverWht()">Driver WHT Reports</button>
          <button class="btn-primary" style="flex:1;min-width:140px;font-size:12px;padding:8px 16px;background:var(--navy-light)" onclick="exportVatSchedule()">VAT Schedule</button>
        </div>
      </div>
    </div>
  </div>
