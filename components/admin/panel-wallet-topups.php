<!-- WALLET TOP-UPS -->
  <div class="panel" id="panel-wallet-topups">
    <div class="page-header"><h2 class="page-title">Wallet Top-Up Requests</h2><div class="page-sub">Customer bank transfer funding requests — approve to credit wallets instantly</div></div>
    <div class="metrics-grid four">
      <div class="metric-card">
        <div class="metric-label">PENDING</div>
        <div class="metric-value" id="topupPendingAmount">--</div>
        <div class="metric-delta down" id="topupPendingCount">Loading…</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">APPROVED TODAY</div>
        <div class="metric-value" id="topupApprovedTodayAmount">--</div>
        <div class="metric-delta up" id="topupApprovedTodayCount">0 requests</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">THIS WEEK</div>
        <div class="metric-value" id="topupWeekAmount">--</div>
        <div class="metric-delta neutral">Approved top-ups</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">REJECTED</div>
        <div class="metric-value" style="color:var(--danger)" id="topupRejectedCount">--</div>
        <div class="metric-delta down">All time</div>
      </div>
    </div>
    <div class="scard">
      <div class="scard-header">
        <h3>Funding Requests</h3>
        <button class="scard-action" onclick="loadWalletTopups(1)">Refresh</button>
      </div>
      <div style="padding:8px 16px 4px;font-size:12px;color:var(--text-secondary)">Customers who have transferred money and uploaded a receipt for review. Approve to add funds to their wallet, or reject with a reason.</div>
      <div class="panel-search" style="padding:10px 16px;margin-bottom:0">
        <input id="topupSearch" placeholder="Search by customer name, email, or bank reference…" oninput="queueTopupSearch(this.value)">
        <select class="filter-select" id="topupStatus" onchange="loadWalletTopups(1)">
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="all">All</option>
        </select>
      </div>
      <div id="topupList"></div>
      <div class="pagination" id="topupPagination"></div>
    </div>
  </div>
