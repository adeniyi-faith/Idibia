<!-- CUSTOMERS -->
  <div class="panel" id="panel-customers">
    <div class="page-header">
      <div class="page-header-text">
        <h2 class="page-title">Customers</h2>
        <div class="page-sub">User accounts, reports and referrals</div>
      </div>
      <button class="btn-primary" onclick="openCustomerPanel()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14" style="display:inline;vertical-align:middle;margin-right:5px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Customer
      </button>
    </div>
    <div class="metrics-grid four">
      <div class="metric-card"><div class="metric-label">TOTAL CUSTOMERS</div><div class="metric-value" id="customersTotalCount">--</div><div class="metric-delta neutral">Current filter</div></div>
      <div class="metric-card"><div class="metric-label">VERIFIED EMAILS</div><div class="metric-value" id="customersVerifiedCount">--</div><div class="metric-delta neutral">Visible page</div></div>
      <div class="metric-card"><div class="metric-label">REFERRALS USED</div><div class="metric-value">--</div><div class="metric-delta neutral">Referral tracking not connected</div></div>
      <div class="metric-card"><div class="metric-label">SUSPENDED</div><div class="metric-value" style="color:var(--danger)" id="customersSuspendedCount">--</div><div class="metric-delta neutral">Current filter</div></div>
    </div>
    <div class="panel-search"><input id="customerSearch" placeholder="Search customer name or phone…" oninput="queueCustomerSearch(this.value)"><button onclick="loadCustomers(1)">Search</button></div>

    <!-- Bulk action bar — hidden until at least one customer is selected -->
    <div id="customerBulkBar" style="display:none;align-items:center;gap:10px;padding:10px 14px;background:var(--surface-1);border-radius:10px;margin-bottom:12px;border:1px solid var(--surface-2)">
      <span id="customerSelectedCount" style="font-size:13px;font-weight:700;color:var(--info)">0 selected</span>
      <div style="flex:1"></div>
      <select class="filter-select" id="customerBulkActionSelect" style="font-size:12px;padding:6px 10px;height:auto">
        <option value="">Bulk Actions…</option>
        <option value="suspend">Suspend</option>
        <option value="reinstate">Reinstate</option>
        <option value="send_notification">Send Notification</option>
        <option value="export">Export Selected</option>
      </select>
      <button class="btn-primary" style="font-size:12px;padding:7px 14px;white-space:nowrap" onclick="executeBulkCustomerAction(document.getElementById('customerBulkActionSelect').value)">Apply</button>
      <button style="font-size:12px;padding:7px 10px;background:none;border:1.5px solid var(--surface-2);border-radius:9px;cursor:pointer;color:var(--text-secondary)" onclick="clearCustomerSelection()">Clear</button>
    </div>

    <div class="scard">
      <div class="scard-header">
        <h3 style="display:flex;align-items:center;gap:10px">
          <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:500;color:var(--text-secondary);cursor:pointer;white-space:nowrap">
            <input type="checkbox" id="customerSelectAll" onchange="toggleCustomerSelectAll(this.checked)"> Select All
          </label>
          Customer Directory
        </h3>
      </div>
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

<!-- Customer Create / Edit Slide Panel -->
<div class="slide-panel-overlay" id="customerSlidePanelOverlay" onclick="closeCustomerPanel()" style="display:none;"></div>
<div class="slide-panel" id="customerSlidePanel" style="display:none;">
  <div class="slide-panel-header">
    <div>
      <h3 id="customerSlideTitle">Add Customer Account</h3>
      <div style="font-size:11px;color:var(--text-muted);margin-top:2px;" id="customerSlideSubtitle">Fill in the details below</div>
    </div>
    <button class="close-slide-btn" onclick="closeCustomerPanel()" aria-label="Close">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <div class="slide-panel-body">
    <form id="customerForm" onsubmit="saveCustomer(event)" novalidate>
      <input type="hidden" id="customerEditId" value="">

      <div class="slide-section-label">Account Details</div>
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Full Name <span class="req-star">*</span></label>
          <input type="text" class="form-input" id="customerFormFullName" placeholder="e.g. Amara Nwosu" autocomplete="name" required>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Phone Number <span class="req-star">*</span></label>
          <input type="tel" class="form-input" id="customerFormPhone" placeholder="e.g. 08012345678" autocomplete="tel" required>
        </div>
      </div>

      <div class="form-group" id="customerFormEmailGroup">
        <label class="form-label">Email Address <span class="req-star">*</span></label>
        <input type="email" class="form-input" id="customerFormEmail" placeholder="customer@example.com" autocomplete="email">
        <div class="form-hint">Email will be marked as verified automatically. Cannot be changed after creation.</div>
      </div>

      <div class="form-group" id="customerFormPasswordGroup">
        <label class="form-label">Password <span class="req-star">*</span></label>
        <div class="pw-input-wrap">
          <input type="password" class="form-input" id="customerFormPassword" placeholder="Min. 6 characters" autocomplete="new-password">
          <button type="button" class="pw-toggle-btn" onclick="togglePwVisibility('customerFormPassword',this)" aria-label="Show password">
            <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
        <div class="form-hint">Customer will receive a referral code automatically.</div>
      </div>

      <div class="slide-panel-footer">
        <button type="button" class="btn-secondary" onclick="closeCustomerPanel()">Cancel</button>
        <button type="submit" class="btn-primary" id="customerSaveBtn">Add Customer</button>
      </div>
    </form>
  </div>
</div>
