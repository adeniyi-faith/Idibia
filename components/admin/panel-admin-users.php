<!-- ADMIN USERS (STAFF) -->
<div class="panel" id="panel-admin-users" style="display:none;">
  <div class="page-header">
    <div>
      <h2 class="page-title">Admin Users</h2>
      <div class="page-sub">Manage internal staff accounts, roles, and granular permissions</div>
    </div>
    <button class="btn-primary" onclick="openAdminUserPanel()">+ Create User</button>
  </div>

  <div class="panel-search">
    <input id="adminUserSearch" placeholder="Search by name or email…" oninput="loadAdminUsers()">
  </div>

  <div class="scard">
    <div class="scard-header">
      <h3>Internal Users</h3>
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
        <tbody id="adminUsersTbody">
          <tr><td colspan="5" class="loading-state">Loading users...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Slide-over panel for Create/Edit Admin User -->
<div class="slide-panel-overlay" id="adminUserSlidePanelOverlay" onclick="closeAdminUserPanel()"></div>
<div class="slide-panel" id="adminUserSlidePanel">
  <div class="slide-panel-header">
    <h3 id="adminUserSlideTitle">Create Admin User</h3>
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

      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input type="text" class="form-input" id="adminUserFullName" required>
      </div>

      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" class="form-input" id="adminUserEmail" required>
      </div>

      <div class="form-group" id="adminUserPasswordGroup">
        <label class="form-label">Initial Password</label>
        <input type="text" class="form-input" id="adminUserPassword">
        <div class="form-hint">User will be forced to change this on first login.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Role</label>
        <select class="form-input" id="adminUserRole" onchange="renderPermissionToggles()" required>
          <option value="">Select a role...</option>
        </select>
        <div class="form-hint" id="adminUserRoleDesc">Select a role to see default permissions.</div>
      </div>

      <div class="permissions-section" style="margin-top:24px;">
        <h4 style="font-size:14px; margin-bottom:12px;">Permissions Overrides</h4>
        <div class="form-hint" style="margin-bottom:16px;">Toggles reflect the role's default permissions. You can override individual permissions below. <span style="display:inline-block;width:8px;height:8px;background:var(--primary);border-radius:50%;margin-left:8px;margin-right:4px;"></span> indicates an override.</div>

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
