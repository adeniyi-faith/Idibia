<!-- ══════════ AUTH SCREEN ══════════ -->
<div class="screen" id="screen-auth">
  <div class="auth-brand">
    <div>
      <div class="brand-logo">
        <div class="brand-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="22" height="22"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        </div>
        Idibia
      </div>
      <div class="brand-headline" style="margin-top:52px">Deliver<br/><span>Faster.</span><br/>Deliver <span>Better.</span></div>
    </div>
    <div>
      <div class="brand-features">
        <div class="brand-feat"><div class="brand-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div><span>End-to-end live package tracking</span></div>
        <div class="brand-feat"><div class="brand-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div><span>Thousands of KYC-verified riders</span></div>
        <div class="brand-feat"><div class="brand-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><span>Guaranteed same-day delivery</span></div>
        <div class="brand-feat"><div class="brand-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div><span>Secure payment & instant receipts</span></div>
      </div>
      <div class="brand-copy">© 2026 Idibia Logistics Inc. All rights reserved.</div>
    </div>
  </div>
  <div class="auth-form-panel">
    <div class="auth-inner">
      <div class="auth-mobile-logo">
        <div class="brand-icon" style="width:40px;height:40px;border-radius:11px">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        </div>
        Idibia
      </div>
      <div class="error-box" id="authError">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span id="authErrorText"></span>
      </div>

      <!-- LOGIN VIEW -->
      <div id="loginView">
        <h2 class="auth-title">Welcome back 👋</h2>
        <p class="auth-sub">Sign in to manage your deliveries</p>
        <div class="form-group">
          <label class="form-label">Email or Phone Number</label>
          <div class="form-input-wrap">
            <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <input class="form-input" type="text" id="loginEmail" placeholder="you@example.com or 08012345678" autocomplete="username">
          </div>
        </div>
        <div class="form-group">
          <div class="form-row">
            <label class="form-label" style="margin-bottom:0">Password</label>
            <button type="button" class="form-forgot" onclick="showUnavailableFeature('Password reset', 'Password reset is not connected yet. Contact support or an admin to recover access.')">Forgot password?</button>
          </div>
          <div class="form-input-wrap">
            <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input class="form-input has-pw-toggle" type="password" id="loginPass" placeholder="Enter your password" autocomplete="current-password">
            <button type="button" class="pw-toggle" onclick="togglePassword(this)" title="Toggle password visibility">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <button type="button" class="btn-primary" onclick="doLogin()" style="margin-top:4px">
          Sign In — Let's Go
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
        <div class="auth-divider"><span>or continue with</span></div>
        <button type="button" class="btn-outline" aria-disabled="true" onclick="showUnavailableFeature('Google sign-in', 'Google OAuth is not configured yet. Use email or phone sign-in for now.')">
          <svg viewBox="0 0 24 24" width="18" height="18"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
          Continue with Google
        </button>
        <button type="button" class="btn-outline" aria-disabled="true" onclick="showUnavailableFeature('Apple sign-in', 'Apple OAuth is not configured yet. Use email or phone sign-in for now.')" style="margin-top:8px">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.7 9.05 7.4c1.39.06 2.35.76 3.16.8 1.22-.27 2.38-1.01 3.68-.9 1.55.14 2.72.75 3.48 1.9-3.17 1.93-2.4 6.06.68 7.24-.63 1.73-1.43 3.43-2.97 4.84zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>
          Continue with Apple
        </button>
        <div class="auth-toggle">Don't have an account? <button type="button" onclick="switchAuth(false)">Create account →</button></div>
      </div>

      <!-- REGISTER VIEW -->
      <div id="registerView" style="display:none">
        <h2 class="auth-title">Join Idibia 🚀</h2>
        <p class="auth-sub">Create your account in 30 seconds</p>
        <div class="error-box" id="regError">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span id="regErrorText"></span>
        </div>
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <div class="form-input-wrap">
            <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input class="form-input" type="text" id="regName" placeholder="John Okafor" autocomplete="name">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <div class="form-input-wrap">
            <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.21h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 9a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <input class="form-input" type="tel" id="regPhone" placeholder="08012345678" autocomplete="tel">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="form-input-wrap">
            <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <input class="form-input" type="email" id="regEmail" placeholder="you@example.com" autocomplete="email">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="form-input-wrap">
            <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input class="form-input has-pw-toggle" type="password" id="regPassword" placeholder="Min. 8 characters" autocomplete="new-password">
            <button type="button" class="pw-toggle" onclick="togglePassword(this)" title="Toggle password visibility">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <div class="form-check">
          <input type="checkbox" id="termsCheck" checked>
          <label for="termsCheck">I agree to Idibia's <a href="#">Terms of Service</a>, <a href="#">Privacy Policy</a>, and <a href="#">Location Data Policy</a></label>
        </div>
        <button type="button" class="btn-primary" style="margin-top:18px" id="regBtn" onclick="doRegister()">
          Create Account &amp; Verify
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
        <div class="auth-toggle">Already have an account? <button type="button" onclick="switchAuth(true)">Sign in →</button></div>
      </div>
    </div>
  </div>
</div>
