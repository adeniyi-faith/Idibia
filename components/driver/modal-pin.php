<div class="modal" id="modal-delivery-pin">
  <div class="modal-content">
    <span class="modal-handle"></span>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <h3 style="font-size:20px;color:var(--text-primary);margin:0;">Delivery PIN</h3>
      <button class="modal-close-btn" onclick="closeModal('modal-delivery-pin')" aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div style="text-align:center;margin-bottom:28px;">
      <div class="pin-icon-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="32" height="32"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      </div>
      <p style="font-size:14px;color:var(--text-secondary);margin-top:12px;line-height:1.5;">Ask the customer for their <strong style="color:var(--text-primary);">4-digit delivery PIN</strong> and enter it below to complete the handoff.</p>
    </div>

    <div class="form-group">
      <input
        type="tel"
        id="delivery-pin-input"
        class="form-input pin-otp-input"
        placeholder="Enter PIN"
        maxlength="6"
        inputmode="numeric"
        pattern="[0-9]*"
        autocomplete="one-time-code"
      />
    </div>

    <button class="btn-primary" id="delivery-pin-submit-btn" onclick="submitDeliveryPin()" style="width:100%;margin-top:8px;">
      Complete Delivery
    </button>
  </div>
</div>
