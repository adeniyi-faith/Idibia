<!-- CUSTOMERS -->
  <div class="panel" id="panel-customers">
    <div class="page-header"><h2 class="page-title">Customers</h2><div class="page-sub">User accounts, reports and referrals</div></div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">TOTAL CUSTOMERS</div><div class="metric-value" id="customersTotalCount">--</div><div class="metric-delta neutral">Current filter</div></div>
      <div class="metric-card"><div class="metric-label">VERIFIED EMAILS</div><div class="metric-value" id="customersVerifiedCount">--</div><div class="metric-delta neutral">Visible page</div></div>
      <div class="metric-card"><div class="metric-label">REFERRALS USED</div><div class="metric-value">--</div><div class="metric-delta neutral">Referral tracking not connected</div></div>
      <div class="metric-card"><div class="metric-label">SUSPENDED</div><div class="metric-value" style="color:var(--danger)" id="customersSuspendedCount">--</div><div class="metric-delta neutral">Current filter</div></div>
    </div>
    <div class="panel-search"><input id="customerSearch" placeholder="Search customer name or phone…" oninput="queueCustomerSearch(this.value)"><button onclick="loadCustomers(1)">Search</button></div>
    <div class="scard">
      <div class="scard-header"><h3>Customer Directory</h3></div>
      <div id="customerDirectory"></div><div class="pagination" id="customerPagination"></div>
    </div>
    <div class="scard">
      <div class="scard-header"><h3>Referral program</h3></div>
      <div style="padding:16px 18px">
        <div class="empty-state">Referral metrics are preserved in the UI but need referral tracking tables and analytics endpoints before live values can be shown.</div>
        <button class="btn-primary disabled-action" style="font-size:12px;padding:8px 16px;margin-top:12px" aria-disabled="true" onclick="showUnavailableFeature('Referral report', 'Referral reporting needs referral tracking tables and export endpoints before files can be generated.')">Download Report</button>
      </div>
    </div>
  </div>
