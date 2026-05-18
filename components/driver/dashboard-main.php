  <!-- ===== DRIVER DASHBOARD ===== -->
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
                <div class="stat-chip-val">₦8,200</div>
                <div class="stat-chip-label">Today</div>
              </div>
              <div class="stat-chip">
                <div class="stat-chip-val">6</div>
                <div class="stat-chip-label">Trips</div>
              </div>
              <div class="stat-chip">
                <div class="stat-chip-val">4.8★</div>
                <div class="stat-chip-label">Rating</div>
              </div>
            </div>

            <!-- Campaign -->
            <div class="campaign-card">
              <div>
                <div class="campaign-badge">⚡ Peak Hour Bonus</div>
                <div class="campaign-text">Complete 3 more trips to unlock a ₦5,000 bonus</div>
              </div>
              <div class="campaign-progress-wrap">
                <div class="campaign-progress-num">2/5</div>
                <div class="campaign-progress-label">done</div>
              </div>
            </div>

            <!-- Map placeholder -->
            <div style="background:linear-gradient(145deg,var(--navy-light),var(--navy));border-radius:var(--radius-lg);padding:28px;text-align:center;border:1px solid rgba(255,255,255,0.07);margin-bottom:16px;box-shadow:var(--shadow-md)">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="1.5" width="36" height="36" style="margin:0 auto 12px"><path d="M9 20l-5.447-2.724A1 1 0 0 1 3 16.382V5.618a1 1 0 0 1 1.447-.894L9 7M9 20l6-3M9 20V7m6 13l4.553 2.276A1 1 0 0 0 21 21.382V10.618a1 1 0 0 0-.553-.894L15 7m0 13V7M9 7l6-3"/></svg>
              <div style="font-family:'Syne',sans-serif;font-size:15px;color:var(--white);margin-bottom:4px">Live Map Active</div>
              <div style="font-size:13px;color:var(--slate-light)">Port Harcourt, Rivers State</div>
            </div>
          </div>

          <!-- EARNINGS TAB -->
          <div class="dash-panel" id="panel-earnings">
            <div class="earnings-card">
              <div class="earnings-top">
                <div class="earnings-label">This week's earnings</div>
                <div class="earnings-period">Mon – Fri</div>
              </div>
              <div class="earnings-amount"><span>₦</span>47,850</div>
              <div class="earnings-row">
                <div class="earnings-sub">
                  <div class="earnings-sub-label">Today</div>
                  <div class="earnings-sub-val up">₦8,200</div>
                </div>
                <div class="earnings-sub">
                  <div class="earnings-sub-label">Trips</div>
                  <div class="earnings-sub-val">23</div>
                </div>
                <div class="earnings-sub">
                  <div class="earnings-sub-label">Rating</div>
                  <div class="earnings-sub-val">4.8 ★</div>
                </div>
              </div>
            </div>

            <!-- Weekly bar chart -->
            <div class="card">
              <div class="card-title">Weekly breakdown</div>
              <div class="week-chart">
                <div class="week-bar-wrap">
                  <div class="week-bar" style="height:45%"></div>
                  <div class="week-day">Mon</div>
                </div>
                <div class="week-bar-wrap">
                  <div class="week-bar" style="height:65%"></div>
                  <div class="week-day">Tue</div>
                </div>
                <div class="week-bar-wrap">
                  <div class="week-bar" style="height:50%"></div>
                  <div class="week-day">Wed</div>
                </div>
                <div class="week-bar-wrap">
                  <div class="week-bar" style="height:80%"></div>
                  <div class="week-day">Thu</div>
                </div>
                <div class="week-bar-wrap">
                  <div class="week-bar active" style="height:55%"></div>
                  <div class="week-day" style="color:var(--gold-dark);font-weight:700">Fri</div>
                </div>
                <div class="week-bar-wrap">
                  <div class="week-bar" style="height:30%"></div>
                  <div class="week-day">Sat</div>
                </div>
                <div class="week-bar-wrap">
                  <div class="week-bar" style="height:20%"></div>
                  <div class="week-day">Sun</div>
                </div>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted)">
                <span>₦0</span><span>₦15k</span>
              </div>
            </div>

            <!-- Active campaign -->
            <div class="card">
              <div class="card-title">Active Campaigns</div>
              <div class="campaign-card" style="margin-bottom:10px">
                <div>
                  <div class="campaign-badge">⚡ Peak Hour</div>
                  <div class="campaign-text">3 more trips = ₦5,000 bonus</div>
                </div>
                <div class="campaign-progress-wrap">
                  <div class="campaign-progress-num">2/5</div>
                  <div class="campaign-progress-label">done</div>
                </div>
              </div>
              <div class="campaign-card">
                <div>
                  <div class="campaign-badge">🌟 Weekend Star</div>
                  <div class="campaign-text">10 trips this weekend = ₦8,000</div>
                </div>
                <div class="campaign-progress-wrap">
                  <div class="campaign-progress-num">0/10</div>
                  <div class="campaign-progress-label">done</div>
                </div>
              </div>
            </div>

            <!-- Tax portal -->
            <div class="card">
              <div class="card-title">
                Tax Portal
                <span class="card-title-action">Learn more</span>
              </div>
              <div class="tax-portal-card" role="button" aria-disabled="true" onclick="showUnavailableFeature('Tax summary', 'Tax report generation is not connected yet. Earnings remain visible in the wallet once payouts are enabled.')">
                <div class="tax-card-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <div class="tax-card-info">
                  <div class="tax-card-title">Q1 2025 Summary</div>
                  <div class="tax-card-sub">Jan – Mar 2025 · Ready to download</div>
                </div>
                <button class="btn-download">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                  PDF
                </button>
              </div>
              <div class="tax-portal-card" role="button" aria-disabled="true" onclick="showUnavailableFeature('Tax summary', 'Tax report generation is not connected yet. Earnings remain visible in the wallet once payouts are enabled.')">
                <div class="tax-card-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
                </div>
                <div class="tax-card-info">
                  <div class="tax-card-title">Q4 2024 Summary</div>
                  <div class="tax-card-sub">Oct – Dec 2024 · Available</div>
                </div>
                <button class="btn-download">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                  PDF
                </button>
              </div>
            </div>
          </div>

          <!-- TRIPS TAB -->
          <div class="dash-panel" id="panel-trips">
            <div class="section-head">
              <div class="section-head-title">Trip History</div>
              <div class="section-head-link">Filter</div>
            </div>

            <!-- Trip items -->
            <div class="trip-history-item" onclick="showToast('Receipt for trip #TRP-00142 opened')">
              <div class="trip-icon completed">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <div class="trip-details">
                <div class="trip-route">Eagle Island → Rumuola Rd</div>
                <div class="trip-meta">Today · 2:14 PM · #TRP-00142</div>
              </div>
              <div>
                <div class="trip-amount">₦3,400</div>
                <div class="trip-status completed">Completed</div>
              </div>
            </div>

            <div class="trip-history-item" onclick="showToast('Receipt for trip #TRP-00141 opened')">
              <div class="trip-icon completed">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <div class="trip-details">
                <div class="trip-route">GRA Phase 2 → Trans Amadi</div>
                <div class="trip-meta">Today · 12:55 PM · #TRP-00141</div>
              </div>
              <div>
                <div class="trip-amount">₦2,100</div>
                <div class="trip-status completed">Completed</div>
              </div>
            </div>

            <div class="trip-history-item" onclick="showToast('Receipt for trip #TRP-00140 opened')">
              <div class="trip-icon cancelled">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </div>
              <div class="trip-details">
                <div class="trip-route">Woji Road → D-Line</div>
                <div class="trip-meta">Today · 11:02 AM · #TRP-00140</div>
              </div>
              <div>
                <div class="trip-amount">₦0</div>
                <div class="trip-status cancelled">Cancelled</div>
              </div>
            </div>

            <div class="trip-history-item" onclick="showToast('Receipt for trip #TRP-00139 opened')">
              <div class="trip-icon completed">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <div class="trip-details">
                <div class="trip-route">Peter Odili Rd → Old GRA</div>
                <div class="trip-meta">Yesterday · 6:45 PM · #TRP-00139</div>
              </div>
              <div>
                <div class="trip-amount">₦4,750</div>
                <div class="trip-status completed">Completed</div>
              </div>
            </div>

            <div class="trip-history-item" onclick="showToast('Receipt for trip #TRP-00138 opened')">
              <div class="trip-icon completed">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <div class="trip-details">
                <div class="trip-route">Rumuokwurushi → Stadium Rd</div>
                <div class="trip-meta">Yesterday · 4:20 PM · #TRP-00138</div>
              </div>
              <div>
                <div class="trip-amount">₦3,200</div>
                <div class="trip-status completed">Completed</div>
              </div>
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
          </div>

          <!-- PROFILE TAB -->
          <div class="dash-panel" id="panel-profile">
            <div class="profile-header">
              <div class="profile-avatar-lg">
                <img src="https://app.oaglobalstandardservice.com/wp/wp-content/uploads/2026/04/1725853367655.jpg" alt="Profile Image">
              </div>
              <div class="profile-name">Chidi Nwosu</div>
              <div class="profile-rating">
                <span class="profile-star">★★★★★</span>
                4.8 · 23 trips this week
              </div>
              <div style="margin-top:16px;display:flex;gap:10px">
                <div style="background:rgba(34,196,122,0.15);border:1px solid rgba(34,196,122,0.25);color:var(--success);font-size:12px;font-weight:600;padding:5px 14px;border-radius:20px">✓ Verified</div>
                <div style="background:rgba(245,200,66,0.1);border:1px solid rgba(245,200,66,0.2);color:var(--gold);font-size:12px;font-weight:600;padding:5px 14px;border-radius:20px">Motorbike</div>
              </div>
            </div>

            <div class="profile-row" onclick="showToast('Edit personal info')">
              <div class="profile-row-left">
                <div class="profile-row-icon" style="background:var(--info-pale);color:var(--info)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div>
                  <div class="profile-row-label">Personal Info</div>
                  <div class="profile-row-sub">Name, DOB, State of Origin</div>
                </div>
              </div>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </div>

            <div class="profile-row" onclick="showToast('Edit bank details')">
              <div class="profile-row-left">
                <div class="profile-row-icon" style="background:rgba(34,196,122,0.1);color:var(--success)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                </div>
                <div>
                  <div class="profile-row-label">Bank Details</div>
                  <div class="profile-row-sub">GTBank · ****7890</div>
                </div>
              </div>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </div>

            <div class="profile-row" onclick="showToast('View vehicle documents')">
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

            <div class="profile-row" onclick="showToast('Edit emergency contact')">
              <div class="profile-row-left">
                <div class="profile-row-icon" style="background:rgba(232,72,74,0.08);color:var(--danger)">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13 19.79 19.79 0 0 1 1.61 4.37 2 2 0 0 1 3.6 2h3a2 2 0 0 1 2 1.72c.127.96.36 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.16 6.16l.97-.86a2 2 0 0 1 2.11-.45c.907.34 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </div>
                <div>
                  <div class="profile-row-label">Emergency Contact</div>
                  <div class="profile-row-sub">Mama Nwosu · Parent</div>
                </div>
              </div>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </div>

            <div style="margin-top:8px">
              <button class="global-btn ghost" style="width:100%;justify-content:center" onclick="location.reload()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Log Out
              </button>
            </div>
          </div>

        </div><!-- end driver-dash-body -->
      </div><!-- end dashBody scroll wrapper -->

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

  <input class="kyc-file-input" type="file" id="driverKycFileInput" accept="image/jpeg,image/png,application/pdf">

  <!-- TOAST -->
  <div class="toast" id="toast"></div>
</div>
