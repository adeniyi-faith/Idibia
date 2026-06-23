<!-- LIVE OPS -->
  <div class="panel" id="panel-ops">
    <div class="page-header"><h2 class="page-title">Live Operations</h2><div class="page-sub">Real-time trip tracking · Auto-refreshes every 15s</div></div>
    <div style="position:relative;margin-bottom:18px">
      <div id="opsLeafletMap" class="ops-map"></div>
      <div class="map-legend" id="opsMapLegend" style="z-index:800"><span style="color:var(--success)">●</span> Loading live operations…</div>
    </div>
    <div class="filter-row">
      <button class="filter-btn active" onclick="filterOps('all',this)">All</button>
      <button class="filter-btn" onclick="filterOps('motorbike',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="5" cy="16" r="2"/><circle cx="19" cy="16" r="2"/><path d="M5 16L9 8h5l3 4h3"/></svg> Motorbike</button>
      <button class="filter-btn" onclick="filterOps('car',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="2" y="9" width="20" height="8" rx="2"/><path d="M5 9l2-4h10l2 4"/><circle cx="7" cy="17" r="1.5"/><circle cx="17" cy="17" r="1.5"/></svg> Car</button>
      <button class="filter-btn" onclick="filterOps('van',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="1" y="6" width="15" height="12" rx="1"/><path d="M16 9l5 3v6h-5"/><circle cx="5" cy="18" r="1.5"/><circle cx="12" cy="18" r="1.5"/></svg> Van</button>
      <button class="filter-btn" onclick="filterOps('tricycle',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="5" cy="17" r="2"/><circle cx="12" cy="17" r="2"/><path d="M5 17V9h7v8"/><path d="M12 12h5v5h-2"/></svg> Tricycle</button>
    </div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">ONLINE DRIVERS</div><div class="metric-value" id="opsOnlineDrivers">--</div><div class="metric-delta neutral">Active &amp; approved</div></div>
      <div class="metric-card"><div class="metric-label">IN PROGRESS</div><div class="metric-value" id="opsActiveTrips">--</div><div class="metric-delta neutral">Trips en route</div></div>
      <div class="metric-card"><div class="metric-label">SEARCHING</div><div class="metric-value" id="opsSearchingTrips">--</div><div class="metric-delta neutral">Awaiting driver</div></div>
      <div class="metric-card"><div class="metric-label">LAST REFRESH</div><div class="metric-value" id="opsRefreshAge">Live</div><div class="metric-delta neutral" id="opsLastLocation">--</div></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Live Trips</h3><span id="opsListMeta" style="font-size:11px;color:var(--text-muted)">Live feed</span></div>
      <div id="opsTripList"></div>
    </div>
    <div class="scard" style="margin-top:16px">
      <div class="scard-header"><h3>Active Riders</h3><span style="font-size:11px;color:var(--text-muted)">Online drivers with last known GPS</span></div>
      <div id="opsDriverList"></div>
    </div>

    <!-- SUPPLY & DEMAND HEATMAP + LIVE ALERTS SIDE BY SIDE -->
    <div style="display:grid;grid-template-columns:1fr minmax(0,340px);gap:16px;margin-top:16px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">

      <!-- Supply vs Demand by Zone -->
      <div class="scard">
        <div class="scard-header">
          <h3>Supply vs Demand by Zone</h3>
          <button class="scard-action" onclick="loadDemandHeatmap()">Refresh</button>
        </div>
        <div style="padding:8px 0">
          <div style="display:flex;gap:18px;padding:8px 16px 6px;font-size:11px;color:var(--text-muted);flex-wrap:wrap">
            <span><span style="color:var(--success)">●</span> Supply &gt; Demand (drivers outnumber requests)</span>
            <span><span style="color:var(--warn)">●</span> Balanced</span>
            <span><span style="color:var(--danger)">●</span> Demand &gt; Supply (customers waiting)</span>
          </div>
          <div id="opsHeatmapZones" style="padding-bottom:8px">
            <div style="padding:16px;color:var(--text-muted);font-size:12px">Loading zone data…</div>
          </div>
        </div>
      </div>

      <!-- Live Alert Feed -->
      <div class="scard" style="display:flex;flex-direction:column">
        <div class="scard-header" style="flex-shrink:0">
          <h3 style="display:flex;align-items:center;gap:6px">
            Live Alerts
            <span id="opsAlertCount" style="display:none;background:var(--danger);color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:700;min-width:18px;text-align:center"></span>
          </h3>
          <button class="scard-action" onclick="loadLiveAlerts()">Refresh</button>
        </div>
        <div id="opsAlertFeed" style="flex:1;overflow-y:auto;max-height:420px;min-height:80px">
          <div style="padding:16px;color:var(--text-muted);font-size:12px">Loading…</div>
        </div>
      </div>

    </div>
  </div>
