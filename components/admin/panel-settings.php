<!-- SETTINGS -->
  <div class="panel" id="panel-settings">
    <div class="page-header"><h2 class="page-title">Settings</h2><div class="page-sub">Platform configuration and policies</div></div>
    <div class="settings-section">
      <h4>Services & APIs</h4>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Nominatim URL</label><input class="form-input" data-setting="nominatim_url" placeholder="https://nominatim.openstreetmap.org/search"></div>
        <div class="form-group"><label class="form-label">ORS URL</label><input class="form-input" data-setting="ors_url" placeholder="https://api.openrouteservice.org/v2/directions/driving-car"></div>
        <div class="form-group"><label class="form-label">ORS API Key</label><input class="form-input" data-setting="ors_api_key" placeholder="ORS API Key"></div>
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
    <button class="btn-primary" onclick="savePaymentSettings()">Save Changes</button>
  </div>
