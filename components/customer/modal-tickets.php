<div class="modal" id="modal-tickets">
  <div class="modal-content">
    <span class="modal-handle"></span>
    <div class="modal-header modal-header-row" style="margin-bottom:12px;">
      <h3>My Support Tickets</h3>
      <button class="icon-btn" aria-label="Close" onclick="closeModal(null,'tickets')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div style="padding:0 0 4px;">
      <div id="tickets-list-container">
        <div style="text-align:center;padding:40px 20px;color:var(--text-muted)">Loading…</div>
      </div>
      <button class="btn-primary" style="margin-top:8px;" onclick="closeModal(null,'tickets');openModal('support')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15" style="margin-right:6px;vertical-align:-2px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Open New Ticket
      </button>
    </div>
  </div>
</div>
