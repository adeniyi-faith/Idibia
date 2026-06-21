<!-- SETTINGS -->
  <div class="panel" id="panel-settings">
    <div class="page-header"><h2 class="page-title">Settings</h2><div class="page-sub">Platform configuration and policies</div></div>
    <div class="settings-section">
      <h4>Services & APIs</h4>
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
      <h4>Commission & Pricing</h4>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Platform commission (%)</label><input class="form-input" type="number" data-setting="platform_commission_pct" value="20" min="1" max="50"></div>
        <div class="form-group"><label class="form-label">Surge multiplier cap (×)</label><input class="form-input" type="number" data-setting="surge_multiplier_cap" value="2.5" min="1" step="0.1"></div>
        <div class="form-group"><label class="form-label">Min. fare (₦)</label><input class="form-input" type="number" data-setting="min_fare" value="800"></div>
        <div class="form-group"><label class="form-label">Max. delivery radius (km)</label><input class="form-input" type="number" data-setting="max_delivery_radius_km" value="50"></div>
      </div>
    </div>

    <div class="settings-section">
      <h4>Manual Transfer Payments</h4>
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
      <div class="toggle-row"><div><div class="toggle-label">Auto-flag blurry ID photos</div><div class="toggle-sub">AI-assisted photo quality check</div></div><button class="toggle" data-setting="kyc_auto_flag_blurry" onclick="this.classList.toggle(\'on\')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Require vehicle inspection report</div><div class="toggle-sub">Mandatory for vans and tricycles</div></div><button class="toggle" data-setting="kyc_require_vehicle_inspection" onclick="this.classList.toggle(\'on\')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">72-hour KYC review SLA alert</div><div class="toggle-sub">Email admin if review exceeds 72h</div></div><button class="toggle" data-setting="kyc_72h_sla_alert" onclick="this.classList.toggle(\'on\')"></button></div>
      <div class="toggle-row"><div><div class="toggle-label">Background check integration</div><div class="toggle-sub">Third-party criminal record API</div></div><button class="toggle" data-setting="kyc_background_check" onclick="this.classList.toggle(\'on\')"></button></div>
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
    <div class="settings-section" id="zones-section">
      <div class="scard-header">
        <h4 style="border:0;margin:0;padding:0">Operational Zones</h4>
        <button class="scard-action" onclick="openZoneModal(null)">+ Add Zone</button>
      </div>
      <div style="font-size:12px;color:var(--text-muted);margin-bottom:12px">Drivers outside all active zones cannot go online. If no zones are defined the check is skipped.</div>
      <div id="zonesList"><div class="list-item"><div class="item-info"><div class="item-name">Loading zones…</div></div></div></div>
    </div>

    <div id="zoneModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center">
      <div style="background:var(--bg-card,#1e2030);border-radius:10px;padding:28px;width:420px;max-width:95vw">
        <h4 style="margin:0 0 18px" id="zoneModalTitle">Add Zone</h4>
        <input type="hidden" id="zoneId">
        <div class="form-group" style="margin-bottom:12px"><label class="form-label">Zone name</label><input class="form-input" id="zoneName" placeholder="e.g. Lagos Island"></div>
        <div class="form-row" style="gap:10px">
          <div class="form-group" style="flex:1"><label class="form-label">Centre latitude</label><input class="form-input" id="zoneLat" type="number" step="any" placeholder="6.4550"></div>
          <div class="form-group" style="flex:1"><label class="form-label">Centre longitude</label><input class="form-input" id="zoneLng" type="number" step="any" placeholder="3.3841"></div>
        </div>
        <div class="form-group" style="margin-bottom:12px"><label class="form-label">Radius (km)</label><input class="form-input" id="zoneRadius" type="number" min="0.1" step="0.1" placeholder="20"></div>
        <div class="toggle-row" style="margin-bottom:18px">
          <div><div class="toggle-label">Active</div></div>
          <button class="toggle on" id="zoneActive" onclick="this.classList.toggle(\'on\')"></button>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end">
          <button class="btn-secondary" onclick="closeZoneModal()">Cancel</button>
          <button class="btn-primary" onclick="saveZone()">Save Zone</button>
        </div>
      </div>
    </div>

    <button class="btn-primary" onclick="savePaymentSettings()">Save Changes</button>
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

  document.addEventListener('DOMContentLoaded',loadZones);
  document.getElementById('zoneModal').addEventListener('click',function(e){if(e.target===this)closeZoneModal();});
})();
</script>
