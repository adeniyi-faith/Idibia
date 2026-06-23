<!-- ══════════ DRIVER WELCOME ONBOARDING ══════════ -->
<div class="screen <?php echo (!$driver_initial_context['logged_in']) ? 'active' : ''; ?>" id="screen-driver-onboarding">
  <div class="donb-bg">
    <div class="donb-blob donb-blob-1"></div>
    <div class="donb-blob donb-blob-2"></div>
    <div class="donb-blob donb-blob-3"></div>
  </div>
  <button class="donb-skip" onclick="driverOnbSkip()">Skip</button>
  <div class="donb-slides-wrap">
    <div class="donb-slides" id="driverOnbSlides">

      <!-- Slide 1: Earn -->
      <div class="donb-slide">
        <div class="donb-icon-ring donb-c-gold">
          <div class="donb-ring-anim"></div>
          <div class="donb-ring-anim-2"></div>
          <div class="donb-icon-ring-inner">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <line x1="12" y1="1" x2="12" y2="23"/>
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
          </div>
        </div>
        <h2>Earn on <span style="color:var(--gold-dark)">Your Terms</span></h2>
        <p>Set your own hours and make money on your schedule. Drive when you want, rest when you need.</p>
      </div>

      <!-- Slide 2: Payouts -->
      <div class="donb-slide">
        <div class="donb-icon-ring donb-c-info">
          <div class="donb-ring-anim"></div>
          <div class="donb-ring-anim-2"></div>
          <div class="donb-icon-ring-inner">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="2" y="5" width="20" height="14" rx="2"/>
              <line x1="2" y1="10" x2="22" y2="10"/>
              <line x1="6" y1="15" x2="10" y2="15"/>
            </svg>
          </div>
        </div>
        <h2>Fast, Direct <span style="color:var(--info)">Payouts</span></h2>
        <p>Earnings go straight to your bank account. No middlemen, no delays — just your money when you need it.</p>
      </div>

      <!-- Slide 3: Network -->
      <div class="donb-slide">
        <div class="donb-icon-ring donb-c-success">
          <div class="donb-ring-anim"></div>
          <div class="donb-ring-anim-2"></div>
          <div class="donb-icon-ring-inner">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
        </div>
        <h2>Grow With <span style="color:var(--success)">Idibia</span></h2>
        <p>Access live delivery requests, bonus campaigns and a growing customer base right across the city.</p>
      </div>

    </div>
  </div>

  <div class="donb-bottom">
    <div class="donb-dots" id="driverOnbDots">
      <div class="donb-dot active"></div>
      <div class="donb-dot"></div>
      <div class="donb-dot"></div>
    </div>
    <button class="donb-cta-btn" id="driverOnbBtn" onclick="driverOnbNext()">
      <span id="driverOnbBtnText">Next</span>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20">
        <path d="M5 12h14M12 5l7 7-7 7"/>
      </svg>
    </button>
  </div>
</div>
