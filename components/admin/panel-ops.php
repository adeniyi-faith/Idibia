<!-- LIVE OPS -->
  <div class="panel" id="panel-ops">
    <div class="page-header"><h2 class="page-title">Live Operations</h2><div class="page-sub">Port Harcourt metro · Real-time driver tracking</div></div>
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
      <div class="ops-rider" id="r1" style="top:28%;left:18%">🛵</div>
      <div class="ops-rider" id="r2" style="top:52%;left:42%">🚗</div>
      <div class="ops-rider" id="r3" style="top:18%;left:62%">🛵</div>
      <div class="ops-rider" id="r4" style="top:68%;left:70%">🚐</div>
      <div class="ops-rider" id="r5" style="top:38%;left:78%">🛵</div>
      <div class="ops-rider" id="r6" style="top:58%;left:12%">🛺</div>
      <div class="ops-rider" id="r7" style="top:75%;left:32%">🛵</div>
      <div class="ops-rider" id="r8" style="top:12%;left:85%">🚗</div>
      <div class="map-legend" id="opsMapLegend"><span style="color:var(--success)">●</span> Loading live operations…</div>
    </div>
    <div class="filter-row">
      <button class="filter-btn active" onclick="filterOps('all',this)">All</button>
      <button class="filter-btn" onclick="filterOps('motorbike',this)">🛵 Motorbike</button>
      <button class="filter-btn" onclick="filterOps('car',this)">🚗 Car</button>
      <button class="filter-btn" onclick="filterOps('van',this)">🚐 Van</button>
      <button class="filter-btn" onclick="filterOps('tricycle',this)">🛺 Tricycle</button>
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