<!-- RATINGS -->
  <div class="panel" id="panel-ratings">
    <div class="page-header">
      <h2 class="page-title">Ratings</h2>
      <div class="page-sub">All reviews submitted by customers and drivers across the platform</div>
    </div>

    <!-- Filters -->
    <div class="panel-search" style="flex-wrap:wrap;gap:8px">
      <select class="filter-select" id="ratingsFilterType" onchange="loadRatings(1)">
        <option value="">All reviewer types</option>
        <option value="customer">Customer → Driver</option>
        <option value="driver">Driver → Customer</option>
      </select>
      <select class="filter-select" id="ratingsFilterStar" onchange="loadRatings(1)">
        <option value="">All stars</option>
        <option value="5">5 stars</option>
        <option value="4">4 stars</option>
        <option value="3">3 stars</option>
        <option value="2">2 stars</option>
        <option value="1">1 star</option>
      </select>
      <input class="filter-select" type="date" id="ratingsDateFrom" onchange="loadRatings(1)" placeholder="From date" style="font-size:13px;padding:8px 12px">
      <input class="filter-select" type="date" id="ratingsDateTo" onchange="loadRatings(1)" placeholder="To date" style="font-size:13px;padding:8px 12px">
      <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-secondary);cursor:pointer;padding:0 4px">
        <input type="checkbox" id="ratingsFlaggedOnly" onchange="loadRatings(1)"> Flagged only
      </label>
      <button onclick="loadRatings(1)">Apply</button>
    </div>

    <!-- Stats row -->
    <div class="metrics-grid four" style="margin-bottom:16px" id="ratingsStatsRow">
      <div class="metric-card"><div class="metric-label">TOTAL RATINGS</div><div class="metric-value" id="ratingsTotalCount">--</div><div class="metric-delta neutral">Current filter</div></div>
      <div class="metric-card"><div class="metric-label">AVG SCORE</div><div class="metric-value" id="ratingsAvgScore">--</div><div class="metric-delta neutral">This page</div></div>
      <div class="metric-card"><div class="metric-label">FLAGGED</div><div class="metric-value" style="color:var(--warning)" id="ratingsFlaggedCount">--</div><div class="metric-delta neutral">Under review</div></div>
      <div class="metric-card"><div class="metric-label">1-STAR RATINGS</div><div class="metric-value" style="color:var(--danger)" id="ratingsOneStarCount">--</div><div class="metric-delta neutral">Needs attention</div></div>
    </div>

    <!-- Ratings table -->
    <div class="scard">
      <div class="scard-header"><h3>All Ratings</h3></div>
      <div id="ratingsList"></div>
      <div class="pagination" id="ratingsPagination"></div>
    </div>
  </div>

  <!-- Subject Ratings Slide-Panel -->
  <div class="slide-panel-overlay" id="subjectRatingsPanelOverlay" onclick="closeSubjectRatingsPanel()" style="display:none"></div>
  <div class="slide-panel" id="subjectRatingsPanel" style="display:none">
    <div class="slide-panel-header">
      <h3 id="subjectRatingsPanelTitle">Rating History</h3>
      <button onclick="closeSubjectRatingsPanel()" style="background:none;border:none;cursor:pointer;color:var(--text-secondary);font-size:20px;line-height:1">×</button>
    </div>
    <div class="slide-panel-body">
      <div id="subjectRatingsPanelBody"></div>
    </div>
  </div>
