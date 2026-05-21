// ═══════════ STATE ═══════════
let currentScreen = 'screen-main';
let screenHistory = ['screen-main'];
let onbSlide = 0;
let selectedCategory = 'Package';
let currentRating = 5;
let etaInterval = null;
const IDIBIA_API_BASE = new URL('.', window.location.href).href.replace(/\/$/, '');
const IDIBIA_VERIFY_NONCE = '' + window.idibiaVerifyNonce + '';
const IDIBIA_SUPPORT_NONCE = '' + window.idibiaSupportNonce + '';
const IDIBIA_PUSHER_CONFIG = window.idibiaPusherConfig;
const CUSTOMER_RATING = window.idibiaCustomerRating || '5.0';

let idibiaPusher = null;
let idibiaTripChannel = null;
let idibiaTripChannelName = null;
let idibiaRealtimePollTimer = null;

function initIdibiaPusher() {
  if (!IDIBIA_PUSHER_CONFIG?.enabled || typeof Pusher === 'undefined') return null;
  if (idibiaPusher) return idibiaPusher;
  idibiaPusher = new Pusher(IDIBIA_PUSHER_CONFIG.key, {
    cluster: IDIBIA_PUSHER_CONFIG.cluster,
    channelAuthorization: {
      endpoint: IDIBIA_PUSHER_CONFIG.authEndpoint,
      transport: 'ajax',
      params: { _nonce: IDIBIA_PUSHER_CONFIG.authNonce }
    }
  });
  return idibiaPusher;
}

function scheduleRealtimeTrackingRefresh() {
  if (idibiaRealtimePollTimer) return;
  idibiaRealtimePollTimer = setTimeout(() => {
    idibiaRealtimePollTimer = null;
    pollTracking();
  }, 300);
}

function subscribeToTripRealtime(tripId) {
  const pusher = initIdibiaPusher();
  if (!pusher || !tripId) return;
  const channelName = `private-trip-${tripId}`;
  if (idibiaTripChannelName === channelName) return;
  if (idibiaTripChannelName) pusher.unsubscribe(idibiaTripChannelName);
  idibiaTripChannelName = channelName;
  idibiaTripChannel = pusher.subscribe(channelName);
  idibiaTripChannel.bind('trip.updated', data => {
    if (data?.title) showToast(data.title);
    scheduleRealtimeTrackingRefresh();
  });
  idibiaTripChannel.bind('driver.location.updated', () => scheduleRealtimeTrackingRefresh());
}

function unsubscribeFromTripRealtime() {
  if (idibiaPusher && idibiaTripChannelName) {
    idibiaPusher.unsubscribe(idibiaTripChannelName);
  }
  idibiaTripChannel = null;
  idibiaTripChannelName = null;
}

async function idibiaPost(endpoint, body = null) {
  const res = await fetch(`${IDIBIA_API_BASE}/${endpoint}`, {
    method: 'POST',
    body,
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json' }
  });

  const rawText = await res.text();
  try {
    return JSON.parse(rawText);
  } catch (err) {
    console.error('Raw response from ' + endpoint + ':', rawText);
    throw new Error('Invalid server response');
  }
}

// ═══════════ INIT ═══════════
document.addEventListener('DOMContentLoaded', () => {
  const ratingEl = document.getElementById('account-rating-display');
  if (ratingEl) {
    ratingEl.innerText = CUSTOMER_RATING;
  }

  // Load preferences initial state
  fetch(IDIBIA_API_BASE + '/preferences-api.php', { credentials: 'same-origin' }).then(r => r.json()).then(res => {
    if (res.success && res.data && res.data.preferences) {
      const notifChip = document.querySelector('.account-row[onclick="openPreferencesModal()"] .chip');
      if (notifChip) {
          if (res.data.preferences.trip_updates) {
              notifChip.innerText = 'On';
              notifChip.className = 'chip chip-success';
          } else {
              notifChip.innerText = 'Off';
              notifChip.className = 'chip chip-warning';
          }
      }
    }
  });
  const start = 'screen-main';
  currentScreen = start;
  screenHistory = [start];
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(start).classList.add('active');
  buildDateGrid();
  // Trip progress is driven by trip-feed-api.php and driver actions; no demo countdown runs in production.
});

// ═══════════ SWIPABLE ONBOARDING ═══════════
function initOnboardingSwipe() {
  const slidesWrap = document.getElementById('onbSlides');
  if (!slidesWrap) return;
  let touchStartX = 0;
  let touchEndX = 0;

  slidesWrap.addEventListener('touchstart', e => {
    touchStartX = e.changedTouches[0].screenX;
  }, {passive: true});

  slidesWrap.addEventListener('touchend', e => {
    touchEndX = e.changedTouches[0].screenX;
    if (touchStartX - touchEndX > 50) {
      onbNext(); // Swipe left (next)
    } else if (touchEndX - touchStartX > 50) {
      onbPrev(); // Swipe right (prev)
    }
  }, {passive: true});
}

function onbPrev() {
  const slides = document.getElementById('onbSlides');
  const dots = document.querySelectorAll('.onb-dot');
  const btnText = document.getElementById('onbBtnText');
  if (onbSlide > 0) {
    onbSlide--;
    slides.style.transform = `translateX(-${onbSlide * 100}%)`;
    dots.forEach((d,i) => d.classList.toggle('active', i === onbSlide));
    btnText.textContent = 'Next';
  }
}

// ═══════════ PASSWORD TOGGLE ═══════════
function togglePassword(btn) {
  const input = btn.previousElementSibling;
  if (input.type === 'password') {
    input.type = 'text';
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22"/></svg>`;
  } else {
    input.type = 'password';
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
  }
}

// ═══════════ ROUTING ═══════════
function goTo(id) {
  if (id === currentScreen) return;
  const curr = document.getElementById(currentScreen);
  const next = document.getElementById(id);
  if (!next) return;
  curr.classList.remove('active');
  curr.classList.add('slide-out');
  setTimeout(() => curr.classList.remove('slide-out'), 400);
  next.classList.add('active');
  screenHistory.push(id);
  currentScreen = id;
}

function goBack() {
  if (screenHistory.length <= 1) return;
  screenHistory.pop();
  const prev = screenHistory[screenHistory.length - 1];
  const curr = document.getElementById(currentScreen);
  const prevEl = document.getElementById(prev);
  curr.classList.remove('active');
  curr.classList.add('slide-out');
  setTimeout(() => curr.classList.remove('slide-out'), 400);
  prevEl.classList.add('active');
  currentScreen = prev;
}

// ═══════════ ONBOARDING ═══════════
function onbNext() {
  const slides = document.getElementById('onbSlides');
  const dots = document.querySelectorAll('.onb-dot');
  const btnText = document.getElementById('onbBtnText');
  if (onbSlide < 2) {
    onbSlide++;
    slides.style.transform = `translateX(-${onbSlide * 100}%)`;
    dots.forEach((d,i) => d.classList.toggle('active', i === onbSlide));
    btnText.textContent = onbSlide === 2 ? 'Get Started' : 'Next';
  } else {
    goTo('screen-auth');
  }
}

// ═══════════ OTP ═══════════
function otpNext(el, idx) {
  const inputs = document.querySelectorAll('.otp-input');
  if (el.value.length === 1 && idx < 4) inputs[idx + 1].focus();
  // Auto-verify if all filled
  const all = Array.from(inputs).every(i => i.value.length === 1);
  if (all) setTimeout(() => doVerify(), 400);
}

// ═══════════ AUTH ═══════════
function switchAuth(login) {
  document.getElementById('loginView').style.display = login ? '' : 'none';
  document.getElementById('registerView').style.display = login ? 'none' : '';
}

function enterCustomerApp(message = 'Welcome back 👋') {
  closeAllModals();
  goTo('screen-main');
  showToast(message);
}

async function doLogin() {
  const errorBox  = document.getElementById('authError');
  const errorText = document.getElementById('authErrorText');
  const identifier = document.getElementById('loginEmail').value.trim();
  const password   = document.getElementById('loginPass').value;

  errorBox.classList.remove('show');

  if (!identifier || !password) {
    errorText.textContent = 'Enter your email/phone and password.';
    errorBox.classList.add('show');
    return;
  }

  try {
    const body = new FormData();
    body.append('email', identifier);
    body.append('password', password);

    const json = await idibiaPost('login-handler.php', body);
    if (json.success) {
      enterCustomerApp(json.data?.first_name ? `Welcome back, ${json.data.first_name} 👋` : 'Welcome back 👋');
    } else {
      errorText.textContent = json.data?.message || 'Login failed. Please try again.';
      errorBox.classList.add('show');
    }
  } catch (err) {
    errorText.textContent = 'Could not reach Idibia right now. Please check your connection and try again.';
    errorBox.classList.add('show');
  }
}

// ═══════════ TAB SWITCHING ═══════════
function switchTab(name, sideBtn, bnavId) {
  document.querySelectorAll('.tab-view').forEach(t => t.classList.remove('active'));
  const target = document.getElementById('tab-' + name);
  if (target) target.classList.add('active');

  document.querySelectorAll('.sidebar-btn').forEach(b => b.classList.remove('active'));
  if (sideBtn) sideBtn.classList.add('active');
  else {
    // Find matching sidebar btn
    document.querySelectorAll('.sidebar-btn').forEach(b => {
      if (b.textContent.trim().toLowerCase().includes(name)) b.classList.add('active');
    });
  }

  document.querySelectorAll('.bnav-btn').forEach(b => b.classList.remove('active'));
  const id = bnavId || name;
  const bnav = document.getElementById('bnav-' + id);
  if (bnav) bnav.classList.add('active');
}

// ═══════════ HOME PANEL ═══════════
function swapLocations() {
  const pickup = document.getElementById('pickupInput');
  const dropoff = document.getElementById('dropoffInput');
  const temp = pickup.value;
  pickup.value = dropoff.value;
  dropoff.value = temp;
  showToast('Locations swapped');
}

function setSched(btn, mode) {
  document.querySelectorAll('.sched-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

function selCat(el, name) {
  document.querySelectorAll('.cat-card').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  selectedCategory = name;
  showToast(`"${name}" selected`);
}

function toggleFlag(el) {
  el.classList.toggle('active');
  const toggle = el.querySelector('.toggle-wrap');
  toggle.classList.toggle('on');
  const title = el.querySelector('.sf-title').textContent;
  const on = toggle.classList.contains('on');
  showToast(on ? `"${title}" enabled` : `"${title}" disabled`);
}

function findRider() {
  const pickup = document.getElementById('pickupInput').value;
  const dropoff = document.getElementById('dropoffInput').value;
  if (!dropoff.trim()) {
    showToast('Please enter a drop-off location');
    document.getElementById('dropoffInput').focus();
    return;
  }
  showToast('Searching for nearby riders...');
  setTimeout(() => {
    showToast('Amina K. is on the way! ETA: 4 min');
    setTimeout(() => startLiveTracking(1), 1500);
  }, 2000);
}

// ═══════════ ACTIVITY FILTERS ═══════════
function filterTrips(btn, status) {
  document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  const cards = document.querySelectorAll('.trip-card');
  cards.forEach(card => {
    if (status === 'all') {
      card.style.display = '';
    } else {
      card.style.display = card.dataset.status === status ? '' : 'none';
    }
  });
}

// ═══════════ MODALS ═══════════
function openModal(id) {
  document.getElementById('modal-' + id).classList.add('show');
}

function closeModal(e, id) {
  if (!e || e.target.classList.contains('modal-overlay')) {
    const modal = document.getElementById('modal-' + id);
    if (modal) modal.classList.remove('show');
  }
}

function closeAllModals() {
  document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('show'));
}


async function submitCustomerRatingAndClose() {
  if (currentActiveTripId) {
    const activeChips = Array.from(document.querySelectorAll('#feedbackChips .feedback-chip.active')).map(chip => chip.textContent.trim());
    const body = new FormData();
    body.append('trip_id', currentActiveTripId);
    body.append('rating', currentRating || 5);
    body.append('comment', activeChips.join(', '));
    body.append('_nonce', IDIBIA_SUPPORT_NONCE);
    try {
      const json = await idibiaPost('rating-api.php', body);
      if (!json.success) showToast(json.data?.message || 'Could not save rating.');
    } catch (err) {
      showToast('Connection error saving rating.');
    }
  }
  closeModalAndGoHome();
}


function chooseOptionalEvidence() {
  return new Promise(resolve => {
    if (!confirm('Do you want to attach a photo/PDF as evidence?')) return resolve(null);
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,application/pdf';
    input.onchange = () => resolve(input.files?.[0] || null);
    input.click();
  });
}

async function openSupportTicket(category = 'general') {
  const message = prompt('Tell support what happened:');
  if (!message) return;
  const body = new FormData();
  body.append('action', 'create_ticket');
  if (currentActiveTripId) body.append('trip_id', currentActiveTripId);
  body.append('category', category);
  body.append('message', message.trim());
  const evidence = await chooseOptionalEvidence();
  if (evidence) body.append('evidence', evidence);
  body.append('_nonce', IDIBIA_SUPPORT_NONCE);
  try {
    const json = await idibiaPost('support-api.php', body);
    showToast(json.success ? (json.data?.message || 'Support ticket opened.') : (json.data?.message || 'Could not open support ticket.'));
  } catch (err) {
    showToast('Connection error opening support ticket.');
  }
}

function sendSafetyReport() {
  openModal('modal-sos');
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
  if (currentActiveTripId) body.append('trip_id', currentActiveTripId);
  body.append('category', category);
  body.append('severity', severity);
  body.append('message', message);
  body.append('_nonce', IDIBIA_SUPPORT_NONCE);

  try {
    const json = await idibiaPost('support-api.php', body);
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

function openPreferencesModal() {
  fetch(IDIBIA_API_BASE + '/preferences-api.php', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(res => {
      if (res.success && res.data && res.data.preferences) {
        const pref = res.data.preferences;
        const tripUpdates = document.getElementById('pref_trip_updates');
        const promotions = document.getElementById('pref_promotions');
        const emailReceipts = document.getElementById('pref_email_receipts');

        if (tripUpdates) tripUpdates.checked = !!pref.trip_updates;
        if (promotions) promotions.checked = !!pref.promotions;
        if (emailReceipts) emailReceipts.checked = !!pref.email_receipts;
      }
      openModal('preferences');
    })
    .catch(() => {
      showToast('Could not load preferences.');
      openModal('preferences');
    });
}

function closeModalAndGoHome() {
  closeAllModals();
  goBack();
  setTimeout(() => {
    switchTab('home', null, 'home');
    showToast('Thanks for your feedback, John!');
  }, 100);
}

function confirmLogout() { openModal('logout'); }

function doLogout() {
  closeAllModals();
  showToast('Signed out successfully');
  setTimeout(() => location.reload(), 1200);
}

// ═══════════ STAR RATING ═══════════
const ratingLabels = ['', 'Poor 😕', 'Fair 😐', 'Good 🙂', 'Great 😊', 'Amazing! 🌟'];

function rateStar(num) {
  currentRating = num;
  const stars = document.querySelectorAll('#starRating .star-btn');
  stars.forEach((btn, idx) => btn.classList.toggle('active', idx < num));
  const label = document.getElementById('ratingLabel');
  if (label) label.textContent = ratingLabels[num];
}

function toggleChip(el) {
  el.classList.toggle('active');
}

// ═══════════ SCHEDULE MODAL ═══════════
function buildDateGrid() {
  const grid = document.getElementById('dateGrid');
  if (!grid) return;
  const days = ['S','M','T','W','T','F','S'];
  const today = new Date();
  grid.innerHTML = '';
  for (let i = 0; i < 7; i++) {
    const d = new Date(today);
    d.setDate(today.getDate() + i);
    const dateStr = d.toISOString().split('T')[0];
    const el = document.createElement('div');
    el.className = 'date-day' + (i === 0 ? ' today active' : '');
    el.dataset.date = dateStr;
    el.innerHTML = `<div class="date-day-name">${days[d.getDay()]}</div><div class="date-day-num">${d.getDate()}</div>`;
    el.onclick = () => {
      document.querySelectorAll('.date-day').forEach(d => d.classList.remove('active'));
      el.classList.add('active');
    };
    grid.appendChild(el);
  }
}

let selectedScheduleTime = null;

function convertAmPmTo24(timeStr) {
  const [time, modifier] = timeStr.split(' ');
  let [hours, minutes] = time.split(':');
  if (hours === '12') hours = '00';
  if (modifier === 'PM') hours = parseInt(hours, 10) + 12;
  return `${hours.toString().padStart(2, '0')}:${minutes}:00`;
}

function confirmSchedule() {
  const selectedDateEl = document.querySelector('#dateGrid .date-day.active');
  const selectedTimeEl = document.querySelector('.time-select');

  if (selectedDateEl && selectedTimeEl) {
    const dateStr = selectedDateEl.dataset.date;
    const timeStr = selectedTimeEl.value;
    const time24 = convertAmPmTo24(timeStr);
    selectedScheduleTime = `${dateStr} ${time24}`;
  }

  closeAllModals();
  const schedBtn = document.getElementById('schedLater');
  if (schedBtn) {
    schedBtn.classList.add('active');
    schedBtn.textContent = 'Scheduled';
  }
  const immBtn = document.getElementById('schedImmediate');
  if (immBtn) immBtn.classList.remove('active');
  showToast('Pickup scheduled successfully!');
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c]));
}

// ═══════════ LIVE TRACKING (Phase 5) ═══════════

async function cancelTrip() {
  if (!currentActiveTripId) return;
  if (!confirm('Are you sure you want to cancel this trip?')) return;

  try {
    const body = new FormData();
    body.append('trip_id', currentActiveTripId);

    const json = await idibiaPost('customer-cancel-api.php', body);

    if (json.success) {
      showToast(json.data.message);
      stopLiveTracking();
      goTo('screen-home');
      fetchRecentActivity();
    } else {
      showToast(json.data?.message || 'Could not cancel trip.');
    }
  } catch(err) {
     showToast('Connection error cancelling trip.');
  }
}
// ═══════════ LIVE TRACKING (Phase 5) ═══════════
let currentActiveTripId = null;
let trackingInterval = null;

function startLiveTracking(tripId) {
  if (!tripId) return;
  currentActiveTripId = tripId;
  goTo('screen-tracking');
  setTimeout(() => initLeafletMap('tracking-map-container', 6.5244, 3.3792, true), 300); // Default to Lagos, will update on feed
  subscribeToTripRealtime(tripId);
  pollTracking();
  if (trackingInterval) clearInterval(trackingInterval);
  trackingInterval = setInterval(pollTracking, IDIBIA_PUSHER_CONFIG?.enabled ? 30000 : 5000);
}

function stopLiveTracking() {
  if (trackingInterval) {
    clearInterval(trackingInterval);
    trackingInterval = null;
  }
  unsubscribeFromTripRealtime();
}

async function pollTracking() {
  if (!currentActiveTripId) return stopLiveTracking();
  try {
    const res = await fetch(`${IDIBIA_API_BASE}/trip-feed-api.php?trip_id=${currentActiveTripId}`, {
      credentials: 'same-origin'
    });
    if (!res.ok) {
      if (res.status === 401 || res.status === 403) stopLiveTracking();
      return;
    }
    const json = await res.json();
    if (json.success) {
      updateTrackingUI(json.data);
    }
  } catch (err) {
    console.error('Tracking error:', err);
  }
}


function renderManualPaymentPanel(trip) {
  const panel = document.getElementById('manualPaymentPanel');
  if (!panel) return;
  const payment = trip?.payment || {};
  const record = payment.record || null;
  const settings = payment.settings || {};

  if (payment.receipt_url) {
    currentReceiptUrl = payment.receipt_url;
    const receiptBtn = document.getElementById('receiptLinkBtn');
    if (receiptBtn) receiptBtn.style.display = 'inline-block';
  } else {
    currentReceiptUrl = null;
    const receiptBtn = document.getElementById('receiptLinkBtn');
    if (receiptBtn) receiptBtn.style.display = 'none';
  }
  const manual = settings.manual_transfer || {};
  const status = record?.status || trip?.payment_status || 'pending';
  const show = settings.active_provider === 'manual_transfer' && status !== 'captured' && status !== 'approved' && status !== 'refunded';
  panel.style.display = show ? 'block' : 'none';
  if (!show) return;

  const amount = record?.amount || trip?.fare || 0;
  document.getElementById('manualPaymentAmount').textContent = `₦${Number(amount).toLocaleString()}`;
  document.getElementById('manualPaymentBank').textContent = manual.bank_name || 'Bank details not set yet';
  document.getElementById('manualPaymentAccountName').textContent = manual.account_name || 'Account name pending';
  document.getElementById('manualPaymentAccountNumber').textContent = manual.account_number || 'Account number pending';
  document.getElementById('manualPaymentInstructions').textContent = manual.instructions || 'Transfer the exact fare, then upload your receipt for admin approval.';

  const statusEl = document.getElementById('manualPaymentStatus');
  const uploadBtn = panel.querySelector('button');
  const hasProof = !!record?.proof_path;
  if (status === 'failed' || status === 'rejected') {
    statusEl.textContent = record?.admin_notes ? `Rejected: ${record.admin_notes}` : 'Payment proof was rejected. Please upload a clearer proof.';
    uploadBtn.disabled = false;
  } else if (hasProof) {
    statusEl.textContent = 'Proof uploaded. Waiting for admin approval.';
    uploadBtn.disabled = false;
  } else {
    statusEl.textContent = 'Upload your receipt or transfer screenshot after paying.';
    uploadBtn.disabled = false;
  }
}

async function uploadManualPaymentProof() {
  if (!currentActiveTripId) return showToast('No active trip selected.');
  const fileInput = document.getElementById('manualPaymentProof');
  const file = fileInput?.files?.[0];
  if (!file) return showToast('Choose your transfer receipt or screenshot first.');

  const body = new FormData();
  body.append('trip_id', currentActiveTripId);
  body.append('_nonce', IDIBIA_PAYMENT_NONCE);
  body.append('bank_ref', document.getElementById('manualPaymentRef')?.value || '');
  body.append('payment_proof', file);

  try {
    const json = await idibiaPost('payment-proof-handler.php', body);
    if (json.success) {
      showToast(json.data?.message || 'Payment proof uploaded.');
      if (fileInput) fileInput.value = '';
      pollTracking();
    } else {
      showToast(json.data?.message || 'Could not upload payment proof.');
    }
  } catch (err) {
    showToast('Connection error uploading payment proof.');
  }
}

function updateTrackingUI(trip) {
  if (!trip) return;

  // Terminal states stop polling after rendering the final timeline once.
  const isTerminal = trip.status === 'completed' || trip.status === 'cancelled' || ['completed','cancelled'].includes(trip.dispatch_status);

  const drv = trip.driver;
  const driverName = drv?.full_name || drv?.first_name || 'Searching for driver';
  const shortName = drv?.first_name || driverName.split(' ')[0] || 'Driver';
  const initials = driverName.split(' ').filter(Boolean).map(part => part[0]).join('').slice(0, 2).toUpperCase() || 'DR';

  const drvName = document.getElementById('trackingDriverName');
  if (drvName) drvName.textContent = driverName;


  const drvPlate = document.getElementById('trackingDriverPlate');
  if (drvPlate) drvPlate.textContent = drv?.plate || (drv ? 'No plate' : 'Pending');

  const drvRating = document.getElementById('trackingDriverRating');
  if (drvRating) drvRating.textContent = drv ? `${drv.rating || '5.0'} · ${Number(drv.total_trips || 0).toLocaleString()} trips · ${drv.masked_phone || 'masked'}` : 'Waiting for assignment';

  const callBtn = document.getElementById('trackingCallButton');
  if (callBtn) {
      if (trip.driver) {
          callBtn.href = `tel:${trip.driver.phone || '08000000000'}`;
          callBtn.onclick = () => {
              if (currentActiveTripId) {
                  const body = new FormData();
                  body.append('action', 'log_event');
                  body.append('trip_id', currentActiveTripId);
                  body.append('event_type', 'customer_contacted_driver');
                  fetch('/customer-cancel-api.php', { method: 'POST', body, credentials: 'same-origin' }).catch(()=>{});
              }
              return true;
          };
      } else {
          callBtn.href = '#';
          callBtn.onclick = (e) => { e.preventDefault(); return false; };
      }
  }

  const drvAvatar = document.querySelector('.rider-avatar');
  if (drvAvatar) drvAvatar.innerHTML = drv ? `${escapeHtml(initials)}<div class="rider-online"></div>` : '…';

  const eta = trip.eta || {};
  const etaChip = document.getElementById('trackingEtaLabel');
  if (etaChip) etaChip.textContent = eta.label || statusLabel(trip.dispatch_status);

  const distanceLabel = document.getElementById('trackingDistanceLabel');
  if (distanceLabel) distanceLabel.textContent = eta.distance_km != null ? `${eta.distance_km} km remaining` : `Trip ${trip.trip_ref || ''}`;

  if (trip.driver && trip.driver.location) {
      updateMapLocation(trip.driver.location.lat, trip.driver.location.lng);
  }

  if (trip.pickup_location && trip.dropoff_location) {
     drawRouteOnMap([[trip.pickup_location.lat, trip.pickup_location.lng], [trip.dropoff_location.lat, trip.dropoff_location.lng]]);
  }

  const etaMinutes = document.getElementById('etaMinutes');
  if (etaMinutes) etaMinutes.textContent = eta.minutes != null ? eta.minutes : '--';

  const distanceValue = document.getElementById('trackingDistanceValue');
  if (distanceValue) distanceValue.textContent = eta.distance_km != null ? eta.distance_km : '--';

  // Cancel button logic
  const cancelBtnCont = document.getElementById('cancelBtnContainer');
  if (cancelBtnCont) {
    const cancellableStatuses = ['pending', 'searching', 'offered', 'accepted', 'no_driver'];
    if (cancellableStatuses.includes(trip.dispatch_status) && !['completed', 'cancelled'].includes(trip.status)) {
        cancelBtnCont.style.display = 'block';
    } else {
        cancelBtnCont.style.display = 'none';
    }
  }

  const fareValue = document.getElementById('trackingFareValue');
  if (fareValue) fareValue.textContent = `₦${Number(trip.fare || 0).toLocaleString()}`;
  renderManualPaymentPanel(trip);

  const pinText = document.getElementById('trackingPinText');
  if (pinText) pinText.textContent = trip.delivery_pin ? `Delivery PIN: ${trip.delivery_pin} · only share with your assigned driver at handoff.` : 'PIN pending. Support and cancellation actions remain available.';

  const stepsCont = document.querySelector('.tracking-steps');
  if (stepsCont) stepsCont.innerHTML = renderTrackingTimeline(trip);

  if (isTerminal) {
    stopLiveTracking();
    showToast('Trip is ' + trip.status);
  }
}

function statusLabel(status) {
  return ({
    searching: 'Finding nearby driver', offered: 'Awaiting driver acceptance', accepted: 'Driver assigned',
    arriving: 'Driver is arriving', arrived_pickup: 'Driver at pickup', picked_up: 'Package in transit',
    arrived_dropoff: 'Driver at drop-off', completed: 'Delivered', cancelled: 'Cancelled', no_driver: 'No driver available yet'
  })[status] || 'Tracking trip';
}

function renderTrackingTimeline(trip) {
  const timeline = Array.isArray(trip.timeline) && trip.timeline.length ? trip.timeline : [{ label: statusLabel(trip.dispatch_status), created_at: trip.created_at }];
  return timeline.map((event, idx) => {
    const active = idx === timeline.length - 1 && !['completed','cancelled'].includes(trip.dispatch_status);
    const dot = active ? 'active' : 'done';
    const when = event.created_at ? new Date(String(event.created_at).replace(' ', 'T') + 'Z').toLocaleString([], { month:'short', day:'numeric', hour:'numeric', minute:'2-digit' }) : 'Just now';
    return `<div class="t-step"><div class="t-step-dot ${dot}">${dot === 'done' ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="11" height="11"><polyline points="20 6 9 17 4 12"/></svg>' : '<svg viewBox="0 0 24 24" fill="currentColor" width="8" height="8"><circle cx="12" cy="12" r="5"/></svg>'}</div><div class="t-step-info"><h4>${escapeHtml(event.label || event.event_type || 'Trip update')}</h4><p>${escapeHtml(when)}</p></div></div>`;
  }).join('');
}

async function shareTrackingLink() {
  if (!currentActiveTripId) return;
  const body = new FormData();
  body.append('trip_id', currentActiveTripId);
  try {
      const json = await idibiaPost('tracking-token-api.php', body);
      if (json.success) {
          const url = `${window.location.origin}/index.php?track=${encodeURIComponent(json.data.token)}`;
          if (navigator.clipboard) navigator.clipboard.writeText(url).catch(() => {});
          showToast('Trip tracking link copied.');
      } else {
          showToast('Could not generate tracking link.');
      }
  } catch (e) {
      showToast('Error generating tracking link.');
  }
}

// ═══════════ TOAST ═══════════
let toastTimeout = null;
function showUnavailableFeature(title, detail) {
  showToast(`${title}: ${detail}`);
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  if (toastTimeout) clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => t.classList.remove('show'), 3000);
}

// ═══════════ KEYBOARD / ACCESSIBILITY ═══════════
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeAllModals();
});

// \u2500\u2500 REGISTRATION \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
async function doRegister() {
  const btn       = document.getElementById('regBtn');
  const errorBox  = document.getElementById('regError');
  const errorText = document.getElementById('regErrorText');

  const full_name = document.getElementById('regName').value.trim();
  const phone     = document.getElementById('regPhone').value.trim();
  const email     = document.getElementById('regEmail').value.trim();
  const password  = document.getElementById('regPassword').value;
  const terms     = document.getElementById('termsCheck').checked;
  const nonce     = document.getElementById('regNonce').value;

  errorBox.classList.remove('show');

  if ( ! full_name || ! phone || ! email || ! password ) {
    errorText.textContent = 'Please fill in all fields.';
    errorBox.classList.add('show');
    return;
  }

  btn.disabled    = true;
  btn.textContent = 'Creating account\u2026';

  try {
    const body = new FormData();
    body.append( '_nonce',    nonce );
    body.append( 'full_name', full_name );
    body.append( 'phone',     phone );
    body.append( 'email',     email );
    body.append( 'password',  password );
    body.append( 'terms',     terms ? '1' : '' );

    const json = await idibiaPost( 'register-handler.php', body );

    if ( json.success ) {
      enterCustomerApp( json.data?.first_name ? `Welcome, ${json.data.first_name}! 🎉` : 'Account created successfully.' );
    } else {
      errorText.textContent = json.data?.message || 'Registration failed. Please try again.';
      errorBox.classList.add('show');
    }
  } catch ( err ) {
    errorText.textContent = 'Could not reach Idibia right now. Please check your connection and try again.';
    errorBox.classList.add('show');
  } finally {
    btn.disabled  = false;
    btn.innerHTML = 'Create Account &amp; Verify <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
  }
}

// \u2500\u2500 RESEND VERIFICATION EMAIL \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
async function resendCode() {
  showToast( 'Sending a new code\u2026' );
  try {
    const body = new FormData();
    body.append( '_nonce', IDIBIA_VERIFY_NONCE );

    const json = await idibiaPost( 'resend-code.php', body );
    if ( json.success ) {
      showToast( 'New code sent! Check your inbox.' );
    } else {
      showToast( json.data?.message || 'Could not resend. Try again.' );
    }
  } catch {
    showToast( 'Could not reach Idibia right now. Try again.' );
  }
}

// \u2500\u2500 EMAIL VERIFICATION \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
async function doVerify() {
  const btn       = document.getElementById('verifyBtn');
  const errorBox  = document.getElementById('verifyError');
  const errorText = document.getElementById('verifyErrorText');
  const inputs    = document.querySelectorAll('.otp-input');

  const code = Array.from(inputs).map(i => i.value.trim()).join('');

  errorBox.classList.remove('show');

  if ( code.length < 5 ) {
    errorText.textContent = 'Enter all 5 digits of the code.';
    errorBox.classList.add('show');
    return;
  }

  btn.disabled    = true;
  btn.textContent = 'Verifying\u2026';

  try {
    const body = new FormData();
    body.append( '_nonce', IDIBIA_VERIFY_NONCE );
    body.append( 'code', code );

    const json = await idibiaPost( 'verify-handler.php', body );

    if ( json.success ) {
      const name = json.data?.first_name || '';
      inputs.forEach( i => i.value = '' );
      enterCustomerApp( name ? `Welcome, ${name}! 🎉` : 'Email verified! Welcome.' );
    } else {
      errorText.textContent = json.data?.message || 'Verification failed. Please try again.';
      errorBox.classList.add('show');
    }
  } catch ( err ) {
    errorText.textContent = 'Could not reach Idibia right now. Please check your connection and try again.';
    errorBox.classList.add('show');
  } finally {
    btn.disabled  = false;
    btn.innerHTML = 'Verify &amp; Get Started <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><polyline points="20 6 9 17 4 12"/></svg>';
  }
}

// ═══════════ QUOTE AND BOOKING ═══════════
let currentQuoteId = null;

async function requestQuote() {
  const pickup = document.getElementById('pickupInput').value.trim();
  const dropoff = document.getElementById('dropoffInput').value.trim();
  const cat = selectedCategory.toLowerCase(); // Package, Food, etc.

  // Hardcode vehicle type for now or pick based on cat
  const vehicle = cat === 'package' ? 'bike' : 'car';

  if (!pickup || !dropoff) {
    showToast('Please enter both pickup and drop-off locations.');
    return;
  }

  const btn = document.querySelector('button[onclick="requestQuote()"]');
  if(btn) {
    btn.disabled = true;
    btn.textContent = 'Calculating...';
  }

  try {
    const body = new FormData();
    body.append('pickup', pickup);
    body.append('dropoff', dropoff);
    body.append('category', cat);
    body.append('vehicle_type', vehicle);
    if (typeof selectedScheduleTime !== 'undefined' && selectedScheduleTime !== null) {
      body.append('scheduled_time', selectedScheduleTime);
    }

    const json = await idibiaPost('quote-api.php', body);

    if (json.success) {
      currentQuoteId = json.data.quote_id;
      await confirmBooking();
    } else {
      showToast(json.data?.message || 'Could not calculate fare. Try again.');
      if(btn) {
        btn.disabled = false;
        btn.textContent = 'Find Rider';
      }
    }
  } catch (err) {
    showToast('Connection error calculating fare.');
    if(btn) {
      btn.disabled = false;
      btn.textContent = 'Find Rider';
    }
  }
}

async function confirmBooking() {
  if (!currentQuoteId) return;

  try {
    const body = new FormData();
    body.append('quote_id', currentQuoteId);

    const json = await idibiaPost('book-trip-handler.php', body);

    if (json.success) {
      showToast(json.data.message);
      startLiveTracking(json.data.trip_id || 1);

      document.getElementById('pickupInput').value = '';
      document.getElementById('dropoffInput').value = '';
      currentQuoteId = null;
      selectedScheduleTime = null;
      const schedBtn = document.getElementById('schedLater');
      if (schedBtn) {
        schedBtn.classList.remove('active');
        schedBtn.textContent = 'Schedule';
      }

      const btn = document.querySelector('button[onclick="requestQuote()"]');
      if(btn) {
        btn.disabled = false;
        btn.textContent = 'Find Rider';
      }
    } else {
      if (json.data?.message && json.data.message.toLowerCase().includes('expired')) {
        const requote = confirm('Your quote has expired. Would you like to recalculate and try again?');
        if (requote) {
            currentQuoteId = null;
            requestQuote();
            return;
        }
      }
      showToast(json.data?.message || 'Could not book trip.');
      const btn = document.querySelector('button[onclick="requestQuote()"]');
      if(btn) {
        btn.disabled = false;
        btn.textContent = 'Find Rider';
      }
    }
  } catch(err) {
     showToast('Connection error booking trip.');
     const btn = document.querySelector('button[onclick="requestQuote()"]');
     if(btn) {
       btn.disabled = false;
       btn.textContent = 'Find Rider';
     }
  }
}


// ═══════════ TRIP DETAILS AND REORDER ═══════════
async function showTripDetails(tripId) {
  try {
    const res = await fetch(`${IDIBIA_API_BASE}/trip-feed-api.php?trip_id=${tripId}`, {
      credentials: 'same-origin'
    });
    if (!res.ok) return showToast('Could not fetch trip details');
    const json = await res.json();
    if (json.success) {
      const trip = json.data;
      currentActiveTripId = trip.id;

      document.getElementById('td-status').textContent = trip.status;
      document.getElementById('td-pickup').textContent = trip.pickup;
      document.getElementById('td-dropoff').textContent = trip.dropoff;
      document.getElementById('td-fare').textContent = '₦' + parseFloat(trip.fare).toLocaleString();

      const reorderBtn = document.getElementById('td-reorder-btn');
      if (reorderBtn) reorderBtn.onclick = () => reorderTrip(trip.pickup, trip.dropoff, trip.category || 'package');

      openModal('trip-details');
    }
  } catch (err) {
    showToast('Connection error fetching trip details');
  }
}

function reorderTrip(pickup, dropoff, category) {
  closeModal(null, 'trip-details');
  document.getElementById('pickupInput').value = pickup;
  document.getElementById('dropoffInput').value = dropoff;

  // Set category
  const tabs = document.querySelectorAll('.cat-tab');
  tabs.forEach(t => t.classList.remove('active'));
  for (let t of tabs) {
    if (t.textContent.trim().toLowerCase() === category.toLowerCase()) {
      t.classList.add('active');
      selectedCategory = t.textContent.trim();
      break;
    }
  }

  goTo('screen-main');
  requestQuote();
}

// ═══════════ SAVED ADDRESSES ═══════════
async function fetchSavedAddresses() {
  try {
    const res = await fetch(`${IDIBIA_API_BASE}/get-addresses-api.php`, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    if (json.success && json.data.addresses) {
      renderAddressChips(json.data.addresses);
    }
  } catch (err) {
    console.error("Failed to load saved addresses", err);
  }
}

function renderAddressChips(addresses) {
  const pickupCont = document.getElementById('pickupChips');
  const dropoffCont = document.getElementById('dropoffChips');
  if (!pickupCont || !dropoffCont) return;

  let html = '';
  addresses.forEach(addr => {
    // Only display first part of address for brevity
    const shortAddr = escapeHtml(addr.address.split(',')[0]);
    html += `<div class="filter-pill" style="display:inline-flex; align-items:center; gap:4px; padding:4px 8px;font-size:11px;" title="${escapeHtml(addr.address)}">
      <span onclick="fillAddress(this, '${escapeHtml(addr.address)}')">${escapeHtml(addr.label)}: ${shortAddr}</span>
      <span style="cursor:pointer; color:var(--danger);" onclick="deleteAddress('${escapeHtml(addr.label)}')">&times;</span>
    </div>`;
  });

  pickupCont.innerHTML = html;
  dropoffCont.innerHTML = html;
}

function fillAddress(el, address) {
  const inputId = el.parentElement.parentElement.parentElement.querySelector('input').id;
  document.getElementById(inputId).value = address;
}

async function saveAddress(inputId) {
  const address = document.getElementById(inputId).value.trim();
  if (!address) {
    showToast('Please enter an address to save.');
    return;
  }
  const label = prompt('Enter a label for this address (e.g. Home, Work):');
  if (!label) return;

  try {
    const body = new FormData();
    body.append('label', label);
    body.append('address', address);

    const json = await idibiaPost('save-address-api.php', body);

    if (json.success) {
      showToast(json.data.message);
      renderAddressChips(json.data.addresses);
    } else {
      showToast(json.data?.message || 'Could not save address.');
    }
  } catch(err) {
     showToast('Connection error saving address.');
  }
}

async function deleteAddress(label) {
  if (!confirm(`Delete saved address '${label}'?`)) return;
  try {
    const body = new FormData();
    body.append('label', label);
    const json = await idibiaPost('delete-address-api.php', body);
    if (json.success) {
      showToast(json.data.message);
      renderAddressChips(json.data.addresses);
    } else {
      showToast(json.data?.message || 'Could not delete address.');
    }
  } catch(err) {
     showToast('Connection error deleting address.');
  }
}

// ═══════════ RECENT ACTIVITY ═══════════
async function fetchRecentActivity() {
  const container = document.getElementById('activityCards');
  if (!container) return;

  try {
    const res = await fetch(`${IDIBIA_API_BASE}/customer-trips-api.php`, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });

    const json = await res.json();

    if (json.success && json.data.trips.length > 0) {
      // ⚡ Bolt: Accumulate HTML in a string to avoid O(N^2) innerHTML reflows inside loop
      let tripsHtml = '';

      json.data.trips.forEach(trip => {
        const date = new Date(trip.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });

        let iconHtml = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>';

        const isTerm = trip.status === 'completed' || trip.status === 'cancelled';
        const clickAction = isTerm ? `showTripDetails(${trip.id})` : `startLiveTracking(${trip.id})`;
        tripsHtml += `
        <div class="activity-card" onclick="${clickAction}">
          <div class="ac-icon">${iconHtml}</div>
          <div class="ac-details">
            <strong>${trip.dropoff_address || trip.dropoff || 'Delivery'}</strong>
            <p>${date} • ${trip.status}</p>
          </div>
          <div class="ac-amt">₦${parseFloat(trip.fare).toLocaleString()}</div>
        </div>`;
      });

      container.innerHTML = tripsHtml;
    } else {
       container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted)">No recent activity</div>';
    }
  } catch (err) {
    console.error("Failed to load trips", err);
  }
}

// Call it when showing main screen
const origEnter = window.enterCustomerApp;
window.enterCustomerApp = function(msg) {
    if(origEnter) origEnter(msg);
    fetchRecentActivity();
    fetchSavedAddresses();
    setTimeout(() => initLeafletMap('home-map-container', 6.5244, 3.3792), 300);
};

// ═══════════ LEAFLET MAP INTEGRATION ═══════════
let currentMap = null;
let currentMarker = null;
let currentRouteLayer = null;
let currentReceiptUrl = null;

function initLeafletMap(containerId, lat, lng, isTracking = false) {
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

  if (isTracking) {
    currentMarker = L.marker([lat, lng], { icon }).addTo(currentMap);
  }
}

function updateMapLocation(lat, lng) {
  if (currentMap && currentMarker) {
    const newLatLng = new L.LatLng(lat, lng);
    currentMarker.setLatLng(newLatLng);
    currentMap.panTo(newLatLng);
  }
}

function drawRouteOnMap(routeCoordinates) {
    if (!currentMap) return;
    if (currentRouteLayer) {
        currentMap.removeLayer(currentRouteLayer);
    }

    currentRouteLayer = L.polyline(routeCoordinates, {color: 'var(--navy)', weight: 5, opacity: 0.7}).addTo(currentMap);
    currentMap.fitBounds(currentRouteLayer.getBounds(), { padding: [50, 50] });
}

// ═══════════ PROFILE EDIT ═══════════
async function submitProfileEdit(e) {
  e.preventDefault();
  const btn = document.getElementById('profileSaveBtn');
  const initialText = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Saving...';

  try {
    const body = new FormData();
    const newFullName = document.getElementById('profileFullName').value.trim();
    body.append('_nonce', window.idibiaProfileNonce);
    body.append('full_name', newFullName);

    const json = await idibiaPost('profile-api.php', body);

    if (json.success) {
      showToast(json.data?.message || 'Profile updated successfully.');
      closeModal(null, 'profile');

      // Update DOM elements dynamically
      const avatarName = document.querySelector('.avatar-name');
      if (avatarName) avatarName.textContent = newFullName;

      // Look for the "Profile Details" account row to update its meta element
      const accountRows = document.querySelectorAll('.account-row');
      accountRows.forEach(row => {
          const label = row.querySelector('.account-row-label');
          if (label && label.textContent === 'Profile Details') {
              const meta = row.querySelector('.account-row-meta');
              if (meta) meta.textContent = newFullName;
          }
      });

      // Update initials
      const parts = newFullName.split(' ').filter(p => p.length > 0);
      let initials = '';
      if (parts.length >= 2) {
          initials = parts[0][0].toUpperCase() + parts[1][0].toUpperCase();
      } else if (parts.length === 1) {
          initials = parts[0].substring(0, 2).toUpperCase();
      }
      if (!initials) initials = 'CU';

      const avatarIcon = document.querySelector('.avatar');
      if (avatarIcon) {
          let foundTextNode = false;
          for (let node of avatarIcon.childNodes) {
              if (node.nodeType === Node.TEXT_NODE) {
                  node.nodeValue = initials;
                  foundTextNode = true;
                  break;
              }
          }
          if (!foundTextNode) {
              avatarIcon.appendChild(document.createTextNode(initials));
          }
      }

    } else {
      showToast(json.data?.message || 'Could not update profile.');
    }
  } catch (err) {
    showToast('Connection error updating profile.');
  } finally {
    btn.disabled = false;
    btn.textContent = initialText;
  }
}

async function submitSupportTicket() {
  const category = document.getElementById('support_category')?.value || 'general';
  const subject = document.getElementById('support_subject')?.value?.trim() || '';
  const messageText = document.getElementById('support_message')?.value?.trim() || '';

  if (!messageText || !subject) {
      showToast('Subject and message are required.');
      return;
  }

  const combinedMessage = `Subject: ${subject}\n\n${messageText}`;

  const body = new FormData();
  body.append('action', 'create_ticket');
  body.append('_nonce', window.idibiaSupportNonce);
  body.append('category', category);
  body.append('message', combinedMessage);

  const btn = document.getElementById('btnSubmitSupport');
  const oldText = btn.textContent;
  btn.textContent = 'Submitting...';
  btn.disabled = true;

  try {
    const json = await idibiaPost('/support-api.php', body);
    if (json.success) {
      showToast(json.data?.message || 'Support ticket opened.');
      closeModal(null, 'support');
      document.getElementById('supportForm')?.reset();
    } else {
      showToast(json.data?.message || 'Could not submit support ticket.');
    }
  } catch (e) {
    showToast('Connection error submitting support ticket.');
  } finally {
    btn.textContent = oldText;
    btn.disabled = false;
  }
}

async function uploadCustomerAvatar(event) {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('action', 'upload_avatar');
    formData.append('avatar', file);
    formData.append('_nonce', window.idibiaProfileNonce || '');

    showToast('Uploading profile picture...');
    try {
        const response = await fetch('/profile-api.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        const json = await response.json();
        if (json.success) {
            showToast('Profile picture updated.');
            if (json.data?.avatar_path) {
                window.idibiaCustomerAvatar = json.data.avatar_path;
                // Update DOM directly since customer doesn't have a renderProfile function
                const avatarWrap = document.querySelector('.avatar');
                if (avatarWrap) {
                    let img = avatarWrap.querySelector('img');
                    if (!img) {
                        // clear initials
                        const contents = Array.from(avatarWrap.childNodes).filter(node =>
                            node.nodeType === Node.TEXT_NODE ||
                            (node.nodeType === Node.ELEMENT_NODE && !node.classList.contains('camera-badge') && node.tagName !== 'INPUT')
                        );
                        contents.forEach(node => avatarWrap.removeChild(node));

                        img = document.createElement('img');
                        img.alt = 'Avatar';
                        img.style.width = '100%';
                        img.style.height = '100%';
                        img.style.borderRadius = '50%';
                        img.style.objectFit = 'cover';

                        // Insert img before the camera badge
                        const badge = avatarWrap.querySelector('.camera-badge');
                        if (badge) {
                            avatarWrap.insertBefore(img, badge);
                        } else {
                            avatarWrap.appendChild(img);
                        }
                    }
                    img.src = '/wp/wp-content/uploads/' + json.data.avatar_path;
                }
            }
        } else {
            showToast(json.data?.message || 'Could not upload picture.');
        }
    } catch (err) {
        showToast('Connection error uploading picture.');
    }
}

async function savePreferences() {
  const tripUpdates = document.getElementById('pref_trip_updates')?.checked ? 'true' : 'false';
  const promotions = document.getElementById('pref_promotions')?.checked ? 'true' : 'false';
  const emailReceipts = document.getElementById('pref_email_receipts')?.checked ? 'true' : 'false';

  const body = new FormData();
  body.append('_nonce', window.idibiaVerifyNonce); // Using an existing nonce, wait, preferences-api uses idibia_verify or idibia_profile_update.
  body.append('trip_updates', tripUpdates);
  body.append('promotions', promotions);
  body.append('email_receipts', emailReceipts);

  const btn = document.getElementById('btnSavePreferences');
  const oldText = btn.textContent;
  btn.textContent = 'Saving...';
  btn.disabled = true;

  try {
    const json = await idibiaPost('/preferences-api.php', body);
    if (json.success) {
      showToast('Preferences updated.');
      closeModal(null, 'preferences');
      // Update UI toggle
      const notifChip = document.querySelector('.account-row[onclick="openPreferencesModal()"] .chip');
      if (notifChip) {
          if (tripUpdates === 'true') {
              notifChip.innerText = 'On';
              notifChip.className = 'chip chip-success';
          } else {
              notifChip.innerText = 'Off';
              notifChip.className = 'chip chip-warning';
          }
      }
    } else {
      showToast(json.data?.message || 'Could not update preferences.');
    }
  } catch (e) {
    showToast('Connection error updating preferences.');
  } finally {
    btn.textContent = oldText;
    btn.disabled = false;
  }
}

async function openPreferencesModal() {
  openModal('preferences');
  try {
    const json = await idibiaPost('/preferences-api.php', new FormData(), { method: 'GET' });
    if (json.success && json.data?.preferences) {
      const prefs = json.data.preferences;
      const tripUpdatesEl = document.getElementById('pref_trip_updates');
      if (tripUpdatesEl) tripUpdatesEl.checked = prefs.trip_updates;
      const promotionsEl = document.getElementById('pref_promotions');
      if (promotionsEl) promotionsEl.checked = prefs.promotions;
      const emailReceiptsEl = document.getElementById('pref_email_receipts');
      if (emailReceiptsEl) emailReceiptsEl.checked = prefs.email_receipts;
    }
  } catch (e) {
    // silently fail
  }
}
