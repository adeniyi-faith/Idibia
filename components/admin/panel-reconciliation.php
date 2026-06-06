<!-- RECONCILIATION -->
  <div class="panel" id="panel-reconciliation" style="display:none;">
    <div class="page-header"><h2 class="page-title">Reconciliation</h2><div class="page-sub">Finance payment verification</div></div>

    <div class="filter-row">
      <input type="text" class="form-input search-input" placeholder="Search trip ref or customer…" oninput="queueReconciliationSearch(this.value)">
      <input type="date" class="form-input filter-select" onchange="setReconciliationDateStart(this.value)" placeholder="Start Date">
      <input type="date" class="form-input filter-select" onchange="setReconciliationDateEnd(this.value)" placeholder="End Date">
      <select class="form-input filter-select" onchange="setReconciliationStatus(this.value)">
        <option value="all">All statuses</option>
        <option value="proof_submitted">Waiting review</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
    </div>

    <div id="reconciliationList"></div>

    <div class="pagination" id="reconciliationPagination"></div>
  </div>
