<div class="modal" id="modal-preferences">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Notification Preferences</h3>
      <button class="icon-btn" aria-label="Close modal" onclick="closeModal('modal-preferences')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body" style="padding: 20px;">
      <form id="preferencesForm" onsubmit="event.preventDefault(); savePreferences();">
        <div class="fi-row" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; padding-bottom:15px; border-bottom:1px solid rgba(255,255,255,0.05);">
          <div>
            <div style="font-weight:600; font-size:14px; margin-bottom:4px;">Trip Updates</div>
            <div style="font-size:12px; color:var(--text-muted);">SMS and Push notifications about your trip status.</div>
          </div>
          <label style="position:relative; display:inline-block; width:40px; height:24px;">
            <input type="checkbox" id="pref_trip_updates" style="opacity:0; width:0; height:0;">
            <span class="slider" style="position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background-color:var(--surface-light); transition:.4s; border-radius:34px;">
              <span class="knob" style="position:absolute; content:''; height:16px; width:16px; left:4px; bottom:4px; background-color:white; transition:.4s; border-radius:50%;"></span>
            </span>
          </label>
        </div>

        <div class="fi-row" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; padding-bottom:15px; border-bottom:1px solid rgba(255,255,255,0.05);">
          <div>
            <div style="font-weight:600; font-size:14px; margin-bottom:4px;">Promotions & Offers</div>
            <div style="font-size:12px; color:var(--text-muted);">Receive discounts and special offers.</div>
          </div>
          <label style="position:relative; display:inline-block; width:40px; height:24px;">
            <input type="checkbox" id="pref_promotions" style="opacity:0; width:0; height:0;">
            <span class="slider" style="position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background-color:var(--surface-light); transition:.4s; border-radius:34px;">
              <span class="knob" style="position:absolute; content:''; height:16px; width:16px; left:4px; bottom:4px; background-color:white; transition:.4s; border-radius:50%;"></span>
            </span>
          </label>
        </div>

        <div class="fi-row" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
          <div>
            <div style="font-weight:600; font-size:14px; margin-bottom:4px;">Email Receipts</div>
            <div style="font-size:12px; color:var(--text-muted);">Automatically email receipts after each trip.</div>
          </div>
          <label style="position:relative; display:inline-block; width:40px; height:24px;">
            <input type="checkbox" id="pref_email_receipts" style="opacity:0; width:0; height:0;">
            <span class="slider" style="position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background-color:var(--surface-light); transition:.4s; border-radius:34px;">
              <span class="knob" style="position:absolute; content:''; height:16px; width:16px; left:4px; bottom:4px; background-color:white; transition:.4s; border-radius:50%;"></span>
            </span>
          </label>
        </div>

        <button type="submit" class="btn-primary" id="btnSavePreferences">Save Preferences</button>
      </form>
    </div>
  </div>
</div>

<style>
input:checked + .slider { background-color: var(--primary); }
input:checked + .slider .knob { transform: translateX(16px); }
</style>
