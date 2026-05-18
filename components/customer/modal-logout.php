<!-- ══════════ MODAL: LOGOUT CONFIRM ══════════ -->
<div class="modal-overlay" id="modal-logout" onclick="closeModal(event,'logout')">
  <div class="modal-content" style="max-height:auto" onclick="event.stopPropagation()">
    <div class="modal-handle"></div>
    <div style="text-align:center;padding:8px 0 24px">
      <div style="width:60px;height:60px;border-radius:50%;background:var(--danger-soft);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;border:2px solid rgba(232,72,74,0.2)">
        <svg viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="2" width="28" height="28"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </div>
      <h2 style="font-size:22px;color:var(--text-primary);margin-bottom:8px">Sign Out?</h2>
      <p style="font-size:14px;color:var(--text-muted);margin-bottom:24px">You'll need to sign in again to access your deliveries.</p>
      <button type="button" class="btn-primary" style="background:var(--danger);box-shadow:0 4px 16px rgba(232,72,74,0.3)" onclick="doLogout()">Yes, Sign Out</button>
      <button type="button" onclick="closeModal(null,'logout')" style="width:100%;height:48px;background:none;border:none;color:var(--text-muted);font-size:14px;font-weight:600;cursor:pointer;margin-top:10px;user-select:none;">Cancel</button>
    </div>
  </div>
</div>
