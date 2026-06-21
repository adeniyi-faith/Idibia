<!-- DISPUTES -->
  <div class="panel" id="panel-disputes">
    <div class="page-header"><h2 class="page-title">Disputes &amp; Support</h2><div class="page-sub">Customer complaints, escalations, and support tickets</div></div>

    <!-- Sub-section tabs: switch between disputes and support tickets -->
    <div class="filter-row" style="margin-bottom:20px;border-bottom:2px solid var(--surface-2);padding-bottom:10px">
      <button class="filter-btn active" id="tab-disputes-btn" onclick="switchDisputeTab('disputes',this)">Disputes</button>
      <button class="filter-btn" id="tab-tickets-btn" onclick="switchDisputeTab('tickets',this)">Support Tickets <span class="badge badge-warn" id="ticketOpenBadge" style="margin-left:4px;display:none"></span></button>
    </div>

    <!-- ── DISPUTES sub-panel ── -->
    <div id="sub-disputes">
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
      <div class="scard"><div id="disputeList"></div><div class="pagination" id="disputePagination"></div></div>
    </div>

    <!-- ── SUPPORT TICKETS sub-panel ── -->
    <div id="sub-tickets" style="display:none">
      <div class="metrics-grid three">
        <div class="metric-card"><div class="metric-label">OPEN TICKETS</div><div class="metric-value" style="color:var(--warn)" id="ticketOpenCount">--</div><div class="metric-delta neutral">Needs attention</div></div>
        <div class="metric-card"><div class="metric-label">TOTAL MATCHES</div><div class="metric-value" id="ticketTotalCount">--</div><div class="metric-delta neutral">Current filter</div></div>
        <div class="metric-card"><div class="metric-label">FILTER</div><div class="metric-value" style="font-size:14px" id="ticketActiveFilter">All</div><div class="metric-delta neutral">Active view</div></div>
      </div>
      <div class="filter-row">
        <button class="filter-btn active" data-filter="all" onclick="filterTickets('all',this)">All</button>
        <button class="filter-btn" data-filter="unassigned" onclick="filterTickets('unassigned',this)">Unassigned</button>
        <button class="filter-btn" data-filter="mine" onclick="filterTickets('mine',this)">Mine</button>
        <button class="filter-btn" data-filter="escalated" onclick="filterTickets('escalated',this)">Escalated</button>
        <button class="filter-btn" data-filter="resolved" onclick="filterTickets('resolved',this)">Resolved</button>
      </div>
      <div class="panel-search">
        <input id="ticketSearch" placeholder="Search by customer name, category…" oninput="queueTicketSearch(this.value)">
        <select class="filter-select" id="ticketStatusFilter" onchange="setTicketStatusFilter(this.value)">
          <option value="">All statuses</option>
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="escalated">Escalated</option>
          <option value="resolved">Resolved</option>
          <option value="closed">Closed</option>
        </select>
      </div>
      <div class="scard"><div id="ticketList"></div><div class="pagination" id="ticketPagination"></div></div>
    </div>
  </div>
