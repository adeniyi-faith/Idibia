// ═══════════ STATE ═══════════
let currentScreen = 'screen-main';
let screenHistory = ['screen-main'];
let onbSlide = 0;
let selectedCategory = 'Package';
let pickupCoords = null;
let dropoffCoords = null;
let pinMap = null;
let pinField = null;
let bookingPickupMarker = null;
let bookingDropoffMarker = null;
let _homeMapActive = false;
let _pinReverseTimer = null;
let _saveAddressInputId = null;
let _savedAddresses = [];            // client-side cache of the customer's saved addresses
let _savedFormCoords = null;         // coords picked while adding/editing in the manager modal
let _editingOriginalLabel = null;    // when set, the manager form is updating this existing label
let _savedManagerWired = false;      // ensures the manager's autocomplete is wired only once
let currentRating = 5;
let etaInterval = null;
const IDIBIA_API_BASE = new URL('.', window.location.href).href.replace(/\/$/, '');
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
      transport: 'ajax'
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
  initPhotonAutocomplete('pickupInput',  'pickupSuggestions',  c => { pickupCoords  = c; updateBookingMapMarkers(); });
  initPhotonAutocomplete('dropoffInput', 'dropoffSuggestions', c => { dropoffCoords = c; updateBookingMapMarkers(); });
  fetchSavedAddresses(); // load saved places so they're available in the inputs on first paint
  setTimeout(() => initLeafletMap('home-map-container', 6.5244, 3.3792), 500);
  // Auto-resume tracking if the customer has an active trip (handles page refresh mid-trip).
  if (window.idibiaActiveTrip) {
    setTimeout(() => startLiveTracking(window.idibiaActiveTrip), 400);
  }
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
  const btn = document.querySelector('button[onclick="doLogin()"]');
  const initialHtml = btn.innerHTML;
  btn.classList.add('btn-loading');
  const errorBox  = document.getElementById('authError');
  const errorText = document.getElementById('authErrorText');
  const identifier = document.getElementById('loginEmail').value.trim();
  const password   = document.getElementById('loginPass').value;

  errorBox.classList.remove('show');

  if (!identifier || !password) {
    errorText.textContent = 'Enter your email/phone and password.';
    errorBox.classList.add('show');
    btn.classList.remove('btn-loading');
    btn.innerHTML = initialHtml;
    return;
  }

  try {
    const body = new FormData();
    body.append('email', identifier);
    body.append('password', password);

    const json = await idibiaPost('login-handler.php', body);
    if (json.success) {
      window.location.href = json.data?.redirect || 'dashboard.php';
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
    document.querySelectorAll('.sidebar-btn').forEach(b => {
      if (b.textContent.trim().toLowerCase().includes(name)) b.classList.add('active');
    });
  }

  document.querySelectorAll('.bnav-btn').forEach(b => b.classList.remove('active'));
  const id = bnavId || name;
  const bnav = document.getElementById('bnav-' + id);
  if (bnav) bnav.classList.add('active');

  if (name === 'home' && currentMap) {
    // Wait for the screen slide-in transition (380ms) before remeasuring
    setTimeout(() => currentMap.invalidateSize(), 420);
  }
  if (name === 'map') {
    // Delay past the 380ms screen transition so Leaflet measures the settled position
    setTimeout(() => initOrShowExploreMap(), 420);
  }
  if (name === 'activity') {
    fetchActivityTrips();
  }
}

// ═══════════ PHOTON AUTOCOMPLETE ═══════════
const _photonTimers = {};

function initPhotonAutocomplete(inputId, suggestionsId, setCoords) {
  const input = document.getElementById(inputId);
  const box = document.getElementById(suggestionsId);
  if (!input || !box) return;

  input.addEventListener('input', () => {
    setCoords(null);
    clearTimeout(_photonTimers[inputId]);
    const q = input.value.trim();
    if (q.length < 3) { _showPinOnly(box, input); return; }
    _photonTimers[inputId] = setTimeout(() => _fetchPhoton(q, box, input, setCoords), 400);
  });

  // Tapping into an empty/short field still surfaces the "Pin on map" escape hatch
  input.addEventListener('focus', () => {
    if (input.value.trim().length < 3) _showPinOnly(box, input);
  });

  document.addEventListener('click', e => {
    if (!input.contains(e.target) && !box.contains(e.target)) {
      box.style.display = 'none';
    }
  });
}

// Renders the standalone "Pin location on map" row — the fallback escape hatch
// shown whenever there are too few characters to search or a lookup fails.
function _pinRowHtml(field) {
  return `<div class="photon-pin-row" data-pin-field="${escapeHtml(field)}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
    Can't find it? Pin location on map
  </div>`;
}

function _wirePinRows(box) {
  box.querySelectorAll('.photon-pin-row').forEach(row => {
    row.addEventListener('mousedown', e => {
      e.preventDefault();
      box.innerHTML = '';
      box.style.display = 'none';
      openPinModal(row.dataset.pinField);
    });
  });
}

// Saved-address quick-picks and the "Pin on map" escape hatch only make sense
// on the booking inputs — not on the manager's address field, which reuses the
// same autocomplete purely for searching.
function _isBookingInput(input) {
  return input.id === 'pickupInput' || input.id === 'dropoffInput';
}

function _showPinOnly(box, input) {
  if (!_isBookingInput(input)) { box.innerHTML = ''; box.style.display = 'none'; return; }
  const field = input.id.replace('Input', '');
  box.innerHTML = _savedAddressRowsHtml() + _pinRowHtml(field);
  box.style.display = 'block';
  _wireSavedRows(box, input);
  _wirePinRows(box);
}

// Quick-pick rows for the customer's saved addresses, shown at the top of an
// input's dropdown when it's focused/empty. Available to BOTH pickup and
// drop-off without rendering a duplicated, always-on chip strip.
function _savedAddressRowsHtml() {
  if (!_savedAddresses.length) return '';
  return _savedAddresses.map((a, i) => {
    const short = escapeHtml((a.address || '').split(',').slice(0, 3).join(',').trim());
    return `<div class="photon-saved-row" data-saved-idx="${i}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
      <span><strong>${escapeHtml(a.label)}</strong> · ${short}</span>
    </div>`;
  }).join('');
}

function _wireSavedRows(box, input) {
  box.querySelectorAll('.photon-saved-row').forEach(row => {
    row.addEventListener('mousedown', e => {
      e.preventDefault();
      const addr = _savedAddresses[parseInt(row.dataset.savedIdx, 10)];
      if (addr) _applySavedAddress(input, addr);
      box.innerHTML = '';
      box.style.display = 'none';
    });
  });
}

// Fills an input from a saved address and restores its coordinates so the
// quote uses the exact saved point instead of re-geocoding the text.
function _applySavedAddress(input, addr) {
  input.value = addr.address || '';
  input.blur(); // dismiss mobile keyboard cleanly before layout shifts
  const lat = parseFloat(addr.lat);
  const lng = parseFloat(addr.lng);
  const coords = (lat && lng) ? { lat, lng } : null;
  if (input.id === 'pickupInput') pickupCoords = coords;
  else if (input.id === 'dropoffInput') dropoffCoords = coords;
  updateBookingMapMarkers();
}

async function _fetchPhoton(q, box, input, setCoords) {
  const inputId = input.id;
  try {
    const url = `https://photon.komoot.io/api/?q=${encodeURIComponent(q)}&limit=5&lang=en&bbox=2.7,4.0,14.7,13.9`;
    const res = await fetch(url);
    let photonFeatures = [];
    if (res.ok) {
      const data = await res.json();
      photonFeatures = (data.features || []).filter(f => f.geometry?.coordinates);
    }

    // If Photon has no results, fall back to Nominatim with Nigeria country code
    let nominatimResults = [];
    if (photonFeatures.length < 2) {
      nominatimResults = await _fetchNominatimSuggestions(q);
    }

    const hasResults = photonFeatures.length > 0 || nominatimResults.length > 0;
    const field = inputId.replace('Input', '');

    let html = '';

    // Render Photon results
    if (photonFeatures.length > 0) {
      box._photonFeatures = photonFeatures;
      html += photonFeatures.map((f, i) => {
        const p = f.properties;
        const label = [p.name, p.city || p.county, p.state, 'Nigeria'].filter(Boolean).join(', ');
        return `<div class="photon-item" data-src="photon" data-idx="${i}">${escapeHtml(label)}</div>`;
      }).join('');
    }

    // Render Nominatim fallback results (avoid duplicates)
    if (nominatimResults.length > 0) {
      box._nominatimResults = nominatimResults;
      const photonNames = new Set(photonFeatures.map(f => {
        const p = f.properties;
        return [p.name, p.city || p.county].filter(Boolean).join(',').toLowerCase();
      }));
      nominatimResults.forEach((item, i) => {
        const shortLabel = item.display_name.split(',').slice(0, 4).join(',').trim();
        const key = shortLabel.split(',').slice(0, 2).join(',').toLowerCase();
        if (!photonNames.has(key)) {
          html += `<div class="photon-item" data-src="nominatim" data-idx="${i}">${escapeHtml(shortLabel)}</div>`;
        }
      });
    }

    if (!hasResults) {
      html += `<div class="photon-no-results">No results found</div>`;
    }

    // Offer "Pin on map" as the last option — only for the booking inputs.
    if (_isBookingInput(input)) html += _pinRowHtml(field);

    box.innerHTML = html;
    box.style.display = 'block';

    box.querySelectorAll('.photon-item').forEach(item => {
      item.addEventListener('mousedown', e => {
        e.preventDefault();
        const src = item.dataset.src;
        const idx = parseInt(item.dataset.idx);
        if (src === 'photon') {
          const f = box._photonFeatures[idx];
          const p = f.properties;
          const displayName = [p.name, p.city || p.county, p.state, 'Nigeria'].filter(Boolean).join(', ');
          const [lng, lat] = f.geometry.coordinates;
          input.value = displayName;
          setCoords({ lat, lng });
        } else {
          const d = box._nominatimResults[idx];
          const label = d.display_name.split(',').slice(0, 4).join(',').trim();
          input.value = label;
          setCoords({ lat: parseFloat(d.lat), lng: parseFloat(d.lon) });
        }
        input.blur(); // dismiss mobile keyboard cleanly before layout shifts (mirrors _applySavedAddress)
        box.innerHTML = '';
        box.style.display = 'none';
      });
    });

    _wirePinRows(box);

  } catch (e) {
    // Network/search failure — still offer the pin-on-map escape hatch
    _showPinOnly(box, input);
  }
}

async function _fetchNominatimSuggestions(q) {
  try {
    const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(q)}&format=json&limit=5&countrycodes=ng&addressdetails=0&accept-language=en`;
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) return [];
    const data = await res.json();
    return Array.isArray(data) ? data : [];
  } catch {
    return [];
  }
}

async function _reverseGeocode(lat, lng) {
  try {
    const url = `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&accept-language=en`;
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) return `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    const data = await res.json();
    if (data.display_name) {
      return data.display_name.split(',').slice(0, 4).join(',').trim();
    }
    return `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
  } catch {
    return `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
  }
}

// ═══════════ USE MY LOCATION ═══════════
function useMyLocation(field) {
  if (!navigator.geolocation) {
    showToast('Location not available on this device');
    return;
  }
  const btn = document.getElementById(field + 'GpsBtn');
  if (btn) btn.classList.add('loading');
  showToast('Getting your location…');

  navigator.geolocation.getCurrentPosition(
    async pos => {
      const { latitude: lat, longitude: lng } = pos.coords;
      if (field === 'pickup') pickupCoords = { lat, lng };
      else dropoffCoords = { lat, lng };
      updateBookingMapMarkers();
      const address = await _reverseGeocode(lat, lng);
      const input = document.getElementById(field + 'Input');
      if (input) input.value = address;
      if (btn) btn.classList.remove('loading');
    },
    () => {
      if (btn) btn.classList.remove('loading');
      showLocPermissionHint('loc-perm-hint', field);
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 }
  );
}

// ═══════════ PIN ON MAP ═══════════
function openPinModal(field) {
  pinField = field;
  const overlay = document.getElementById('pin-location-overlay');
  if (!overlay) return;
  document.getElementById('pinModalTitle').textContent =
    field === 'pickup' ? 'Pin Pickup Location' : 'Pin Delivery Location';
  overlay.style.display = 'flex';

  if (!window.L) { showToast('Map not ready, try again'); overlay.style.display = 'none'; return; }

  if (!pinMap) {
    pinMap = L.map('pin-map', { zoomControl: true, attributionControl: true })
      .setView([6.5244, 3.3792], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
      maxZoom: 19
    }).addTo(pinMap);
    pinMap.on('moveend', () => {
      clearTimeout(_pinReverseTimer);
      document.getElementById('pinAddressLabel').textContent = 'Getting address…';
      _pinReverseTimer = setTimeout(_updatePinAddress, 700);
    });
  }

  setTimeout(() => pinMap.invalidateSize(), 80);

  const existingCoords = field === 'pickup' ? pickupCoords : dropoffCoords;
  if (existingCoords) {
    pinMap.setView([existingCoords.lat, existingCoords.lng], 16);
  } else if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      pos => { if (pinMap) pinMap.setView([pos.coords.latitude, pos.coords.longitude], 16); },
      () => {}
    );
  }

  _updatePinAddress();
}

async function _updatePinAddress() {
  if (!pinMap) return;
  const { lat, lng } = pinMap.getCenter();
  const address = await _reverseGeocode(lat, lng);
  const label = document.getElementById('pinAddressLabel');
  if (label) label.textContent = address;
}

function confirmPin() {
  if (!pinMap) return;
  const { lat, lng } = pinMap.getCenter();
  const label = document.getElementById('pinAddressLabel');
  const address = (label && label.textContent && label.textContent !== 'Getting address…')
    ? label.textContent
    : `${lat.toFixed(5)}, ${lng.toFixed(5)}`;

  const input = document.getElementById(pinField + 'Input');
  if (input) input.value = address;
  if (pinField === 'pickup') pickupCoords = { lat, lng };
  else dropoffCoords = { lat, lng };
  updateBookingMapMarkers();

  closePinModal();
  showToast('Location pinned');
}

function closePinModal() {
  const overlay = document.getElementById('pin-location-overlay');
  if (overlay) overlay.style.display = 'none';
  clearTimeout(_pinReverseTimer);
}

// ═══════════ MAP SIZING ═══════════
// Leaflet caches the container size at init time. Inside nested flexbox the
// final height can settle a frame (or more) later, especially on mobile
// Safari, leaving the map rendered at a stale, partial size with a blank gap.
// A ResizeObserver re-syncs Leaflet to the container's real size whenever it
// changes, so the map always fills its tab section on mobile and desktop.
function keepMapSized(map) {
  if (!map || typeof ResizeObserver === 'undefined') return;
  const el = map.getContainer();
  let raf = null;
  const ro = new ResizeObserver(() => {
    if (!document.contains(el)) { ro.disconnect(); return; }
    if (raf) cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => {
      try { map.invalidateSize({ animate: false }); } catch (_) { ro.disconnect(); }
    });
  });
  ro.observe(el);
}

// ═══════════ EXPLORE MAP (MAP TAB) ═══════════
let exploreMap = null;

function initOrShowExploreMap() {
  const el = document.getElementById('explore-map-container');
  if (!el) return;
  if (!window.L) { setTimeout(() => initOrShowExploreMap(), 800); return; }
  if (exploreMap) {
    // Invalidate once the screen transition (380ms) has fully settled
    setTimeout(() => {
      if (exploreMap) {
        exploreMap.invalidateSize({ animate: false });
        exploreMap.setView(exploreMap.getCenter(), exploreMap.getZoom(), { animate: false });
      }
    }, 50);
    return;
  }
  if (!el.offsetWidth || !el.offsetHeight) {
    setTimeout(() => initOrShowExploreMap(), 100);
    return;
  }
  exploreMap = L.map('explore-map-container', { zoomControl: true }).setView([6.5244, 3.3792], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(exploreMap);
  keepMapSized(exploreMap);
  setTimeout(() => exploreMap && exploreMap.invalidateSize(), 150);
  setTimeout(() => exploreMap && exploreMap.invalidateSize(), 700);
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      if (exploreMap) exploreMap.setView([pos.coords.latitude, pos.coords.longitude], 14);
    }, () => {});
  }
}

function centerExploreMap() {
  if (!navigator.geolocation) { showToast('Location not available'); return; }
  showToast('Locating you...');
  navigator.geolocation.getCurrentPosition(pos => {
    if (exploreMap) exploreMap.setView([pos.coords.latitude, pos.coords.longitude], 15);
  }, () => showLocPermissionHint('explore-loc-perm-hint'));
}

// ═══════════ BOOKING OPTIONS TOGGLE ═══════════
function toggleBookingOptions() {
  const panel = document.getElementById('bookingOptions');
  const chevron = document.getElementById('optionsChevron');
  if (!panel) return;
  const isOpen = panel.style.display !== 'none';
  panel.style.display = isOpen ? 'none' : 'block';
  if (chevron) chevron.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
}

function updateOptionsLabel() {
  const label = document.getElementById('optionsLabel');
  if (!label) return;
  const activeService = document.querySelector('.service-flag.active .sf-title');
  const serviceName = activeService ? activeService.textContent : 'Standard';
  label.textContent = selectedCategory + ' · ' + serviceName;
}

// ═══════════ HOME PANEL ═══════════
function swapLocations() {
  const pickup = document.getElementById('pickupInput');
  const dropoff = document.getElementById('dropoffInput');
  const temp = pickup.value;
  pickup.value = dropoff.value;
  dropoff.value = temp;
  const tempCoords = pickupCoords;
  pickupCoords = dropoffCoords;
  dropoffCoords = tempCoords;
  updateBookingMapMarkers();
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
  updateOptionsLabel();
}

function toggleFlag(el) {
  el.classList.toggle('active');
  const toggle = el.querySelector('.toggle-wrap');
  toggle.classList.toggle('on');
  const title = el.querySelector('.sf-title').textContent;
  const on = toggle.classList.contains('on');
  showToast(on ? `"${title}" enabled` : `"${title}" disabled`);
  updateOptionsLabel();
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
  let anyVisible = false;
  cards.forEach(card => {
    if (status === 'all') {
      card.style.display = '';
      anyVisible = true;
    } else {
      if (card.dataset.status === status) {
        card.style.display = '';
        anyVisible = true;
      } else {
        card.style.display = 'none';
      }
    }
  });

  const emptyState = document.querySelector('#trips-container .empty-state');
  if (!anyVisible) {
    if (emptyState) emptyState.style.display = '';
  } else {
    if (emptyState) emptyState.style.display = 'none';
  }
}

// ═══════════ MODALS ═══════════
function openModal(id) {
  document.getElementById('modal-' + id).classList.add('show');
}

function closeModal(e, id) {
  if (!e || e.target.classList.contains('modal-overlay') || e.target.classList.contains('modal')) {
    const modal = document.getElementById('modal-' + id);
    if (modal) modal.classList.remove('show');
  }
}

function closeAllModals() {
  document.querySelectorAll('.modal-overlay, .modal').forEach(m => m.classList.remove('show'));
}

function copyAccountNumber() {
  const el = document.getElementById('company_account_number');
  const text = el ? el.textContent.trim() : '';
  if (!text || text === 'Not Available') { showToast('No account number to copy.'); return; }
  navigator.clipboard.writeText(text).then(() => showToast('Account number copied!')).catch(() => {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    showToast('Account number copied!');
  });
}


async function submitCustomerRatingAndClose() {
  if (currentActiveTripId) {
    const activeChips = Array.from(document.querySelectorAll('#feedbackChips .feedback-chip.active')).map(chip => chip.textContent.trim());
    const body = new FormData();
    body.append('trip_id', currentActiveTripId);
    body.append('rating', currentRating || 5);
    body.append('comment', activeChips.join(', '));
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
    showToast(`Thanks for your feedback, ${window.idibiaCustomerName || 'you'}!`);
  }, 100);
}

function viewReceipt() {
  if (currentReceiptUrl) window.open(currentReceiptUrl, '_blank');
}

function showPostTripModal(trip) {
  // Populate receipt breakdown
  const payment = trip?.payment || {};
  const record = payment.record || null;
  const fare = Number(trip.fare || record?.amount || 0);
  const baseFare = Number(trip.base_fare || fare * 0.64);
  const distanceFare = Number(trip.distance_fare || fare * 0.25);
  const fees = fare - baseFare - distanceFare;
  const distanceKm = trip.distance_km || trip.eta?.distance_km || null;

  const rows = document.querySelectorAll('#modal-post-trip .receipt-row:not(.total)');
  if (rows[0]) { rows[0].querySelector('span:first-child').textContent = 'Base Fare'; rows[0].querySelector('span:last-child').textContent = `₦${baseFare.toLocaleString()}`; }
  if (rows[1]) { rows[1].querySelector('span:first-child').textContent = distanceKm ? `Distance (${distanceKm} km)` : 'Distance'; rows[1].querySelector('span:last-child').textContent = `₦${distanceFare.toLocaleString()}`; }
  if (rows[2]) { rows[2].querySelector('span:first-child').textContent = 'Taxes & Fees'; rows[2].querySelector('span:last-child').textContent = `₦${Math.max(0, fees).toLocaleString()}`; }
  const totalRow = document.querySelector('#modal-post-trip .receipt-row.total');
  if (totalRow) totalRow.querySelector('span:last-child').textContent = `₦${fare.toLocaleString()}`;

  // Payment method label
  const payLabel = document.querySelector('#modal-post-trip .receipt-payment');
  if (payLabel) {
    const method = payment.settings?.active_provider || record?.payment_method || 'payment';
    const methodLabel = method === 'manual_transfer' ? 'bank transfer — payment verified' : method === 'card' ? 'card' : method;
    const textNode = payLabel.firstChild;
    if (textNode && textNode.nodeType === Node.TEXT_NODE) textNode.textContent = `Paid by ${methodLabel}`;
  }

  // Delivery address in header
  const headerP = document.querySelector('#modal-post-trip .modal-header p');
  const dropoffText = trip.dropoff || trip.dropoff_address;
  if (headerP && dropoffText) headerP.textContent = `Package safely delivered to ${dropoffText}`;

  // Driver name in rating prompt
  const ratingPrompt = document.querySelector('#modal-post-trip [style*="font-weight:600"]');
  const driverFirstName = trip.driver?.first_name || trip.driver?.full_name?.split(' ')[0] || 'your rider';
  if (ratingPrompt) ratingPrompt.textContent = `How was your rider, ${escapeHtml(driverFirstName)}? ⭐`;

  // Reset star rating to 5
  currentRating = 5;
  rateStar(5);

  // Reset feedback chips to default
  document.querySelectorAll('#feedbackChips .feedback-chip').forEach((chip, idx) => {
    chip.classList.toggle('active', idx < 2);
  });

  openModal('post-trip');
}

function confirmLogout() { openModal('logout'); }

async function doLogout() {
  try {
    const body = new FormData();
    await fetch("logout-handler.php", { method: "POST", body });
    closeAllModals();
    showToast('Signed out successfully');
    window.location.href = window.idibiaLogoutUrl || "/";
  } catch (err) {
    showToast('Logout failed. Please try again.');
  }
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
let _lastDispatchStatus = null;

function playMilestoneBeep(type) {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const patterns = {
      trip_created:      [[440, 0, 0.12], [550, 0.13, 0.12]],
      driver_assigned:   [[520, 0, 0.1], [620, 0.12, 0.1], [720, 0.24, 0.15]],
      driver_at_pickup:  [[660, 0, 0.1], [660, 0.15, 0.1]],
      package_picked_up: [[500, 0, 0.08], [600, 0.1, 0.08], [700, 0.2, 0.08], [800, 0.3, 0.12]],
      trip_completed:    [[523, 0, 0.1], [659, 0.12, 0.1], [784, 0.24, 0.18]],
      default:           [[440, 0, 0.1]],
    };
    const tones = patterns[type] || patterns.default;
    tones.forEach(([freq, delay, dur]) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.frequency.value = freq;
      osc.type = 'sine';
      gain.gain.setValueAtTime(0.18, ctx.currentTime + delay);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + dur);
      osc.start(ctx.currentTime + delay);
      osc.stop(ctx.currentTime + delay + dur + 0.05);
    });
  } catch (_) {}
}

function startLiveTracking(tripId) {
  if (!tripId) return;
  currentActiveTripId = tripId;
  _lastDispatchStatus = null;
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
  document.getElementById('manualPaymentInstructions').textContent = manual.instructions || 'Transfer the exact fare to the account below, then upload your payment receipt.';

  const statusEl = document.getElementById('manualPaymentStatus');
  const uploadBtn = panel.querySelector('button');
  const hasProof = !!record?.proof_path;
  if (status === 'failed' || status === 'rejected') {
    statusEl.textContent = record?.admin_notes ? `Unable to verify: ${record.admin_notes}` : 'We could not verify your receipt. Please upload a clearer copy.';
    uploadBtn.disabled = false;
  } else if (hasProof) {
    statusEl.textContent = 'Receipt received. Your payment is being processed.';
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

  // Beep on milestone transitions
  const newStatus = trip.dispatch_status;
  if (newStatus && newStatus !== _lastDispatchStatus) {
    if (_lastDispatchStatus !== null) {
      const beepMap = {
        accepted:       'driver_assigned',
        arriving:       'driver_assigned',
        arrived_pickup: 'driver_at_pickup',
        picked_up:      'package_picked_up',
      };
      if (trip.status === 'completed') playMilestoneBeep('trip_completed');
      else if (beepMap[newStatus]) playMilestoneBeep(beepMap[newStatus]);
    }
    _lastDispatchStatus = newStatus;
  }

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
  if (drvRating) drvRating.textContent = drv ? `${drv.rating != null ? drv.rating : '—'} · ${Number(drv.total_trips || 0).toLocaleString()} trips · ${drv.masked_phone || 'masked'}` : 'Waiting for assignment';

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

  const drvAvatar = document.getElementById('trackingDriverAvatar') || document.querySelector('.rider-avatar');
  if (drvAvatar) drvAvatar.innerHTML = drv ? `${escapeHtml(initials)}<div class="rider-online"></div>` : '<span style="font-size:18px;opacity:.5;">?</span><div class="rider-online"></div>';

  const eta = trip.eta || {};
  const etaChip = document.getElementById('trackingEtaLabel');
  if (etaChip) etaChip.textContent = eta.label || statusLabel(trip.dispatch_status);

  const distanceLabel = document.getElementById('trackingDistanceLabel');
  if (distanceLabel) distanceLabel.textContent = eta.distance_km != null ? `${eta.distance_km} km remaining` : `Trip ${trip.trip_ref || ''}`;

  if (trip.driver && trip.driver.location) {
      updateMapLocation(trip.driver.location.lat, trip.driver.location.lng);
  }

  // Draw the relevant road segment based on where the driver is in the trip.
  const driverLoc = trip.driver?.location;
  const enRoute = ['accepted', 'arriving', 'arrived_pickup'].includes(trip.dispatch_status);
  const inTransit = ['picked_up', 'arrived_dropoff'].includes(trip.dispatch_status);
  if (driverLoc && enRoute && trip.pickup_location?.lat != null) {
    drawRouteOnMap([[driverLoc.lat, driverLoc.lng], [trip.pickup_location.lat, trip.pickup_location.lng]]);
  } else if (driverLoc && inTransit && trip.dropoff_location?.lat != null) {
    drawRouteOnMap([[driverLoc.lat, driverLoc.lng], [trip.dropoff_location.lat, trip.dropoff_location.lng]]);
  } else if (trip.pickup_location?.lat != null && trip.dropoff_location?.lat != null) {
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
    if (trip.status === 'completed') {
      showPostTripModal(trip);
    } else {
      showToast('Trip is ' + trip.status);
    }
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
          const shareUrl = json.data.share_url
              ? `${window.location.origin}${json.data.share_url}`
              : `${window.location.origin}/public-tracking.php?token=${encodeURIComponent(json.data.token)}`;
          if (navigator.share) {
              navigator.share({ title: 'Track my delivery', url: shareUrl }).catch(() => {});
          } else if (navigator.clipboard) {
              navigator.clipboard.writeText(shareUrl).catch(() => {});
              showToast('Tracking link copied! Share it with anyone.');
          } else {
              prompt('Copy this tracking link:', shareUrl);
          }
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

// ═══════════ LOCATION PERMISSION HINT ═══════════
function showLocPermissionHint(hintId, field) {
  const el = document.getElementById(hintId);
  if (!el) return;
  const altBtn = el.querySelector('.lph-alt-btn');
  if (altBtn && field) {
    altBtn.onclick = () => { dismissLocHint(hintId); openPinModal(field); };
    altBtn.style.display = '';
  } else if (altBtn) {
    altBtn.style.display = 'none';
  }
  el.classList.add('visible');
}

function dismissLocHint(hintId) {
  const el = document.getElementById(hintId || 'loc-perm-hint');
  if (el) el.classList.remove('visible');
}

// ═══════════ KEYBOARD / ACCESSIBILITY ═══════════
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeAllModals();
});

// \u2500\u2500 REGISTRATION \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
async function doRegister() {
  const btn       = document.getElementById('regBtn');
  const initialHtml = btn.innerHTML;
  btn.classList.add('btn-loading');
  const errorBox  = document.getElementById('regError');
  const errorText = document.getElementById('regErrorText');

  const full_name = document.getElementById('regName').value.trim();
  const phone     = document.getElementById('regPhone').value.trim();
  const email     = document.getElementById('regEmail').value.trim();
  const password  = document.getElementById('regPassword').value;
  const terms     = document.getElementById('termsCheck').checked;

  errorBox.classList.remove('show');

  if ( ! full_name || ! phone || ! email || ! password ) {
    errorText.textContent = 'Please fill in all fields.';
    errorBox.classList.add('show');
    btn.classList.remove('btn-loading');
    btn.innerHTML = initialHtml;
    return;
  }

  btn.disabled    = true;
  btn.textContent = 'Creating account\u2026';

  try {
    const body = new FormData();
    body.append( 'full_name', full_name );
    body.append( 'phone',     phone );
    body.append( 'email',     email );
    body.append( 'password',  password );
    body.append( 'terms',     terms ? '1' : '' );

    const json = await idibiaPost( 'register-handler.php', body );

    if ( json.success ) {
      if (json.data?.message && json.data.message.includes('verify')) {
        document.getElementById('screen-auth').classList.remove('active');
        document.getElementById('screen-otp').classList.add('active');
        const otpHelp = document.getElementById('otpEmailDisplay');
        if (otpHelp) otpHelp.textContent = `We sent a 5-digit code to ${email}.`;
        showToast(json.data.message);
      } else {
        enterCustomerApp( json.data?.first_name ? `Welcome, ${json.data.first_name}! 🎉` : 'Account created successfully.' );
      }
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
    btn.classList.remove('btn-loading');
    btn.innerHTML = initialHtml;
    return;
  }

  btn.disabled    = true;
  btn.textContent = 'Verifying\u2026';

  try {
    const body = new FormData();
    body.append( 'code', code );

    const json = await idibiaPost( 'verify-handler.php', body );

    if ( json.success ) {
      inputs.forEach( i => i.value = '' );
      window.location.href = 'dashboard.php';
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
    if (pickupCoords)  { body.append('pickup_lat',  pickupCoords.lat);  body.append('pickup_lng',  pickupCoords.lng);  }
    if (dropoffCoords) { body.append('dropoff_lat', dropoffCoords.lat); body.append('dropoff_lng', dropoffCoords.lng); }
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
      playMilestoneBeep('trip_created');
      startLiveTracking(json.data.trip_id || 1);

      document.getElementById('pickupInput').value = '';
      document.getElementById('dropoffInput').value = '';
      currentQuoteId = null;
      selectedScheduleTime = null;
      pickupCoords = null;
      dropoffCoords = null;
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

function viewDeliveryPhoto(encodedUrl) {
  const url = decodeURIComponent(encodedUrl);
  if (!url) return;
  // Show in a simple lightbox or new tab
  const win = window.open(url, '_blank', 'noopener,width=800,height=600');
  if (!win) { showToast('Open pop-ups to view the delivery photo.'); }
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
    if (json.success && Array.isArray(json.data.addresses)) {
      _refreshSavedAddresses(json.data.addresses);
    }
  } catch (err) {
    console.error("Failed to load saved addresses", err);
  }
}

// Single source of truth: update the cache, the Account count badge, and the
// manager list (if it's open). Booking inputs read straight from the cache.
function _refreshSavedAddresses(addresses) {
  _savedAddresses = Array.isArray(addresses) ? addresses : [];
  const countEl = document.getElementById('savedAddressesCount');
  if (countEl) countEl.textContent = `${_savedAddresses.length} ${_savedAddresses.length === 1 ? 'place' : 'places'}`;
  if (document.getElementById('savedAddressesList')) renderSavedAddressesList();
}

// ── Saved Addresses manager (Account → Saved Addresses) ──
function openSavedAddressesModal() {
  if (!_savedManagerWired) {
    initPhotonAutocomplete('savedAddrInput', 'savedAddrSuggestions', c => { _savedFormCoords = c; });
    _savedManagerWired = true;
  }
  resetSavedAddressForm();
  renderSavedAddressesList();
  openModal('saved-addresses');
  fetchSavedAddresses(); // refresh in the background in case it changed elsewhere
}

function renderSavedAddressesList() {
  const list = document.getElementById('savedAddressesList');
  if (!list) return;
  if (!_savedAddresses.length) {
    list.innerHTML = `<div style="text-align:center;padding:24px 12px;color:var(--text-muted);font-size:13px;">No saved places yet. Add one below to reuse it on every booking.</div>`;
    return;
  }
  list.innerHTML = _savedAddresses.map((a, i) => `
    <div class="saved-addr-item">
      <div class="saved-addr-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
      </div>
      <div class="saved-addr-text">
        <div class="saved-addr-label">${escapeHtml(a.label)}</div>
        <div class="saved-addr-addr">${escapeHtml(a.address || '')}</div>
      </div>
      <button type="button" class="saved-addr-action" title="Edit" data-saved-action="edit" data-saved-idx="${i}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      </button>
      <button type="button" class="saved-addr-action danger" title="Delete" data-saved-action="delete" data-saved-idx="${i}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
      </button>
    </div>`).join('');

  // Wire actions via listeners (not inline onclick) so labels with quotes are safe.
  list.querySelectorAll('[data-saved-action]').forEach(btn => {
    btn.addEventListener('click', () => {
      const i = parseInt(btn.dataset.savedIdx, 10);
      const addr = _savedAddresses[i];
      if (!addr) return;
      if (btn.dataset.savedAction === 'edit') startEditSavedAddress(i);
      else deleteSavedAddress(addr.label);
    });
  });
}

function resetSavedAddressForm() {
  _editingOriginalLabel = null;
  _savedFormCoords = null;
  const label = document.getElementById('savedAddrLabel');
  const addr = document.getElementById('savedAddrInput');
  const btn = document.getElementById('savedAddrSubmitBtn');
  const cancel = document.getElementById('savedAddrCancelEdit');
  const title = document.getElementById('savedAddrFormTitle');
  if (label) label.value = '';
  if (addr) addr.value = '';
  if (btn) btn.textContent = 'Add Address';
  if (cancel) cancel.style.display = 'none';
  if (title) title.textContent = 'Add a new place';
}

function startEditSavedAddress(idx) {
  const a = _savedAddresses[idx];
  if (!a) return;
  _editingOriginalLabel = a.label;
  const lat = parseFloat(a.lat), lng = parseFloat(a.lng);
  _savedFormCoords = (lat && lng) ? { lat, lng } : null;
  const label = document.getElementById('savedAddrLabel');
  const addr = document.getElementById('savedAddrInput');
  const btn = document.getElementById('savedAddrSubmitBtn');
  const cancel = document.getElementById('savedAddrCancelEdit');
  const title = document.getElementById('savedAddrFormTitle');
  if (label) label.value = a.label;
  if (addr) addr.value = a.address || '';
  if (btn) btn.textContent = 'Update Address';
  if (cancel) cancel.style.display = 'inline-flex';
  if (title) title.textContent = `Editing “${a.label}”`;
  if (label) label.focus();
}

async function saveSavedAddressFromForm() {
  const labelEl = document.getElementById('savedAddrLabel');
  const addrEl = document.getElementById('savedAddrInput');
  const label = labelEl ? labelEl.value.trim() : '';
  const address = addrEl ? addrEl.value.trim() : '';
  if (!label) { showToast('Please enter a label (e.g. Home, Work).'); if (labelEl) labelEl.focus(); return; }
  if (!address) { showToast('Please enter an address.'); if (addrEl) addrEl.focus(); return; }

  // Block duplicate labels (case-insensitive) unless we're editing that same entry.
  const clash = _savedAddresses.some(a =>
    a.label.toLowerCase() === label.toLowerCase() &&
    (!_editingOriginalLabel || a.label.toLowerCase() !== _editingOriginalLabel.toLowerCase()));
  if (clash) { showToast(`You already have a place labelled “${label}”.`); return; }

  const btn = document.getElementById('savedAddrSubmitBtn');
  if (btn) { btn.disabled = true; btn.dataset.txt = btn.textContent; btn.textContent = 'Saving…'; }

  try {
    // Renaming a label means removing the old row first (the API keys on label).
    if (_editingOriginalLabel && _editingOriginalLabel.toLowerCase() !== label.toLowerCase()) {
      const del = new FormData();
      del.append('label', _editingOriginalLabel);
      await idibiaPost('delete-address-api.php', del);
    }
    const body = new FormData();
    body.append('label', label);
    body.append('address', address);
    if (_savedFormCoords) { body.append('lat', _savedFormCoords.lat); body.append('lng', _savedFormCoords.lng); }
    const json = await idibiaPost('save-address-api.php', body);
    if (json.success) {
      showToast(_editingOriginalLabel ? 'Address updated.' : 'Address saved.');
      _refreshSavedAddresses(json.data.addresses);
      resetSavedAddressForm();
    } else {
      showToast(json.data?.message || 'Could not save address.');
    }
  } catch (err) {
    console.error('Save address error:', err);
    showToast('Could not reach server — check your connection and try again.');
  } finally {
    if (btn) { btn.disabled = false; if (btn.dataset.txt) btn.textContent = btn.dataset.txt; }
  }
}

function _saveModalKeyboardFix() {
  const modal = document.getElementById('save-address-modal');
  const sheet = modal ? modal.querySelector('div') : null;
  if (!sheet || !window.visualViewport) return;
  const vv = window.visualViewport;
  const keyboardH = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
  sheet.style.paddingBottom = `calc(${keyboardH + 20}px + env(safe-area-inset-bottom))`;
}

function saveAddress(inputId) {
  const input = document.getElementById(inputId);
  if (!input || !input.value.trim()) {
    showToast('Please type a location first, then tap the bookmark to save it.');
    return;
  }
  _saveAddressInputId = inputId;
  const modal = document.getElementById('save-address-modal');
  if (!modal) { showToast('Save feature unavailable — please refresh the page.'); return; }
  document.getElementById('save-address-preview').textContent = input.value.trim();
  document.getElementById('save-address-label-input').value = '';
  modal.style.display = 'flex';
  if (window.visualViewport) window.visualViewport.addEventListener('resize', _saveModalKeyboardFix);
  setTimeout(() => {
    const lbl = document.getElementById('save-address-label-input');
    if (lbl) lbl.focus();
  }, 80);
}

function closeSaveAddressModal() {
  const modal = document.getElementById('save-address-modal');
  if (modal) {
    modal.style.display = 'none';
    const sheet = modal.querySelector('div');
    if (sheet) sheet.style.paddingBottom = '';
  }
  if (window.visualViewport) window.visualViewport.removeEventListener('resize', _saveModalKeyboardFix);
  _saveAddressInputId = null;
}

async function confirmSaveAddress() {
  const label = document.getElementById('save-address-label-input').value.trim();
  if (!label) {
    showToast('Please enter a label (e.g. Home, Work).');
    document.getElementById('save-address-label-input').focus();
    return;
  }
  const inputEl = _saveAddressInputId ? document.getElementById(_saveAddressInputId) : null;
  const address = inputEl ? inputEl.value.trim() : '';
  if (!address) {
    showToast('No address to save — please type a location first.');
    closeSaveAddressModal();
    return;
  }

  const field = _saveAddressInputId === 'pickupInput' ? 'pickup' : 'dropoff';
  const coords = field === 'pickup' ? pickupCoords : dropoffCoords;

  const saveBtn = document.querySelector('#save-address-modal button:last-child');
  const cancelBtn = document.querySelector('#save-address-modal button:first-child');
  if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving…'; }
  if (cancelBtn) cancelBtn.disabled = true;

  try {
    const body = new FormData();
    body.append('label', label);
    body.append('address', address);
    if (coords) {
      body.append('lat', coords.lat);
      body.append('lng', coords.lng);
    }
    const json = await idibiaPost('save-address-api.php', body);
    if (json.success) {
      closeSaveAddressModal();
      showToast(json.data.message || 'Address saved.');
      _refreshSavedAddresses(json.data.addresses);
    } else {
      showToast(json.data?.message || 'Could not save address.');
      if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Address'; }
      if (cancelBtn) cancelBtn.disabled = false;
    }
  } catch (err) {
    console.error('Save address error:', err);
    showToast('Could not reach server — check your connection and try again.');
    if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Address'; }
    if (cancelBtn) cancelBtn.disabled = false;
  }
}

async function deleteSavedAddress(label) {
  if (!confirm(`Delete saved address “${label}”?`)) return;
  try {
    const body = new FormData();
    body.append('label', label);
    const json = await idibiaPost('delete-address-api.php', body);
    if (json.success) {
      showToast(json.data.message || 'Address deleted.');
      _refreshSavedAddresses(json.data.addresses);
      if (_editingOriginalLabel && _editingOriginalLabel.toLowerCase() === label.toLowerCase()) resetSavedAddressForm();
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

// ═══════════ ACTIVITY TAB TRIPS ═══════════
let _activityLoaded = false;

function _tripFilterStatus(trip) {
  const s = (trip.status || '').toLowerCase();
  const d = (trip.dispatch_status || '').toLowerCase();
  if (s === 'completed' || d === 'completed') return 'delivered';
  if (s === 'cancelled' || d === 'cancelled') return 'cancelled';
  if (['accepted','arriving','arrived_pickup','picked_up','arrived_dropoff'].includes(d)) return 'in-transit';
  return 'scheduled';
}

function _buildTripCard(trip) {
  const filterStatus = _tripFilterStatus(trip);
  const statusLabels = { 'delivered': 'Delivered', 'in-transit': 'In Transit', 'cancelled': 'Cancelled', 'scheduled': 'Scheduled' };
  const label = statusLabels[filterStatus] || filterStatus;
  const isTerm = filterStatus === 'delivered' || filterStatus === 'cancelled';
  const clickFn = isTerm ? `showTripDetails(${trip.id})` : `startLiveTracking(${trip.id})`;
  const fare = trip.fare ? `₦${parseFloat(trip.fare).toLocaleString()}` : (trip.fare_estimate ? `~₦${parseFloat(trip.fare_estimate).toLocaleString()}` : '—');
  const date = new Date(trip.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  const pickup = trip.pickup_address || trip.pickup || 'Pickup location';
  const dropoff = trip.dropoff_address || trip.dropoff || 'Drop-off location';
  const podBtn = (filterStatus === 'delivered' && trip.proof_of_delivery_url)
    ? `<button class="trip-action-btn" onclick="event.stopPropagation();viewDeliveryPhoto('${encodeURIComponent(trip.proof_of_delivery_url)}')" style="margin-left:4px">📷 POD</button>`
    : '';
  const actionBtn = isTerm
    ? `<button class="trip-action-btn primary" onclick="event.stopPropagation();showTripDetails(${trip.id})">Details</button>${podBtn}`
    : `<button class="trip-action-btn primary" onclick="event.stopPropagation();startLiveTracking(${trip.id})">Track</button>`;

  return `
    <div class="trip-card" data-status="${filterStatus}" data-trip-id="${trip.id}" onclick="${clickFn}">
      <div class="trip-top">
        <div>
          <div class="trip-id">${trip.trip_ref || '#' + trip.id}</div>
          <div class="trip-date">${date}</div>
        </div>
        <div class="trip-status ${filterStatus}">${label}</div>
      </div>
      <div class="trip-route">
        <div class="trip-point"><div class="trip-point-dot from"></div>${pickup}</div>
        <div class="trip-line"></div>
        <div class="trip-point"><div class="trip-point-dot to"></div>${dropoff}</div>
      </div>
      <div class="trip-meta">
        <div class="trip-price">${fare}</div>
        <div class="trip-actions">${actionBtn}</div>
      </div>
    </div>`;
}

async function fetchActivityTrips(force = false) {
  if (_activityLoaded && !force) return;
  const container = document.getElementById('trips-container');
  if (!container) return;

  container.innerHTML = '<div style="text-align:center;padding:40px 20px;color:var(--text-muted)">Loading…</div>';

  try {
    const res = await fetch(`${IDIBIA_API_BASE}/customer-trips-api.php`, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();

    if (json.success && json.data.trips && json.data.trips.length > 0) {
      container.innerHTML = json.data.trips.map(_buildTripCard).join('');
      _activityLoaded = true;
      // Respect whichever filter pill is currently active
      const activePill = document.querySelector('#tab-activity .filter-pill.active');
      if (activePill) {
        const status = activePill.getAttribute('onclick').match(/'([^']+)'\)$/)?.[1] || 'all';
        filterTrips(activePill, status);
      }
    } else {
      container.innerHTML = '<div class="empty-state" style="text-align:center;padding:40px 20px;color:var(--text-muted)">No trips yet. Book a delivery to get started!</div>';
      _activityLoaded = true;
    }
  } catch (err) {
    console.error('Failed to load activity trips', err);
    container.innerHTML = '<div class="empty-state" style="text-align:center;padding:40px 20px;color:var(--text-muted)">Could not load trips. Pull down to retry.</div>';
  }
}

// Call it when showing main screen
let _activityRefreshTimer = null;
const origEnter = window.enterCustomerApp;
window.enterCustomerApp = function(msg) {
    if(origEnter) origEnter(msg);
    fetchRecentActivity();
    fetchSavedAddresses();
    setTimeout(() => initLeafletMap('home-map-container', 6.5244, 3.3792), 500);
    if (!_activityRefreshTimer) {
        _activityRefreshTimer = setInterval(() => {
            fetchRecentActivity();
            if (document.getElementById('tab-activity')?.classList.contains('active')) {
                _activityLoaded = false;
                fetchActivityTrips();
            }
        }, 30000);
    }
};

// ═══════════ LEAFLET MAP INTEGRATION ═══════════
let currentMap = null;
let currentMarker = null;
let currentRouteLayer = null;
let currentReceiptUrl = null;

function initLeafletMap(containerId, lat, lng, isTracking = false, _retries = 6) {
  if (currentMap) {
    currentMap.remove();
    currentMap = null;
    _homeMapActive = false;
    bookingPickupMarker = null;
    bookingDropoffMarker = null;
  }

  const el = document.getElementById(containerId);
  if (!el) return;
  if (!window.L) {
    if (_retries > 0) setTimeout(() => initLeafletMap(containerId, lat, lng, isTracking, _retries - 1), 800);
    return;
  }
  if (!el.offsetWidth || !el.offsetHeight) {
    if (_retries > 0) setTimeout(() => initLeafletMap(containerId, lat, lng, isTracking, _retries - 1), 200);
    return;
  }

  currentMap = L.map(containerId, { zoomControl: false }).setView([lat, lng], 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(currentMap);
  keepMapSized(currentMap);
  setTimeout(() => currentMap && currentMap.invalidateSize(), 150);
  setTimeout(() => currentMap && currentMap.invalidateSize(), 700);

  const iconHtml = `<div class="rider-avatar" style="width: 32px; height: 32px; font-size: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.2);"><div class="rider-online"></div></div>`;
  const icon = L.divIcon({ html: iconHtml, className: 'leaflet-custom-icon', iconSize: [32, 32], iconAnchor: [16, 16] });

  if (isTracking) {
    currentMarker = L.marker([lat, lng], { icon }).addTo(currentMap);
  }

  if (containerId === 'home-map-container') {
    _homeMapActive = true;
    setTimeout(() => updateBookingMapMarkers(), 850);
  }
}

function updateBookingMapMarkers() {
  if (!currentMap || !_homeMapActive) return;
  if (bookingPickupMarker) { currentMap.removeLayer(bookingPickupMarker); bookingPickupMarker = null; }
  if (bookingDropoffMarker) { currentMap.removeLayer(bookingDropoffMarker); bookingDropoffMarker = null; }
  if (pickupCoords) {
    bookingPickupMarker = L.circleMarker([pickupCoords.lat, pickupCoords.lng], {
      radius: 9, fillColor: '#4A9EFF', color: '#ffffff', weight: 3, fillOpacity: 1, interactive: false
    }).addTo(currentMap);
  }
  if (dropoffCoords) {
    bookingDropoffMarker = L.circleMarker([dropoffCoords.lat, dropoffCoords.lng], {
      radius: 9, fillColor: '#C8952A', color: '#ffffff', weight: 3, fillOpacity: 1, interactive: false
    }).addTo(currentMap);
  }
  if (pickupCoords && dropoffCoords) {
    currentMap.fitBounds(
      [[pickupCoords.lat, pickupCoords.lng], [dropoffCoords.lat, dropoffCoords.lng]],
      { padding: [60, 60], maxZoom: 15 }
    );
  } else if (pickupCoords) {
    currentMap.setView([pickupCoords.lat, pickupCoords.lng], 15);
  } else if (dropoffCoords) {
    currentMap.setView([dropoffCoords.lat, dropoffCoords.lng], 15);
  }
}

function updateMapLocation(lat, lng) {
  if (currentMap && currentMarker) {
    const newLatLng = new L.LatLng(lat, lng);
    currentMarker.setLatLng(newLatLng);
    currentMap.panTo(newLatLng);
  }
}

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

// ═══════════ PROFILE EDIT ═══════════
async function submitProfileEdit(e) {
  e.preventDefault();
  const btn = document.getElementById('profileSaveBtn');
  const initialText = btn.innerHTML;
  btn.disabled = true;
  btn.classList.add('btn-loading');

  try {
    const body = new FormData();
    const newFullName = document.getElementById('profileFullName').value.trim();
    const newPhone = document.getElementById('profilePhone').value.trim();
    body.append('full_name', newFullName);
    body.append('phone', newPhone);

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
    btn.classList.remove('btn-loading');
    btn.innerHTML = initialText;
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
  body.append('category', category);
  body.append('message', combinedMessage);

  const btn = document.getElementById('btnSubmitSupport');
  const oldText = btn.textContent;
  btn.textContent = 'Submitting...';
  btn.disabled = true;

  try {
    const json = await idibiaPost('/support-api.php', body);
    if (json.success) {
      showToast('Ticket submitted. Track it in My Tickets.');
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

// ═══════════ SUPPORT TICKET TRACKING ═══════════
let _currentTicketId = null;

async function openMyTickets() {
  openModal('tickets');
  await fetchMyTickets();
}

async function fetchMyTickets() {
  const container = document.getElementById('tickets-list-container');
  if (!container) return;
  container.innerHTML = '<div style="text-align:center;padding:40px 20px;color:var(--text-muted)">Loading…</div>';
  try {
    const res = await fetch('/customer-tickets-api.php', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    });
    const json = await res.json();
    if (json.success && json.data.tickets.length > 0) {
      container.innerHTML = json.data.tickets.map(_buildTicketCard).join('');
    } else if (json.success) {
      container.innerHTML = '<div style="text-align:center;padding:40px 20px;color:var(--text-muted)">No tickets yet.<br>Open a new ticket if you need help.</div>';
    } else {
      container.innerHTML = '<div style="text-align:center;padding:40px 20px;color:var(--text-muted)">Could not load tickets.</div>';
    }
  } catch (_) {
    container.innerHTML = '<div style="text-align:center;padding:40px 20px;color:var(--text-muted)">Connection error. Please try again.</div>';
  }
}

function _buildTicketCard(ticket) {
  const statusLabels = { open: 'Open', in_progress: 'In Progress', escalated: 'Escalated', resolved: 'Resolved', closed: 'Closed' };
  const catLabels = { general: 'General', trip_issue: 'Trip Issue', billing: 'Billing', account: 'Account', emergency_safety: 'Safety' };
  const statusLabel = statusLabels[ticket.status] || ticket.status;
  const catLabel = catLabels[ticket.category] || ticket.category;
  const date = new Date(ticket.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  const rawMsg = ticket.last_message || '';
  const subject = rawMsg.match(/^Subject:\s*([^\n]+)/)?.[1] || rawMsg.slice(0, 60) || 'Support Ticket';
  const preview = rawMsg.replace(/^Subject:[^\n]*\n\n?/, '').slice(0, 80);

  return `
    <div class="ticket-card" onclick="openTicketDetail(${ticket.id})">
      <div class="ticket-card-top">
        <span class="ticket-card-ref">Ticket #${ticket.id}</span>
        <span class="ticket-status ${ticket.status}">${statusLabel}</span>
      </div>
      <div class="ticket-card-subject">${_escHtml(subject)}</div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-top:6px;">
        <span class="ticket-card-category">${catLabel}</span>
        <span class="ticket-card-date">${date}</span>
      </div>
      ${preview ? `<div class="ticket-card-preview">${_escHtml(preview)}</div>` : ''}
    </div>`;
}

function _escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function openTicketDetail(ticketId) {
  _currentTicketId = ticketId;
  closeModal(null, 'tickets');
  openModal('ticket-detail');
  document.getElementById('ticket-detail-title').textContent = `Ticket #${ticketId}`;
  document.getElementById('ticket-detail-status').innerHTML = '';
  document.getElementById('ticket-reply-box').style.display = 'none';
  const container = document.getElementById('ticket-thread-container');
  container.innerHTML = '<div style="text-align:center;padding:30px 0;color:var(--text-muted)">Loading…</div>';

  try {
    const res = await fetch(`/customer-tickets-api.php?ticket_id=${ticketId}`, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    });
    const json = await res.json();
    if (!json.success) {
      container.innerHTML = '<div style="text-align:center;padding:30px 0;color:var(--text-muted)">Could not load ticket.</div>';
      return;
    }

    const { ticket, messages } = json.data;
    const statusLabels = { open: 'Open', in_progress: 'In Progress', escalated: 'Escalated', resolved: 'Resolved', closed: 'Closed' };
    const catLabels = { general: 'General', trip_issue: 'Trip Issue', billing: 'Billing', account: 'Account', emergency_safety: 'Safety' };
    document.getElementById('ticket-detail-status').innerHTML =
      `<span class="ticket-status ${ticket.status}">${statusLabels[ticket.status] || ticket.status}</span>` +
      `<span style="font-size:11px;color:var(--text-muted)">${catLabels[ticket.category] || ticket.category}</span>`;

    if (messages.length === 0) {
      container.innerHTML = '<div style="text-align:center;padding:30px 0;color:var(--text-muted)">No messages yet.</div>';
    } else {
      container.innerHTML = messages.map(m => {
        const side = m.is_mine ? 'customer' : 'support';
        const senderLabel = m.is_mine ? 'You' : 'Support';
        const time = new Date(m.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
        const text = _escHtml(m.message).replace(/\n/g, '<br>');
        return `
          <div class="ticket-bubble-wrap ${side}">
            <div class="ticket-bubble">${text}</div>
            <div class="ticket-bubble-meta">${senderLabel} · ${time}</div>
          </div>`;
      }).join('');
      container.scrollTop = container.scrollHeight;
    }

    if (!['closed', 'resolved'].includes(ticket.status)) {
      document.getElementById('ticket-reply-box').style.display = 'block';
    }
  } catch (_) {
    container.innerHTML = '<div style="text-align:center;padding:30px 0;color:var(--text-muted)">Connection error.</div>';
  }
}

async function submitTicketReply() {
  if (!_currentTicketId) return;
  const input = document.getElementById('ticket-reply-input');
  const message = input?.value?.trim();
  if (!message) { showToast('Write a message first.'); return; }

  const btn = document.getElementById('btnSendReply');
  const oldText = btn.textContent;
  btn.textContent = 'Sending…';
  btn.disabled = true;

  const body = new FormData();
  body.append('action', 'add_message');
  body.append('ticket_id', _currentTicketId);
  body.append('message', message);

  try {
    const json = await idibiaPost('/support-api.php', body);
    if (json.success) {
      input.value = '';
      await openTicketDetail(_currentTicketId);
    } else {
      showToast(json.data?.message || 'Could not send reply.');
    }
  } catch (_) {
    showToast('Connection error.');
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
                    img.src = json.data.avatar_url || (json.data.avatar_path.startsWith('http') ? json.data.avatar_path : ((window.idibiaUploadBaseUrl ? window.idibiaUploadBaseUrl + '/' : '/') + json.data.avatar_path));
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

// ── WALLET PANEL ────────────────────────────────────────────────────────────

async function openWalletPanel() {
  const panel = document.getElementById('walletPanel');
  if (!panel) return;
  panel.style.display = 'block';
  loadWalletData();
}

function closeWalletPanel() {
  const panel = document.getElementById('walletPanel');
  if (panel) panel.style.display = 'none';
}

async function loadWalletData() {
  const listEl = document.getElementById('walletLedgerList');
  const balEl  = document.getElementById('walletBalanceDisplay');
  if (!listEl) return;
  listEl.textContent = 'Loading…';
  try {
    const resp = await fetch('/wallet-topup-api.php?action=get_wallet');
    const json = await resp.json();
    if (!json.success) { listEl.textContent = 'Could not load wallet.'; return; }
    if (balEl) balEl.textContent = '₦' + Number(json.data.balance).toLocaleString('en-NG', {minimumFractionDigits:2});
    const ledger = json.data.ledger || [];
    if (!ledger.length) { listEl.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:12px 0">No transactions yet.</div>'; return; }
    const typeLabel = {topup:'Top-Up', refund:'Refund', credit:'Credit', debit:'Debit', referral_bonus:'Referral Bonus'};
    listEl.innerHTML = ledger.map(row => {
      const isIn  = ['topup','refund','credit','referral_bonus'].includes(row.entry_type);
      const color = isIn ? 'var(--success)' : 'var(--danger,#e53)';
      const sign  = isIn ? '+' : '-';
      return `<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
        <div>
          <div style="font-size:13px;font-weight:600">${typeLabel[row.entry_type]||row.entry_type}</div>
          <div style="font-size:11px;color:var(--text-muted)">${row.description||''} · ${new Date(row.created_at).toLocaleDateString()}</div>
        </div>
        <div style="font-size:14px;font-weight:700;color:${color}">${sign}₦${Number(row.amount).toLocaleString('en-NG',{minimumFractionDigits:2})}</div>
      </div>`;
    }).join('');
  } catch(e) {
    if (listEl) listEl.textContent = 'Could not load transactions.';
  }
}

// ── TOP-UP MODAL ─────────────────────────────────────────────────────────────

function openTopUpModal() {
  const m = document.getElementById('topUpModal');
  if (m) { m.style.display = 'flex'; }
}

function closeTopUpModal() {
  const m = document.getElementById('topUpModal');
  if (m) m.style.display = 'none';
}

function setTopUpAmount(amount) {
  const inp = document.getElementById('topUpAmount');
  if (inp) inp.value = amount;
  document.querySelectorAll('.preset-amount-btn').forEach(b => {
    b.style.borderColor = Number(b.dataset.amount) === amount ? 'var(--primary)' : 'var(--border)';
    b.style.color       = Number(b.dataset.amount) === amount ? 'var(--primary)' : 'var(--text-primary)';
  });
}

async function submitTopUp() {
  const amount   = Number(document.getElementById('topUpAmount')?.value || 0);
  const provider = document.getElementById('topUpProvider')?.value || 'paystack';
  const btn      = document.getElementById('topUpSubmitBtn');

  if (amount < 100) { showToast('Minimum top-up is ₦100.'); return; }

  if (btn) { btn.disabled = true; btn.textContent = 'Please wait…'; }

  try {
    const fd = new FormData();
    fd.append('action', 'init_topup');
    fd.append('amount', amount);
    fd.append('provider', provider);
    const resp = await fetch('/wallet-topup-api.php', { method:'POST', body: fd });
    const json = await resp.json();
    if (!json.success) { showToast(json.data?.message || 'Could not start payment.'); return; }
    // Redirect to the payment provider's checkout page
    window.location.href = json.data.payment_url;
  } catch(e) {
    showToast('Network error. Please try again.');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = 'Continue to Payment'; }
  }
}
