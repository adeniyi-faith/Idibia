import re

with open('dashboard.php', 'r') as f:
    content = f.read()

polling_script = """// ═══════════ LIVE TRACKING (Phase 5) ═══════════
let currentActiveTripId = null;
let trackingInterval = null;

function startLiveTracking(tripId) {
  if (!tripId) return;
  currentActiveTripId = tripId;
  goTo('screen-tracking');
  pollTracking();
  if (trackingInterval) clearInterval(trackingInterval);
  trackingInterval = setInterval(pollTracking, 5000);
}

function stopLiveTracking() {
  if (trackingInterval) {
    clearInterval(trackingInterval);
    trackingInterval = null;
  }
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

function updateTrackingUI(trip) {
  // Terminal states stop polling
  if (trip.status === 'completed' || trip.status === 'cancelled') {
    stopLiveTracking();
    showToast('Trip is ' + trip.status);
    setTimeout(() => goTo('screen-home'), 2000);
    return;
  }

  // Map updates (simulate or real based on available driver lat/lng)
  const drv = trip.driver;
  if (drv) {
    const drvName = document.querySelector('.rider-name');
    if (drvName) drvName.textContent = drv.first_name || 'Driver';

    const drvPlate = document.querySelector('.rider-plate');
    if (drvPlate) drvPlate.textContent = drv.plate || '---';

    const drvRating = document.querySelector('.rider-rating-text');
    if (drvRating) drvRating.textContent = drv.rating || '5.0';

    const drvAvatar = document.querySelector('.rider-avatar');
    if (drvAvatar) drvAvatar.innerHTML = (drv.first_name ? drv.first_name[0] : 'D') + '<div class="rider-online"></div>';
  } else {
    const drvName = document.querySelector('.rider-name');
    if (drvName) drvName.textContent = 'Searching...';

    const drvPlate = document.querySelector('.rider-plate');
    if (drvPlate) drvPlate.textContent = '';

    const drvRating = document.querySelector('.rider-rating-text');
    if (drvRating) drvRating.textContent = '';

    const drvAvatar = document.querySelector('.rider-avatar');
    if (drvAvatar) drvAvatar.innerHTML = '...';
  }

  // Update dots
  const ds = trip.dispatch_status;
  let etaMsg = 'Finding driver...';
  let stepsHTML = '';

  if (ds === 'searching' || ds === 'no_driver') {
    etaMsg = 'Finding nearby driver...';
    stepsHTML = `<div class="t-step"><div class="t-step-dot done"></div><div class="t-step-info"><h4>Looking for driver</h4><p>We are assigning the best driver.</p></div></div>`;
  } else if (ds === 'accepted' || ds === 'arriving') {
    etaMsg = 'Driver is arriving';
    stepsHTML = `<div class="t-step"><div class="t-step-dot done"></div><div class="t-step-info"><h4>Driver on the way</h4><p>Heading to pickup</p></div></div>`;
  } else if (ds === 'arrived_pickup') {
    etaMsg = 'Driver at pickup';
    stepsHTML = `<div class="t-step"><div class="t-step-dot done"></div><div class="t-step-info"><h4>Driver at pickup</h4><p>Meet driver at pickup location</p></div></div>`;
  } else if (ds === 'picked_up') {
    etaMsg = 'Package in transit';
    stepsHTML = `<div class="t-step"><div class="t-step-dot done"></div><div class="t-step-info"><h4>Picked up</h4><p>Heading to destination</p></div></div>`;
  } else if (ds === 'arrived_dropoff') {
    etaMsg = 'Driver at dropoff';
    stepsHTML = `<div class="t-step"><div class="t-step-dot done"></div><div class="t-step-info"><h4>Driver at dropoff</h4><p>Ready to handover package</p></div></div>`;
  }

  const etaChip = document.querySelector('.eta-chip-text');
  if (etaChip) etaChip.textContent = etaMsg;
  const stepsCont = document.querySelector('.tracking-steps');
  if (stepsCont) stepsCont.innerHTML = stepsHTML;
}

// ═══════════ ETA COUNTDOWN"""

content = content.replace("// ═══════════ ETA COUNTDOWN", polling_script)

with open('dashboard.php', 'w') as f:
    f.write(content)
