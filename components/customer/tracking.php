<!-- ══════════ TRACKING SCREEN ══════════ -->
<div class="screen" id="screen-tracking">
  <div class="tracking-header">
    <button class="back-btn" onclick="goBack()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </button>
    <h3>Live Tracking</h3>
    <div class="header-badge">Live</div>
    <button type="button" aria-disabled="true" onclick="showUnavailableFeature('Manual trip completion', 'Trip completion is controlled by driver delivery actions. The simulation shortcut is disabled in production.')" style="margin-left:10px;background:var(--surface-2);border:none;padding:6px 12px;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer;color:var(--text-secondary)">Completion controlled by driver</button>
  </div>
  <div class="tracking-map">
    <div class="tracking-map-bg"></div>
    <svg viewBox="0 0 100 60" preserveAspectRatio="none" style="position:absolute;inset:0;width:100%;height:100%;opacity:0.6">
      <path d="M0 18 Q25 16 50 20 T100 18" stroke="white" stroke-width="2.5" fill="none"/>
      <path d="M0 40 Q40 38 62 43 T100 40" stroke="white" stroke-width="2" fill="none"/>
      <path d="M30 0 Q28 30 32 60" stroke="white" stroke-width="2" fill="none"/>
      <path d="M70 0 Q68 30 72 60" stroke="white" stroke-width="1.5" fill="none"/>
    </svg>
    <!-- Moving rider -->
    <div class="moving-rider">
      <div class="rider-blip">
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2" width="16" height="16"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
      </div>
      <div class="rider-label" id="trackingMapDriverLabel">Searching</div>
    </div>
    <!-- Route line -->
    <svg viewBox="0 0 100 60" preserveAspectRatio="none" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none">
      <path d="M 22 38 Q 40 32 65 38" stroke="var(--info)" stroke-width="2" stroke-dasharray="4 3" fill="none" opacity="0.7"/>
    </svg>
    <!-- Destination pin -->
    <div style="position:absolute;bottom:22%;right:26%;display:flex;flex-direction:column;align-items:center">
      <div style="background:var(--navy);color:var(--white);font-size:10px;font-weight:700;padding:3px 9px;border-radius:8px;margin-bottom:4px;font-family:'Syne',sans-serif">Drop-off</div>
      <div class="map-pin-head" style="width:34px;height:34px"></div>
      <div class="map-pin-shadow" style="width:14px;height:5px;margin-top:2px"></div>
    </div>
    <!-- ETA chip on map -->
    <div class="map-eta-chip" style="bottom:14px;left:14px">
      <div class="eta-chip-dot"></div>
      <div>
        <div class="eta-chip-text" id="trackingEtaLabel">Finding driver</div>
        <div class="eta-chip-sub" id="trackingDistanceLabel">Live feed connected</div>
      </div>
    </div>
  </div>
  <div class="tracking-scroll">
    <div class="tracking-card">
      <div class="rider-status">
        <div class="rider-avatar">AK<div class="rider-online"></div></div>
        <div class="rider-info-col">
          <div class="rider-profile-top">
            <div class="rider-name" id="trackingDriverName">Searching for driver</div>
            <div class="rider-plate" id="trackingDriverPlate">Pending</div>
          </div>
          <div class="rider-rating">
            <svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <span class="rider-rating-text" id="trackingDriverRating">Waiting for assignment</span>
          </div>
        </div>
      </div>
      <div class="rider-contact">
        <button class="contact-btn call" onclick="showToast('Starting masked relay call...')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.21h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          Call
        </button>
        <button class="contact-btn" onclick="openSupportTicket('general')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          Message
        </button>
        <button class="contact-btn" onclick="sendSafetyReport()">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2" width="14" height="14"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          SOS
        </button>
      </div>
      <div class="eta-bar">
        <div class="eta-item">
          <div class="eta-label">ETA</div>
          <div class="eta-value" id="etaMinutes">--</div>
          <div class="eta-unit">minutes</div>
        </div>
        <div class="eta-divider"></div>
        <div class="eta-item">
          <div class="eta-label">Distance</div>
          <div class="eta-value" id="trackingDistanceValue">--</div>
          <div class="eta-unit">km away</div>
        </div>
        <div class="eta-divider"></div>
        <div class="eta-item">
          <div class="eta-label">Trip Fare</div>
          <div class="eta-value" id="trackingFareValue" style="font-size:18px">₦--</div>
          <div class="eta-unit">fixed price</div>
        </div>
      </div>
      <div class="receipt-box" id="manualPaymentPanel" style="display:none;margin-top:14px;">
        <div class="receipt-label">Manual Bank Transfer</div>
        <div class="receipt-row total"><span>Amount to pay</span><span id="manualPaymentAmount">₦--</span></div>
        <div class="receipt-row"><span>Bank</span><span id="manualPaymentBank">Set by admin</span></div>
        <div class="receipt-row"><span>Account Name</span><span id="manualPaymentAccountName">Set by admin</span></div>
        <div class="receipt-row"><span>Account No.</span><span id="manualPaymentAccountNumber">Set by admin</span></div>
        <div class="receipt-payment" style="align-items:flex-start;flex-direction:column;gap:8px;">
          <div id="manualPaymentInstructions">Transfer the exact fare, then upload your receipt for review.</div>
          <input class="loc-input" id="manualPaymentRef" placeholder="Bank reference / sender name (optional)" style="width:100%;font-size:13px;">
          <input class="loc-input" id="manualPaymentProof" type="file" accept="image/jpeg,image/png,application/pdf" style="width:100%;font-size:13px;">
          <button class="btn-primary" type="button" onclick="uploadManualPaymentProof()" style="height:42px;font-size:13px;">Upload Payment Proof</button>
          <div id="manualPaymentStatus" style="font-size:12px;color:var(--text-muted);"></div>
        </div>
      </div>
      <div class="tracking-steps">
        <div class="t-step">
          <div class="t-step-dot done"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="11" height="11"><polyline points="20 6 9 17 4 12"/></svg></div>
          <div class="t-step-info"><h4>Order Confirmed</h4><p>Today at 2:14 PM · #SD-00928</p></div>
        </div>
        <div class="t-step">
          <div class="t-step-dot done"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="11" height="11"><polyline points="20 6 9 17 4 12"/></svg></div>
          <div class="t-step-info"><h4>Rider Assigned</h4><p>Amina picked up your package</p></div>
        </div>
        <div class="t-step">
          <div class="t-step-dot active"><svg viewBox="0 0 24 24" fill="currentColor" width="8" height="8"><circle cx="12" cy="12" r="5"/></svg></div>
          <div class="t-step-info"><h4>On the Way</h4><p>Heading to drop-off location now</p></div>
        </div>
        <div class="t-step">
          <div class="t-step-dot pending"></div>
          <div class="t-step-info"><h4>Delivered</h4><p style="color:var(--text-muted)">Awaiting delivery confirmation</p></div>
        </div>
      </div>
    </div>
    <!-- Safety panel -->
    <div class="safety-panel">
      <div class="safety-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <div class="safety-text">
        <h4>Trip is secured</h4>
        <p id="trackingPinText">PIN required on delivery. Share your trip only with trusted contacts.</p>
      </div>
      <button class="safety-btn" onclick="shareTrackingLink()">Share</button>
    </div>
  </div>
</div>
