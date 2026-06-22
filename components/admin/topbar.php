<!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-left">
      <button class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Open Menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <div class="topbar-title" id="topbar-title">Platform Overview</div>
        <div class="topbar-sub" id="topbar-sub">Live · <?php echo esc_html( date_i18n( 'D M j, Y' ) ); ?></div>
      </div>
    </div>
    <div class="topbar-actions">
      <input class="topbar-search" placeholder="Search…" oninput="handleSearch(this.value)">
      <button class="topbar-btn" onclick="toggleNotif()" title="Notifications" style="position:relative">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span id="notifBadge" style="display:none;position:absolute;top:-4px;right:-4px;background:var(--danger);color:#fff;border-radius:10px;padding:1px 5px;font-size:10px;font-weight:700;min-width:16px;text-align:center;line-height:16px"></span>
      </button>
      <button class="topbar-btn disabled-action" title="Export reports are not connected yet" aria-disabled="true" onclick="showUnavailableFeature('Report export', 'Exports need a backend report endpoint before files can be generated.')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      </button>
    </div>
  </div>