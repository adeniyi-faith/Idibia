<!-- ADMIN USERS (STAFF) -->
<div class="panel" id="panel-admin-users">
  <div class="page-header">
    <div class="page-header-text">
      <h2 class="page-title">Staff &amp; Admin Users</h2>
      <div class="page-sub">Manage internal team accounts, roles, and granular permissions</div>
    </div>
    <button class="btn-primary" onclick="openAdminUserPanel()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14" style="display:inline;vertical-align:middle;margin-right:5px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Create User
    </button>
  </div>

  <!-- Quick Stats -->
  <div class="staff-stats" id="staffStatsRow">
    <div class="staff-stat-card">
      <div class="staff-stat-label">Total Staff</div>
      <div class="staff-stat-value" id="staffStatTotal">—</div>
    </div>
    <div class="staff-stat-card">
      <div class="staff-stat-label">Active</div>
      <div class="staff-stat-value" id="staffStatActive" style="color:var(--success)">—</div>
    </div>
    <div class="staff-stat-card">
      <div class="staff-stat-label">Suspended</div>
      <div class="staff-stat-value" id="staffStatSuspended" style="color:var(--danger)">—</div>
    </div>
    <div class="staff-stat-card">
      <div class="staff-stat-label">Roles</div>
      <div class="staff-stat-value" id="staffStatRoles">—</div>
    </div>
  </div>

  <div class="panel-search">
    <input id="adminUserSearch" placeholder="Search by name or email…" oninput="loadAdminUsers()">
  </div>

  <div class="scard">
    <div class="scard-header">
      <h3>Internal Users</h3>
      <span id="staffUserCount" style="font-size:12px;color:var(--text-muted);"></span>
    </div>
    <div class="table-responsive">
      <table class="data-table" id="adminUsersTable">
        <thead>
          <tr>
            <th>User</th>
            <th>Role</th>
            <th>Status</th>
            <th>Last Login</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="adminUsersTbody"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Slide-over panel for Create/Edit Admin User -->
<div class="slide-panel-overlay" id="adminUserSlidePanelOverlay" onclick="closeAdminUserPanel()" style="display:none;"></div>
<div class="slide-panel" id="adminUserSlidePanel" style="display:none;">
  <div class="slide-panel-header">
    <div>
      <h3 id="adminUserSlideTitle">Create Admin User</h3>
      <div style="font-size:11px;color:var(--text-muted);margin-top:2px;" id="adminUserSlideSubtitle">Fill in the details below</div>
    </div>
    <button class="close-slide-btn" onclick="closeAdminUserPanel()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
      </svg>
    </button>
  </div>
  <div class="slide-panel-body">
    <form id="adminUserForm" onsubmit="saveAdminUser(event)">
      <input type="hidden" id="adminUserId" value="">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Full Name</label>
          <input type="text" class="form-input" id="adminUserFullName" placeholder="Jane Doe" required>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Email Address</label>
          <input type="email" class="form-input" id="adminUserEmail" placeholder="jane@company.com" required>
        </div>
      </div>

      <div class="form-group" id="adminUserPasswordGroup">
        <label class="form-label">Initial Password</label>
        <input type="text" class="form-input" id="adminUserPassword" placeholder="Temporary password…">
        <div class="form-hint">User will be required to change this on first login.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Role</label>
        <div class="role-select-wrapper">
          <select class="form-input" id="adminUserRole" onchange="renderPermissionToggles()" required>
            <option value="">Loading roles…</option>
          </select>
        </div>
        <div class="role-desc-card" id="adminUserRoleDescCard"></div>
      </div>

      <div class="permissions-section" style="margin-top:8px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
          <h4 style="font-size:13px;font-weight:700;color:var(--text-primary);">Permission Overrides</h4>
          <span style="font-size:11px;color:var(--text-muted);">
            <span style="display:inline-block;width:7px;height:7px;background:var(--info);border-radius:50%;margin-right:3px;vertical-align:middle;"></span>
            = override from role default
          </span>
        </div>
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:14px;line-height:1.5;">
          Toggles reflect the role's default permissions. Toggle any permission to override it for this specific user.
        </div>
        <div id="adminUserPermissionsContainer">
          <!-- Permissions rendered by JS -->
        </div>
      </div>

      <div class="slide-panel-footer">
        <button type="button" class="btn-secondary" onclick="closeAdminUserPanel()">Cancel</button>
        <button type="submit" class="btn-primary" id="adminUserSaveBtn">Save User</button>
      </div>
    </form>
  </div>
</div>
