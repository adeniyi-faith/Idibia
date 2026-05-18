<!-- KYC QUEUE -->
  <div class="panel" id="panel-kyc">
    <div class="page-header">
      <h2 class="page-title">KYC Review Queue</h2>
      <div class="page-sub">Driver applications awaiting admin review</div>
    </div>
    <div class="tabs">
      <button class="tab active" onclick="kycTab('under_review',this)">Pending <span id="kyc-pending-count">(0)</span></button>
      <button class="tab" onclick="kycTab('approved',this)">Approved</button>
      <button class="tab" onclick="kycTab('rejected',this)">Rejected</button>
    </div>
    <div class="filter-row">
      <button class="filter-btn active" onclick="filterKyc('all',this)">All Vehicles</button>
      <button class="filter-btn" onclick="filterKyc('bike',this)">Motorbike</button>
      <button class="filter-btn" onclick="filterKyc('car',this)">Car</button>
      <button class="filter-btn" onclick="filterKyc('van',this)">Van</button>
      <button class="filter-btn" onclick="filterKyc('keke',this)">Tricycle</button>
    </div>
    <div class="scard" id="kycQueue">
      <div style="padding:32px;text-align:center;color:var(--text-muted);font-size:13px">Loading driver applications…</div>
      <div id="kyc-empty" style="display:none;padding:32px;text-align:center;color:var(--text-muted);font-size:13px">All applications reviewed ✓</div>
    </div>
    <!-- KYC DETAIL OVERLAY -->
    <div class="driver-detail" id="kycDetail">
      <div class="detail-header"><button class="detail-back" onclick="closeKycDetail()">← Back to queue</button></div>
      <div class="scard">
        <div style="padding:20px">
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
            <div class="avatar" style="width:56px;height:56px;font-size:18px;background:rgba(74,158,255,0.1);color:var(--info)" id="detail-avatar">CN</div>
            <div><div style="font-size:17px;font-weight:700" id="detail-name">Chidi Nwosu</div><div style="font-size:12px;color:var(--text-muted)" id="detail-meta">Motorbike · Rivers · Applied 2h ago</div></div>
          </div>
          <div class="kyc-steps"><div class="kyc-step done"></div><div class="kyc-step done"></div><div class="kyc-step done"></div><div class="kyc-step done"></div><div class="kyc-step active"></div></div>
          <div style="font-size:11px;color:var(--text-muted);margin-bottom:16px">Step 5 of 5 · Pending admin review</div>
          <div class="metrics-grid">
            <div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:4px">DOCUMENT STATUS</div><div style="font-size:12px;font-weight:600" id="detail-docs">ID verified ✓</div></div>
            <div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:4px">VEHICLE DOCS</div><div style="font-size:12px;font-weight:600" id="detail-vehicle-docs">License & Inspection ✓</div></div>
            <div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:4px">PHOTO REVIEW</div><div style="font-size:12px;font-weight:600" id="detail-photo">Clear portrait ✓</div></div>
            <div style="background:var(--surface);border-radius:10px;padding:12px"><div style="font-size:10px;color:var(--text-muted);margin-bottom:4px">BANK DETAILS</div><div style="font-size:12px;font-weight:600" id="detail-bank">Bank details pending</div></div>
          </div>
          <div id="kycReviewDetails"></div>
          <div class="form-group" id="kycRejectReasonGroup">
            <label class="form-label">Rejection reason (if rejecting)</label>
            <select class="form-input" id="reject-reason">
              <option value="">Select reason…</option>
              <option>Blurry/invalid ID photo</option><option>License expired</option>
              <option>Vehicle inspection failed</option><option>Incomplete documents</option>
              <option>Profile photo invalid (cap/glasses)</option><option>Other</option>
            </select>
          </div>
          <div id="kycReviewActions" style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn-sm btn-reject" style="flex:1;min-width:140px;height:40px;font-size:13px" onclick="kycDetailAction('rejected')">Reject Application</button>
            <button class="btn-sm btn-approve" style="flex:1;min-width:140px;height:40px;font-size:13px" onclick="kycDetailAction('approved')">Approve Driver</button>
          </div>
        </div>
      </div>
    </div>
  </div>