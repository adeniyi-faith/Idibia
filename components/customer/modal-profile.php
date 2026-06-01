<!-- ══════════ MODAL: PROFILE EDIT ══════════ -->
<div class="modal-overlay" id="modal-profile" onclick="closeModal(event,'profile')">
  <div class="modal-content" onclick="event.stopPropagation()">
    <div class="modal-handle"></div>
    <div class="modal-header" style="text-align: left; align-items: flex-start; padding-bottom: 0;">
      <h2 style="font-size: 20px;">Edit Profile</h2>
      <p style="margin-top: 4px;">Update your personal information below.</p>
    </div>
    <form id="profileEditForm" onsubmit="submitProfileEdit(event)" style="padding-top: 16px;">
      <div class="form-group">
        <label class="form-label" for="profileFullName">Full Name</label>
        <div class="input-wrapper">
          <input type="text" class="form-input" id="profileFullName" value="<?php echo esc_attr($customer_full_name); ?>" required>
        </div>
      </div>

      <div class="form-group" style="margin-top: 12px;">
        <label class="form-label" for="profilePhone">Phone Number</label>
        <div class="input-wrapper">
          <input type="tel" class="form-input" id="profilePhone" value="<?php echo esc_attr($customer_phone); ?>" required>
        </div>
      </div>
      <div class="form-group" style="margin-top: 12px;">
        <label class="form-label" for="profileEmail">Email Address</label>
        <div class="input-wrapper disabled">
          <input type="email" class="form-input" id="profileEmail" value="<?php echo esc_attr($customer_email); ?>" disabled style="background: var(--surface-1); color: var(--text-muted); cursor: not-allowed;">
        </div>
        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Email cannot be changed directly. Contact support if you need to update it.</div>
      </div>
      <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 12px;">
        <button type="submit" class="btn-primary" id="profileSaveBtn">Save Changes</button>
        <button type="button" class="global-btn ghost" style="width:100%; justify-content:center; border: 1px solid var(--surface-3); background: transparent;" onclick="closeModal(null, 'profile')">Cancel</button>
      </div>
    </form>
  </div>
</div>
