<!-- OVERVIEW -->
  <div class="panel active" id="panel-overview">
    <div class="page-header">
      <h2 class="page-title">Platform Overview</h2>
      <div class="page-sub">Live snapshot · Port Harcourt metro</div>
    </div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">ACTIVE RIDERS</div><div class="metric-value" id="overviewActiveDrivers">--</div><div class="metric-delta neutral" id="overviewOnlineDrivers">Loading…</div></div>
      <div class="metric-card"><div class="metric-label">TRIPS TODAY</div><div class="metric-value" id="overviewTripsToday">--</div><div class="metric-delta neutral" id="overviewTripsDelta">Loading…</div></div>
      <div class="metric-card"><div class="metric-label">REVENUE TODAY</div><div class="metric-value" id="overviewRevenueToday">--</div><div class="metric-delta neutral">Platform commission</div></div>
      <div class="metric-card"><div class="metric-label">KYC PENDING</div><div class="metric-value" style="color:var(--danger)" id="overviewKycPending">--</div><div class="metric-delta down">Needs attention</div></div>
    </div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">COMPLETION RATE</div><div class="metric-value" id="overviewCompletionRate">--</div><div class="metric-delta neutral">Last 24h</div></div>
      <div class="metric-card"><div class="metric-label">AVG PICKUP TIME</div><div class="metric-value" id="overviewAvgPickup">--</div><div class="metric-delta neutral">Last 24h</div></div>
      <div class="metric-card"><div class="metric-label">OPEN DISPUTES</div><div class="metric-value" style="color:var(--warn)" id="overviewOpenDisputes">--</div><div class="metric-delta down" id="overviewEscalatedDisputes">-- escalated</div></div>
      <div class="metric-card"><div class="metric-label">SUSPENDED</div><div class="metric-value" style="color:var(--danger)" id="overviewSuspended">--</div><div class="metric-delta neutral">Driver accounts</div></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Recent Deliveries</h3><button class="scard-action" onclick="nav('trips',document.querySelectorAll('.nav-btn')[3])">View all →</button></div>
      <div id="overviewRecentTrips"><div class="loading-state">Loading recent deliveries…</div></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Activity Trend (7 days)</h3></div>
      <div style="padding:16px 18px">
        <div style="display:flex;align-items:flex-end;gap:6px;height:60px;margin-bottom:8px">
          <div style="flex:1;background:var(--navy-light);border-radius:3px 3px 0 0;height:40%"></div>
          <div style="flex:1;background:var(--navy-light);border-radius:3px 3px 0 0;height:55%"></div>
          <div style="flex:1;background:var(--navy-light);border-radius:3px 3px 0 0;height:60%"></div>
          <div style="flex:1;background:var(--navy-light);border-radius:3px 3px 0 0;height:70%"></div>
          <div style="flex:1;background:var(--navy-light);border-radius:3px 3px 0 0;height:80%"></div>
          <div style="flex:1;background:var(--navy-light);border-radius:3px 3px 0 0;height:90%"></div>
          <div style="flex:1;background:var(--gold);border-radius:3px 3px 0 0;height:100%"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text-muted)"><span>Sa</span><span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span style="color:var(--gold);font-weight:700">Fr</span></div>
        <div style="margin-top:8px;font-size:11px;color:var(--text-muted)">Peak: Friday · <span style="color:var(--success)">1,423 trips</span></div>
      </div>
    </div>
  </div>