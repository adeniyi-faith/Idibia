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
      transport: 'ajax'
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
  if (!driverInitialContext.email_verified) {
    showToast('Please verify your email before submitting KYC.');
    return false;
  }
  const body = new FormData();
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
      startKycPolling();
      return true;
    }
    showToast(json.data?.message || 'Failed to submit KYC.');
  } catch (err) { showToast('Could not reach server. Please try again.'); }
  return false;
}

function validateDriverStep(step) {
  if (step === 2) {
    if (!driverFiles['id_front']) {
      showToast("Please upload your Identity Document (Front).");
      return false;
    }
    if (!driverFiles['id_back']) {
      showToast("Please upload your Identity Document (Back).");
      return false;
    }
    if (!driverFiles['selfie']) {
      showToast("Please upload a Selfie.");
      return false;
    }
  }
  if (step === 3) {
    if (!document.getElementById('driverVehicleYear').value.trim() ||
        !document.getElementById('driverVehicleManufacturer').value.trim() ||
        !document.getElementById('driverVehiclePlate').value.trim() ||
        !document.getElementById('driverVehicleColor').value.trim()) {
      showToast("Please complete all vehicle information fields.");
      return false;
    }
    if (!driverFiles['vehicle_photo'] || !driverFiles['vehicle_interior_photo'] || !driverFiles['vehicle_front_photo'] || !driverFiles['vehicle_rear_photo'] || !driverFiles['vehicle_license_doc']) {
      showToast("Please upload all required vehicle photos.");
      return false;
    }
  }
  if (step === 4) {
    if (!document.getElementById('driverAccountHolder').value.trim() ||
        !document.getElementById('driverAccountNumber').value.trim() ||
        !document.getElementById('driverBankName').value.trim()) {
      showToast("Please complete your primary bank account details.");
      return false;
    }
    if (!document.getElementById('driverEmergencyName').value.trim() ||
        !document.getElementById('driverEmergencyPhone').value.trim() ||
        !document.getElementById('driverEmergencyAddress').value.trim() ||
        !document.getElementById('driverEmergencyRelationship').value) {
      showToast("Please complete your emergency contact details.");
      return false;
    }
  }
  return true;
}

async function driverNext() {
  const nextBtn = document.getElementById('driverNext');
  const initialHtml = nextBtn.innerHTML;
  nextBtn.classList.add('btn-loading');
  if (driverStep === 1 && !driverAuthenticated) {
    if (driverAwaitingEmailVerification) {
      await verifyDriverEmail();
      nextBtn.classList.remove('btn-loading');
      nextBtn.innerHTML = initialHtml;
      return;
    }
    if (driverAuthMode === 'login') {
      await driverLogin();
      nextBtn.classList.remove('btn-loading');
      nextBtn.innerHTML = initialHtml;
      return;
    }

    await submitDriverSignup();
    nextBtn.classList.remove('btn-loading');
    nextBtn.innerHTML = initialHtml;
    return;
  }

  if (driverStep >= 2 && driverStep <= 3 && !validateDriverStep(driverStep)) { nextBtn.classList.remove('btn-loading'); nextBtn.innerHTML = initialHtml; return; }

  if (driverStep === 4) {
    if (!validateDriverStep(4)) { nextBtn.classList.remove('btn-loading'); nextBtn.innerHTML = initialHtml; return; }
    const kycSubmitted = await submitDriverKyc();
    if (!kycSubmitted) { nextBtn.classList.remove('btn-loading'); nextBtn.innerHTML = initialHtml; return; }
  }

  if (driverStep < 5) {
    driverStep++;
    updateDriver();
    document.getElementById('driverContent').scrollTop = 0;
  }
  nextBtn.classList.remove('btn-loading');
  nextBtn.innerHTML = initialHtml;
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
    if (file) {
        driverFiles[fieldName] = file;
        el.classList.remove('error');
        markDone(el, file.name, file);
    }
    input.value = '';
  };
  input.click();
}

function markDone(el, filename = '', file = null) {
  el.classList.add('done');
  el.querySelector('p').innerHTML = '<strong style="color:var(--success)">✓ File selected</strong>';
  const small = el.querySelector('small');
  if (small) small.textContent = filename || 'Tap to replace';

  if (file && file.type.startsWith('image/')) {
    let imgPreview = el.querySelector('img.kyc-preview');
    if (!imgPreview) {
        imgPreview = document.createElement('img');
        imgPreview.className = 'kyc-preview';
        imgPreview.style.width = '100%';
        imgPreview.style.height = '100%';
        imgPreview.style.objectFit = 'cover';
        imgPreview.style.position = 'absolute';
        imgPreview.style.top = '0';
        imgPreview.style.left = '0';
        imgPreview.style.borderRadius = 'var(--radius-sm)';
        imgPreview.style.opacity = '0.3';
        imgPreview.style.zIndex = '0';
        el.style.position = 'relative';
        el.style.overflow = 'hidden';
        el.appendChild(imgPreview);
    }
    imgPreview.src = URL.createObjectURL(file);

    // ensure text content sits above the image preview
    Array.from(el.children).forEach(child => {
        if (!child.classList.contains('kyc-preview')) {
            child.style.position = 'relative';
            child.style.zIndex = '1';
        }
    });
  }
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
  // Re-render the overlay from the (now-hydrated) context. On the SPA login path
  // the dashboard becomes visible without a page reload, so the server-rendered
  // overlay still holds pre-login (empty) values until we repaint it here.
  renderDriverProfile();
  subscribeToDriverRealtime();
  setTimeout(ensureDriverMap, 500);
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
    toggle.setAttribute('aria-checked', isOnline ? 'true' : 'false');
    document.getElementById('onlineLabel').textContent = isOnline ? "Online" : "Offline";
    showToast(isOnline ? '✓ You are now online' : 'You are now offline');
    if (isOnline) startLocationWatch(); else stopLocationWatch();
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

    if (latestDriverCoords) {
      // watchPosition already has a fresh position — use it directly.
      body.append('lat', latestDriverCoords.latitude);
      body.append('lng', latestDriverCoords.longitude);
      body.append('heading', latestDriverCoords.heading || 0);
      const response = await fetch('/driver-heartbeat-api.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      const json = await parseDriverJson(response);
      if (json.success) renderDriverOffers(json.data?.offers || [], json.data?.active_trip || null);
    } else if (navigator.geolocation) {
      // watchPosition hasn't fired yet — fall back to a one-shot request.
      navigator.geolocation.getCurrentPosition(
        async (position) => {
          latestDriverCoords = position.coords;
          body.append('lat', position.coords.latitude);
          body.append('lng', position.coords.longitude);
          body.append('heading', position.coords.heading || 0);
          updateMapLocation(position.coords.latitude, position.coords.longitude);
          const response = await fetch('/driver-heartbeat-api.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
          const json = await parseDriverJson(response);
          if (json.success) renderDriverOffers(json.data?.offers || [], json.data?.active_trip || null);
        },
        async () => {
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
      <div class="dispatch-idle" id="driverNoOfferCard">
        <span class="dispatch-idle-dot"></span>
        <div class="dispatch-idle-text">
          <strong>No live requests</strong>
          <span>${isOnline ? 'Waiting for nearby bookings · stay online' : 'Go online to start receiving bookings'}</span>
        </div>
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
    let fullAvatarUrl;
    if (ctx._avatar_url) {
        fullAvatarUrl = ctx._avatar_url;
    } else if (avatarToUse && avatarToUse.startsWith('http')) {
        fullAvatarUrl = avatarToUse;
    } else if (avatarToUse) {
        fullAvatarUrl = baseUploadUrl ? `${baseUploadUrl}/${avatarToUse}` : `/${avatarToUse}`;
    }

    if (avatarToUse) {
        if (imgDisplay) {
            imgDisplay.src = fullAvatarUrl;
            imgDisplay.style.display = 'block';
        }
        if (initialsDisplay) initialsDisplay.style.display = 'none';

        if (dashHomeAvatar) {
            dashHomeAvatar.src = fullAvatarUrl;
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
    const accHolderInput = document.getElementById('inputProfileAccountHolder');
    if (accHolderInput) accHolderInput.value = ctx.account_holder_name || '';
    const accNumInput = document.getElementById('inputProfileAccountNumber');
    if (accNumInput) accNumInput.value = ctx.account_number || '';
    const secBankNameInput = document.getElementById('inputProfileSecondaryBankName');
    if (secBankNameInput) secBankNameInput.value = ctx.secondary_bank_name || '';
    const secAccHolderInput = document.getElementById('inputProfileSecondaryAccountHolder');
    if (secAccHolderInput) secAccHolderInput.value = ctx.secondary_account_holder || '';
    const secAccNumInput = document.getElementById('inputProfileSecondaryAccountNumber');
    if (secAccNumInput) secAccNumInput.value = ctx.secondary_account_number || '';

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

    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.classList.add('btn-loading');

    try {
        const response = await fetch('/driver-profile-api.php', { method: 'POST', body: formData, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        const json = await parseDriverJson(response);
        if (json.success) {
            showToast(json.data?.message || 'Profile updated.');
            // Update context
            for (let [key, value] of formData.entries()) {
                if (key !== 'action') {
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
        btn.classList.remove('btn-loading');
        btn.innerHTML = originalText;
    }
}

async function uploadDriverAvatar(event) {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('action', 'upload_avatar');
    formData.append('selfie', file);

    showToast('Uploading profile picture...');
    try {
        const response = await fetch('/driver-profile-api.php', { method: 'POST', body: formData, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        const json = await parseDriverJson(response);
        if (json.success) {
            showToast('Profile picture updated.');
            if (json.data?.avatar_path) {
                window.driverInitialContext.avatar_path = json.data.avatar_path;
                if (json.data?.avatar_url) {
                    window.driverInitialContext._avatar_url = json.data.avatar_url;
                }
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

  // The map-first Home is a full-bleed surface; toggling this class on the
  // screen switches the content area between scroll-with-padding (other tabs)
  // and full-height map layout (home).
  const screen = document.getElementById('screen-driver-dash');
  if (screen) screen.classList.toggle('tab-home', tab === 'home');

  if (tab === 'earnings') {
    loadWalletData();
  } else if (tab === 'home') {
    setTimeout(ensureDriverMap, 300);
  }

  // bottom nav
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const navItem = document.getElementById('nav-' + tab);
  if (navItem) navItem.classList.add('active');
  // sidebar icons
  document.querySelectorAll('.dash-sidebar-icon').forEach(i => i.classList.remove('active'));
  const sidebarItems = document.querySelectorAll('.dash-sidebar-icon');
  const tabOrder = ['home','earnings','trips','profile'];
  const idx = tabOrder.indexOf(tab);
  if (sidebarItems[idx]) sidebarItems[idx].classList.add('active');

  document.getElementById('dashBody').scrollTop = 0;
}

// Peek/expand toggle for the Home bottom sheet (today's snapshot + campaigns).
function toggleHomeSheet() {
  const sheet = document.getElementById('homeBottomSheet');
  if (sheet) sheet.classList.toggle('expanded');
}

function togglePasswordVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  if (input.type === 'password') {
    input.type = 'text';
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
  } else {
    input.type = 'password';
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
  }
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

let _kycPollTimer = null;

function startKycPolling() {
  if (_kycPollTimer) return;
  _kycPollTimer = setInterval(async () => {
    try {
      const res = await fetch('driver-kyc-status.php', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      const json = await res.json();
      if (!json.success) return;
      const { kyc_status, is_approved, kyc_notes } = json.data;
      if (kyc_status === driverInitialContext.kyc_status) return;
      driverInitialContext.kyc_status = kyc_status;
      driverInitialContext.is_approved = is_approved;
      clearInterval(_kycPollTimer);
      _kycPollTimer = null;
      if (is_approved) {
        showToast('🎉 Your application was approved! Welcome aboard.');
        goToDashboard();
        subscribeToDriverRealtime();
        startLocationWatch();
        fetchDriverOffers();
        setInterval(fetchDriverOffers, IDIBIA_PUSHER_CONFIG?.enabled ? 30000 : 15000);
      } else if (kyc_status === 'rejected') {
        showToast('Your application was not approved. ' + (kyc_notes || 'Please check your documents and reapply.'));
        driverStep = 1;
        updateDriver();
      }
    } catch (_) {}
  }, 20000);
}

function stopKycPolling() {
  if (_kycPollTimer) { clearInterval(_kycPollTimer); _kycPollTimer = null; }
}

if (driverInitialContext.logged_in) {
  driverAuthenticated = true;
  if (!driverInitialContext.is_approved) {
    if (driverInitialContext.kyc_status === 'under_review') {
      driverStep = 5;
      startKycPolling();
    } else if (driverInitialContext.kyc_status === 'pending') {
      driverStep = 2;
    }
    updateDriver();
  } else {
    goToDashboard();
    subscribeToDriverRealtime();
    startLocationWatch();
    fetchDriverOffers();
    setInterval(fetchDriverOffers, IDIBIA_PUSHER_CONFIG?.enabled ? 30000 : 15000);
  }
}

if (document.getElementById('onlineToggle')) {
  const _toggle = document.getElementById('onlineToggle');
  _toggle.classList.toggle('online', isOnline);
  _toggle.classList.toggle('offline', !isOnline);
  _toggle.setAttribute('aria-checked', isOnline ? 'true' : 'false');
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

let driverWatchId = null;
let latestDriverCoords = null;
let _lastLocationPingTs = 0;

function startLocationWatch() {
  if (!navigator.geolocation || driverWatchId !== null) return;
  driverWatchId = navigator.geolocation.watchPosition(
    (position) => {
      latestDriverCoords = position.coords;
      updateMapLocation(position.coords.latitude, position.coords.longitude);
      // Throttle server pings to once every 8 s so we don't flood the heartbeat API.
      const now = Date.now();
      if (now - _lastLocationPingTs > 8000) {
        _lastLocationPingTs = now;
        _sendLocationPing(position.coords.latitude, position.coords.longitude, position.coords.heading || 0);
      }
    },
    () => { /* silent — fetchDriverOffers toasts on permission denial */ },
    { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 }
  );
}

function stopLocationWatch() {
  if (driverWatchId !== null) {
    navigator.geolocation.clearWatch(driverWatchId);
    driverWatchId = null;
  }
  latestDriverCoords = null;
}

async function _sendLocationPing(lat, lng, heading) {
  const body = new FormData();
  body.append('_nonce', driverInitialContext.nonces?.driver_action || '');
  body.append('lat', lat);
  body.append('lng', lng);
  body.append('heading', heading);
  try {
    const res = await fetch('/driver-heartbeat-api.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    const json = await res.json().catch(() => null);
    if (json?.success) renderDriverOffers(json.data?.offers || [], json.data?.active_trip || null);
  } catch (e) { /* silent */ }
}

function keepMapSized(map) {
  if (!map || typeof ResizeObserver === 'undefined') return;
  const el = map.getContainer();
  let raf = null;
  const ro = new ResizeObserver(() => {
    if (raf) cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => map.invalidateSize({ animate: false }));
  });
  ro.observe(el);
}

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

  keepMapSized(currentMap);

  // Leaflet may be initialised before the flex container reaches its final size
  // (especially on hard reload where CSS layout settles after the first paint).
  // Re-measure on several milestones so tiles always fill the map viewport.
  const invalidate = () => currentMap && currentMap.invalidateSize({ animate: false });
  requestAnimationFrame(() => requestAnimationFrame(invalidate));
  if (document.readyState !== 'complete') window.addEventListener('load', invalidate, { once: true });
  [50, 200, 500, 1000, 2000, 3500].forEach(ms => setTimeout(invalidate, ms));
}

// Mount the dashboard map once the dashboard is visible. Defaults to Lagos and
// re-centres on the driver's real GPS position once geolocation reports it.
// Retries only until Leaflet is available — the dimension check was removed
// because flex layout may not have settled yet, and Leaflet initialises fine
// on a zero-size container; keepMapSized() + invalidateSize() handle the rest.
function ensureDriverMap(_retries = 8) {
  if (currentMap) {
    currentMap.invalidateSize({ animate: false });
    return;
  }
  const el = document.getElementById('driver-map-container');
  if (!el || !window.L) {
    if (_retries > 0) setTimeout(() => ensureDriverMap(_retries - 1), 300);
    return;
  }
  const lat = latestDriverCoords?.latitude ?? 6.5244;
  const lng = latestDriverCoords?.longitude ?? 3.3792;
  initLeafletMap('driver-map-container', lat, lng);
}

function updateMapLocation(lat, lng) {
  if (currentMap && currentMarker) {
    const newLatLng = new L.LatLng(lat, lng);
    currentMarker.setLatLng(newLatLng);
    currentMap.panTo(newLatLng);
  }
}

let currentRouteLayer = null;
async function drawRouteOnMap(routeCoordinates) {
    if (!currentMap || !routeCoordinates || routeCoordinates.length < 2) return;
    if (currentRouteLayer) {
        currentMap.removeLayer(currentRouteLayer);
        currentRouteLayer = null;
    }

    const [from, to] = routeCoordinates;
    try {
        // OSRM uses lng,lat order (opposite of Leaflet's lat,lng).
        const url = `https://router.project-osrm.org/route/v1/driving/${from[1]},${from[0]};${to[1]},${to[0]}?overview=full&geometries=geojson`;
        const res = await fetch(url);
        if (res.ok) {
            const data = await res.json();
            if (data.code === 'Ok' && data.routes?.[0]?.geometry?.coordinates?.length) {
                const coords = data.routes[0].geometry.coordinates.map(([lng, lat]) => [lat, lng]);
                currentRouteLayer = L.polyline(coords, { color: 'var(--navy)', weight: 5, opacity: 0.7 }).addTo(currentMap);
                currentMap.fitBounds(currentRouteLayer.getBounds(), { padding: [50, 50] });
                return;
            }
        }
    } catch (e) { /* fall through to straight-line fallback */ }

    currentRouteLayer = L.polyline(routeCoordinates, { color: 'var(--navy)', weight: 5, opacity: 0.7 }).addTo(currentMap);
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
        container.innerHTML = `<div class="empty-state" style="text-align:center; padding: 40px 20px; color: var(--text-muted);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="48" height="48" style="margin-bottom:12px;opacity:0.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><p>${msg}.</p></div>`;
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

async function loadWalletData() {
  try {
    const formData = new URLSearchParams();
    formData.append('action', 'get_wallet');
    const response = await fetch('driver-wallet-api.php', {
      method: 'POST',
      body: formData
    });
    const result = await response.json();
    if (result.success) {
      const data = result.data;

      const balanceDisplay = document.getElementById('wallet-balance-display');
      if (balanceDisplay) balanceDisplay.textContent = formatCurrency(data.wallet_balance);

      window.driverWalletBalance = data.wallet_balance;
      window.driverBankName = data.bank_name;
      window.driverAccountNumber = data.account_number;

      // Populate earnings summary card
      if (data.earnings) {
        const e = data.earnings;

        const weekTotal = document.getElementById('earnings-week-total');
        if (weekTotal) weekTotal.textContent = new Intl.NumberFormat('en-NG', { minimumFractionDigits: 0 }).format(e.week_total);

        const todayTotal = document.getElementById('earnings-today-total');
        if (todayTotal) todayTotal.textContent = formatCurrency(e.today_total);

        const weekTrips = document.getElementById('earnings-week-trips');
        if (weekTrips) weekTrips.textContent = e.week_trips;

        const ratingText = document.getElementById('earnings-rating-text');
        if (ratingText) ratingText.textContent = parseFloat(e.rating).toFixed(1);

        const ratingStars = document.getElementById('earnings-rating-stars');
        if (ratingStars) {
          const full = Math.floor(e.rating);
          const half = (e.rating - full) >= 0.5;
          let stars = '★'.repeat(full);
          if (half) stars += '½';
          stars += '☆'.repeat(Math.max(0, 5 - full - (half ? 1 : 0)));
          ratingStars.textContent = stars;
        }

        // Weekly bar chart
        const chart = document.getElementById('weekly-bar-chart');
        if (chart && e.daily_breakdown && e.daily_breakdown.length) {
          const maxVal = Math.max(...e.daily_breakdown.map(d => d.total), 1);
          const chartMax = document.getElementById('weekly-chart-max');
          if (chartMax) chartMax.textContent = formatCurrency(maxVal);

          chart.innerHTML = e.daily_breakdown.map(day => {
            const heightPct = Math.round((day.total / maxVal) * 100);
            const isToday = day.date === new Date().toISOString().slice(0, 10);
            return `
              <div style="display:flex;flex-direction:column;align-items:center;flex:1;gap:4px;">
                <div style="font-size:11px;color:var(--text-muted);font-weight:${isToday ? '700' : '400'}">${escapeHtml(day.label)}</div>
                <div style="flex:1;width:100%;display:flex;align-items:flex-end;">
                  <div style="width:100%;height:${heightPct}%;min-height:${day.total > 0 ? 4 : 0}px;background:${isToday ? 'var(--primary)' : 'var(--surface-2)'};border-radius:4px 4px 0 0;" title="${formatCurrency(day.total)}"></div>
                </div>
              </div>`;
          }).join('');
        }
      }

      const ledgerList = document.getElementById('wallet-ledger-list');
      if (ledgerList) {
        if (data.ledger && data.ledger.length > 0) {
          ledgerList.innerHTML = data.ledger.map(entry => `
            <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--surface-2);">
              <div>
                <div style="font-size:14px;">${escapeHtml(entry.description || entry.entry_type)}</div>
                <div style="font-size:12px; color:var(--text-muted);">${new Date(entry.created_at).toLocaleString()}</div>
              </div>
              <div style="font-weight:600; color:${parseFloat(entry.amount) > 0 ? 'var(--success)' : 'inherit'}">
                ${parseFloat(entry.amount) > 0 ? '+' : ''}${formatCurrency(parseFloat(entry.amount))}
              </div>
            </div>
          `).join('');
        } else {
          ledgerList.innerHTML = '<div class="info-note" style="text-align:center; padding: 20px; color: var(--text-muted);">No transactions yet.</div>';
        }
      }

      const payoutsList = document.getElementById('wallet-payouts-list');
      if (payoutsList) {
        if (data.payouts && data.payouts.length > 0) {
          payoutsList.innerHTML = data.payouts.map(payout => `
            <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--surface-2);">
              <div>
                <div style="font-size:14px;">Payout Request</div>
                <div style="font-size:12px; color:var(--text-muted);">${new Date(payout.created_at).toLocaleString()}</div>
              </div>
              <div style="text-align:right;">
                <div style="font-weight:600;">${formatCurrency(parseFloat(payout.amount))}</div>
                <div style="font-size:12px; color:${payout.status === 'paid' ? 'var(--success)' : payout.status === 'failed' ? 'var(--danger)' : 'var(--warning)'}; text-transform:capitalize;">${payout.status}</div>
              </div>
            </div>
          `).join('');
        } else {
          payoutsList.innerHTML = '<div class="info-note" style="text-align:center; padding: 20px; color: var(--text-muted);">No payouts yet.</div>';
        }
      }
    } else {
      showToast(result.data?.message || 'Could not load wallet data.');
    }
  } catch (err) {
    showToast('Connection error loading wallet data.');
  }
}

function openPayoutModal() {
  if (!window.driverBankName || !window.driverAccountNumber) {
    showToast('Please add bank details in Profile first.');
    openModal('modal-edit-bank');
    return;
  }

  if (parseFloat(window.driverWalletBalance) <= 0) {
    showToast('Insufficient balance for payout.');
    return;
  }

  const bankInfo = document.getElementById('payout-bank-info');
  if (bankInfo) {
    bankInfo.innerHTML = `<strong>${escapeHtml(window.driverBankName)}</strong><br>${escapeHtml(window.driverAccountNumber)}`;
  }

  const amountInput = document.getElementById('inputPayoutAmount');
  if (amountInput) {
    amountInput.value = window.driverWalletBalance;
    amountInput.max = window.driverWalletBalance;
  }

  openModal('modal-request-payout');
}

async function requestPayout(e) {
  e.preventDefault();
  const btn = document.getElementById('btnRequestPayout');
  const originalText = btn.textContent;
  btn.textContent = 'Processing...';
  btn.disabled = true;

  try {
    const amount = document.getElementById('inputPayoutAmount').value;
    const formData = new URLSearchParams();
    formData.append('action', 'request_payout');
    formData.append('amount', amount);
    formData.append('idempotency_key', 'req-' + window.driverInitialContext.driver_id + '-' + Date.now());

    const response = await fetch('driver-wallet-api.php', {
      method: 'POST',
      body: formData
    });
    const result = await response.json();

    if (result.success) {
      showToast('Payout requested successfully');
      closeModal('modal-request-payout');
      loadWalletData();
    } else {
      showToast(result.data?.message || 'Failed to request payout');
    }
  } catch (err) {
    showToast('Connection error requesting payout');
  } finally {
    btn.textContent = originalText;
    btn.disabled = false;
  }
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
  // An approved driver's dashboard is already active on page load (set via PHP),
  // but goToDashboard() is only called on login — so init the map here too.
  if (driverInitialContext?.is_approved) {
    setTimeout(ensureDriverMap, 500);
  }
});

async function doDriverLogout() {
  const btn = document.querySelector('button[onclick="doDriverLogout()"]');
  if (btn) btn.textContent = 'Logging out...';
  try {
    const body = new FormData();
    await fetch("driver-logout-handler.php", { method: "POST", body });
    window.location.href = window.idibiaLogoutUrl || "/";
  } catch (err) {
    showToast('Logout failed. Please try again.');
    if (btn) btn.textContent = 'Log Out';
  }
}
