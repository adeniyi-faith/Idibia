<div class="modal-overlay" id="modal-payment" onclick="closeModal(event,'payment')">
  <div class="modal-content">
    <div class="modal-header" style="display:flex; flex-direction:row; align-items:center; justify-content:space-between; text-align:left; margin-bottom:8px;">
      <h3 style="color:var(--text-primary);">Payment Methods</h3>
      <button class="icon-btn" aria-label="Close modal" onclick="closeModal(null, 'payment')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body" style="padding: 20px;">
      <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 20px;">We currently only support manual bank transfers. Please make payments to the account below when required.</p>

      <div style="background: var(--surface); border: 1px solid var(--surface-2); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
        <div style="margin-bottom: 15px;">
          <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">Bank Name</div>
          <div style="font-size: 16px; font-weight: 600; color: var(--text-primary);"><?php echo esc_html( $company_bank_name ); ?></div>
        </div>
        <div style="margin-bottom: 15px;">
          <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">Account Name</div>
          <div style="font-size: 16px; font-weight: 600; color: var(--text-primary);"><?php echo esc_html( $company_account_name ); ?></div>
        </div>
        <div>
          <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">Account Number</div>
          <div style="font-size: 20px; font-weight: 700; color: var(--primary); font-family: monospace; letter-spacing: 1px;" id="company_account_number"><?php echo esc_html( $company_account_number ); ?></div>
        </div>
      </div>

      <button class="btn-primary" onclick="copyAccountNumber()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="margin-right: 8px;">
          <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
          <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
        </svg>
        Copy Account Number
      </button>
    </div>
  </div>
</div>
