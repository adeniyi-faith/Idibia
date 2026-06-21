<!-- DRIVERS -->
  <div class="panel" id="panel-drivers">
    <div class="page-header"><h2 class="page-title">Drivers</h2><div class="page-sub">All approved and active delivery riders</div></div>
    <div class="tabs">
      <button class="tab active" id="driverTabDrivers" onclick="driverPanelTab('drivers',this)">All Drivers</button>
      <button class="tab" id="driverTabBlacklist" onclick="driverPanelTab('blacklist',this)">Blacklist</button>
    </div>

    <!-- ALL DRIVERS SECTION -->
    <div id="driverMainSection">
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
        <div id="driverDirectory"></div><div class="pagination" id="driverPagination"></div>
      </div>
    </div>

    <!-- BLACKLIST SECTION -->
    <div id="blacklistSection" style="display:none">
      <div class="scard" style="margin-top:16px">
        <div class="scard-header"><h3>Add to Blacklist</h3></div>
        <div style="padding:16px">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Identifier Type</label>
              <select class="form-input" id="blacklistType">
                <option value="phone">Phone Number</option>
                <option value="email">Email Address</option>
                <option value="device_id">Device ID</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Identifier Value</label>
              <input class="form-input" id="blacklistValue" placeholder="Phone number, email, or device ID">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Reason for Blacklisting</label>
            <textarea class="form-input" id="blacklistReason" rows="2" placeholder="Explain why this identifier is being permanently banned"></textarea>
          </div>
          <button class="btn-primary" style="font-size:12px;padding:8px 14px;width:auto" onclick="addToBlacklist()">Add to Blacklist</button>
        </div>
      </div>
      <div class="scard" style="margin-top:16px">
        <div class="scard-header"><h3>Blacklisted Identifiers</h3><button class="scard-action" onclick="loadBlacklist()">Refresh</button></div>
        <div id="blacklistDirectory"></div>
      </div>
    </div>
  </div>
