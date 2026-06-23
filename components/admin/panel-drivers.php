<!-- DRIVERS -->
  <div class="panel" id="panel-drivers">
    <div class="page-header">
      <div class="page-header-text">
        <h2 class="page-title">Drivers</h2>
        <div class="page-sub">All approved and active delivery riders</div>
      </div>
      <button class="btn-primary" onclick="openDriverPanel()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14" style="display:inline;vertical-align:middle;margin-right:5px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Driver
      </button>
    </div>
    <div class="tabs">
      <button class="tab active" id="driverTabDrivers" onclick="driverPanelTab('drivers',this)">All Drivers</button>
      <button class="tab" id="driverTabBlacklist" onclick="driverPanelTab('blacklist',this)">Blacklist</button>
    </div>

    <!-- ALL DRIVERS SECTION -->
    <div id="driverMainSection">
      <div class="metrics-grid four">
        <div class="metric-card"><div class="metric-label">TOTAL DRIVERS</div><div class="metric-value" id="driversTotalCount">--</div><div class="metric-delta neutral">Current filter</div></div>
        <div class="metric-card"><div class="metric-label">ONLINE NOW</div><div class="metric-value" id="driversOnlineCount">--</div><div class="metric-delta neutral">Loaded from driver records</div></div>
        <div class="metric-card"><div class="metric-label">SUSPENDED</div><div class="metric-value" style="color:var(--danger)" id="driversSuspendedCount">--</div><div class="metric-delta down">Review needed</div></div>
        <div class="metric-card"><div class="metric-label">AVG RATING</div><div class="metric-value" id="driversAvgRating">--</div><div class="metric-delta neutral">Visible page</div></div>
      </div>
      <div class="panel-search">
        <input id="driverSearch" placeholder="Search name, state…" oninput="queueDriverSearch(this.value)">
        <select class="filter-select" id="driverStatusFilter" onchange="setDriverStatusFilter(this.value)"><option value="all">All statuses</option><option value="active">Active</option><option value="suspended">Suspended</option></select>
        <button onclick="loadDrivers(1)">Search</button>
      </div>
      <!-- Bulk action bar — hidden until at least one driver is selected -->
      <div id="driverBulkBar" style="display:none;align-items:center;gap:10px;padding:10px 14px;background:var(--surface-1);border-radius:10px;margin-bottom:12px;border:1px solid var(--surface-2)">
        <span id="driverSelectedCount" style="font-size:13px;font-weight:700;color:var(--info)">0 selected</span>
        <div style="flex:1"></div>
        <select class="filter-select" id="driverBulkActionSelect" style="font-size:12px;padding:6px 10px;height:auto">
          <option value="">Bulk Actions…</option>
          <option value="suspend">Suspend</option>
          <option value="reinstate">Reinstate</option>
          <option value="send_notification">Send Notification</option>
          <option value="export">Export Selected</option>
        </select>
        <button class="btn-primary" style="font-size:12px;padding:7px 14px;white-space:nowrap" onclick="executeBulkDriverAction(document.getElementById('driverBulkActionSelect').value)">Apply</button>
        <button style="font-size:12px;padding:7px 10px;background:none;border:1.5px solid var(--surface-2);border-radius:9px;cursor:pointer;color:var(--text-secondary)" onclick="clearDriverSelection()">Clear</button>
      </div>

      <div class="scard">
        <div class="scard-header">
          <h3 style="display:flex;align-items:center;gap:10px">
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:500;color:var(--text-secondary);cursor:pointer;white-space:nowrap">
              <input type="checkbox" id="driverSelectAll" onchange="toggleDriverSelectAll(this.checked)"> Select All
            </label>
            Driver Directory
          </h3>
        </div>
        <div id="driverDirectory"></div><div class="pagination" id="driverPagination"></div>
      </div>
    </div>

    <!-- BLACKLIST SECTION -->
    <div id="blacklistSection" style="display:none;margin-top:0">
      <div class="scard" style="margin-top:16px">
        <div class="scard-header"><h3>Add to Blacklist</h3></div>
        <div style="padding:16px">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Identifier Type</label>
              <select class="form-input" id="blacklistType">
                <option value="phone">Phone Number</option>
                <option value="email">Email Address</option>
                <option value="device_id">Device ID</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Identifier Value</label>
              <input class="form-input" id="blacklistValue" placeholder="Phone number, email, or device ID">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Reason for Blacklisting</label>
            <textarea class="form-input" id="blacklistReason" rows="2" placeholder="Explain why this identifier is being permanently banned"></textarea>
          </div>
          <button class="btn-primary" style="font-size:12px;padding:8px 14px;width:auto" onclick="addToBlacklist()">Add to Blacklist</button>
        </div>
      </div>
      <div class="scard" style="margin-top:16px">
        <div class="scard-header"><h3>Blacklisted Identifiers</h3><button class="scard-action" onclick="loadBlacklist()">Refresh</button></div>
        <div id="blacklistDirectory"></div>
      </div>
    </div>
  </div>

<!-- Driver Create / Edit Slide Panel -->
<div class="slide-panel-overlay" id="driverSlidePanelOverlay" onclick="closeDriverPanel()" style="display:none;"></div>
<div class="slide-panel" id="driverSlidePanel" style="display:none;">
  <div class="slide-panel-header">
    <div>
      <h3 id="driverSlideTitle">Add Driver Account</h3>
      <div style="font-size:11px;color:var(--text-muted);margin-top:2px;" id="driverSlideSubtitle">Fill in the details below</div>
    </div>
    <button class="close-slide-btn" onclick="closeDriverPanel()" aria-label="Close">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <div class="slide-panel-body">
    <form id="driverForm" onsubmit="saveDriver(event)" novalidate>
      <input type="hidden" id="driverEditId" value="">

      <!-- Basic Info -->
      <div class="slide-section-label">Basic Information</div>
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Full Name <span class="req-star">*</span></label>
          <input type="text" class="form-input" id="driverFormFullName" placeholder="e.g. Chukwuemeka Obi" autocomplete="name" required>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Phone Number <span class="req-star">*</span></label>
          <input type="tel" class="form-input" id="driverFormPhone" placeholder="e.g. 08012345678" autocomplete="tel" required>
        </div>
      </div>

      <div class="form-group" id="driverFormEmailGroup">
        <label class="form-label">Email Address <span class="req-star">*</span></label>
        <input type="email" class="form-input" id="driverFormEmail" placeholder="driver@example.com" autocomplete="email">
        <div class="form-hint">Used for login and notifications. Cannot be changed after creation.</div>
      </div>

      <div class="form-group" id="driverFormPasswordGroup">
        <label class="form-label">Password <span class="req-star">*</span></label>
        <div class="pw-input-wrap">
          <input type="password" class="form-input" id="driverFormPassword" placeholder="Min. 6 characters" autocomplete="new-password">
          <button type="button" class="pw-toggle-btn" onclick="togglePwVisibility('driverFormPassword',this)" aria-label="Show password">
            <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Vehicle Type <span class="req-star">*</span></label>
        <select class="form-input" id="driverFormVehicleType" required>
          <option value="bike">Bike</option>
          <option value="car">Car</option>
          <option value="keke">Keke (Tricycle)</option>
          <option value="van">Van</option>
        </select>
      </div>

      <!-- Personal Details -->
      <div class="slide-section-label" style="margin-top:4px;">Personal Details <span style="font-weight:400;color:var(--text-muted)">(optional)</span></div>
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Middle Name</label>
          <input type="text" class="form-input" id="driverFormMiddleName" placeholder="Middle name">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Date of Birth</label>
          <input type="date" class="form-input" id="driverFormDob">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Gender</label>
          <select class="form-input" id="driverFormGender">
            <option value="">Select gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Language</label>
          <select class="form-input" id="driverFormLanguage">
            <option value="English">English</option>
            <option value="Yoruba">Yoruba</option>
            <option value="Igbo">Igbo</option>
            <option value="Hausa">Hausa</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">State of Origin</label>
        <select class="form-input" id="driverFormState">
          <option value="">Select state</option>
          <option>Abia</option><option>Adamawa</option><option>Akwa Ibom</option><option>Anambra</option>
          <option>Bauchi</option><option>Bayelsa</option><option>Benue</option><option>Borno</option>
          <option>Cross River</option><option>Delta</option><option>Ebonyi</option><option>Edo</option>
          <option>Ekiti</option><option>Enugu</option><option>FCT</option><option>Gombe</option>
          <option>Imo</option><option>Jigawa</option><option>Kaduna</option><option>Kano</option>
          <option>Katsina</option><option>Kebbi</option><option>Kogi</option><option>Kwara</option>
          <option>Lagos</option><option>Nasarawa</option><option>Niger</option><option>Ogun</option>
          <option>Ondo</option><option>Osun</option><option>Oyo</option><option>Plateau</option>
          <option>Rivers</option><option>Sokoto</option><option>Taraba</option><option>Yobe</option>
          <option>Zamfara</option>
        </select>
      </div>

      <!-- Vehicle Details — edit mode only -->
      <div id="driverFormVehicleSection" style="display:none;">
        <div class="slide-section-label">Vehicle Details</div>
        <div class="form-row">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Make / Model</label>
            <input type="text" class="form-input" id="driverFormVehicleModel" placeholder="e.g. Honda CG125">
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Year</label>
            <input type="text" class="form-input" id="driverFormVehicleYear" placeholder="e.g. 2021" maxlength="4" inputmode="numeric">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Plate Number</label>
            <input type="text" class="form-input" id="driverFormVehiclePlate" placeholder="e.g. LAG-123AB" style="text-transform:uppercase">
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Colour</label>
            <input type="text" class="form-input" id="driverFormVehicleColor" placeholder="e.g. Red">
          </div>
        </div>
      </div>

      <!-- Banking — edit mode only -->
      <div id="driverFormBankSection" style="display:none;">
        <div class="slide-section-label">Bank Account</div>
        <div class="form-group">
          <label class="form-label">Bank Name</label>
          <input type="text" class="form-input" id="driverFormBankName" placeholder="e.g. Access Bank">
        </div>
        <div class="form-row">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Account Holder Name</label>
            <input type="text" class="form-input" id="driverFormAccountHolder" placeholder="Name on account">
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Account Number</label>
            <input type="text" class="form-input" id="driverFormAccountNumber" placeholder="10-digit NUBAN" maxlength="10" inputmode="numeric">
          </div>
        </div>
      </div>

      <!-- Emergency Contact — edit mode only -->
      <div id="driverFormEmergencySection" style="display:none;">
        <div class="slide-section-label">Emergency Contact</div>
        <div class="form-row">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Contact Name</label>
            <input type="text" class="form-input" id="driverFormEmergencyName" placeholder="Full name">
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Relationship</label>
            <input type="text" class="form-input" id="driverFormEmergencyRelationship" placeholder="e.g. Spouse">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Phone</label>
            <input type="tel" class="form-input" id="driverFormEmergencyPhone" placeholder="Phone number">
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Address</label>
            <input type="text" class="form-input" id="driverFormEmergencyAddress" placeholder="Home address">
          </div>
        </div>
      </div>

      <div class="slide-panel-footer">
        <button type="button" class="btn-secondary" onclick="closeDriverPanel()">Cancel</button>
        <button type="submit" class="btn-primary" id="driverSaveBtn">Add Driver</button>
      </div>
    </form>
  </div>
</div>
