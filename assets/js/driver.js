// ===== ONBOARDING =====
const driverInitialContext = window.driverInitialContext;
const IDIBIA_PUSHER_CONFIG = window.idibiaPusherConfig;

let idibiaDriverPusher = null;
let idibiaDriverChannelName = null;

function initDriverPusher() {
  if (!IDIBIA_PUSHER_CONFIG?.enabled || typeof Pusher === 'undefined') return null;
  if (idibiaDriverPusher) return idibiaDriverPusher;
  idibiaDriverPusher = new Pusher(IDIBIA_PUSHER_CONFIG.key, {
    cluster: IDIBIA_PUSHER_CONFIG.cluster,
    channelAuthorization: {
      endpoint: IDIBIA_PUSHER_CONFIG.authEndpoint,
      transport: 'ajax',
      params: { _nonce: IDIBIA_PUSHER_CONFIG.authNonce }
    }
  });
  return idibiaDriverPusher;
}

function subscribeToDriverRealtime() {
  const pusher = initDriverPusher();
  const driverId = Number(driverInitialContext.driver_id || 0);
  if (!pusher || !driverId || !driverInitialContext.is_approved) return;
  const channelName = `private-driver-${driverId}`;
  if (idibiaDriverChannelName === channelName) return;
  if (idibiaDriverChannelName) pusher.unsubscribe(idibiaDriverChannelName);
  idibiaDriverChannelName = channelName;
  const channel = pusher.subscribe(channelName);
  channel.bind('driver.offers.updated', data => {
    if (data?.event_type === 'dispatch_offers_created') showToast('New delivery request available');
    fetchDriverOffers();
  });
}

function setAppHeight() {
  document.documentElement.style.setProperty('--app-height', `${window.innerHeight}px`);
}
setAppHeight();
window.addEventListener('resize', setAppHeight);
window.addEventListener('orientationchange', setAppHeight);

let driverStep = 1;
let driverAuthenticated = false;
let driverAwaitingEmailVerification = false;
let driverAuthMode = 'signup';
const driverTitles = ['Account Setup','Identity Verification','Vehicle Information','Financial & Emergency','Application Submitted'];
let driverFiles = {};

async function parseDriverJson(response) {
  const rawText = await response.text();
  try {
    return JSON.parse(rawText);
  } catch (err) {
    console.error('Raw driver auth response:', rawText);
    throw new Error('Invalid server response');
  }
}

async function driverAuthPost(endpoint, body) {
  const response = await fetch(endpoint, {
    method: 'POST',
    body,
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json' }
  });

  return parseDriverJson(response);
}

function setDriverAuthMode(mode) {
  driverAuthMode = mode === 'login' ? 'login' : 'signup';
  document.getElementById('driverSignupTab').classList.toggle('active', driverAuthMode === 'signup');
  document.getElementById('driverLoginTab').classList.toggle('active', driverAuthMode === 'login');
  document.getElementById('driverSignupPanel').classList.toggle('active', driverAuthMode === 'signup');
  document.getElementById('driverLoginPanel').classList.toggle('active', driverAuthMode === 'login');
  document.getElementById('driverSignupTab').setAttribute('aria-selected', driverAuthMode === 'signup');
  document.getElementById('driverLoginTab').setAttribute('aria-selected', driverAuthMode === 'login');
  syncDriverRegistrationFields();
  updateDriver();
}

async function driverLogin() {
  const identifier = document.getElementById('driverLoginPhone').value.trim();
  const password = document.getElementById('driverLoginPassword').value;

  if (!identifier || !password) {
    showToast('Enter your phone/email and password.');
    return false;
  }

  const body = new FormData();
  body.append('action', 'login');
  body.append('phone', identifier);
  body.append('password', password);
  body.append('language', document.getElementById('driverLanguage').value);
  body.append('middle_name', document.getElementById('driverMiddleName').value.trim());
  body.append('date_of_birth', document.getElementById('driverDob').value);
  body.append('gender', document.getElementById('driverGender').value);
  body.append('state_of_origin', document.getElementById('driverStateOrigin').value);
  body.append('vehicle_type', getSelectedValue('vg1', 'bike'));

  try {
    const json = await driverAuthPost('driver-login-handler.php', body);
    if (json.success) {
      Object.assign(driverInitialContext, json.data || {}, { logged_in: true });
      if (!driverInitialContext.email_verified) {
        driverAuthenticated = false;
        driverAwaitingEmailVerification = true;
        const help = document.getElementById('driverEmailVerifyHelp');
        if (help) help.textContent = 'Welcome back. Enter the 5-digit code we sent to ' + identifier + ' to unlock KYC.';
        showToast('Please verify your email to continue your driver application.');
        driverStep = 1;
        updateDriver();
        document.getElementById('driverVerifyCode')?.focus();
        return true;
      }
      driverAuthenticated = true;
      driverAwaitingEmailVerification = false;
      showToast(json.data?.first_name ? `Welcome back, ${json.data.first_name} 👋` : 'Welcome back 👋');
      if (driverInitialContext.is_approved) {
        goToDashboard();
      } else if (driverInitialContext.kyc_status === 'under_review') {
        driverStep = 5;
        updateDriver();
      } else {
        driverStep = 2; // Move to identity verification
        updateDriver();
      }
      return true;
    }

    showToast(json.data?.message || 'Driver login failed.');
  } catch (err) {
    showToast('Could not reach Idibia right now. Please try again.');
  }

  return false;
}

async function submitDriverSignup() {
  const firstName = document.getElementById('driverFirstName').value.trim();
  const lastName = document.getElementById('driverLastName').value.trim();
  const email = document.getElementById('driverEmail').value.trim();
  const phone = document.getElementById('driverPhone').value.trim();
  const password = document.getElementById('driverPassword').value;

  if (!firstName || !lastName || !email || !phone || !password) {
    showToast('Complete your name, email, phone, and password first.');
    return false;
  }

  const body = new FormData();
  body.append('action', 'signup');
  body.append('first_name', firstName);
  body.append('last_name', lastName);
  body.append('email', email);
  body.append('phone', phone);
  body.append('password', password);
  body.append('middle_name', document.getElementById('driverMiddleName').value.trim());
  body.append('date_of_birth', document.getElementById('driverDob').value);
  body.append('gender', document.getElementById('driverGender').value);
  body.append('state_of_origin', document.getElementById('driverStateOrigin').value);
  body.append('vehicle_type', getSelectedValue('vg1', 'bike'));

  try {
    const json = await driverAuthPost('driver-register-handler.php', body);
    if (json.success) {
      driverAuthenticated = false;
      driverAwaitingEmailVerification = true;
      Object.assign(driverInitialContext, json.data || {}, { logged_in: false, email_verified: false });
      const help = document.getElementById('driverEmailVerifyHelp');
      if (help) help.textContent = 'We sent a 5-digit code to ' + email + '. Paste it below to unlock KYC.';
      showToast(json.data?.message || 'Driver account created. Please verify your email.');
      updateDriver();
      document.getElementById('driverVerifyCode')?.focus();
      return false;
    }

    showToast(json.data?.message || 'Driver registration failed.');
  } catch (err) {
    showToast('Could not reach Idibia right now. Please try again.');
  }

  return false;
}

function syncDriverRegistrationFields() {
  const isSignup = driverAuthMode === 'signup';
  document.querySelectorAll('.driver-register-only').forEach(el => {
    el.classList.toggle('hidden', !isSignup);
  });
}

function updateDriver() {
  syncDriverRegistrationFields();
  for (let i = 1; i <= 5; i++) {
    document.getElementById('dstep-' + i).classList.toggle('active', i === driverStep);
  }
  document.getElementById('driverStepNum').textContent = driverStep;
  document.getElementById('driverStepTitle').textContent = driverTitles[driverStep - 1];
  document.getElementById('driverProgress').style.width = (driverStep / 5 * 100) + '%';

  const backBtn = document.getElementById('driverBack');
  const nextBtn = document.getElementById('driverNext');
  const headerWrap = document.getElementById('driverHeaderWrap');
  const progressWrap = document.getElementById('driverProgressWrap');
  const footerWrap = document.getElementById('driverFooterWrap');
  const emailVerifyPanel = document.getElementById('driverEmailVerifyPanel');
  if (emailVerifyPanel) emailVerifyPanel.classList.toggle('hidden', !(driverStep === 1 && driverAwaitingEmailVerification));

  backBtn.style.visibility = driverStep === 1 ? 'hidden' : 'visible';

  if (driverStep === 1) {
    nextBtn.innerHTML = driverAwaitingEmailVerification
      ? 'Verify email <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>'
      : (driverAuthMode === 'login'
        ? 'Sign in <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>'
        : 'Continue <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>');
  } else {
    nextBtn.innerHTML = 'Continue <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
  }

  if (driverStep === 5) {
    headerWrap.style.display = 'none';
    progressWrap.style.display = 'none';
    footerWrap.style.display = 'none';
  } else {
    headerWrap.style.display = 'flex';
    progressWrap.style.display = 'block';
    footerWrap.style.display = 'flex';
  }
}

async function verifyDriverEmail() {
  const codeInput = document.getElementById('driverVerifyCode');
  const code = (codeInput?.value || '').replace(/\D/g, '').slice(0, 5);
  if (code.length !== 5) {
    showToast('Enter the 5-digit code from your email.');
    codeInput?.focus();
    return false;
  }
  const body = new FormData();
  body.append('code', code);
  try {
    const json = await driverAuthPost('driver-verify-handler.php', body);
    if (json.success) {
      driverAuthenticated = true;
      driverAwaitingEmailVerification = false;
      Object.assign(driverInitialContext, json.data || {}, { logged_in: true, email_verified: true });
      showToast(json.data?.message || 'Email verified. Continue your driver application.');
      driverStep = 2;
      updateDriver();
      document.getElementById('driverContent').scrollTop = 0;
      return true;
    }
    showToast(json.data?.message || 'Could not verify your email.');
  } catch (err) {
    showToast('Could not reach Idibia right now. Please try again.');
  }
  return false;
}

async function resendDriverVerifyCode() {
  try {
    const json = await driverAuthPost('driver-resend-code.php', new FormData());
    showToast(json.success ? (json.data?.message || 'New code sent! Check your inbox.') : (json.data?.message || 'Could not resend code.'));
  } catch (err) {
    showToast('Could not resend code right now. Please try again.');
  }
}

async function submitDriverKyc() {
  if (!driverInitialContext.email_verified || !driverInitialContext.nonces?.driver_kyc) {
    showToast('Please verify your email before submitting KYC.');
    return false;
  }
  const body = new FormData();
  body.append('_nonce', driverInitialContext.nonces?.driver_kyc || '');
  body.append('vehicle_type', getSelectedValue('vg1', 'bike'));
  body.append('vehicle_year', document.getElementById('driverVehicleYear').value.trim());
  body.append('vehicle_model', document.getElementById('driverVehicleManufacturer').value.trim());
  body.append('vehicle_plate', document.getElementById('driverVehiclePlate').value.trim());
  body.append('vehicle_color', document.getElementById('driverVehicleColor').value.trim());
  body.append('account_holder_name', document.getElementById('driverAccountHolder').value.trim());
  body.append('account_number', document.getElementById('driverAccountNumber').value.trim());
  body.append('bank_name', document.getElementById('driverBankName').value);
  body.append('emergency_name', document.getElementById('driverEmergencyName').value.trim());
  body.append('emergency_relationship', document.getElementById('driverEmergencyRelationship').value);
  body.append('emergency_phone', document.getElementById('driverEmergencyPhone').value.trim());
  body.append('emergency_address', document.getElementById('driverEmergencyAddress').value.trim());
  body.append('billing_type', getSelectedValue('billingGroup', 'personal'));

  for (const [key, file] of Object.entries(driverFiles)) {
    body.append(key, file);
  }

  showToast('Submitting your application...');
  try {
    const response = await fetch('driver-kyc-handler.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    const json = await parseDriverJson(response);
    if (json.success) {
      driverInitialContext.kyc_status = 'under_review';
      return true;
    }
    showToast(json.data?.message || 'Failed to submit KYC.');
  } catch (err) { showToast('Could not reach server. Please try again.'); }
  return false;
}

async function driverNext() {
  if (driverStep === 1 && !driverAuthenticated) {
    if (driverAwaitingEmailVerification) {
      await verifyDriverEmail();
      return;
    }
    if (driverAuthMode === 'login') {
      await driverLogin();
      return;
    }

    await submitDriverSignup();
    return;
  }

  if (driverStep >= 2 && driverStep <= 3 && !validateDriverStep(driverStep)) return;

  if (driverStep === 4) {
    if (!validateDriverStep(4)) return;
    const kycSubmitted = await submitDriverKyc();
    if (!kycSubmitted) return;
  }

  if (driverStep < 5) {
    driverStep++;
    updateDriver();
    document.getElementById('driverContent').scrollTop = 0;
  }
}
function driverPrev() {
  if (driverStep > 1) {
    driverStep--;
    updateDriver();
    document.getElementById('driverContent').scrollTop = 0;
  }
}

function selVehicle(el, groupId) {
  const parent = el.closest('[id=' + groupId + ']') || el.parentElement;
  parent.querySelectorAll('.vehicle-card').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
}

function getSelectedValue(groupId, defaultVal) {
  const group = document.getElementById(groupId);
  if (!group) return defaultVal;
  const active = group.querySelector('.active');
  return active && active.hasAttribute('data-value') ? active.getAttribute('data-value') : defaultVal;
}

function chooseKycFile(el) {
  const fieldName = el.getAttribute('data-field');
  const input = document.getElementById('driverKycFileInput');
  input.onchange = function(e) {
    const file = e.target.files[0];
    if (file) { driverFiles[fieldName] = file; el.classList.remove('error'); markDone(el, file.name); }
    input.value = '';
  };
  input.click();
}

function markDone(el, filename = '') {
  el.classList.add('done');
  el.querySelector('p').innerHTML = '<strong style="color:var(--success)">✓ File selected</strong>';
  const small = el.querySelector('small');
  if (small) small.textContent = filename || 'Tap to replace';
}

function goToDashboard() {
  if (!driverInitialContext.is_approved) {
    driverStep = driverInitialContext.kyc_status === 'under_review' ? 5 : 1;
    document.getElementById('screen-driver-dash').classList.remove('active');
    document.getElementById('screen-driver').classList.add('active');
    updateDriver();
    return;
  }
  document.getElementById('screen-driver').classList.remove('active');
  document.getElementById('screen-driver-dash').classList.add('active');
  subscribeToDriverRealtime();
}

// ===== DASHBOARD =====
let isOnline = true;
let currentTab = 'home';

async function toggleOnline() {
  if (!driverInitialContext.is_approved) {
    showToast('Your account must be approved before going online.');
    return;
  }

  const requestedState = !isOnline;
  const body = new FormData();
  body.append('online', requestedState ? '1' : '0');
  body.append('_nonce', driverInitialContext.nonces?.toggle_online || '');

  try {
    const response = await fetch('/driver-toggle-online.php', {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });
    const json = await parseDriverJson(response);
    if (!json.success) {
      showToast(json.data?.message || 'Could not update online status.');
      return;
    }

    isOnline = !!json.data?.is_online;
    driverInitialContext.is_online = isOnline;
    const toggle = document.getElementById('onlineToggle');
    toggle.classList.toggle('online', isOnline);
    toggle.classList.toggle('offline', !isOnline);
    document.getElementById('onlineLabel').textContent = isOnline ? "Online" : "Offline";
    showToast(isOnline ? '✓ You are now online' : 'You are now offline');
    subscribeToDriverRealtime();
    fetchDriverOffers();
  } catch (err) {
    showToast('Could not update online status. Please try again.');
  }
}


async function fetchDriverOffers() {
  if (!driverAuthenticated || !driverInitialContext.is_approved || !isOnline) {
    renderDriverOffers([]);
    return;
  }
  try {
    const body = new FormData();
    body.append('_nonce', driverInitialContext.nonces?.driver_action || '');

    // Attempt to get real location if browser supports it; never send a fake GPS location.
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        async (position) => {
          body.append('lat', position.coords.latitude);
          body.append('lng', position.coords.longitude);
          body.append('heading', position.coords.heading || 0);
          updateMapLocation(position.coords.latitude, position.coords.longitude);
          const response = await fetch('/driver-heartbeat-api.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
          const json = await parseDriverJson(response);
          if (json.success) renderDriverOffers(json.data?.offers || [], json.data?.active_trip || null);
        },
        async (err) => {
          showToast('Location permission is needed for nearby dispatch. Turn it on to receive distance-based offers.');
          const response = await fetch('/driver-heartbeat-api.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
          const json = await parseDriverJson(response);
          if (json.success) renderDriverOffers(json.data?.offers || [], json.data?.active_trip || null);
        },
        { timeout: 5000 }
      );
    } else {
      showToast('This browser does not support live location. Offers may be limited until location is available.');
      const response = await fetch('/driver-heartbeat-api.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      const json = await parseDriverJson(response);
      if (json.success) renderDriverOffers(json.data?.offers || [], json.data?.active_trip || null);
    }
  } catch (err) {
    // Keep the last rendered state; polling should not interrupt the driver UI.
  }
}

function renderDriverOffers(offers, activeTrip = null) {
  const container = document.getElementById('driverOfferContainer');
  if (!container) return;

  if (activeTrip) {
    if (activeTrip.pickup_location && activeTrip.dropoff_location) {
        // Wait till next tick to draw
        setTimeout(() => drawRouteOnMap([[activeTrip.pickup_location.lat, activeTrip.pickup_location.lng], [activeTrip.dropoff_location.lat, activeTrip.dropoff_location.lng]]), 10);
    }
    container.innerHTML = renderActiveTrip(activeTrip);
    return;
  }

  if (!offers.length) {
    container.innerHTML = `
      <div class="trip-request-card" id="driverNoOfferCard">
        <div class="trq-header"><div class="trq-tag">Dispatch</div></div>
        <div class="trq-fee">No live requests <span>· stay online</span></div>
        <div class="trq-meta"><div class="trq-meta-chip">Waiting for nearby bookings</div></div>
      </div>`;
    return;
  }

  container.innerHTML = offers.map(offer => `
    <div class="trip-request-card" data-offer-id="${offer.offer_id}">
      <div class="trq-header">
        <div class="trq-tag">New Request</div>
        <div class="trq-timer-wrap"><div class="trq-timer">${Math.max(0, offer.expires_in || 0)}</div><div class="trq-timer-label">sec</div></div>
      </div>
      <div class="trq-fee">₦${Number(offer.fare || 0).toLocaleString()} <span>· ${Number(offer.pickup_distance_km || offer.distance_km || 0).toFixed(1)} km away</span></div>
      <div class="trq-meta">
        <div class="trq-meta-chip">~${offer.duration_mins || 0} mins</div>
        <div class="trq-meta-chip">${escapeHtml(offer.service_category || 'Package')}</div>
        <div class="trq-meta-chip">${escapeHtml(offer.vehicle_type || 'bike')}</div>
      </div>
      <div class="trq-route">
        <div class="trq-route-line"></div>
        <div class="trq-point"><div class="trq-dot from"></div><div><div style="font-size:11px;color:var(--slate-light);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:2px">Pickup</div><div class="trq-point-addr">${escapeHtml(offer.pickup_address)}</div></div></div>
        <div class="trq-point"><div class="trq-dot to"></div><div><div style="font-size:11px;color:var(--slate-light);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:2px">Drop-off</div><div class="trq-point-addr">${escapeHtml(offer.dropoff_address)}</div></div></div>
      </div>
      <div class="trq-actions">
        <button class="trq-decline" onclick="driverOfferAction('decline_offer', ${offer.offer_id})">Decline</button>
        <button class="trq-accept" onclick="driverOfferAction('accept_offer', ${offer.offer_id})">Accept Delivery</button>
      </div>
    </div>`).join('');
}

function renderActiveTrip(trip) {
  const nextAction = {
    accepted: ['start_to_pickup', 'Start to pickup'],
    arriving: ['arrived_pickup', 'Arrived at pickup'],
    arrived_pickup: ['picked_up', 'Picked up'],
    picked_up: ['arrived_dropoff', 'Arrived at drop-off'],
    arrived_dropoff: ['complete', 'Complete with PIN']
  }[trip.dispatch_status];
  const navTarget = trip.dispatch_status === 'picked_up' || trip.dispatch_status === 'arrived_dropoff'
    ? `${trip.dropoff_lat || ''},${trip.dropoff_lng || ''}`
    : `${trip.pickup_lat || ''},${trip.pickup_lng || ''}`;
  const navUrl = navTarget.replace(',', '').trim() ? `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(navTarget)}` : '#';
  return `
    <div class="trip-request-card">
      <div class="trq-header"><div class="trq-tag">Active Trip · ${escapeHtml(trip.trip_ref || '')}</div></div>
      <div class="trq-fee">₦${Number(trip.fare || 0).toLocaleString()} <span>· ${escapeHtml(trip.dispatch_status || '')}</span></div>
      <div class="trq-meta">
        <div class="trq-meta-chip">Next: ${escapeHtml(nextAction?.[1] || 'Await update')}</div>
        <div class="trq-meta-chip">Customer: ${escapeHtml(trip.customer?.name || 'Customer')}</div>
        <div class="trq-meta-chip">${escapeHtml(trip.customer?.masked_phone || 'Masked relay')}</div>
      </div>
      <div class="trq-route">
        <div class="trq-route-line"></div>
        <div class="trq-point"><div class="trq-dot from"></div><div><div style="font-size:11px;color:var(--slate-light);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:2px">Pickup</div><div class="trq-point-addr">${escapeHtml(trip.pickup_address)}</div></div></div>
        <div class="trq-point"><div class="trq-dot to"></div><div><div style="font-size:11px;color:var(--slate-light);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:2px">Drop-off</div><div class="trq-point-addr">${escapeHtml(trip.dropoff_address)}</div></div></div>
      </div>
      <div class="trq-actions">
        <button class="trq-decline" onclick="window.open('${navUrl}', '_blank')">Navigate</button>
        <button class="trq-decline" onclick="window.currentActiveTripId = ${trip.trip_id}; callTripCustomer('${encodeURIComponent(trip.customer?.phone || '')}')">Contact</button>
        <button class="trq-decline" onclick="driverSafetyReport(${trip.trip_id})">Safety</button>
        ${nextAction ? `<button class="trq-accept" onclick="driverTripAction('${nextAction[0]}', ${trip.trip_id})">${nextAction[1]}</button>` : ''}
      </div>
      ${trip.delivery_pin_required ? '<div class="info-note" style="margin-top:12px">Delivery PIN required before completing handoff. Ask the customer for the PIN only at delivery.</div>' : ''}
    </div>`;
}


async function callTripCustomer(encodedPhone) {
  const phone = decodeURIComponent(encodedPhone || '').replace(/[^\d+]/g, '');
  if (!phone) return showToast('Customer phone is not available.');

  if (window.currentActiveTripId) {
      const body = new FormData();
      body.append('action', 'log_event');
      body.append('trip_id', window.currentActiveTripId);
      body.append('event_type', 'driver_contacted_customer');
      // Fire and forget
      fetch('/driver-trip-action-api.php', { method: 'POST', body, credentials: 'same-origin' }).catch(()=>{});
  }

  window.location.href = `tel:${phone}`;
}


async function driverSupportRequest(tripId, category = 'driver_support') {
  const message = prompt('Tell support what happened:');
  if (!message) return;
  const body = new FormData();
  body.append('action', 'create_ticket');
  body.append('trip_id', tripId);
  body.append('category', category);
  body.append('message', message.trim());
  body.append('_nonce', driverInitialContext.nonces?.support_action || '');
  try {
    const response = await fetch('/support-api.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    const json = await parseDriverJson(response);
    showToast(json.data?.message || (json.success ? 'Support ticket opened.' : 'Could not open support ticket.'));
  } catch (err) {
    showToast('Could not open support ticket.');
  }
}

function driverSafetyReport(tripId) {
  window.currentActiveTripId = tripId;
  const modal = document.getElementById('modal-sos');
  if (modal) {
      modal.classList.add('show');
  }
}

function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('show');
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('show');
}

async function submitSosReport() {
  const descEl = document.getElementById('sosDescription');
  const catEl = document.getElementById('sosCategory');
  const sevEl = document.getElementById('sosSeverity');
  const message = descEl ? descEl.value.trim() : '';
  const category = catEl ? catEl.value : 'other';
  const severity = sevEl ? sevEl.value : 'high';

  if (!message) {
      showToast('Please describe the situation.');
      return;
  }

  const body = new FormData();
  body.append('action', 'safety_report');
  if (window.currentActiveTripId) body.append('trip_id', window.currentActiveTripId);
  body.append('category', category);
  body.append('severity', severity);
  body.append('message', message);
  body.append('_nonce', driverInitialContext.nonces?.support_action || '');

  try {
    const response = await fetch('/support-api.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    const json = await parseDriverJson(response);
    if (json.success) {
        showToast(json.data?.message || 'Safety report sent.');
        closeModal('modal-sos');
        if (descEl) descEl.value = '';
    } else {
        showToast(json.data?.message || 'Could not send safety report.');
    }
  } catch (err) {
    showToast('Connection error sending safety report.');
  }
}

async function submitDriverCustomerRating(tripId) {
  const rating = prompt('Rate the customer from 1 to 5 stars:', '5');
  if (!rating) return;
  const numeric = Number(rating);
  if (!Number.isInteger(numeric) || numeric < 1 || numeric > 5) return showToast('Choose a rating from 1 to 5.');
  const comment = prompt('Optional note about this customer:') || '';
  const body = new FormData();
  body.append('trip_id', tripId);
  body.append('rating', numeric);
  body.append('comment', comment.trim());
  body.append('_nonce', driverInitialContext.nonces?.support_action || '');
  try {
    const response = await fetch('/rating-api.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    const json = await parseDriverJson(response);
    showToast(json.data?.message || (json.success ? 'Rating saved.' : 'Could not save rating.'));
  } catch (err) {
    showToast('Could not save rating.');
  }
}

async function driverOfferAction(action, offerId) {
  const body = new FormData();
  body.append('action', action);
  body.append('offer_id', offerId);
  body.append('_nonce', driverInitialContext.nonces?.driver_action || '');
  await sendDriverTripAction(body);
}

async function driverTripAction(action, tripId) {
  const body = new FormData();
  body.append('action', action);
  body.append('trip_id', tripId);
  if (action === 'complete') {
    const pin = prompt('Enter customer delivery PIN');
    if (!pin) return;
    body.append('delivery_pin', pin.trim());
  }
  body.append('_nonce', driverInitialContext.nonces?.driver_action || '');
  await sendDriverTripAction(body);
}

async function sendDriverTripAction(body) {
  try {
    const action = body.get('action');
    const tripId = body.get('trip_id');
    const response = await fetch('/driver-trip-action-api.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    const json = await parseDriverJson(response);
    showToast(json.data?.message || (json.success ? 'Trip updated.' : 'Could not update trip.'));
    if (json.success) {
      fetchDriverOffers();
      if (action === 'complete' && tripId) submitDriverCustomerRating(Number(tripId));
    }
  } catch (err) {
    showToast('Could not update trip. Please try again.');
  }
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c]));
}

function renderDriverProfile() {
    const ctx = window.driverInitialContext;
    if (!ctx || !ctx.logged_in) return;

    // Display basic info
    const nameDisplay = document.getElementById('profileNameDisplay');
    if (nameDisplay) nameDisplay.textContent = ctx.full_name;

    const imgDisplay = document.getElementById('profileAvatarImg');
    const initialsDisplay = document.getElementById('profileAvatarInitials');
    const dashHomeAvatar = document.getElementById('dashHomeAvatar');
    const dashHomeAvatarInitials = document.getElementById('dashHomeAvatarInitials');
    const dashHomeName = document.getElementById('dashHomeName');

    if (dashHomeName) dashHomeName.textContent = ctx.full_name;

    const avatarToUse = ctx.avatar_path || ctx.selfie_path;
    const baseUploadUrl = window.driverInitialContext.upload_baseurl || '';

    if (avatarToUse) {
        if (imgDisplay) {
            imgDisplay.src = `${baseUploadUrl}/${avatarToUse}`;
            imgDisplay.style.display = 'block';
        }
        if (initialsDisplay) initialsDisplay.style.display = 'none';

        if (dashHomeAvatar) {
            dashHomeAvatar.src = `${baseUploadUrl}/${avatarToUse}`;
            dashHomeAvatar.style.display = 'block';
        }
        if (dashHomeAvatarInitials) dashHomeAvatarInitials.style.display = 'none';
    } else {
        if (imgDisplay) imgDisplay.style.display = 'none';
        if (dashHomeAvatar) dashHomeAvatar.style.display = 'none';

        const parts = (ctx.full_name || '').trim().split(' ');
        let initials = '';
        if (parts.length > 0 && parts[0]) initials += parts[0][0];
        if (parts.length > 1 && parts[parts.length - 1]) initials += parts[parts.length - 1][0];
        initials = initials.toUpperCase() || '?';

        if (initialsDisplay) {
            initialsDisplay.textContent = initials;
            initialsDisplay.style.display = 'flex';
        }
        if (dashHomeAvatarInitials) {
            dashHomeAvatarInitials.textContent = initials;
            dashHomeAvatarInitials.style.display = 'flex';
        }
    }

    const statsDisplay = document.getElementById('profileStatsDisplay');
    if (statsDisplay) {
        const starSvg = `<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>`;
        const emptyStarSvg = `<svg viewBox="0 0 24 24" width="16" height="16" fill="var(--surface-3)" stroke="var(--surface-3)" stroke-width="0"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>`;
        const halfStarSvg = `<svg viewBox="0 0 24 24" width="16" height="16" fill="url(#halfGrad)" stroke="var(--surface-3)" stroke-width="0"><defs><linearGradient id="halfGrad"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="var(--surface-3)"/></linearGradient></defs><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>`;

        if (!ctx.total_trips || ctx.total_trips === 0 || ctx.rating == 0) {
            let svgStarsHtml = '';
            for (let i = 0; i < 5; i++) svgStarsHtml += emptyStarSvg;
            statsDisplay.innerHTML = `<span class="profile-star" style="display:inline-flex;gap:2px;align-items:center;color:var(--surface-3);">${svgStarsHtml}</span> No ratings yet`;
        } else {
            const rating = parseFloat(ctx.rating) || 0;
            const fullStars = Math.floor(rating);
            const halfStar = (rating - fullStars) >= 0.5 ? 1 : 0;
            const emptyStars = 5 - fullStars - halfStar;

            let svgStarsHtml = '';
            for (let i = 0; i < fullStars; i++) svgStarsHtml += starSvg;
            if (halfStar) svgStarsHtml += halfStarSvg;
            for (let i = 0; i < emptyStars; i++) svgStarsHtml += emptyStarSvg;

            statsDisplay.innerHTML = `<span class="profile-star" style="display:inline-flex;gap:2px;align-items:center;color:var(--gold);">${svgStarsHtml}</span> ${rating.toFixed(2)} · ${ctx.total_trips} trips total`;
        }
    }

    const verifyBadge = document.getElementById('profileVerifyBadge');
    if (verifyBadge) {
        verifyBadge.style.display = ctx.kyc_status === 'approved' ? 'block' : 'none';
    }

    const vehicleBadge = document.getElementById('profileVehicleBadge');
    if (vehicleBadge && ctx.vehicle_type) {
        vehicleBadge.textContent = ctx.vehicle_type;
    }

    // Populate rows
    const personalSub = document.getElementById('profilePersonalSub');
    if (personalSub) personalSub.textContent = `${ctx.full_name}, ${ctx.phone || 'No phone'}`;

    const bankSub = document.getElementById('profileBankSub');
    if (bankSub) bankSub.textContent = `${ctx.bank_name || 'No Bank'} · ${ctx.account_number ? '****' + ctx.account_number.slice(-4) : 'No Account'}`;

    const emergencySub = document.getElementById('profileEmergencySub');
    if (emergencySub) emergencySub.textContent = `${ctx.emergency_name || 'No Name'} · ${ctx.emergency_phone || 'No Phone'}`;

    // Populate forms
    const nameInput = document.getElementById('inputProfileName');
    if (nameInput) nameInput.value = ctx.full_name || '';

    const phoneInput = document.getElementById('inputProfilePhone');
    if (phoneInput) phoneInput.value = ctx.phone || '';

    const bankNameInput = document.getElementById('inputProfileBankName');
    if (bankNameInput) bankNameInput.value = ctx.bank_name || '';

    const accNumInput = document.getElementById('inputProfileAccountNumber');
    if (accNumInput) accNumInput.value = ctx.account_number || '';

    const emerNameInput = document.getElementById('inputProfileEmergencyName');
    if (emerNameInput) emerNameInput.value = ctx.emergency_name || '';

    const emerPhoneInput = document.getElementById('inputProfileEmergencyPhone');
    if (emerPhoneInput) emerPhoneInput.value = ctx.emergency_phone || '';
}

async function submitProfileForm(event, action) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', action);
    formData.append('_nonce', window.driverInitialContext.nonces?.driver_profile_update || '');

    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Saving...';

    try {
        const response = await fetch('/driver-profile-api.php', { method: 'POST', body: formData, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        const json = await parseDriverJson(response);
        if (json.success) {
            showToast(json.data?.message || 'Profile updated.');
            // Update context
            for (let [key, value] of formData.entries()) {
                if (key !== 'action' && key !== '_nonce') {
                    window.driverInitialContext[key] = value;
                }
            }
            renderDriverProfile();
            const modalId = form.closest('.modal')?.id;
            if (modalId) closeModal(modalId);
        } else {
            showToast(json.data?.message || 'Could not update profile.');
        }
    } catch (err) {
        showToast('Connection error updating profile.');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

async function uploadDriverAvatar(event) {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('action', 'upload_avatar');
    formData.append('selfie', file);
    formData.append('_nonce', window.driverInitialContext.nonces?.driver_profile_update || '');

    showToast('Uploading profile picture...');
    try {
        const response = await fetch('/driver-profile-api.php', { method: 'POST', body: formData, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        const json = await parseDriverJson(response);
        if (json.success) {
            showToast('Profile picture updated.');
            if (json.data?.avatar_path) {
                window.driverInitialContext.avatar_path = json.data.avatar_path;
                renderDriverProfile();
            }
        } else {
            showToast(json.data?.message || 'Could not upload picture.');
        }
    } catch (err) {
        showToast('Connection error uploading picture.');
    }
}

function switchTab(tab) {
  currentTab = tab;
  // panels
  document.querySelectorAll('.dash-panel').forEach(p => p.classList.remove('active'));
  const panel = document.getElementById('panel-' + tab);
  if (panel) panel.classList.add('active');
  // bottom nav
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const navItem = document.getElementById('nav-' + tab);
  if (navItem) navItem.classList.add('active');
  // sidebar icons
  document.querySelectorAll('.dash-sidebar-icon').forEach(i => i.classList.remove('active'));
  const sidebarItems = document.querySelectorAll('.dash-sidebar-icon');
  const tabOrder = ['home','earnings','trips','help','profile'];
  const idx = tabOrder.indexOf(tab);
  if (sidebarItems[idx]) sidebarItems[idx].classList.add('active');

  document.getElementById('dashBody').scrollTop = 0;
}

// Timer countdown
let trqCount = 14;
setInterval(() => {
  trqCount--;
  if (trqCount <= 0) trqCount = 14;
  const el = document.getElementById('trqTimer');
  if (el) {
    el.textContent = trqCount;
    el.classList.toggle('urgent', trqCount <= 5);
  }
}, 1000);

// Dynamic greeting
function setGreeting() {
  const h = new Date().getHours();
  const greet = h < 12 ? 'Good morning,' : h < 17 ? 'Good afternoon,' : 'Good evening,';
  const el = document.getElementById('dashGreeting');
  if (el) el.textContent = greet;
}
setGreeting();
setDriverAuthMode(driverAuthMode);
isOnline = !!driverInitialContext.is_online;

if (driverInitialContext.logged_in) {
  driverAuthenticated = true;
  if (!driverInitialContext.is_approved) {
    if (driverInitialContext.kyc_status === 'under_review') {
      driverStep = 5;
    } else if (driverInitialContext.kyc_status === 'pending') {
      driverStep = 2;
    }
    updateDriver();
  } else {
    goToDashboard();
    subscribeToDriverRealtime();
    fetchDriverOffers();
    setInterval(fetchDriverOffers, IDIBIA_PUSHER_CONFIG?.enabled ? 30000 : 15000);
  }
}

if (document.getElementById('onlineToggle')) {
  document.getElementById('onlineToggle').classList.toggle('online', isOnline);
  document.getElementById('onlineToggle').classList.toggle('offline', !isOnline);
  document.getElementById('onlineLabel').textContent = isOnline ? 'Online' : 'Offline';
}

// Toast
function showUnavailableFeature(title, detail) {
  showToast(`${title}: ${detail}`);
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), 3000);
}

updateDriver();
renderDriverProfile();

// ═══════════ LEAFLET MAP INTEGRATION ═══════════
let currentMap = null;
let currentMarker = null;

function initLeafletMap(containerId, lat, lng) {
  if (currentMap) {
    currentMap.remove();
    currentMap = null;
  }

  const el = document.getElementById(containerId);
  if (!el || !window.L) return;

  currentMap = L.map(containerId, { zoomControl: false }).setView([lat, lng], 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(currentMap);

  const iconHtml = `<div class="rider-avatar" style="width: 32px; height: 32px; font-size: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.2);"><div class="rider-online"></div></div>`;
  const icon = L.divIcon({ html: iconHtml, className: 'leaflet-custom-icon', iconSize: [32, 32], iconAnchor: [16, 16] });
  currentMarker = L.marker([lat, lng], { icon }).addTo(currentMap);
}

function updateMapLocation(lat, lng) {
  if (currentMap && currentMarker) {
    const newLatLng = new L.LatLng(lat, lng);
    currentMarker.setLatLng(newLatLng);
    currentMap.panTo(newLatLng);
  }
}

let currentRouteLayer = null;
function drawRouteOnMap(routeCoordinates) {
    if (!currentMap) return;
    if (currentRouteLayer) {
        currentMap.removeLayer(currentRouteLayer);
    }

    currentRouteLayer = L.polyline(routeCoordinates, {color: 'var(--navy)', weight: 5, opacity: 0.7}).addTo(currentMap);
    currentMap.fitBounds(currentRouteLayer.getBounds(), { padding: [50, 50] });
}

// ===== DASHBOARD RENDER =====
function formatCurrency(amount) {
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 0 }).format(amount);
}

function renderStars(rating) {
  let html = '';
  const fullStars = Math.floor(rating);
  const halfStar = (rating % 1) >= 0.5;
  const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);

  const fullSvg = `<svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" width="14" height="14"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`;
  const halfSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><defs><linearGradient id="halfGrad"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="transparent" stop-opacity="1"/></linearGradient></defs><polygon fill="url(#halfGrad)" points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`;
  const emptySvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`;

  for (let i = 0; i < fullStars; i++) html += fullSvg;
  if (halfStar) html += halfSvg;
  for (let i = 0; i < emptyStars; i++) html += emptySvg;

  return html;
}

function renderWeeklyChart(breakdownData) {
  const chartContainer = document.getElementById('weekly-bar-chart');
  if (!chartContainer) return;

  const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  const amounts = [0, 0, 0, 0, 0, 0, 0];

  Object.keys(breakdownData).forEach(dateStr => {
      const date = new Date(dateStr);
      let dayIdx = date.getDay() - 1;
      if (dayIdx === -1) dayIdx = 6;
      amounts[dayIdx] += breakdownData[dateStr];
  });

  const maxAmount = Math.max(...amounts, 1);
  const maxLabel = document.getElementById('weekly-chart-max');
  if(maxLabel) maxLabel.textContent = formatCurrency(maxAmount);

  let html = '';
  const todayDate = new Date();
  let todayIdx = todayDate.getDay() - 1;
  if (todayIdx === -1) todayIdx = 6;

  days.forEach((day, idx) => {
    const heightPct = (amounts[idx] / maxAmount) * 100;
    const isActive = idx === todayIdx ? 'active' : '';
    const activeColorStyle = idx === todayIdx ? 'color:var(--gold-dark);font-weight:700' : '';
    html += `
      <div class="week-bar-wrap" title="${formatCurrency(amounts[idx])}">
        <div class="week-bar ${isActive}" style="height:${heightPct}%"></div>
        <div class="week-day" style="${activeColorStyle}">${day}</div>
      </div>
    `;
  });

  chartContainer.innerHTML = html;
}

function applyTripFilters() {
    const status = document.getElementById('tripFilterSelect')?.value || 'all';
    const startDate = document.getElementById('tripFilterDateStart')?.value || null;
    const endDate = document.getElementById('tripFilterDateEnd')?.value || null;

    renderTripHistory(status, startDate, endDate);
    closeModal('modal-filter-trips');
}

function renderTripHistory(filterStatus = 'all', filterStartDate = null, filterEndDate = null) {
    const container = document.getElementById('trip-history-list');
    if (!container) return;

    const trips = driverInitialContext.trips_history || [];
    const filteredTrips = trips.filter(t => {
        const statusMatch = filterStatus === 'all' || t.status === filterStatus;
        if (!statusMatch) return false;

        if (filterStartDate || filterEndDate) {
            const tripDate = new Date(t.created_at + 'Z');
            if (filterStartDate) {
                const start = new Date(filterStartDate);
                start.setHours(0,0,0,0);
                if (tripDate < start) return false;
            }
            if (filterEndDate) {
                const end = new Date(filterEndDate);
                end.setHours(23,59,59,999);
                if (tripDate > end) return false;
            }
        }

        return true;
    });

    if (filteredTrips.length === 0) {
        let msg = `No trips found`;
        if (filterStatus !== 'all') msg = `No ${filterStatus} trips found`;
        container.innerHTML = `<div style="text-align:center; padding: 40px 20px; color: var(--slate);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="48" height="48" style="margin-bottom:12px;opacity:0.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><p>${msg}.</p></div>`;
        return;
    }

    let html = '';
    filteredTrips.forEach(trip => {
        const isCompleted = trip.status === 'completed';
        const isCancelled = trip.status === 'cancelled';
        const iconClass = isCompleted ? 'completed' : (isCancelled ? 'cancelled' : '');
        const statusClass = isCompleted ? 'completed' : (isCancelled ? 'cancelled' : '');
        const statusLabel = trip.status.charAt(0).toUpperCase() + trip.status.slice(1);

        let iconSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
        if (isCompleted) {
            iconSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><polyline points="20 6 9 17 4 12"/></svg>';
        } else if (isCancelled) {
            iconSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        }

        const dateObj = new Date(trip.created_at + 'Z');
        const dateStr = dateObj.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) + ' · ' + dateObj.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });

        const maxLocLen = 20;
        const shortPickup = trip.pickup.length > maxLocLen ? trip.pickup.substring(0, maxLocLen) + '...' : trip.pickup;
        const shortDropoff = trip.dropoff.length > maxLocLen ? trip.dropoff.substring(0, maxLocLen) + '...' : trip.dropoff;

        html += `
        <div class="trip-history-item" onclick="showToast('Receipt for trip #${trip.trip_ref} opened')">
            <div class="trip-icon ${iconClass}">
            ${iconSvg}
            </div>
            <div class="trip-details">
            <div class="trip-route">${shortPickup} &rarr; ${shortDropoff}</div>
            <div class="trip-meta">${dateStr} &middot; #${trip.trip_ref}</div>
            </div>
            <div>
            <div class="trip-amount">${formatCurrency(trip.fare || 0)}</div>
            <div class="trip-status ${statusClass}">${statusLabel}</div>
            </div>
        </div>
        `;
    });

    container.innerHTML = html;
}

function renderCampaigns() {
    const campaigns = driverInitialContext.active_campaigns || [];
    const homeContainer = document.getElementById('home-active-campaigns');
    const earningsContainer = document.getElementById('earnings-active-campaigns');

    if (campaigns.length === 0) {
        const emptyState = `<div style="text-align:center; padding: 20px; color: var(--slate); font-size: 13px;">No active campaigns at the moment. Keep driving to unlock new bonuses!</div>`;
        if (homeContainer) homeContainer.innerHTML = emptyState;
        if (earningsContainer) earningsContainer.innerHTML = emptyState;
        return;
    }

    let html = '';
    campaigns.forEach(c => {
        const progressNum = Math.min(c.progress, c.target_trips);
        html += `
        <div class="campaign-card" style="margin-bottom:10px">
            <div>
                <div class="campaign-badge">⚡ ${c.title}</div>
                <div class="campaign-text">${c.description}</div>
            </div>
            <div class="campaign-progress-wrap">
                <div class="campaign-progress-num">${progressNum}/${c.target_trips}</div>
                <div class="campaign-progress-label">done</div>
            </div>
        </div>
        `;
    });

    if (homeContainer) homeContainer.innerHTML = html;
    if (earningsContainer) earningsContainer.innerHTML = html;
}

function renderDashboardStats() {
    if (!driverInitialContext.dashboard_stats) return;
    const stats = driverInitialContext.dashboard_stats;

    // Home
    const homeTodayEarnings = document.getElementById('home-today-earnings');
    if (homeTodayEarnings) {
        if (stats.today_earnings > 0) {
            homeTodayEarnings.textContent = formatCurrency(stats.today_earnings);
        } else {
            homeTodayEarnings.textContent = '-';
        }
    }

    const homeTodayTrips = document.getElementById('home-today-trips');
    if (homeTodayTrips) homeTodayTrips.textContent = stats.today_trips;

    const homeRating = document.getElementById('home-rating');
    if (homeRating) homeRating.textContent = stats.avg_rating.toFixed(1) + '★';

    // Earnings
    const earningsWeekTotal = document.getElementById('earnings-week-total');
    if (earningsWeekTotal) {
        if (stats.week_earnings > 0) {
            earningsWeekTotal.textContent = Number(stats.week_earnings).toLocaleString('en-NG');
        } else {
            earningsWeekTotal.parentElement.innerHTML = '<span style="font-size:16px; font-weight:500; color:var(--slate);">No earnings this week</span>';
        }
    }

    const earningsTodayTotal = document.getElementById('earnings-today-total');
    if (earningsTodayTotal) {
        if (stats.today_earnings > 0) {
            earningsTodayTotal.textContent = formatCurrency(stats.today_earnings);
        } else {
            earningsTodayTotal.textContent = '-';
        }
    }

    const earningsWeekTrips = document.getElementById('earnings-week-trips');
    if (earningsWeekTrips) earningsWeekTrips.textContent = stats.week_trips;

    const earningsRatingText = document.getElementById('earnings-rating-text');
    if (earningsRatingText) earningsRatingText.textContent = stats.avg_rating.toFixed(1);

    const earningsRatingStars = document.getElementById('earnings-rating-stars');
    if (earningsRatingStars) earningsRatingStars.innerHTML = renderStars(stats.avg_rating);

    renderWeeklyChart(stats.daily_breakdown);
    renderTripHistory('all');
    renderCampaigns();
}

document.addEventListener('DOMContentLoaded', () => {
  renderDashboardStats();
});
