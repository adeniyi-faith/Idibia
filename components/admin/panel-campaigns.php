<!-- CAMPAIGNS -->
<div class="panel" id="panel-campaigns">
  <div class="page-header">
    <h2 class="page-title">Driver Campaigns</h2>
    <div class="page-sub">Incentive challenges — reward drivers who hit trip targets</div>
  </div>

  <!-- Summary metrics -->
  <div class="metrics-grid four">
    <div class="metric-card"><div class="metric-label">ACTIVE CAMPAIGNS</div><div class="metric-value" id="campMetricActive">--</div><div class="metric-delta neutral">Running now</div></div>
    <div class="metric-card"><div class="metric-label">TOTAL BONUS PAID</div><div class="metric-value" id="campMetricBonusPaid">--</div><div class="metric-delta up">All time</div></div>
    <div class="metric-card"><div class="metric-label">DRIVERS ENROLLED</div><div class="metric-value" id="campMetricEnrolled">--</div><div class="metric-delta neutral">Across active</div></div>
    <div class="metric-card"><div class="metric-label">COMPLETION RATE</div><div class="metric-value" id="campMetricCompletion">--</div><div class="metric-delta up">Hit target</div></div>
  </div>

  <!-- Campaign list card -->
  <div class="scard">
    <div class="scard-header">
      <h3>All Campaigns</h3>
      <div style="display:flex;gap:8px;align-items:center">
        <select class="filter-select" id="campStatusFilter" onchange="loadCampaigns(1)" style="max-width:160px">
          <option value="all">All statuses</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
          <option value="completed">Completed</option>
        </select>
        <button class="btn-primary" style="font-size:11px;padding:6px 14px;width:auto" onclick="openCreateCampaignModal()">+ Create Campaign</button>
      </div>
    </div>
    <div id="campList" style="min-height:80px"></div>
    <div class="pagination" id="campPagination"></div>
  </div>

  <!-- Leaderboard / detail card (hidden by default, shown when a campaign is selected) -->
  <div class="scard" id="campLeaderboardCard" style="display:none">
    <div class="scard-header">
      <h3 id="campLeaderboardTitle">Campaign Leaderboard</h3>
      <button class="scard-action" onclick="closeCampaignLeaderboard()">Close</button>
    </div>
    <div id="campLeaderboardContent" style="padding:16px"></div>
  </div>
</div>

<!-- Create / Edit Campaign Modal -->
<div class="modal-overlay" id="campaignModalOverlay" style="display:none" onclick="if(event.target===this)closeCampaignModal()">
  <div class="modal" style="max-width:520px;width:90%">
    <div class="modal-header">
      <span id="campaignModalTitle">Create Campaign</span>
      <button class="modal-close" onclick="closeCampaignModal()">✕</button>
    </div>
    <div class="modal-body" style="padding:20px">
      <input type="hidden" id="campEditId" value="">

      <div style="margin-bottom:14px">
        <label class="form-label">Title *</label>
        <input class="form-input" id="campTitle" placeholder="e.g. Weekend Warriors" maxlength="255">
      </div>
      <div style="margin-bottom:14px">
        <label class="form-label">Description</label>
        <textarea class="form-input" id="campDescription" rows="3" placeholder="Tell drivers what this campaign is about…" style="resize:vertical"></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div>
          <label class="form-label">Target Trips *</label>
          <input class="form-input" id="campTargetTrips" type="number" min="1" placeholder="e.g. 50">
        </div>
        <div>
          <label class="form-label">Bonus Amount (₦) *</label>
          <input class="form-input" id="campBonusAmount" type="number" min="1" step="0.01" placeholder="e.g. 5000">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
        <div>
          <label class="form-label">Start Date & Time *</label>
          <input class="form-input" id="campStartTime" type="datetime-local">
        </div>
        <div>
          <label class="form-label">End Date & Time *</label>
          <input class="form-input" id="campEndTime" type="datetime-local">
        </div>
      </div>
      <div style="margin-bottom:20px">
        <label class="form-label">Eligible Vehicle Types</label>
        <input class="form-input" id="campVehicleTypes" placeholder='all  —or—  ["bike","car"]' value="all">
        <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Type <strong>all</strong> to allow every vehicle type, or enter a JSON list like <code>["bike","car"]</code></div>
      </div>

      <div id="campaignModalError" style="display:none;color:var(--danger);font-size:13px;margin-bottom:12px"></div>

      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button class="btn-secondary" onclick="closeCampaignModal()">Cancel</button>
        <button class="btn-primary" id="campSaveBtn" onclick="saveCampaign()">Create Campaign</button>
      </div>
    </div>
  </div>
</div>
