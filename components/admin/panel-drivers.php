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
      <!-- Bulk action bar — hidden until at least one driver is selected -->
      <div id="driverBulkBar" style="display:none;align-items:center;gap:10px;padding:10px 14px;background:var(--surface-1);border-radius:10px;margin-bottom:12px;border:1px solid var(--surface-2)">
        <span id="driverSelectedCount" style="font-size:13px;font-weight:700;color:var(--info)">0 selected</span>
        <div style="flex:1"></div>
        <select class="filter-select" id="driverBulkActionSelect" style="font-size:12px;padding:6px 10px;height:auto">
          <option value="">Bulk Actions…</option>
          <option value="suspend">Suspend</option>
          <option value="reinstate">Reinstate</option>
          <option value="send_notification">Send Notification</option>
          <option value="export">Export Selected</option>
        </select>
        <button class="btn-primary" style="font-size:12px;padding:7px 14px;white-space:nowrap" onclick="executeBulkDriverAction(document.getElementById('driverBulkActionSelect').value)">Apply</button>
        <button style="font-size:12px;padding:7px 10px;background:none;border:1.5px solid var(--surface-2);border-radius:9px;cursor:pointer;color:var(--text-secondary)" onclick="clearDriverSelection()">Clear</button>
      </div>

      <div class="scard">
        <div class="scard-header">
          <h3 style="display:flex;align-items:center;gap:10px">
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:500;color:var(--text-secondary);cursor:pointer;white-space:nowrap">
              <input type="checkbox" id="driverSelectAll" onchange="toggleDriverSelectAll(this.checked)"> Select All
            </label>
            Driver Directory
          </h3>
        </div>
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
