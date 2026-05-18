<!-- ══════════ ONBOARDING ══════════ -->
<div class="screen" id="screen-onboarding">
  <div class="onb-bg">
    <div class="onb-blob onb-blob-1"></div>
    <div class="onb-blob onb-blob-2"></div>
    <div class="onb-blob onb-blob-3"></div>
  </div>
  <button class="onb-skip" onclick="goTo('screen-auth')">Skip</button>
  <div class="onb-slides-wrap">
    <div class="onb-slides" id="onbSlides">
      <!-- Slide 1 -->
      <div class="onb-slide">
        <div class="onb-icon-ring c-gold">
          <div class="ring-anim"></div>
          <div class="ring-anim-2"></div>
          <div class="onb-icon-ring-inner">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
              <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
            </svg>
          </div>
        </div>
        <h2>Deliver <span style="color:var(--gold-dark)">Faster.</span><br/>Deliver <span style="color:var(--gold-dark)">Better.</span></h2>
        <p>The premier logistics network connecting your items with <strong>verified local riders</strong> instantly across the city.</p>
      </div>
      <!-- Slide 2 -->
      <div class="onb-slide">
        <div class="onb-icon-ring c-info">
          <div class="ring-anim"></div>
          <div class="ring-anim-2"></div>
          <div class="onb-icon-ring-inner">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
          </div>
        </div>
        <h2>Track Every <span style="color:var(--info)">Package</span> Live</h2>
        <p>End-to-end real-time GPS tracking with live driver location and <strong>instant push notifications</strong> at every step.</p>
      </div>
      <!-- Slide 3 -->
      <div class="onb-slide">
        <div class="onb-icon-ring c-success">
          <div class="ring-anim"></div>
          <div class="ring-anim-2"></div>
          <div class="onb-icon-ring-inner">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
              <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
            </svg>
          </div>
        </div>
        <h2>Thousands of <span style="color:var(--success)">Verified</span> Riders</h2>
        <p>Every rider is KYC-verified with full background checks, guaranteeing <strong>safe same-day delivery.</strong></p>
      </div>
    </div>
  </div>
  <div class="onb-bottom">
    <div class="onb-dots" id="onbDots">
      <div class="onb-dot active"></div>
      <div class="onb-dot"></div>
      <div class="onb-dot"></div>
    </div>
    <button class="find-btn" id="onbBtn" onclick="onbNext()">
      <span id="onbBtnText">Next</span>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>
  </div>
</div>
