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
        <button class="sidebar-btn" onclick="switchTab('map',this,'map')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          Map
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
    <!-- Notification Bell -->
    <div style="position:relative;margin-top:auto;padding-bottom:8px">
      <button class="sidebar-exit" id="notifBellBtn" onclick="toggleNotifDropdown()" title="Notifications" style="position:relative">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span id="notifBadge" style="display:none;position:absolute;top:2px;right:2px;background:#e53e3e;color:#fff;font-size:10px;font-weight:700;min-width:16px;height:16px;border-radius:8px;padding:0 4px;line-height:16px;text-align:center"></span>
      </button>
    </div>
    <button class="sidebar-exit" onclick="confirmLogout()" title="Sign Out">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </button>
  </nav>

  <!-- Mobile Header (visible only on mobile) -->
  <header id="mobile-header">
    <div class="mobile-header-brand">
      <div class="mobile-header-logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
      </div>
      <span class="mobile-header-name">Idibia</span>
    </div>
    <button class="mobile-notif-btn" id="notifBellBtnMobile" onclick="toggleNotifDropdown()" aria-label="Notifications">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <span id="notifBadgeMobile" style="display:none;position:absolute;top:4px;right:4px;background:#e53e3e;color:#fff;font-size:9px;font-weight:700;min-width:15px;height:15px;border-radius:8px;padding:0 3px;line-height:15px;text-align:center;pointer-events:none;"></span>
    </button>
  </header>

  <!-- Notification Dropdown -->
  <div id="notifDropdown" style="display:none;position:fixed;top:0;left:60px;width:300px;height:100%;background:var(--white);border-right:1px solid var(--surface-2);z-index:900;display:none;flex-direction:column;box-shadow:4px 0 16px rgba(0,0,0,0.12)">
    <div style="padding:16px;border-bottom:1px solid var(--surface-2);display:flex;justify-content:space-between;align-items:center">
      <strong style="font-size:15px;color:var(--text-primary)">Notifications</strong>
      <div style="display:flex;align-items:center;gap:12px;">
        <button onclick="markAllNotifRead()" style="font-size:12px;color:var(--primary);background:none;border:none;cursor:pointer;padding:0">Mark all read</button>
        <button class="notif-close-mobile" onclick="toggleNotifDropdown()" aria-label="Close" style="display:none;background:none;border:none;cursor:pointer;padding:4px;color:var(--text-muted);line-height:0;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
    </div>
    <div id="notifList" style="flex:1;overflow-y:auto;padding:8px 0">
      <div style="padding:16px;color:var(--text-muted);font-size:13px">Loading…</div>
    </div>
  </div>
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
          <!-- Location permission hint (shown when GPS access is denied) -->
          <div id="loc-perm-hint" class="loc-perm-hint" role="alert" aria-live="polite" aria-atomic="true">
            <div class="lph-card">
              <div class="lph-icon-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M17.5 17.5 6.5 6.5"/><path d="M10.5 6.2A7 7 0 0 1 19 10c0 3.5-2 6.6-4.5 9"/><path d="M7.4 7.9C6.2 8.8 5 10.3 5 10c0 5 7 10 7 10 1-.7 2-1.5 2.8-2.4"/><circle cx="12" cy="10" r="2.5"/></svg>
              </div>
              <div class="lph-text">
                <strong>Location access blocked</strong>
                <span>Allow location in your browser settings, then retry</span>
              </div>
              <button class="lph-close" onclick="dismissLocHint('loc-perm-hint')" aria-label="Dismiss">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
            <button class="lph-alt-btn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              Pin location on map instead
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
                  <div style="position:relative;">
                    <div class="loc-input-row">
                      <input class="loc-input" type="text" id="pickupInput" placeholder="Pickup location" autocomplete="off">
                      <button type="button" id="pickupGpsBtn" class="loc-icon-btn loc-gps-btn" onclick="useMyLocation('pickup')" title="Use my current location" aria-label="Use my current location">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><circle cx="12" cy="12" r="7"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/><circle cx="12" cy="12" r="2.5" fill="currentColor" stroke="none"/></svg>
                      </button>
                      <button type="button" class="loc-icon-btn loc-save-btn" onclick="saveAddress('pickupInput')" title="Save this address" aria-label="Save this address">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                      </button>
                    </div>
                    <div id="pickupSuggestions" class="photon-dropdown"></div>
                  </div>
                </div>
              </div>
              <div class="loc-divider"></div>
              <div class="loc-row">
                <div class="loc-dot dropoff">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13" style="color:var(--gold-dark)"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </div>
                <div style="position:relative;flex:1; display:flex; flex-direction:column;">
                  <div style="position:relative;">
                    <div class="loc-input-row">
                      <input class="loc-input" type="text" id="dropoffInput" placeholder="Where to deliver?" autocomplete="off">
                      <button type="button" id="dropoffGpsBtn" class="loc-icon-btn loc-gps-btn" onclick="useMyLocation('dropoff')" title="Use my current location" aria-label="Use my current location">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><circle cx="12" cy="12" r="7"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/><circle cx="12" cy="12" r="2.5" fill="currentColor" stroke="none"/></svg>
                      </button>
                      <button type="button" class="loc-icon-btn loc-save-btn" onclick="saveAddress('dropoffInput')" title="Save this address" aria-label="Save this address">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                      </button>
                    </div>
                    <div id="dropoffSuggestions" class="photon-dropdown"></div>
                  </div>
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

            <!-- Options toggle (collapsed by default) -->
            <div class="options-toggle" onclick="toggleBookingOptions()" id="optionsToggle">
              <div style="display:flex;align-items:center;gap:9px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15" style="color:var(--text-muted);flex-shrink:0"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                <span id="optionsLabel">Package · Same Day</span>
              </div>
              <div style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--gold-dark);font-weight:700;">
                Options
                <svg id="optionsChevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13" style="transition:transform 0.25s"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
            </div>
            <div id="bookingOptions" style="display:none;">

            <!-- Category Selection with fade hint -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-shrink:0; margin-top:14px;">
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

            </div><!-- /bookingOptions -->
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
          <button class="filter-pill" onclick="filterTrips(this,'searching')">Searching</button>
          <button class="filter-pill" onclick="filterTrips(this,'in-transit')">In Transit</button>
          <button class="filter-pill" onclick="filterTrips(this,'delivered')">Delivered</button>
          <button class="filter-pill" onclick="filterTrips(this,'scheduled')">Scheduled</button>
          <button class="filter-pill" onclick="filterTrips(this,'cancelled')">Cancelled</button>
        </div>

        <div id="trips-container">
          <!-- Empty state -->
          <div class="empty-state" style="text-align:center; padding: 40px 20px; color: var(--text-muted);">No trips yet. Book a delivery to get started!</div>
        </div>
      </div>
    </div>

    <!-- ACCOUNT TAB -->
    <div class="tab-view" id="tab-account">
      <div class="account-tab">
        <div class="account-hero">
          <div class="account-hero-bg"></div>
          <div class="avatar-wrap">
            <div class="avatar" style="position: relative;">
              <?php if ( ! empty( $customer_avatar_path ) ) : ?>
                <img src="<?php echo esc_url( strpos($customer_avatar_path, 'http') === 0 ? $customer_avatar_path : ( $upload_baseurl ? $upload_baseurl . '/' . $customer_avatar_path : '/' . $customer_avatar_path ) ); ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
              <?php else : ?>
                <?php echo esc_html($customer_initials); ?>
              <?php endif; ?>
              <div class="camera-badge" onclick="document.getElementById('customerAvatarInput').click()" style="position:absolute; bottom:-4px; right:-4px; width:24px; height:24px; background:var(--primary); border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid var(--white); cursor:pointer;">
                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" width="12" height="12"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
              </div>
              <input type="file" id="customerAvatarInput" style="display:none;" accept="image/jpeg, image/png, image/webp" onchange="uploadCustomerAvatar(event)">
            </div>
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
          <div class="stat-card" onclick="openWalletPanel()" style="cursor:pointer;border-color:rgba(245,200,66,0.3);background:linear-gradient(135deg,#fffdf0 0%,var(--white) 100%)">
            <div class="stat-value" style="color:var(--gold-dark)">₦<?php
              $w = (float)$customer_wallet_balance;
              if ($w >= 1000000) { echo number_format($w/1000000, 1) . 'M'; }
              elseif ($w >= 1000) { echo number_format($w/1000, 1) . 'K'; }
              else { echo number_format($w, 0); }
            ?></div>
            <div class="stat-label">Wallet</div>
          </div>
        </div>

        <!-- Wallet Panel (always visible) -->
        <div id="walletPanel" style="margin:0 16px 16px">
          <!-- Dark premium wallet header -->
          <div style="background:linear-gradient(135deg,var(--navy) 0%,#1a2a4a 100%);border-radius:16px 16px 0 0;padding:20px 20px 24px;border:1.5px solid rgba(245,200,66,0.15);border-bottom:none;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
              <div style="display:flex;align-items:center;gap:9px">
                <div style="width:34px;height:34px;background:rgba(245,200,66,0.1);border:1px solid rgba(245,200,66,0.2);border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                  <svg viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="2" width="17" height="17"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                </div>
                <span style="font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,0.55)">My Wallet</span>
              </div>
              <div style="background:rgba(34,196,122,0.12);border:1px solid rgba(34,196,122,0.25);border-radius:20px;padding:3px 10px;font-size:10px;font-weight:700;color:#22c47a;letter-spacing:0.5px">ACTIVE</div>
            </div>
            <div style="margin-bottom:20px">
              <div style="font-size:11px;color:rgba(255,255,255,0.4);letter-spacing:0.6px;text-transform:uppercase;margin-bottom:6px">Available Balance</div>
              <div id="walletBalanceDisplay" style="font-family:'Syne',sans-serif;font-size:38px;font-weight:800;color:var(--white);letter-spacing:-1px;line-height:1.1">₦<?php echo esc_html(number_format((float)$customer_wallet_balance, 2)); ?></div>
            </div>
            <button onclick="openTopUpModal()" style="width:100%;padding:14px;background:var(--gold);color:var(--navy);border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;box-sizing:border-box;transition:opacity 0.2s;" onmouseenter="this.style.opacity='0.88'" onmouseleave="this.style.opacity='1'">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Top Up Wallet
            </button>
            <button onclick="openManualTopUpModal()" style="width:100%;margin-top:8px;padding:11px;background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.75);border:1px solid rgba(255,255,255,0.15);border-radius:12px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:7px;box-sizing:border-box;transition:opacity 0.2s;" onmouseenter="this.style.opacity='0.7'" onmouseleave="this.style.opacity='1'">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Fund by Bank Transfer
            </button>
          </div>
          <!-- Transactions section on white background -->
          <div style="background:var(--white);border:1.5px solid rgba(245,200,66,0.15);border-top:1px solid var(--surface-2);border-radius:0 0 16px 16px;padding:16px 20px;box-shadow:0 4px 20px rgba(11,22,40,0.08)">
            <div id="walletLedger">
              <div style="font-size:10px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px">Recent Transactions</div>
              <div id="walletLedgerList" style="font-size:13px;color:var(--text-secondary)">Loading...</div>
            </div>
            <div id="topupRequestsSection" style="margin-top:14px;display:none">
              <div style="font-size:10px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px">Transfer Requests</div>
              <div id="topupRequestsList" style="font-size:13px;color:var(--text-secondary)"></div>
            </div>
          </div>
        </div>

        <!-- Top-Up Modal -->
        <div class="modal-overlay" id="topUpModal" onclick="if(event.target===this)closeTopUpModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:flex-end;justify-content:center">
          <div style="background:var(--white);border-radius:20px 20px 0 0;padding:24px;width:100%;max-width:480px;max-height:85vh;overflow-y:auto">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
              <h3 style="font-size:16px;font-weight:700;margin:0">Top Up Wallet</h3>
              <button onclick="closeTopUpModal()" style="background:none;border:none;cursor:pointer;font-size:22px;color:var(--text-secondary);line-height:1">×</button>
            </div>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">Choose an amount to add to your wallet. You can use it to pay for trips instantly.</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px">
              <?php foreach([500,1000,2000,5000] as $preset): ?>
              <button onclick="setTopUpAmount(<?php echo $preset; ?>)" class="preset-amount-btn" data-amount="<?php echo $preset; ?>" style="padding:12px;border:1.5px solid var(--border);border-radius:10px;background:var(--surface);font-size:14px;font-weight:600;cursor:pointer;color:var(--text-primary)">₦<?php echo number_format($preset); ?></button>
              <?php endforeach; ?>
            </div>
            <div style="margin-bottom:16px">
              <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px">CUSTOM AMOUNT</label>
              <input id="topUpAmount" type="number" min="100" step="50" placeholder="e.g. 3000" style="width:100%;padding:12px;border:1.5px solid var(--border);border-radius:10px;font-size:15px;box-sizing:border-box;background:var(--surface)">
            </div>
            <div style="margin-bottom:20px">
              <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px">PAY WITH</label>
              <select id="topUpProvider" style="width:100%;padding:12px;border:1.5px solid var(--border);border-radius:10px;font-size:14px;background:var(--surface)">
                <option value="paystack">Paystack (Card / Bank Transfer)</option>
                <option value="flutterwave">Flutterwave (Card / USSD)</option>
              </select>
            </div>
            <button onclick="submitTopUp()" id="topUpSubmitBtn" style="width:100%;padding:14px;background:var(--primary);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer">
              Continue to Payment
            </button>
            <p style="font-size:11px;color:var(--text-muted);text-align:center;margin-top:10px">Funds appear in your wallet instantly after payment.</p>
          </div>
        </div>

        <!-- Manual Bank Transfer Top-Up Modal -->
        <div id="manualTopUpModal" onclick="if(event.target===this)closeManualTopUpModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:flex-end;justify-content:center">
          <div style="background:var(--white);border-radius:20px 20px 0 0;padding:24px;width:100%;max-width:480px;max-height:88vh;overflow-y:auto;box-sizing:border-box">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
              <h3 style="font-size:16px;font-weight:700;margin:0;color:var(--text-primary)">Fund Wallet by Bank Transfer</h3>
              <button onclick="closeManualTopUpModal()" style="background:none;border:none;cursor:pointer;font-size:22px;color:var(--text-secondary);line-height:1;padding:0">×</button>
            </div>

            <!-- Bank account box -->
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px">
              <div style="font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:12px">Transfer to this account</div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
                <div>
                  <div style="font-size:11px;color:var(--text-muted);margin-bottom:2px">Bank</div>
                  <div style="font-size:14px;font-weight:600;color:var(--text-primary)"><?php echo esc_html( $company_bank_name ); ?></div>
                </div>
                <div>
                  <div style="font-size:11px;color:var(--text-muted);margin-bottom:2px">Account Name</div>
                  <div style="font-size:14px;font-weight:600;color:var(--text-primary)"><?php echo esc_html( $company_account_name ); ?></div>
                </div>
              </div>
              <div style="margin-bottom:12px">
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px">Account Number</div>
                <div id="manualTopupAcctNum" style="font-size:22px;font-weight:800;color:var(--primary);font-family:monospace;letter-spacing:3px"><?php echo esc_html( $company_account_number ); ?></div>
              </div>
              <button onclick="copyManualTopupAcctNum()" style="width:100%;padding:10px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;box-sizing:border-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                Copy Account Number
              </button>
            </div>

            <!-- Amount -->
            <div style="margin-bottom:14px">
              <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px">Amount You Transferred (₦)</label>
              <input id="manualTopupAmount" type="number" min="100" step="50" placeholder="e.g. 5000" style="width:100%;padding:12px;border:1.5px solid var(--border);border-radius:10px;font-size:15px;box-sizing:border-box;background:var(--surface);color:var(--text-primary)">
            </div>

            <!-- Bank reference -->
            <div style="margin-bottom:14px">
              <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px">Bank Transfer Reference <span style="font-weight:400;text-transform:none">(optional)</span></label>
              <input id="manualTopupRef" type="text" placeholder="e.g. TRF230012345" style="width:100%;padding:12px;border:1.5px solid var(--border);border-radius:10px;font-size:14px;box-sizing:border-box;background:var(--surface);color:var(--text-primary)">
              <div style="font-size:11px;color:var(--text-muted);margin-top:4px">The reference number shown in your bank app after the transfer.</div>
            </div>

            <!-- Receipt upload -->
            <div style="margin-bottom:20px">
              <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px">Upload Transfer Receipt</label>
              <label for="manualTopupProof" style="display:flex;flex-direction:column;align-items:center;padding:20px 16px;border:2px dashed var(--border);border-radius:10px;cursor:pointer;background:var(--surface);transition:border-color 0.2s;text-align:center" onmouseenter="this.style.borderColor='var(--primary)'" onmouseleave="this.style.borderColor='var(--border)'">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2" width="28" height="28" style="margin-bottom:8px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <div id="manualTopupFileName" style="font-size:13px;font-weight:600;color:var(--text-secondary)">Tap to upload receipt</div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Screenshot, photo, or PDF · max 8MB</div>
              </label>
              <input type="file" id="manualTopupProof" accept=".jpg,.jpeg,.png,.pdf" style="display:none" onchange="onTopupFileSelected(this)">
            </div>

            <button onclick="submitManualTopUp()" id="manualTopupSubmitBtn" style="width:100%;padding:14px;background:var(--primary);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;box-sizing:border-box">
              Submit Top-Up Request
            </button>
            <p style="font-size:11px;color:var(--text-muted);text-align:center;margin-top:10px">We'll review your receipt and credit your wallet — usually within a few hours.</p>
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
            <div class="account-row" onclick="openModal('payment')">
              <div class="account-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
              <span class="account-row-label">Payment Methods</span>
              <span class="account-row-meta">Bank Transfer</span>
              <div class="account-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 18l6-6-6-6"/></svg></div>
            </div>
            <div class="account-row" onclick="openSavedAddressesModal()">
              <div class="account-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
              <span class="account-row-label">Saved Addresses</span>
              <span class="account-row-meta" id="savedAddressesCount"><?php echo esc_html($saved_addresses_count); ?> <?php echo $saved_addresses_count === 1 ? 'place' : 'places'; ?></span>
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
            <div class="account-row" onclick="openModal('support')">
              <div class="account-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
              <span class="account-row-label">Customer Support</span>
              <div class="account-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 18l6-6-6-6"/></svg></div>
            </div>
            <div class="account-row" onclick="openMyTickets()">
              <div class="account-row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
              <span class="account-row-label">My Tickets</span>
              <div class="account-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M9 18l6-6-6-6"/></svg></div>
            </div>
            <div class="account-row" onclick="openModal('faq')">
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

    <!-- MAP TAB -->
    <div class="tab-view" id="tab-map">
      <div id="explore-map-container" style="flex:1;position:relative;overflow:hidden;min-height:0;">
        <div class="map-float" style="z-index:1000;">
          <button class="map-btn" onclick="centerExploreMap()" title="My location">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="19" height="19"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
          </button>
        </div>
        <!-- Location permission hint for explore map -->
        <div id="explore-loc-perm-hint" class="loc-perm-hint" role="alert" aria-live="polite" aria-atomic="true">
          <div class="lph-card">
            <div class="lph-icon-wrap">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M17.5 17.5 6.5 6.5"/><path d="M10.5 6.2A7 7 0 0 1 19 10c0 3.5-2 6.6-4.5 9"/><path d="M7.4 7.9C6.2 8.8 5 10.3 5 10c0 5 7 10 7 10 1-.7 2-1.5 2.8-2.4"/><circle cx="12" cy="10" r="2.5"/></svg>
            </div>
            <div class="lph-text">
              <strong>Location access blocked</strong>
              <span>Allow location in your browser settings to centre the map on you</span>
            </div>
            <button class="lph-close" onclick="dismissLocHint('explore-loc-perm-hint')" aria-label="Dismiss">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
        </div>
        <div style="position:absolute;bottom:max(28px,env(safe-area-inset-bottom));left:50%;transform:translateX(-50%);z-index:1000;">
          <button onclick="switchTab('home',null,'home')" class="explore-book-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Book a Delivery
          </button>
        </div>
      </div>
    </div>
  </div><!-- /main-content -->

  <!-- Bottom Nav -->
  <nav class="bottom-nav">
    <button class="bnav-btn active" id="bnav-home" onclick="switchTab('home',null,'home')">
      <div class="bnav-indicator"></div>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Home
    </button>
    <button class="bnav-btn" id="bnav-map" onclick="switchTab('map',null,'map')">
      <div class="bnav-indicator"></div>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
      Map
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

<!-- SAVE ADDRESS MODAL -->
<div id="save-address-modal" style="display:none;position:fixed;inset:0;z-index:9600;background:rgba(11,22,40,0.55);align-items:flex-end;justify-content:center;" onclick="if(event.target===this)closeSaveAddressModal()">
  <div style="background:var(--white);border-radius:var(--radius) var(--radius) 0 0;padding:24px 20px calc(20px + env(safe-area-inset-bottom));width:100%;max-width:480px;box-sizing:border-box;">
    <div style="font-weight:700;font-size:16px;color:var(--text-primary);margin-bottom:4px;">Save This Address</div>
    <div id="save-address-preview" style="font-size:13px;color:var(--text-muted);margin-bottom:16px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></div>
    <label style="font-size:13px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px;">Label (e.g. Home, Work)</label>
    <input id="save-address-label-input" type="text" placeholder="Enter a label…" maxlength="30" autocomplete="off"
      style="width:100%;border:1.5px solid var(--surface-2);border-radius:var(--radius-sm);padding:10px 14px;font-size:15px;color:var(--text-primary);outline:none;box-sizing:border-box;-webkit-appearance:none;"
      onkeydown="if(event.key==='Enter')confirmSaveAddress()">
    <div style="display:flex;gap:10px;margin-top:16px;">
      <button onclick="closeSaveAddressModal()" style="flex:1;padding:13px;background:var(--surface);border:1.5px solid var(--surface-2);border-radius:var(--radius-sm);font-size:14px;font-weight:600;color:var(--text-secondary);cursor:pointer;">Cancel</button>
      <button onclick="confirmSaveAddress()" style="flex:2;padding:13px;background:var(--primary);border:none;border-radius:var(--radius-sm);font-size:14px;font-weight:700;color:var(--white);cursor:pointer;">Save Address</button>
    </div>
  </div>
</div>

<!-- PIN ON MAP OVERLAY — fullscreen, position:fixed, z-index above everything -->
<div id="pin-location-overlay" style="display:none;position:fixed;inset:0;z-index:9500;flex-direction:column;background:var(--white);">
  <!-- Header -->
  <div style="display:flex;align-items:center;gap:12px;padding:max(14px,env(safe-area-inset-top)) 16px 14px;background:var(--white);border-bottom:1px solid var(--surface-2);flex-shrink:0;">
    <button onclick="closePinModal()" style="background:none;border:none;cursor:pointer;padding:4px;color:var(--text-primary);display:flex;align-items:center;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="22" height="22"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </button>
    <div>
      <div style="font-weight:700;font-size:15px;color:var(--text-primary)" id="pinModalTitle">Pin Pickup Location</div>
      <div style="font-size:12px;color:var(--text-muted)">Drag the map — keep the crosshair on your spot, then tap Confirm</div>
    </div>
  </div>
  <!-- Map area -->
  <div style="flex:1;position:relative;min-height:0;">
    <div id="pin-map" style="width:100%;height:100%;"></div>
    <!-- Crosshair fixed at center -->
    <div class="pin-crosshair" aria-hidden="true">
      <svg viewBox="0 0 44 44" width="44" height="44" fill="none">
        <circle cx="22" cy="22" r="10" stroke="var(--primary)" stroke-width="2.5"/>
        <line x1="22" y1="2" x2="22" y2="14" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round"/>
        <line x1="22" y1="30" x2="22" y2="42" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round"/>
        <line x1="2" y1="22" x2="14" y2="22" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round"/>
        <line x1="30" y1="22" x2="42" y2="22" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round"/>
        <circle cx="22" cy="22" r="3" fill="var(--primary)"/>
      </svg>
    </div>
  </div>
  <!-- Footer -->
  <div style="padding:14px 16px calc(14px + env(safe-area-inset-bottom));background:var(--white);border-top:1px solid var(--surface-2);flex-shrink:0;">
    <div style="font-size:13px;color:var(--text-muted);margin-bottom:10px;min-height:18px;" id="pinAddressLabel">Move map to your location…</div>
    <button onclick="confirmPin()" style="width:100%;padding:14px;background:var(--primary);color:var(--white);border:none;border-radius:var(--radius-sm);font-size:15px;font-weight:700;cursor:pointer;">
      Confirm This Location
    </button>
  </div>
</div>

<!-- ══════════ MODAL: Trip Details ══════════ -->
<div class="modal-overlay" id="modal-trip-details" onclick="if(event.target===this)closeModal(null,'trip-details')">
  <div class="modal-content" onclick="event.stopPropagation()" style="max-width:420px;width:100%">
    <div class="modal-handle"></div>
    <div class="modal-header" style="text-align:left;margin-bottom:16px">
      <h2 style="font-size:18px">Trip Details</h2>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;font-size:14px;">
      <div style="display:flex;justify-content:space-between;">
        <span style="color:var(--text-muted)">Status</span>
        <span style="font-weight:600;text-transform:capitalize" id="td-status">—</span>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <span style="color:var(--text-muted)">Pickup</span>
        <span style="font-weight:500;text-align:right;max-width:60%" id="td-pickup">—</span>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <span style="color:var(--text-muted)">Drop-off</span>
        <span style="font-weight:500;text-align:right;max-width:60%" id="td-dropoff">—</span>
      </div>
      <div style="display:flex;justify-content:space-between;">
        <span style="color:var(--text-muted)">Fare</span>
        <span style="font-weight:700" id="td-fare">—</span>
      </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:20px;flex-wrap:wrap">
      <button id="td-reorder-btn" class="btn-primary" style="flex:1;min-width:120px">Book Again</button>
      <button id="td-receipt-btn" onclick="viewTripDetailReceipt()" style="flex:1;min-width:120px;background:var(--surface);border:1.5px solid var(--border);border-radius:8px;padding:10px;font-size:14px;font-weight:600;color:var(--text-primary);cursor:pointer;display:none;">View Receipt</button>
      <button id="td-pdf-btn" onclick="downloadTripDetailPDF()" style="flex:1;min-width:120px;background:var(--surface);border:1.5px solid var(--border);border-radius:8px;padding:10px;font-size:14px;font-weight:600;color:var(--text-primary);cursor:pointer;display:none;">⬇ PDF</button>
    </div>
  </div>
</div>
