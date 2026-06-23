<!-- SYSTEM HEALTH PANEL -->
<div class="panel" id="panel-system">
  <div class="page-header">
    <div>
      <h2 class="page-title">System Health</h2>
      <div class="page-sub">Live platform status · refreshes every 60 seconds</div>
    </div>
    <button class="btn-primary" style="height:36px;padding:0 16px;font-size:13px" onclick="systemHealthRefresh()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="margin-right:6px"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
      Refresh Now
    </button>
  </div>

  <!-- Gateway Status Cards -->
  <div class="scard" style="margin-bottom:16px">
    <div class="scard-header"><h3>Gateway &amp; Service Status</h3><span id="systemHealthLastUpdated" style="font-size:11px;color:var(--text-muted)">Loading…</span></div>
    <div class="metrics-grid four" style="padding:0 16px 16px">
      <div class="metric-card" id="shCardPaystack">
        <div class="metric-label">PAYSTACK</div>
        <div class="metric-value" id="shValPaystack" style="font-size:20px">--</div>
        <div class="metric-delta neutral" id="shSubPaystack">Payment gateway</div>
      </div>
      <div class="metric-card" id="shCardFlutter">
        <div class="metric-label">FLUTTERWAVE</div>
        <div class="metric-value" id="shValFlutter" style="font-size:20px">--</div>
        <div class="metric-delta neutral" id="shSubFlutter">Payment gateway</div>
      </div>
      <div class="metric-card" id="shCardPusher">
        <div class="metric-label">PUSHER (REAL-TIME)</div>
        <div class="metric-value" id="shValPusher" style="font-size:20px">--</div>
        <div class="metric-delta neutral" id="shSubPusher">Live notifications</div>
      </div>
      <div class="metric-card" id="shCardSmtp">
        <div class="metric-label">EMAIL (SMTP)</div>
        <div class="metric-value" id="shValSmtp" style="font-size:20px">--</div>
        <div class="metric-delta neutral" id="shSubSmtp">Email delivery</div>
      </div>
    </div>
  </div>

  <!-- Stale Driver Sessions + Quick Counters -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;margin-bottom:16px">

    <div class="metric-card" style="cursor:default">
      <div class="metric-label">STALE DRIVER SESSIONS</div>
      <div class="metric-value" id="shStaleCount" style="color:var(--warn)">--</div>
      <div style="margin-top:10px">
        <button class="btn-primary" style="height:30px;padding:0 12px;font-size:12px;background:var(--danger)" onclick="systemHealthForceOffline()" id="shForceOfflineBtn">
          Force All Offline
        </button>
      </div>
      <div class="metric-delta neutral" style="margin-top:6px">Online but GPS silent &gt;5 min</div>
    </div>

    <div class="metric-card" style="cursor:pointer" onclick="nav('kyc', document.querySelector('[onclick*=\\'nav(\\\'kyc\\\'\\')'))" title="Go to KYC queue">
      <div class="metric-label">PENDING KYC</div>
      <div class="metric-value" id="shKycCount" style="color:var(--warn)">--</div>
      <div class="metric-delta neutral">Applications awaiting review</div>
    </div>

    <div class="metric-card" style="cursor:pointer" onclick="nav('disputes', document.querySelector('[onclick*=\\'nav(\\\'disputes\\\'\\')'))" title="Go to disputes">
      <div class="metric-label">OPEN DISPUTES</div>
      <div class="metric-value" id="shDisputeCount" style="color:var(--danger)">--</div>
      <div class="metric-delta neutral">Unresolved cases</div>
    </div>

    <div class="metric-card" style="cursor:pointer" onclick="nav('payouts', document.querySelector('[onclick*=\\'nav(\\\'payouts\\\'\\')'))" title="Go to payouts">
      <div class="metric-label">FAILED PAYOUTS (24H)</div>
      <div class="metric-value" id="shFailedPayouts" style="color:var(--danger)">--</div>
      <div class="metric-delta neutral">Needs manual action</div>
    </div>

  </div>

  <!-- Trip Pipeline Table -->
  <div class="scard" style="margin-bottom:16px">
    <div class="scard-header">
      <h3>Trip Dispatch Pipeline</h3>
      <span style="font-size:11px;color:var(--text-muted)">Active trips only — high counts in one stage may mean a stuck pipeline</span>
    </div>
    <div id="shPipelineWrap" style="padding:0 16px 16px">
      <div style="color:var(--text-muted);font-size:13px;padding:20px 0">Loading…</div>
    </div>
  </div>

  <!-- Trips Stuck Searching -->
  <div class="scard" style="margin-bottom:16px">
    <div class="scard-header"><h3>Infrastructure</h3></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:0 16px 16px">
      <div class="metric-card" style="cursor:default">
        <div class="metric-label">TRIPS STUCK IN SEARCHING</div>
        <div class="metric-value" id="shStuckSearching" style="color:var(--danger)">--</div>
        <div class="metric-delta neutral">Beyond timeout setting — the cron should cancel these automatically</div>
      </div>
      <div class="metric-card" style="cursor:default">
        <div class="metric-label">DATABASE SIZE</div>
        <div class="metric-value" id="shDbSize">-- MB</div>
        <div class="metric-delta neutral">All tables in this database</div>
      </div>
    </div>
  </div>

  <!-- Last Cron Run -->
  <div class="scard">
    <div class="scard-header"><h3>Scheduled Tasks (Cron)</h3></div>
    <div style="padding:0 16px 20px">
      <div style="font-size:13px;color:var(--text-muted);margin-bottom:6px">The cron job runs every few minutes to expire offers, dispatch scheduled trips, cancel timed-out searches, and take stale drivers offline.</div>
      <div style="display:flex;align-items:center;gap:8px">
        <span style="font-size:13px;font-weight:600">Last cron run:</span>
        <span id="shLastCron" style="font-size:13px;color:var(--text-muted)">--</span>
        <span id="shCronAgeWarn" style="display:none;background:var(--danger);color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px">OVERDUE</span>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var _shTimer = null;

  // Called by the nav() function when this panel becomes active
  window.systemHealthInit = function () {
    systemHealthRefresh();
    _shTimer = setInterval(systemHealthRefresh, 60000);
  };

  window.systemHealthStop = function () {
    if (_shTimer) { clearInterval(_shTimer); _shTimer = null; }
  };

  window.systemHealthRefresh = function () {
    fetch(ADMIN_API_URL + '?action=get_system_health')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) return;
        var h = data.data.health;
        renderSystemHealth(h);
      })
      .catch(function () {
        document.getElementById('systemHealthLastUpdated').textContent = 'Error loading health data.';
      });
  };

  function renderSystemHealth(h) {
    document.getElementById('systemHealthLastUpdated').textContent =
      'Last updated: ' + new Date().toLocaleTimeString();

    // Gateway cards
    setGatewayCard('shCardPaystack', 'shValPaystack', 'shSubPaystack', h.paystack_status, 'Payment gateway');
    setGatewayCard('shCardFlutter',  'shValFlutter',  'shSubFlutter',  h.flutterwave_status, 'Payment gateway');
    setGatewayCard('shCardPusher',   'shValPusher',   'shSubPusher',   h.pusher_status, 'Live notifications');
    setGatewayCard('shCardSmtp',     'shValSmtp',     'shSubSmtp',     h.smtp_status, 'Email delivery');

    // Stale sessions
    var stale = parseInt(h.stale_driver_sessions, 10) || 0;
    document.getElementById('shStaleCount').textContent = stale;
    document.getElementById('shStaleCount').style.color = stale > 0 ? 'var(--warn)' : 'var(--success)';

    // Quick counters
    var kyc = parseInt(h.pending_kyc_count, 10) || 0;
    document.getElementById('shKycCount').textContent = kyc;
    document.getElementById('shKycCount').style.color = kyc > 0 ? 'var(--warn)' : 'var(--success)';

    var disp = parseInt(h.open_disputes_count, 10) || 0;
    document.getElementById('shDisputeCount').textContent = disp;
    document.getElementById('shDisputeCount').style.color = disp > 0 ? 'var(--danger)' : 'var(--success)';

    var fp = parseInt(h.failed_payouts_24h, 10) || 0;
    document.getElementById('shFailedPayouts').textContent = fp;
    document.getElementById('shFailedPayouts').style.color = fp > 0 ? 'var(--danger)' : 'var(--success)';

    // Trip pipeline table
    renderPipeline(h.trips_by_dispatch_status || {});

    // Stuck searching
    var stuck = parseInt(h.trips_stuck_searching, 10) || 0;
    document.getElementById('shStuckSearching').textContent = stuck;
    document.getElementById('shStuckSearching').style.color = stuck > 0 ? 'var(--danger)' : 'var(--success)';

    // DB size
    document.getElementById('shDbSize').textContent = (parseFloat(h.db_size_mb) || 0).toFixed(2) + ' MB';

    // Last cron run
    var lastCron = h.last_cron_run;
    if (lastCron) {
      var cronDate = new Date(lastCron.replace(' ', 'T') + 'Z');
      var minsAgo  = Math.round((Date.now() - cronDate.getTime()) / 60000);
      document.getElementById('shLastCron').textContent = cronDate.toLocaleString() + ' (' + minsAgo + ' min ago)';
      // Warn if cron hasn't run in 15+ minutes
      document.getElementById('shCronAgeWarn').style.display = minsAgo >= 15 ? 'inline-block' : 'none';
    } else {
      document.getElementById('shLastCron').textContent = 'Never recorded (cron may not be running)';
      document.getElementById('shCronAgeWarn').style.display = 'inline-block';
    }
  }

  function setGatewayCard(cardId, valId, subId, status, label) {
    var card = document.getElementById(cardId);
    var val  = document.getElementById(valId);
    var sub  = document.getElementById(subId);
    var map = {
      'ok':             { text: '● OK',            color: 'var(--success)', bg: 'rgba(34,196,122,0.06)' },
      'degraded':       { text: '◐ Degraded',       color: 'var(--warn)',    bg: 'rgba(245,200,66,0.06)' },
      'down':           { text: '✕ Down',           color: 'var(--danger)',  bg: 'rgba(232,72,74,0.06)'  },
      'configured':     { text: '● OK',             color: 'var(--success)', bg: 'rgba(34,196,122,0.06)' },
      'not_configured': { text: '○ Not set',        color: 'var(--text-muted)', bg: 'transparent' },
    };
    var s = map[status] || { text: status, color: 'var(--text-muted)', bg: 'transparent' };
    val.textContent  = s.text;
    val.style.color  = s.color;
    card.style.background = s.bg;
    sub.textContent = label;
  }

  // Human-readable labels for each dispatch stage
  var PIPELINE_LABELS = {
    searching:        'Searching for driver',
    offered:          'Offer sent to driver',
    accepted:         'Driver accepted',
    arriving:         'Driver on the way',
    arrived_pickup:   'Driver at pickup',
    picked_up:        'In transit',
    arrived_dropoff:  'At dropoff',
    completed:        'Completed',
    cancelled:        'Cancelled',
    no_driver:        'No driver found',
  };

  // Stages where a large count is suspicious / worth highlighting
  var WARN_STAGES = ['searching', 'offered', 'no_driver'];

  function renderPipeline(pipeline) {
    var wrap = document.getElementById('shPipelineWrap');
    var stages = Object.keys(pipeline);
    if (stages.length === 0) {
      wrap.innerHTML = '<div style="color:var(--text-muted);font-size:13px;padding:20px 0">No active trips.</div>';
      return;
    }

    var total = stages.reduce(function (s, k) { return s + pipeline[k]; }, 0);

    var html = '<table style="width:100%;border-collapse:collapse;font-size:13px">' +
      '<thead><tr style="border-bottom:1px solid var(--surface-2)">' +
      '<th style="text-align:left;padding:8px 4px;color:var(--text-muted);font-weight:500">Stage</th>' +
      '<th style="text-align:right;padding:8px 4px;color:var(--text-muted);font-weight:500">Count</th>' +
      '<th style="text-align:right;padding:8px 4px;color:var(--text-muted);font-weight:500">Share</th>' +
      '</tr></thead><tbody>';

    stages.forEach(function (key) {
      var count   = pipeline[key];
      var pct     = total > 0 ? ((count / total) * 100).toFixed(0) : 0;
      var label   = PIPELINE_LABELS[key] || key;
      var isWarn  = WARN_STAGES.indexOf(key) !== -1 && count > 2;
      var rowStyle = isWarn ? 'background:rgba(232,72,74,0.04)' : '';
      var countCol = isWarn
        ? '<td style="text-align:right;padding:8px 4px;font-weight:700;color:var(--danger)">' + count + ' ⚠</td>'
        : '<td style="text-align:right;padding:8px 4px;font-weight:600">' + count + '</td>';
      html += '<tr style="border-bottom:1px solid var(--surface-2);' + rowStyle + '">' +
        '<td style="padding:8px 4px;color:var(--text-primary)">' + label + '</td>' +
        countCol +
        '<td style="text-align:right;padding:8px 4px;color:var(--text-muted)">' + pct + '%</td>' +
        '</tr>';
    });

    html += '</tbody></table>';
    wrap.innerHTML = html;
  }

  window.systemHealthForceOffline = function () {
    if (!confirm('Force all stale drivers offline? They will need to toggle back online manually.')) return;
    var btn = document.getElementById('shForceOfflineBtn');
    btn.disabled = true;
    btn.textContent = 'Working…';

    fetch(ADMIN_API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=force_drivers_offline',
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        btn.disabled = false;
        btn.textContent = 'Force All Offline';
        if (data.success) {
          alert(data.data.message);
          systemHealthRefresh();
        } else {
          alert('Error: ' + (data.data && data.data.message ? data.data.message : 'Unknown error'));
        }
      })
      .catch(function () {
        btn.disabled = false;
        btn.textContent = 'Force All Offline';
        alert('Network error. Please try again.');
      });
  };
})();
</script>
