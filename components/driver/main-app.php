<div class="screen <?php echo $driver_initial_context['is_approved'] ? 'active' : ''; ?>" id="screen-driver-dash">

    <!-- Sidebar (desktop only) -->
    <div class="dash-sidebar">
      <div style="font-family:'Syne',sans-serif;font-size:13px;font-weight:800;color:var(--gold);margin-bottom:20px;letter-spacing:0.5px">SD</div>
      <div class="dash-sidebar-icon active" onclick="switchTab('home')" title="Home">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      </div>
      <div class="dash-sidebar-icon" onclick="switchTab('earnings')" title="Earnings">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      </div>
      <div class="dash-sidebar-icon" onclick="switchTab('trips')" title="Trips">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M9 20l-5.447-2.724A1 1 0 0 1 3 16.382V5.618a1 1 0 0 1 1.447-.894L9 7M9 20l6-3M9 20V7m6 13l4.553 2.276A1 1 0 0 0 21 21.382V10.618a1 1 0 0 0-.553-.894L15 7m0 13V7M9 7l6-3"/></svg>
      </div>
      <div class="dash-sidebar-icon" onclick="switchTab('help')" title="Help">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div style="flex:1"></div>
      <div class="dash-sidebar-icon" onclick="switchTab('profile')" title="Profile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
    </div>

    <!-- Main content -->
    <div class="dash-main">
      <div class="dash-content-scroll" id="dashBody">

        <div class="driver-dash-body">

          <!-- HOME TAB -->
          <div class="dash-panel active" id="panel-home">

            <!-- Clean, minimal Top Bar restricted to Home Tab -->
            <div class="home-top-bar">
              <div class="dash-top-left">
                <img class="dash-avatar-img" src="https://app.oaglobalstandardservice.com/wp/wp-content/uploads/2026/04/1725853367655.jpg" alt="Profile">
                <div>
                  <div class="dash-greeting" id="dashGreeting">Good afternoon,</div>
                  <div class="dash-name">Chidi Nwosu 👋</div>
                </div>
              </div>
              <div class="online-pill online" id="onlineToggle" onclick="toggleOnline()">
                <span class="online-status-dot"></span>
                <span id="onlineLabel">Online</span>
              </div>
            </div>

            <!-- Incoming request -->
            <div id="driverOfferContainer">
              <div class="trip-request-card" id="driverNoOfferCard">
                <div class="trq-header">
                  <div class="trq-tag">Dispatch</div>
                </div>
                <div class="trq-fee">No live requests <span>· stay online</span></div>
                <div class="trq-meta">
                  <div class="trq-meta-chip">Waiting for nearby bookings</div>
                </div>
              </div>
            </div>

            <!-- Quick stats -->
            <div class="stat-row">
              <div class="stat-chip">
                <div class="stat-chip-val" id="home-today-earnings">₦0</div>
                <div class="stat-chip-label">Today</div>
              </div>
              <div class="stat-chip">
                <div class="stat-chip-val" id="home-today-trips">0</div>
                <div class="stat-chip-label">Trips</div>
              </div>
              <div class="stat-chip">
                <div class="stat-chip-val" id="home-rating">0.0★</div>
                <div class="stat-chip-label">Rating</div>
              </div>
            </div>

            <!-- Campaign -->
            <div id="home-active-campaigns">
                <!-- Dynamically populated via JS -->
            </div>

            <!-- Map container -->
            <div id="driver-map-container" style="height: 200px; border-radius:var(--radius-lg); margin-bottom:16px; box-shadow:var(--shadow-md); z-index: 1;">
            </div>
          </div>

          <!-- EARNINGS TAB -->
          <div class="dash-panel" id="panel-earnings">
            <div class="earnings-card">
              <div class="earnings-top">
                <div class="earnings-label">This week's earnings</div>
                <div class="earnings-period">Mon – Sun</div>
              </div>
              <div class="earnings-amount" id="earnings-week-total-container"><span>₦</span><span id="earnings-week-total">0</span></div>
              <div class="earnings-row">
                <div class="earnings-sub">
                  <div class="earnings-sub-label">Today</div>
                  <div class="earnings-sub-val" id="earnings-today-total">₦0</div>
                </div>
                <div class="earnings-sub">
                  <div class="earnings-sub-label">Trips</div>
                  <div class="earnings-sub-val" id="earnings-week-trips">0</div>
                </div>
                <div class="earnings-sub">
                  <div class="earnings-sub-label">Rating</div>
                  <div class="earnings-sub-val" style="display:flex;align-items:center;gap:4px">
                      <span id="earnings-rating-text">0.0</span>
                      <span id="earnings-rating-stars" style="display:flex;gap:2px;color:var(--gold)"></span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Weekly bar chart -->
            <div class="card">
              <div class="card-title">Weekly breakdown</div>
              <div class="week-chart" id="weekly-bar-chart">
                  <!-- Bars will be generated via JS -->
              </div>
              <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted)">
                <span>₦0</span><span id="weekly-chart-max">₦0</span>
              </div>
            </div>

            <!-- Active campaign -->
            <div class="card">
              <div class="card-title">Active Campaigns</div>
              <div id="earnings-active-campaigns">
                 <!-- Dynamically populated via JS -->
              </div>
            </div>

          </div>

          <!-- TRIPS TAB -->
          <div class="dash-panel" id="panel-trips">
            <div class="section-head">
              <div class="section-head-title">Trip History</div>
              <div class="section-head-link" onclick="openModal('modal-filter-trips')" style="cursor:pointer">Filter</div>
            </div>

            <!-- Trip items -->
            <div id="trip-history-list">
                <!-- Populated via JS -->
            </div>
          </div>

          <!-- HELP TAB -->
          <div class="dash-panel" id="panel-help">
            <div class="section-head" style="margin-bottom:16px">
              <div class="section-head-title">Driver Help</div>
            </div>
            <div class="info-note" style="margin-bottom:20px">Select a recent trip below to get targeted support, or browse help topics.</div>

            <div class="card">
              <div class="card-title">Get help for a specific trip</div>
              <div class="trip-history-item" style="margin-bottom:8px" onclick="showToast('Support ticket opened for TRP-00142')">
                <div class="trip-icon completed">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="trip-details">
                  <div class="trip-route">#TRP-00142 · Eagle Island → Rumuola Rd</div>
                  <div class="trip-meta">Today · ₦3,400</div>
                </div>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
              </div>
              <div class="trip-history-item" style="margin-bottom:0" onclick="showToast('Support ticket opened for TRP-00141')">
                <div class="trip-icon completed">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="trip-details">
                  <div class="trip-route">#TRP-00141 · GRA Phase 2 → Trans Amadi</div>
                  <div class="trip-meta">Today · ₦2,100</div>
                </div>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
              </div>
            </div>

            <div class="section-head">
              <div class="section-head-title">Help topics</div>
            </div>
            <div class="help-category" onclick="showToast('Opening: Payments & Earnings help')">
              <div class="help-cat-icon" style="background:rgba(34,196,122,0.1);color:var(--success)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              </div>
              <div class="help-cat-text">
                <div class="help-cat-title">Payments & Earnings</div>
                <div class="help-cat-sub">Payout issues, missing earnings, bank details</div>
              </div>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
            </div>
            <div class="help-category" onclick="showToast('Opening: Trip issues help')">
              <div class="help-cat-icon" style="background:var(--info-pale);color:var(--info)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M9 20l-5.447-2.724A1 1 0 0 1 3 16.382V5.618a1 1 0 0 1 1.447-.894L9 7M9 20l6-3M9 20V7m6 13l4.553 2.276A1 1 0 0 0 21 21.382V10.618a1 1 0 0 0-.553-.894L15 7m0 13V7M9 7l6-3"/></svg>
              </div>
              <div class="help-cat-text">
                <div class="help-cat-title">Trip & Delivery issues</div>
                <div class="help-cat-sub">Wrong route, item damaged, dispute with customer</div>
              </div>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
            </div>
            <div class="help-category" onclick="showToast('Opening: Account & documents help')">
              <div class="help-cat-icon" style="background:var(--gold-pale);color:var(--gold-dark)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </div>
              <div class="help-cat-text">
                <div class="help-cat-title">Account & Documents</div>
                <div class="help-cat-sub">KYC update, vehicle change, profile issues</div>
              </div>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
            </div>
            <div class="help-category" onclick="showToast('Opening: Safety & emergency help')">
              <div class="help-cat-icon" style="background:rgba(232,72,74,0.08);color:var(--danger)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              </div>
              <div class="help-cat-text">
                <div class="help-cat-title">Safety & Emergency</div>
                <div class="help-cat-sub">Report an incident, emergency support</div>
              </div>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
            </div>
            <div class="help-category" onclick="openModal('modal-faq')">
              <div class="help-cat-icon" style="background:rgba(100,100,100,0.1);color:var(--text-primary)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              </div>
              <div class="help-cat-text">
                <div class="help-cat-title">FAQs</div>
                <div class="help-cat-sub">Frequently asked questions</div>
              </div>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--text-muted)"><path d="M9 18l6-6-6-6"/></svg>
            </div>
          </div>

          <!-- PROFILE TAB -->
          <div class="dash-panel" id="panel-profile">
            <div class="profile-header">
              <div class="profile-avatar-lg" onclick="document.getElementById('profileAvatarInput').click()">
                <div class="avatar-initials" id="profileAvatarInitials" style="display:none;"></div>
                <img src="" alt="Profile Image" id="profileAvatarImg">
                <input type="file" id="profileAvatarInput" style="display:none;" accept="image/jpeg, image/png, image/webp" onchange="uploadDriverAvatar(event)">
                <div class="avatar-edit-badge">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                </div>
              </div>
              <div class="profile-name" id="profileNameDisplay">Driver Name</div>
              <div class="profile-rating" id="profileStatsDisplay">
                <span class="profile-star">★★★★★</span>
                0.0 · 0 trips total
              </div>
              <div style="margin-top:16px;display:flex;gap:10px">
                <div style="background:rgba(34,196,122,0.15);border:1px solid rgba(34,196,122,0.25);color:var(--success);font-size:12px;font-weight:600;padding:5px 14px;border-radius:20px" id="profileVerifyBadge">✓ Verified</div>
                <div style="background:rgba(245,200,66,0.1);border:1px solid rgba(245,200,66,0.2);color:var(--gold);font-size:12px;font-weight:600;padding:5px 14px;border-radius:20px; text-transform: capitalize;" id="profileVehicleBadge">Vehicle</div>
              </div>
            </div>

            <div class="profile-row" onclick="openModal('modal-edit-personal')">
              <div class="profile-row-left">
                <div class="profile-row-icon" style="background:var(--info-pale);color:var(--info)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div>
                  <div class="profile-row-label">Personal Info</div>
                  <div class="profile-row-sub" id="profilePersonalSub">Name, Phone</div>
                </div>
              </div>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </div>

            <div class="profile-row" onclick="openModal('modal-edit-bank')">
              <div class="profile-row-left">
                <div class="profile-row-icon" style="background:rgba(34,196,122,0.1);color:var(--success)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                </div>
                <div>
                  <div class="profile-row-label">Bank Details</div>
                  <div class="profile-row-sub" id="profileBankSub">Bank Name · Account</div>
                </div>
              </div>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </div>

            <div class="profile-row" onclick="showToast('Vehicle documents can only be updated via support at this time.')">
              <div class="profile-row-left">
                <div class="profile-row-icon" style="background:var(--gold-pale);color:var(--gold-dark)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="1" y="6" width="15" height="10" rx="2"/><polygon points="16 9 20 9 23 13 23 16 16 16 16 9"/><circle cx="5.5" cy="18.5" r="2"/><circle cx="18.5" cy="18.5" r="2"/></svg>
                </div>
                <div>
                  <div class="profile-row-label">Vehicle Documents</div>
                  <div class="profile-row-sub">License, Inspection Report</div>
                </div>
              </div>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </div>

            <div class="profile-row" onclick="openModal('modal-edit-emergency')">
              <div class="profile-row-left">
                <div class="profile-row-icon" style="background:rgba(232,72,74,0.08);color:var(--danger)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13 19.79 19.79 0 0 1 1.61 4.37 2 2 0 0 1 3.6 2h3a2 2 0 0 1 2 1.72c.127.96.36 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.16 6.16l.97-.86a2 2 0 0 1 2.11-.45c.907.34 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </div>
                <div>
                  <div class="profile-row-label">Emergency Contact</div>
                  <div class="profile-row-sub" id="profileEmergencySub">Contact Name · Phone</div>
                </div>
              </div>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </div>

            <div style="margin-top:8px">
              <button class="global-btn ghost" style="width:100%;justify-content:center" onclick="window.location.href = window.idibiaLogoutUrl">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Log Out
              </button>
            </div>
          </div>

        </div><!-- end driver-dash-body -->
      </div><!-- end dashBody scroll wrapper -->

      <!-- Profile Edit Modals -->
      <div class="modal" id="modal-edit-personal" onclick="closeModal('modal-edit-personal')">
        <div class="modal-content" onclick="event.stopPropagation()">
          <h3>Edit Personal Info</h3>
          <p class="modal-sub">Update your personal information below.</p>
          <form onsubmit="submitProfileForm(event, 'update_personal')">
            <div class="form-group">
              <label class="form-label">Full Name</label>
              <input type="text" class="form-input" name="full_name" id="inputProfileName" required>
            </div>
            <div class="form-group">
              <label class="form-label">Phone Number</label>
              <input type="tel" class="form-input" name="phone" id="inputProfilePhone" required>
            </div>
            <div style="display:flex;gap:12px;margin-top:24px">
              <button type="button" class="global-btn ghost" style="flex:1;justify-content:center" onclick="closeModal('modal-edit-personal')">Cancel</button>
              <button type="submit" class="global-btn" style="flex:1;justify-content:center">Save Changes</button>
            </div>
          </form>
        </div>
      </div>

      <div class="modal" id="modal-edit-bank" onclick="closeModal('modal-edit-bank')">
        <div class="modal-content" onclick="event.stopPropagation()">
          <h3>Edit Bank Details</h3>
          <p class="modal-sub">Update where you receive your payouts.</p>
          <form onsubmit="submitProfileForm(event, 'update_bank')">
            <div class="form-group">
              <label class="form-label">Bank Name</label>
              <input type="text" class="form-input" name="bank_name" id="inputProfileBankName" required>
            </div>
            <div class="form-group">
              <label class="form-label">Account Number</label>
              <input type="text" class="form-input" name="account_number" id="inputProfileAccountNumber" required>
            </div>
            <div style="display:flex;gap:12px;margin-top:24px">
              <button type="button" class="global-btn ghost" style="flex:1;justify-content:center" onclick="closeModal('modal-edit-bank')">Cancel</button>
              <button type="submit" class="global-btn" style="flex:1;justify-content:center">Save Changes</button>
            </div>
          </form>
        </div>
      </div>

      <div class="modal" id="modal-edit-emergency" onclick="closeModal('modal-edit-emergency')">
        <div class="modal-content" onclick="event.stopPropagation()">
          <h3>Edit Emergency Contact</h3>
          <p class="modal-sub">Who should we contact in an emergency?</p>
          <form onsubmit="submitProfileForm(event, 'update_emergency')">
            <div class="form-group">
              <label class="form-label">Contact Name</label>
              <input type="text" class="form-input" name="emergency_name" id="inputProfileEmergencyName" required>
            </div>
            <div class="form-group">
              <label class="form-label">Contact Phone</label>
              <input type="tel" class="form-input" name="emergency_phone" id="inputProfileEmergencyPhone" required>
            </div>
            <div style="display:flex;gap:12px;margin-top:24px">
              <button type="button" class="global-btn ghost" style="flex:1;justify-content:center" onclick="closeModal('modal-edit-emergency')">Cancel</button>
              <button type="submit" class="global-btn" style="flex:1;justify-content:center">Save Changes</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Trips Filter Modal -->
      <div class="modal modal-overlay" id="modal-filter-trips" onclick="closeModal('modal-filter-trips')">
        <div class="modal-content" onclick="event.stopPropagation()">
          <h3>Filter Trips</h3>
          <p class="modal-sub">Filter your trip history by status and date.</p>
          <div class="form-group" style="margin-top:16px;">
            <label>Trip Status</label>
            <select class="global-input" id="tripFilterSelect">
                <option value="all">All Trips</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="form-group" style="margin-top:16px;">
            <label>Date Range</label>
            <div style="display:flex;gap:8px">
              <input type="date" class="global-input" id="tripFilterDateStart" placeholder="Start Date">
              <input type="date" class="global-input" id="tripFilterDateEnd" placeholder="End Date">
            </div>
          </div>
          <div style="display:flex;gap:12px;margin-top:24px">
            <button type="button" class="global-btn ghost" style="flex:1;justify-content:center" onclick="closeModal('modal-filter-trips')">Cancel</button>
            <button type="button" class="global-btn" style="flex:1;justify-content:center" onclick="applyTripFilters()">Apply Filters</button>
          </div>
        </div>
      </div>

      <!-- BOTTOM NAV (mobile only) -->
      <nav class="bottom-nav">
        <div class="nav-item active" id="nav-home" onclick="switchTab('home')">
          <div class="nav-icon-wrap">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          </div>
          <span class="nav-label">Home</span>
        </div>
        <div class="nav-item" id="nav-earnings" onclick="switchTab('earnings')">
          <div class="nav-icon-wrap">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
          <span class="nav-label">Earnings</span>
        </div>
        <div class="nav-item" id="nav-trips" onclick="switchTab('trips')">
          <div class="nav-icon-wrap">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 20l-5.447-2.724A1 1 0 0 1 3 16.382V5.618a1 1 0 0 1 1.447-.894L9 7M9 20l6-3M9 20V7m6 13l4.553 2.276A1 1 0 0 0 21 21.382V10.618a1 1 0 0 0-.553-.894L15 7m0 13V7M9 7l6-3"/></svg>
          </div>
          <span class="nav-label">Trips</span>
        </div>
        <div class="nav-item" id="nav-help" onclick="switchTab('help')">
          <div class="nav-icon-wrap">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div class="nav-badge">1</div>
          </div>
          <span class="nav-label">Help</span>
        </div>
        <div class="nav-item" id="nav-profile" onclick="switchTab('profile')">
          <div class="nav-icon-wrap">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <span class="nav-label">Profile</span>
        </div>
      </nav>
    </div><!-- end dash-main -->
  </div><!-- end screen-driver-dash -->