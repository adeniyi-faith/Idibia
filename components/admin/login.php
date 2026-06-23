<div class="admin-login-body">
<form class="login-card" id="adminLoginForm" method="post" novalidate>

  <div class="login-brand">
    <div class="brand-badge">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </div>
  </div>

  <h1 class="login-title">Admin Login</h1>
  <p class="login-sub">Sign in to manage Idibia operations.</p>

  <div class="err" id="adminLoginError"></div>

  <div class="login-field">
    <label for="adminLogin">Email or Username</label>
    <input
      id="adminLogin"
      name="login"
      type="text"
      autocomplete="username"
      placeholder="Enter your email or username"
      required
    >
  </div>

  <div class="login-field">
    <label for="adminPassword">Password</label>
    <div class="pw-wrap">
      <input
        id="adminPassword"
        name="password"
        type="password"
        autocomplete="current-password"
        placeholder="Enter your password"
        required
      >
      <button type="button" class="eye-btn" id="togglePassword" aria-label="Show password">
        <svg class="eye-icon eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        <svg class="eye-icon eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
      </button>
    </div>
  </div>

  <div class="login-remember">
    <label class="remember-label">
      <input type="checkbox" name="remember_me" id="rememberMe" value="1">
      <span class="remember-check"></span>
      <span class="remember-text">Keep me signed in for 30 days</span>
    </label>
  </div>

  <button class="login-btn" id="adminLoginBtn">Sign In</button>

</form>
</div>
