<!-- ══════════ OTP VERIFICATION ══════════ -->
<div class="screen" id="screen-otp">
  <div class="auth-form-panel" style="background:var(--surface)">
    <div class="auth-inner">
      <button type="button" onclick="goBack()" style="display:flex;align-items:center;gap:8px;background:none;border:none;color:var(--text-secondary);font-size:14px;font-weight:600;cursor:pointer;margin-bottom:32px;padding:0">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back
      </button>
      <div style="text-align:center;margin-bottom:32px">
        <div style="width:72px;height:72px;background:var(--info-soft);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;border:2px solid rgba(74,158,255,0.2)">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--info)" stroke-width="2" width="32" height="32"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <h2 class="auth-title" style="margin-bottom:8px">Check Your Email</h2>
        <p style="font-size:15px;color:var(--text-secondary);line-height:1.6">We emailed a 5-digit code to<br/><strong style="color:var(--text-primary)" id="otpEmailDisplay">your email address</strong></p>
      </div>
      <div class="otp-inputs">
        <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,0)">
        <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,1)">
        <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,2)">
        <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,3)">
        <input class="otp-input" type="tel" maxlength="1" oninput="otpNext(this,4)">
      </div>
      <div class="error-box" id="verifyError" style="margin-bottom:12px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span id="verifyErrorText"></span>
      </div>
      <button type="button" class="btn-primary" id="verifyBtn" onclick="doVerify()">
        Verify &amp; Get Started
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><polyline points="20 6 9 17 4 12"/></svg>
      </button>
      <div style="text-align:center;margin-top:20px;font-size:14px;color:var(--text-muted)">
        Didn't receive it? <button type="button" onclick="resendCode()" style="background:none;border:none;color:var(--navy);font-weight:700;cursor:pointer;font-size:14px;padding:4px">Resend Email</button>
      </div>
    </div>
  </div>
</div>
