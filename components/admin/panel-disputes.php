<!-- DISPUTES -->
  <div class="panel" id="panel-disputes">
    <div class="page-header"><h2 class="page-title">Disputes</h2><div class="page-sub">Customer complaints and escalations</div></div>
    <div class="metrics-grid three">
      <div class="metric-card"><div class="metric-label">OPEN DISPUTES</div><div class="metric-value" style="color:var(--warn)" id="disputeOpenCount">--</div><div class="metric-delta down" id="disputeEscalatedCount">-- escalated</div></div>
      <div class="metric-card"><div class="metric-label">TOTAL MATCHES</div><div class="metric-value" id="disputeTotalCount">--</div><div class="metric-delta neutral">Current filter</div></div>
      <div class="metric-card"><div class="metric-label">REFUNDS ISSUED</div><div class="metric-value" id="disputeRefundAmount">--</div><div class="metric-delta neutral">Visible page</div></div>
    </div>
    <div class="filter-row">
      <button class="filter-btn active" data-status="all" onclick="filterDisputes('all',this)">All</button>
      <button class="filter-btn" data-status="open" onclick="filterDisputes('open',this)">Open</button>
      <button class="filter-btn" data-status="escalated" onclick="filterDisputes('escalated',this)">Escalated</button>
      <button class="filter-btn" data-status="resolved" onclick="filterDisputes('resolved',this)">Resolved</button>
    </div>
    <div class="panel-search">
      <input id="disputeSearch" placeholder="Search dispute, trip, customer, driver…" oninput="queueDisputeSearch(this.value)">
      <select class="filter-select" id="disputeStatus" onchange="setDisputeStatus(this.value)"><option value="all">All statuses</option><option value="open">Open</option><option value="escalated">Escalated</option><option value="resolved">Resolved</option></select>
    </div>
    <div class="scard"><div id="disputeList"><div class="loading-state">Loading disputes…</div></div><div class="pagination" id="disputePagination"></div></div>
  </div>
