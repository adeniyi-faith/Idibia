<!-- SAVED ADDRESSES MANAGER -->
<div class="modal-overlay" id="modal-saved-addresses" onclick="closeModal(event,'saved-addresses')">
  <div class="modal-content">
    <div class="modal-header saved-addr-header">
      <h3>Saved Addresses</h3>
      <button class="icon-btn" aria-label="Close modal" onclick="closeModal(null,'saved-addresses')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body" style="padding:20px;">
      <p style="font-size:12.5px;color:var(--text-muted);margin:0 0 14px;">Manage the places you reuse. They appear as quick-picks when you tap a pickup or drop-off field.</p>

      <div id="savedAddressesList"></div>

      <!-- Add / edit form -->
      <div class="saved-addr-form">
        <div id="savedAddrFormTitle" class="saved-addr-form-title">Add a new place</div>
        <input id="savedAddrLabel" class="saved-addr-input" type="text" placeholder="Label (e.g. Home, Work)" maxlength="30" autocomplete="off">
        <div style="position:relative;margin-top:10px;">
          <input id="savedAddrInput" class="saved-addr-input" type="text" placeholder="Search address…" autocomplete="off" style="margin-top:0;">
          <div id="savedAddrSuggestions" class="photon-dropdown"></div>
        </div>
        <div style="display:flex;gap:10px;margin-top:14px;">
          <button type="button" id="savedAddrCancelEdit" onclick="resetSavedAddressForm()" style="display:none;flex:1;align-items:center;justify-content:center;padding:13px;background:var(--surface);border:1.5px solid var(--surface-2);border-radius:var(--radius-sm);font-size:14px;font-weight:600;color:var(--text-secondary);cursor:pointer;">Cancel</button>
          <button type="button" id="savedAddrSubmitBtn" onclick="saveSavedAddressFromForm()" style="flex:2;padding:13px;background:var(--primary);border:none;border-radius:var(--radius-sm);font-size:14px;font-weight:700;color:var(--white);cursor:pointer;">Add Address</button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.saved-addr-header { display:flex !important; flex-direction:row !important; align-items:center !important; justify-content:space-between !important; text-align:left !important; margin-bottom:8px !important; }
.saved-addr-header h3 { font-size:17px; color:var(--text-primary); }
.saved-addr-item { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--surface-2); }
.saved-addr-item:first-child { padding-top:0; }
.saved-addr-icon { width:34px; height:34px; flex-shrink:0; border-radius:10px; background:var(--surface); display:flex; align-items:center; justify-content:center; color:var(--primary); }
.saved-addr-text { flex:1; min-width:0; }
.saved-addr-label { font-size:14px; font-weight:700; color:var(--text-primary); }
.saved-addr-addr { font-size:12px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.saved-addr-action { width:32px; height:32px; flex-shrink:0; border:none; background:var(--surface); border-radius:8px; color:var(--text-secondary); cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background 0.15s,color 0.15s; }
.saved-addr-action:hover { background:var(--surface-2); }
.saved-addr-action.danger { color:var(--danger); }
.saved-addr-form { margin-top:18px; padding-top:18px; border-top:1.5px solid var(--surface-2); }
.saved-addr-form-title { font-size:13px; font-weight:700; color:var(--text-secondary); margin-bottom:10px; }
.saved-addr-input { width:100%; border:1.5px solid var(--surface-2); border-radius:var(--radius-sm); padding:11px 14px; font-size:15px; color:var(--text-primary); outline:none; box-sizing:border-box; -webkit-appearance:none; }
.saved-addr-input:focus { border-color:var(--primary); }
.photon-saved-row { padding:10px 14px; font-size:13px; color:var(--text-primary); cursor:pointer; display:flex; align-items:center; gap:8px; border-bottom:1px solid var(--surface-2); }
.photon-saved-row svg { color:var(--primary); flex-shrink:0; }
.photon-saved-row:hover { background:var(--surface); }
</style>
