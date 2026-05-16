import re

with open('driver.php', 'r') as f:
    content = f.read()

correct = """async function fetchDriverOffers() {
  if (!driverAuthenticated || !driverInitialContext.is_approved || !isOnline) {
    renderDriverOffers([]);
    return;
  }
  try {
    const body = new FormData();
    body.append('_nonce', driverInitialContext.nonces?.driver_action || '');

    // Attempt to get real location if browser supports it, else use fallback
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        async (position) => {
          body.append('lat', position.coords.latitude);
          body.append('lng', position.coords.longitude);
          body.append('heading', position.coords.heading || 0);
          const response = await fetch('/driver-heartbeat-api.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
          const json = await parseDriverJson(response);
          if (json.success) renderDriverOffers(json.data?.offers || [], json.data?.active_trip || null);
        },
        async (err) => {
          // Fallback to static location for demo if permission denied/fails
          body.append('lat', '4.8156');
          body.append('lng', '7.0498');
          body.append('heading', '90');
          const response = await fetch('/driver-heartbeat-api.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
          const json = await parseDriverJson(response);
          if (json.success) renderDriverOffers(json.data?.offers || [], json.data?.active_trip || null);
        },
        { timeout: 5000 }
      );
    } else {
      body.append('lat', '4.8156');
      body.append('lng', '7.0498');
      body.append('heading', '90');
      const response = await fetch('/driver-heartbeat-api.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      const json = await parseDriverJson(response);
      if (json.success) renderDriverOffers(json.data?.offers || [], json.data?.active_trip || null);
    }
  } catch (err) {
    // Keep the last rendered state; polling should not interrupt the driver UI.
  }
}

function renderDriverOffers(offers, activeTrip = null) {"""

content = re.sub(r'async function fetchDriverOffers\(\) \{[\s\S]*?function renderDriverOffers\(offers, activeTrip = null\) \{', correct, content)

with open('driver.php', 'w') as f:
    f.write(content)
