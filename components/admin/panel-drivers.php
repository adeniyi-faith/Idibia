<!-- DRIVERS -->
  <div class="panel" id="panel-drivers">
    <div class="page-header"><h2 class="page-title">Drivers</h2><div class="page-sub">All approved and active delivery riders</div></div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">TOTAL DRIVERS</div><div class="metric-value" id="driversTotalCount">--</div><div class="metric-delta neutral">Current filter</div></div>
      <div class="metric-card"><div class="metric-label">ONLINE NOW</div><div class="metric-value" id="driversOnlineCount">--</div><div class="metric-delta neutral">Loaded from driver records</div></div>
      <div class="metric-card"><div class="metric-label">SUSPENDED</div><div class="metric-value" style="color:var(--danger)" id="driversSuspendedCount">--</div><div class="metric-delta down">Review needed</div></div>
      <div class="metric-card"><div class="metric-label">AVG RATING</div><div class="metric-value" id="driversAvgRating">--</div><div class="metric-delta neutral">Visible page</div></div>
    </div>
    <div class="panel-search">
      <input id="driverSearch" placeholder="Search name, state…" oninput="queueDriverSearch(this.value)">
      <select class="filter-select" id="driverStatusFilter" onchange="setDriverStatusFilter(this.value)"><option value="all">All statuses</option><option value="active">Active</option><option value="suspended">Suspended</option></select>
      <button onclick="loadDrivers(1)">Search</button>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Driver Directory</h3></div>
      <div id="driverDirectory"><div class="loading-state">Loading drivers…</div></div><div class="pagination" id="driverPagination"></div>
    </div>
  </div>
