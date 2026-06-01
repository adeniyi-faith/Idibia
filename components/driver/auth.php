<div class="screen <?php echo $driver_initial_context['is_approved'] ? '' : 'active'; ?>" id="screen-driver">
    <div class="driver-header" id="driverHeaderWrap">
      <div>
        <div style="font-size:11px;color:var(--slate);letter-spacing:1.2px;text-transform:uppercase;margin-bottom:4px;position:relative;z-index:1">Driver Registration</div>
        <h2 id="driverStepTitle">Account Setup</h2>
      </div>
      <div class="step-indicator">Step <strong id="driverStepNum">1</strong>&nbsp;/ 5</div>
    </div>
    <div class="progress-bar-wrap" id="driverProgressWrap">
      <div class="progress-bar-track">
        <div class="progress-bar-fill" id="driverProgress" style="width:20%"></div>
      </div>
    </div>

    <div class="driver-content" id="driverContent">

      <!-- Step 1: Account Initialization -->
      <div class="driver-step active" id="dstep-1">
        <h3 class="step-title">Let's get you started</h3>
        <p class="step-sub">Select your language and fill in your basic personal information</p>
        <div class="form-group driver-register-only">
          <label class="form-label">Preferred Language</label>
          <select class="form-input" id="driverLanguage">
            <option>English</option>
            <option>Hausa</option>
            <option>Yoruba</option>
            <option>Igbo</option>
            <option>Pidgin</option>
          </select>
        </div>

        <div class="driver-auth-card">
          <div class="driver-auth-tabs" role="tablist" aria-label="Driver account access">
            <button class="driver-auth-tab active" id="driverSignupTab" type="button" role="tab" aria-selected="true" aria-controls="driverSignupPanel" onclick="setDriverAuthMode('signup')">Register</button>
            <button class="driver-auth-tab" id="driverLoginTab" type="button" role="tab" aria-selected="false" aria-controls="driverLoginPanel" onclick="setDriverAuthMode('login')">Sign in</button>
          </div>

          <div class="driver-auth-panel active" id="driverSignupPanel" role="tabpanel" aria-labelledby="driverSignupTab">
            <p class="driver-auth-help">Create your driver account first. The Continue button will save it before moving to verification.</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div class="form-group">
                <label class="form-label">First Name</label>
                <input class="form-input" type="text" id="driverFirstName" placeholder="First" autocomplete="given-name">
              </div>
              <div class="form-group">
                <label class="form-label">Last Name</label>
                <input class="form-input" type="text" id="driverLastName" placeholder="Last" autocomplete="family-name">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input class="form-input" type="email" id="driverEmail" placeholder="you@example.com" autocomplete="email">
            </div>
            <div class="form-group">
              <label class="form-label">Phone Number</label>
              <input class="form-input" type="tel" id="driverPhone" placeholder="08012345678" autocomplete="tel">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Password</label>
              <div style="position:relative">
                <input class="form-input" type="password" id="driverPassword" placeholder="Min. 6 characters" autocomplete="new-password" style="padding-right:40px">
                <button type="button" onclick="togglePasswordVisibility('driverPassword', this)" aria-label="Toggle password visibility" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);display:flex"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
              </div>
            </div>
          </div>

          <div class="driver-auth-panel" id="driverLoginPanel" role="tabpanel" aria-labelledby="driverLoginTab">
            <p class="driver-auth-help">Already registered? Sign in with your phone/email and password to open your driver dashboard.</p>
            <div class="form-group"><label class="form-label">Phone or Email</label><input class="form-input" type="text" id="driverLoginPhone" placeholder="Phone or email" autocomplete="username"></div>
            <div class="form-group"><label class="form-label">Password</label>
              <div style="position:relative">
                <input class="form-input" type="password" id="driverLoginPassword" placeholder="Password" autocomplete="current-password" style="padding-right:40px">
                <button type="button" onclick="togglePasswordVisibility('driverLoginPassword', this)" aria-label="Toggle password visibility" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);display:flex"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
              </div>
            </div>

          </div>
        </div>

        <div class="driver-auth-card hidden" id="driverEmailVerifyPanel">
          <p class="driver-auth-help" id="driverEmailVerifyHelp">We sent a 5-digit code to your email. Paste it below to unlock KYC.</p>
          <div class="form-group">
            <label class="form-label">Email Verification Code</label>
            <input class="form-input" type="tel" id="driverVerifyCode" inputmode="numeric" maxlength="5" placeholder="12345" autocomplete="one-time-code">
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;align-items:center;justify-content:space-between;">
            <button type="button" onclick="resendDriverVerifyCode()" style="background:none;border:none;color:var(--info);font-weight:600;font-size:14px;cursor:pointer;padding:8px 0;transition:opacity 0.2s;">Resend code</button>
          </div>
        </div>

        <div class="driver-register-only" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Middle Name</label>
            <input class="form-input" type="text" id="driverMiddleName" placeholder="Middle">
          </div>
          <div class="form-group">
            <label class="form-label">Date of Birth</label>
            <input class="form-input" type="date" id="driverDob">
          </div>
        </div>
        <div class="form-group driver-register-only">
          <label class="form-label">Gender</label>
          <select class="form-input" id="driverGender">
            <option>Male</option>
            <option>Female</option>
            <option>Prefer not to say</option>
          </select>
        </div>
        <div class="form-group driver-register-only">
          <label class="form-label">State of Origin</label>
          <select class="form-input" id="driverStateOrigin">
            <option>Abia</option><option>Adamawa</option><option>Akwa Ibom</option><option>Anambra</option>
            <option>Bauchi</option><option>Bayelsa</option><option>Benue</option><option>Borno</option>
            <option>Cross River</option><option>Delta</option><option>Ebonyi</option><option>Edo</option>
            <option>Ekiti</option><option>Enugu</option><option>Gombe</option><option>Imo</option>
            <option>Jigawa</option><option>Kaduna</option><option>Kano</option><option>Katsina</option>
            <option>Kebbi</option><option>Kogi</option><option>Kwara</option><option>Lagos</option>
            <option>Nasarawa</option><option>Niger</option><option>Ogun</option><option>Ondo</option>
            <option>Osun</option><option>Oyo</option><option>Plateau</option><option>Rivers</option>
            <option>Sokoto</option><option>Taraba</option><option>Yobe</option><option>Zamfara</option>
            <option>Abuja FCT</option>
          </select>
        </div>
        <span class="section-label driver-register-only" style="margin-top:8px">Select your vehicle class</span>
        <div class="vehicle-grid driver-register-only" id="vg1">
          <div class="vehicle-card active" data-value="bike" onclick="selVehicle(this, 'vg1')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="5.5" cy="17.5" r="2.5"/><circle cx="17" cy="17.5" r="2.5"/><path d="M5.5 17.5h11.5M8 9l-2.5 8.5M12 5l4 12.5M12 5H8l-2.5 4h9l-2.5-4z"/></svg>
            <span>Motorbike</span>
          </div>
          <div class="vehicle-card" data-value="car" onclick="selVehicle(this, 'vg1')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="6" width="15" height="10" rx="2"/><polygon points="16 9 20 9 23 13 23 16 16 16 16 9"/><circle cx="5.5" cy="18.5" r="2"/><circle cx="18.5" cy="18.5" r="2"/></svg>
            <span>Car</span>
          </div>
          <div class="vehicle-card" data-value="keke" onclick="selVehicle(this, 'vg1')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 17H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v3m0 0h4l2 5v4h-6"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
            <span>Tricycle</span>
          </div>
          <div class="vehicle-card" data-value="van" onclick="selVehicle(this, 'vg1')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="20" height="14" rx="2"/><circle cx="5.5" cy="20" r="2"/><circle cx="18.5" cy="20" r="2"/><line x1="1" y1="12" x2="21" y2="12"/></svg>
            <span>Van</span>
          </div>
        </div>
      </div>

      <!-- Step 2: Identity Verification -->
      <div class="driver-step" id="dstep-2">
        <h3 class="step-title">Identity verification</h3>
        <p class="step-sub">Your documents are encrypted and stored securely. This step is confidential.</p>
        <div class="info-note">
          <strong>🔒 Strictly confidential</strong><br>
          This information is used only for background verification and will never be shared with customers.
        </div>
        <div class="upload-box" data-field="id_front" onclick="chooseKycFile(this)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
          <p><strong>Driver's License</strong></p>
          <small>Tap to upload · JPG, PNG or PDF</small>
        </div>
        <div class="upload-box" data-field="id_back" onclick="chooseKycFile(this)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-6-4L3 20"/></svg>
          <p><strong>National ID Card / NIN Slip</strong></p>
          <small>Tap to upload · JPG or PNG</small>
        </div>
        <div class="upload-box" data-field="selfie" onclick="chooseKycFile(this)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <p><strong>Profile Photo</strong></p>
          <small>Clear portrait · Full face · No caps or glasses</small>
        </div>
        <div class="info-note gold">
          📷 Photo requirements: Clear face, neutral background, eyes open, no headwear or sunglasses.
        </div>
      </div>

      <!-- Step 3: Vehicle Information -->
      <div class="driver-step" id="dstep-3">
        <h3 class="step-title">Vehicle information</h3>
        <p class="step-sub">Tell us about the vehicle you'll be using for deliveries</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Year</label>
            <input class="form-input" type="number" id="driverVehicleYear" placeholder="2020">
          </div>
          <div class="form-group">
            <label class="form-label">Manufacturer</label>
            <input class="form-input" type="text" id="driverVehicleManufacturer" placeholder="Toyota">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">License Plate Number</label>
          <input class="form-input" type="text" id="driverVehiclePlate" placeholder="e.g. ABC 123 DE" style="text-transform:uppercase">
        </div>
        <div class="form-group">
          <label class="form-label">Vehicle Color</label>
          <input class="form-input" type="text" id="driverVehicleColor" placeholder="e.g. Red">
        </div>
        <span class="section-label" style="margin-top:4px">Vehicle photos (high quality, all angles)</span>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
          <div class="upload-box" data-field="vehicle_photo" style="padding:22px 12px;margin-bottom:0" onclick="chooseKycFile(this)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-6-6L3 21"/></svg>
            <p style="font-size:12px;margin-top:6px">Exterior</p>
          </div>
          <div class="upload-box" data-field="vehicle_interior_photo" style="padding:22px 12px;margin-bottom:0" onclick="chooseKycFile(this)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-6-6L3 21"/></svg>
            <p style="font-size:12px;margin-top:6px">Interior</p>
          </div>
          <div class="upload-box" data-field="vehicle_front_photo" style="padding:22px 12px;margin-bottom:0" onclick="chooseKycFile(this)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-6-6L3 21"/></svg>
            <p style="font-size:12px;margin-top:6px">Front angle</p>
          </div>
          <div class="upload-box" data-field="vehicle_rear_photo" style="padding:22px 12px;margin-bottom:0" onclick="chooseKycFile(this)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-6-6L3 21"/></svg>
            <p style="font-size:12px;margin-top:6px">Rear angle</p>
          </div>
        </div>
        <span class="section-label">Vehicle documents</span>
        <div class="upload-box" data-field="vehicle_license_doc" onclick="chooseKycFile(this)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <p><strong>Vehicle License Certificate</strong></p>
          <small>Upload valid document</small>
        </div>
        <div class="upload-box" data-field="insurance_doc" onclick="chooseKycFile(this)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <p><strong>Vehicle Inspection Report</strong></p>
          <small>Recent inspection certificate</small>
        </div>
      </div>

      <!-- Step 4: Financial & Emergency -->
      <div class="driver-step" id="dstep-4">
        <h3 class="step-title">Financial & emergency info</h3>
        <p class="step-sub">For payouts and your safety record</p>
        <span class="section-label">Billing type</span>
        <div style="display:flex;gap:10px;margin-bottom:22px" id="billingGroup">
          <div class="vehicle-card active" data-value="personal" onclick="selVehicle(this, 'billingGroup')" style="flex:1;flex-direction:row;justify-content:flex-start;padding:14px 16px;gap:10px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>Personal</span>
          </div>
          <div class="vehicle-card" data-value="company" onclick="selVehicle(this, 'billingGroup')" style="flex:1;flex-direction:row;justify-content:flex-start;padding:14px 16px;gap:10px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            <span>Company</span>
          </div>
        </div>
        <span class="section-label">Bank details</span>
        <div class="form-group">
          <label class="form-label">Account Holder Name</label>
          <input class="form-input" type="text" id="driverAccountHolder" placeholder="As on bank records">
        </div>
        <div class="form-group">
          <label class="form-label">Account Number</label>
          <input class="form-input" type="number" id="driverAccountNumber" placeholder="0123456789" maxlength="10">
        </div>
        <div class="form-group">
          <label class="form-label">Bank Name</label>
          <select class="form-input" id="driverBankName">
            <option>Access Bank</option>
            <option>GTBank</option>
            <option>UBA</option>
            <option>Zenith Bank</option>
            <option>First Bank</option>
            <option>Kuda Bank</option>
            <option>OPay</option>
            <option>Palmpay</option>
            <option>Wema Bank</option>
            <option>Polaris Bank</option>
          </select>
        </div>
        <span class="section-label" style="margin-top:4px">Emergency contact</span>
        <div class="form-group">
          <label class="form-label">Next of Kin Full Name</label>
          <input class="form-input" type="text" id="driverEmergencyName" placeholder="Full name">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Relationship</label>
            <select class="form-input" id="driverEmergencyRelationship">
              <option>Parent</option>
              <option>Spouse</option>
              <option>Sibling</option>
              <option>Child</option>
              <option>Friend</option>
              <option>Other</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input class="form-input" type="tel" id="driverEmergencyPhone" placeholder="+234...">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Address</label>
          <input class="form-input" type="text" id="driverEmergencyAddress" placeholder="Next of kin address">
        </div>
      </div>

      <!-- Step 5: Pending -->
      <div class="driver-step" id="dstep-5">
        <div class="pending-screen">
          <div class="pending-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <h3>Application Under Review</h3>
          <p>We're verifying your documents and details. This usually takes <strong>24–48 hours.</strong><br><br>You'll receive a push notification once your account is approved.</p>
          <div class="pending-app-id">
            <div class="label">Application Reference</div>
            <div class="id">#DRV-00412</div>
          </div>
          <div class="pending-steps">
            <div class="pending-step-item">
              <div class="pending-step-dot done"></div>
              <div class="pending-step-text"><strong>Documents Submitted</strong>All files uploaded successfully</div>
            </div>
            <div class="pending-step-item">
              <div class="pending-step-dot pending"></div>
              <div class="pending-step-text"><strong>Identity Verification</strong>Checking NIN & Driver's License</div>
            </div>
            <div class="pending-step-item">
              <div class="pending-step-dot waiting"></div>
              <div class="pending-step-text"><strong>Vehicle Inspection</strong>Awaiting document review</div>
            </div>
            <div class="pending-step-item">
              <div class="pending-step-dot waiting"></div>
              <div class="pending-step-text"><strong>Account Activation</strong>Final approval & onboarding</div>
            </div>
          </div>
          <button class="global-btn" style="margin-top:28px;width:100%;justify-content:center" onclick="goToDashboard()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Open Driver Dashboard
          </button>
        </div>
      </div>
    </div>

    <div class="driver-footer" id="driverFooterWrap">
      <button class="btn-back" id="driverBack" onclick="driverPrev()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      </button>
      <button class="btn-primary" id="driverNext" onclick="driverNext()" style="flex:1">
        Continue
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </button>
    </div>
  </div>
