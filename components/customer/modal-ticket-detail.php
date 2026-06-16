<div class="modal" id="modal-ticket-detail">
  <div class="modal-content">
    <span class="modal-handle"></span>
    <div class="modal-header modal-header-row" style="margin-bottom:8px;">
      <button class="icon-btn" aria-label="Back to tickets" onclick="closeModal(null,'ticket-detail');fetchMyTickets();openModal('tickets')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      </button>
      <div style="flex:1;text-align:center;">
        <div id="ticket-detail-title" style="font-size:17px;font-weight:700;color:var(--text-primary);">Ticket</div>
        <div id="ticket-detail-status" style="margin-top:3px;display:flex;align-items:center;justify-content:center;gap:8px;"></div>
      </div>
      <button class="icon-btn" aria-label="Close" onclick="closeModal(null,'ticket-detail')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div id="ticket-thread-container" class="ticket-thread" style="padding:8px 0 4px;flex:1;overflow-y:auto;">
      <div style="text-align:center;padding:30px 0;color:var(--text-muted)">Loading…</div>
    </div>
    <div id="ticket-reply-box" style="padding-top:14px;border-top:1.5px solid var(--surface-2);display:none;flex-shrink:0;">
      <div class="fi-input" style="margin-bottom:10px;">
        <textarea id="ticket-reply-input" rows="2" placeholder="Write a reply…" style="min-height:64px;resize:none;"></textarea>
      </div>
      <button class="btn-primary" id="btnSendReply" onclick="submitTicketReply()">Send Reply</button>
    </div>
  </div>
</div>
