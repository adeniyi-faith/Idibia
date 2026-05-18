<!-- DELIVERIES -->
  <div class="panel" id="panel-trips">
    <div class="page-header"><h2 class="page-title">Deliveries</h2><div class="page-sub">All trips, tracking and receipts</div></div>
    <div class="panel-search">
      <input placeholder="Search order ID, driver…" id="tripSearch" oninput="searchTrips(this.value)">
      <select class="filter-select" onchange="filterTrips(this.value)">
        <option value="">All categories</option>
        <option>Package</option><option>Gift</option><option>Documents</option><option>Groceries</option><option>Flowers</option><option>Laundry</option>
      </select>
      <button class="disabled-action" aria-disabled="true" onclick="showUnavailableFeature('Delivery export', 'Delivery CSV export needs a backend report endpoint before files can be generated.')">Export</button>
    </div>
    <div class="filter-row">
      <button class="filter-btn active" onclick="filterTripStatus('all',this)">All</button>
      <button class="filter-btn" onclick="filterTripStatus('in-transit',this)">In Transit</button>
      <button class="filter-btn" onclick="filterTripStatus('delivered',this)">Delivered</button>
      <button class="filter-btn" onclick="filterTripStatus('delayed',this)">Delayed</button>
      <button class="filter-btn" onclick="filterTripStatus('cancelled',this)">Cancelled</button>
    </div>
    <div class="scard">
      <div id="tripList"><div class="loading-state">Loading deliveries…</div></div><div class="pagination" id="tripPagination"></div>
    </div>
  </div>