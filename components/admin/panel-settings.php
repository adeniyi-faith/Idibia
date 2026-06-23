<!-- SETTINGS -->
  <div class="panel" id="panel-settings">
    <div class="page-header"><h2 class="page-title">Settings</h2><div class="page-sub">Platform configuration and policies</div></div>
    <div class="settings-section" style="background:rgba(255,204,0,0.06);border:1px solid rgba(255,204,0,0.2);border-radius:8px;padding:14px 18px;margin-bottom:4px">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <div>
          <div style="font-weight:700;font-size:13px;color:var(--text-primary)">Settings Change History</div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:3px">Every time a setting is changed, the old and new values are recorded automatically.</div>
        </div>
        <button class="btn-primary" style="width:auto;padding:7px 16px;font-size:12px" onclick="openSettingsHistoryModal('')">View Full History</button>
      </div>
    </div>

    <div class="settings-section">
      <h4>Email (SMTP) <button class="scard-action" style="margin-left:10px;font-size:11px" onclick="openSettingsHistoryModal('smtp')">Change History</button></h4>
      <p style="font-size:13px;color:var(--text-muted);margin:0 0 16px">
        Connect a real email service so messages actually reach users' inboxes instead of going to spam.
        Leave blank to keep using the server's default mail (not recommended).
      </p>
      <div class="form-row">
        <div class="form-group"><label class="form-label">SMTP Host</label><input class="form-input" data-setting="smtp_host" placeholder="smtp.gmail.com or smtp.mailgun.org"></div>
        <div class="form-group"><label class="form-label">SMTP Port</label><input class="form-input" type="number" data-setting="smtp_port" placeholder="587" value="587"></div>
        <div class="form-group"><label class="form-label">SMTP Username</label><input class="form-input" data-setting="smtp_username" placeholder="your@email.com"></div>
        <div class="form-group"><label class="form-label">SMTP Password</label><input class="form-input" type="password" data-setting="smtp_password" placeholder="••••••••"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">From Email</label><input class="form-input" data-setting="smtp_from_email" placeholder="no-reply@idibia.com"></div>
        <div class="form-group"><label class="form-label">From Name</label><input class="form-input" data-setting="smtp_from_name" placeholder="Idibia"></div>
      </div>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <button class="btn-primary" style="font-size:12px;padding:8px 14px;width:auto" onclick="savePaymentSettings()">Save Email settings</button>
        <div style="display:flex;gap:6px;align-items:center">
          <input class="form-input" id="smtpTestEmail" type="email" placeholder="test@example.com" style="width:200px">
          <button class="btn-primary" style="font-size:12px;padding:8px 14px;width:auto;background:var(--text-muted)" onclick="sendTestEmail()">Send Test Email</button>
        </div>
        <span id="smtp-test-status" style="font-size:13px;color:var(--text-muted)"></span>
      </div>
    </div>

    <div class="settings-section">
      <h4>Services &amp; APIs <button class="scard-action" style="margin-left:10px;font-size:11px" onclick="openSettingsHistoryModal('api')">Change History</button></h4>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Nominatim URL</label><input class="form-input" data-setting="nominatim_url" placeholder="https://nominatim.openstreetmap.org/search"></div>
        <div class="form-group"><label class="form-label">ORS URL</label><input class="form-input" data-setting="ors_url" placeholder="https://api.openrouteservice.org/v2/directions/driving-car"></div>
        <div class="form-group"><label class="form-label">ORS API Key</label><input class="form-input" data-setting="ors_api_key" placeholder="ORS API Key"></div>
        <div class="form-group"><label class="form-label">OpenCage API Key <span style="font-weight:400;color:var(--text-muted)">(improves Nigerian address search)</span></label><input class="form-input" data-setting="opencage_api_key" placeholder="Get free key at opencagedata.com"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Pusher App ID</label><input class="form-input" data-setting="pusher_app_id" placeholder="App ID"></div>
        <div class="form-group"><label class="form-label">Pusher Key</label><input class="form-input" data-setting="pusher_key" placeholder="Key"></div>
        <div class="form-group"><label class="form-label">Pusher Secret</label><input class="form-input" type="password" data-setting="pusher_secret" placeholder="********"></div>
        <div class="form-group"><label class="form-label">Pusher Cluster</label><input class="form-input" data-setting="pusher_cluster" placeholder="eu"></div>
      </div>
      <button class="btn-primary" style="font-size:12px;padding:8px 14px;width:auto" onclick="savePaymentSettings()">Save API settings</button>
    </div>

    <div class="settings-section">
      <h4>Commission &amp; Pricing <button class="scard-action" style="margin-left:10px;font-size:11px" onclick="openSettingsHistoryModal('commission')">Change History</button></h4>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Platform commission (%)</label><input class="form-input" type="number" data-setting="platform_commission_pct" value="20" min="1" max="50"></div>
        <div class="form-group"><label class="form-label">Surge multiplier cap (×)</label><input class="form-input" type="number" data-setting="surge_multiplier_cap" value="2.5" min="1" step="0.1"></div>
        <div class="form-group"><label class="form-label">Min. fare (₦)</label><input class="form-input" type="number" data-setting="min_fare" value="800"></div>
        <div class="form-group"><label class="form-label">Max. delivery radius (km)</label><input class="form-input" type="number" data-setting="max_delivery_radius_km" value="50"></div>
      </div>
    </div>

    <div class="settings-section">
      <h4>Manual Transfer Payments <button class="scard-action" style="margin-left:10px;font-size:11px" onclick="openSettingsHistoryModal('payment')">Change History</button></h4>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Active provider</label><select class="form-input" data-setting="payment_active_provider"><option value="manual_transfer">Manual transfer</option><option value="paystack">Paystack (future)</option><option value="flutterwave">Flutterwave (future)</option></select></div>
        <div class="form-group"><label class="form-label">Bank name</label><input class="form-input" data-setting="manual_bank_name" placeholder="e.g. GTBank"></div>
        <div class="form-group"><label class="form-label">Account name</label><input class="form-input" data-setting="manual_account_name" placeholder="Idibia Logistics Ltd"></div>
        <div class="form-group"><label class="form-label">Account number</label><input class="form-input" data-setting="manual_account_number" placeholder="0123456789"></div>
      </div>
      <div class="form-group"><label class="form-label">Customer payment instructions</label><textarea class="form-input" data-setting="manual_payment_instructions" rows="3" placeholder="Transfer exact fare and upload receipt."></textarea></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Paystack public key</label><input class="form-input" data-setting="paystack_public_key" placeholder="Future use"></div>
        <div class="form-group"><label class="form-label">Paystack secret key</label><input class="form-input" data-setting="paystack_secret_key" placeholder="Future use"></div>
        <div class="form-group"><label class="form-label">Flutterwave public key</label><input class="form-input" data-setting="flutterwave_public_key" placeholder="Future use"></div>
        <div class="form-group"><label class="form-label">Flutterwave secret key</label><input class="form-input" data-setting="flutterwave_secret_key" placeholder="Future use"></div>
      </div>
      <button class="btn-primary" style="font-size:12px;padding:8px 14px;width:auto" onclick="savePaymentSettings()">Save payment settings</button>
    </div>
    <div class="settings-section">
      <div class="scard-header"><h4 style="border:0;margin:0;padding:0">Manual Payment Reviews</h4><button class="scard-action" onclick="loadManualPayments()">Refresh</button></div>
      <div id="manualPaymentsList"><div class="list-item"><div class="item-info"><div class="item-name">Loading payment proofs…</div></div></div></div>
    </div>
    <div class="settings-section">
      <h4>KYC Policy</h4>
      <div class="toggle-row"><div><div class="toggle-label">Auto-flag blurry ID photos</div><div class="toggle-sub">AI-assisted photo quality check</div></div><button class="toggle" data-setting="kyc_auto_flag_blurry" onclick="this.classList.toggle('on')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Require vehicle inspection report</div><div class="toggle-sub">Mandatory for vans and tricycles</div></div><button class="toggle" data-setting="kyc_require_vehicle_inspection" onclick="this.classList.toggle('on')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">72-hour KYC review SLA alert</div><div class="toggle-sub">Email admin if review exceeds 72h</div></div><button class="toggle" data-setting="kyc_72h_sla_alert" onclick="this.classList.toggle('on')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Background check integration</div><div class="toggle-sub">Third-party criminal record API</div></div><button class="toggle" data-setting="kyc_background_check" onclick="this.classList.toggle('on')"></button></div>
    </div>
    <div class="settings-section">
      <h4>KYC Document Requirements</h4>
      <p style="font-size:13px;color:var(--text-muted);margin:0 0 16px">Configure which documents each vehicle class must submit during KYC. Changes take effect for new applications immediately.</p>
      <div id="kycPolicySection" style="min-height:80px"></div>
      <button class="btn-primary" style="font-size:12px;padding:8px 14px;width:auto;margin-top:16px" onclick="saveKycPolicy()">Save Document Policy</button>
    </div>
    <div class="settings-section">
      <h4>Notifications</h4>
      <div class="toggle-row"><div><div class="toggle-label">KYC queue alerts</div><div class="toggle-sub">Email when queue exceeds 5</div></div><button class="toggle" data-setting="notif_kyc_queue" onclick="this.classList.toggle(\'on\')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Dispute escalation alerts</div><div class="toggle-sub">Push alert when dispute >48h unresolved</div></div><button class="toggle" data-setting="notif_dispute_escalation" onclick="this.classList.toggle(\'on\')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Daily revenue digest</div><div class="toggle-sub">Email summary at 8pm daily</div></div><button class="toggle" data-setting="notif_daily_revenue" onclick="this.classList.toggle(\'on\')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Failed payout alerts</div><div class="toggle-sub">Instant alert on payout failures</div></div><button class="toggle" data-setting="notif_failed_payout" onclick="this.classList.toggle(\'on\')"></button></div>
    </div>
    <div class="settings-section">
      <h4>Legal & Compliance</h4>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Terms & Conditions URL</label><input class="form-input" data-setting="legal_terms_url" placeholder="https://..."></div>
        <div class="form-group"><label class="form-label">Privacy Policy URL</label><input class="form-input" data-setting="legal_privacy_url" placeholder="https://..."></div>
        <div class="form-group"><label class="form-label">Location Data Policy URL</label><input class="form-input" data-setting="legal_location_url" placeholder="https://..."></div>
        <div class="form-group"><label class="form-label">Software License URL</label><input class="form-input" data-setting="legal_license_url" placeholder="https://..."></div>
      </div>
    </div>
    <div class="settings-section">
      <h4>Operational Settings <button class="scard-action" style="margin-left:10px;font-size:11px" onclick="openSettingsHistoryModal('operational')">Change History</button></h4>
      <p style="font-size:13px;color:var(--text-muted);margin:0 0 16px">Controls automated dispatch timing, offer retry behaviour, and the no-driver cancellation timeout.</p>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Dispatch retry limit</label>
          <input class="form-input" type="number" data-setting="dispatch_retry_limit" value="3" min="1" max="20" placeholder="3">
          <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Max dispatch rounds before marking a trip as no-driver (default: 3)</div>
        </div>
        <div class="form-group">
          <label class="form-label">Trip timeout (minutes)</label>
          <input class="form-input" type="number" data-setting="trip_timeout_minutes" value="10" min="1" max="120" placeholder="10">
          <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Minutes to wait before auto-cancelling a trip with no driver (default: 10)</div>
        </div>
        <div class="form-group">
          <label class="form-label">Scheduled dispatch advance (minutes)</label>
          <input class="form-input" type="number" data-setting="scheduled_dispatch_advance_minutes" value="10" min="1" max="60" placeholder="10">
          <div style="font-size:11px;color:var(--text-muted);margin-top:4px">How many minutes before scheduled_time to begin dispatching (default: 10)</div>
        </div>
      </div>
      <button class="btn-primary" style="font-size:12px;padding:8px 14px;width:auto" onclick="savePaymentSettings()">Save operational settings</button>
    </div>

    <button class="btn-primary" onclick="savePaymentSettings()">Save Changes</button>

    <div class="settings-section">
      <div class="scard-header">
        <h4 style="border:0;margin:0;padding:0">Geographic Zones</h4>
        <button class="scard-action" onclick="openZoneModal(null)">+ Add Zone</button>
      </div>
      <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px">Define service zones. When at least one zone is active, only pickups within a zone will be accepted.</p>
      <div id="zonesList"><div class="list-item"><div class="item-info"><div class="item-name" style="color:var(--text-muted)">Loading zones…</div></div></div></div>
    </div>
  </div>

<script>
(function(){
  function loadZones(){
    fetch('/admin/api.php?action=get_zones').then(r=>r.json()).then(d=>{
      var el=document.getElementById('zonesList');
      if(!d.success||!d.data.zones.length){el.innerHTML='<div class="list-item"><div class="item-info"><div class="item-name" style="color:var(--text-muted)">No zones defined — geofence check is disabled.</div></div></div>';return;}
      el.innerHTML=d.data.zones.map(function(z){
        return '<div class="list-item" style="display:flex;align-items:center;justify-content:space-between">'+
          '<div class="item-info">'+
            '<div class="item-name">'+z.name+'</div>'+
            '<div class="item-sub" style="font-size:11px;color:var(--text-muted)">'+z.center_lat+', '+z.center_lng+' &bull; '+z.radius_km+' km &bull; '+(z.is_active?'<span style="color:#4caf50">Active</span>':'<span style="color:var(--text-muted)">Inactive</span>')+'</div>'+
          '</div>'+
          '<div style="display:flex;gap:8px">'+
            '<button class="scard-action" onclick=\'openZoneModal('+JSON.stringify(z)+')\'>Edit</button>'+
            '<button class="scard-action" style="color:#f44" onclick="deleteZone('+z.id+')">Delete</button>'+
          '</div>'+
        '</div>';
      }).join('');
    });
  }

  window.openZoneModal=function(z){
    document.getElementById('zoneModalTitle').textContent=z?'Edit Zone':'Add Zone';
    document.getElementById('zoneId').value=z?z.id:'';
    document.getElementById('zoneName').value=z?z.name:'';
    document.getElementById('zoneLat').value=z?z.center_lat:'';
    document.getElementById('zoneLng').value=z?z.center_lng:'';
    document.getElementById('zoneRadius').value=z?z.radius_km:'';
    var btn=document.getElementById('zoneActive');
    btn.className='toggle'+((!z||z.is_active)?' on':'');
    var modal=document.getElementById('zoneModal');
    modal.style.display='flex';
  };

  window.closeZoneModal=function(){document.getElementById('zoneModal').style.display='none';};

  window.saveZone=function(){
    var id=document.getElementById('zoneId').value;
    var body=new FormData();
    body.append('action',id?'update_zone':'create_zone');
    if(id) body.append('zone_id',id);
    body.append('name',document.getElementById('zoneName').value);
    body.append('center_lat',document.getElementById('zoneLat').value);
    body.append('center_lng',document.getElementById('zoneLng').value);
    body.append('radius_km',document.getElementById('zoneRadius').value);
    body.append('is_active',document.getElementById('zoneActive').classList.contains('on')?'1':'0');
    fetch('/admin/api.php',{method:'POST',body:body}).then(r=>r.json()).then(function(d){
      if(d.success){closeZoneModal();loadZones();}else{alert(d.data&&d.data.message?d.data.message:'Save failed.');}
    });
  };

  window.deleteZone=function(id){
    if(!confirm('Delete this zone?')) return;
    var body=new FormData();
    body.append('action','delete_zone');
    body.append('zone_id',id);
    fetch('/admin/api.php',{method:'POST',body:body}).then(r=>r.json()).then(function(d){
      if(d.success) loadZones(); else alert(d.data&&d.data.message?d.data.message:'Delete failed.');
    });
  };

  document.addEventListener('DOMContentLoaded',function(){
    loadZones();
    var zm=document.getElementById('zoneModal');
    if(zm) zm.addEventListener('click',function(e){if(e.target===this)closeZoneModal();});
  });

  // ── Settings Change History modal ──────────────────────────────────────────

  // Keys that belong to each section (for client-side filtering when keys
  // don't share a common DB prefix).
  var _SECT_KEYS = {
    api: ['nominatim_url','ors_url','ors_api_key','opencage_api_key',
          'pusher_app_id','pusher_key','pusher_secret','pusher_cluster'],
    commission: ['platform_commission_pct','surge_multiplier_cap',
                 'min_fare','max_delivery_radius_km'],
    payment: ['payment_active_provider','manual_bank_name','manual_account_name',
              'manual_account_number','manual_payment_instructions',
              'paystack_public_key','paystack_secret_key',
              'flutterwave_public_key','flutterwave_secret_key'],
    operational: ['dispatch_retry_limit','trip_timeout_minutes',
                  'scheduled_dispatch_advance_minutes']
  };
  var _SECT_TITLES = {
    '':'All Settings', smtp:'Email (SMTP)', api:'Services & APIs',
    commission:'Commission & Pricing', payment:'Manual Transfer Payments',
    operational:'Operational Settings'
  };
  var _shPage = 1;
  var _shPrefix = '';

  window.openSettingsHistoryModal = function(prefix){
    _shPrefix = prefix || '';
    _shPage   = 1;
    var title = (_SECT_TITLES[_shPrefix] || 'Settings') + ' — Change History';
    document.getElementById('settingsHistoryTitle').textContent = title;
    document.getElementById('settingsHistoryModal').style.display = 'flex';
    _loadSettingsHistory();
  };

  window.closeSettingsHistoryModal = function(){
    document.getElementById('settingsHistoryModal').style.display = 'none';
  };

  window.settingsHistoryChangePage = function(p){
    _shPage = p;
    _loadSettingsHistory();
  };

  function _loadSettingsHistory(){
    var body = document.getElementById('settingsHistoryBody');
    body.innerHTML = '<div style="text-align:center;padding:32px;color:var(--text-muted);font-size:13px">Loading…</div>';
    var params = { page: _shPage, per_page: 25 };
    if(_shPrefix === 'smtp'){
      params.key_prefix = 'smtp_';
    }
    adminApi('get_settings_history', params).then(function(data){
      var rows = data.history || [];
      if(_shPrefix && _shPrefix !== 'smtp'){
        var allow = _SECT_KEYS[_shPrefix];
        if(allow) rows = rows.filter(function(r){ return allow.indexOf(r.setting_key) !== -1; });
      }
      if(!rows.length){
        body.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted);font-size:13px">No changes recorded for this section yet.</div>';
        document.getElementById('settingsHistoryPagination').innerHTML = '';
        return;
      }
      body.innerHTML = rows.map(function(r){
        var changed = r.old_value !== r.new_value;
        var oldV = (r.old_value === null || r.old_value === undefined || r.old_value === '')
          ? '<span style="color:var(--text-muted)">—</span>'
          : '<code style="background:rgba(239,68,68,0.08);color:#dc2626;padding:2px 5px;border-radius:4px;font-size:11px;word-break:break-all">' + escapeHtml(String(r.old_value).slice(0,80) + (String(r.old_value).length > 80 ? '…' : '')) + '</code>';
        var newV = (r.new_value === null || r.new_value === undefined || r.new_value === '')
          ? '<span style="color:var(--text-muted)">—</span>'
          : '<code style="background:rgba(34,197,94,0.08);color:#16a34a;padding:2px 5px;border-radius:4px;font-size:11px;word-break:break-all">' + escapeHtml(String(r.new_value).slice(0,80) + (String(r.new_value).length > 80 ? '…' : '')) + '</code>';
        var who  = r.changed_by_name ? escapeHtml(r.changed_by_name) : 'Admin #' + r.changed_by_admin_id;
        var when = r.changed_at ? new Date(r.changed_at.replace(' ','T')+'Z').toLocaleString() : '—';
        return '<div style="padding:12px 16px;border-bottom:1px solid var(--surface-2)">' +
          '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px;flex-wrap:wrap">' +
            '<code style="font-size:12px;font-weight:600;color:var(--text)">' + escapeHtml(r.setting_key) + '</code>' +
            '<span style="font-size:11px;color:var(--text-muted);white-space:nowrap">' + who + ' · ' + when + '</span>' +
          '</div>' +
          (changed
            ? '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;font-size:12px">' +
                '<span style="color:var(--text-muted);font-size:11px;flex-shrink:0">was</span>' + oldV +
                '<span style="color:var(--text-muted);font-size:11px;flex-shrink:0">→ now</span>' + newV +
              '</div>'
            : '<div style="font-size:11px;color:var(--text-muted)">Value unchanged (recorded on save)</div>'
          ) +
        '</div>';
      }).join('');
      var total = parseInt(data.total || 0, 10);
      var perPage = parseInt(data.per_page || 25, 10);
      var pages = Math.max(1, Math.ceil(total / perPage));
      document.getElementById('settingsHistoryPagination').innerHTML =
        '<span>Page ' + _shPage + ' of ' + pages + ' · ' + total + ' total</span> ' +
        '<button style="padding:4px 10px;border-radius:6px;border:1px solid var(--surface-2);background:none;font-size:12px;cursor:pointer" ' + (_shPage <= 1 ? 'disabled' : '') + ' onclick="settingsHistoryChangePage(' + (_shPage - 1) + ')">Prev</button> ' +
        '<button style="padding:4px 10px;border-radius:6px;border:1px solid var(--surface-2);background:none;font-size:12px;cursor:pointer" ' + (_shPage >= pages ? 'disabled' : '') + ' onclick="settingsHistoryChangePage(' + (_shPage + 1) + ')">Next</button>';
    }).catch(function(e){
      body.innerHTML = '<div style="text-align:center;padding:24px;color:var(--danger);font-size:13px">' + escapeHtml(e.message || 'Failed to load history') + '</div>';
    });
  }

  window.sendTestEmail = function(){
    var to = document.getElementById('smtpTestEmail').value.trim();
    var st = document.getElementById('smtp-test-status');
    if(!to){ alert('Enter a recipient email address first.'); return; }
    st.style.color='var(--text-muted)'; st.textContent='Sending…';
    var fd=new FormData();
    fd.append('action','test_smtp_email');
    fd.append('to', to);
    fetch('/admin/api.php',{method:'POST',body:fd,credentials:'include'})
      .then(function(r){ return r.json(); })
      .then(function(d){
        st.style.color = d.success ? '#4caf50' : '#f44';
        st.textContent = d.data && d.data.message ? d.data.message : (d.success ? 'Sent!' : 'Failed.');
      })
      .catch(function(){ st.style.color='#f44'; st.textContent='Network error.'; });
  };
})();
</script>
