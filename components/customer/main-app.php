<!-- ══════════ MAIN APP ══════════ -->
<div class="screen" id="screen-main">
  <nav class="sidebar">
    <div style="display:flex;flex-direction:column;align-items:center;width:100%">
      <div class="sidebar-logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="22" height="22"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
      </div>
      <div class="sidebar-nav">
        <button class="sidebar-btn active" onclick="switchTab('home',this,'home')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          Home
        </button>
        <button class="sidebar-btn" onclick="switchTab('activity',this,'activity')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          Activity
        </button>
        <button class="sidebar-btn" onclick="switchTab('account',this,'account')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Account
        </button>
      </div>
    </div>
    <button class="sidebar-exit" onclick="confirmLogout()" title="Sign Out">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </button>
  </nav>
  <div class="main-content">

    <!-- HOME TAB -->
    <div class="tab-view active" id="tab-home">
      <div class="home-split">
        <!-- MAP -->
        <div class="map-area" id="home-map-container" style="z-index: 1;">
          <!-- Map controls -->
          <div class="map-float" style="z-index: 1000;">
            <button class="map-btn" onclick="showToast('Location updated')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="19" height="19"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
            </button>
          </div>
        </div>

        <!-- BOOKING PANEL -->
        <div class="booking-panel">
          <div class="drag-handle"></div>
          <div class="panel-scroll">
            <h2 class="panel-title">Request a Delivery</h2>
            <!-- Location inputs -->
            <div class="location-box">
              <div class="loc-row">
                <div class="loc-dot pickup">
                  <svg viewBox="0 0 24 24" fill="currentColor" width="12" height="12" style="color:var(--info)"><circle cx="12" cy="12" r="6"/></svg>
                </div>
                <div style="position:relative; display:flex; flex-direction:column; flex:1;">
                  <div style="display:flex;">
                    <input class="loc-input" type="text" id="pickupInput" placeholder="Pickup location" value="Agip Junction, Port Harcourt">
                    <button type="button" onclick="saveAddress('pickupInput')" style="margin-left:8px;background:none;border:none;color:var(--primary);font-size:12px;cursor:pointer;">Save</button>
                  </div>
                  <div id="pickupChips" style="display:flex;gap:8px;margin-top:8px;overflow-x:auto;"></div>
                </div>
              </div>
              <div class="loc-divider"></div>
              <div class="loc-row">
                <div class="loc-dot dropoff">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13" style="color:var(--gold-dark)"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <div style="position:relative;flex:1; display:flex; flex-direction:column;">
                  <div style="display:flex;">
                    <input class="loc-input" type="text" id="dropoffInput" placeholder="Where to deliver?">
                    <button type="button" onclick="saveAddress('dropoffInput')" style="margin-left:8px;background:none;border:none;color:var(--primary);font-size:12px;cursor:pointer;">Save</button>
                  </div>
                  <div id="dropoffChips" style="display:flex;gap:8px;margin-top:8px;overflow-x:auto;"></div>
                </div>
              </div>
              <button class="loc-swap" onclick="swapLocations()" title="Swap locations">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
              </button>
            </div>

            <!-- Schedule -->
            <div class="schedule-row">
              <button class="sched-btn active" id="schedImmediate" onclick="setSched(this,'immediate')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Immediate
              </button>
              <button class="sched-btn" id="schedLater" onclick="openModal('schedule')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Schedule
              </button>
            </div>

            <!-- Category Selection with fade hint -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-shrink:0;">
              <p class="section-label" style="margin-bottom:0;">What are you sending?</p>
              <span class="swipe-hint">Swipe <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="10" height="10"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
            </div>
            <div class="categories-wrapper">
              <div class="categories-scroll">
                <div class="cat-card active" onclick="selCat(this,'Package')">
                  <div class="cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div>
                  <span>Package</span>
                </div>
                <div class="cat-card" onclick="selCat(this,'Forgotten Items')">
                  <div class="cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                  <span>Forgotten Items</span>
                </div>
                <div class="cat-card" onclick="selCat(this,'Groceries')">
                  <div class="cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
                  <span>Groceries</span>
                </div>
                <div class="cat-card" onclick="selCat(this,'Documents')">
                  <div class="cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></div>
                  <span>Documents</span>
                </div>
                <div class="cat-card" onclick="selCat(this,'Office Items')">
                  <div class="cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div>
                  <span>Office Items</span>
                </div>
                <div class="cat-card" onclick="selCat(this,'Gift')">
                  <div class="cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg></div>
                  <span>Gift</span>
                </div>
                <div class="cat-card" onclick="selCat(this,'Flowers')">
                  <div class="cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div>
                  <span>Flowers</span>
                </div>
                <div class="cat-card" onclick="selCat(this,'Household')">
                  <div class="cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                  <span>Household</span>
                </div>
                <div class="cat-card" onclick="selCat(this,'Laundry')">
                  <div class="cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M3 3h18v4H3zM3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/><circle cx="12" cy="14" r="3"/></svg></div>
                  <span>Laundry</span>
                </div>
                <div class="cat-card" onclick="selCat(this,'Dry Cleaning')">
                  <div class="cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg></div>
                  <span>Dry Cleaning</span>
                </div>
                <div class="cat-card" onclick="selCat(this,'Customer Orders')">
                  <div class="cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
                  <span>Customer Orders</span>
                </div>
                <div class="cat-card" onclick="selCat(this,'Vendor Samples')">
                  <div class="cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></div>
                  <span>Vendor Samples</span>
                </div>
                <div class="cat-card" onclick="selCat(this,'Inventory Docs')">
                  <div class="cat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg></div>
                  <span>Inventory Docs</span>
                </div>
              </div>
            </div>

            <!-- Special Services -->
            <p class="section-label">Special Services</p>
            <div class="service-flags">
              <div class="service-flag active" onclick="toggleFlag(this)">
                <div class="sf-left">
                  <div class="sf-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                  </div>
                  <div>
                    <div class="sf-title">Same Day Delivery</div>
                    <div class="sf-desc">Guaranteed delivery by end of day</div>
                  </div>
                </div>
                <div class="toggle-wrap on"><div class="toggle-knob"></div></div>
              </div>
              <div class="service-flag" onclick="toggleFlag(this)">
                <div class="sf-left">
                  <div class="sf-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                  </div>
                  <div>
                    <div class="sf-title">Send Safely</div>
                    <div class="sf-desc">High security tracking + PIN confirm</div>
                  </div>
                </div>
                <div class="toggle-wrap"><div class="toggle-knob"></div></div>
              </div>
              <div class="service-flag" onclick="toggleFlag(this)">
                <div class="sf-left">
                  <div class="sf-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                  </div>
                  <div>
                    <div class="sf-title">Cash on Pickup</div>
                    <div class="sf-desc">Pay rider directly on collection</div>
                  </div>
                </div>
                <div class="toggle-wrap"><div class="toggle-knob"></div></div>
              </div>
            </div>
          </div>
          <div class="panel-footer">
            <button class="find-btn" onclick="requestQuote()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              Find a Rider
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ACTIVITY TAB -->
    <div class="tab-view" id="tab-activity">
      <div class="activity-tab">
        <h2 class="tab-header">My Activity</h2>
        <p class="tab-sub">Your delivery history & scheduled pickups</p>
        <div class="filter-row">
          <button class="filter-pill active" onclick="filterTrips(this,'all')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            All
          </button>
          <button class="filter-pill" onclick="filterTrips(this,'in-transit')">In Transit</button>
          <button class="filter-pill" onclick="filterTrips(this,'delivered')">Delivered</button>
          <button class="filter-pill" onclick="filterTrips(this,'scheduled')">Scheduled</button>
          <button class="filter-pill" onclick="filterTrips(this,'cancelled')">Cancelled</button>
        </div>

        <div id="trips-container">
          <!-- In Transit -->
          <div class="trip-card" data-status="in-transit" onclick="startLiveTracking(1)">
            <div class="trip-top">
              <div><div class="trip-id">#SD-00928</div><div class="trip-date">Today · 2:14 PM</div></div>
              <span class="trip-status in-transit">In Transit</span>
            </div>
            <div class="trip-route">
              <div class="trip-point"><div class="trip-point-dot from"></div> Agip Junction, Port Harcourt</div>
              <div class="trip-line"></div>
              <div class="trip-point"><div class="trip-point-dot to"></div> D-Line, Rumuola Road</div>
            </div>
            <div class="trip-meta">
              <span class="trip-price">₦2,800</span>
              <div class="trip-actions">
                <button class="trip-action-btn primary" onclick="event.stopPropagation();startLiveTracking(1)">Track Live</button>
              </div>
            </div>
          </div>

          <!-- Scheduled -->
          <div class="trip-card" data-status="scheduled" onclick="showToast('Scheduled pickup details')">
            <div class="trip-top">
              <div><div class="trip-id">#SD-00940</div><div class="trip-date">Tomorrow · 9:00 AM</div></div>
              <span class="trip-status scheduled">Scheduled</span>
            </div>
            <div class="trip-route">
              <div class="trip-point"><div class="trip-point-dot from"></div> Woji Market, Port Harcourt</div>
              <div class="trip-line"></div>
              <div class="trip-point"><div class="trip-point-dot to"></div> Rumuomasi, PH</div>
            </div>
            <div class="trip-meta">
              <span class="trip-price">₦1,900</span>
              <div class="trip-actions">
                <button class="trip-action-btn" onclick="event.stopPropagation();showToast('Pickup cancelled')">Cancel</button>
                <button class="trip-action-btn primary" onclick="event.stopPropagation();showToast('Editing pickup...')">Edit</button>
              </div>
            </div>
          </div>

          <!-- Delivered -->
          <div class="trip-card" data-status="delivered" onclick="openModal('post-trip')">
            <div class="trip-top">
              <div><div class="trip-id">#SD-00871</div><div class="trip-date">Yesterday · 11:30 AM</div></div>
              <span class="trip-status delivered">Delivered</span>
            </div>
            <div class="trip-route">
              <div class="trip-point"><div class="trip-point-dot from"></div> Ada George Road</div>
              <div class="trip-line"></div>
              <div class="trip-point"><div class="trip-point-dot to"></div> GRA Phase 2, PH</div>
            </div>
            <div class="trip-meta">
              <span class="trip-price">₦4,200</span>
              <div class="trip-actions">
                <button class="trip-action-btn" onclick="event.stopPropagation();showToast('Receipt downloaded')">Receipt</button>
                <button class="trip-action-btn primary" onclick="event.stopPropagation();showToast('Booking again...')">Re-book</button>
              </div>
            </div>
          </div>

          <!-- Delivered 2 -->
          <div class="trip-card" data-status="delivered">
            <div class="trip-top">
              <div><div class="trip-id">#SD-00803</div><div class="trip-date">Apr 7 · 9:00 AM</div></div>
              <span class="trip-status delivered">Delivered</span>
            </div>
            <div class="trip-route">
              <div class="trip-point"><div class="trip-point-dot from"></div> Trans-Amadi Industrial Layout</div>
              <div class="trip-line"></div>
              <div class="trip-point"><div class="trip-point-dot to"></div> Woji, Off Peter Odili</div>
            </div>
            <div class="trip-meta">
              <span class="trip-price">₦1,500</span>
              <div class="trip-actions">
                <button class="trip-action-btn" onclick="showToast('Receipt downloaded')">Receipt</button>
                <button class="trip-action-btn primary" onclick="showToast('Booking again...')">Re-book</button>
              </div>
            </div>
          </div>

          <!-- Cancelled -->
          <div class="trip-card" data-status="cancelled">
            <div class="trip-top">
              <div><div class="trip-id">#SD-00791</div><div class="trip-date">Apr 5 · 4:45 PM</div></div>
              <span class="trip-status cancelled">Cancelled</span>
            </div>
            <div class="trip-route">
              <div class="trip-point"><div class="trip-point-dot from"></div> Eleme Junction</div>
              <div class="trip-line"></div>
              <div class="trip-point"><div class="trip-point-dot to"></div> Borokiri, PH</div>
            </div>
            <div class="trip-meta">
              <span class="trip-price" style="color:var(--text-muted);font-size:14px">Refunded</span>
              <div class="trip-actions">
                <button class="trip-action-btn primary" onclick="showToast('Booking again...')">Re-book</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ACCOUNT TAB -->
    <div class="tab-view" id="tab-account">
      <div class="account-tab">
        <div class="account-hero">
          <div class="account-hero-bg"></div>
          <div class="avatar-wrap">
            <div class="avatar"><?php echo esc_html($customer_initials); ?></div>
            <div>
              <div class="avatar-name"><?php echo esc_html($customer_full_name); ?></div>
              <div class="avatar-email"><?php echo esc_html($customer_email); ?></div>
              <div class="avatar-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="11" height="11"><polyline points="20 6 9 17 4 12"/></svg>
                Verified Account
              </div>
            </div>
          </div>
        </div>
        <!-- Stats -->
        <div class="account-stats">
          <div class="stat-card">
            <div class="stat-value"><?php echo esc_html($trips_count); ?></div>
            <div class="stat-label">Trips</div>
          </div>
          <div class="stat-card">
            <div class="stat-value"><?php echo esc_html($customer_rating); ?></div>
            <div class="stat-label">Rating</div>
          </div>
          <div class="stat-card">
            <div class="stat-value">₦500</div>
            <div class="stat-label">Referral Bonus</div>
          </div>
        </div>
        <div class="account-body">
          <!-- Referral -->
          <div class="referral-banner">
            <div class="ref-text">
              <h4>Refer & Earn ₦500</h4>
              <p>Share your code with friends</p>
            </div>
            <div class="ref-code" onclick="showToast('Referral code <?php echo esc_js($customer_referral_code); ?> copied!')"><?php echo esc_html($customer_referral_code); ?></div>
          </div>

          <!-- Account Settings -->
          <div class="account-card">
            <div class="account-card-title">My Account</div>
            <div class="account-row" onclick="openModal('profile')">
              <div class="account-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
              <span class="account-row-label">Profile Details</span>
              <span class="account-row-meta"><?php echo esc_html($customer_full_name); ?></span>
              <div class="account-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 18l6-6-6-6"/></svg></div>
            </div>
            <div class="account-row" onclick="openModal('modal-payment')">
              <div class="account-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
              <span class="account-row-label">Payment Methods</span>
              <span class="account-row-meta">Bank Transfer</span>
              <div class="account-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 18l6-6-6-6"/></svg></div>
            </div>
            <div class="account-row" onclick="switchTab('home')">
              <div class="account-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
              <span class="account-row-label">Saved Addresses</span>
              <span class="account-row-meta"><?php echo esc_html($saved_addresses_count); ?> places</span>
              <div class="account-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 18l6-6-6-6"/></svg></div>
            </div>
            <div class="account-row" onclick="openPreferencesModal()">
              <div class="account-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></div>
              <span class="account-row-label">Notifications</span>
              <span class="chip chip-success" style="font-size:10px">On</span>
              <div class="account-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 18l6-6-6-6"/></svg></div>
            </div>
          </div>

          <!-- Support -->
          <div class="account-card">
            <div class="account-card-title">Support & Help</div>
            <div class="account-row" onclick="openModal('modal-support')">
              <div class="account-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
              <span class="account-row-label">Customer Support</span>
              <div class="account-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 18l6-6-6-6"/></svg></div>
            </div>
            <div class="account-row" onclick="openModal('modal-faq')">
              <div class="account-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
              <span class="account-row-label">FAQs</span>
              <div class="account-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 18l6-6-6-6"/></svg></div>
            </div>
          </div>

          <!-- Legal Center -->
          <div class="account-card">
            <div class="account-card-title">Legal Center</div>
            <div class="account-row" onclick="openLegalModal('Terms & Conditions', 'legal_terms')">
              <div class="account-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
              <span class="account-row-label">Terms & Conditions</span>
              <div class="account-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 18l6-6-6-6"/></svg></div>
            </div>
            <div class="account-row" onclick="openLegalModal('Privacy Policy', 'legal_privacy')">
              <div class="account-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
              <span class="account-row-label">Privacy Policy</span>
              <div class="account-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 18l6-6-6-6"/></svg></div>
            </div>
            <div class="account-row" onclick="openLegalModal('Location Data Policy', 'legal_location')">
              <div class="account-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
              <span class="account-row-label">Location Data Policy</span>
              <div class="account-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 18l6-6-6-6"/></svg></div>
            </div>
            <div class="account-row" onclick="openLegalModal('Software License', 'legal_license')">
              <div class="account-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></div>
              <span class="account-row-label">Software License</span>
              <div class="account-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 18l6-6-6-6"/></svg></div>
            </div>
            <div class="account-row" onclick="openLegalModal('Copyright Notice', 'legal_copyright')">
              <div class="account-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="12" r="10"/><path d="M15 9.354a4 4 0 1 0 0 5.292"/></svg></div>
              <span class="account-row-label">Copyright</span>
              <div class="account-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 18l6-6-6-6"/></svg></div>
            </div>
          </div>

          <!-- Sign Out -->
          <div class="account-card">
            <div class="account-row" onclick="confirmLogout()">
              <div class="account-row-icon" style="background:var(--danger-soft)"><svg viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2" width="18" height="18"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></div>
              <span class="account-row-label" style="color:var(--danger)">Sign Out</span>
              <div class="account-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2" width="15" height="15"><path d="M9 18l6-6-6-6"/></svg></div>
            </div>
          </div>
          <p style="text-align:center;font-size:12px;color:var(--text-muted);padding:16px 0 8px">Idibia v2.6.1 · © 2026 Idibia Logistics Inc.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Bottom Nav -->
  <nav class="bottom-nav">
    <button class="bnav-btn active" id="bnav-home" onclick="switchTab('home',null,'home')">
      <div class="bnav-indicator"></div>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Home
    </button>
    <button class="bnav-btn" id="bnav-activity" onclick="switchTab('activity',null,'activity')">
      <div class="bnav-indicator"></div>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Activity
    </button>
    <button class="bnav-btn" id="bnav-account" onclick="switchTab('account',null,'account')">
      <div class="bnav-indicator"></div>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Account
    </button>
  </nav>
</div>
