<div class="screen tab-home <?php echo $driver_initial_context['is_approved'] ? 'active' : ''; ?>" id="screen-driver-dash">

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
      <div style="flex:1"></div>
      <div class="dash-sidebar-icon" onclick="switchTab('profile')" title="Profile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
    </div>

    <!-- Main content -->
    <div class="dash-main">

      <!-- ═══ HOME TAB ═══
           Lives directly inside .dash-main — NOT inside the scroll wrapper.
           This guarantees zero overflow-clipping ancestors between the screen
           root and the map container. -->
      <div class="dash-panel map-home active" id="panel-home">

        <!-- Full-bleed live map (position:absolute; inset:0 fills the panel) -->
        <div id="driver-map-container" class="driver-map-fullscreen"></div>

        <!-- Floating identity header: avatar · greeting · online toggle -->
        <?php
          $__is_online    = !empty($driver_initial_context['is_online']);
          $__full_name    = $driver_initial_context['full_name'] ?? '';
          $__avatar_raw   = $driver_initial_context['avatar_path'] ?? ($driver_initial_context['selfie_path'] ?? '');
          $__base_url     = $driver_initial_context['upload_baseurl'] ?? '';
          if ($__avatar_raw) {
            $__avatar_url = (strpos($__avatar_raw, 'http') === 0) ? $__avatar_raw : ($__base_url ? "{$__base_url}/{$__avatar_raw}" : "/{$__avatar_raw}");
          } else {
            $__avatar_url = '';
          }
          $__name_parts = array_values(array_filter(explode(' ', trim($__full_name))));
          $__initials   = '';
          if ($__name_parts) {
            $__initials .= strtoupper(substr($__name_parts[0], 0, 1));
            if (count($__name_parts) > 1) $__initials .= strtoupper(substr(end($__name_parts), 0, 1));
          }
          if (!$__initials) $__initials = '?';
        ?>
        <div class="map-top-overlay">
          <div class="driver-id-left">
            <div class="driver-id-avatar">
              <img src="<?php echo esc_attr($__avatar_url); ?>" alt="Profile" id="dashHomeAvatar"<?php echo $__avatar_url ? '' : ' style="display:none;"'; ?> onerror="this.style.display='none'; document.getElementById('dashHomeAvatarInitials').style.display='flex';">
              <span id="dashHomeAvatarInitials"<?php echo !$__avatar_url ? '' : ' style="display:none;"'; ?>><?php echo esc_html($__initials); ?></span>
            </div>
            <div class="driver-id-text">
              <div class="dash-greeting" id="dashGreeting">Good afternoon,</div>
              <div class="dash-name" id="dashHomeName"><?php echo esc_html($__full_name); ?></div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:10px">
            <!-- Notification Bell (driver) -->
            <div style="position:relative">
              <button id="driverNotifBellBtn" onclick="toggleDriverNotifDropdown()" title="Notifications" style="background:rgba(255,255,255,0.15);border:none;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;position:relative">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span id="driverNotifBadge" style="display:none;position:absolute;top:2px;right:2px;background:#e53e3e;color:#fff;font-size:10px;font-weight:700;min-width:14px;height:14px;border-radius:7px;padding:0 3px;line-height:14px;text-align:center"></span>
              </button>
            </div>
            <div class="online-switch-wrap">
              <span class="online-switch-label" id="onlineLabel"><?php echo $__is_online ? 'Online' : 'Offline'; ?></span>
              <button type="button" class="online-switch<?php echo $__is_online ? ' online' : ''; ?>" id="onlineToggle" role="switch" aria-checked="<?php echo $__is_online ? 'true' : 'false'; ?>" aria-label="Toggle online status" onclick="toggleOnline()">
                <span class="online-switch-thumb"></span>
              </button>
            </div>
          </div>

          <!-- Driver Notification Dropdown -->
          <div id="driverNotifDropdown" style="display:none;position:fixed;top:64px;right:16px;width:300px;max-height:400px;background:var(--white,#fff);border:1px solid rgba(0,0,0,0.12);border-radius:8px;z-index:2000;box-shadow:0 8px 24px rgba(0,0,0,0.2);display:none;flex-direction:column;overflow:hidden">
            <div style="padding:12px 16px;border-bottom:1px solid rgba(0,0,0,0.08);display:flex;justify-content:space-between;align-items:center">
              <strong style="font-size:14px;color:#1a1a2e">Notifications</strong>
              <button onclick="markAllDriverNotifRead()" style="font-size:12px;color:#6c63ff;background:none;border:none;cursor:pointer;padding:0">Mark all read</button>
            </div>
            <div id="driverNotifList" style="overflow-y:auto;max-height:340px;padding:8px 0">
              <div style="padding:16px;color:#666;font-size:13px">Loading…</div>
            </div>
          </div>
        </div>

        <!-- Incoming request overlay -->
        <div id="driverOfferContainer" class="map-offer-overlay">
          <div class="dispatch-idle" id="driverNoOfferCard">
            <span class="dispatch-idle-dot"></span>
            <div class="dispatch-idle-text">
              <strong>No live requests</strong>
              <span>Waiting for nearby bookings · stay online</span>
            </div>
          </div>
        </div>

        <!-- Bottom sheet: today's snapshot + campaigns -->
        <div class="map-bottom-sheet" id="homeBottomSheet">
          <div class="sheet-handle" onclick="toggleHomeSheet()"></div>
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
          <div id="home-active-campaigns" class="sheet-campaigns">
              <!-- Dynamically populated via JS -->
          </div>
        </div>
      </div><!-- end panel-home -->

      <!-- ═══ SCROLLABLE PANELS (earnings / trips / profile) ═══
           Separate from the home panel so overflow:auto never affects the map. -->
      <div class="dash-content-scroll" id="dashBody">
        <div class="driver-dash-body">

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
                  <div class="earnings-sub-val" id="earnings-rating-text">0.0<span style="color:var(--gold);font-size:14px;margin-left:2px;">★</span></div>
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

            <!-- Wallet & Payouts -->
            <div class="card" style="margin-top:20px;">
              <div class="card-title">Wallet & Payouts</div>
              <div style="background:var(--surface); border-radius:12px; padding:16px; display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <div>
                  <div style="font-size:13px; color:var(--text-muted); margin-bottom:4px;">Available Balance</div>
                  <div style="font-size:24px; font-weight:700;" id="wallet-balance-display">₦0</div>
                </div>
                <button class="global-btn" style="padding:8px 16px; font-size:14px;" onclick="openPayoutModal()">Request Payout</button>
              </div>

              <div class="section-head" style="margin-bottom:12px;">
                <div class="section-head-title" style="font-size:14px;">Recent Transactions</div>
              </div>
              <div id="wallet-ledger-list">
                <!-- Ledger entries via JS -->
                <div class="info-note">Loading transactions...</div>
              </div>

              <div class="section-head" style="margin-top:24px; margin-bottom:12px;">
                <div class="section-head-title" style="font-size:14px;">Payout History</div>
              </div>
              <div id="wallet-payouts-list">
                <!-- Payout entries via JS -->
                <div class="info-note">Loading payouts...</div>
              </div>
            </div>

          </div><!-- end panel-earnings -->

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
          </div><!-- end panel-trips -->

          <!-- PROFILE TAB -->
          <div class="dash-panel" id="panel-profile">
            <div class="profile-header">
              <div class="profile-avatar-lg" onclick="document.getElementById('profileAvatarInput').click()">
                <div class="avatar-initials" id="profileAvatarInitials" style="display:none;"></div>
                <img src="" alt="Profile Image" id="profileAvatarImg" style="display:none;" onerror="this.style.display='none'; document.getElementById('profileAvatarInitials').style.display='flex';">
                <input type="file" id="profileAvatarInput" style="display:none;" accept="image/jpeg, image/png, image/webp" onchange="uploadDriverAvatar(event)">
                <div class="avatar-edit-badge">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                </div>
              </div>
              <div class="profile-name" id="profileNameDisplay"><?php echo esc_html($driver_row['full_name'] ?? 'Driver Name'); ?></div>
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

            <!-- Help & Support -->
            <div class="section-head" style="margin-top:28px;margin-bottom:8px">
              <div class="section-head-title">Help & Support</div>
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

            <div style="margin-top:20px">
              <button class="global-btn ghost" style="width:100%;justify-content:center" onclick="doDriverLogout()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Log Out
              </button>
            </div>
          </div><!-- end panel-profile -->

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

      <!-- Request Payout Modal -->
      <div class="modal" id="modal-request-payout" onclick="closeModal('modal-request-payout')">
        <div class="modal-content" onclick="event.stopPropagation()">
          <h3>Request Payout</h3>
          <p class="modal-sub">Transfer funds to your bank account.</p>
          <div id="payout-bank-info" style="background:var(--surface); border-radius:8px; padding:12px; margin-bottom:16px; font-size:14px;">
            <!-- Populated via JS -->
          </div>
          <form onsubmit="requestPayout(event)">
            <div class="form-group">
              <label class="form-label">Amount (₦)</label>
              <input type="number" step="0.01" class="form-input" id="inputPayoutAmount" required>
            </div>
            <div style="display:flex;gap:12px;margin-top:24px">
              <button type="button" class="global-btn ghost" style="flex:1;justify-content:center" onclick="closeModal('modal-request-payout')">Cancel</button>
              <button type="submit" class="global-btn" style="flex:1;justify-content:center" id="btnRequestPayout">Withdraw</button>
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
              <label class="form-label">Account Name (Primary)</label>
              <input type="text" class="form-input" name="account_holder_name" id="inputProfileAccountHolder" required>
            </div>
            <div class="form-group">
              <label class="form-label">Bank Name (Primary)</label>
              <input type="text" class="form-input" name="bank_name" id="inputProfileBankName" required>
            </div>
            <div class="form-group">
              <label class="form-label">Account Number (Primary)</label>
              <input type="text" class="form-input" name="account_number" id="inputProfileAccountNumber" required>
            </div>
            <hr style="margin:20px 0; border: none; border-top: 1px solid var(--surface-3);">
            <div class="form-group">
              <label class="form-label">Account Name (Secondary - Optional)</label>
              <input type="text" class="form-input" name="secondary_account_holder" id="inputProfileSecondaryAccountHolder">
            </div>
            <div class="form-group">
              <label class="form-label">Bank Name (Secondary - Optional)</label>
              <input type="text" class="form-input" name="secondary_bank_name" id="inputProfileSecondaryBankName">
            </div>
            <div class="form-group">
              <label class="form-label">Account Number (Secondary - Optional)</label>
              <input type="text" class="form-input" name="secondary_account_number" id="inputProfileSecondaryAccountNumber">
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
      <div class="modal" id="modal-filter-trips" onclick="closeModal('modal-filter-trips')">
        <div class="modal-content" onclick="event.stopPropagation()">
          <div class="modal-handle"></div>
          <h3>Filter Trips</h3>
          <p class="modal-sub">Filter your trip history by status and date.</p>
          <div class="form-group">
            <label class="form-label">Trip Status</label>
            <select class="form-input" id="tripFilterSelect">
                <option value="all">All Trips</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Date Range</label>
            <div style="display:flex;gap:8px">
              <input type="date" class="form-input" id="tripFilterDateStart">
              <input type="date" class="form-input" id="tripFilterDateEnd">
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
        <div class="nav-item" id="nav-profile" onclick="switchTab('profile')">
          <div class="nav-icon-wrap">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <span class="nav-label">Profile</span>
        </div>
      </nav>
    </div><!-- end dash-main -->
  </div><!-- end screen-driver-dash -->

<script>
/* ── Driver Notification Bell ── */
(function(){
  var open = false;

  window.toggleDriverNotifDropdown = function(){
    var dd = document.getElementById('driverNotifDropdown');
    open = !open;
    dd.style.display = open ? 'flex' : 'none';
    if(open) fetchDriverNotifications();
    document.onclick = open ? function(e){
      var btn=document.getElementById('driverNotifBellBtn');
      var drop=document.getElementById('driverNotifDropdown');
      if(!btn.contains(e.target) && !drop.contains(e.target)){
        open=false; dd.style.display='none'; document.onclick=null;
      }
    } : null;
  };

  function escD(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
  function fmtD(s){ if(!s) return ''; var d=new Date(s.replace(' ','T')); return d.toLocaleString(); }

  function renderDriverList(items){
    var el=document.getElementById('driverNotifList');
    if(!items.length){ el.innerHTML='<div style="padding:16px;color:#666;font-size:13px">No notifications yet.</div>'; return; }
    el.innerHTML=items.map(function(n){
      return '<div onclick="markDriverNotifRead('+n.id+')" style="padding:12px 16px;border-bottom:1px solid rgba(0,0,0,0.06);cursor:pointer;background:'+(n.is_read?'transparent':'rgba(108,99,255,0.05)')+'">'
        +'<div style="font-size:13px;font-weight:'+(n.is_read?'400':'600')+';color:#1a1a2e">'+escD(n.title)+'</div>'
        +'<div style="font-size:12px;color:#666;margin-top:2px">'+escD(n.body)+'</div>'
        +'<div style="font-size:11px;color:#999;margin-top:4px">'+fmtD(n.created_at)+'</div>'
      +'</div>';
    }).join('');
  }

  function updateDriverBadge(count){
    var b=document.getElementById('driverNotifBadge');
    if(count>0){ b.textContent=count>99?'99+':count; b.style.display='inline-block'; }
    else { b.style.display='none'; }
  }

  function fetchDriverNotifications(){
    var fd=new FormData(); fd.append('action','get_notifications');
    fetch('/notifications-api.php',{method:'POST',body:fd,credentials:'include'})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if(d.success){
          updateDriverBadge(d.data.unread_count);
          if(open) renderDriverList(d.data.notifications);
        }
      }).catch(function(){});
  }

  window.markDriverNotifRead = function(id){
    var fd=new FormData(); fd.append('action','mark_read'); fd.append('notification_id',id);
    fetch('/notifications-api.php',{method:'POST',body:fd,credentials:'include'}).then(function(){ fetchDriverNotifications(); });
  };

  window.markAllDriverNotifRead = function(){
    var fd=new FormData(); fd.append('action','mark_read'); fd.append('notification_id','all');
    fetch('/notifications-api.php',{method:'POST',body:fd,credentials:'include'}).then(function(){ fetchDriverNotifications(); });
  };

  // Poll every 30 seconds
  fetchDriverNotifications();
  setInterval(fetchDriverNotifications, 30000);

  // Pusher real-time: bind notification.new on the existing driver private channel.
  // Uses initDriverPusher() (from driver.js) to avoid opening a duplicate WebSocket.
  document.addEventListener('DOMContentLoaded', function(){
    var driverId = window.CURRENT_DRIVER_ID || 0;
    if(!driverId) return;
    function trySubscribe(){
      var pusher = typeof initDriverPusher === 'function' ? initDriverPusher() : null;
      if(!pusher){ setTimeout(trySubscribe, 500); return; }
      var channel = pusher.subscribe('private-driver-' + driverId);
      channel.bind('notification.new', function(data){
        fetchDriverNotifications();
        if(typeof showToast === 'function') showToast(data.title + ': ' + data.body);
      });
    }
    trySubscribe();
  });
})();
</script>
