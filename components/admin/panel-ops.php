<!-- LIVE OPS -->
  <div class="panel" id="panel-ops">
    <div class="page-header"><h2 class="page-title">Live Operations</h2><div class="page-sub">Real-time driver tracking</div></div>
    <div class="ops-map">
      <div class="ops-map-grid"></div>
      <svg viewBox="0 0 400 220" style="position:absolute;inset:0;width:100%;height:100%" preserveAspectRatio="none">
        <path d="M0 80 Q100 70 200 90 T400 80" stroke="white" stroke-width="4" fill="none" opacity="0.35"/>
        <path d="M0 150 Q120 140 200 160 T400 155" stroke="white" stroke-width="3" fill="none" opacity="0.25"/>
        <path d="M100 0 Q110 110 115 220" stroke="white" stroke-width="4" fill="none" opacity="0.35"/>
        <path d="M270 0 Q265 110 272 220" stroke="white" stroke-width="3" fill="none" opacity="0.25"/>
        <circle cx="200" cy="110" r="6" fill="#F5C842" opacity="0.8"/>
        <text x="207" y="114" fill="white" font-size="8" opacity="0.7">City Center</text>
      </svg>
      <div class="ops-rider" id="r1" style="top:28%;left:18%"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><circle cx="5" cy="16" r="2"/><circle cx="19" cy="16" r="2"/><path d="M5 16L9 8h5l3 4h3"/></svg></div>
      <div class="ops-rider" id="r2" style="top:52%;left:42%"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><rect x="2" y="9" width="20" height="8" rx="2"/><path d="M5 9l2-4h10l2 4"/><circle cx="7" cy="17" r="1.5"/><circle cx="17" cy="17" r="1.5"/></svg></div>
      <div class="ops-rider" id="r3" style="top:18%;left:62%"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><circle cx="5" cy="16" r="2"/><circle cx="19" cy="16" r="2"/><path d="M5 16L9 8h5l3 4h3"/></svg></div>
      <div class="ops-rider" id="r4" style="top:68%;left:70%"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><rect x="1" y="6" width="15" height="12" rx="1"/><path d="M16 9l5 3v6h-5"/><circle cx="5" cy="18" r="1.5"/><circle cx="12" cy="18" r="1.5"/></svg></div>
      <div class="ops-rider" id="r5" style="top:38%;left:78%"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><circle cx="5" cy="16" r="2"/><circle cx="19" cy="16" r="2"/><path d="M5 16L9 8h5l3 4h3"/></svg></div>
      <div class="ops-rider" id="r6" style="top:58%;left:12%"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><circle cx="5" cy="17" r="2"/><circle cx="12" cy="17" r="2"/><path d="M5 17V9h7v8"/><path d="M12 12h5v5h-2"/></svg></div>
      <div class="ops-rider" id="r7" style="top:75%;left:32%"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><circle cx="5" cy="16" r="2"/><circle cx="19" cy="16" r="2"/><path d="M5 16L9 8h5l3 4h3"/></svg></div>
      <div class="ops-rider" id="r8" style="top:12%;left:85%"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><rect x="2" y="9" width="20" height="8" rx="2"/><path d="M5 9l2-4h10l2 4"/><circle cx="7" cy="17" r="1.5"/><circle cx="17" cy="17" r="1.5"/></svg></div>
      <div class="map-legend" id="opsMapLegend"><span style="color:var(--success)">●</span> Loading live operations…</div>
    </div>
    <div class="filter-row">
      <button class="filter-btn active" onclick="filterOps('all',this)">All</button>
      <button class="filter-btn" onclick="filterOps('motorbike',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="5" cy="16" r="2"/><circle cx="19" cy="16" r="2"/><path d="M5 16L9 8h5l3 4h3"/></svg> Motorbike</button>
      <button class="filter-btn" onclick="filterOps('car',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="2" y="9" width="20" height="8" rx="2"/><path d="M5 9l2-4h10l2 4"/><circle cx="7" cy="17" r="1.5"/><circle cx="17" cy="17" r="1.5"/></svg> Car</button>
      <button class="filter-btn" onclick="filterOps('van',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="1" y="6" width="15" height="12" rx="1"/><path d="M16 9l5 3v6h-5"/><circle cx="5" cy="18" r="1.5"/><circle cx="12" cy="18" r="1.5"/></svg> Van</button>
      <button class="filter-btn" onclick="filterOps('tricycle',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="5" cy="17" r="2"/><circle cx="12" cy="17" r="2"/><path d="M5 17V9h7v8"/><path d="M12 12h5v5h-2"/></svg> Tricycle</button>
    </div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">ONLINE DRIVERS</div><div class="metric-value" id="opsOnlineDrivers">--</div></div>
      <div class="metric-card"><div class="metric-label">ACTIVE TRIPS</div><div class="metric-value" id="opsActiveTrips">--</div></div>
      <div class="metric-card"><div class="metric-label">LAST LOCATION</div><div class="metric-value" id="opsLastLocation">--</div></div>
      <div class="metric-card"><div class="metric-label">REFRESH</div><div class="metric-value" id="opsRefreshAge">Live</div></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Active Riders</h3><span id="opsListMeta" style="font-size:11px;color:var(--text-muted)">Live feed</span></div>
      <div id="opsDriverList">
        <div class="list-item"><div class="item-info"><div class="item-name">Loading live driver locations…</div><div class="item-meta">Authenticated admins can view current trip state and last known driver location here.</div></div></div>
      </div>
    </div>
  </div>